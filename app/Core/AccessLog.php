<?php
declare(strict_types=1);

namespace AutoBusiness\Core;

/**
 * Access log — records every visit to the site on its own line in a plain,
 * human-readable text file (storage/access.log) that the owner can open later
 * from the hosting file manager.
 *
 * Each line looks like:
 *
 *     (2) Ludhiana, Punjab, India | 07-07-2026 | 14:30 | 1h 05m | 203.0.113.45
 *      ^   \_____ location _____/    \__date__/   \time/  \dur./   \___ IP ___/
 *
 *   • Location — city, province (region), country, resolved from the visitor's
 *     IP via a cached geo lookup (ip-api.com). Falls back to "Unknown" when the
 *     lookup is unavailable (e.g. offline, private IP).
 *   • Date     — DD-MM-YYYY (server local date at first hit).
 *   • Time     — HH:MM, 24-hour clock (server local time at first hit).
 *   • Duration — how long the visitor stayed (updated live by a browser
 *     heartbeat), shown as "Xh YYm" or "Ym".
 *   • IP       — the visitor's IP address.
 *   • (N)      — a repeat counter prefixed ONLY when the SAME IP opens the site
 *     again: the 2nd visit is "(2)", the 3rd "(3)", and so on. The very first
 *     visit from an IP has no prefix.
 *
 * DESIGN NOTE — this class is deliberately fail-safe. Every public method is
 * wrapped so that a missing directory, an unwritable file, a locked file, or an
 * offline geo service can NEVER throw into the request and take the site down.
 * If logging cannot happen, the site simply serves the page without a log line.
 */
final class AccessLog
{
    /** Directory (project root, ABOVE the webroot) that holds the log + state. */
    private static function dir(): string
    {
        return (defined('AB_ROOT') ? AB_ROOT : dirname(__DIR__, 2)) . '/storage';
    }

    private static function logFile(): string   { return self::dir() . '/access.log'; }
    private static function stateFile(): string { return self::dir() . '/.visits.json'; }
    private static function geoFile(): string   { return self::dir() . '/.geo_cache.json'; }

