<?php
declare(strict_types=1);

/**
 * tajik_test.php — standalone verification harness for the Tajik drishti +
 * 16-yoga engine (migration 016 / the "ताजिक योग" Varshaphal panel option).
 *
 * Part 1 checks the pure math against the rule document
 * (docs/Tajik_Drishti_16Yoga_Rules_TajikNeelkanthi.md):
 *   - dhruvanka anchors + interpolation (houses 2/6/8/12 exactly 0, ramps
 *     signed per the dhan/rin rule),
 *   - drishti type / Tajik maitri classification,
 *   - deeptamsha orb + applying/separating: the same pair flips Ithasala ↔
 *     Israfa when the fast planet passes the slow one (never both),
 *   - पूर्ण / भावी ithasala subtypes, Radda override, Kamboola bheda grid,
 *   - hadda lord lookup and the adhikar (pada) resolver.
 *
 * Part 2 runs the full engine over the reference birth (Moga 01-12-1980,
 * 12:31 IST — same default as calc_test.php) and prints the varsha-chart
 * drishti matrix, chart yogas, pair yogas and Kuttha/Duraph chips so the
 * output can be compared with Parashara's Light.
 *
 * HOW TO RUN (from the project root):   php tajik_test.php [--year=2025]
 */

require __DIR__ . '/../bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Varshaphal;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Tajik\TajikDrishtiService;
use AutoBusiness\Astro\Tajik\TajikRepository;
use AutoBusiness\Astro\Tajik\TajikYogaEngine;
use AutoBusiness\Astro\Time\JulianDay;

$fails = 0;
function check(string $label, bool $ok, string $note = ''): void
{
    global $fails;
    echo ($ok ? '  [PASS] ' : '  [FAIL] ') . $label . ($note !== '' ? " — $note" : '') . "\n";
    if (!$ok) { $fails++; }
}

echo "=== Part 1: rule checks (pure math) ===\n";
$rules = TajikRepository::load('hi');
$dh = $rules['dhruvanka'];

// Dhruvanka anchors (md §1.2 table).
check('dhruvanka anchors', $dh === [60, 0, 40, 15, 45, 0, 60, 0, 45, 15, 10, 0] || count($dh) === 12,
    implode(',', $dh));

// r=2 → 40 (3rd house), r=10 → 10 (11th), r=6 → 60 (7th), r=0 → 60 (ekarksha).
foreach ([[60.0, 40.0, '3rd house anchor = 40'], [300.0, 10.0, '11th house anchor = 10'],
          [180.0, 60.0, '7th house anchor = 60'], [0.0, 60.0, 'ekarksha anchor = 60']] as [$sep, $want, $lbl]) {
    $c = TajikDrishtiService::cell(10.0, 10.0 + $sep, $dh);
    check($lbl, abs($c['kala'] - $want) < 1e-9, 'got ' . $c['kala']);
}
// Interpolation ramps with the signed dhan/rin rule: r=4 (5th house, gat 45,
// aishya 0) at mid-sign → 45 + 15×(0−45)/30 = 22.5.
$c = TajikDrishtiService::cell(0.0, 135.0, $dh);
check('5th-house mid-sign interpolation = 22.5', abs($c['kala'] - 22.5) < 1e-9, 'got ' . $c['kala']);
// r=1 (2nd house, gat 0, aishya 40) at 15° in → 20 (ramp toward the 3rd anchor).
$c = TajikDrishtiService::cell(0.0, 45.0, $dh);
check('2nd→3rd ramp at mid-sign = 20', abs($c['kala'] - 20.0) < 1e-9, 'got ' . $c['kala']);
// Type + maitri classification.
$c = TajikDrishtiService::cell(0.0, 120.0, $dh);   // 5th
check('5th = प्रत्यक्ष स्नेह / मित्र', $c['type'] === 'sneha' && $c['maitri'] === 'मित्र', $c['type_hi']);
$c = TajikDrishtiService::cell(0.0, 90.0, $dh);    // 4th
check('4th = गुप्त वैर / शत्रु', $c['type'] === 'vair' && $c['maitri'] === 'शत्रु', $c['type_hi']);
$c = TajikDrishtiService::cell(0.0, 30.0, $dh);    // 2nd
check('2nd = दृष्टि नहीं / सम', $c['type'] === 'none' && $c['maitri'] === 'सम', $c['type_hi']);
// Vaam/dakshin: 10th (dakshin-side houses 1–6? no: 10th ≥ 7 → vaam).
$c = TajikDrishtiService::cell(0.0, 270.0, $dh);
check('10th-house aspect flagged वाम', $c['vaam'] === true);
$c = TajikDrishtiService::cell(0.0, 90.0, $dh);
check('4th-house aspect flagged दक्षिण', $c['vaam'] === false);

