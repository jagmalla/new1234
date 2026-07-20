// Options page (Module 6): full settings + download history.

import {
  clearHistory,
  getHistory,
  getSettings,
  setSettings,
  type Settings,
} from './lib/settings';

const $ = <T extends HTMLElement>(id: string) => document.getElementById(id) as T;

const subfolder = $<HTMLInputElement>('subfolder');
const template = $<HTMLInputElement>('template');
const format = $<HTMLSelectElement>('format');
const quality = $<HTMLSelectElement>('quality');
const segconc = $<HTMLInputElement>('segconc');
const maxseg = $<HTMLInputElement>('maxseg');
const badge = $<HTMLSelectElement>('badge');
const siteInput = $<HTMLInputElement>('site');
const sitesEl = $<HTMLUListElement>('sites');
const historyEl = $<HTMLUListElement>('history');
const savedEl = $<HTMLSpanElement>('saved');

let flashTimer: number | undefined;
function flashSaved(): void {
  savedEl.hidden = false;
  if (flashTimer) clearTimeout(flashTimer);
  flashTimer = setTimeout(() => (savedEl.hidden = true), 1200) as unknown as number;
}

async function save(patch: Partial<Settings>): Promise<void> {
  await setSettings(patch);
  flashSaved();
}

async function loadSettings(): Promise<void> {
  const s = await getSettings();
  subfolder.value = s.subfolder;
  template.value = s.filenameTemplate;
  format.value = s.defaultFormat;
  quality.value = s.defaultQuality;
  segconc.value = String(s.segmentConcurrency);
  maxseg.value = String(s.maxSegments);
  badge.value = String(s.badge);
  renderSites(s.disabledSites);
}

function renderSites(sites: string[]): void {
  sitesEl.innerHTML = '';
  if (sites.length === 0) {
    sitesEl.innerHTML = '<li class="empty">None</li>';
    return;
  }
  for (const host of sites) {
    const li = document.createElement('li');
    li.textContent = host;
    const rm = document.createElement('button');
    rm.className = 'secondary';
    rm.textContent = 'Remove';
    rm.addEventListener('click', async () => {
      const s = await getSettings();
      const next = s.disabledSites.filter((h) => h !== host);
      await save({ disabledSites: next });
      renderSites(next);
    });
    li.append(rm);
    sitesEl.append(li);
  }
}

async function renderHistory(): Promise<void> {
  const list = await getHistory();
  historyEl.innerHTML = '';
  if (list.length === 0) {
    historyEl.innerHTML = '<li class="empty">No downloads yet</li>';
    return;
  }
  for (const h of list) {
    const li = document.createElement('li');
    const name = document.createElement('span');
    name.textContent = h.filename;
    name.title = h.url;
    const when = document.createElement('span');
    when.className = 'when';
    when.textContent = new Date(h.when).toLocaleString();
    li.append(name, when);
    historyEl.append(li);
  }
}

// Wire inputs.
subfolder.addEventListener('input', () => void save({ subfolder: subfolder.value }));
template.addEventListener('input', () => void save({ filenameTemplate: template.value }));
format.addEventListener('change', () => void save({ defaultFormat: format.value as Settings['defaultFormat'] }));
quality.addEventListener('change', () => void save({ defaultQuality: quality.value as Settings['defaultQuality'] }));
segconc.addEventListener('change', () => void save({ segmentConcurrency: Number(segconc.value) }));
maxseg.addEventListener('change', () => void save({ maxSegments: Number(maxseg.value) }));
badge.addEventListener('change', () => void save({ badge: badge.value === 'true' }));

$<HTMLButtonElement>('addSite').addEventListener('click', async () => {
  const host = siteInput.value.trim().replace(/^https?:\/\//, '').split('/')[0];
  if (!host) return;
  const s = await getSettings();
  if (!s.disabledSites.includes(host)) {
    const next = [...s.disabledSites, host];
    await save({ disabledSites: next });
    renderSites(next);
  }
  siteInput.value = '';
});

$<HTMLButtonElement>('clearHistory').addEventListener('click', async () => {
  await clearHistory();
  await renderHistory();
});

void loadSettings();
void renderHistory();
