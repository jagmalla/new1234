// HLS + DASH manifest parsing (Module 3).
// Runs inside the MV3 service worker, which has NO DOMParser — so DASH is parsed
// with regex rather than XML DOM. Good enough for size estimates and quality lists.

import type { Variant } from './types';

function resolve(base: string, ref: string): string {
  try {
    return new URL(ref, base).href;
  } catch {
    return ref;
  }
}

// ---- HLS ------------------------------------------------------------------

export interface HlsParse {
  isMaster: boolean;
  variants: Variant[];
  durationSec?: number; // only for a media playlist (sum of EXTINF)
}

export function parseHls(text: string, baseUrl: string): HlsParse {
  const lines = text.split(/\r?\n/);
  const isMaster = text.includes('#EXT-X-STREAM-INF');

  if (!isMaster) {
    // Media playlist: duration = sum of EXTINF values.
    let duration = 0;
    for (const line of lines) {
      const m = /^#EXTINF:([\d.]+)/.exec(line);
      if (m) duration += parseFloat(m[1]);
    }
    return { isMaster: false, variants: [], durationSec: duration || undefined };
  }

  const variants: Variant[] = [];
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (!line.startsWith('#EXT-X-STREAM-INF:')) continue;
    const attrs = line.slice('#EXT-X-STREAM-INF:'.length);
    const bandwidth = num(/BANDWIDTH=(\d+)/.exec(attrs)?.[1]);
    const res = /RESOLUTION=(\d+)x(\d+)/.exec(attrs);
    const codecs = /CODECS="([^"]+)"/.exec(attrs)?.[1];
    // The URL is the next non-comment line.
    let url = '';
    for (let j = i + 1; j < lines.length; j++) {
      if (lines[j] && !lines[j].startsWith('#')) {
        url = resolve(baseUrl, lines[j].trim());
        break;
      }
    }
    variants.push({
      url,
      width: res ? Number(res[1]) : null,
      height: res ? Number(res[2]) : null,
      bandwidth,
      codecs,
    });
  }
  // Highest quality first.
  variants.sort((a, b) => (b.height ?? 0) - (a.height ?? 0) || (b.bandwidth ?? 0) - (a.bandwidth ?? 0));
  return { isMaster: true, variants };
}

// Segments of a media playlist (for actual downloading).
export interface HlsSegments {
  initUrl?: string;      // #EXT-X-MAP (fMP4 init segment)
  segmentUrls: string[]; // in order
  isFmp4: boolean;
}

export function parseHlsSegments(text: string, baseUrl: string): HlsSegments {
  const lines = text.split(/\r?\n/);
  const segmentUrls: string[] = [];
  let initUrl: string | undefined;
  for (const raw of lines) {
    const line = raw.trim();
    if (!line) continue;
    const mapMatch = /^#EXT-X-MAP:.*URI="([^"]+)"/.exec(line);
    if (mapMatch) {
      initUrl = resolve(baseUrl, mapMatch[1]);
      continue;
    }
    if (line.startsWith('#')) continue;
    segmentUrls.push(resolve(baseUrl, line));
  }
  const isFmp4 = !!initUrl || segmentUrls.some((u) => /\.m4s(\?|#|$)/i.test(u));
  return { initUrl, segmentUrls, isFmp4 };
}

// ---- DASH -----------------------------------------------------------------

export interface DashParse {
  variants: Variant[];
  durationSec?: number;
}

export function parseDash(xml: string): DashParse {
  const durationSec = parseIsoDuration(
    /mediaPresentationDuration="([^"]+)"/.exec(xml)?.[1],
  );

  const variants: Variant[] = [];
  const repRe = /<Representation\b([^>]*)\/?>/g;
  let m: RegExpExecArray | null;
  while ((m = repRe.exec(xml)) !== null) {
    const attrs = m[1];
    const width = num(/\bwidth="(\d+)"/.exec(attrs)?.[1]);
    const height = num(/\bheight="(\d+)"/.exec(attrs)?.[1]);
    const bandwidth = num(/\bbandwidth="(\d+)"/.exec(attrs)?.[1]);
    const codecs = /\bcodecs="([^"]+)"/.exec(attrs)?.[1];
    // Skip audio-only reps for the quality list (no width/height) but keep video.
    variants.push({
      url: '', // DASH segment URLs need template expansion — out of scope for the estimate
      width: width ?? null,
      height: height ?? null,
      bandwidth,
      codecs,
    });
  }
  variants.sort((a, b) => (b.height ?? 0) - (a.height ?? 0) || (b.bandwidth ?? 0) - (a.bandwidth ?? 0));
  return { variants, durationSec };
}

// ---- helpers --------------------------------------------------------------

function num(s?: string): number | undefined {
  if (s == null) return undefined;
  const n = Number(s);
  return Number.isFinite(n) ? n : undefined;
}

// ISO-8601 duration like "PT1H2M3.5S" -> seconds.
export function parseIsoDuration(s?: string): number | undefined {
  if (!s) return undefined;
  const m = /P(?:(\d+)D)?T(?:(\d+)H)?(?:(\d+)M)?(?:([\d.]+)S)?/.exec(s);
  if (!m) return undefined;
  const d = Number(m[1] ?? 0);
  const h = Number(m[2] ?? 0);
  const min = Number(m[3] ?? 0);
  const sec = Number(m[4] ?? 0);
  const total = d * 86400 + h * 3600 + min * 60 + sec;
  return total > 0 ? total : undefined;
}

// Estimate bytes from a stream's peak bitrate and duration. ALWAYS an estimate.
export function estimateBytes(bandwidthBitsPerSec?: number, durationSec?: number): number | undefined {
  if (!bandwidthBitsPerSec || !durationSec) return undefined;
  return Math.round((bandwidthBitsPerSec / 8) * durationSec);
}
