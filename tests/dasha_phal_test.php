<?php
declare(strict_types=1);

/**
 * dasha_phal_test.php — verification for the Tajik-Neelakanthi Dasha-Phal module
 * (migration 024): the 87-rule catalogue, the Patyayini dasha computation, the
 * bala-tier phal selection + upचय upgrade, the Vamanacharya antardasha grading
 * and the bhavastha-graha layer.
 *
 * Run from the project root:  php dasha_phal_test.php
 */

require __DIR__ . '/../bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Varshaphal;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Astro\Varshaphal\DashaPhalData;
use AutoBusiness\Astro\Varshaphal\DashaPhalRepository;
use AutoBusiness\Astro\Varshaphal\DashaPhalEngine;
use AutoBusiness\Astro\Varshaphal\PatyayiniDasha;

$fails = 0;
function check(string $label, bool $ok, string $note = ''): void
{
    global $fails;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($note !== '' ? " — $note" : '') . "\n";
    if (!$ok) { $fails++; }
}

echo "=== Part A: catalogue + data ===\n";
$byId = [];
foreach (DashaPhalData::RULES as $r) { $byId[$r['id']] = $r; }
check('87 baked rules', count(DashaPhalData::RULES) === 87, (string) count(DashaPhalData::RULES));
check('each planet has 4 tier phal + upgrade (SURYA_D_01..05)',
    isset($byId['SURYA_D_01'], $byId['SURYA_D_04'], $byId['SURYA_D_05'], $byId['SHANI_D_04']));
check('37 bhavastha rules (BHAV_01..37)', isset($byId['BHAV_01'], $byId['BHAV_37']) && !isset($byId['BHAV_38']));
check('Vamanacharya: Sun→Moon,Mars,Jupiter shubh', DashaPhalData::ANTAR_SHUBH['Sun'] === ['Moon', 'Mars', 'Jupiter']);
check('Vamanacharya: Saturn→Jupiter,Mercury,Venus shubh', DashaPhalData::ANTAR_SHUBH['Saturn'] === ['Jupiter', 'Mercury', 'Venus']);
check('upgrade houses: Sun 3/6/10/11, Moon=other-than-dusthana',
    DashaPhalData::UPGRADE['Sun']['houses'] === [3, 6, 10, 11] && DashaPhalData::UPGRADE['Moon']['other'] === true);

echo "\n=== Part B: Patyayini dasha math ===\n";
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
$natal = $engine->computeChart(JulianDay::fromGregorian(1980, 12, 1, 12, 31, 0.0, 5.5), 30.8, 75.1667);
$vp = Varshaphal::compute($engine, $natal, 1980, 12, 1, 12, 31, 5.5, 30.8, 75.1667, 2025);
$periods = PatyayiniDasha::compute($vp);
check('8 dashas (Lagna + 7 planets)', count($periods) === 8, (string) count($periods));
$lords = array_column($periods, 'lord');
check('all bodies present once', count(array_unique($lords)) === 8);
// Descending amsha order → Mercury first for this chart.
check('dasha order by descending amsha (Mercury first)', $periods[0]['lord'] === 'Mercury', $periods[0]['lord']);
$totalDays = 0.0; foreach ($periods as $d) { $totalDays += $d['days']; }
check('spans sum to ~365.2425 days', abs($totalDays - 365.2425) < 0.01, (string) round($totalDays, 3));
// Antardashas start with the dasha lord (pakapati).
check('first antar of each dasha = dasha lord', (function () use ($periods) {
    foreach ($periods as $d) { if ($d['antars'][0]['lord'] !== $d['lord']) { return false; } }
    return true;
})());
// Antar spans sum to the dasha span.
$d1 = $periods[1];
$asum = 0.0; foreach ($d1['antars'] as $a) { $asum += $a['days']; }
check('antar spans sum to dasha span', abs($asum - $d1['days']) < 1e-6);
// Contiguity: each dasha starts where the previous ended.
$contig = true;
for ($i = 1; $i < count($periods); $i++) { if (abs($periods[$i]['start_jd'] - $periods[$i - 1]['end_jd']) > 1e-6) { $contig = false; } }
check('dashas are contiguous', $contig);

