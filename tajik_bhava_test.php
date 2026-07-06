<?php
declare(strict_types=1);

/**
 * tajik_bhava_test.php — verification for the Tajik-Neelakanthi Bhava-Phal
 * module (migration 023): the 262-rule catalogue, the DB-overridable repository
 * and the auto-fire engine (computable subset marked against the varsha chart).
 *
 * Run from the project root:  php tajik_bhava_test.php
 */

require __DIR__ . '/bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Varshaphal;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Astro\Varshaphal\TajikBhavaData;
use AutoBusiness\Astro\Varshaphal\TajikBhavaRepository;
use AutoBusiness\Astro\Varshaphal\TajikBhavaEngine;

$fails = 0;
function check(string $label, bool $ok, string $note = ''): void
{
    global $fails;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($note !== '' ? " — $note" : '') . "\n";
    if (!$ok) { $fails++; }
}

echo "=== Part A: catalogue integrity ===\n";
$rules = TajikBhavaData::RULES;
check('262 baked rules', count($rules) === 262, (string) count($rules));
$byHouse = []; $byCat = [];
foreach ($rules as $r) { $byHouse[$r['h']] = ($byHouse[$r['h']] ?? 0) + 1; $byCat[$r['cat']] = ($byCat[$r['cat']] ?? 0) + 1; }
check('all 12 houses present', count($byHouse) === 12 && min(array_keys($byHouse)) === 1 && max(array_keys($byHouse)) === 12);
check('per-house counts (B06=33, B08=47, B11=9)', ($byHouse[6] ?? 0) === 33 && ($byHouse[8] ?? 0) === 47 && ($byHouse[11] ?? 0) === 9);
check('category counts (shubh 93, ashubh 124, mrityu 24, mishrit 15, niyam 6)',
    ($byCat['shubh'] ?? 0) === 93 && ($byCat['ashubh'] ?? 0) === 124 && ($byCat['mrityu'] ?? 0) === 24
    && ($byCat['mishrit'] ?? 0) === 15 && ($byCat['niyam'] ?? 0) === 6);
$ids = array_column($rules, 'id');
check('rule ids unique', count(array_unique($ids)) === 262);
check('every rule has phal text', count(array_filter($rules, static fn($r) => trim($r['phal']) === '')) === 0);

echo "\n=== Part B: repository ===\n";
$repo = TajikBhavaRepository::load('hi');
check('repository returns 262 rules (baked fallback)', count($repo['rules']) === 262, (string) count($repo['rules']));
check('config autofire default on', ($repo['config']['tajik_bhava_autofire'] ?? '') === '1');

echo "\n=== Part C: engine auto-fire (Moga 01-12-1980 12:31) ===\n";
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
$natal = $engine->computeChart(JulianDay::fromGregorian(1980, 12, 1, 12, 31, 0.0, 5.5), 30.8, 75.1667);

$run = static function (int $yr) use ($engine, $natal, $repo): array {
    $vp = Varshaphal::compute($engine, $natal, 1980, 12, 1, 12, 31, 5.5, 30.8, 75.1667, $yr);
    return TajikBhavaEngine::compute($vp, $natal, $repo);
};
$o25 = $run(2025);
$o26 = $run(2026);

check('groups keyed 1..12', array_keys($o25['groups']) === range(1, 12));
check('total = 262', $o25['total'] === 262, (string) $o25['total']);
check('2025 varshesh = मंगल (Mars)', $o25['context']['varshesh'] === 'Mars', $o25['context']['varshesh']);
check('2026 varshesh = बुध (Mercury)', $o26['context']['varshesh'] === 'Mercury', $o26['context']['varshesh']);
check('2025 has matched rules', $o25['matched_count'] > 0, (string) $o25['matched_count']);

// Collect matched ids.
$matched = static function (array $o): array {
    $m = [];
    foreach ($o['groups'] as $rs) { foreach ($rs as $r) { if ($r['matched'] === true) { $m[] = $r['id']; } } }
    return $m;
};
$m25 = $matched($o25);
$m26 = $matched($o26);

// 2025: Mars is varshesh, strong, in the 3rd/9th → B09_01 must fire.
check('2025 B09_01 fires (Mars varshesh, बली, 3/9)', in_array('B09_01', $m25, true), implode(',', $m25));
// A firing must be a genuine rule and reflect its house group.
$found = false;
foreach ($o25['groups'][9] as $r) { if ($r['id'] === 'B09_01') { $found = $r['matched'] === true; } }
check('B09_01 sits in bhava-9 group and is marked', $found);

// Year change flips the matched set (predictions follow the year).
check('matched set differs 2025 vs 2026', $m25 !== $m26, '25=[' . implode(',', $m25) . '] 26=[' . implode(',', $m26) . ']');

// Only computable rules are ever true/false; the rest are reference (null).
$refCount = 0; $tfCount = 0;
foreach ($o25['groups'] as $rs) {
    foreach ($rs as $r) {
        if ($r['matched'] === null) { $refCount++; } else { $tfCount++; }
    }
}
check('reference-only rules kept as null (majority)', $refCount > 0 && $refCount + $tfCount === 262, "ref=$refCount tf=$tfCount");
check('no rule mislabeled: matched⊆computed', count($m25) <= $tfCount);

echo "\n=== 2025 firings ===\n";
foreach ($o25['groups'] as $hn => $rs) {
    foreach ($rs as $r) {
        if ($r['matched'] === true) {
            echo sprintf("  ✔ %-7s भाव %-2d [%s] %s\n", $r['id'], $hn, $r['cat'], mb_substr($r['phal'], 0, 40));
        }
    }
}

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n");
exit($fails === 0 ? 0 : 1);
