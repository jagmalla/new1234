#!/usr/bin/env python3
# JIM Video Catcher - Native Helper (Module 7), single-file Python edition.
#
# Two modes, auto-detected:
#   * Double-clicked in Windows  -> opens a small GUI app (install / update / uninstall).
#   * Launched by Edge/Chrome    -> runs as the native-messaging host driving yt-dlp.
#
# Edge passes the calling extension origin (chrome-extension://...) as an argument;
# when that is present we run the stdio host, otherwise we show the window.

import sys
import os
import json
import struct
import subprocess

HOST_NAME = "com.jim.videocatcher"
EXTENSION_ID = "gnaljceaknidngnpjgkpbaeoimkkfkdo"
HOST_VERSION = "1.0.0"
HERE = os.path.dirname(os.path.abspath(__file__))
YTDLP = os.path.join(HERE, "yt-dlp.exe")
FFMPEG = os.path.join(HERE, "ffmpeg.exe")


def is_host_mode() -> bool:
    return any(a.startswith("chrome-extension://") for a in sys.argv[1:])


# ===========================================================================
# NATIVE MESSAGING HOST MODE
# ===========================================================================

def _send(obj) -> None:
    data = json.dumps(obj).encode("utf-8")
    sys.stdout.buffer.write(struct.pack("<I", len(data)))
    sys.stdout.buffer.write(data)
    sys.stdout.buffer.flush()


def _read():
    raw = sys.stdin.buffer.read(4)
    if len(raw) < 4:
        return None
    length = struct.unpack("<I", raw)[0]
    body = sys.stdin.buffer.read(length)
    return json.loads(body.decode("utf-8"))


def _ytdlp_path() -> str:
    return YTDLP if os.path.exists(YTDLP) else "yt-dlp"


def _ytdlp_version():
    try:
        out = subprocess.run(
            [_ytdlp_path(), "--version"],
            capture_output=True, text=True, creationflags=_no_window()
        )
        return out.stdout.strip() or None
    except Exception:
        return None


def _no_window() -> int:
    # Prevent console windows from popping up for child processes on Windows.
    return getattr(subprocess, "CREATE_NO_WINDOW", 0)


def _run_download(msg) -> None:
    if msg.get("drm"):
        _send({"type": "error", "message": "Refused: DRM-protected content."})
        return
    url = str(msg.get("url", ""))
    if not (url.startswith("http://") or url.startswith("https://")):
        _send({"type": "error", "message": "Invalid URL."})
        return

    out_dir = msg.get("outDir") or os.path.join(
        os.environ.get("USERPROFILE", HERE), "Downloads"
    )
    args = [
        _ytdlp_path(), url,
        "--newline", "--no-playlist", "--restrict-filenames",
        "-o", os.path.join(out_dir, "%(title)s.%(ext)s"),
    ]
    if msg.get("cookiesFromBrowser") is not False:
        args += ["--cookies-from-browser", "edge"]
    if os.path.exists(FFMPEG):
        args += ["--ffmpeg-location", HERE]

    if msg.get("format") == "audio":
        args += ["-x", "--audio-format", "mp3", "--audio-quality", "0"]
    else:
        h = msg.get("quality")
        if isinstance(h, (int, float)) and h > 0:
            args += ["-f", f"bv*[height<={int(h)}]+ba/b[height<={int(h)}]/b"]
        else:
            args += ["-f", "bv*+ba/b"]
        args += ["--merge-output-format", "mp4"]

    import re
    prog = re.compile(
        r"\[download\]\s+([\d.]+)% of\s+~?\s*([\d.]+\w+)"
        r"(?:\s+at\s+([\d.]+\w+/s))?(?:\s+ETA\s+([\d:]+))?"
    )
    last_file = ""
    try:
        p = subprocess.Popen(
            args, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
            text=True, creationflags=_no_window()
        )
    except Exception as e:
        _send({"type": "error", "message": f"Could not launch yt-dlp: {e}"})
        return

    for line in p.stdout:
        m = prog.search(line)
        if m:
            _send({
                "type": "progress",
                "percent": int(float(m.group(1))),
                "totalText": m.group(2) or "",
                "speedText": m.group(3) or "",
                "etaText": m.group(4) or "",
            })
        d = re.search(r"\[download\] Destination:\s+(.+)", line) or \
            re.search(r'\[Merger\] Merging formats into "(.+)"', line)
        if d:
            last_file = d.group(1).strip()

    err = p.stderr.read()
    code = p.wait()
    if code == 0:
        _send({"type": "done", "file": last_file})
    else:
        _send({"type": "error", "message": (err or "").strip() or f"yt-dlp exited {code}"})


