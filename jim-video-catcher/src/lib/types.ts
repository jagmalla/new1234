// Shared types for detection (Module 2).

export type MediaKind = 'DIRECT' | 'HLS' | 'DASH' | 'SEGMENT' | 'IGNORE';

// Headers we must replay at download time or most real sites answer 403.
export interface CapturedHeaders {
  referer?: string;
  userAgent?: string;
  cookie?: string;
  authorization?: string;
  origin?: string;
}

// A single quality variant from an HLS master / DASH MPD.
export interface Variant {
  url: string;
  width: number | null;
  height: number | null;
  bandwidth?: number;   // bits per second
  codecs?: string;
}

// Page-level metadata gathered by the content script.
export interface PageMeta {
  title?: string;
  thumbnail?: string;   // data: URL or remote image URL
  durationSec?: number;
  pageUrl: string;
}

// A media resource seen on the network layer.
export interface NetworkDetection {
  id: string;            // stable id (the URL)
  url: string;
  kind: Exclude<MediaKind, 'SEGMENT' | 'IGNORE'>;
  contentType?: string;
  contentLength?: number;
  pageUrl?: string;
  headers: CapturedHeaders;
  tabId: number;
  firstSeen: number;

  // ---- Module 3 enrichment (filled in asynchronously) ----
  enriched?: boolean;
  title?: string;             // resolved, sanitized display/file name
  sizeBytes?: number;         // exact (direct) or estimated (stream)
  sizeEstimated?: boolean;    // true -> show with "~"
  durationSec?: number;
  variants?: Variant[];       // quality list for HLS/DASH
  width?: number | null;
  height?: number | null;
}

// A <video> element observed in the page by the content script.
export interface ElementDetection {
  id: string;
  src: string;           // currentSrc or resolved <source>
  isBlob: boolean;       // blob:/MSE — matched to a manifest by the network layer
  duration: number | null;
  width: number | null;
  height: number | null;
  playing: boolean;
  pageUrl: string;
}

// Messages from content script -> background.
export type ContentMessage =
  | { type: 'ELEMENTS'; elements: ElementDetection[] }
  | { type: 'DRM_DETECTED'; keySystem: string }
  | { type: 'PAGE_META'; meta: PageMeta };

// Messages the popup may request from background.
export type PopupRequest =
  | { type: 'GET_STATE'; tabId: number };

export interface TabState {
  network: NetworkDetection[];
  elements: ElementDetection[];
  drm: boolean;
  drmKeySystem?: string;
  meta?: PageMeta;
}
