#!/usr/bin/env node
// JIM Video Catcher — native messaging host (Module 7).
// Speaks Chrome/Edge native messaging over stdio and drives yt-dlp (+ ffmpeg).
// Uses the browser's own cookies (--cookies-from-browser edge) so login-only and
// private videos resolve. Never attempts DRM-protected content.

import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import process from 'node:process';

const HERE = dirname(fileURLToPath(import.meta.url));
const HOST_VERSION = '1.0.0';

// Prefer binaries shipped next to this host; fall back to PATH.
const YTDLP = existsSync(join(HERE, 'yt-dlp.exe')) ? join(HERE, 'yt-dlp.exe') : 'yt-dlp';
const FFMPEG_DIR = existsSync(join(HERE, 'ffmpeg.exe')) ? HERE : null;

// ---- native messaging framing (4-byte LE length prefix + UTF-8 JSON) --------

function send(msg) {
  const json = Buffer.from(JSON.stringify(msg), 'utf8');
  const len = Buffer.alloc(4);
  len.writeUInt32LE(json.length, 0);
  process.stdout.write(Buffer.concat([len, json]));
}

let buf = Buffer.alloc(0);
process.stdin.on('data', (chunk) => {
  buf = Buffer.concat([buf, chunk]);
  for (;;) {
    if (buf.length < 4) return;
    const len = buf.readUInt32LE(0);
    if (buf.length < 4 + len) return;
    const body = buf.subarray(4, 4 + len);
    buf = buf.subarray(4 + len);
    let msg;
    try {
      msg = JSON.parse(body.toString('utf8'));
    } catch {
      send({ type: 'error', message: 'bad message' });
      continue;
    }
    handle(msg);
  }
});
process.stdin.on('end', () => process.exit(0));

// ---- command handling -------------------------------------------------------

function ytdlpVersion() {
  return new Promise((resolve) => {
    const p = spawn(YTDLP, ['--version']);
    let out = '';
    p.stdout.on('data', (d) => (out += d));
    p.on('error', () => resolve(null));
    p.on('close', () => resolve(out.trim() || null));
  });
}

async function handle(msg) {
  if (msg.type === 'ping') {
    send({ type: 'pong', hostVersion: HOST_VERSION, ytdlp: await ytdlpVersion() });
    return;
  }
  if (msg.type === 'update') {
    const p = spawn(YTDLP, ['-U']);
    let out = '';
    p.stdout.on('data', (d) => (out += d));
    p.stderr.on('data', (d) => (out += d));
    p.on('error', (e) => send({ type: 'error', message: String(e) }));
    p.on('close', () => send({ type: 'updated', message: out.trim() }));
    return;
  }
  if (msg.type === 'download') {
    runDownload(msg);
    return;
  }
  send({ type: 'error', message: `unknown command: ${msg.type}` });
}

function runDownload(msg) {
  // Refuse anything the extension flagged as protected.
  if (msg.drm) {
    send({ type: 'error', message: 'Refused: DRM-protected content.' });
    return;
  }
  const url = String(msg.url || '');
  if (!/^https?:\/\//i.test(url)) {
    send({ type: 'error', message: 'Invalid URL.' });
    return;
  }

  const outDir = msg.outDir || join(process.env.USERPROFILE || HERE, 'Downloads');
  const args = [
    url,
    '--newline',
    '--no-playlist',
    '--restrict-filenames',
    '-o',
    join(outDir, '%(title)s.%(ext)s'),
  ];

  // Use the browser's cookies so private / login-only videos resolve.
  if (msg.cookiesFromBrowser !== false) args.push('--cookies-from-browser', 'edge');
  if (FFMPEG_DIR) args.push('--ffmpeg-location', FFMPEG_DIR);

  if (msg.format === 'audio') {
    args.push('-x', '--audio-format', 'mp3', '--audio-quality', '0');
  } else {
    const h = Number(msg.quality);
    if (Number.isFinite(h) && h > 0) {
      args.push('-f', `bv*[height<=${h}]+ba/b[height<=${h}]/b`);
    } else {
      args.push('-f', 'bv*+ba/b');
    }
    args.push('--merge-output-format', 'mp4');
  }

  const p = spawn(YTDLP, args);
  let lastFile = '';

  p.stdout.on('data', (d) => {
    const text = String(d);
    for (const line of text.split(/\r?\n/)) {
      const m = /\[download\]\s+([\d.]+)% of\s+~?\s*([\d.]+\w+)(?:\s+at\s+([\d.]+\w+\/s))?(?:\s+ETA\s+([\d:]+))?/.exec(line);
      if (m) {
        send({
          type: 'progress',
          percent: Math.round(parseFloat(m[1])),
          totalText: m[2],
          speedText: m[3] || '',
          etaText: m[4] || '',
        });
      }
      const dest = /\[download\] Destination:\s+(.+)/.exec(line) || /\[Merger\] Merging formats into "(.+)"/.exec(line);
      if (dest) lastFile = dest[1].trim();
    }
  });

  let err = '';
  p.stderr.on('data', (d) => {
    err += d;
  });
  p.on('error', (e) => send({ type: 'error', message: String(e) }));
  p.on('close', (code) => {
    if (code === 0) send({ type: 'done', file: lastFile });
    else send({ type: 'error', message: err.trim() || `yt-dlp exited ${code}` });
  });
}

send({ type: 'ready', hostVersion: HOST_VERSION });