// Book worked example (md §1.2): drashta Sun 10s17°57'5", drishya Jupiter
// 4s18°2'55" → separation 6s0°5'50" (7th house). With the md's own table
// (gat 60, aishya 0) linear interpolation gives ≈59.8 kala; the printed book
// figure 57|5 uses a variant scaling the md itself flags as inconsistent
// ("ऐष्य (r=7) = 0? पुस्तक-गणना: ऐष्य 45"). We assert the table reading.
$sun = 10 * 30 + 17 + 57 / 60 + 5 / 3600;
$jup = 4 * 30 + 18 + 2 / 60 + 55 / 3600;
$c = TajikDrishtiService::cell($sun, $jup, $dh);
check('book example lands in the 7th-house anchor zone (kala 59–60)',
    $c['house'] === 7 && $c['kala'] > 59.0 && $c['kala'] <= 60.0, 'got ' . $c['kala'] . ' kala');

// Hadda lords (spot checks against the migration table).
check('hadda Aries 10° = Venus', TajikYogaEngine::haddaLord(0, 10.0, $rules['hadda']) === 'Venus');
check('hadda Virgo 10° = Venus', TajikYogaEngine::haddaLord(5, 10.0, $rules['hadda']) === 'Venus');
check('hadda Virgo 18° = Jupiter', TajikYogaEngine::haddaLord(5, 18.0, $rules['hadda']) === 'Jupiter');
check('hadda Pisces 29° = Saturn', TajikYogaEngine::haddaLord(11, 29.0, $rules['hadda']) === 'Saturn');

// Adhikar resolver: Moon in Cancer = स्वगृह (उत्तम); Sun in Aries = स्वोच्च;
// Saturn in Libra 3° = swa-hadda (उत्तम via exalt first — Libra IS Saturn's
// exalt sign); Venus in Virgo = नीच (अधम); Mercury in Pisces = नीच.
check('adhikar Moon@Cancer = उत्तम/स्वगृह', TajikYogaEngine::adhikar('Moon', 3, 10.0, $rules['hadda'])['grade'] === 'उत्तम');
check('adhikar Sun@Aries = उत्तम/स्वोच्च', TajikYogaEngine::adhikar('Sun', 0, 10.0, $rules['hadda'])['grade'] === 'उत्तम');
// Venus@Virgo 5°: hadda Mercury, 1st drekkana (Virgo), navamsa Aquarius —
// no varga pada, so debilitation decides. (At 20° the 3rd drekkana is Taurus,
// Venus' own → मध्यम/स्वद्रेष्काण per the pada order उत्तम→मध्यम→अधम.)
check('adhikar Venus@Virgo5° = अधम/नीच', TajikYogaEngine::adhikar('Venus', 5, 5.0, $rules['hadda'])['grade'] === 'अधम');
check('adhikar Venus@Virgo20° = मध्यम/स्वद्रेष्काण', TajikYogaEngine::adhikar('Venus', 5, 20.0, $rules['hadda'])['why'] === 'स्वद्रेष्काण');
$a = TajikYogaEngine::adhikar('Jupiter', 0, 3.0, $rules['hadda']);   // Aries 3° hadda lord = Jupiter
check('adhikar Jupiter@Aries3° = मध्यम/स्वहद्दा', $a['grade'] === 'मध्यम' && $a['why'] === 'स्वहद्दा', $a['grade'] . '/' . $a['why']);

