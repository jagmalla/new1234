// Injected into the PAGE's own JS context (not the isolated content-script world),
// so we can see the real navigator.requestMediaKeySystemAccess the site calls.
// When the page asks for Widevine/PlayReady/Clearkey, we mark the page DRM-protected.
//
// This file is built as a standalone script and injected via a <script> tag by content.ts.

(() => {
  const nav = navigator as Navigator & {
    requestMediaKeySystemAccess?: (
      keySystem: string,
      configs: MediaKeySystemConfiguration[],
    ) => Promise<MediaKeySystemAccess>;
  };
  const original = nav.requestMediaKeySystemAccess?.bind(navigator);
  if (!original) return;

  nav.requestMediaKeySystemAccess = (keySystem, configs) => {
    try {
      window.postMessage({ __jimvc: true, keySystem }, '*');
    } catch {
      /* ignore */
    }
    return original(keySystem, configs);
  };
})();
