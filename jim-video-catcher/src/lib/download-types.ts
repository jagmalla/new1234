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

// Service worker -> offscreen document.
export type ToOffscreen =
  | {
      target: 'offscreen';
      cmd: 'START_HLS';
      id: string;
      playlistUrl: string;   // chosen variant media playlist, or master
      variantIndex?: number; // if playlistUrl is a master
      headers: CapturedHeaders;
      filename: string;
      title: string;
      concurrency?: number;
      maxSegments?: number;
    }
  | { target: 'offscreen'; cmd: 'CANCEL'; id: string };

export const DOWNLOADS_KEY = 'downloads';