    /**
     * Record a new visit. Appends one line to the log and returns a short visit
     * id (token) that the page passes back via {@see ping()} so the duration can
     * be updated as the visitor stays. Returns '' if logging is unavailable.
     */
    public static function begin(): string
    {
        try {
            if (!self::ensureDir()) {
                return '';
            }
            $ip = self::clientIp();

            $state = self::readJson(self::stateFile());
            if (!isset($state['counts']) || !is_array($state['counts'])) {
                $state['counts'] = [];
            }
            if (!isset($state['open']) || !is_array($state['open'])) {
                $state['open'] = [];
            }

            // How many times this IP has now accessed the site (1 = first time).
            $count = (int) ($state['counts'][$ip] ?? 0) + 1;
            $state['counts'][$ip] = $count;

            $loc  = self::locate($ip);
            $now  = time();
            $date = date('d-m-Y', $now);
            $time = date('H:i', $now);

            $line = self::formatLine($count, $loc, $date, $time, self::formatDuration(0), $ip);

            // Append the line; remember its 0-based index so ping() can rewrite it.
            $index = self::appendLine($line);

            $vid = bin2hex(random_bytes(6));
            if ($index !== null) {
                $state['open'][$vid] = [
                    'ip'    => $ip,
                    'line'  => $index,
                    'count' => $count,
                    'loc'   => $loc,
                    'date'  => $date,
                    'time'  => $time,
                    'start' => $now,
                    'seen'  => $now,
                ];
            }
            self::pruneOpen($state);
            self::writeJson(self::stateFile(), $state);

            return $index !== null ? $vid : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Update the duration of an open visit (called by the page heartbeat). The
     * visit's log line is rewritten in place with the elapsed time. Safe no-op
     * for an unknown/expired vid.
     */
    public static function ping(string $vid): void
    {
        try {
            if ($vid === '' || !preg_match('/^[a-f0-9]{6,32}$/', $vid)) {
                return;
            }
            if (!is_file(self::stateFile())) {
                return;
            }
            $state = self::readJson(self::stateFile());
            if (empty($state['open'][$vid]) || !is_array($state['open'][$vid])) {
                return;
            }
            $v = $state['open'][$vid];
            $now = time();
            $v['seen'] = $now;
            $state['open'][$vid] = $v;

            $secs = max(0, $now - (int) ($v['start'] ?? $now));
            $line = self::formatLine(
                (int) ($v['count'] ?? 1),
                (string) ($v['loc'] ?? 'Unknown'),
                (string) ($v['date'] ?? date('d-m-Y')),
                (string) ($v['time'] ?? date('H:i')),
                self::formatDuration($secs),
                (string) ($v['ip'] ?? '')
            );
            self::rewriteLine((int) ($v['line'] ?? -1), $line);
            self::writeJson(self::stateFile(), $state);
        } catch (\Throwable $e) {
            // fail silent
        }
    }

    // ---------------------------------------------------------------- helpers

    private static function formatLine(int $count, string $loc, string $date, string $time, string $dur, string $ip): string
    {
        $prefix = $count > 1 ? "($count) " : '';
        return $prefix . $loc . ' | ' . $date . ' | ' . $time . ' | ' . $dur . ' | ' . $ip;
    }

    private static function formatDuration(int $secs): string
    {
        $h = intdiv($secs, 3600);
        $m = intdiv($secs % 3600, 60);
        return $h > 0 ? sprintf('%dh %02dm', $h, $m) : sprintf('%dm', $m);
    }

    /** Resolve the visitor's IP, honouring common proxy / CDN headers. */
    private static function clientIp(): string
    {
        $candidates = [];
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $val = $_SERVER[$key] ?? '';
            if ($val === '') {
                continue;
            }
            // X-Forwarded-For may be a comma list "client, proxy1, proxy2".
            foreach (explode(',', (string) $val) as $part) {
                $candidates[] = trim($part);
            }
        }
        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return $candidates[0] ?? 'unknown';
    }

    /** City, Province, Country from the IP (cached). "Unknown" on failure. */
    private static function locate(string $ip): string
    {
        // Private / reserved / non-routable addresses can't be geolocated.
        if (!filter_var($ip, FILTER_VALIDATE_IP)
            || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'Local network';
        }

        $cache = self::readJson(self::geoFile());
        if (isset($cache[$ip]) && is_string($cache[$ip]) && $cache[$ip] !== '') {
            return $cache[$ip];
        }

        $loc = self::geoLookup($ip);
        if ($loc !== null) {
            $cache[$ip] = $loc;
            self::writeJson(self::geoFile(), $cache);
            return $loc;
        }
        // Do NOT cache failures — retry next time (the host may be offline now).
        return 'Unknown';
    }

    /** One geo request to ip-api.com (free, no key). Null on any problem. */
    private static function geoLookup(string $ip): ?string
    {
        $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,regionName,city';
        $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return null;
        }
        $parts = array_filter([
            (string) ($data['city'] ?? ''),
            (string) ($data['regionName'] ?? ''),
            (string) ($data['country'] ?? ''),
        ], static fn($s) => trim($s) !== '');
        return $parts === [] ? null : implode(', ', $parts);
    }

    private static function ensureDir(): bool
    {
        $dir = self::dir();
        if (is_dir($dir)) {
            return is_writable($dir);
        }
        return @mkdir($dir, 0775, true) && is_writable($dir);
    }

    /** Append a line to the log; returns its 0-based line index, or null. */
    private static function appendLine(string $line): ?int
    {
        $file = self::logFile();
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return null;
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                return null;
            }
            $contents = stream_get_contents($fh);
            // Count existing lines (each existing entry ends with \n).
            $existing = ($contents === '' || $contents === false)
                ? 0
                : substr_count($contents, "\n");
            fseek($fh, 0, SEEK_END);
            fwrite($fh, $line . "\n");
            fflush($fh);
            return $existing;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** Rewrite a single line (by 0-based index) in place. */
    private static function rewriteLine(int $index, string $line): void
    {
        if ($index < 0) {
            return;
        }
        $file = self::logFile();
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return;
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                return;
            }
            $contents = stream_get_contents($fh);
            if ($contents === false || $contents === '') {
                return;
            }
            $lines = explode("\n", rtrim($contents, "\n"));
            if (!isset($lines[$index])) {
                return;
            }
            $lines[$index] = $line;
            $new = implode("\n", $lines) . "\n";
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, $new);
            fflush($fh);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** Keep the "open visits" map from growing without bound. */
    private static function pruneOpen(array &$state): void
    {
        if (!isset($state['open']) || !is_array($state['open'])) {
            return;
        }
        $cutoff = time() - 86400; // drop visits untouched for 24h
        foreach ($state['open'] as $vid => $v) {
            if ((int) ($v['seen'] ?? 0) < $cutoff) {
                unset($state['open'][$vid]);
            }
        }
        // Hard cap as a final guard.
        if (count($state['open']) > 800) {
            $state['open'] = array_slice($state['open'], -800, null, true);
        }
    }

    /** @return array<string,mixed> */
    private static function readJson(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private static function writeJson(string $file, array $data): void
    {
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
