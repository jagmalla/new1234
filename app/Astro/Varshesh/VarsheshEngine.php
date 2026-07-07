<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Varshesh;

use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;
use AutoBusiness\Astro\Calc\Varshesha;
use AutoBusiness\Astro\Tajik\TajikDrishtiService;
use AutoBusiness\Astro\Tajik\TajikYogaEngine;

/**
 * Varshesh (year-lord) selection + phal — Tajik Neelkanthi, Varsha-tantra
 * shloka 9–44.
 *
 * SELECTION (shloka 9–12): among the five Panchadhikari office-bearers, keep
 *   those that aspect the Varsha Lagna by Tajik sphuta drishti; the strongest
 *   (Panchavargeeya bala) of those is the Varshesh. Ties go to the Varsha
 *   Lagnesh (config); if none aspect the lagna, or all who do are हीन, the
 *   Munthesh is the year-lord (fallback). The full decision trail is returned.
 * BAND (shloka 37): the winner's Panchavargeeya band is judged in BOTH the
 *   natal and the varsha chart (both पूर्ण → पूर्ण, both हीन → हीन, else मध्यम).
 * PHAL (shloka 13–36): the 21-row band-graded text, a shloka-13 bonus line
 *   when the winner is outside 6/8/12, un-combust and natal-strong, and the
 *   fired shloka 37–44 modifiers (ithasala/israfa/kamboola from the Tajik
 *   engine).
 *
 * Reuses the existing PL-matched Panchavargeeya ({@see Varshesha::components})
 * and the Tajik drishti/yoga services — no astronomy is recomputed here.
 */
final class VarsheshEngine
{
    private const BAND_HI = ['full' => 'पूर्ण', 'madhya' => 'मध्यम', 'heen' => 'हीन'];

