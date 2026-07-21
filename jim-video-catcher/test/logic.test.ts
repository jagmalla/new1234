// Focused unit tests for the pure logic (no browser needed).
import { classify } from '../src/lib/classify';
import { parseHls, parseHlsSegments, parseIsoDuration, estimateBytes } from '../src/lib/manifest-parse';
import { applyTemplate, withSubfolder } from '../src/lib/settings';

let pass = 0;
let fail = 0;
function eq(name: string, got: unknown, want: unknown): void {
  const g = JSON.stringify(got);
  const w = JSON.stringify(want);
  if (g === w) {
    pass++;
  } else {
    fail++;
    console.error(`FAIL ${name}\n  got  ${g}\n  want ${w}`);
  }
}

// --- classify ---
eq('mp4 ext', classify('https://h/a.mp4'), 'DIRECT');
eq('m3u8 ext', classify('https://h/a.m3u8?x=1'), 'HLS');
eq('mpd ext', classify('https://h/a.mpd'), 'DASH');
eq('ts seg', classify('https://h/seg001.ts'), 'SEGMENT');
eq('m4s seg', classify('https://h/seg.m4s'), 'SEGMENT');
eq('ct hls', classify('https://h/playlist', 'application/vnd.apple.mpegurl'), 'HLS');
eq('ct mp4', classify('https://h/stream', 'video/mp4'), 'DIRECT');
eq('ignore html', classify('https://h/page', 'text/html'), 'IGNORE');

// --- adaptive junk regex (mirrors background) ---
const ADAPTIVE_JUNK = /(\.googlevideo\.com\/|\/videoplayback\?|\.c\.youtube\.com\/)/i;
eq('yt googlevideo', ADAPTIVE_JUNK.test('https://r5---sn-x.googlevideo.com/videoplayback?mime=video/mp4'), true);
eq('normal not junk', ADAPTIVE_JUNK.test('https://cdn.example.com/a.mp4'), false);

// --- HLS master parse ---
const master = `#EXTM3U
#EXT-X-STREAM-INF:BANDWIDTH=800000,RESOLUTION=640x360,CODECS="avc1"
360/index.m3u8
#EXT-X-STREAM-INF:BANDWIDTH=6222000,RESOLUTION=1920x1080,CODECS="avc1"
1080/index.m3u8`;
const m = parseHls(master, 'https://h/master.m3u8');
eq('master isMaster', m.isMaster, true);
eq('master count', m.variants.length, 2);
eq('master sorted highest first', m.variants[0].height, 1080);
eq('master resolves url', m.variants[0].url, 'https://h/1080/index.m3u8');

// --- HLS media playlist duration + segments ---
const media = `#EXTM3U
#EXT-X-MAP:URI="init.mp4"
#EXTINF:6.0,
seg0.m4s
#EXTINF:6.0,
seg1.m4s`;
const seg = parseHlsSegments(media, 'https://h/720/index.m3u8');
eq('seg init resolved', seg.initUrl, 'https://h/720/init.mp4');
eq('seg count', seg.segmentUrls.length, 2);
eq('seg fmp4', seg.isFmp4, true);
eq('media duration', parseHls(media, 'https://h/720/index.m3u8').durationSec, 12);

// --- ISO duration + estimate ---
eq('iso duration', parseIsoDuration('PT1H2M3S'), 3723);
eq('estimate bytes', estimateBytes(8_000_000, 10), 10_000_000);

// --- filename template ---
eq('template full', applyTemplate('{title} - {resolution} ({date})',
  { title: 'My Vid', resolution: '720p', date: '2026-07-21' }, '.mp4'),
  'My Vid - 720p (2026-07-21).mp4');
eq('template missing resolution collapses', applyTemplate('{title} - {resolution}',
  { title: 'My Vid', resolution: '', date: '2026-07-21' }, '.mp4'),
  'My Vid.mp4');
eq('template sanitizes', applyTemplate('{title}',
  { title: 'a/b:c*?<>|d', date: '2026-07-21' }, '.mp4'),
  'abcd.mp4');
eq('subfolder', withSubfolder('JIM Video Catcher', 'x.mp4'), 'JIM Video Catcher/x.mp4');
eq('subfolder empty', withSubfolder('', 'x.mp4'), 'x.mp4');

console.log(`\n${pass} passed, ${fail} failed`);
if (fail > 0) process.exit(1);
