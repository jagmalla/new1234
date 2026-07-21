# JIM Video Catcher — Complete Install Guide

A start-to-finish guide for a brand-new Windows PC. No experience needed. Follow the
steps in order and don't skip the **YOU SHOULD SEE** checkpoints.

---

## 1. What this is

JIM Video Catcher is a Microsoft Edge add-on that spots the video playing on a web page
and shows you its name, size, and quality with a **Download** button. It downloads plain
video files and normal streams on its own; an optional helper app adds support for
YouTube (including your own private uploads), plus MP3 audio. You are responsible for
only downloading content you have the right to — it never touches DRM-protected video
(Netflix, Prime, etc.).

There are **two pieces**:
- **The extension** — loads into Edge. Enough for direct files and normal streams.
- **The helper app** (optional) — a small program for YouTube / MP3.

You can stop after the extension if you don't need YouTube.

---

## 2. Install the programs you need first

### 2a. Microsoft Edge
Already on Windows. If missing, get it from https://www.microsoft.com/edge.

### 2b. Node.js — ONLY if you will build from source
If you were given a **ready-to-load** folder (a `dist` folder), **skip this** — you don't
need Node.js. If you were given the **source code** to build, do this:

1. Open Edge and go to **https://nodejs.org**.
2. Click the big **LTS** button. A file like `node-v20.x.x-x64.msi` downloads.
3. Double-click the downloaded file.
4. Click **Next**, tick **I accept**, click **Next** on each screen, click **Install**,
   click **Yes** if asked, then **Finish**.

**YOU SHOULD SEE:** the installer closes with no error.

### 2c. Python — ONLY if you want the YouTube helper
1. Go to **https://www.python.org/downloads/**.
2. Click **Download Python 3.x**.
3. Double-click the downloaded file.
4. **IMPORTANT:** on the first screen, tick the box **“Add python.exe to PATH”** at the
   bottom.
5. Click **Install Now**, then **Yes** if asked, then **Close**.

**YOU SHOULD SEE:** a “Setup was successful” screen.

---

## 3. How to open a terminal and move around (skip if you have a ready-to-load folder)

You only need this to *build from source*.

1. Press the **Windows key**.
2. Type: `powershell`
3. Click **Windows PowerShell**.

**YOU SHOULD SEE:** a dark window with a blinking cursor.

To move into a folder, type `cd` (which means “change directory”), a space, then the
folder path in quotes, and press **Enter**. Example:
```
cd "C:\Users\YourName\Downloads\jim-video-catcher"
```
To list what's in the current folder, type `dir` and press **Enter**.

---

## 4. Get the project files into a folder

1. Put the folder you were given (either the ready-to-load `dist` folder, or the source
   folder) somewhere permanent — for example make a folder **`C:\JIM`** and put it there.
2. Do **not** leave it in Downloads or a Temp folder — if it moves later, the extension
   or helper can break.

**YOU SHOULD SEE:** e.g. `C:\JIM\jim-video-catcher` containing the files.

---

## 5. Build it (skip if you already have a `dist` folder)

Only if you have the **source**:

1. Open PowerShell (Section 3).
2. Move into the project folder:
   ```
   cd "C:\JIM\jim-video-catcher"
   ```
3. Install the parts it needs (wait for it to finish):
   ```
   npm install
   ```
   **YOU SHOULD SEE:** several lines, ending back at the cursor with no red “ERR!”.
4. Build it:
   ```
   npm run build
   ```
   **YOU SHOULD SEE:** a list of built files ending with `✓ built in …`, and a new
   **`dist`** folder appears inside the project.

---

## 6. Load the extension into Edge

1. Open **Edge**.
2. Click the address bar, type this exactly, press **Enter**:
   ```
   edge://extensions
   ```
   **YOU SHOULD SEE:** a page titled **Extensions**.
3. At the **bottom-left**, click the **Developer mode** switch so it turns **on** (blue).
   **YOU SHOULD SEE:** three buttons appear near the top.
4. Click **Load unpacked**.
5. A folder picker opens. Go into your project and click the **`dist`** folder **once**
   to select it (do **not** double-click into it). *(If you were given a ready-to-load
   folder that already contains `manifest.json` directly inside, select that folder
   instead — the one with `manifest.json` in it.)*
