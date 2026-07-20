// MV3 service worker — Module 2: detection engine.
//
// Observation only. MV3 removed blocking webRequest for extensions like this, so we
// never modify or cancel requests — we only watch them to learn what media a tab loads.

import { classify } from './lib/classify';
import type {
  CapturedHeaders,
  ContentMessage,
  NetworkDetection,
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
  const n = downloadableCount(state);
  try {
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

chrome.webRequest.onHeadersReceived.addListener(
  (details) => {
    const requestId = details.requestId;
    const headers = pendingHeaders.get(requestId) ?? {};
    pendingHeaders.delete(requestId);
    if (details.tabId < 0) return;

    const contentType = pick(details.responseHeaders, 'content-type');
    const kind = classify(details.url, contentType);
    if (kind === 'IGNORE' || kind === 'SEGMENT') return; // segments infer a stream, not listed

    const lenRaw = pick(details.responseHeaders, 'content-length');
    const contentLength = lenRaw ? Number(lenRaw) : undefined;

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

async function recordNetwork(det: NetworkDetection): Promise<void> {
  const state = await getState(det.tabId);
  if (state.network.some((n) => n.url === det.url)) return; // dedupe by URL
  state.network = [...state.network, det];
  await setState(det.tabId, state);
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
    }
    await setState(tabId, state);
    sendResponse({ ok: true });
  })();

  return true; // async response
});

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

export {};
