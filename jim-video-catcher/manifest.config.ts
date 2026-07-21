import { defineManifest } from '@crxjs/vite-plugin';
import pkg from './package.json' with { type: 'json' };

// Manifest V3. Edge runs the Chromium extension platform, so chrome.* APIs apply.
// Each permission below is annotated so we can justify it to users and to store review.
export default defineManifest({
  manifest_version: 3,
  name: 'JIM Video Catcher',
  version: pkg.version,
  description: pkg.description,

  // Fixed public key -> stable extension ID (gnaljceaknidngnpjgkpbaeoimkkfkdo),
  // so the native messaging host can whitelist this extension by ID.
  key: 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArxEQvs2eGIgpTwE29ATEMZ8yhaf02jdUM56KAh4/L5NSMlJDO7xgxssr4AXzE9IWW80M67Q/gig/c4yIvdMlTBW9NDQW4SA6eXEocDxsB1Q9WIyjKZaTzChobFQ18oNgnQPOzztohX+DKH9vn0i0Ns39Lhn7u05MIFtnYMmm+IZfQQgOJarvn1XOLeCOaBdHsfZT3A+NLlU6H4CWEpWW7g+CLIZz0TYaSQoGfVWjgGlc1QTZh/PuL7BQmD/jbvv7jb+DHNLi7PnlXt70p/EEkAhUzvgQsjLr6C15ienpdrEEFi/prLFmJvZXgilwgFpgjoT4XQgCf49dKFsPZR/y/wIDAQAB',

  icons: {
    16: 'icons/icon16.png',
    32: 'icons/icon32.png',
    48: 'icons/icon48.png',
    128: 'icons/icon128.png',
  },

  action: {
    default_title: 'JIM Video Catcher',
    default_popup: 'src/popup/popup.html',
    default_icon: {
      16: 'icons/icon16.png',
      32: 'icons/icon32.png',
      48: 'icons/icon48.png',
      128: 'icons/icon128.png',
    },
  },

  options_page: 'src/options.html',

  background: {
    // MV3 service worker (event-based, can be killed when idle — we design for that later).
    service_worker: 'src/background.ts',
    type: 'module',
  },

  content_scripts: [
    {
      // Runs in the page's OWN JS world at the earliest moment so it can wrap
      // navigator.requestMediaKeySystemAccess before the site calls it (DRM detection).
      matches: ['<all_urls>'],
      js: ['src/lib/eme-hook.ts'],
      run_at: 'document_start',
      all_frames: true,
      world: 'MAIN',
    },
    {
      // Isolated world: scans <video> elements and relays messages to the worker.
      matches: ['<all_urls>'],
      js: ['src/content.ts'],
      run_at: 'document_idle',
      all_frames: true,
    },
  ],

  permissions: [
    'webRequest', // Observe media network requests to detect streams (observation only in MV3).
    'storage',    // Persist per-tab detections, settings, and history.
    'downloads',  // Save detected files to disk via chrome.downloads.
    'activeTab',  // Act on the tab the user is currently looking at when they open the popup.
    'scripting',  // Inject/execute detection logic on demand.
    'tabs',       // Map detections to the correct tab and clear them on navigation/close.
    'offscreen',  // Run the HLS assembler in a document that outlives the worker.
    'declarativeNetRequestWithHostAccess', // Replay Referer/Cookie/UA on downloads to avoid 403s.
    'contextMenus', // Right-click "Download this video with JIM" on <video> elements.
    'nativeMessaging', // Talk to the optional yt-dlp native helper (Module 7).
  ],

  commands: {
    _execute_action: {
      suggested_key: { default: 'Ctrl+Shift+Y' },
      description: 'Open JIM Video Catcher',
    },
  },

  // Needed so detection and downloads work on arbitrary sites, including private/self-hosted ones.
  host_permissions: ['<all_urls>'],

  minimum_chrome_version: '110',
});
