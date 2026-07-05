<?php
declare(strict_types=1);

/**
 * sadesati_test.php — verification for the Shani Sade Sati / Paya overlay
 * (migration 020 / Gochar Update 04) and the DashaSupport functional-role
 * service (Update 05). Confirms detection, severity inputs, Paya, and — most
 * importantly — that the overlay does NOT change the existing Layer-1 Saturn
 * text (C16) and keeps the Paya score separate (C15).
 *
 * Run from the project root:  php sadesati_test.php
 */

require __DIR__ . '/bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Astro\Gochar\DashaSupport;
use AutoBusiness\Astro\Gochar\GocharPhalEngine;
use AutoBusiness\Astro\Gochar\GocharRepository;
use AutoBusiness\Astro\Gochar\SadeSatiRepository;

$fails = 0;
function check(string $label, bool $ok, string $note = ''): void
{
    global $fails;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($note !== '' ? " — $note" : '') . "\n";
    if (!$ok) { $fails++; }
}

echo "=== Part 1: rule data ===\n";
$ss = SadeSatiRepository::load('hi');
check('12 paya rows', count($ss['paya']) === 12, (string) count($ss['paya']));
check('paya 1 = Gold/ashubh', ($ss['paya'][1]['shubh'] ?? true) === false && str_contains($ss['paya'][1]['metal'], 'सोना'));
check('paya 2 = Silver/shubh', ($ss['paya'][2]['shubh'] ?? false) === true);
check('phase/dhaiyya/pancham phal present',
    isset($ss['phal']['phase1'], $ss['phal']['phase2'], $ss['phal']['phase3'], $ss['phal']['dhaiyya'], $ss['phal']['pancham']));

echo "\n=== Part 2: DashaSupport functional roles (Update 05) ===\n";
// Classical yogakaraka anchors (0=Aries..11=Pisces): Kark(3)/Simha(4)=Mars,
// Vrishabh(1)/Tula(6)=Saturn, Makar(9)/Kumbh(10)=Venus.
check('Mars yogakaraka for Cancer', DashaSupport::functionalRole('Mars', 3)['yogakaraka']);
check('Saturn yogakaraka for Libra', DashaSupport::functionalRole('Saturn', 6)['yogakaraka']);
check('Venus yogakaraka for Capricorn', DashaSupport::functionalRole('Venus', 9)['yogakaraka']);
check('Jupiter NOT yogakaraka for Aries', DashaSupport::functionalRole('Jupiter', 0)['yogakaraka'] === false);

echo "\n=== Part 3: detection + no-conflict overlay ===\n";
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
// Surrey birth (from the owner's screenshot): Moon in Pisces.
$natal = $engine->computeChart(JulianDay::fromGregorian(1983, 12, 12, 12, 36, 0.0, -8.0), 49.1063, -122.8251);
$moonSign = $natal['planets']['Moon']['sign'];
$rules = GocharRepository::load('hi');
$jdG = JulianDay::fromGregorian(2026, 7, 6, 19, 0, 0.0, -7.0);
$g = $engine->gochar($natal, $jdG, 49.1063, -122.8251);

// Layer-1 Saturn text WITHOUT the overlay (3-arg call) vs WITH (4-arg).
$noOverlay = GocharPhalEngine::compute($natal, $g['transits'], $rules);
$withOverlay = GocharPhalEngine::compute($natal, $g['transits'], $rules, $jdG);
$satText = static function (array $out): string {
    foreach ($out['layer1'] as $e) { if ($e['planet'] === 'Saturn') { return $e['text']; } }
    return '';
};
check('natal Moon = Pisces (Sade Sati chart)', $moonSign === 'Pisces', $moonSign);
check('Layer-1 Saturn text identical with/without overlay (C16)', $satText($noOverlay) === $satText($withOverlay));
check('Layer-1 count unchanged by overlay', count($noOverlay['layer1']) === count($withOverlay['layer1']));

$sp = $withOverlay['shani_special'];
check('shani_special present', $sp !== null);
check('Sade Sati active (Saturn in Pisces = 1st from Moon)', !empty($sp['active']) && $sp['house'] === 1);
check('phase = 2 (peak)', ($sp['phase'] ?? 0) === 2);
check('severity in 0..3', $sp['severity'] >= 0 && $sp['severity'] <= 3, (string) $sp['severity']);
check('paya present and separate from Layer 1', isset($sp['paya']['metal']));
check('event map non-empty (Saturn conjunct/aspects natal planets)', !empty($sp['events']));

// Dhaiyya / Pancham / none by synthetic Saturn houses from Moon.
$mk = static function (int $satSign) use ($g): array {
    $t = $g['transits']; $t['Saturn']['sign_index'] = $satSign;
    $t['Saturn']['sidereal_lon'] = $satSign * 30.0 + 5.0; $t['Saturn']['deg_in_sign'] = 5.0;
    return $t;
};
$moonIdx = (int) $natal['planets']['Moon']['sign_index'];
$dhai = GocharPhalEngine::compute($natal, $mk(($moonIdx + 3) % 12), $rules, $jdG)['shani_special'];  // 4th
check('Saturn 4th from Moon -> Dhaiyya', ($dhai['type'] ?? '') === 'dhaiyya');
$panc = GocharPhalEngine::compute($natal, $mk(($moonIdx + 4) % 12), $rules, $jdG)['shani_special'];  // 5th
check('Saturn 5th from Moon -> Pancham', ($panc['type'] ?? '') === 'pancham');
$none = GocharPhalEngine::compute($natal, $mk(($moonIdx + 2) % 12), $rules, $jdG)['shani_special'];   // 3rd
check('Saturn 3rd from Moon -> not active (paya only)', ($none['active'] ?? true) === false);

echo "\n=== reading (Surrey, 2026) ===\n";
echo "मून: $moonSign · " . ($sp['type_hi'] ?? '') . " · तीव्रता " . ($sp['severity_hi'] ?? '') . " · पाया " . ($sp['paya']['metal'] ?? '') . "\n";

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n");
exit($fails === 0 ? 0 : 1);
