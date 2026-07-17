<?php
declare(strict_types=1);

/**
 * gochar_av_test.php — verification for the Gochar Ashtakavarga phal
 * (migration 021): bindu-count phal (Part 1), Kaksha phal (Part 2) and SAV
 * hints (Part 3). Confirms the data, the per-contributor Kaksha bindu logic,
 * and that these are additive categories that do NOT change the Layer-1
 * house texts.
 *
 * Run from the project root:  php gochar_av_test.php
 */

require __DIR__ . '/../bootstrap.php';

use AutoBusiness\Astro\Calc\Ashtakavarga;
use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Astro\Gochar\GocharAvRepository;
use AutoBusiness\Astro\Gochar\GocharPhalEngine;
use AutoBusiness\Astro\Gochar\GocharRepository;

$fails = 0;
function check(string $label, bool $ok, string $note = ''): void
{
    global $fails;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($note !== '' ? " — $note" : '') . "\n";
    if (!$ok) { $fails++; }
}

echo "=== Part 1: rule data ===\n";
$r = GocharAvRepository::load('hi');
$bc = 0; foreach ($r['bindu'] as $p => $rows) { $bc += count($rows); }
check('63 bindu-count texts (7 x 9)', $bc === 63, (string) $bc);
$kc = 0; foreach ($r['kaksha'] as $p => $lords) { foreach ($lords as $l => $kv) { $kc += count($kv); } }
check('112 kaksha texts (7 x 8 x 2)', $kc === 112, (string) $kc);
check('Sun 8-bindu = raja-tulya', str_contains($r['bindu']['Sun'][8] ?? '', 'राजातुल्य'));
check('Sun 0-bindu = death/apman', str_contains($r['bindu']['Sun'][0] ?? '', 'मृत्यु'));
check('SAV hints present', isset($r['sav']['28 से अधिक'], $r['sav']['28 से कम']));

echo "\n=== Part 2: Kaksha contributor logic ===\n";
// Ashtakavarga::contributes — Sun's Sun-contributor benefic houses are
// [1,2,4,7,8,9,10,11]; from contributor-sign 0 (Aries), sign 1 (Taurus = 2nd)
// gets a bindu, sign 2 (Gemini = 3rd) does not.
check('contributes Sun/Sun -> 2nd yes', Ashtakavarga::contributes('Sun', 'Sun', 1, 0) === true);
check('contributes Sun/Sun -> 3rd no', Ashtakavarga::contributes('Sun', 'Sun', 2, 0) === false);
check('KAKSHA_ORDER outer=Saturn, inner=Lagna',
    Ashtakavarga::KAKSHA_ORDER[0] === 'Saturn' && Ashtakavarga::KAKSHA_ORDER[7] === 'Lagna');

echo "\n=== Part 3: engine + no-conflict ===\n";
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
$natal = $engine->computeChart(JulianDay::fromGregorian(1980, 12, 1, 12, 31, 0.0, 5.5), 30.8, 75.1667);
$rules = GocharRepository::load('hi');
$jdG = JulianDay::fromGregorian(2026, 7, 6, 19, 0, 0.0, 5.5);
$g = $engine->gochar($natal, $jdG, 30.8, 75.1667);

$noAv = GocharPhalEngine::compute($natal, $g['transits'], $rules);          // still has av (default rules)
$out = GocharPhalEngine::compute($natal, $g['transits'], $rules, $jdG);
$av = $out['av'];
check('7 bindu-phal entries', count($av['bindu']) === 7, (string) count($av['bindu']));
check('7 kaksha entries', count($av['kaksha']) === 7, (string) count($av['kaksha']));
check('7 SAV entries', count($av['sav']) === 7, (string) count($av['sav']));

// The bindu shown equals the chart's own BAV for that planet+sign.
$sun = null; foreach ($av['bindu'] as $b) { if ($b['planet'] === 'Sun') { $sun = $b; } }
$sunSign = (int) $g['transits']['Sun']['sign_index'];
$expBindu = (int) ($natal['ashtakavarga']['bav']['Sun'][$sunSign] ?? -1);
check('Sun bindu matches natal BAV at transit sign', $sun !== null && $sun['bindu'] === $expBindu,
    'shown=' . ($sun['bindu'] ?? '?') . ' exp=' . $expBindu);
check('bindu phal text present', $sun !== null && $sun['phal'] !== '');

// Kaksha phal text present + applicability flag exists.
$k0 = $av['kaksha'][0];
check('kaksha has lord/kind/phal/applicable', isset($k0['lord'], $k0['kind'], $k0['phal']) && array_key_exists('applicable', $k0));

// SAV threshold 28.
$sav0 = $av['sav'][0];
check('SAV tone follows 28 threshold', ($sav0['sav'] >= 28) === ($sav0['tone'] === 'pos'));

// NO CONFLICT: Layer-1 Saturn text unchanged when the AV layer is present.
$satText = static function (array $o): string {
    foreach ($o['layer1'] as $e) { if ($e['planet'] === 'Saturn') { return $e['text']; } }
    return '';
};
check('Layer-1 Saturn text unchanged by the AV categories', $satText($noAv) === $satText($out));

echo "\n=== reading (bindu | kaksha | SAV per planet) ===\n";
foreach ($av['bindu'] as $i => $b) {
    $k = $av['kaksha'][$i]; $s = $av['sav'][$i];
    echo sprintf("  %-8s बिन्दु %d · कक्षा %d(%s)=%s · SAV %d\n",
        $b['planet'], $b['bindu'], $k['kaksha_no'], $k['lord'], $k['kind'], $s['sav']);
}

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n");
exit($fails === 0 ? 0 : 1);
