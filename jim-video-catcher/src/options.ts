// Options page — Module 4 placeholder (expanded in Module 6).

const input = document.getElementById('subfolder') as HTMLInputElement;
const saved = document.getElementById('saved') as HTMLParagraphElement;

chrome.storage.local.get('subfolder').then((r) => {
  if (typeof r.subfolder === 'string') input.value = r.subfolder;
});

let t: number | undefined;
input.addEventListener('input', () => {
  void chrome.storage.local.set({ subfolder: input.value });
  saved.hidden = false;
  if (t) clearTimeout(t);
  t = setTimeout(() => (saved.hidden = true), 1200) as unknown as number;
});
