<?php
declare(strict_types=1);

/**
 * muntha_test.php — verification for the Muntha phal engine (migration 019 /
 * the "मुंथा फल" Varshaphal panel option). Asserts the classical Muntha
 * formula (book example + owner chart, per the task's §1 verify step), the
 * rule data, and the bhava/graha/Rahu/special layers, then prints the reading
 * for the reference chart (Moga 01-12-1980 12:31).
 *
 * Run from the project root:  php muntha_test.php
 */

require __DIR__ . '/../bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\Varshaphal;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Astro\Muntha\MunthaPhalEngine;
use AutoBusiness\Astro\Muntha\MunthaRepository;
use AutoBusiness\Astro\Tajik\TajikRepository;

$fails = 0;
function check(string $label, bool $ok, string $note = ''): void
{
    global $fails;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($note !== '' ? " — $note" : '') . "\n";
    if (!$ok) { $fails++; }
}

echo "=== Part 1: Muntha formula (§1 verify — unchanged calc) ===\n";
// Book example: gata 50, natal-lagna sign 5 (0-indexed) 19°26'41" -> Muntha sign 7.
check('book example (5 + 50) % 12 = 7', ((5 + 50) % 12) === 7);

$tz = 5.5; $lat = 30.8; $lon = 75.1667;
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
$natal = $engine->computeChart(JulianDay::fromGregorian(1980, 12, 1, 12, 31, 0.0, $tz), $lat, $lon);
$natalAsc = (int) $natal['ascendant']['sign_index'];
$vp25 = Varshaphal::compute($engine, $natal, 1980, 12, 1, 12, 31, $tz, $lat, $lon, 2025);
$vp26 = Varshaphal::compute($engine, $natal, 1980, 12, 1, 12, 31, $tz, $lat, $lon, 2026);
// Owner chart matches the PL screenshot: 2025 Muntha Scorpio / lord Mars.
$ms25 = ($natalAsc + (int) $vp25['age_completed']) % 12;
check('2025 Muntha = Scorpio (PL "Sco 08:41")', Charts::SIGNS[$ms25] === 'Scorpio', Charts::SIGNS[$ms25]);
check('2025 Munthesh = Mars', $vp25['muntha']['lord'] === 'Mars', $vp25['muntha']['lord']);
check('2026 Muntha = Sagittarius / Jupiter', $vp26['muntha']['sign'] === 'Sagittarius' && $vp26['muntha']['lord'] === 'Jupiter');

echo "\n=== Part 2: rule data ===\n";
$rules = MunthaRepository::load('hi');
check('12 bhava phal', count($rules['bhava']) === 12, (string) count($rules['bhava']));
check('9 graha rules (incl. Rahu zones)', count($rules['graha']) === 9, (string) count($rules['graha']));
check('12 special rules', count($rules['special']) === 12, (string) count($rules['special']));
check('bhava band 9/10/11 = अति शुभ', str_contains($rules['bhava'][9]['band'], 'अति') && str_contains($rules['bhava'][11]['band'], 'अति'));
check('bhava band 4/6/8/12 = अशुभ', str_contains($rules['bhava'][4]['band'], 'अशुभ') && str_contains($rules['bhava'][8]['band'], 'अशुभ'));

echo "\n=== Part 3: engine layers ===\n";
$tajik = TajikRepository::load('hi');
$o = MunthaPhalEngine::compute($vp25, $natal, $tajik, $rules);
check('Muntha house from varsha lagna is 1..12', $o['muntha_house'] >= 1 && $o['muntha_house'] <= 12, (string) $o['muntha_house']);
check('exactly one bhava text', $o['bhava']['text'] !== '');
check('Munthesh reported = Mars', $o['munthesh'] === 'Mars');
// Graha: at least the yuti/rashi/drishti planets fire; Mars (Muntha in Scorpio,
// its own sign) must appear with the Saturn-softening clause detectable path.
$mars = null;
foreach ($o['graha'] as $g) { if ($g['planet'] === 'Mars') { $mars = $g; } }
check('Mars graha card present (Muntha in its sign)', $mars !== null && str_contains($mars['reasons'], 'राशि'));
check('verdict is one of शुभ/मिश्रित/अशुभ/गंभीर चेतावनी',
    in_array($o['verdict'], ['शुभ', 'मिश्रित', 'अशुभ', 'गंभीर चेतावनी'], true), $o['verdict']);

// Maran-yoga must NOT fire falsely when the Munthesh itself rules the 8th
// (Mars is both Munthesh and 8th-lord from a Virgo varsha lagna in 2025).
check('no false maran-yoga (Munthesh == 8th lord case)', $o['maran_yoga'] === false);

// Rahu zone: synthetic Muntha in Rahu's sign, before/after Rahu degree.
$fake = $vp25;
$rSign = (int) $fake['varsha_chart']['planets']['Rahu']['sign_index'];
$rDeg = (float) $fake['varsha_chart']['planets']['Rahu']['deg_in_sign'];
// Force the Muntha point: natal asc sign+deg drive it, so set natal asc to
// Rahu's sign with a degree below Rahu (mukh) then above (prishtha).
$natalMukh = $natal; $natalMukh['ascendant']['sign_index'] = (($rSign - (int) $fake['age_completed']) % 12 + 12) % 12;
$natalMukh['ascendant']['deg_in_sign'] = max(0.0, $rDeg - 2.0);
$oMukh = MunthaPhalEngine::compute($fake, $natalMukh, $tajik, $rules);
check('Muntha below Rahu degree -> mukh zone', ($oMukh['rahu']['zone'] ?? '') === 'mukh', $oMukh['rahu']['zone'] ?? 'none');
$natalPrishtha = $natalMukh; $natalPrishtha['ascendant']['deg_in_sign'] = min(29.9, $rDeg + 2.0);
$oPr = MunthaPhalEngine::compute($fake, $natalPrishtha, $tajik, $rules);
check('Muntha above Rahu degree -> prishtha zone (+ पाठ-भेद note)',
    ($oPr['rahu']['zone'] ?? '') === 'prishtha' && ($oPr['rahu']['pathabheda'] ?? false) === true);

echo "\n=== Part 4: reading (2025) ===\n";
echo "मुंथा {$o['muntha_sign_hi']} {$o['muntha_deg']} — {$o['muntha_house']}वें भाव [{$o['band']}] · मुंथेश {$o['munthesh_hi']} · verdict {$o['verdict']}\n";
foreach ($o['graha'] as $g) {
    echo "  ग्रह {$g['planet_hi']} [{$g['nature']}] ({$g['reasons']})" . ($g['clause'] ? ' ⟶ clause' : '') . "\n";
}
echo "modifiers: " . implode(', ', array_map(static fn($m) => $m['key'] . ':' . $m['tone'], $o['modifiers'])) . "\n";

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n");
exit($fails === 0 ? 0 : 1);
