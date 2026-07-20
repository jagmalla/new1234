// Popup — Module 2. Shows what the detection engine found for the active tab.
// (The polished card UI is Module 4; this is an honest read-out so we can verify detection.)

import type { TabState } from '../lib/types';

const versionEl = document.getElementById('version');
if (versionEl) versionEl.textContent = `v${chrome.runtime.getManifest().version}`;

const body = document.getElementById('body')!;

function fmtBytes(n?: number): string {
  if (!n || !Number.isFinite(n)) return 'Unknown size';
  const units = ['B', 'KB', 'MB', 'GB'];
  let v = n;
  let u = 0;
  while (v >= 1024 && u < units.length - 1) {
    v /= 1024;
    u++;
  }
  return `${v.toFixed(v < 10 && u > 0 ? 1 : 0)} ${units[u]}`;
}

function fileName(url: string): string {
  try {
    const p = new URL(url).pathname;
    return decodeURIComponent(p.split('/').pop() || url);
  } catch {
    return url;
  }
}

function fmtDuration(sec?: number): string | null {
  if (!sec || !Number.isFinite(sec)) return null;
  const s = Math.round(sec);
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const ss = String(s % 60).padStart(2, '0');
  return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${ss}` : `${m}:${ss}`;
}

function render(state: TabState): void {
  body.innerHTML = '';

  if (state.drm) {
    body.innerHTML =
      '<p class="placeholder">🔒 This page uses DRM-protected media.<br>Protected — cannot be downloaded.</p>';
    return;
  }

  const items = [...state.network];
  const elementUrls = state.elements
    .filter((e) => e.src && !e.isBlob)
    .map((e) => e.src);

  const seen = new Set(items.map((i) => i.url));
  for (const url of elementUrls) {
    if (!seen.has(url)) {
      seen.add(url);
      items.push({
        id: url,
        url,
        kind: 'DIRECT',
        headers: {},
        tabId: -1,
        firstSeen: Date.now(),
      });
    }
  }

  if (items.length === 0) {
    body.innerHTML =
      '<p class="placeholder">No video detected on this page yet.<br>Press <b>play</b> — many sites don’t load the stream until playback starts.</p>';
    return;
  }

  for (const it of items) {
    const card = document.createElement('div');
    card.className = 'item';

    // Thumbnail (from page meta), shown once per list.
    if (state.meta?.thumbnail && items.indexOf(it) === 0) {
      const img = document.createElement('img');
      img.className = 'thumb';
      img.src = state.meta.thumbnail;
      img.alt = '';
      card.append(img);
    }

    const name = document.createElement('div');
    name.className = 'item-name';
    name.textContent = it.title || fileName(it.url);
    name.title = it.title || it.url;

    const meta = document.createElement('div');
    meta.className = 'item-meta';

    const bits: string[] = [`<span class="badge">${it.kind}</span>`];

    // Size: exact or estimated with "~", else honest "Unknown size".
    const sizeVal = it.sizeBytes ?? it.contentLength;
    if (sizeVal) {
      bits.push(`<span>${it.sizeEstimated ? '~' : ''}${fmtBytes(sizeVal)}</span>`);
    } else if (it.enriched) {
      bits.push('<span>Unknown size</span>');
    } else {
      bits.push('<span class="dim">reading…</span>');
    }

    const dur = fmtDuration(it.durationSec);
    if (dur) bits.push(`<span>${dur}</span>`);

    const res = it.height ? `${it.height}p` : null;
    if (res) bits.push(`<span>${res}</span>`);

    if (it.variants && it.variants.length > 1) {
      bits.push(`<span class="dim">${it.variants.length} qualities</span>`);
    }

    meta.innerHTML = bits.join('');
    card.append(name, meta);
    body.append(card);
  }
}

async function load(): Promise<void> {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (tab?.id == null) return;
  const res = await chrome.storage.session.get(`tab:${tab.id}`);
  render((res[`tab:${tab.id}`] as TabState) ?? { network: [], elements: [], drm: false });
}

void load();

// Live-update while the popup is open.
chrome.storage.session.onChanged.addListener(() => void load());