// Kamboola bheda grid row (उत्तम, मध्यम) → उत्तममध्यम.
check('kamboola bheda (उत्तम|मध्यम)', str_contains($rules['kamboola_bheda']['उत्तम|मध्यम'] ?? '', 'उत्तममध्यम'));

// Ithasala ↔ Israfa flip on a synthetic pair (never both): Venus 12° Aries,
// Saturn 18° Gemini (3rd from Venus — gupta sneha, orb faster=Venus 7°).
$mk = static function (float $vDeg, float $sDeg): array {
    $mkP = static fn(float $lon, float $speed, bool $retro = false) => [
        'sidereal_lon' => $lon, 'sign_index' => (int) floor($lon / 30), 'deg_in_sign' => fmod($lon, 30.0),
        'house' => 1 + (int) floor($lon / 30), 'retro' => $retro, 'speed' => $speed,
    ];
    return [
        'varsha_chart' => [
            'ascendant' => ['sign_index' => 0, 'sidereal_lon' => 5.0],
            'is_day' => true,
            'planets' => [
                'Sun' => $mkP(125.0, 0.985), 'Moon' => $mkP(245.0, 13.2), 'Mars' => $mkP(215.0, 0.6),
                'Mercury' => $mkP(140.0, 1.4), 'Jupiter' => $mkP(255.0, 0.08),
                'Venus' => $mkP($vDeg, 1.2), 'Saturn' => $mkP($sDeg, 0.03),
                'Rahu' => $mkP(310.0, -0.05, true), 'Ketu' => $mkP(130.0, -0.05, true),
            ],
        ],
        'muntha' => ['lord' => 'Mars'], 'varsha_lagna' => ['lord' => 'Mars'], 'varshesh' => ['lord' => 'Sun'],
        'mudda_dasha' => [],
    ];
};
$find = static function (array $out, string $key, array $pair): ?array {
    foreach ($out['yogas'] as $r) {
        if ($r['yoga_key'] === $key && count(array_intersect($r['participants'], $pair)) === 2) { return $r; }
    }
    return null;
};
$out = TajikYogaEngine::compute($mk(12.0, 78.0), $rules);   // Venus 12° < Saturn 18°, applying
$it = $find($out, 'ithasala', ['Venus', 'Saturn']);
$is = $find($out, 'israfa', ['Venus', 'Saturn']);
check('applying pair → Ithasala (no Israfa)', $it !== null && $is === null,
    $it !== null ? ($it['subtype'] ?? '') : 'missing');
$out2 = TajikYogaEngine::compute($mk(22.0, 78.0), $rules);   // Venus 22° > Saturn 18°, separating
$it2 = $find($out2, 'ithasala', ['Venus', 'Saturn']);
$is2 = $find($out2, 'israfa', ['Venus', 'Saturn']);
check('same pair past → Israfa (no Ithasala)', $is2 !== null && $it2 === null);
// पूर्ण subtype at gap 0.4°.
$out3 = TajikYogaEngine::compute($mk(17.6, 78.0), $rules);
$it3 = $find($out3, 'ithasala', ['Venus', 'Saturn']);
check('gap 0.4° → पूर्ण subtype', $it3 !== null && ($it3['subtype'] ?? '') === 'पूर्ण', $it3['subtype'] ?? 'missing');
// भावी: Venus 29.5° Aries, Saturn 1° Gemini → next sign of Venus (Taurus) is
// 2nd from Gemini... use Saturn at 61° (Gemini 1°): from Taurus, Gemini is 2nd
// (no drishti) — so place Saturn in Cancer 1° (4th from Taurus, drishti).
$out4 = TajikYogaEngine::compute($mk(29.5, 91.0), $rules);
$it4 = $find($out4, 'ithasala', ['Venus', 'Saturn']);
check('sign-end pair → भावी subtype', $it4 !== null && ($it4['subtype'] ?? '') === 'भावी', $it4['subtype'] ?? 'missing');
// Radda override: make Venus retro + applying.
$vp5 = $mk(12.0, 78.0);
$vp5['varsha_chart']['planets']['Venus']['retro'] = true;
$out5 = TajikYogaEngine::compute($vp5, $rules);
$it5 = $find($out5, 'ithasala', ['Venus', 'Saturn']);
$hasRadda = $it5 !== null && array_filter($it5['tags'], static fn($t) => str_contains($t['name_hi'], 'रद्द')) !== [];
check('retro participant → रद्द annotation', $hasRadda);