def run_host() -> None:
    _send({"type": "ready", "hostVersion": HOST_VERSION})
    while True:
        try:
            msg = _read()
        except Exception:
            break
        if msg is None:
            break
        t = msg.get("type")
        if t == "ping":
            _send({"type": "pong", "hostVersion": HOST_VERSION, "ytdlp": _ytdlp_version()})
        elif t == "download":
            _run_download(msg)
        elif t == "update":
            try:
                out = subprocess.run(
                    [_ytdlp_path(), "-U"], capture_output=True, text=True,
                    creationflags=_no_window()
                )
                _send({"type": "updated", "message": (out.stdout + out.stderr).strip()})
            except Exception as e:
                _send({"type": "error", "message": str(e)})
        else:
            _send({"type": "error", "message": f"unknown command: {t}"})


# ===========================================================================
# GUI MODE (double-click)
# ===========================================================================

def manifest_path() -> str:
    return os.path.join(HERE, f"{HOST_NAME}.json")


def launcher_path() -> str:
    return os.path.join(HERE, "run_host.bat")


def python_exe() -> str:
    exe = sys.executable
    # Use console python for the host (reliable stdio), not pythonw.
    low = exe.lower()
    if low.endswith("pythonw.exe"):
        cand = exe[:-len("pythonw.exe")] + "python.exe"
        if os.path.exists(cand):
            return cand
    return exe


def is_registered() -> bool:
    try:
        import winreg
        key = winreg.OpenKey(
            winreg.HKEY_CURRENT_USER,
            r"Software\Microsoft\Edge\NativeMessagingHosts\\" + HOST_NAME,
        )
        val, _ = winreg.QueryValueEx(key, None)
        return bool(val) and os.path.exists(val)
    except Exception:
        return False


