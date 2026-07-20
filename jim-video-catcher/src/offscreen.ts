// Offscreen download engine (Module 5, storage-queue model in 0.6.2).
// Reads HLS jobs from storage.session (set on load AND on change, so there is no
// message-before-listener race), downloads segments with bounded concurrency +
// retry, assembles a playable file, and saves it via chrome.downloads. Progress
// is written straight to storage.session so the popup stays live even if the
// service worker is asleep.

import { parseHls, parseHlsSegments } from './lib/manifest-parse';
import {
  CANCELED_KEY,
  DOWNLOADS_KEY,
  JOBS_KEY,
  type DownloadProgress,
  type HlsJob,
} from './lib/download-types';
import { addHistory } from './lib/settings';

const DEFAULT_CONCURRENCY = 6;
const MAX_RETRIES = 3;
const started = new Set<string>();
let canceled = new Set<string>();

console.log('[JIM offscreen] document loaded, draining queue');
// Pick up jobs already queued when this document loaded, and any added later.
void drain();
chrome.storage.session.onChanged.addListener((changes) => {
  if (changes[CANCELED_KEY]) {
    canceled = new Set((changes[CANCELED_KEY].newValue as string[]) ?? []);
  }
  if (changes[JOBS_KEY]) void drain();
});

async function drain(): Promise<void> {
  const store = await chrome.storage.session.get([JOBS_KEY, CANCELED_KEY]);
  canceled = new Set((store[CANCELED_KEY] as string[]) ?? []);
  const jobs: HlsJob[] = store[JOBS_KEY] ?? [];
  for (const job of jobs) {
    if (started.has(job.id)) continue;
    started.add(job.id);
    void runHls(job); // run jobs concurrently
  }
}

// ---- progress persistence ---------------------------------------------------

async function patchProgress(id: string, patch: Partial<DownloadProgress>): Promise<void> {
  const store = await chrome.storage.session.get(DOWNLOADS_KEY);
  const list: DownloadProgress[] = store[DOWNLOADS_KEY] ?? [];
  const idx = list.findIndex((d) => d.id === id);
  if (idx >= 0) list[idx] = { ...list[idx], ...patch };
  await chrome.storage.session.set({ [DOWNLOADS_KEY]: list });
}

// Remove a finished job from the queue so it isn't reprocessed.
async function removeJob(id: string): Promise<void> {
  const store = await chrome.storage.session.get(JOBS_KEY);
  const jobs: HlsJob[] = store[JOBS_KEY] ?? [];
  await chrome.storage.session.set({ [JOBS_KEY]: jobs.filter((j) => j.id !== id) });
}

// ---- fetch with retry -------------------------------------------------------

// fetch with an abort timeout so a hanging host surfaces as an error instead of
// leaving the download stuck forever.
async function fetchTimeout(url: string, ms = 25000): Promise<Response> {
  const c = new AbortController();
  const t = setTimeout(() => c.abort(), ms);
  try {
    return await fetch(url, { credentials: 'include', signal: c.signal });
  } finally {
    clearTimeout(t);
  }
}

async function fetchBuf(url: string, tries = MAX_RETRIES): Promise<ArrayBuffer> {
  let lastErr: unknown;
  for (let i = 0; i < tries; i++) {
    try {
      const r = await fetchTimeout(url);
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return await r.arrayBuffer();
    } catch (e) {
      lastErr = e;
      await sleep(400 * (i + 1)); // backoff before resume
    }
  }
  throw lastErr instanceof Error ? lastErr : new Error('fetch failed');
}

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

// ---- main HLS routine -------------------------------------------------------

async function runHls(job: HlsJob): Promise<void> {
  const { id, filename } = job;
  console.log('[JIM offscreen] starting job', id, job.playlistUrl);
  try {
    await patchProgress(id, { state: 'preparing', error: undefined });

    // Resolve to a media playlist (follow the master + chosen variant if needed).
    let playlistText = await fetchText(job.playlistUrl);
    let playlistUrl = job.playlistUrl;
    const master = parseHls(playlistText, playlistUrl);
    if (master.isMaster && master.variants.length) {
      const v = master.variants[job.variantIndex ?? 0] ?? master.variants[0];
      playlistUrl = v.url;
      playlistText = await fetchText(playlistUrl);
    }

    const parsed = parseHlsSegments(playlistText, playlistUrl);
    const { initUrl, isFmp4 } = parsed;
    let { segmentUrls } = parsed;
    if (segmentUrls.length === 0) throw new Error('No segments found in playlist');

    // Livestream safeguard: never fetch more than the configured cap.
    const cap = job.maxSegments ?? 20000;
    if (segmentUrls.length > cap) segmentUrls = segmentUrls.slice(0, cap);
    const concurrency = Math.max(1, Math.min(job.concurrency ?? DEFAULT_CONCURRENCY, 12));

    const segTotal = segmentUrls.length;
    await patchProgress(id, { state: 'downloading', segTotal, segDone: 0 });

    const parts: ArrayBuffer[] = [];
    if (initUrl) parts.push(await fetchBuf(initUrl));

    const buffers: (ArrayBuffer | null)[] = new Array(segTotal).fill(null);
    let done = 0;
    let received = initUrl ? parts[0].byteLength : 0;
    const startedAt = Date.now();
    let next = 0;

    async function worker(): Promise<void> {
      while (next < segTotal) {
        if (canceled.has(id)) return;
        const i = next++;
        const buf = await fetchBuf(segmentUrls[i]);
        buffers[i] = buf;
        done++;
        received += buf.byteLength;
        const elapsed = (Date.now() - startedAt) / 1000;
        const speed = elapsed > 0 ? received / elapsed : undefined;
        const percent = Math.round((done / segTotal) * 100);
        const remaining = speed ? ((received / done) * (segTotal - done)) / speed : undefined;
        await patchProgress(id, { segDone: done, received, percent, speed, etaSec: remaining });
      }
    }

    await Promise.all(Array.from({ length: Math.min(concurrency, segTotal) }, worker));

    if (canceled.has(id)) {
      await patchProgress(id, { state: 'canceled' });
      await removeJob(id);
      return;
    }

    // Plain concatenation is playable for MPEG-TS and for fMP4 (init + fragments).
    await patchProgress(id, { state: 'assembling', percent: 100 });
    for (const b of buffers) if (b) parts.push(b);
    const type = isFmp4 ? 'video/mp4' : 'video/mp2t';
    const blob = new Blob(parts, { type });

    // Correct the extension now that we know the container.
    const ext = isFmp4 ? '.mp4' : '.ts';
    const base = filename.replace(/\.(mp4|ts|m3u8|m4s|webm)$/i, '');
    const finalName = base + ext;
    await patchProgress(id, { state: 'saving', filename: finalName });
    const url = URL.createObjectURL(blob);
    await chrome.downloads.download({ url, filename: finalName });
    setTimeout(() => URL.revokeObjectURL(url), 60_000);

    await addHistory({
      title: job.title,
      filename: finalName,
      url: job.playlistUrl,
      kind: 'HLS',
      sizeBytes: blob.size,
      when: Date.now(),
    });
    await patchProgress(id, { state: 'done', percent: 100, speed: undefined, etaSec: 0 });
    await removeJob(id);
  } catch (e) {
    await patchProgress(id, { state: 'error', error: e instanceof Error ? e.message : String(e) });
    await removeJob(id);
  }
}

async function fetchText(url: string): Promise<string> {
  const r = await fetchTimeout(url);
  if (!r.ok) throw new Error(`HTTP ${r.status} fetching playlist`);
  return r.text();
}
