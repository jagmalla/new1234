<?php
declare(strict_types=1);

/**
 * muhurat_test.php — verification for the Gochar Muhurat module (migration 022):
 * Rahu Kaal (Part 2), Disha Shul (Part 3), Tithi (Part 5), janma-nakshatra
 * weekday phal (Part 7), combustion warnings (Part 4) and Shani-AV kashta rashi
 * (Part 1). Confirms the baked data, the per-rule computation, and that the
 * "मुहूर्त" category is ADDITIVE (does not alter the Layer-1 house texts).
 *
 * Run from the project root:  php muhurat_test.php
 */

require __DIR__ . '/../bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Astro\Gochar\GocharPhalEngine;
use AutoBusiness\Astro\Gochar\GocharRepository;
use AutoBusiness\Astro\Gochar\MuhuratData;
use AutoBusiness\Astro\Gochar\MuhuratEngine;
use AutoBusiness\Astro\Gochar\MuhuratRepository;

$fails = 0;
function check(string $label, bool $ok, string $note = ''): void
{
    global $fails;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($note !== '' ? " — $note" : '') . "\n";
    if (!$ok) { $fails++; }
}

echo "=== Part A: rule data ===\n";
$r = MuhuratRepository::load('hi');
check('5 tithi rows (नन्दा..पूर्णा)', count($r['tithi']) === 5, (string) count($r['tithi']));
check('तिथि 4 = रिक्ता / शनि', ($r['tithi'][4]['name'] ?? '') === 'रिक्ता' && ($r['tithi'][4]['lord'] ?? '') === 'शनि');
check('7 disha-shul rows', count($r['disha']) === 7, (string) count($r['disha']));
check('रविवार वर्जित दिशा = पश्चिम', ($r['disha'][0]['dir'] ?? '') === 'पश्चिम');
check('गुरुवार वर्जित दिशा = नैऋत्य', str_contains($r['disha'][4]['dir'] ?? '', 'नैऋत्य'));
check('7 nak-vaar phal rows', count($r['nak_vaar']) === 7, (string) count($r['nak_vaar']));
check('मंगलवार tithi bonus = 3,8,13', ($r['nak_vaar'][2]['bonus'] ?? []) === [3, 8, 13]);
check('2 combust warnings (गुरु/शुक्र)', count($r['combust']) === 2 && isset($r['combust']['Jupiter'], $r['combust']['Venus']));

echo "\n=== Part B: Rahu Kaal (Part 2) ===\n";
// रवि=8, सोम=2, मंगल=7, बुध=5, गुरु=6, शुक्र=4, शनि=3
$expPart = [0 => 8, 1 => 2, 2 => 7, 3 => 5, 4 => 6, 5 => 4, 6 => 3];
foreach ($expPart as $wd => $p) {
    check('RAHU_KAAL_PART[' . $wd . '] = ' . $p, (MuhuratData::RAHU_KAAL_PART[$wd] ?? 0) === $p);
}

echo "\n=== Part C: engine computation ===\n";
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
$natalJd = JulianDay::fromGregorian(1980, 12, 1, 12, 31, 0.0, 5.5);
$natal = $engine->computeChart($natalJd, 30.8, 75.1667);

// Sunday: Rahu Kaal = 8th part = 16:30–18:00 (default 6:00 sunrise / 18:00 sunset).
$sunday = MuhuratEngine::compute($natal, ['Sun' => ['sidereal_lon' => 0.0, 'sign_index' => 0], 'Moon' => ['sidereal_lon' => 124.0, 'sign_index' => 4]], $r, 0);
check('रविवार राहु काल भाग = 8', ($sunday['rahu_kaal']['part'] ?? 0) === 8, (string) ($sunday['rahu_kaal']['part'] ?? 0));
check('रविवार राहु काल 16:30–18:00', ($sunday['rahu_kaal']['start'] ?? '') === '16:30' && ($sunday['rahu_kaal']['end'] ?? '') === '18:00',
    ($sunday['rahu_kaal']['start'] ?? '') . '–' . ($sunday['rahu_kaal']['end'] ?? ''));
check('रविवार दिशा शूल = पश्चिम', ($sunday['disha_shul']['dir'] ?? '') === 'पश्चिम');

// Tithi from Moon 124°, Sun 0° → diff 124 → floor(124/12)=10 → tithi 11 (एकादशी),
// शुक्ल पक्ष, नाम नन्दा (11 → ((11-1)%15)%5+1 = 1), subgroup 2 (अन्तिम) → शुभ.
$ti = $sunday['tithi'] ?? [];
check('तिथि संख्या = 11', ($ti['num'] ?? 0) === 11, (string) ($ti['num'] ?? 0));
check('तिथि पक्ष = शुक्ल', ($ti['paksha_hi'] ?? '') === 'शुक्ल पक्ष');
check('तिथि नाम = नन्दा', ($ti['name'] ?? '') === 'नन्दा');
check('तिथि ग्रेड = शुभ (अन्तिम समूह, शुक्ल)', ($ti['grade'] ?? '') === 'शुभ', ($ti['grade'] ?? ''));

