// URL + Content-Type classification (Module 2).
import type { MediaKind } from './types';

const DIRECT_EXT = /\.(mp4|webm|mkv|m4a|mp3|mov)(\?|#|$)/i;
const HLS_EXT = /\.m3u8(\?|#|$)/i;
const DASH_EXT = /\.mpd(\?|#|$)/i;
const SEGMENT_EXT = /\.(ts|m4s)(\?|#|$)/i;

// Content-Type hints (many streams carry no file extension in the URL).
const CT_HLS = /(mpegurl|x-mpegurl|vnd\.apple\.mpegurl)/i;
const CT_DASH = /(dash\+xml)/i;
const CT_DIRECT = /^(video\/(mp4|webm|quicktime|x-matroska)|audio\/(mpeg|mp4|webm))/i;
const CT_SEGMENT = /(mp2t|iso\.segment)/i;

export function classify(url: string, contentType?: string): MediaKind {
  const ct = contentType?.toLowerCase() ?? '';

  // URL extension first — cheapest and most reliable when present.
  if (HLS_EXT.test(url)) return 'HLS';
  if (DASH_EXT.test(url)) return 'DASH';
  if (SEGMENT_EXT.test(url)) return 'SEGMENT';
  if (DIRECT_EXT.test(url)) return 'DIRECT';

  // Fall back to Content-Type for extension-less stream URLs.
  if (CT_HLS.test(ct)) return 'HLS';
  if (CT_DASH.test(ct)) return 'DASH';
  if (CT_SEGMENT.test(ct)) return 'SEGMENT';
  if (CT_DIRECT.test(ct)) return 'DIRECT';

  return 'IGNORE';
}
