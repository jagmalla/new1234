# JIM Video Catcher — Native Helper (Module 7)

A single Python app that lets the extension download YouTube (including your own
private uploads), DASH, and real MP3 by driving **yt-dlp** + **ffmpeg**.

There is just **one file**: `JIM_Video_Catcher_Helper.pyw`.

- **Double-click it** → a small window opens (Install / Update / Uninstall).
- **Edge launches it automatically** in the background when you download — you don't
  run it by hand for downloads.

## Install (Windows)

1. Put this `native-host` folder somewhere permanent — e.g. `C:\JIM\native-host`
   (**not** Downloads or Temp; if you move it later, re-run Install).
2. Install **Python 3** if you don't have it: https://www.python.org/downloads/
   — on the first installer screen, tick **“Add python.exe to PATH”**.
3. **Double-click `JIM_Video_Catcher_Helper.pyw`.** A window titled
   “JIM Video Catcher — Helper” appears.
4. Click **Install / Register**. It downloads yt-dlp + ffmpeg, writes its launcher and
   manifest, and registers itself for Edge. Wait for **“DONE”**.
5. **Fully quit Edge** (every window) and reopen it.
6. Open the JIM popup — it should show **“Native helper: on · yt-dlp <version>”**.

## Use

- On a YouTube page, open the popup → a card appears with a quality dropdown and a
  Video/Audio toggle → **Download**. The file lands in your Downloads folder.
- Private/unlisted videos work because yt-dlp reads your **Edge** cookies — stay logged
  into that YouTube account in Edge.

## Update yt-dlp

Sites break yt-dlp often. Double-click the app and click **Update yt-dlp**.

## Uninstall

Double-click the app → **Uninstall** (removes the registry keys). Then delete this
folder to remove yt-dlp/ffmpeg.

## How it works (for the curious)

The same file runs in two modes. When Edge starts it, it passes the extension origin
(`chrome-extension://…`) as an argument; the app detects that and runs as a
native-messaging host over stdio. With no such argument (a double-click) it opens the
Tkinter GUI instead. Install writes `run_host.bat` (which calls Python on this file),
`com.jim.videocatcher.json`, and the `HKCU\…\NativeMessagingHosts\com.jim.videocatcher`
registry values for Edge and Chrome.

## Legal

Only download content you have the right to. DRM-protected content is refused. You are
responsible for complying with each site's terms of service and with copyright law.
