<?php
declare(strict_types=1);

/**
 * Shadbala PL-match harness — reference chart 01-12-1980 12:31 Moga, Punjab.
 * Prints our per-planet six-fold breakdown next to Parashara's Light targets
 * (from shadbala_pl_corrections.md) and the delta, so corrections can be
 * verified to ±0.5 virupa. Run:  php shadbala_pl_test.php
 */

require __DIR__ . '/../bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;

$lat = 30.8165; $lon = 75.1717; $tz = 5.5;
$jd = JulianDay::fromGregorian(1980, 12, 1, 12, 31, 0.0, $tz);
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
$chart = $engine->computeChart($jd, $lat, $lon);
$sb = $chart['shadbala'];
$bb = $chart['bhava_bala'];

// PL targets (virupas) from the corrections doc.
$PL = [
    'Sun'     => ['ayana' => 1.95,  'total' => 505.60, 'ratio' => 1.30, 'drig' => -2.69],
    'Moon'    => ['ayana' => 29.95, 'total' => 386.93, 'ratio' => 1.07, 'drig' => -5.81, 'ishta' => 21.01, 'kashta' => 38.99],
    'Mars'    => ['ayana' => 0.22,  'total' => 427.72, 'ratio' => 1.43, 'drig' => 34.18, 'ishta' => 34.58, 'kashta' => 25.42, 'chesta' => 24.01],
    'Mercury' => ['ayana' => 54.03, 'total' => 472.86, 'ratio' => 1.13, 'drig' => -2.07, 'ishta' => 41.28, 'kashta' => 18.72, 'chesta' => 37.44],
    'Jupiter' => ['ayana' => 26.83, 'total' => 412.95, 'ratio' => 1.06, 'drig' => -4.26, 'ishta' => 37.57, 'kashta' => 22.43, 'chesta' => 37.64],
    'Venus'   => ['ayana' => 11.15, 'total' => 393.26, 'ratio' => 1.19, 'drig' => -0.28, 'ishta' => 18.12, 'kashta' => 41.88, 'chesta' => 30.13],
    'Saturn'  => ['ayana' => 34.05, 'total' => 401.29, 'ratio' => 1.34, 'drig' => -3.84, 'ishta' => 41.71, 'kashta' => 18.29, 'chesta' => 35.36],
];
$PL_BHAVA = [1=>544,2=>479,3=>481,4=>491,5=>549,6=>443,7=>549,8=>493,9=>487,10=>409,11=>419,12=>515];

// Positions (for context).
echo "== positions (sidereal / tropical / decl) ==\n";
foreach (['Sun','Moon','Mars','Mercury','Jupiter','Venus','Saturn'] as $p) {
    $pl = $chart['planets'][$p];
    printf("  %-8s sid %8.3f  trop %8.3f\n", $p, $pl['sidereal_lon'], $pl['tropical_lon']);
}

echo "\n== Shadbala breakdown (ours vs PL) ==\n";
printf("%-8s %8s %8s %8s %8s %8s %8s | %8s %8s | %6s %6s\n",
    'PLANET','STHANA','DIG','KAALA','CHESTA','NAISARG','DRIG','TOTAL','PL_TOT','RATIO','PL_RAT');
foreach (['Sun','Moon','Mars','Mercury','Jupiter','Venus','Saturn'] as $p) {
    $r = $sb[$p];
    $t = $PL[$p];
    $flag = abs($r['total_virupa'] - $t['total']) <= 0.5 ? 'OK ' : sprintf('Δ%+.1f', $r['total_virupa'] - $t['total']);
    printf("%-8s %8.1f %8.1f %8.1f %8.1f %8.1f %8.1f | %8.1f %8.1f | %5.2f %5.2f  %s\n",
        $p, $r['sthana']['total'], $r['dig'], $r['kaala'], $r['chesta'], $r['naisargika'], $r['drig'],
        $r['total_virupa'], $t['total'], $r['ratio'], $t['ratio'], $flag);
}

echo "\n== Ayana / Drig / Ishta / Kashta vs PL ==\n";
printf("%-8s %8s %8s | %8s %8s | %8s %8s | %8s %8s\n",
    'PLANET','DRIG','PL_DRIG','ISHTA','PL_ISH','KASHTA','PL_KASH','CHESTA','PL_CHE');
foreach (['Sun','Moon','Mars','Mercury','Jupiter','Venus','Saturn'] as $p) {
    $r = $sb[$p]; $t = $PL[$p];
    printf("%-8s %8.2f %8s | %8.2f %8s | %8.2f %8s | %8.2f %8s\n",
        $p, $r['drig'], isset($t['drig'])?sprintf('%.2f',$t['drig']):'-',
        $r['ishta'], isset($t['ishta'])?sprintf('%.2f',$t['ishta']):'-',
        $r['kashta'], isset($t['kashta'])?sprintf('%.2f',$t['kashta']):'-',
        $r['chesta'], isset($t['chesta'])?sprintf('%.2f',$t['chesta']):'-');
}

echo "\n== Bhava Bala (ours vs PL) ==\n";
$maxd = 0;
for ($h=1;$h<=12;$h++){
    $ours = $bb[$h]['total_virupa'];
    $d = $ours - $PL_BHAVA[$h];
    $maxd = max($maxd, abs($d));
    printf("  H%-2d  ours %6.0f   PL %5d   Δ %+5.0f\n", $h, $ours, $PL_BHAVA[$h], $d);
}
printf("  max |Δ| = %.0f\n", $maxd);

// Summary — how close the ratio (÷ min-req, the number shown on screen) is.
// Deterministic components (Sthana/Dig/Naisargika/Ayana/Kaala-Paksha/Ishta-
// Kashta/Bhava-Dig) match PL; the residuals below are the five star planets'
// Chesta (PL's internal anomaly model) and Mars/Mercury Drig.
echo "\n== ratio (÷ min-req) — the on-screen number ==\n";
$within=0;
foreach ($PL as $p=>$t){
    $d = $sb[$p]['ratio'] - $t['ratio'];
    if (abs($d) <= 0.05) { $within++; }
    printf("  %-8s ours %.2f  PL %.2f  Δ %+.2f  %s\n", $p, $sb[$p]['ratio'], $t['ratio'], $d,
        abs($d) <= 0.05 ? 'ok' : 'residual');
}
echo "  $within / 7 planets within ±0.05 of PL ratio\n";