6. Click **Select Folder**.
   **YOU SHOULD SEE:** a card titled **JIM Video Catcher** with a version number and no
   red errors.
7. Click the **puzzle-piece** icon at Edge's top-right, then click the **eye** icon next
   to JIM Video Catcher so it's pinned to the toolbar.

**YOU SHOULD SEE:** the JIM icon in your toolbar.

---

## 7. First run

1. Click the **JIM icon** in the toolbar.

**YOU SHOULD SEE:** a panel opens with a one-time notice: *“You are responsible for
complying with the terms of service and copyright law of the sites you use this on.
DRM-protected content is never downloaded.”*

2. Click **Got it**.

---

## 8. How to use it

1. Go to a web page that has a video.
2. **Press play** on the video. (Important — many sites don't load the video until you
   press play.)
3. Click the **JIM icon**.
   **YOU SHOULD SEE:** a card with the video's name, size, and quality.
4. If there's a **quality** dropdown, pick one. Use the **Video / Audio** toggle if you
   want audio only.
5. Click **Download**.

**YOU SHOULD SEE:** a progress bar (for streams) and then the file in your **Downloads**
folder — by default inside a **JIM Video Catcher** sub-folder.

To download a video: **go to the page → press play → click the icon → read the name and
size → pick a quality → click Download.**

---

## 9. (Optional) Set up the YouTube helper

Needed only for YouTube (including your own private uploads) and MP3.

1. Make sure **Python** is installed (Section 2c), including the **Add to PATH** box.
2. Put the **native-host** folder somewhere permanent, e.g. `C:\JIM\native-host`.
3. Open that folder and **double-click `JIM_Video_Catcher_Helper.pyw`**.
   **YOU SHOULD SEE:** a window titled **JIM Video Catcher — Helper** with a red status
   line and three buttons.
4. Click **Install / Register**. Watch the log download yt-dlp and ffmpeg and register
   itself.
   **YOU SHOULD SEE:** the status turns **green: “Installed and registered”** and the log
   says **DONE**.
5. **Fully quit Edge** — close every Edge window — then reopen Edge.
6. Log into your YouTube account in Edge (needed for private videos).
7. Open a YouTube video and click the **JIM icon**.
   **YOU SHOULD SEE:** a green line **“Native helper: on”** and a card with a quality
   dropdown. Pick a quality and click **Download**.

---

## 10. How to rebuild and reload after a change (source users only)

1. In PowerShell, in the project folder, run:
   ```
   npm run build
   ```
2. Go to `edge://extensions` and click the **circular reload arrow** on the JIM card.

**YOU SHOULD SEE:** the version/updated card refresh with no errors.

---

## 11. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| The icon doesn't appear | Not pinned | Puzzle-piece icon → click the eye next to JIM |
| Popup is blank | A load glitch | `edge://extensions` → click the reload arrow on the JIM card |
| “No video detected” | Video not requested yet | Press **play**, then reopen the popup |
| Says “a video is playing but its stream wasn't captured” | Stream loaded before JIM was watching | Press **F5** to reload the page, then play again |
| Download fails with 403 | The site needs sign-in headers | Make sure you're logged into that site in Edge, then retry |
| Download stalls at 0% | The host refused a segment | Try a lower quality; for YouTube use the helper (Section 9) |
| Edge says the extension is “corrupt” or won't load | Wrong folder selected | Remove it, and Load unpacked the folder that has `manifest.json` directly inside |
| YouTube shows “needs native helper” | Helper not installed/enabled | Do Section 9; fully quit and reopen Edge afterwards |
| Helper badge stays “off” | Edge wasn't fully restarted | Close **every** Edge window (Task Manager if needed), reopen |
| `.pyw` opens Notepad | Python not installed / not associated | Install Python with “Add to PATH”, or right-click the file → Open with → Python |

---

## 12. How to uninstall cleanly

1. **Extension:** `edge://extensions` → JIM card → **Remove** → **Remove**.
2. **Helper:** double-click `JIM_Video_Catcher_Helper.pyw` → **Uninstall**, then delete
   the `native-host` folder.
3. Optionally delete the `C:\JIM` folder.

**YOU SHOULD SEE:** no JIM card in `edge://extensions` and no JIM icon in the toolbar.

---

*Only download content you have the right to. This tool never downloads DRM-protected
video, and you are responsible for complying with each site's terms of service and with
copyright law.*
