// MV3 service worker — Module 2: detection engine.
//
// Observation only. MV3 removed blocking webRequest for extensions like this, so we
// never modify or cancel requests — we only watch them to learn what media a tab loads.

import { classify } from './lib/classify';
import { parseDash, parseHls, estimateBytes } from './lib/manifest-parse';
import { extFor, sanitizeFilename, urlFilename } from './lib/name';
import {
  addHistory,
  applyTemplate,
  getSettings,
  withSubfolder,
} from './lib/settings';
import { CANCELED_KEY, JOBS_KEY, type HlsJob } from './lib/download-types';
import type {
  CapturedHeaders,
  ContentMessage,
  NetworkDetection,
  PageMeta,
  TabState,
} from './lib/types';

// ---- Per-tab state, persisted in session storage (survives SW restart, clears on browser close).

const KEY = (tabId: number) => `tab:${tabId}`;
const EMPTY: TabState = { network: [], elements: [], drm: false };

async function getState(tabId: number): Promise<TabState> {
  const res = await chrome.storage.session.get(KEY(tabId));
  return (res[KEY(tabId)] as TabState) ?? { ...EMPTY, network: [], elements: [] };
}

async function setState(tabId: number, state: TabState): Promise<void> {
  await chrome.storage.session.set({ [KEY(tabId)]: state });
  await updateBadge(tabId, state);
}

async function clearTab(tabId: number): Promise<void> {
  await chrome.storage.session.remove(KEY(tabId));
  await updateBadge(tabId, EMPTY);
}

// ---- Badge = count of downloadable items for the tab (network media + real element srcs).

function downloadableCount(state: TabState): number {
  if (state.drm) return 0; // protected pages advertise nothing downloadable
  const urls = new Set(state.network.map((n) => n.url));
  for (const el of state.elements) {
    if (el.src && !el.isBlob) urls.add(el.src);
  }
  return urls.size;
}

async function updateBadge(tabId: number, state: TabState): Promise<void> {
  const settings = await getSettings();
  const n = downloadableCount(state);
  try {
    if (!settings.badge) {
      await chrome.action.setBadgeText({ tabId, text: '' });
      return;
    }
    await chrome.action.setBadgeBackgroundColor({ tabId, color: state.drm ? '#9ca3af' : '#4f46e5' });
    await chrome.action.setBadgeText({ tabId, text: n > 0 ? String(n) : '' });
  } catch {
    // tab may be gone — ignore
  }
}

// ---- Header capture: remembered per-request-URL until the response arrives.

const pendingHeaders = new Map<string, CapturedHeaders>();

function pick(headers: chrome.webRequest.HttpHeader[] | undefined, name: string): string | undefined {
  return headers?.find((h) => h.name.toLowerCase() === name)?.value;
}

chrome.webRequest.onSendHeaders.addListener(
  (details) => {
    if (details.tabId < 0) return;
    const h = details.requestHeaders;
    pendingHeaders.set(details.requestId, {
      referer: pick(h, 'referer'),
      userAgent: pick(h, 'user-agent'),
      cookie: pick(h, 'cookie'),
      authorization: pick(h, 'authorization'),
      origin: pick(h, 'origin'),
    });
  },
  { urls: ['<all_urls>'] },
  ['requestHeaders', 'extraHeaders'],
);

// ---- Response: classify, capture Content-Type/Length, store the detection.

// Hosts/paths that serve protected adaptive chunks with no downloadable single
// URL (YouTube & co). We flag the tab rather than listing the useless chunks.
const ADAPTIVE_JUNK = /(\.googlevideo\.com\/|\/videoplayback\?|\.c\.youtube\.com\/)/i;
const MIN_DIRECT_BYTES = 50 * 1024; // below this a "DIRECT" hit is a chunk/handshake, not the media

