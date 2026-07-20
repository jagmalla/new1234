# JIM Video Catcher

A Microsoft Edge (Manifest V3) extension that detects video playing on the current
page and shows its name, size, resolution and format with a Download button.

> **Status:** Module 1 — empty working skeleton. It loads into Edge and opens a popup.
> Detection, metadata, UI and downloading arrive in later modules.

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