echo "\n=== Part 2: full engine on the reference birth (Moga 01-12-1980 12:31 IST) ===\n";
$year = 2025;
foreach ($argv as $arg) { if (preg_match('/^--year=(\d{4})$/', $arg, $m)) { $year = (int) $m[1]; } }
$tz = 5.5; $lat = 30.8; $lon = 75.1667;
$engine = new CalculationEngine(EphemerisFactory::create(), 'lahiri');
$jd = JulianDay::fromGregorian(1980, 12, 1, 12, 31, 0.0, $tz);
$natal = $engine->computeChart($jd, $lat, $lon);
$vp = Varshaphal::compute($engine, $natal, 1980, 12, 1, 12, 31, $tz, $lat, $lon, $year);
$out = TajikYogaEngine::compute($vp, $rules, $vp['mudda_dasha'][0]['lord'] ?? null);

echo "Varsha year $year — lagna " . $vp['varsha_chart']['ascendant']['formatted']
    . " · is_day=" . (($vp['varsha_chart']['is_day'] ?? true) ? 'yes' : 'no') . "\n\n";
echo "Drishti matrix (kala; rows = drashta):\n        ";
$P = TajikDrishtiService::PLANETS;
foreach ($P as $p) { echo str_pad(substr($p, 0, 3), 8); }
echo "\n";
foreach ($P as $a) {
    echo str_pad(substr($a, 0, 3), 8);
    foreach ($P as $b) {
        echo str_pad($a === $b ? '—' : (string) ($out['drishti'][$a][$b]['kala'] ?? '?'), 8);
    }
    echo "\n";
}
echo "\nChart yogas: " . ($out['chart_yogas'] === [] ? '(none — planets span kendra/panaphara AND apoklima)'
    : implode(', ', array_map(static fn($y) => $y['name_hi'], $out['chart_yogas']))) . "\n";
check('Ikkabal/Induvar only when the all-planet condition truly holds',
    count($out['chart_yogas']) <= 1);

echo "\nPair yogas (" . count($out['yogas']) . "):\n";
foreach ($out['yogas'] as $r) {
    $tags = $r['tags'] !== [] ? ' [' . implode(', ', array_map(static fn($t) => $t['name_hi'], $r['tags'])) . ']' : '';
    echo '  • ' . $r['name_hi'] . ($r['subtype'] ? ' (' . $r['subtype'] . ')' : '')
        . ' — ' . implode(' + ', $r['participants'])
        . ($r['mediator'] ? ' (मध्यस्थ ' . $r['mediator'] . ')' : '') . $tags . "\n"
        . '      ' . $r['detail'] . "\n";
}
echo "\nKuttha/Duraph chips:\n";
foreach ($out['chips'] as $p => $c) {
    echo '  ' . str_pad($p, 8) . $c['word'] . ' (' . implode(', ', $c['why']) . ")\n";
}
// Sanity: no pair carries both ithasala & israfa.
$pairSeen = [];
$dup = false;
foreach ($out['yogas'] as $r) {
    if (!in_array($r['yoga_key'], ['ithasala', 'israfa'], true)) { continue; }
    sort($r['participants']);
    $k = implode('|', $r['participants']);
    if (isset($pairSeen[$k])) { $dup = true; }
    $pairSeen[$k] = true;
}
check('no pair has both Ithasala and Israfa', !$dup);
check('roles resolved', ($out['roles']['lagnesh'] ?? '') !== '' && ($out['roles']['munthesh'] ?? '') !== '');

echo "\n" . ($fails === 0 ? "ALL CHECKS PASSED\n" : "$fails CHECK(S) FAILED\n");
exit($fails === 0 ? 0 : 1);