chrome.webRequest.onHeadersReceived.addListener(
  (details) => {
    const requestId = details.requestId;
    const headers = pendingHeaders.get(requestId) ?? {};
    pendingHeaders.delete(requestId);
    if (details.tabId < 0) return;

    // Protected adaptive streaming (YouTube): don't list chunks, flag the tab.
    if (ADAPTIVE_JUNK.test(details.url)) {
      void markAdaptive(details.tabId, /googlevideo|youtube/i.test(details.url) ? 'YouTube' : 'this site');
      return;
    }

    const contentType = pick(details.responseHeaders, 'content-type');
    const kind = classify(details.url, contentType);
    if (kind === 'IGNORE' || kind === 'SEGMENT') return; // segments infer a stream, not listed

    const lenRaw = pick(details.responseHeaders, 'content-length');
    const contentLength = lenRaw ? Number(lenRaw) : undefined;

    // Drop tiny "DIRECT" responses — these are adaptive chunks / handshakes /
    // tracking pixels, not a real downloadable file. Streams are kept regardless.
    if (kind === 'DIRECT' && contentLength != null && contentLength < MIN_DIRECT_BYTES) return;

    void recordNetwork({
      id: details.url,
      url: details.url,
      kind,
      contentType,
      contentLength: Number.isFinite(contentLength) ? contentLength : undefined,
      pageUrl: details.initiator,
      headers,
      tabId: details.tabId,
      firstSeen: Date.now(),
    });
  },
  { urls: ['<all_urls>'] },
  ['responseHeaders', 'extraHeaders'],
);

const MAX_DETECTIONS = 100; // robustness: cap pages that emit hundreds of media requests

async function markAdaptive(tabId: number, site: string): Promise<void> {
  const state = await getState(tabId);
  if (state.adaptive) return; // already flagged
  state.adaptive = true;
  state.adaptiveSite = site;
  await setState(tabId, state);
}

async function recordNetwork(det: NetworkDetection): Promise<void> {
  const state = await getState(det.tabId);
  if (state.network.some((n) => n.url === det.url)) return; // dedupe by URL
  if (state.network.length >= MAX_DETECTIONS) return;       // stop growing unbounded
  state.network = [...state.network, det];
  await setState(det.tabId, state);
  void enrich(det.tabId, det.url); // async — updates the record when done
}

// ---- Module 3: metadata enrichment (size, qualities, duration, title) ------

const enriching = new Set<string>();
const enrichCache = new Map<string, Partial<NetworkDetection>>();

async function enrich(tabId: number, url: string): Promise<void> {
  if (enriching.has(url)) return;
  enriching.add(url);
  try {
    const cached = enrichCache.get(url);
    const patch = cached ?? (await computeEnrichment(tabId, url));
    enrichCache.set(url, patch);

    const state = await getState(tabId);
    const idx = state.network.findIndex((n) => n.url === url);
    if (idx < 0) return;
    state.network[idx] = { ...state.network[idx], ...patch, enriched: true };
    await setState(tabId, state);
  } catch (e) {
    console.warn('[JIM] enrich failed', url, e);
  } finally {
    enriching.delete(url);
  }
}

async function computeEnrichment(
  tabId: number,
  url: string,
): Promise<Partial<NetworkDetection>> {
  const state = await getState(tabId);
  const det = state.network.find((n) => n.url === url);
  if (!det) return {};

  const meta = state.meta;
  const patch: Partial<NetworkDetection> = {};

  // Title: prefer page metadata, fall back to the URL filename.
  const ext = extFor(url, det.kind);
  patch.title = sanitizeFilename(meta?.title || urlFilename(url), ext);
  if (meta?.durationSec) patch.durationSec = meta.durationSec;

  if (det.kind === 'DIRECT') {
    patch.sizeBytes = det.contentLength ?? (await headSize(url));
    patch.sizeEstimated = false;
  } else if (det.kind === 'HLS') {
    Object.assign(patch, await enrichHls(url, meta));
  } else if (det.kind === 'DASH') {
    Object.assign(patch, await enrichDash(url, meta));
  }

  return patch;
}

// Exact size for direct files: HEAD, then Range 0-0 fallback.
async function headSize(url: string): Promise<number | undefined> {
  try {
    const r = await fetch(url, { method: 'HEAD', credentials: 'include' });
    const len = r.headers.get('content-length');
    if (len) return Number(len);
  } catch {
    /* fall through */
  }
  try {
    const r = await fetch(url, {
      headers: { Range: 'bytes=0-0' },
      credentials: 'include',
    });
    const cr = r.headers.get('content-range'); // e.g. "bytes 0-0/12345"
    const total = cr?.split('/')?.[1];
    if (total && total !== '*') return Number(total);
  } catch {
    /* unknown */
  }
  return undefined;
}

