<?php
declare(strict_types=1);

/**
 * varshesh_test.php — verification for the Varshesh (year-lord) selection +
 * phal engine (migration 018 / the "वर्षेश फल" Varshaphal panel option).
 * Checks the rule data and the classical selection paths against
 * docs/Varshesh_Rules_TajikNeelkanthi.md, then prints the reading for the
 * reference chart (Moga 01-12-1980 12:31).
 *
 * Run from the project root:  php varshesh_test.php
 */

require __DIR__ . '/../bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Varshaphal;
use AutoBusiness\Astro\Calc\Varshesha;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Astro\Tajik\TajikRepository;
use AutoBusiness\Astro\Varshesh\VarsheshEngine;
use AutoBusiness\Astro\Varshesh\VarsheshRepository;

$fails = 0;
function check(string $label, bool $ok, string $note = ''): void
{
    global $fails;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($note !== '' ? " — $note" : '') . "\n";
    if (!$ok) { $fails++; }
}

echo "=== Part 1: rule data ===\n";
$rules = VarsheshRepository::load('hi');
$planets = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
$phalCount = 0;
foreach ($planets as $p) { $phalCount += count($rules['phal'][$p] ?? []); }
check('21 band-graded phal (7 x 3)', $phalCount === 21, "got $phalCount");
check('special rules include sp_13/37/38/44/moon18',
    isset($rules['special']['sp_13'], $rules['special']['sp_37'], $rules['special']['sp_38'], $rules['special']['sp_44'], $rules['special']['sp_moon18']));
check('config bands 45 / 30', ($rules['config']['varshesh_full_min'] ?? '') === '45' && ($rules['config']['varshesh_madhya_min'] ?? '') === '30');

echo "\n=== Part 2: Panchavargeeya extremes ===\n";
// deep exaltation -> uchcha 20; deep debilitation -> uchcha 0 (Sun deep exalt 10° Aries).
$mk = static fn(float $lon): array => ['Sun' => ['sidereal_lon' => $lon, 'sign_index' => (int) ($lon / 30) % 12, 'deg_in_sign' => fmod($lon, 30.0)]];
$exalt = Varshesha::components('Sun', $mk(10.0));
$debil = Varshesha::components('Sun', $mk(190.0));   // 10° Libra
check('Sun deep-exalt uchcha = 20', abs($exalt['uchcha'] - 20.0) < 0.01, (string) $exalt['uchcha']);
check('Sun deep-debil uchcha = 0', abs($debil['uchcha'] - 0.0) < 0.01, (string) $debil['uchcha']);

echo "\n=== Part 3: selection on the reference chart ===\n";
$tz = 5.5; $lat = 30.8; $lon = 75.1667;
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
$natal = $engine->computeChart(JulianDay::fromGregorian(1980, 12, 1, 12, 31, 0.0, $tz), $lat, $lon);
$tajik = TajikRepository::load('hi');

$run = static function (int $yr) use ($engine, $natal, $tajik, $rules, $tz, $lat, $lon): array {
    $vp = Varshaphal::compute($engine, $natal, 1980, 12, 1, 12, 31, $tz, $lat, $lon, $yr);
    return [$vp, VarsheshEngine::compute($vp, $natal, $tajik, $rules, ($vp['mudda_dasha'][0]['lord'] ?? null))];
};

[$vp25, $o25] = $run(2025);
[$vp26, $o26] = $run(2026);
check('2025 Varshesh = Mars (matches PL / header)', $o25['winner'] === 'Mars', $o25['winner']);
check('2026 Varshesh = Mercury (matches header)', $o26['winner'] === 'Mercury', $o26['winner']);
check('classical winner agrees with header 2025 (no method-diff)', $o25['method_diff'] === null);
check('classical winner agrees with header 2026 (no method-diff)', $o26['method_diff'] === null);

// lagna-drishti gating: the Moon (Trirashi Pati, 2025) does NOT aspect the lagna.
$moonRow = null;
foreach ($o25['trail'] as $c) { if ($c['planet'] === 'Moon') { $moonRow = $c; } }
check('2025 Moon dropped (no lagna drishti)', $moonRow !== null && $moonRow['aspects'] === false);
check('winner is a lagna-aspecting candidate', (function () use ($o25) {
    foreach ($o25['trail'] as $c) { if (!empty($c['selected'])) { return $c['aspects'] === true; } }
    return false;
})());

// band (shloka 37) combines varsha + natal.
check('2025 band is combined varsha+natal', in_array($o25['band'], ['full', 'madhya', 'heen'], true)
    && ($o25['band'] === 'madhya' || $o25['v_band'] === $o25['n_band']),
    'v=' . $o25['v_band'] . ' n=' . $o25['n_band'] . ' -> ' . $o25['band']);
check('phal text present for winner+band', $o25['phal'] !== '');
check('modifiers include shloka 37 + 44', (function () use ($o25) {
    $keys = array_column($o25['modifiers'], 'key');
    return in_array('sp_37', $keys, true) && in_array('sp_44', $keys, true);
})());

// Fallback: synthetic Varshaphal where no office-bearer aspects the lagna
// (place every candidate 2nd from the lagna = a no-drishti house) -> munthesh.
$fake = $vp25;
$asc = (int) $fake['varsha_chart']['ascendant']['sign_index'];
$noDrishtiSign = ($asc + 1) % 12;                 // 2nd house = no Tajik drishti
foreach ($fake['varsha_chart']['planets'] as $n => &$pp) {
    $pp['sign_index'] = $noDrishtiSign;
    $pp['sidereal_lon'] = $noDrishtiSign * 30.0 + 15.0;
    $pp['deg_in_sign'] = 15.0;
}
unset($pp);
$oFb = VarsheshEngine::compute($fake, $natal, $tajik, $rules, null);
$munthesh = null;
foreach (($fake['varshesh']['offices'] ?? []) as $of) { if (($of['key'] ?? '') === 'muntha') { $munthesh = $of['planet']; } }
check('no candidate aspects lagna -> munthesh fallback', $oFb['winner'] === $munthesh,
    'winner=' . $oFb['winner'] . ' munthesh=' . $munthesh);

echo "\n=== Part 4: reading (2025) ===\n";
echo "वर्षेश: {$o25['winner']} · बैंड {$o25['band_hi']} · बल {$o25['bala20']}/20\n";
echo "विधि: {$o25['method']}\n";
foreach ($o25['trail'] as $c) {
    echo sprintf("  %-8s %6.2f  %-6s  drishti=%-4s  %s\n", $c['planet'], $c['bala'], $c['band_hi'], $c['aspects'] ? 'हाँ' : 'नहीं', $c['reason']);
}
echo "modifiers: " . implode(' | ', array_map(static fn($m) => $m['key'] . ':' . $m['tone'], $o25['modifiers'])) . "\n";

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n");
exit($fails === 0 ? 0 : 1);
