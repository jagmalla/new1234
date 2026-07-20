// Popup entry — Module 1. Must open instantly, so this stays tiny.
// Shows the real version from the manifest.

const versionEl = document.getElementById('version');
if (versionEl) {
  const { version } = chrome.runtime.getManifest();
  versionEl.textContent = `v${version}`;
}
