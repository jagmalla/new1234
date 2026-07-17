<?php
declare(strict_types=1);

/**
 * shaap_test.php — verification for the Poorva-Shaap module (migration 026):
 * the 105-rule catalogue, remedies, the repository fallback and the detection
 * engine (computable subset marked + remedy attached for fired dosha
 * categories). Uses a real chart plus a synthetic chart engineered to fire
 * specific rules.
 *
 * Run from the project root:  php shaap_test.php
 */

require __DIR__ . '/../bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Astro\Phala\ShaapData;
use AutoBusiness\Astro\Phala\ShaapRepository;
use AutoBusiness\Astro\Phala\ShaapEngine;

$fails = 0;
function check(string $label, bool $ok, string $note = ''): void
{
    global $fails;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($note !== '' ? " — $note" : '') . "\n";
    if (!$ok) { $fails++; }
}

/**
 * Build a minimal whole-sign chart: $place maps planet => house (1..12).
 * ascSign 0 (Aries) so house h holds sign (h-1); house lord = sign lord.
 */
function synthChart(array $place, int $ascSign = 0): array
{
    $planets = [];
    $bodies = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];
    foreach ($bodies as $p) {
        $h = $place[$p] ?? 11;   // park unlisted bodies in the 11th
        $s = ($ascSign + $h - 1) % 12;
        $planets[$p] = ['sign_index' => $s, 'house' => $h, 'deg_in_sign' => 10.0,
            'navamsa_sign' => $s, 'retro' => false, 'sidereal_lon' => $s * 30.0 + 10.0];
    }
    $houses = [];
    for ($h = 1; $h <= 12; $h++) {
        $s = ($ascSign + $h - 1) % 12;
        $houses[$h] = ['lord' => Charts::signLord($s), 'sign_index' => $s];
    }
    return ['ascendant' => ['sign_index' => $ascSign], 'is_day' => true, 'planets' => $planets, 'houses' => $houses];
}

echo "=== Part A: catalogue integrity ===\n";
$R = ShaapData::RULES;
check('105 baked rules', count($R) === 105, (string) count($R));
check('13 categories', count(ShaapData::CATEGORIES) === 13, (string) count(ShaapData::CATEGORIES));
check('10 remedies', count(ShaapData::REMEDIES) === 10, (string) count(ShaapData::REMEDIES));
$types = array_count_values(array_column($R, 'type'));
check('type counts (ashubh 75, mishrit 16, shubh 14)', ($types['ashubh'] ?? 0) === 75 && ($types['mishrit'] ?? 0) === 16 && ($types['shubh'] ?? 0) === 14);
check('ids unique', count(array_unique(array_column($R, 'id'))) === 105);
check('every rule has rule + result', count(array_filter($R, static fn($r) => trim($r['rule']) === '' || trim($r['result']) === '')) === 0);

echo "\n=== Part B: repository ===\n";
$repo = ShaapRepository::load('hi');
check('repository returns 105 (baked fallback)', count($repo['rules']) === 105, (string) count($repo['rules']));
check('remedies keyed by category', isset($repo['remedies']['सर्पशाप'], $repo['remedies']['पितृशाप']));
check('autodetect default on', ($repo['config']['shaap_autodetect'] ?? '') === '1');

echo "\n=== Part C: detection on synthetic charts ===\n";
// PR04 (प्रेतशाप): Rahu in 1, Saturn in 5, Jupiter in 8.
$o = ShaapEngine::compute(synthChart(['Rahu' => 1, 'Saturn' => 5, 'Jupiter' => 8]), $repo);
$flat = [];
foreach ($o['groups'] as $rs) { foreach ($rs as $r) { $flat[$r['id']] = $r['detected']; } }
check('PR04 detected (Rahu-1, Saturn-5, Jupiter-8)', ($flat['PR04'] ?? null) === true);
check('प्रेतशाप in detected categories', in_array('प्रेतशाप', $o['detected_categories'], true));
$remCats = array_column($o['remedies'], 'cat');
check('प्रेतशाप remedy attached', in_array('प्रेतशाप', $remCats, true));
check('remedy text present', ($o['remedies'][0]['upaay'] ?? '') !== '');

// PR07: Saturn-1, Rahu-5, Sun-8, Mars-12.
$o2 = ShaapEngine::compute(synthChart(['Saturn' => 1, 'Rahu' => 5, 'Sun' => 8, 'Mars' => 12]), $repo);
$flat2 = [];
foreach ($o2['groups'] as $rs) { foreach ($rs as $r) { $flat2[$r['id']] = $r['detected']; } }
check('PR07 detected (Saturn-1, Rahu-5, Sun-8, Mars-12)', ($flat2['PR07'] ?? null) === true);

// BP13 (बहुपुत्र, शुभ): L1 in 7, L7 in 1, L2 in 1. Aries asc → L1=Mars, L7=L2=Venus.
$o3 = ShaapEngine::compute(synthChart(['Mars' => 7, 'Venus' => 1]), $repo);
$flat3 = [];
foreach ($o3['groups'] as $rs) { foreach ($rs as $r) { $flat3[$r['id']] = $r['detected']; } }
check('BP13 detected (शुभ बहुपुत्र)', ($flat3['BP13'] ?? null) === true);
check('no remedy for a शुभ-only detection', $o3['remedies'] === []);

echo "\n=== Part D: real chart + partition ===\n";
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
$chart = $engine->computeChart(JulianDay::fromGregorian(1980, 12, 1, 12, 31, 0.0, 5.5), 30.8, 75.1667);
$ro = ShaapEngine::compute($chart, $repo);
check('total 105', $ro['total'] === 105, (string) $ro['total']);
check('13 category groups', count($ro['groups']) === 13, (string) count($ro['groups']));
$t = 0; $f = 0; $ref = 0;
foreach ($ro['groups'] as $rs) { foreach ($rs as $r) { if ($r['detected'] === true) { $t++; } elseif ($r['detected'] === false) { $f++; } else { $ref++; } } }
check('flags partition all 105', $t + $f + $ref === 105, "t=$t f=$f ref=$ref");
check('detected_count matches true flags', $ro['detected_count'] === $t);
check('reference-only rules kept null', $ref > 0);

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n");
exit($fails === 0 ? 0 : 1);
