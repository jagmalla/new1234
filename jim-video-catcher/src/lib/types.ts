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
  | { type: 'DRM_DETECTED'; keySystem: string };

// Messages the popup may request from background.
export type PopupRequest =
  | { type: 'GET_STATE'; tabId: number };

export interface TabState {
  network: NetworkDetection[];
  elements: ElementDetection[];
  drm: boolean;
  drmKeySystem?: string;
}
