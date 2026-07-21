# JIM Video Catcher

A Microsoft Edge (Manifest V3) extension that detects video playing on the current
page and shows its name, size, resolution and format with a Download button.

> **Status:** Module 6 — settings, polish & packaging. Full options page
> (subfolder, filename templates `{title}/{resolution}/{date}`, default
> format/quality, segment concurrency, livestream cap, badge toggle, per-site
> disable), download history with clear, `Ctrl+Shift+Y` shortcut, right-click
> "Download this video with JIM" on video elements, and robustness caps.
>
> Previous — Module 5 — download engine. Direct files and **HLS streams**
> download for real: segments fetched with concurrency 6 + retry in an offscreen
> document (so the download survives the MV3 worker sleeping), assembled into a
> playable `.ts`/`.mp4`, with a live progress bar (%, speed, ETA, cancel).
> Referer/Cookie/UA are replayed via declarativeNetRequest to avoid 403s.
> DASH muxing and MP3 transcoding are deferred to the Module 7 native host.

### Header replay — what Edge allows
`chrome.downloads` cannot set Referer/Cookie/User-Agent directly. Module 5 instead
installs short-lived `declarativeNetRequest` session rules that set those request
headers for the media host, which covers the common 403 case. Cookies for the media
host are also sent automatically because fetches use `credentials: 'include'`.

### Surviving the service-worker being killed
The worker only *starts* a download; all fetching/assembly runs in the **offscreen
document**, which is not subject to the ~30s idle-kill. Progress is written directly
to `storage.session`, so the popup shows live state even if the worker has slept.

## Module 2 test plan

Test each category and confirm the described behavior:

| Site type | Example | Correct behavior |
|---|---|---|
| Direct file | a page linking a plain `.mp4` | Item listed as `DIRECT` with a real byte size; badge shows `1` |
| HLS | an `.m3u8` test stream | Item listed as `HLS` after you press play; size deferred to Module 3 |
| DASH | an `.mpd` test stream | Item listed as `DASH` after play |
| Login-required | a site needing sign-in | Detection still works; captured Cookie/Referer make Module 5 downloads succeed |
| DRM | a Widevine/PlayReady page | Popup shows "Protected — cannot be downloaded"; badge greyed, count 0; no capture |

Nothing appears until playback starts on most sites — that is expected.

## Build & load (short version)

```bash
cd jim-video-catcher
npm install
npm run build      # outputs ./dist
```

Then in Edge: open `edge://extensions`, turn on **Developer mode**, click
**Load unpacked**, and select the `jim-video-catcher/dist` folder.

Use `npm run dev` to rebuild on save; click the **reload** ↻ icon on the
extension card in `edge://extensions` after each rebuild.

See the module checklist for the full, click-by-click walkthrough.

## Module 7 — native yt-dlp helper (optional, unlocks YouTube/DASH/MP3)

The pure extension can't download adaptive sites like YouTube. The **native helper**
adds a small Node program that drives **yt-dlp** (+ ffmpeg) and talks to the extension
over Chrome native messaging. With it installed, YouTube (incl. your own private
uploads, via your Edge login), DASH, and real MP3 all work; without it, the extension
falls back to its built-in engine and never shows a broken state.

**Install (Windows):**
1. Keep the `native-host` folder somewhere stable (not Temp).
2. Right-click `native-host/install-host.ps1` → **Run with PowerShell**. It downloads
   `yt-dlp.exe` + `ffmpeg.exe`, writes the native-messaging manifest, and registers it
   under `HKCU\Software\Microsoft\Edge\NativeMessagingHosts\com.jim.videocatcher`.
3. Fully quit and reopen Edge. The popup shows **“Native helper: on”**.

Requires Node.js on PATH (same one used to build). The extension's ID is pinned via a
`key` in the manifest (`gnaljceaknidngnpjgkpbaeoimkkfkdo`) so the host can whitelist it.

**Uninstall:** run `native-host/uninstall-host.ps1`, then delete the folder.

**Store note:** bundling yt-dlp is not publishable on the Edge/Chrome stores — the
native helper is self-distributed only. The pure extension is the store-candidate half.

## What it can and cannot download

| Source | Works in the pure extension? |
|---|---|
| Direct `.mp4` / `.webm` / `.mkv` / `.m4a` | ✅ Yes |
| Standard **HLS** (`.m3u8`) | ✅ Yes |
| Standard **DASH** (`.mpd`, single track) | ⚠️ Detected; muxing separate A/V needs the native host |
| **YouTube** (incl. your own private uploads) | ❌ No — needs the Module 7 native helper |
| Netflix / Prime / other **DRM** | ❌ Never (protected, by design) |

**Why YouTube can't work in the browser:** YouTube serves video as adaptive DASH
over `googlevideo.com` — separate audio/video, chunked, behind rotating tokens and a
throttling parameter (`n`) that must be de-scrambled by executing YouTube's own player
JavaScript. There is no single downloadable URL to capture; the only requests visible
are tiny chunks/handshakes. Defeating this reliably is what **yt-dlp** does, which is
why it lives in the optional **Module 7 native host**. When JIM detects such a site it
says so plainly instead of listing useless chunks.

## Testing

Pure logic (classifier, HLS/DASH parsing, filename templates) has unit tests:
```bash
npm test
```

## Packaging & distribution

**Zip for sharing/backup** (a loadable copy, no store):
```bash
cd jim-video-catcher
npm run build
cd dist && zip -r ../jim-video-catcher.zip .
```
Anyone can then `edge://extensions` → Developer mode → **Load unpacked** on the
unzipped `dist`.

**Load it permanently:** an unpacked extension stays loaded across restarts as long
as its folder isn't deleted and Developer mode stays on. Keep the `dist` folder
somewhere stable (not in Downloads/Temp).

### Edge Add-ons store — the honest state

This category is **hard to publish**. Both the Microsoft Edge Add-ons store and the
Chrome Web Store restrict tools that download media from sites whose terms prohibit
it. Realistically:

- **What can pass review:** a downloader scoped to *direct media files* and to
  *content the user owns or self-hosts*, with no built-in targeting of streaming
  services, no DRM circumvention, and clear user-consent + legal notices (all of
  which this extension has).
- **What will get it rejected:** broad `<all_urls>` host permissions framed as a
  general "download from any site" tool, anything that looks aimed at YouTube/Netflix,
  and the Module 7 yt-dlp native host (bundling yt-dlp is a near-certain rejection).
- **To have a realistic chance:** narrow `host_permissions` to the sites you actually
  support, drop the generic streaming-site framing, keep DRM detection/refusal
  prominent, and ship the native-host build **outside** the store (self-distributed).

For personal use, **Load unpacked is the intended path** and needs no store approval.

## Legal

You are responsible for complying with the terms of service and copyright law of
the sites you use this on. DRM-protected content is detected and never captured.
