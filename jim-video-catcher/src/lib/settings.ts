// User settings (Module 6). Stored in chrome.storage.local under 'settings'.

export interface Settings {
  subfolder: string;             // '' saves to the Downloads root
  filenameTemplate: string;      // tokens: {title} {resolution} {date}
  defaultFormat: 'video' | 'audio';
  defaultQuality: 'highest' | 'lowest';
  segmentConcurrency: number;    // parallel HLS segment fetches
  maxSegments: number;           // safeguard against endless livestreams
  badge: boolean;                // show the toolbar count
  theme: 'system' | 'light' | 'dark';
  disabledSites: string[];       // hostnames where detection is off
}

export const DEFAULT_SETTINGS: Settings = {
  subfolder: 'JIM Video Catcher',
  filenameTemplate: '{title}',
  defaultFormat: 'video',
  defaultQuality: 'highest',
  segmentConcurrency: 6,
  maxSegments: 20000,
  badge: true,
  theme: 'system',
  disabledSites: [],
};

export async function getSettings(): Promise<Settings> {
  const r = await chrome.storage.local.get('settings');
  return { ...DEFAULT_SETTINGS, ...(r.settings as Partial<Settings> | undefined) };
}

export async function setSettings(patch: Partial<Settings>): Promise<Settings> {
  const next = { ...(await getSettings()), ...patch };
  await chrome.storage.local.set({ settings: next });
  return next;
}

export interface HistoryEntry {
  title: string;
  filename: string;
  url: string;
  kind: string;
  sizeBytes?: number;
  when: number;
}

const HISTORY_KEY = 'history';
const HISTORY_MAX = 200;

export async function addHistory(entry: HistoryEntry): Promise<void> {
  const r = await chrome.storage.local.get(HISTORY_KEY);
  const list: HistoryEntry[] = r[HISTORY_KEY] ?? [];
  list.unshift(entry);
  await chrome.storage.local.set({ [HISTORY_KEY]: list.slice(0, HISTORY_MAX) });
}

export async function getHistory(): Promise<HistoryEntry[]> {
  const r = await chrome.storage.local.get(HISTORY_KEY);
  return r[HISTORY_KEY] ?? [];
}

export async function clearHistory(): Promise<void> {
  await chrome.storage.local.remove(HISTORY_KEY);
}

// Apply the filename template. An EMPTY token takes its adjacent separator /
// brackets with it, so "{title} - {resolution}" with no resolution becomes just
// the title — while separators between PRESENT tokens are preserved.
export function applyTemplate(
  template: string,
  tokens: { title: string; resolution?: string; date?: string },
  ext: string,
): string {
  const values: Record<string, string> = {
    title: tokens.title || 'video',
    resolution: tokens.resolution ?? '',
    date: tokens.date ?? new Date().toISOString().slice(0, 10),
  };

  let name = template;
  for (const [key, val] of Object.entries(values)) {
    const tok = `\\{${key}\\}`;
    if (val) {
      name = name.replace(new RegExp(tok, 'g'), val);
    } else {
      // Consume a leading separator/open-bracket and an optional close-bracket.
      name = name.replace(new RegExp(`\\s*[-_]?\\s*\\(?${tok}\\)?`, 'g'), '');
    }
  }

  name = name
    .replace(/[\\/:*?"<>|]/g, '')
    .replace(/\s{2,}/g, ' ')
    .replace(/^[\s_-]+|[\s_-]+$/g, '')
    .trim();
  if (!name) name = 'video';
  const hasExt = new RegExp(`${ext.replace('.', '\\.')}$`, 'i').test(name);
  return hasExt ? name : name + ext;
}

// Prefix a subfolder for chrome.downloads (forward slashes only).
export function withSubfolder(subfolder: string, filename: string): string {
  const sf = subfolder.replace(/[\\]/g, '/').replace(/^\/+|\/+$/g, '').trim();
  return sf ? `${sf}/${filename}` : filename;
}