async function enrichHls(
  url: string,
  meta?: PageMeta,
): Promise<Partial<NetworkDetection>> {
  const text = await fetchText(url);
  if (!text) return {};
  const master = parseHls(text, url);

  if (master.isMaster && master.variants.length) {
    const top = master.variants[0];
    // Duration: page meta, else fetch the top media playlist and sum EXTINF.
    let duration = meta?.durationSec;
    if (!duration && top.url) {
      const media = await fetchText(top.url);
      if (media) duration = parseHls(media, top.url).durationSec;
    }
    const size = estimateBytes(top.bandwidth, duration);
    return {
      variants: master.variants,
      width: top.width,
      height: top.height,
      durationSec: duration,
      sizeBytes: size,
      sizeEstimated: size != null,
    };
  }

  // Media playlist directly: single quality, duration known.
  return {
    durationSec: master.durationSec,
    sizeEstimated: true,
  };
}

async function enrichDash(
  url: string,
  meta?: PageMeta,
): Promise<Partial<NetworkDetection>> {
  const xml = await fetchText(url);
  if (!xml) return {};
  const dash = parseDash(xml);
  const top = dash.variants.find((v) => v.height) ?? dash.variants[0];
  const duration = meta?.durationSec ?? dash.durationSec;
  const size = estimateBytes(top?.bandwidth, duration);
  return {
    variants: dash.variants,
    width: top?.width ?? null,
    height: top?.height ?? null,
    durationSec: duration,
    sizeBytes: size,
    sizeEstimated: size != null,
  };
}

async function fetchText(url: string): Promise<string | undefined> {
  try {
    const r = await fetch(url, { credentials: 'include' });
    if (!r.ok) return undefined;
    return await r.text();
  } catch {
    return undefined;
  }
}

// ---- Content-script reports (elements + DRM).

chrome.runtime.onMessage.addListener((msg: ContentMessage, sender, sendResponse) => {
  const tabId = sender.tab?.id;
  if (tabId == null) return;

  (async () => {
    const state = await getState(tabId);
    if (msg.type === 'ELEMENTS') {
      state.elements = msg.elements;
    } else if (msg.type === 'DRM_DETECTED') {
      state.drm = true;
      state.drmKeySystem = msg.keySystem;
    } else if (msg.type === 'PAGE_META') {
      state.meta = msg.meta;
    }
    await setState(tabId, state);
    sendResponse({ ok: true });

    // If page metadata just arrived, re-title any detections that used the URL fallback.
    if (msg.type === 'PAGE_META') {
      for (const n of state.network) {
        enrichCache.delete(n.url);
        void enrich(tabId, n.url);
      }
    }
  })();

  return true; // async response
});

// ---- Downloads (Module 5: direct + HLS engine). ----------------------------

interface DownloadRequest {
  type: 'DOWNLOAD';
  url: string;
  kind: NetworkDetection['kind'];
  title?: string;
  format: 'video' | 'audio';
  variantIndex?: number;
}
interface CancelRequest {
  type: 'CANCEL_DOWNLOAD';
  id: string;
}

chrome.runtime.onMessage.addListener(
  (msg: DownloadRequest | CancelRequest, _sender, sendResponse) => {
    if (msg?.type === 'CANCEL_DOWNLOAD') {
      void cancelJob(msg.id);
      sendResponse({ ok: true });
      return true;
    }
    if (msg?.type !== 'DOWNLOAD') return; // handled by the content-message listener
    void startDownload(msg).then(sendResponse);
    return true;
  },
);

