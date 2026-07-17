<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\VimshottariDasha;
use AutoBusiness\Astro\Time\JulianDay;

/**
 * Prediction-confidence meter for the D1 panels.
 *
 * Combines the strengths the engine already computes — Shadbala (vs the
 * Parashari minimum), the planet's own Ashtakavarga bindus in its sign, the
 * SAV of the occupied house-sign, and the Navamsha (D9) standing
 * (vargottama / dignity) — into one transparent additive score per planet,
 * so every prediction can carry a "प्रबल / मध्यम / क्षीण फल" verdict with the
 * reasons spelled out. Weights:
 *
 *   Shadbala ratio ≥1.25 → +2 · ≥1.0 → +1 · <0.85 → −1
 *   BAV bindus (own sign) ≥5 → +1 · ≤2 → −1
 *   SAV of occupied sign ≥30 → +1 · ≤23 → −1
 *   Vargottama (D1 sign = D9 sign) → +2; else D9 उच्च/स्वगृही → +1, D9 नीच → −1
 *   D1 उच्च/स्वगृही → +1 · D1 नीच → −1
 *
 * Tier: score ≥3 = प्रबल (pos) · 1–2 = मध्यम (mix) · ≤0 = क्षीण (neg).
 *
 * Also answers "कब?" — each planet's own Vimshottari mahadasha window and its
 * upcoming antardasha windows, so the फल-काल line can sit under each phal.
 * Pure computation over the already-built chart; no DB.
 */
final class StrengthMeter
{
    /** Exaltation sign index per planet. */
    private const EXALT = [
        'Sun' => 0, 'Moon' => 1, 'Mars' => 9, 'Mercury' => 5,
        'Jupiter' => 3, 'Venus' => 11, 'Saturn' => 6, 'Rahu' => 1, 'Ketu' => 7,
    ];
    /** Debilitation sign index per planet. */
    private const DEBIL = [
        'Sun' => 6, 'Moon' => 7, 'Mars' => 3, 'Mercury' => 11,
        'Jupiter' => 9, 'Venus' => 5, 'Saturn' => 0, 'Rahu' => 7, 'Ketu' => 1,
    ];

