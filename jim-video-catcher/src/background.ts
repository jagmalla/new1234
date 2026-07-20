// MV3 service worker — Module 1 placeholder.
// The detection engine (webRequest listeners, per-tab storage, badge) arrives in Module 2.
// Kept intentionally minimal so the extension loads cleanly with nothing stubbed ahead.

chrome.runtime.onInstalled.addListener((details) => {
  console.log('[JIM Video Catcher] installed:', details.reason);
});

export {};
