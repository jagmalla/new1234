# JIM Video Catcher — Native Helper (Module 7)

This small helper lets the extension download YouTube (including your own private
uploads), DASH, and real MP3 by driving **yt-dlp** + **ffmpeg**.

## Install (Windows)

1. Put this `native-host` folder somewhere permanent — e.g. `C:\JIM\native-host`.
   **Not** in Downloads or Temp.
2. Make sure **Node.js** is installed (https://nodejs.org, LTS). Verify in PowerShell:
   ```
   node -v
   ```
3. Right-click **`install-host.ps1`** → **Run with PowerShell**.
   - If Windows blocks it, open PowerShell and run:
     ```
     powershell -ExecutionPolicy Bypass -File .\install-host.ps1
     ```
4. It downloads `yt-dlp.exe` and `ffmpeg.exe`, writes the manifest, and registers the
   helper for Edge (and Chrome).
5. **Fully quit Edge** (close every window) and reopen it.
6. Open the JIM popup — it should show **“Native helper: on · yt-dlp <version>”**.

## Use

- On a YouTube page, open the popup → a card appears with a quality dropdown and a
  Video/Audio toggle → **Download**. The file lands in your Downloads folder.
- Private/unlisted videos work because yt-dlp reads your Edge cookies
  (`--cookies-from-browser edge`) — stay logged into YouTube in Edge.

## Update yt-dlp

Sites break yt-dlp often. To update, either re-run `install-host.ps1` after deleting
`yt-dlp.exe`, or run in this folder:
```
.\yt-dlp.exe -U
```

## Uninstall

Run **`uninstall-host.ps1`**, then delete this folder.

## Files

| File | What it is |
|---|---|
| `host.mjs` | The native-messaging host (Node), drives yt-dlp |
| `host.bat` | Launcher Edge invokes (runs `host.mjs` via Node) |
| `com.jim.videocatcher.json` | Native-messaging manifest template |
| `install-host.ps1` / `uninstall-host.ps1` | Installer / uninstaller |

## Legal

Only download content you have the right to. DRM-protected content is refused. You are
responsible for complying with each site's terms of service and with copyright law.
