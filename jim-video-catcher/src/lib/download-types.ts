// Shared download/progress types (Module 5).
import type { CapturedHeaders } from './types';

export type DownloadState =
  | 'preparing'
  | 'downloading'
  | 'assembling'
  | 'saving'
  | 'done'
  | 'error'
  | 'canceled';

export interface DownloadProgress {
  id: string;
  title: string;
  filename: string;
  kind: string;
  state: DownloadState;
  segDone: number;
  segTotal: number;
  received: number;      // bytes downloaded so far
  percent: number;       // 0..100
  speed?: number;        // bytes/sec (rolling)
  etaSec?: number;
  error?: string;
  startedAt: number;
}

// A download job handed from the service worker to the offscreen document
// through storage.session (avoids a message-before-listener startup race).
export interface HlsJob {
  id: string;
  playlistUrl: string;   // chosen variant media playlist, or master
  variantIndex?: number; // if playlistUrl is a master
  headers: CapturedHeaders;
  filename: string;
  title: string;
  concurrency?: number;
  maxSegments?: number;
}

export const DOWNLOADS_KEY = 'downloads';
export const JOBS_KEY = 'jobs';
export const CANCELED_KEY = 'canceledIds';
