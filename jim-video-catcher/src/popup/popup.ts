// Popup — Module 4: product UI (cards, quality dropdown, format toggle, Download).
// Direct-file download is wired for real here; streams/MP3 use the Module 5 engine.

import type { NetworkDetection, TabState, Variant } from '../lib/types';
import { DOWNLOADS_KEY, type DownloadProgress } from '../lib/download-types';

const versionEl = document.getElementById('version')!;
versionEl.textContent = `v${chrome.runtime.getManifest().version}`;

const body = document.getElementById('body')!;
const downloadsEl = document.getElementById('downloads')!;
const settingsBtn = document.getElementById('settings')!;
const notice = document.getElementById('notice')!;
const noticeOk = document.getElementById('notice-ok')!;

settingsBtn.addEventListener('click', () => chrome.runtime.openOptionsPage());

// ---- First-run legal notice -------------------------------------------------
chrome.storage.local.get('noticeSeen').then((r) => {
  if (!r.noticeSeen) notice.hidden = false;
});
noticeOk.addEventListener('click', () => {
  notice.hidden = true;
  void chrome.storage.local.set({ noticeSeen: true });
});

// ---- Formatting helpers -----------------------------------------------------
function fmtBytes(n?: number): string {
  if (!n || !Number.isFinite(n)) return 'Unknown size';
  const u = ['B', 'KB', 'MB', 'GB'];
  let v = n;
  let i = 0;
  while (v >= 1024 && i < u.length - 1) {
    v /= 1024;
    i++;
  }
  return `${v.toFixed(v < 10 && i > 0 ? 1 : 0)} ${u[i]}`;
}
function fmtDuration(sec?: number): string | null {
  if (!sec || !Number.isFinite(sec)) return null;
  const s = Math.round(sec);
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const ss = String(s % 60).padStart(2, '0');
  return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${ss}` : `${m}:${ss}`;
}
function fileName(url: string): string {
  try {
    return decodeURIComponent(new URL(url).pathname.split('/').pop() || url);
  } catch {
    return url;
  }
}

// ---- Card rendering ---------------------------------------------------------
function buildCard(it: NetworkDetection, thumb?: string): HTMLElement {
  const card = document.createElement('div');
  card.className = 'card';

  // Thumbnail
  if (thumb) {
    const img = document.createElement('img');
    img.className = 'thumb';
    img.src = thumb;
    img.alt = '';
    card.append(img);
  } else {
    const ph = document.createElement('div');
    ph.className = 'thumb placeholder-thumb';
    ph.textContent = '🎬';
    card.append(ph);
  }

  // Name
  const name = document.createElement('div');
  name.className = 'card-name';
  name.textContent = it.title || fileName(it.url);
  name.title = it.title || it.url;
  card.append(name);

  // Meta row
  const meta = document.createElement('div');
  meta.className = 'meta-row';
  const parts: string[] = [`<span class="badge">${it.kind}</span>`];
  const sizeVal = it.sizeBytes ?? it.contentLength;
  if (sizeVal) parts.push(`<span class="${it.sizeEstimated ? 'est' : ''}">${it.sizeEstimated ? '~' : ''}${fmtBytes(sizeVal)}</span>`);
  else if (it.enriched) parts.push('<span>Unknown size</span>');
  else parts.push('<span class="est">reading…</span>');
  const dur = fmtDuration(it.durationSec);
  if (dur) parts.push(`<span>${dur}</span>`);
  if (it.height) parts.push(`<span>${it.height}p</span>`);
  meta.innerHTML = parts.join('');
  card.append(meta);

  // Quality dropdown (when >1 variant)
  let selectedVariant = 0;
  const controls = document.createElement('div');
  controls.className = 'controls';

  const variants: Variant[] = it.variants ?? [];
  if (variants.length > 1) {
    const sel = document.createElement('select');
    variants.forEach((v, i) => {
      const opt = document.createElement('option');
      const label = v.height ? `${v.height}p` : 'audio';
      const br = v.bandwidth ? ` · ${Math.round(v.bandwidth / 1000)} kbps` : '';
      opt.value = String(i);
      opt.textContent = label + br;
      sel.append(opt);
    });
    sel.addEventListener('change', () => (selectedVariant = Number(sel.value)));
    controls.append(sel);
  }

  // Format toggle: Video (MP4) / Audio (MP3)
  let format: 'video' | 'audio' = 'video';
  const toggle = document.createElement('div');
  toggle.className = 'toggle';
  const vBtn = document.createElement('button');
  vBtn.textContent = 'Video';
  vBtn.className = 'active';
  const aBtn = document.createElement('button');
  aBtn.textContent = 'Audio';
  vBtn.addEventListener('click', () => {
    format = 'video';
    vBtn.classList.add('active');
    aBtn.classList.remove('active');
  });
  aBtn.addEventListener('click', () => {
    format = 'audio';
    aBtn.classList.add('active');
    vBtn.classList.remove('active');
  });
  toggle.append(vBtn, aBtn);
  controls.append(toggle);
  card.append(controls);

  // Download button
  const btn = document.createElement('button');
  btn.className = 'btn';
  btn.textContent = 'Download';
  const hint = document.createElement('p');
  hint.className = 'hint';

  btn.addEventListener('click', () => {
    btn.disabled = true;
    btn.textContent = 'Starting…';
    chrome.runtime.sendMessage(
      {
        type: 'DOWNLOAD',
        url: it.url,
        kind: it.kind,
        title: it.title,
        format,
        variantIndex: variants.length > 1 ? selectedVariant : undefined,
      },
      (res: { ok: boolean; message?: string }) => {
        if (res?.ok) {
          btn.textContent = 'Download started ✓';
          setTimeout(() => {
            btn.disabled = false;
            btn.textContent = 'Download';
          }, 2000);
        } else {
          btn.disabled = false;
          btn.textContent = 'Download';
          hint.textContent = res?.message || 'Could not start download.';
        }
      },
    );
  });

  card.append(btn, hint);
  return card;
}

// ---- State -> UI ------------------------------------------------------------
function render(state: TabState): void {
  body.innerHTML = '';

  if (state.drm) {
    const card = document.createElement('div');
    card.className = 'card drm';
    card.innerHTML =
      '<div class="card-name">🔒 Protected content</div>' +
      '<div class="meta-row"><span class="badge grey">DRM</span></div>' +
      '<div class="protected-label" title="This page uses Widevine/PlayReady DRM. ' +
      'JIM never attempts to capture protected media.">Protected — cannot be downloaded</div>';
    body.append(card);
    return;
  }

  // Merge network detections + element srcs that aren't already covered.
  const items: NetworkDetection[] = [...state.network];
  const seen = new Set(items.map((i) => i.url));
  for (const el of state.elements) {
    if (el.src && !el.isBlob && !seen.has(el.src)) {
      seen.add(el.src);
      items.push({
        id: el.src,
        url: el.src,
        kind: 'DIRECT',
        headers: {},
        tabId: -1,
        firstSeen: Date.now(),
        title: state.meta?.title,
      });
    }
  }

  if (items.length === 0) {
    body.innerHTML =
      '<p class="placeholder">No video detected on this page yet.<br>' +
      'Press <b>play</b> — many sites don’t load the stream until playback starts.</p>';
    return;
  }

  items.forEach((it, i) => body.append(buildCard(it, i === 0 ? state.meta?.thumbnail : undefined)));
}

// ---- Active downloads (progress rows) --------------------------------------
function fmtSpeed(bps?: number): string {
  if (!bps) return '';
  return `${fmtBytes(bps)}/s`;
}
function fmtEta(sec?: number): string {
  if (sec == null || !Number.isFinite(sec) || sec <= 0) return '';
  const s = Math.round(sec);
  const m = Math.floor(s / 60);
  return m > 0 ? `${m}m ${s % 60}s` : `${s}s`;
}

function renderDownloads(list: DownloadProgress[]): void {
  const active = list.filter((d) => d.state !== 'done' && d.state !== 'canceled');
  downloadsEl.innerHTML = '';
  for (const d of active) {
    const row = document.createElement('div');
    row.className = 'dl';

    const top = document.createElement('div');
    top.className = 'dl-top';
    top.innerHTML =
      `<span class="dl-name" title="${d.filename}">${d.filename}</span>` +
      (d.state === 'error'
        ? `<span class="dl-err">${d.error || 'error'}</span>`
        : `<span class="dl-pct">${d.percent}%</span>`);

    const bar = document.createElement('div');
    bar.className = 'dl-bar';
    const fill = document.createElement('div');
    fill.className = 'dl-fill';
    fill.style.width = `${d.percent}%`;
    if (d.state === 'error') fill.style.background = '#ef4444';
    bar.append(fill);

    const info = document.createElement('div');
    info.className = 'dl-info';
    const stateLabel =
      d.state === 'downloading'
        ? `${d.segDone}/${d.segTotal} · ${fmtSpeed(d.speed)} · ${fmtEta(d.etaSec)}`
        : d.state;
    const cancel = document.createElement('button');
    cancel.className = 'dl-cancel';
    cancel.textContent = d.state === 'error' ? 'Dismiss' : 'Cancel';
    cancel.addEventListener('click', () => {
      chrome.runtime.sendMessage({ type: 'CANCEL_DOWNLOAD', id: d.id });
      void dismiss(d.id);
    });
    info.innerHTML = `<span>${stateLabel}</span>`;
    info.append(cancel);

    row.append(top, bar, info);
    downloadsEl.append(row);
  }
}

async function dismiss(id: string): Promise<void> {
  const store = await chrome.storage.session.get(DOWNLOADS_KEY);
  const list: DownloadProgress[] = store[DOWNLOADS_KEY] ?? [];
  await chrome.storage.session.set({
    [DOWNLOADS_KEY]: list.filter((d) => d.id !== id),
  });
}

async function load(): Promise<void> {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  const dl = await chrome.storage.session.get(DOWNLOADS_KEY);
  renderDownloads(dl[DOWNLOADS_KEY] ?? []);
  if (tab?.id == null) return;
  const res = await chrome.storage.session.get(`tab:${tab.id}`);
  render((res[`tab:${tab.id}`] as TabState) ?? { network: [], elements: [], drm: false });
}

void load();
chrome.storage.session.onChanged.addListener(() => void load());
