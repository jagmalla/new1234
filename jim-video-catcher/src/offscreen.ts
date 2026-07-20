// Offscreen download engine (Module 5).
// Downloads HLS segments with bounded concurrency + retry, assembles a playable
// file, and saves it via chrome.downloads. Writes progress straight to
// storage.session so the popup stays live even if the service worker is asleep.

import { parseHls, parseHlsSegments } from './lib/manifest-parse';
import { DOWNLOADS_KEY, type DownloadProgress, type ToOffscreen } from './lib/download-types';
import { addHistory } from './lib/settings';

const DEFAULT_CONCURRENCY = 6;
const MAX_RETRIES = 3;
const canceled = new Set<string>();

chrome.runtime.onMessage.addListener((msg: ToOffscreen) => {
  if (msg?.target !== 'offscreen') return;
  if (msg.cmd === 'CANCEL') {
    canceled.add(msg.id);
    return;
  }
  if (msg.cmd === 'START_HLS') {
    void runHls(msg);
  }
});

// ---- progress persistence ---------------------------------------------------

async function patchProgress(id: string, patch: Partial<DownloadProgress>): Promise<void> {
  const store = await chrome.storage.session.get(DOWNLOADS_KEY);
  const list: DownloadProgress[] = store[DOWNLOADS_KEY] ?? [];
  const idx = list.findIndex((d) => d.id === id);
  if (idx >= 0) list[idx] = { ...list[idx], ...patch };
  await chrome.storage.session.set({ [DOWNLOADS_KEY]: list });
}

// ---- fetch with retry -------------------------------------------------------

async function fetchBuf(url: string, tries = MAX_RETRIES): Promise<ArrayBuffer> {
  let lastErr: unknown;
  for (let i = 0; i < tries; i++) {
    try {
      const r = await fetch(url, { credentials: 'include' });
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

async function runHls(msg: Extract<ToOffscreen, { cmd: 'START_HLS' }>): Promise<void> {
  const { id, filename, headers } = msg;
  void headers; // header replay is applied by the service worker via declarativeNetRequest
  try {
    await patchProgress(id, { state: 'preparing' });

    // Resolve to a media playlist (follow the master + chosen variant if needed).
    let playlistText = await fetchText(msg.playlistUrl);
    let playlistUrl = msg.playlistUrl;
    const master = parseHls(playlistText, playlistUrl);
    if (master.isMaster && master.variants.length) {
      const v = master.variants[msg.variantIndex ?? 0] ?? master.variants[0];
      playlistUrl = v.url;
      playlistText = await fetchText(playlistUrl);
    }

    const parsed = parseHlsSegments(playlistText, playlistUrl);
    const { initUrl, isFmp4 } = parsed;
    let { segmentUrls } = parsed;
    if (segmentUrls.length === 0) throw new Error('No segments found in playlist');

    // Livestream safeguard: never fetch more than the configured cap.
    const cap = msg.maxSegments ?? 20000;
    if (segmentUrls.length > cap) segmentUrls = segmentUrls.slice(0, cap);
    const concurrency = Math.max(1, Math.min(msg.concurrency ?? DEFAULT_CONCURRENCY, 12));

    const segTotal = segmentUrls.length;
    await patchProgress(id, { state: 'downloading', segTotal, segDone: 0 });

    // Download init segment first (fMP4).
    const parts: ArrayBuffer[] = [];
    if (initUrl) parts.push(await fetchBuf(initUrl));

    // Bounded-concurrency segment download, preserving order.
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
        await patchProgress(id, {
          segDone: done,
          received,
          percent,
          speed,
          etaSec: remaining,
        });
      }
    }

    await Promise.all(Array.from({ length: Math.min(concurrency, segTotal) }, worker));

    if (canceled.has(id)) {
      canceled.delete(id);
      await patchProgress(id, { state: 'canceled' });
      return;
    }

    // Assemble in order. Plain concatenation is playable for MPEG-TS and for
    // fMP4 (init + fragments); no ffmpeg needed for single-track HLS.
    await patchProgress(id, { state: 'assembling', percent: 100 });
    for (const b of buffers) if (b) parts.push(b);
    const type = isFmp4 ? 'video/mp4' : 'video/mp2t';
    const blob = new Blob(parts, { type });

    // Save. Correct the extension now that we know the container.
    const ext = isFmp4 ? '.mp4' : '.ts';
    const base = filename.replace(/\.(mp4|ts|m3u8|m4s|webm)$/i, '');
    const finalName = base + ext;
    await patchProgress(id, { state: 'saving', filename: finalName });
    const url = URL.createObjectURL(blob);
    await chrome.downloads.download({ url, filename: finalName });
    // Give the download a beat to read the blob, then release memory.
    setTimeout(() => URL.revokeObjectURL(url), 60_000);

    await addHistory({
      title: msg.title,
      filename: finalName,
      url: msg.playlistUrl,
      kind: 'HLS',
      sizeBytes: blob.size,
      when: Date.now(),
    });
    await patchProgress(id, { state: 'done', percent: 100, speed: undefined, etaSec: 0 });
  } catch (e) {
    await patchProgress(id, { state: 'error', error: e instanceof Error ? e.message : String(e) });
  } finally {
    canceled.delete(id);
  }
}

async function fetchText(url: string): Promise<string> {
  const r = await fetch(url, { credentials: 'include' });
  if (!r.ok) throw new Error(`HTTP ${r.status} fetching playlist`);
  return r.text();
}