    /**
     * @param array<string,mixed> $chart CalculationEngine::computeChart output
     * @param float $tz display timezone for the dasha dates
     * @param float|null $nowJd "now" for running/upcoming classification
     * @return array{planets: array<string,array<string,mixed>>}
     */
    public static function compute(array $chart, float $tz = 0.0, ?float $nowJd = null): array
    {
        $planets = $chart['planets'] ?? [];
        $shad = $chart['shadbala'] ?? [];
        $bav = $chart['ashtakavarga']['bav'] ?? [];
        $sav = $chart['ashtakavarga']['sav'] ?? [];
        $mds = $chart['dasha']['mahadashas'] ?? [];
        if ($nowJd === null) {
            $nowJd = JulianDay::fromGregorian(
                (int) date('Y'), (int) date('n'), (int) date('j'),
                (int) date('G'), (int) date('i'), 0.0, $tz
            );
        }

        $out = [];
        foreach ($planets as $pl => $p) {
            $sign = (int) ($p['sign_index'] ?? 0);
            $score = 0;
            $reasons = [];

            // Shadbala vs the Parashari minimum (nodes have no Shadbala).
            $ratio = isset($shad[$pl]['ratio']) ? (float) $shad[$pl]['ratio'] : null;
            if ($ratio !== null) {
                if ($ratio >= 1.25) { $score += 2; $reasons[] = 'शडबल ' . number_format($ratio, 2) . '× आवश्यक (+2)'; }
                elseif ($ratio >= 1.0) { $score += 1; $reasons[] = 'शडबल पर्याप्त ' . number_format($ratio, 2) . '× (+1)'; }
                elseif ($ratio < 0.85) { $score -= 1; $reasons[] = 'शडबल न्यून ' . number_format($ratio, 2) . '× (−1)'; }
            }

            // Own Ashtakavarga bindus in the occupied sign (7 classical only).
            $bindu = isset($bav[$pl][$sign]) ? (int) $bav[$pl][$sign] : null;
            if ($bindu !== null) {
                if ($bindu >= 5) { $score += 1; $reasons[] = 'स्व-अष्टकवर्ग ' . $bindu . ' बिन्दु (+1)'; }
                elseif ($bindu <= 2) { $score -= 1; $reasons[] = 'स्व-अष्टकवर्ग केवल ' . $bindu . ' बिन्दु (−1)'; }
            }

            // SAV of the occupied sign.
            $savv = isset($sav[$sign]) ? (int) $sav[$sign] : null;
            if ($savv !== null) {
                if ($savv >= 30) { $score += 1; $reasons[] = 'राशि SAV ' . $savv . ' (+1)'; }
                elseif ($savv <= 23) { $score -= 1; $reasons[] = 'राशि SAV केवल ' . $savv . ' (−1)'; }
            }

            // D1 dignity.
            if ((self::EXALT[$pl] ?? -1) === $sign) { $score += 1; $reasons[] = 'उच्च राशि (+1)'; }
            elseif ((self::DEBIL[$pl] ?? -1) === $sign) { $score -= 1; $reasons[] = 'नीच राशि (−1)'; }
            elseif (Charts::signLord($sign) === $pl) { $score += 1; $reasons[] = 'स्वगृही (+1)'; }

            // Navamsha (D9) standing.
            $navName = (string) ($p['navamsa_sign'] ?? '');
            $nav = array_search($navName, Charts::SIGNS, true);
            $d9 = null;
            if ($nav !== false) {
                $nav = (int) $nav;
                if ($nav === $sign) { $score += 2; $reasons[] = 'वर्गोत्तम — D1 व D9 एक ही राशि (+2)'; $d9 = 'वर्गोत्तम'; }
                elseif ((self::EXALT[$pl] ?? -1) === $nav) { $score += 1; $reasons[] = 'नवांश में उच्च (+1)'; $d9 = 'D9 उच्च'; }
                elseif (Charts::signLord($nav) === $pl) { $score += 1; $reasons[] = 'नवांश में स्वगृही (+1)'; $d9 = 'D9 स्वगृही'; }
                elseif ((self::DEBIL[$pl] ?? -1) === $nav) { $score -= 1; $reasons[] = 'नवांश में नीच (−1)'; $d9 = 'D9 नीच'; }
            }

            [$tier, $word] = $score >= 3 ? ['pos', 'प्रबल फल'] : ($score >= 1 ? ['mix', 'मध्यम फल'] : ['neg', 'क्षीण फल']);

            $out[$pl] = [
                'score' => $score,
                'tier' => $tier,        // pos | mix | neg (matches gc-chip classes)
                'word' => $word,
                'reasons' => $reasons,
                'ratio' => $ratio,
                'bindu' => $bindu,
                'sav' => $savv,
                'd9' => $d9,
                'timing' => self::timing($pl, $mds, $nowJd, $tz),
            ];
        }
        return ['planets' => $out];
    }

    /**
     * फल-काल for one planet: its own mahadasha window (with past/running/future
     * status) + up to two current/upcoming antardasha windows of this planet.
     *
     * @param list<array{lord:string,start_jd:float,end_jd:float}> $mds
     * @return array{maha: array<string,mixed>|null, antar: list<array<string,mixed>>}
     */
    private static function timing(string $pl, array $mds, float $nowJd, float $tz): array
    {
        $maha = null;
        foreach ($mds as $md) {
            if (($md['lord'] ?? '') === $pl) {
                $s = (float) $md['start_jd'];
                $e = (float) $md['end_jd'];
                $maha = [
                    'from' => JulianDay::toDmy($s, $tz),
                    'to' => JulianDay::toDmy($e, $tz),
                    'status' => $nowJd < $s ? 'future' : ($nowJd < $e ? 'running' : 'past'),
                ];
                break;
            }
        }

        $antar = [];
        foreach ($mds as $md) {
            if ((float) $md['end_jd'] < $nowJd) { continue; }   // finished mahadashas
            foreach (VimshottariDasha::antardashas($md) as $ad) {
                if (($ad['lord'] ?? '') !== $pl || (float) $ad['end_jd'] < $nowJd) { continue; }
                $antar[] = [
                    'maha' => (string) $md['lord'],
                    'from' => JulianDay::toDmy((float) $ad['start_jd'], $tz),
                    'to' => JulianDay::toDmy((float) $ad['end_jd'], $tz),
                    'running' => $nowJd >= (float) $ad['start_jd'],
                ];
                if (count($antar) >= 2) { break 2; }
            }
        }
        return ['maha' => $maha, 'antar' => $antar];
    }
}
