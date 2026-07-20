// Content script (isolated world) — Module 2.
// Scans <video>/<source> elements, watches for DOM changes, relays DRM signals.

import type { ContentMessage, ElementDetection, PageMeta } from './lib/types';

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

// ---- Page metadata: name, thumbnail, duration (Module 3). --------------------

function metaContent(sel: string): string | undefined {
  const el = document.querySelector(sel) as HTMLMetaElement | null;
  return el?.content?.trim() || undefined;
}

function jsonLdName(): string | undefined {
  const scripts = Array.from(
    document.querySelectorAll('script[type="application/ld+json"]'),
  );
  for (const s of scripts) {
    try {
      const data = JSON.parse(s.textContent || 'null');
      const nodes = Array.isArray(data) ? data : [data, ...(data['@graph'] ?? [])];
      for (const n of nodes) {
        if (n && n['@type'] === 'VideoObject' && typeof n.name === 'string') return n.name;
      }
    } catch {
      /* ignore malformed JSON-LD */
    }
  }
  return undefined;
}

function resolveName(v?: HTMLVideoElement): string | undefined {
  return (
    metaContent('meta[property="og:video:title"]') ||
    metaContent('meta[property="og:title"]') ||
    jsonLdName() ||
    v?.title?.trim() ||
    v?.getAttribute('aria-label')?.trim() ||
    nearestH1(v) ||
    document.title?.trim() ||
    undefined
  );
}

function nearestH1(v?: HTMLVideoElement): string | undefined {
  if (v) {
    let node: HTMLElement | null = v;
    for (let i = 0; i < 6 && node; i++) {
      const h1 = node.querySelector?.('h1');
      if (h1?.textContent) return h1.textContent.trim();
      node = node.parentElement;
    }
  }
  return document.querySelector('h1')?.textContent?.trim() || undefined;
}

function captureThumb(v?: HTMLVideoElement): string | undefined {
  const og = metaContent('meta[property="og:image"]');
  if (og) return og;
  const poster = v?.getAttribute('poster');
  if (poster) return poster;
  // Capture the current frame if the video has painted something.
  if (v && v.videoWidth) {
    try {
      const c = document.createElement('canvas');
      c.width = 320;
      c.height = Math.round((320 * v.videoHeight) / v.videoWidth) || 180;
      const ctx = c.getContext('2d');
      if (ctx) {
        ctx.drawImage(v, 0, 0, c.width, c.height);
        return c.toDataURL('image/jpeg', 0.6);
      }
    } catch {
      /* cross-origin frame — cannot read */
    }
  }
  return undefined;
}

function sendMeta(): void {
  const v = document.querySelector('video') as HTMLVideoElement | null;
  const meta: PageMeta = {
    title: resolveName(v ?? undefined),
    thumbnail: captureThumb(v ?? undefined),
    durationSec: v && Number.isFinite(v.duration) ? v.duration : undefined,
    pageUrl: location.href,
  };
  send({ type: 'PAGE_META', meta });
}

// Debounced rescan on DOM mutations (sites inject <video> late / swap sources).
let timer: number | undefined;
function scheduleScan(): void {
  if (timer) clearTimeout(timer);
  timer = setTimeout(() => {
    scan();
    sendMeta();
  }, 400) as unknown as number;
}

const observer = new MutationObserver(scheduleScan);
observer.observe(document.documentElement, { childList: true, subtree: true });

// Rescan when playback state changes (captures duration/dimensions once known).
document.addEventListener('play', scheduleScan, true);
document.addEventListener('loadedmetadata', scheduleScan, true);
document.addEventListener('durationchange', scheduleScan, true);

scan();
sendMeta();
