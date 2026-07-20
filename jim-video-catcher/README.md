# JIM Video Catcher

A Microsoft Edge (Manifest V3) extension that detects video playing on the current
page and shows its name, size, resolution and format with a Download button.

> **Status:** Module 4 — popup UI. Product-grade cards (thumbnail, name, badges,
> quality dropdown, Video/Audio toggle, Download button), DRM greyed out, empty
> state, first-run legal notice, options page, light/dark themes. Direct-file
> downloads work now; streams/MP3 arrive with the Module 5 engine.

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
