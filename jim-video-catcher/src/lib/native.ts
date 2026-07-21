// Native helper client (Module 7). Talks to the yt-dlp host over native messaging.

export const HOST = 'com.jim.videocatcher';

export interface NativeStatus {
  available: boolean;
  hostVersion?: string;
  ytdlp?: string | null;
  error?: string;
}

// One-shot ping to detect whether the host is installed and yt-dlp is present.
export function pingNative(timeoutMs = 3000): Promise<NativeStatus> {
  return new Promise((resolve) => {
    let done = false;
    const finish = (s: NativeStatus) => {
      if (!done) {
        done = true;
        resolve(s);
      }
    };
    const timer = setTimeout(() => finish({ available: false, error: 'timeout' }), timeoutMs);
    try {
      chrome.runtime.sendNativeMessage(HOST, { type: 'ping' }, (resp) => {
        clearTimeout(timer);
        if (chrome.runtime.lastError) {
          finish({ available: false, error: chrome.runtime.lastError.message });
          return;
        }
        finish({
          available: true,
          hostVersion: resp?.hostVersion,
          ytdlp: resp?.ytdlp ?? null,
        });
      });
    } catch (e) {
      clearTimeout(timer);
      finish({ available: false, error: String(e) });
    }
  });
}

export interface NativeDownloadReq {
  url: string;
  format: 'video' | 'audio';
  quality?: number; // max height
  drm?: boolean;
}

export type NativeEvent =
  | { type: 'ready'; hostVersion: string }
  | { type: 'progress'; percent: number; speedText?: string; etaText?: string; totalText?: string }
  | { type: 'done'; file: string }
  | { type: 'error'; message: string }
  | { type: 'disconnect' };

// Open a long-lived port and stream a download's events back to the caller.
export function nativeDownload(
  req: NativeDownloadReq,
  onEvent: (e: NativeEvent) => void,
): chrome.runtime.Port {
  const port = chrome.runtime.connectNative(HOST);
  port.onMessage.addListener((m) => onEvent(m as NativeEvent));
  port.onDisconnect.addListener(() => {
    const err = chrome.runtime.lastError?.message;
    onEvent(err ? { type: 'error', message: err } : { type: 'disconnect' });
  });
  port.postMessage({ type: 'download', ...req });
  return port;
}
