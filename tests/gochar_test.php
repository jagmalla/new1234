<?php
declare(strict_types=1);

/**
 * gochar_test.php — verification harness for the Gochar (transit) prediction
 * engine (migration 017 / the Gochar Phal panel). Checks the rule data and
 * the 3 layers against docs/Gochar_Rules.md, then prints the full reading for
 * the reference chart (Moga 01-12-1980 12:31) transited from Surrey BC on the
 * date shown in the owner's screenshot.
 *
 * Run from the project root:  php gochar_test.php
 */

require __DIR__ . '/../bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Gochar\GocharPhalEngine;
use AutoBusiness\Astro\Gochar\GocharRepository;
use AutoBusiness\Astro\Time\JulianDay;

$fails = 0;
function check(string $label, bool $ok, string $note = ''): void
{
    global $fails;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($note !== '' ? " — $note" : '') . "\n";
    if (!$ok) { $fails++; }
}

echo "=== Part 1: rule data ===\n";
$rules = GocharRepository::load('hi');
$hp = $rules['house_phal'];
check('9 planets in the house bank', count($hp) === 9);
$total = 0;
foreach ($hp as $p => $rows) { $total += count($rows); }
check('108 house texts (9 x 12)', $total === 108, "got $total");
check('Sun 3rd = shubh', ($hp['Sun'][3]['shubh'] ?? false) === true);
check('Sun 1st = ashubh', ($hp['Sun'][1]['shubh'] ?? true) === false);
check('shubh houses Jupiter = 2,5,7,9,11', GocharRepository::SHUBH['Jupiter'] === [2, 5, 7, 9, 11]);
check('vedha Mercury 9 & 10 both = 8', GocharRepository::VEDHA['Mercury'][8] === 8 && GocharRepository::VEDHA['Mercury'][9] === 8);
check('Rahu reads as Saturn / Ketu as Mars', GocharRepository::TEXT_SOURCE['Rahu'] === 'Saturn' && GocharRepository::TEXT_SOURCE['Ketu'] === 'Mars');
check('combo bank normalised to English keys', isset($rules['natal_combo']['Sun']['Sun']));

echo "\n=== Part 2: engine layers ===\n";
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
$natal = $engine->computeChart(JulianDay::fromGregorian(1980, 12, 1, 12, 31, 0.0, 5.5), 30.8, 75.1667);
$g = $engine->gochar($natal, JulianDay::fromGregorian(2026, 7, 4, 22, 31, 0.0, -7.0), 49.1, -122.85);
$out = GocharPhalEngine::compute($natal, $g['transits'], $rules);

check('natal Moon sign = Virgo (5)', (int) $out['moon_sign'] === 5);
check('9 Layer-1 events', count($out['layer1']) === 9);
// house-from-moon: transit Sun sign − natal Moon sign.
$sunEvt = null;
foreach ($out['layer1'] as $e) { if ($e['planet'] === 'Sun') { $sunEvt = $e; } }
$expHouse = (((int) $g['transits']['Sun']['sign_index'] - (int) $out['moon_sign']) % 12 + 12) % 12 + 1;
check('Sun house counted from natal Moon', $sunEvt !== null && $sunEvt['house'] === $expHouse, 'got ' . ($sunEvt['house'] ?? '?') . " exp $expHouse");

// Vedha present on at least one event (this chart has Moon-6 blocked by Venus).
$hasVedha = false;
foreach ($out['layer1'] as $e) {
    foreach ($e['notes'] as $n) { if (str_contains($n['text'], 'वेध')) { $hasVedha = true; } }
}
check('vedha modifier fires on some event', $hasVedha);

// Ksheen rule: Moon in 2/5/9 flips on transit-Moon strength. Build a synthetic
// transit where the Moon sits 5th from natal Moon and is (a) far from Sun
// (strong -> shubh) then (b) within 72° (ksheen -> ashubh).
$mkMoon = static function (int $moonSign, float $moonLon, float $sunLon) use ($g): array {
    $t = $g['transits'];
    $t['Moon']['sign_index'] = $moonSign;
    $t['Moon']['sidereal_lon'] = $moonLon;
    $t['Moon']['deg_in_sign'] = $moonLon - $moonSign * 30.0;
    $t['Sun']['sidereal_lon'] = $sunLon;
    $t['Sun']['sign_index'] = (int) ($sunLon / 30) % 12;
    return $t;
};
$moonSign5 = (5 + 4) % 12; // 5th house from natal Moon (Virgo)
$strong = GocharPhalEngine::compute($natal, $mkMoon($moonSign5, $moonSign5 * 30 + 15, 5.0), $rules);
$weak = GocharPhalEngine::compute($natal, $mkMoon($moonSign5, $moonSign5 * 30 + 15, $moonSign5 * 30 + 5), $rules);
$moonTone = static function (array $o): string {
    foreach ($o['layer1'] as $e) { if ($e['planet'] === 'Moon') { return $e['tone']; } }
    return '?';
};
// Strong Moon lifts the 5th-house ashubh (tone becomes shubh, though a later
// vedha may still block it → not 'neg'); ksheen Moon keeps it ashubh.
check('Moon 5th + strong (far from Sun) -> not ashubh', $moonTone($strong) !== 'neg', $moonTone($strong));
check('Moon 5th + ksheen (near Sun) -> ashubh', $moonTone($weak) === 'neg', $moonTone($weak));

// Layer 3: at least one group, and Rahu (if present) is labelled शनिवत्.
check('Layer 3 produced groups', count($out['layer3']) > 0, count($out['layer3']) . ' groups');
foreach ($out['layer3'] as $grp) {
    if ($grp['transit'] === 'Rahu') { check('transit Rahu reads as शनि', $grp['reads_as'] === 'शनि'); }
    if ($grp['transit'] === 'Ketu') { check('transit Ketu reads as मंगल', $grp['reads_as'] === 'मंगल'); }
}

echo "\n=== Part 3: full reading (reference chart, Surrey transit) ===\n";
echo 'चन्द्र लग्न: ' . (\AutoBusiness\Astro\Calc\Charts::SIGNS[$out['moon_sign']]) . "\n";
foreach ($out['layer1'] as $e) {
    echo sprintf("  %-8s भाव %2d  %s  %s\n", $e['planet'], $e['house'],
        $e['tone'] === 'pos' ? 'शुभ ' : ($e['tone'] === 'neg' ? 'अशुभ' : 'रुका'), $e['deg']);
}
echo 'Layer 3 groups: ';
echo implode(', ', array_map(static fn($g) => $g['transit'] . '(' . count($g['events']) . ')', $out['layer3'])) . "\n";

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n");
exit($fails === 0 ? 0 : 1);