    /**
     * @param array<string,mixed> $vp     Varshaphal::compute output (has varshesh offices+table)
     * @param array<string,mixed> $natal  natal CalculationEngine::computeChart
     * @param array<string,mixed> $tajik  TajikRepository::load (dhruvanka etc.)
     * @param array<string,mixed> $rules  VarsheshRepository::load
     * @param string|null $activeMuddaLord running Mudda mahadasha lord
     * @return array<string,mixed>
     */
    public static function compute(array $vp, array $natal, array $tajik, array $rules, ?string $activeMuddaLord = null): array
    {
        $cfg = $rules['config'] ?? [];
        $fullMin = (float) ($cfg['varshesh_full_min'] ?? 45);
        $madhyaMin = (float) ($cfg['varshesh_madhya_min'] ?? 30);
        $drishtiMin = (float) ($cfg['varshesh_lagna_drishti_min'] ?? 1);
        $checkNatal = ($cfg['varshesh_check_natal'] ?? '1') === '1';
        $tieRule = (string) ($cfg['varshesh_tie_rule'] ?? 'varsha_lagnesh');
        $fallback = (string) ($cfg['varshesh_fallback'] ?? 'munthesh');

        $vc = $vp['varsha_chart'] ?? [];
        $vPlanets = $vc['planets'] ?? [];
        $lagnaLon = (float) ($vc['ascendant']['sidereal_lon'] ?? 0.0);
        $isDay = (bool) ($vc['is_day'] ?? true);
        $table = $vp['varshesh']['table'] ?? [];
        $offices = $vp['varshesh']['offices'] ?? [];
        $dh = $tajik['dhruvanka'] ?? [];

        $bandOf = static fn(float $t): string => $t >= $fullMin ? 'full' : ($t >= $madhyaMin ? 'madhya' : 'heen');

        // Office labels of the Varsha Lagnesh / Munthesh (for tie + fallback).
        $lagneshPlanet = null; $munthaPlanet = null;
        foreach ($offices as $o) {
            if (($o['key'] ?? '') === 'varsha_lagna') { $lagneshPlanet = (string) $o['planet']; }
            if (($o['key'] ?? '') === 'muntha') { $munthaPlanet = (string) $o['planet']; }
        }

        // ---- decision trail: one row per DISTINCT candidate planet ----
        $trail = [];
        foreach ($offices as $o) {
            $pl = (string) $o['planet'];
            $tot = (float) ($table[$pl]['total'] ?? 0.0);
            if (isset($trail[$pl])) {
                $trail[$pl]['office'] .= ' · ' . $o['office'];
                continue;
            }
            $cell = isset($vPlanets[$pl])
                ? TajikDrishtiService::cell((float) $vPlanets[$pl]['sidereal_lon'], $lagnaLon, $dh)
                : ['kala' => 0.0, 'type' => 'none', 'type_hi' => 'दृष्टि नहीं'];
            // Aspects the lagna = a real Tajik drishti (sneha/vair), strong enough.
            $aspects = $cell['type'] !== 'none' && (float) $cell['kala'] >= $drishtiMin;
            $trail[$pl] = [
                'planet' => $pl, 'planet_hi' => self::hi($pl),
                'office' => (string) $o['office'],
                'bala' => round($tot, 2), 'bala20' => round($tot / 4.0, 2),
                'band' => $bandOf($tot), 'band_hi' => self::BAND_HI[$bandOf($tot)],
                'drishti_kala' => round((float) $cell['kala'], 1),
                'drishti_type' => (string) $cell['type_hi'],
                'aspects' => $aspects,
                'reason' => '',
            ];
        }
        $trail = array_values($trail);

        // ---- selection ----
        // DEFAULT = Parashara's Light rule: the Varshesh is simply the graha with
        // the greatest Panchavargeeya bala among the five Panchadhikari — NO
        // lagna-drishti filter. This keeps the Varshesh-phal panel consistent with
        // the header tile and the Year-Lord card (both use Varshesha::compute) and
        // matches Parashara's Light. The classical Tajik-Neelkanthi lagna-drishti
        // method stays available behind the `varshesh_method` config flag.
        $methodMode = (string) ($cfg['varshesh_method'] ?? 'parashara');
        $aspecting = array_values(array_filter($trail, static fn($c) => $c['aspects']));
        $method = '';
        $winner = null;

        if ($methodMode !== 'tajik_lagna_drishti') {
            // Parashara: the panchadhikari with the highest Panchavargeeya bala
            // (= Varshesha::compute's winner, already on $vp['varshesh']['lord']).
            $winner = (string) ($vp['varshesh']['lord'] ?? '');
            if ($winner === '' && $trail !== []) {
                $byBala = $trail;
                usort($byBala, static fn($a, $b) => $b['bala'] <=> $a['bala']);
                $winner = $byBala[0]['planet'];
            }
            $method = 'पंचाधिकारियों में सर्वाधिक पंचवर्गीय बली वाला ग्रह वर्षेश (पराशरी रीति — Parashara\'s Light अनुरूप)';
        } elseif ($aspecting !== []) {
            $maxBala = max(array_map(static fn($c) => $c['bala'], $aspecting));
            $tied = array_values(array_filter($aspecting, static fn($c) => abs($c['bala'] - $maxBala) < 0.01));
            $allHeen = array_filter($aspecting, static fn($c) => $c['band'] !== 'heen') === [];
            if ($allHeen) {
                $winner = $munthaPlanet;
                $method = 'सब लग्न-द्रष्टा हीन-बली — मुंथेश वर्षाधिप (श्लोक 11)';
            } elseif (count($tied) === 1) {
                $winner = $tied[0]['planet'];
                $method = 'लग्न-द्रष्टाओं में सर्वाधिक पंचवर्गीय बली (श्लोक 10)';
            } else {
                $pick = null;
                if ($tieRule === 'varsha_lagnesh' && $lagneshPlanet !== null) {
                    foreach ($tied as $t) { if ($t['planet'] === $lagneshPlanet) { $pick = $t['planet']; } }
                }
                $winner = $pick ?? $tied[0]['planet'];
                $method = $pick !== null
                    ? 'बल-साम्य — वर्ष-लग्नेश को वरीयता (श्लोक 10)'
                    : 'बल-साम्य — प्रथम अधिकारी (श्लोक 9 क्रम)';
            }
        } else {
            if ($fallback === 'strongest') {
                usort($trail, static fn($a, $b) => $b['bala'] <=> $a['bala']);
                $winner = $trail[0]['planet'] ?? $munthaPlanet;
                $method = 'कोई लग्न को न देखे — वीर्याधिक (सर्वाधिक बली) वर्षेश (श्लोक 11)';
            } else {
                $winner = $munthaPlanet;
                $method = 'कोई लग्न-द्रष्टा नहीं — मुंथेश वर्षाधिप (श्लोक 11)';
            }
        }
        $winner = $winner ?? ($trail[0]['planet'] ?? 'Sun');

        // mark the trail
        foreach ($trail as &$c) {
            if ($c['planet'] === $winner) {
                $c['reason'] = '✓ वर्षेश — ' . $method; $c['selected'] = true;
            } elseif ($methodMode !== 'tajik_lagna_drishti') {
                $c['reason'] = 'पंचवर्गीय बल में पीछे'; $c['selected'] = false;
            } elseif (!$c['aspects']) {
                $c['reason'] = 'लग्न को ताजिक दृष्टि नहीं — अपात्र'; $c['selected'] = false;
            } else {
                $c['reason'] = 'लग्न-द्रष्टा, पर बल में पीछे'; $c['selected'] = false;
            }
        }
        unset($c);

        // ---- band (shloka 37): varsha + natal ----
        $vBala = (float) ($table[$winner]['total'] ?? Varshesha::components($winner, $vPlanets)['total']);
        $vBand = $bandOf($vBala);
        $nBala = Varshesha::components($winner, $natal['planets'] ?? [])['total'];
        $nBand = $bandOf($nBala);
        if ($checkNatal) {
            $band = ($vBand === 'full' && $nBand === 'full') ? 'full'
                : (($vBand === 'heen' && $nBand === 'heen') ? 'heen' : 'madhya');
        } else {
            $band = $vBand;
        }

        // ---- phal + modifiers ----
        $phal = (string) ($rules['phal'][$winner][$band] ?? '');
        $special = $rules['special'] ?? [];
        $modifiers = [];

        // shloka 13 bonus: outside 6/8/12, un-combust, natal-strong.
        $winHouse = (int) ($vPlanets[$winner]['house'] ?? 0);
        $combust = PlanetCondition::combustion($winner, $vPlanets);
        $combustPct = $combust !== null ? (int) $combust['pct'] : 0;
        $sp13 = !in_array($winHouse, [6, 8, 12], true) && $combustPct < 40 && $nBand !== 'heen';
        if ($sp13 && isset($special['sp_13'])) {
            $modifiers[] = ['key' => 'sp_13', 'tone' => 'pos', 'title' => 'श्लोक 13 — पूर्ण उत्तम योग',
                'text' => $special['sp_13']['text']];
        }

        // Tajik yogas involving the varshesh (sp_38 ithasala/israfa, sp_moon18 kamboola).
        $tajikOut = TajikYogaEngine::compute($vp, $tajik, $activeMuddaLord);
        $shubhCount = 0; $ashubhCount = 0; $beneficIthasala = false;
        foreach (($tajikOut['yogas'] ?? []) as $y) {
            if (!in_array($winner, $y['participants'] ?? [], true)) { continue; }
            if (!in_array($y['yoga_key'], ['ithasala', 'israfa'], true)) { continue; }
            $partner = null;
            foreach ($y['participants'] as $pp) { if ($pp !== $winner) { $partner = $pp; } }
            $partner = $partner ?? $winner;
            $pBenefic = PlanetCondition::isBenefic($partner, $vPlanets);
            if ($y['yoga_key'] === 'ithasala' && $pBenefic) { $beneficIthasala = true; }
            $tone = ($y['yoga_key'] === 'ithasala') === $pBenefic ? 'pos' : 'neg';
            if ($tone === 'pos') { $shubhCount++; } else { $ashubhCount++; }
            $modifiers[] = [
                'key' => 'sp_38', 'tone' => $tone,
                'title' => $y['name_hi'] . ($y['subtype'] ? ' (' . $y['subtype'] . ')' : '') . ' — ' . self::hi($partner) . ' (' . ($pBenefic ? 'शुभ' : 'पाप') . ')',
                'text' => $special['sp_38']['text'] ?? '',
            ];
            // Kamboola tag on this record => sp_moon18 (night varshapravesh).
            foreach (($y['tags'] ?? []) as $t) {
                if (str_contains($t['name_hi'] ?? '', 'कम्बूल') && !$isDay && isset($special['sp_moon18'])) {
                    $modifiers[] = ['key' => 'sp_moon18', 'tone' => 'pos', 'title' => 'श्लोक 18 — चन्द्र-कम्बूल (रात्रि-वर्षप्रवेश)',
                        'text' => $special['sp_moon18']['text']];
                }
            }
        }
        // Sun madhya + benefic ithasala (shloka 16 exception) chip.
        if ($winner === 'Sun' && $band === 'madhya' && $beneficIthasala) {
            $modifiers[] = ['key' => 'sp_16', 'tone' => 'pos', 'title' => 'श्लोक 16 — शुभ-इत्थशाल सक्रिय',
                'text' => 'सूर्य मध्यम-बली होने पर भी शुभ ग्रह से मुथशिल — फल शुभ की ओर।'];
        }

        // shloka 37 (band statement) + shloka 44 (closing verdict) always.
        if (isset($special['sp_37'])) {
            $modifiers[] = ['key' => 'sp_37', 'tone' => $band === 'full' ? 'pos' : ($band === 'heen' ? 'neg' : 'info'),
                'title' => 'श्लोक 37 — जन्म व वर्ष दोनों का बल',
                'text' => 'वर्ष-कुंडली: ' . self::BAND_HI[$vBand] . ' (' . round($vBala, 1) . '/80) · जन्म-कुंडली: '
                    . self::BAND_HI[$nBand] . ' (' . round($nBala, 1) . '/80) → संयुक्त बैंड: ' . self::BAND_HI[$band] . '।'];
        }
        $verdict = $band === 'full' ? 'समस्त वर्ष शुभ' : ($band === 'heen' ? 'वर्ष प्रायः अनिष्ट — सावधानी' : 'मध्यम — मिश्रित फल');
        $modifiers[] = ['key' => 'sp_44', 'tone' => $band === 'full' ? 'pos' : ($band === 'heen' ? 'neg' : 'info'),
            'title' => 'श्लोक 44 — निर्णय',
            'text' => $verdict . '. बलाबल + योग (इत्थशाल/कम्बूल/ईसराफ): शुभ-योग ' . $shubhCount . ' · अशुभ-योग ' . $ashubhCount . '।'];

        // method-diff vs the existing header year-lord.
        $headerLord = (string) ($vp['varshesh']['lord'] ?? '');
        $methodDiff = ($headerLord !== '' && $headerLord !== $winner) ? $headerLord : null;

        return [
            'winner' => $winner, 'winner_hi' => self::hi($winner),
            'band' => $band, 'band_hi' => self::BAND_HI[$band],
            'v_band' => $vBand, 'n_band' => $nBand,
            'v_bala' => round($vBala, 2), 'n_bala' => round($nBala, 2),
            'bala20' => round($vBala / 4.0, 2),
            'combust_pct' => $combustPct,
            'retro' => (bool) ($vPlanets[$winner]['retro'] ?? false),
            'house' => $winHouse,
            'method' => $method,
            'trail' => $trail,
            'phal' => $phal,
            'modifiers' => $modifiers,
            'method_diff' => $methodDiff,        // header lord if it differs, else null
            'method_diff_hi' => $methodDiff !== null ? self::hi($methodDiff) : null,
            'display' => (string) ($cfg['varshesh_display'] ?? 'both'),
            'is_day' => $isDay,
        ];
    }

    private static function hi(string $planet): string
    {
        return ['Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
            'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि'][$planet] ?? $planet;
    }
}
