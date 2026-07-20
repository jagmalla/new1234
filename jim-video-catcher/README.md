# JIM Video Catcher

A Microsoft Edge (Manifest V3) extension that detects video playing on the current
page and shows its name, size, resolution and format with a Download button.

> **Status:** Module 5 — download engine. Direct files and **HLS streams**
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

## Legal

You are responsible for complying with the terms of service and copyright law of
the sites you use this on. DRM-protected content is detected and never captured.