echo "\n=== Part C: engine phal selection ===\n";
$rules = DashaPhalRepository::load('hi');
$nowJd = JulianDay::fromGregorian(2025, 6, 1, 12, 0, 0.0, 5.5);
$o = DashaPhalEngine::compute($vp, $natal, $rules, $nowJd);
check('running dasha index in range', $o['running']['dasha'] >= 0 && $o['running']['dasha'] < 8);
// Mars (bala 15.1 ≥ 10) → पूर्णबल → SURYA-style tier 0 → MANGAL_D_01 (shubh).
$mars = null; foreach ($o['periods'] as $d) { if ($d['lord'] === 'Mars') { $mars = $d; } }
check('Mars dasha = पूर्णबल tier', $mars['tier'] === 0 && $mars['cat'] === 'shubh', 'tier=' . $mars['tier']);
check('Mars phal = MANGAL_D_01 text (सेनापतित्व)', str_contains($mars['phal'], 'सेनापतित्व'));
// Mars is in house 3 (upचय house for Mars) → upgrade present.
check('Mars in upचय house → upgrade note', $mars['upgrade'] !== null && str_contains((string) $mars['upgrade']['outcome'], 'अत्यन्त'));
// Sun bala 7.5 (5..10) → मध्यबल tier 1 → mishrit.
$sun = null; foreach ($o['periods'] as $d) { if ($d['lord'] === 'Sun') { $sun = $d; } }
check('Sun dasha = मध्यबल (mishrit)', $sun['tier'] === 1 && $sun['cat'] === 'mishrit', 'tier=' . $sun['tier']);
// Mars-dasha antardashas: Sun & Moon shubh, others ashubh (Vamanacharya).
$asun = null; $asat = null;
foreach ($mars['antars'] as $a) { if ($a['lord'] === 'Sun') { $asun = $a; } if ($a['lord'] === 'Saturn') { $asat = $a; } }
check('Mars/Sun antar = शुभ', $asun['shubh'] === true);
check('Mars/Saturn antar = अशुभ', $asat['shubh'] === false);
check('Mars/Mars antar = पाकपति (self, null)', $mars['antars'][0]['self'] === true && $mars['antars'][0]['shubh'] === null);

echo "\n=== Part D: bhavastha layer ===\n";
check('bhavastha fired (constant for year)', count($o['bhavastha']) > 0, (string) count($o['bhavastha']));
foreach ($o['bhavastha'] as $b) {
    check('bhavastha ' . $b['id'] . ' has phal + house', $b['phal'] !== '' && $b['house'] >= 1 && $b['house'] <= 12);
    break;
}

echo "\n=== Part E: year change flips the dasha set ===\n";
$vp26 = Varshaphal::compute($engine, $natal, 1980, 12, 1, 12, 31, 5.5, 30.8, 75.1667, 2026);
$o26 = DashaPhalEngine::compute($vp26, $natal, $rules, $nowJd);
check('2026 dasha order differs from 2025', array_column($o26['periods'], 'lord') !== $lords,
    '25=' . implode(',', $lords) . ' 26=' . implode(',', array_column($o26['periods'], 'lord')));

echo "\n=== 2025 Patyayini dasha ===\n";
foreach ($o['periods'] as $i => $d) {
    echo sprintf("  D%d %-7s बल %.1f %-14s %s→%s (%d दिन) [%s]%s\n", $i, $d['lord_hi'], $d['bala'], $d['tier_hi'],
        JulianDay::toDmy((float) $d['start_jd'], 5.5), JulianDay::toDmy((float) $d['end_jd'], 5.5),
        (int) round($d['days']), $d['cat'], $d['upgrade'] ? ' ↑' . $d['upgrade']['outcome'] : '');
}

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n");
exit($fails === 0 ? 0 : 1);
