<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Shadbala — planetary strength.
 *
 * SCOPE NOTE (honesty): full classical Shadbala is six-fold, and several of its
 * sub-components (the many Kala-bala parts, Cheshta-bala for the true-motion
 * planets, Yuddha, etc.) require lengthy rules that are easy to get subtly
 * wrong. This implementation computes the FOUR unambiguous, fully reproducible
 * balas and reports them in virupas (1 rupa = 60 virupas):
 *
 *   1. Sthana / Uccha Bala  — exaltation strength (0..60)
 *   2. Dig Bala             — directional strength (0..60)
 *   3. Kendradi Bala        — angular/succedent/cadent (60/30/15)
 *   4. Naisargika Bala      — fixed natural strength
 *
 * The total is therefore a PARTIAL Shadbala (clearly labelled in output).
 * Cheshta-bala and the full Kala-bala breakdown are a documented TODO before
 * the numbers should be compared 1:1 with full-Parashari software totals.
 */
final class Shadbala
{
    /** Deep exaltation longitudes (sidereal degrees) for the seven grahas. */
    private const EXALTATION = [
        'Sun' => 10.0, 'Moon' => 33.0, 'Mars' => 298.0, 'Mercury' => 165.0,
        'Jupiter' => 95.0, 'Venus' => 357.0, 'Saturn' => 200.0,
    ];

    /** Naisargika (natural) bala in virupas. */
    private const NAISARGIKA = [
        'Sun' => 60.0, 'Moon' => 51.43, 'Venus' => 42.86, 'Jupiter' => 34.29,
        'Mercury' => 25.71, 'Mars' => 17.14, 'Saturn' => 8.57,
    ];

    /** Each planet's directionally-strong kendra: 1=Asc,4=Nadir,7=Desc,10=MC. */
    private const DIG_STRONG_HOUSE = [
        'Mercury' => 1, 'Jupiter' => 1,
        'Moon' => 4, 'Venus' => 4,
        'Saturn' => 7,
        'Sun' => 10, 'Mars' => 10,
    ];

    /**
     * @param array<string, float> $siderealLon planet => sidereal longitude
     * @param float $ascLon sidereal ascendant longitude (1st cusp)
     * @param float $mcLon  sidereal MC longitude (10th cusp)
     * @return array<string, array{uccha:float,dig:float,kendra:float,naisargika:float,total_virupa:float,total_rupa:float}>
     */
    public static function compute(array $siderealLon, float $ascLon, float $mcLon): array
    {
        $ascSign = Charts::signIndex($ascLon);

        // Kendra cusp longitudes for Dig bala.
        $cusps = [
            1 => Charts::norm($ascLon),
            4 => Charts::norm($mcLon + 180.0),
            7 => Charts::norm($ascLon + 180.0),
            10 => Charts::norm($mcLon),
        ];

        $out = [];
        foreach (self::NAISARGIKA as $planet => $naisargika) {
            $lon = $siderealLon[$planet] ?? null;
            if ($lon === null) {
                continue;
            }

            // 1. Uccha (exaltation) bala: 0 at debilitation, 60 at exaltation.
            $debil = Charts::norm(self::EXALTATION[$planet] + 180.0);
            $arc = self::foldedArc($lon, $debil); // 0..180 from debilitation
            $uccha = $arc / 3.0;

            // 2. Dig bala: 60 at the strong cusp, 0 at the opposite cusp.
            $strongCusp = $cusps[self::DIG_STRONG_HOUSE[$planet]];
            $powerless = Charts::norm($strongCusp + 180.0);
            $dig = self::foldedArc($lon, $powerless) / 3.0;

            // 3. Kendradi bala by house type from the ascendant (whole-sign).
            $house = Charts::houseFromAsc($lon, $ascSign);
            $kendra = match (true) {
                in_array($house, [1, 4, 7, 10], true) => 60.0,  // kendra
                in_array($house, [2, 5, 8, 11], true) => 30.0,  // panapara
                default => 15.0,                                  // apoklima
            };

            $total = $uccha + $dig + $kendra + $naisargika;
            $out[$planet] = [
                'uccha' => round($uccha, 2),
                'dig' => round($dig, 2),
                'kendra' => $kendra,
                'naisargika' => $naisargika,
                'total_virupa' => round($total, 2),
                'total_rupa' => round($total / 60.0, 2),
            ];
        }
        return $out;
    }

    /** Shorter-arc distance folded into 0..180 degrees. */
    private static function foldedArc(float $a, float $b): float
    {
        $d = abs(Charts::norm($a) - Charts::norm($b));
        return $d > 180.0 ? 360.0 - $d : $d;
    }
}
