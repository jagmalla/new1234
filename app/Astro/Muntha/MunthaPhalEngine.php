<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muntha;

use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;
use AutoBusiness\Astro\Calc\Varshesha;

/**
 * Muntha phal engine — Tajik Neelkanthi, Muthaha-phala adhyaya shloka 1–36.
 *
 * The Muntha longitude itself is NOT recomputed here (it stays the classical
 * natal-lagna-sign + completed-years point produced by {@see \AutoBusiness\Astro\Calc\Varshaphal});
 * this engine only reads it and produces the reading in the book's order:
 *   A. भाव — house of the Muntha from the Varsha lagna → bhava phal + band.
 *   B. ग्रह — yuti / rashi / Tajik-drishti of each planet on the Muntha, with the
 *      conditional clauses (Mars softened by Saturn, Saturn lifted by Jupiter,
 *      Moon/Budh-Shukra pain on a paap aspect) actually evaluated.
 *   C. राहु — Muntha in Rahu's mukh / prishtha / puchchha zone.
 *   D. विशेष (shloka 3, 17–23, 33–36) — the janma-varsha bridge + Munthesh
 *      warnings (shloka 34/35 maran-yoga) + the shloka 36 timing line, each
 *      shown only when its condition is detected.
 *   E. verdict chip from the band + graha rows + fired specials.
 *
 * Reuses the Tajik sphuta-drishti service, PlanetCondition (benefic/combustion)
 * and the PL-matched Panchavargeeya ({@see Varshesha::components}).
 */
final class MunthaPhalEngine
{
    private const SEVEN = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
    private const MALEFIC = ['Sun', 'Mars', 'Saturn', 'Rahu', 'Ketu'];
    private const KSHUT = [1, 4, 7, 10];   // क्षुत (enemy) drishti houses
    private const SNEHA = [3, 5, 9, 11];   // स्नेह (friendly) drishti houses

