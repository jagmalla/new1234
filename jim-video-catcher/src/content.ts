// Content script (isolated world) — Module 2.
// Scans <video>/<source> elements, watches for DOM changes, relays DRM signals.

import type { ContentMessage, ElementDetection } from './lib/types';

function send(msg: ContentMessage): void {
  try {
    chrome.runtime.sendMessage(msg);
  } catch {
    // service worker may be asleep; it will pick up the next scan
  }
}

// ---- DRM: the MAIN-world hook postMessages here when the page requests media keys.

let drmReported = false;
window.addEventListener('message', (e) => {
  const d = e.data as { __jimvc?: boolean; keySystem?: string };
  if (e.source === window && d && d.__jimvc && !drmReported) {
    drmReported = true;
    send({ type: 'DRM_DETECTED', keySystem: d.keySystem ?? 'unknown' });
  }
});

// ---- Element scan.

function resolveSrc(v: HTMLVideoElement): string {
  if (v.currentSrc) return v.currentSrc;
  const source = v.querySelector('source');
  return source?.src ?? v.src ?? '';
}

function scan(): void {
  const videos = Array.from(document.querySelectorAll('video')) as HTMLVideoElement[];
  const elements: ElementDetection[] = videos.map((v, i) => {
    const src = resolveSrc(v);
    return {
      id: src || `video-${i}`,
      src,
      isBlob: src.startsWith('blob:') || src === '',
      duration: Number.isFinite(v.duration) ? v.duration : null,
      width: v.videoWidth || null,
      height: v.videoHeight || null,
      playing: !v.paused && !v.ended && v.readyState > 2,
      pageUrl: location.href,
    };
  });
  send({ type: 'ELEMENTS', elements });
}

// Debounced rescan on DOM mutations (sites inject <video> late / swap sources).
let timer: number | undefined;
function scheduleScan(): void {
  if (timer) clearTimeout(timer);
  timer = setTimeout(scan, 400) as unknown as number;
}

const observer = new MutationObserver(scheduleScan);
observer.observe(document.documentElement, { childList: true, subtree: true });

// Rescan when playback state changes (captures duration/dimensions once known).
document.addEventListener('play', scheduleScan, true);
document.addEventListener('loadedmetadata', scheduleScan, true);
document.addEventListener('durationchange', scheduleScan, true);

scan();
