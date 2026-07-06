<?php
declare(strict_types=1);

/**
 * phala_yoga_test.php — verification for the Phaladeepika yoga module
 * (migration 025): the 106-yoga catalogue, the repository fallback and the
 * auto-detection engine (computable subset marked against the D1 chart).
 *
 * Run from the project root:  php phala_yoga_test.php
 */

require __DIR__ . '/bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Astro\Phala\PhaladeepikaYogaData;
use AutoBusiness\Astro\Phala\PhaladeepikaYogaRepository;
use AutoBusiness\Astro\Phala\PhaladeepikaYogaEngine;

$fails = 0;
function check(string $label, bool $ok, string $note = ''): void
{
    global $fails;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($note !== '' ? " — $note" : '') . "\n";
    if (!$ok) { $fails++; }
}

echo "=== Part A: catalogue integrity ===\n";
$Y = PhaladeepikaYogaData::YOGAS;
check('106 baked yogas', count($Y) === 106, (string) count($Y));
check('20 categories', count(PhaladeepikaYogaData::CATEGORIES) === 20, (string) count(PhaladeepikaYogaData::CATEGORIES));
$ids = array_column($Y, 'id');
check('ids unique', count(array_unique($ids)) === 106);
$types = array_count_values(array_column($Y, 'type'));
check('type counts (shubh 78, ashubh 24, mishrit 4)', ($types['shubh'] ?? 0) === 78 && ($types['ashubh'] ?? 0) === 24 && ($types['mishrit'] ?? 0) === 4);
check('every yoga has rule + result', count(array_filter($Y, static fn($y) => trim($y['rule']) === '' || trim($y['result']) === '')) === 0);
check('key yogas present (PMP01, CY01, RY01, NBR04)', in_array('PMP01', $ids, true) && in_array('CY01', $ids, true) && in_array('RY01', $ids, true) && in_array('NBR04', $ids, true));

echo "\n=== Part B: repository ===\n";
$repo = PhaladeepikaYogaRepository::load('hi');
check('repository returns 106 (baked fallback)', count($repo['yogas']) === 106, (string) count($repo['yogas']));
check('autodetect default on', ($repo['config']['phala_yoga_autodetect'] ?? '') === '1');

echo "\n=== Part C: engine detection (Moga 01-12-1980 12:31) ===\n";
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
$chart = $engine->computeChart(JulianDay::fromGregorian(1980, 12, 1, 12, 31, 0.0, 5.5), 30.8, 75.1667);
$o = PhaladeepikaYogaEngine::compute($chart, $repo);
check('total 106', $o['total'] === 106, (string) $o['total']);
check('20 category groups', count($o['groups']) === 20, (string) count($o['groups']));
check('some yogas detected', $o['detected_count'] > 0, (string) $o['detected_count']);

$flat = [];
foreach ($o['groups'] as $ys) { foreach ($ys as $y) { $flat[$y['id']] = $y['detected']; } }
// गजकेसरी (Jupiter kendra from Moon) is a known feature of this chart.
check('CY01 गजकेसरी detected', ($flat['CY01'] ?? null) === true);
// Every id has a detected flag; only computable ones are true/false.
$true = 0; $false = 0; $ref = 0;
foreach ($flat as $d) { if ($d === true) { $true++; } elseif ($d === false) { $false++; } else { $ref++; } }
check('flags partition all 106', $true + $false + $ref === 106, "t=$true f=$false ref=$ref");
check('detected_count matches true flags', $o['detected_count'] === $true, $o['detected_count'] . ' vs ' . $true);
check('reference-only yogas kept null (not auto-fired)', $ref > 0);

// Nabhasa is mutually exclusive: exactly one of NB01..NB07 can be true.
$nb = 0; foreach (['NB01', 'NB02', 'NB03', 'NB04', 'NB05', 'NB06', 'NB07'] as $k) { if (($flat[$k] ?? null) === true) { $nb++; } }
check('exactly one Nabhasa yoga detected', $nb === 1, (string) $nb);

// Sunapha/Anapha/Durudhara/Kemadruma are mutually exclusive too.
$ch = 0; foreach (['CH01', 'CH02', 'CH03', 'CH04'] as $k) { if (($flat[$k] ?? null) === true) { $ch++; } }
check('at most one of Sunapha/Anapha/Durudhara/Kemadruma', $ch <= 1, (string) $ch);

echo "\n=== detected yogas ===\n";
foreach ($o['groups'] as $cat => $ys) {
    foreach ($ys as $y) {
        if ($y['detected'] === true) { echo sprintf("  ✓ %-12s %s [%s]\n", $y['id'], $y['hi'], $y['type']); }
    }
}

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n");
exit($fails === 0 ? 0 : 1);