    /**
     * @param array<string,mixed> $vp     Varshaphal::compute output
     * @param array<string,mixed> $natal  natal CalculationEngine::computeChart
     * @param array<string,mixed> $tajik  TajikRepository::load (dhruvanka)
     * @param array<string,mixed> $rules  MunthaRepository::load
     * @return array<string,mixed>
     */
    public static function compute(array $vp, array $natal, array $tajik, array $rules): array
    {
        $cfg = $rules['config'] ?? [];
        $vc = $vp['varsha_chart'] ?? [];
        $vPlanets = $vc['planets'] ?? [];
        $vLagnaSign = (int) ($vc['ascendant']['sign_index'] ?? 0);
        $natalAscSign = (int) ($natal['ascendant']['sign_index'] ?? 0);
        $natalDeg = (float) ($natal['ascendant']['deg_in_sign'] ?? 0.0);

        // Muntha point (unchanged calc): natal-lagna sign advanced by completed
        // years, carrying the natal lagna degree.
        $age = (int) ($vp['age_completed'] ?? 0);
        $munthaSign = (($natalAscSign + $age) % 12 + 12) % 12;
        $munthaDeg = $natalDeg;
        $munthesh = Charts::signLord($munthaSign);

        // ---- aspect map on the Muntha point ----
        $benefic = static fn(string $p): bool => isset($vPlanets[$p]) && PlanetCondition::isBenefic($p, $vPlanets);
        $signOf = static fn(string $p): int => (int) ($vPlanets[$p]['sign_index'] ?? 0);

        $aspect = [];   // planet => ['type'=>sneha|vair|none,'house'=>rel,'kshut'=>bool,'sneha'=>bool,'yuti'=>bool]
        foreach (['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'] as $p) {
            if (!isset($vPlanets[$p])) { continue; }
            // Tajik drishti is whole-sign: house of the Muntha counted from the
            // planet decides स्नेह (3/5/9/11) vs क्षुत (1/4/7/10) vs none.
            $rel = ((($munthaSign - $signOf($p)) % 12) + 12) % 12 + 1;
            $isSneha = in_array($rel, self::SNEHA, true);
            $isVair = in_array($rel, self::KSHUT, true);
            $aspect[$p] = [
                'type' => $isSneha ? 'sneha' : ($isVair ? 'vair' : 'none'), 'house' => $rel,
                'kshut' => $isVair, 'sneha' => $isSneha,
                'yuti' => $signOf($p) === $munthaSign,
            ];
        }
        // Does a benefic / malefic favourably / harshly touch the Muntha?
        $shubhTouch = static function () use ($aspect, $benefic): bool {
            foreach ($aspect as $p => $a) {
                if (in_array($p, ['Rahu', 'Ketu'], true)) { continue; }
                if (($a['yuti'] || $a['sneha']) && $benefic($p)) { return true; }
            }
            return false;
        };
        $paapTouch = static function () use ($aspect, $benefic): bool {
            foreach ($aspect as $p => $a) {
                if ($p === 'Ketu') { continue; }
                if (($a['yuti'] || $a['kshut']) && ($p === 'Rahu' || !$benefic($p))) { return true; }
            }
            return false;
        };
        $anyKshutMalefic = static function () use ($aspect, $benefic): ?string {
            foreach ($aspect as $p => $a) {
                if ($a['kshut'] && ($p === 'Rahu' || !$benefic($p))) { return $p; }
            }
            return null;
        };

        // ---- A. BHAVA ----
        $house = (($munthaSign - $vLagnaSign) % 12 + 12) % 12 + 1;
        $bhava = $rules['bhava'][$house] ?? ['band' => '', 'text' => ''];
        $bandScore = str_contains($bhava['band'], 'अति') ? 2 : (str_contains($bhava['band'], 'अशुभ') ? -2 : 1);

        // ---- B. GRAHA / RASHI ----
        $grahaCards = [];
        $grahaScore = 0.0;
        $ownSign = static fn(string $p, int $s): bool => Charts::signLord($s) === $p;
        // Mars softened when Muntha in Saturn's sign OR Saturn aspects Muntha.
        $saturnTouch = ($aspect['Saturn']['type'] ?? 'none') !== 'none' || ($aspect['Saturn']['yuti'] ?? false)
            || in_array($munthaSign, [9, 10], true); // Cap/Aqu
        $jupiterTouch = ($aspect['Jupiter']['type'] ?? 'none') !== 'none' || ($aspect['Jupiter']['yuti'] ?? false);
        foreach (self::SEVEN as $p) {
            $key = in_array($p, ['Mercury', 'Venus'], true) ? 'Mercury_Venus' : $p;
            if (!isset($rules['graha'][$key])) { continue; }
            $a = $aspect[$p] ?? null;
            if ($a === null) { continue; }
            $reasons = [];
            if ($a['yuti']) { $reasons[] = 'युति'; }
            if ($ownSign($p, $munthaSign)) { $reasons[] = 'मुंथा इसकी राशि में'; }
            // A drishti reason only for a real aspect from another sign (house 1
            // is the yuti/ekarksha itself, already stated above).
            if ($a['type'] !== 'none' && $a['house'] !== 1) { $reasons[] = ($a['kshut'] ? 'क्षुत' : 'स्नेह') . ' दृष्टि (' . $a['house'] . 'वें से)'; }
            if ($reasons === [] && $key !== 'Mercury_Venus') { continue; }
            // For Mercury_Venus only fire once even if both touch.
            if (isset($grahaCards[$key])) { continue; }
            if ($reasons === []) { continue; }

            $g = $rules['graha'][$key];
            $clause = null;
            if ($p === 'Mars' && $saturnTouch) { $clause = 'शनि-गृह/दृष्ट — पित्त-रुधिर फल घटता; शनि-मंगल का फल समान।'; }
            if ($p === 'Saturn' && $jupiterTouch) { $clause = 'गुरु-दृष्ट — शनि का अनिष्ट घटकर उत्तम/शुभ फल।'; }
            if (in_array($p, ['Moon', 'Mercury', 'Venus'], true) && $anyKshutMalefic() !== null) {
                $clause = 'पाप-दृष्टि साथ — अति-दुःख/कष्ट का योग।';
            }
            $nature = $g['nature'];
            // clause can flip the effective nature
            if ($p === 'Saturn' && $clause !== null) { $nature = 'शुभ'; }
            $grahaScore += $nature === 'शुभ' ? 0.5 : ($nature === 'अशुभ' ? -0.5 : 0.0);
            $grahaCards[$key] = [
                'planet' => $p, 'planet_hi' => self::hi($p),
                'nature' => $nature, 'reasons' => implode(' · ', $reasons),
                'text' => $g['text'], 'clause' => $clause,
            ];
        }
        $grahaCards = array_values($grahaCards);

        // ---- C. RAHU zone ----
        $rahu = null;
        if (isset($vPlanets['Rahu'])) {
            $rSign = $signOf('Rahu'); $rDeg = (float) ($vPlanets['Rahu']['deg_in_sign'] ?? 0.0);
            $mode = (string) ($cfg['muntha_rahu_mode'] ?? 'bhogya');
            $zone = null;
            if ($munthaSign === $rSign) {
                if ($mode === 'deg15') {
                    $zone = ($munthaDeg >= $rDeg - 15.0 && $munthaDeg <= $rDeg) ? 'mukh' : (($munthaDeg > $rDeg && $munthaDeg <= $rDeg + 15.0) ? 'prishtha' : null);
                } else {
                    $zone = $munthaDeg <= $rDeg ? 'mukh' : 'prishtha';   // bhogya (Rahu retrograde)
                }
            } elseif ($munthaSign === ($rSign + 6) % 12) {
                $zone = 'puchchha';
            }
            if ($zone !== null) {
                $rkey = 'Rahu_' . $zone;
                $g = $rules['graha'][$rkey] ?? null;
                if ($g !== null) {
                    $verdict = $zone === 'prishtha' ? (string) ($cfg['muntha_rahu_prishtha'] ?? 'ashubh') : null;
                    $rahu = [
                        'zone' => $zone, 'zone_hi' => ['mukh' => 'मुख', 'prishtha' => 'पृष्ठ', 'puchchha' => 'पुच्छ'][$zone],
                        'nature' => $g['nature'], 'text' => $g['text'],
                        'pathabheda' => $zone === 'prishtha',
                        'verdict' => $verdict,
                        'bonus' => $zone === 'mukh' && (($aspect['Venus']['type'] ?? 'none') !== 'none' || ($aspect['Jupiter']['type'] ?? 'none') !== 'none' || ($aspect['Venus']['yuti'] ?? false) || ($aspect['Jupiter']['yuti'] ?? false)),
                    ];
                    $grahaScore += $zone === 'mukh' ? 0.5 : -0.5;
                }
            }
        }

        // ---- Munthesh facts (for D) ----
        $muntheshSign = $signOf($munthesh);
        $muntheshHouse = (($muntheshSign - $vLagnaSign) % 12 + 12) % 12 + 1;
        $muntheshCombust = PlanetCondition::combustion($munthesh, $vPlanets);
        $muntheshCombustPct = $muntheshCombust !== null ? (int) $muntheshCombust['pct'] : 0;
        $muntheshRetro = (bool) ($vPlanets[$munthesh]['retro'] ?? false);
        $eighthLord = Charts::signLord(($vLagnaSign + 7) % 12);
        $muntheshPanch = Varshesha::components($munthesh, $vPlanets)['total'];
        $muntheshBand = $muntheshPanch >= 45 ? 'पूर्ण' : ($muntheshPanch >= 30 ? 'मध्यम' : 'हीन');

        // Varshesh strength (015 link) — for shloka 21/23 suppression.
        $varsheshLord = (string) ($vp['varshesh']['lord'] ?? '');
        $varsheshFull = $varsheshLord !== '' && Varshesha::components($varsheshLord, $vPlanets)['total'] >= 45;

        // ---- D. SPECIAL rules (fired only) ----
        $sp = $rules['special'] ?? [];
        $mods = [];
        $specScore = 0.0;
        $add = static function (string $key, string $tone, string $extra = '') use (&$mods, $sp): void {
            if (!isset($sp[$key])) { return; }
            $mods[] = ['key' => $key, 'tone' => $tone, 'situation' => $sp[$key]['situation'],
                'text' => $sp[$key]['text'] . ($extra !== '' ? ' — ' . $extra : '')];
        };
        $janmaBridge = ($cfg['muntha_janma_bridge'] ?? '1') === '1';

        // m_sh3: Munthesh paap OR a kshut malefic aspects Muntha.
        $km = $anyKshutMalefic();
        if (!$benefic($munthesh) || $km !== null) {
            $add('m_sh3', 'neg', $km !== null ? self::hi($km) . ' की क्षुत-दृष्टि' : self::hi($munthesh) . ' (मुंथेश) पाप');
            $specScore -= 0.5;
        } elseif ($shubhTouch()) {
            $add('m_sh3', 'pos');
            $specScore += 0.5;
        }
        // m_sh17: a malefic's kshut drishti on the Muntha (bhava).
        if ($km !== null) { $add('m_sh17', 'neg', self::hi($km)); $specScore -= 1; }
        // m_sh18: Muntha shubh-touch / Munthesh strong -> poshit; paap-touch / weak -> nasht.
        if ($shubhTouch() || $muntheshBand !== 'हीन') { $add('m_sh18', 'pos'); $specScore += 1; }
        elseif ($paapTouch()) { $add('m_sh18', 'neg'); $specScore -= 1; }
        // m_sh19 (janma bridge): Muntha house from NATAL lagna in {7,12,6,8,4} + paap, benefic cancels.
        if ($janmaBridge) {
            $hFromNatal = (($munthaSign - $natalAscSign) % 12 + 12) % 12 + 1;
            if (in_array($hFromNatal, [7, 12, 6, 8, 4], true)) {
                if ($paapTouch() && !$shubhTouch()) { $add('m_sh19', 'neg', 'जन्म-लग्न से ' . $hFromNatal . 'वें भाव की मुंथा'); $specScore -= 1; }
                elseif ($shubhTouch()) { $add('m_sh19', 'pos', 'शुभ-दृष्ट — नाश नहीं'); }
            }
            // m_sh20: natal-house of Muntha sign + varsha both afflicted / both shubh.
            $natalHouseOfMuntha = (($munthaSign - $natalAscSign) % 12 + 12) % 12 + 1;
            if ($paapTouch() && in_array($natalHouseOfMuntha, [4, 6, 7, 8, 12], true)) { $add('m_sh20', 'neg', 'भाव सर्वथा नष्ट'); $specScore -= 1; }
            elseif ($shubhTouch() && in_array($natalHouseOfMuntha, [1, 2, 3, 5, 9, 10, 11], true)) { $add('m_sh20', 'pos', 'भाव विशेष वर्धित'); $specScore += 1; }
            // m_sh22: generic natal-lagna example.
            if ($shubhTouch()) { $add('m_sh22', 'pos', 'जन्म-लग्न से ' . $hFromNatal . 'वें भाव का लाभ'); }
            elseif ($paapTouch()) { $add('m_sh22', 'neg', 'जन्म-लग्न से ' . $hFromNatal . 'वें भाव में भय/कष्ट'); }
            // m_sh23: Munthesh shubh -> vardhit, paap -> nasht; SUPPRESSED when varshesh full.
            if ($varsheshFull) { $add('m_sh23', 'pos', 'वर्षेश बलवान (पूर्ण) — मुंथा-कृत अनिष्ट नहीं'); }
            elseif (!$benefic($munthesh) || $km !== null) { $add('m_sh23', 'neg'); $specScore -= 0.5; }
            // m_sh21: varshesh in an anishta house + kroor on Muntha.
            $varsheshHouse = isset($vPlanets[$varsheshLord]) ? (($signOf($varsheshLord) - $vLagnaSign) % 12 + 12) % 12 + 1 : 0;
            if (in_array($varsheshHouse, [4, 6, 8, 12], true) && $km !== null && !$shubhTouch()) { $add('m_sh21', 'neg'); $specScore -= 0.5; }
        }
        // m_sh33: planets whose natal vs varsha panchavargiya band differs.
        $mSh33 = [];
        foreach (self::SEVEN as $p) {
            $vB = Varshesha::components($p, $vPlanets)['total'];
            $nB = Varshesha::components($p, $natal['planets'] ?? [])['total'];
            $vStrong = $vB >= 45; $nStrong = $nB >= 45;
            if ($nStrong && !$vStrong) { $mSh33[] = self::hi($p) . ': वर्षान्त में अशुभ'; }
            elseif (!$nStrong && $vStrong) { $mSh33[] = self::hi($p) . ': वर्षारम्भ में अशुभ, बाद में शुभ'; }
        }
        if ($mSh33 !== [] && isset($sp['m_sh33'])) {
            $mods[] = ['key' => 'm_sh33', 'tone' => 'info', 'situation' => $sp['m_sh33']['situation'],
                'text' => $sp['m_sh33']['text'] . ' — ' . implode('; ', array_slice($mSh33, 0, 4)) . '।'];
        }
        // m_sh34 + m_sh35 (Munthesh warnings, maran-yoga).
        $sh34 = in_array($muntheshHouse, [6, 8, 12, 4], true) || $muntheshCombustPct >= 40 || $muntheshRetro
            || (($aspect[$munthesh]['kshut'] ?? false));
        // sh35 needs a DISTINCT 8th-lord planet conjoining / kshut-aspecting the
        // Munthesh — when the Munthesh itself rules the 8th, that is not a
        // "conjunction with the 8th lord" and must not trigger maran-yoga.
        $eighthDistinct = $eighthLord !== $munthesh;
        $sh35 = $eighthDistinct && (($signOf($munthesh) === $signOf($eighthLord)) || ($aspect[$eighthLord]['kshut'] ?? false));
        if ($sh34) {
            $why = [];
            if (in_array($muntheshHouse, [6, 8, 12, 4], true)) { $why[] = 'मुंथेश ' . $muntheshHouse . 'वें भाव में'; }
            if ($muntheshCombustPct >= 40) { $why[] = 'अस्त ' . $muntheshCombustPct . '%'; }
            if ($muntheshRetro) { $why[] = 'वक्री'; }
            if (($aspect[$munthesh]['kshut'] ?? false)) { $why[] = 'क्रूर-राशि से क्षुत'; }
            $add('m_sh34', 'neg', implode(', ', $why));
            $specScore -= 1;
        }
        if ($sh35) {
            $add('m_sh35', 'neg', ($signOf($munthesh) === $signOf($eighthLord)) ? 'मुंथेश-अष्टमेश (' . self::hi($eighthLord) . ') युति' : 'अष्टमेश की क्षुत-दृष्टि');
            $specScore -= 1;
        }
        $maranYoga = $sh34 && $sh35;

        // m_sh36 timing.
        $muntheshShubh = $shubhTouch() || $benefic($munthesh);
        if ($muntheshShubh && $shubhTouch()) { $add('m_sh36', 'pos', 'वर्ष का पूर्व-भाग शुभ'); }
        elseif ($paapTouch()) { $add('m_sh36', 'neg', 'वर्ष का अन्त अशुभ'); }

        // ---- E. verdict ----
        $total = $bandScore + $grahaScore + $specScore - ($maranYoga ? 3 : 0);
        $verdict = $maranYoga ? 'गंभीर चेतावनी' : ($total >= 1.5 ? 'शुभ' : ($total <= -1.5 ? 'अशुभ' : 'मिश्रित'));
        $vTone = $maranYoga ? 'neg' : ($total >= 1.5 ? 'pos' : ($total <= -1.5 ? 'neg' : 'info'));

        return [
            'muntha_sign' => $munthaSign, 'muntha_sign_hi' => self::rashiHi($munthaSign),
            'muntha_deg' => self::dms($munthaDeg),
            'muntha_house' => $house, 'band' => $bhava['band'],
            'munthesh' => $munthesh, 'munthesh_hi' => self::hi($munthesh),
            'munthesh_house' => $muntheshHouse, 'munthesh_band' => $muntheshBand,
            'munthesh_combust' => $muntheshCombustPct, 'munthesh_retro' => $muntheshRetro,
            'bhava' => $bhava,
            'graha' => $grahaCards,
            'rahu' => $rahu,
            'modifiers' => $mods,
            'maran_yoga' => $maranYoga,
            'verdict' => $verdict, 'verdict_tone' => $vTone,
            'rahu_prishtha_note' => (string) ($cfg['muntha_rahu_prishtha'] ?? 'ashubh'),
        ];
    }

    private static function hi(string $p): string
    {
        return ['Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
            'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु'][$p] ?? $p;
    }

    private static function rashiHi(int $s): string
    {
        return ['मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या', 'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुंभ', 'मीन'][$s] ?? '';
    }

    private static function dms(float $deg): string
    {
        $d = (int) floor($deg);
        $m = (int) round(($deg - $d) * 60.0);
        if ($m === 60) { $d++; $m = 0; }
        return $d . '°' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . "'";
    }
}
