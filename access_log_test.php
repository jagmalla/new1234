<?php
declare(strict_types=1);

/**
 * Access-log harness — verifies the visit log without needing a browser or DB:
 *   • a first visit writes a plain (unnumbered) line,
 *   • a repeat from the same IP is prefixed (2), (3), …,
 *   • ping() rewrites the SAME visit's line with an updated duration,
 *   • the format is: [ (N) ]Location | DD-MM-YYYY | HH:MM | dur | IP,
 *   • it is fail-safe (bad vid / no state is a silent no-op).
 *
 * Run from the project root:  php access_log_test.php
 */

require __DIR__ . '/bootstrap.php';

use AutoBusiness\Core\AccessLog;

$storage = AB_ROOT . '/storage';
$log     = $storage . '/access.log';
$state   = $storage . '/.visits.json';

// Start from a clean slate so counts/prefixes are deterministic.
@unlink($log);
@unlink($state);

$pass = 0; $fail = 0;
function check(string $name, bool $ok): void {
    global $pass, $fail;
    echo ($ok ? "  PASS  " : "  FAIL  ") . $name . "\n";
    $ok ? $pass++ : $fail++;
}
function logLines(string $log): array {
    if (!is_file($log)) { return []; }
    return array_values(array_filter(explode("\n", (string) file_get_contents($log)), fn($l) => $l !== ''));
}

// A public IP won't geolocate offline (→ "Unknown"), which is fine for format.
$_SERVER['REMOTE_ADDR'] = '203.0.113.45';

echo "\n=== Access log ===\n";

// --- Visit 1 (first time for this IP) --------------------------------------
$vid1 = AccessLog::begin();
check('begin() returns a visit id', $vid1 !== '');
$lines = logLines($log);
check('one line written', count($lines) === 1);
$re = '/^(\(\d+\)\s)?.+ \| \d{2}-\d{2}-\d{4} \| \d{2}:\d{2} \| (\d+h )?\d{1,2}m \| 203\.0\.113\.45$/';
check('line 1 matches the required format', (bool) preg_match($re, $lines[0]));
check('line 1 has NO repeat prefix (first visit)', strpos($lines[0], '(') !== 0);

// --- ping() rewrites the duration in place ---------------------------------
// Backdate the visit's start so the elapsed time is > 1 hour, then ping.
$s = json_decode((string) file_get_contents($state), true);
$s['open'][$vid1]['start'] = time() - (3600 + 5 * 60); // 1h 05m ago
file_put_contents($state, json_encode($s));
AccessLog::ping($vid1);
$lines = logLines($log);
check('ping updates the SAME line (still one line)', count($lines) === 1);
check('duration became "1h 05m"', strpos($lines[0], '| 1h 05m |') !== false);

// --- Visit 2 (SAME IP again) → prefixed (2) --------------------------------
$vid2 = AccessLog::begin();
$lines = logLines($log);
check('second visit adds a new line', count($lines) === 2);
check('repeat visit is prefixed "(2)"', strpos($lines[1], '(2) ') === 0);
check('previous line untouched by the new visit', strpos($lines[0], '| 1h 05m |') !== false);

// --- Visit 3 (SAME IP) → prefixed (3) --------------------------------------
AccessLog::begin();
$lines = logLines($log);
check('third visit is prefixed "(3)"', isset($lines[2]) && strpos($lines[2], '(3) ') === 0);

// --- A DIFFERENT IP starts fresh (no prefix) -------------------------------
$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
AccessLog::begin();
$lines = logLines($log);
check('different IP starts unnumbered', isset($lines[3]) && strpos($lines[3], '(') !== 0
      && strpos($lines[3], '198.51.100.7') !== false);

// --- Fail-safe: junk vid, empty vid are silent no-ops ----------------------
$before = file_get_contents($log);
AccessLog::ping('');
AccessLog::ping('not-a-real-vid');
AccessLog::ping('deadbeefdead');
check('bad/empty ping() calls change nothing', file_get_contents($log) === $before);

echo "\n--- sample log ---\n" . implode("\n", logLines($log)) . "\n";
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