// Friday tithi 3 (जया) → कृष्ण? tithi 3 = शुक्ल पहला समूह → अशुभ.
$t3 = MuhuratEngine::compute($natal, ['Sun' => ['sidereal_lon' => 0.0, 'sign_index' => 0], 'Moon' => ['sidereal_lon' => 30.0, 'sign_index' => 1]], $r, 5)['tithi'];
check('तिथि 3 (जया) शुक्ल पहला समूह = अशुभ', ($t3['num'] ?? 0) === 3 && ($t3['name'] ?? '') === 'जया' && ($t3['grade'] ?? '') === 'अशुभ');

// Krishna-paksha tithi 18 (Moon 210°, Sun 0° → diff 210 → floor/12=17 → tithi 18)
// → कृष्ण, posInPaksha=2 (जया), subgroup 0 (पहला) → कृष्ण पहला = शुभ.
$t18 = MuhuratEngine::compute($natal, ['Sun' => ['sidereal_lon' => 0.0, 'sign_index' => 0], 'Moon' => ['sidereal_lon' => 210.0, 'sign_index' => 7]], $r, 3)['tithi'];
check('तिथि 18 कृष्ण पहला समूह = शुभ', ($t18['num'] ?? 0) === 18 && ($t18['paksha_hi'] ?? '') === 'कृष्ण पक्ष' && ($t18['grade'] ?? '') === 'शुभ',
    'num=' . ($t18['num'] ?? 0) . ' grade=' . ($t18['grade'] ?? ''));

echo "\n=== Part D: combustion warning (Part 4) ===\n";
// Jupiter within combust orb of the Sun → विवाह चेतावनी.
$combTransits = [
    'Sun' => ['sidereal_lon' => 100.0, 'sign_index' => 3, 'retro' => false],
    'Jupiter' => ['sidereal_lon' => 102.0, 'sign_index' => 3, 'retro' => false],
    'Moon' => ['sidereal_lon' => 124.0, 'sign_index' => 4],
];
$mc = MuhuratEngine::compute($natal, $combTransits, $r, 4);
$hasJup = false; foreach (($mc['combust']['warns'] ?? []) as $w) { if ($w['planet'] === 'Jupiter') { $hasJup = true; } }
check('गुरु अस्त → विवाह चेतावनी', $hasJup);

echo "\n=== Part E: janma-nakshatra weekday phal (Part 7) ===\n";
$jn = $sunday['janma_nak_phal'] ?? [];
check('जन्म-नक्षत्र फल text present (रविवार)', str_contains($jn['phal'] ?? '', 'कलह'));
check('janma nakshatra name present', ($jn['janma_nak'] ?? '') !== '');

echo "\n=== Part F: kashta rashi (Part 1) ===\n";
$kr = $sunday['kashta_rashi'] ?? [];
check('कष्ट-राशि least-bindu computed', isset($kr['bindu']) && !empty($kr['signs']));
$satBav = $natal['ashtakavarga']['bav']['Saturn'] ?? [];
check('कष्ट-राशि बिन्दु = शनि-BAV न्यूनतम', $satBav !== [] && (int) ($kr['bindu'] ?? -1) === (int) min($satBav),
    'kr=' . ($kr['bindu'] ?? '?') . ' min=' . ($satBav !== [] ? min($satBav) : '?'));

echo "\n=== Part G: integration + no-conflict ===\n";
$rules = GocharRepository::load('hi');
$jdG = JulianDay::fromGregorian(2026, 7, 6, 19, 0, 0.0, 5.5);   // 2026-07-06 is a Monday
$g = $engine->gochar($natal, $jdG, 30.8, 75.1667);
$noWd = GocharPhalEngine::compute($natal, $g['transits'], $rules);            // no weekday/jd → muhurat null
$out = GocharPhalEngine::compute($natal, $g['transits'], $rules, $jdG, 1);    // Monday
check('muhurat null when no weekday/jd', array_key_exists('muhurat', $noWd) && $noWd['muhurat'] === null);
check('muhurat present with weekday', is_array($out['muhurat'] ?? null));
check('सोमवार weekday_hi', ($out['muhurat']['weekday_hi'] ?? '') === 'सोमवार');
check('सोमवार राहु काल भाग = 2', ($out['muhurat']['rahu_kaal']['part'] ?? 0) === 2);

// NO CONFLICT: Layer-1 Saturn text identical with/without the muhurat overlay.
$satText = static function (array $o): string {
    foreach ($o['layer1'] as $e) { if ($e['planet'] === 'Saturn') { return $e['text']; } }
    return '';
};
check('Layer-1 Saturn text unchanged by मुहूर्त', $satText($noWd) === $satText($out));
check('AV categories still present alongside मुहूर्त', count($out['av']['bindu']) === 7);

echo "\n=== reading (2026-07-06 सोमवार) ===\n";
$m = $out['muhurat'];
echo sprintf("  वार %s · राहु काल %s–%s · दिशा शूल %s · तिथि %d %s (%s=%s)\n",
    $m['weekday_hi'], $m['rahu_kaal']['start'], $m['rahu_kaal']['end'],
    $m['disha_shul']['dir'], $m['tithi']['num'], $m['tithi']['name'], $m['tithi']['group_label'], $m['tithi']['grade']);

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n");
exit($fails === 0 ? 0 : 1);
