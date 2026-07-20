// Filename resolution + sanitization (Module 3).

const SITE_SUFFIX = /\s*[-|–—]\s*(YouTube|Vimeo|Dailymotion|Facebook|Twitter|X)\s*$/i;

// Strip characters Windows forbids in filenames and trim length, keeping any extension.
export function sanitizeFilename(name: string, fallbackExt = ''): string {
  let base = name.replace(SITE_SUFFIX, '').trim();
  base = base.replace(/[\\/:*?"<>|]/g, '').replace(/\s+/g, ' ').trim();
  if (!base) base = 'video';

  // Preserve an existing extension; otherwise append the fallback.
  const hasExt = /\.[a-z0-9]{2,4}$/i.test(base);
  const ext = hasExt ? '' : fallbackExt;

  const MAX = 120;
  if (base.length > MAX) base = base.slice(0, MAX).trim();
  return base + ext;
}

// Extension implied by kind/URL, e.g. ".mp4".
export function extFor(url: string, kind: string): string {
  const m = /\.([a-z0-9]{2,4})(?:\?|#|$)/i.exec(url);
  if (m) return `.${m[1].toLowerCase()}`;
  if (kind === 'HLS' || kind === 'DASH') return '.mp4';
  return '';
}

// Last path segment of a URL, decoded.
export function urlFilename(url: string): string {
  try {
    const p = new URL(url).pathname;
    return decodeURIComponent(p.split('/').pop() || '') || url;
  } catch {
    return url;
  }
}