async function startDownload(
  msg: DownloadRequest,
): Promise<{ ok: boolean; message?: string }> {
  if (msg.format === 'audio') {
    return {
      ok: false,
      message: 'Audio-only (MP3) extraction needs the Module 7 native host (yt-dlp/ffmpeg).',
    };
  }
  if (msg.kind === 'DASH') {
    return {
      ok: false,
      message: 'DASH needs separate video+audio muxing — use the Module 7 native host.',
    };
  }

  try {
    const settings = await getSettings();
    const state = await getState((await activeTabId()) ?? -1);
    const det = state.network.find((n) => n.url === msg.url);
    const headers: CapturedHeaders = det?.headers ?? {};

    const resolution = det?.height ? `${det.height}p` : undefined;
    const ext = extFor(msg.url, msg.kind);
    const baseTitle = sanitizeFilename(msg.title || urlFilename(msg.url), '').replace(ext, '');
    const templated = applyTemplate(settings.filenameTemplate, { title: baseTitle, resolution }, ext);
    const filename = withSubfolder(settings.subfolder, templated);

    if (msg.kind === 'DIRECT') {
      await addHeaderRule(msg.url, headers, det?.pageUrl);
      await chrome.downloads.download({ url: msg.url, filename });
      await addHistory({
        title: baseTitle,
        filename,
        url: msg.url,
        kind: msg.kind,
        sizeBytes: det?.sizeBytes ?? det?.contentLength,
        when: Date.now(),
      });
      return { ok: true };
    }

    // HLS -> offscreen engine. Enqueue the job in storage BEFORE creating the
    // offscreen document, so the document picks it up on load (no message race).
    const id = `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
    await registerDownload(id, templated, msg.kind);
    await addHeaderRule(msg.url, headers, det?.pageUrl);
    await enqueueJob({
      id,
      playlistUrl: msg.url,
      variantIndex: msg.variantIndex,
      headers,
      filename,
      title: baseTitle,
      concurrency: settings.segmentConcurrency,
      maxSegments: settings.maxSegments,
    });
    try {
      await ensureOffscreen();
    } catch (e) {
      await patchDownloadRow(id, {
        state: 'error',
        error: `Could not start worker: ${e instanceof Error ? e.message : String(e)}`,
      });
      return { ok: false, message: 'Could not start the download worker.' };
    }
    watchdog(id);
    return { ok: true };
  } catch (e) {
    return { ok: false, message: String(e) };
  }
}

// ---- Right-click context menu on <video> -----------------------------------

chrome.runtime.onInstalled.addListener(() => {
  chrome.contextMenus.create({
    id: 'jim-download',
    title: 'Download this video with JIM',
    contexts: ['video'],
  });
});

chrome.contextMenus.onClicked.addListener((info) => {
  const src = info.srcUrl;
  if (!src) return;
  if (src.startsWith('blob:')) {
    // A blob src has no downloadable URL — the network layer holds the real
    // stream. Point the user at the popup which lists it.
    return;
  }
  const kind = classify(src);
  void startDownload({
    type: 'DOWNLOAD',
    url: src,
    kind: kind === 'IGNORE' || kind === 'SEGMENT' ? 'DIRECT' : kind,
    format: 'video',
  });
});

async function activeTabId(): Promise<number | undefined> {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  return tab?.id ?? undefined;
}

async function patchDownloadRow(
  id: string,
  patch: Partial<{ state: string; error: string }>,
): Promise<void> {
  const store = await chrome.storage.session.get('downloads');
  const list = store.downloads ?? [];
  const idx = list.findIndex((d: { id: string }) => d.id === id);
  if (idx >= 0) {
    list[idx] = { ...list[idx], ...patch };
    await chrome.storage.session.set({ downloads: list });
  }
}

// If a job is still 'preparing' after a grace period the offscreen worker never
// picked it up — retry ensuring the document, then surface an error if still stuck.
function watchdog(id: string): void {
  setTimeout(async () => {
    const store = await chrome.storage.session.get('downloads');
    const row = (store.downloads ?? []).find((d: { id: string }) => d.id === id);
    if (row && row.state === 'preparing') {
      try {
        await ensureOffscreen();
      } catch {
        /* handled below */
      }
    }
  }, 6000);
  setTimeout(async () => {
    const store = await chrome.storage.session.get('downloads');
    const row = (store.downloads ?? []).find((d: { id: string }) => d.id === id);
    if (row && row.state === 'preparing') {
      await patchDownloadRow(id, {
        state: 'error',
        error: 'Worker did not start. Reload the extension, then try again.',
      });
    }
  }, 25000);
}

async function enqueueJob(job: HlsJob): Promise<void> {
  const store = await chrome.storage.session.get(JOBS_KEY);
  const jobs: HlsJob[] = store[JOBS_KEY] ?? [];
  jobs.push(job);
  await chrome.storage.session.set({ [JOBS_KEY]: jobs });
}

async function cancelJob(id: string): Promise<void> {
  const store = await chrome.storage.session.get(CANCELED_KEY);
  const ids: string[] = store[CANCELED_KEY] ?? [];
  if (!ids.includes(id)) ids.push(id);
  await chrome.storage.session.set({ [CANCELED_KEY]: ids });
}

async function registerDownload(id: string, filename: string, kind: string): Promise<void> {
  const store = await chrome.storage.session.get('downloads');
  const list = store.downloads ?? [];
  list.unshift({
    id,
    title: filename,
    filename,
    kind,
    state: 'preparing',
    segDone: 0,
    segTotal: 0,
    received: 0,
    percent: 0,
    startedAt: Date.now(),
  });
  await chrome.storage.session.set({ downloads: list });
}

// ---- Offscreen document lifecycle ------------------------------------------

let creatingOffscreen: Promise<void> | null = null;
async function ensureOffscreen(): Promise<void> {
  const has = await chrome.offscreen.hasDocument();
  if (has) return;
  if (!creatingOffscreen) {
    creatingOffscreen = chrome.offscreen
      .createDocument({
        url: 'src/offscreen.html',
        reasons: [chrome.offscreen.Reason.BLOBS],
        justification: 'Assemble HLS segments into a downloadable file.',
      })
      .finally(() => (creatingOffscreen = null));
  }
  await creatingOffscreen;
}

// ---- Header replay via declarativeNetRequest (kills 403s) -------------------

let ruleId = 1000;
async function addHeaderRule(
  url: string,
  headers: CapturedHeaders,
  pageUrl?: string,
): Promise<void> {
  const SET = chrome.declarativeNetRequest.HeaderOperation.SET;
  const requestHeaders: chrome.declarativeNetRequest.ModifyHeaderInfo[] = [];
  const referer = headers.referer || pageUrl;
  if (referer) requestHeaders.push({ header: 'referer', operation: SET, value: referer });
  if (headers.userAgent)
    requestHeaders.push({ header: 'user-agent', operation: SET, value: headers.userAgent });
  if (headers.cookie)
    requestHeaders.push({ header: 'cookie', operation: SET, value: headers.cookie });
  if (headers.origin)
    requestHeaders.push({ header: 'origin', operation: SET, value: headers.origin });
  if (requestHeaders.length === 0) return;

  let host = '*';
  try {
    host = new URL(url).hostname;
  } catch {
    /* keep wildcard */
  }
  const id = ++ruleId;
  await chrome.declarativeNetRequest.updateSessionRules({
    addRules: [
      {
        id,
        priority: 1,
        action: {
          type: chrome.declarativeNetRequest.RuleActionType.MODIFY_HEADERS,
          requestHeaders,
        },
        condition: {
          urlFilter: `||${host}`,
          resourceTypes: [
            chrome.declarativeNetRequest.ResourceType.XMLHTTPREQUEST,
            chrome.declarativeNetRequest.ResourceType.MEDIA,
            chrome.declarativeNetRequest.ResourceType.OTHER,
          ],
        },
      },
    ],
  });
  // Rules are best-effort and cheap; drop this one after 10 min.
  setTimeout(() => {
    void chrome.declarativeNetRequest.updateSessionRules({ removeRuleIds: [id] });
  }, 600_000);
}

// ---- Lifecycle: clear on navigation and tab close.

chrome.webNavigation?.onCommitted.addListener((details) => {
  if (details.frameId === 0) void clearTab(details.tabId);
});

// Fallback if webNavigation permission isn't present: clear on main-frame load.
chrome.tabs.onUpdated.addListener((tabId, info) => {
  if (info.status === 'loading' && info.url) void clearTab(tabId);
});

chrome.tabs.onRemoved.addListener((tabId) => void clearTab(tabId));

chrome.runtime.onInstalled.addListener((d) => {
  console.log('[JIM Video Catcher] installed:', d.reason);
});

// If the service worker restarted while jobs were queued, re-create the offscreen
// document so those downloads resume instead of hanging at 'preparing'.
async function recoverPendingJobs(): Promise<void> {
  const store = await chrome.storage.session.get(JOBS_KEY);
  const jobs: HlsJob[] = store[JOBS_KEY] ?? [];
  if (jobs.length > 0) {
    try {
      await ensureOffscreen();
    } catch {
      /* nothing more we can do here */
    }
  }
}
void recoverPendingJobs();

export {};