def run_gui() -> None:
    import tkinter as tk
    from tkinter import ttk, scrolledtext
    import threading
    import urllib.request
    import zipfile
    import tempfile

    root = tk.Tk()
    root.title("JIM Video Catcher — Helper")
    root.geometry("560x460")
    try:
        root.configure(bg="#f4f4f6")
    except Exception:
        pass

    header = tk.Label(root, text="JIM Video Catcher — Native Helper",
                      font=("Segoe UI", 14, "bold"), bg="#f4f4f6")
    header.pack(pady=(14, 2))
    sub = tk.Label(root, text="Enables YouTube / DASH / MP3 downloads via yt-dlp",
                   font=("Segoe UI", 9), fg="#555", bg="#f4f4f6")
    sub.pack()

    status_var = tk.StringVar(value="Checking…")
    status = tk.Label(root, textvariable=status_var, font=("Segoe UI", 10, "bold"),
                      bg="#f4f4f6")
    status.pack(pady=(10, 4))

    log = scrolledtext.ScrolledText(root, height=13, font=("Consolas", 9),
                                    wrap="word")
    log.pack(fill="both", expand=True, padx=14, pady=8)

    def logln(s: str) -> None:
        log.insert("end", s + "\n")
        log.see("end")
        root.update_idletasks()

    def refresh_status() -> None:
        yt = os.path.exists(YTDLP)
        reg = is_registered()
        if yt and reg:
            status_var.set("● Installed and registered")
            status.config(fg="#16a34a")
        elif yt or reg:
            status_var.set("● Partly installed — click Install to finish")
            status.config(fg="#d97706")
        else:
            status_var.set("● Not installed — click Install")
            status.config(fg="#b91c1c")

    def download(url: str, dest: str) -> None:
        logln(f"Downloading {os.path.basename(dest)} …")
        with urllib.request.urlopen(url) as r, open(dest, "wb") as f:
            total = int(r.headers.get("Content-Length", 0))
            got = 0
            while True:
                chunk = r.read(65536)
                if not chunk:
                    break
                f.write(chunk)
                got += len(chunk)
                if total:
                    logln_replace(f"  {os.path.basename(dest)}: {got*100//total}%")
        logln(f"  {os.path.basename(dest)}: done")

    def logln_replace(s: str) -> None:
        # Overwrite the last line for a simple progress feel.
        log.delete("end-2l", "end-1l")
        log.insert("end", s + "\n")
        log.see("end")
        root.update_idletasks()

    def write_launcher_and_manifest() -> None:
        with open(launcher_path(), "w", encoding="utf-8") as f:
            f.write("@echo off\r\n")
            f.write(f'"{python_exe()}" "{os.path.abspath(__file__)}" %*\r\n')
        manifest = {
            "name": HOST_NAME,
            "description": "JIM Video Catcher native helper (yt-dlp)",
            "path": launcher_path(),
            "type": "stdio",
            "allowed_origins": [f"chrome-extension://{EXTENSION_ID}/"],
        }
        with open(manifest_path(), "w", encoding="utf-8") as f:
            json.dump(manifest, f, indent=2)
        logln("Wrote launcher + manifest.")

    def register() -> None:
        import winreg
        for base_path in (
            r"Software\Microsoft\Edge\NativeMessagingHosts",
            r"Software\Google\Chrome\NativeMessagingHosts",
        ):
            key = winreg.CreateKey(winreg.HKEY_CURRENT_USER, base_path + "\\" + HOST_NAME)
            winreg.SetValueEx(key, None, 0, winreg.REG_SZ, manifest_path())
            winreg.CloseKey(key)
        logln("Registered for Edge and Chrome (current user).")

    def unregister() -> None:
        import winreg
        for base_path in (
            r"Software\Microsoft\Edge\NativeMessagingHosts",
            r"Software\Google\Chrome\NativeMessagingHosts",
        ):
            try:
                winreg.DeleteKey(winreg.HKEY_CURRENT_USER, base_path + "\\" + HOST_NAME)
            except FileNotFoundError:
                pass
        logln("Removed registry keys.")

    def do_install() -> None:
        for b in buttons:
            b.config(state="disabled")

        def work():
            try:
                if not os.path.exists(YTDLP):
                    download(
                        "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp.exe",
                        YTDLP,
                    )
                else:
                    logln("yt-dlp.exe already present.")

                if not os.path.exists(FFMPEG):
                    try:
                        tmpzip = os.path.join(tempfile.gettempdir(), "jim_ffmpeg.zip")
                        download(
                            "https://github.com/GyanD/codexffmpeg/releases/latest/"
                            "download/ffmpeg-release-essentials.zip",
                            tmpzip,
                        )
                        logln("Extracting ffmpeg…")
                        with zipfile.ZipFile(tmpzip) as z:
                            for n in z.namelist():
                                if n.endswith("/bin/ffmpeg.exe"):
                                    with z.open(n) as src, open(FFMPEG, "wb") as dst:
                                        dst.write(src.read())
                                    break
                        os.remove(tmpzip)
                        logln("ffmpeg.exe installed.")
                    except Exception as e:
                        logln(f"WARNING: ffmpeg failed ({e}). MP3/merge may not work.")
                else:
                    logln("ffmpeg.exe already present.")

                write_launcher_and_manifest()
                register()
                logln("")
                logln("DONE. Fully quit Edge (all windows) and reopen it,")
                logln("then open the JIM popup — it should say 'Native helper: on'.")
            except Exception as e:
                logln(f"ERROR: {e}")
            finally:
                refresh_status()
                for b in buttons:
                    b.config(state="normal")

        threading.Thread(target=work, daemon=True).start()

    def do_update() -> None:
        def work():
            if not os.path.exists(YTDLP):
                logln("yt-dlp not installed yet — click Install first.")
                return
            logln("Updating yt-dlp…")
            try:
                out = subprocess.run([YTDLP, "-U"], capture_output=True, text=True,
                                     creationflags=_no_window())
                logln((out.stdout + out.stderr).strip() or "Done.")
            except Exception as e:
                logln(f"ERROR: {e}")
        threading.Thread(target=work, daemon=True).start()

    def do_uninstall() -> None:
        unregister()
        logln("Unregistered. You may delete this folder to remove yt-dlp/ffmpeg too.")
        refresh_status()

    btnbar = tk.Frame(root, bg="#f4f4f6")
    btnbar.pack(pady=(0, 12))
    b_install = ttk.Button(btnbar, text="Install / Register", command=do_install)
    b_update = ttk.Button(btnbar, text="Update yt-dlp", command=do_update)
    b_uninstall = ttk.Button(btnbar, text="Uninstall", command=do_uninstall)
    b_install.grid(row=0, column=0, padx=6)
    b_update.grid(row=0, column=1, padx=6)
    b_uninstall.grid(row=0, column=2, padx=6)
    buttons = [b_install, b_update, b_uninstall]

    logln(f"Folder: {HERE}")
    logln(f"Extension ID: {EXTENSION_ID}")
    logln("Click 'Install / Register' to set up the helper.\n")
    refresh_status()
    root.mainloop()


# ===========================================================================

if __name__ == "__main__":
    if is_host_mode():
        run_host()
    else:
        run_gui()
