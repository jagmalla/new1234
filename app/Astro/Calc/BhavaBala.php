<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Bhava Bala — strength of the twelve houses, in the component model used by
 * Parashara's Light. Each component is in virupas; their sum is the Bhava Bala.
 *
 *   • From Lord  (Bhavadhipati Bala) — the bhava lord's Shadbala total.
 *   • Dig Bala   (Bhava Digbala)     — directional strength of the bhava.
 *   • Drishti    (Bhava Drishti Bala)— net weighted Sphuta Drishti on the bhava,
 *                                       computed at the whole-sign cusp using PL's
 *                                       drishti curve (reverse-engineered from PL's
 *                                       "Aspects on Bhavas" table). Each planet's
 *                                       aspect is weighted: a benefic counts full
 *                                       when its Ishta exceeds its Kashta, else a
 *                                       quarter; a malefic counts a quarter. The
 *                                       nodes are excluded (no Shadbala).
 *   • Planets in (occupants)         — sign(benefics − malefics in the bhava) × 60.
 *   • Day-Night  (Bhava Kaala)       — +15 to day-strong bhavas by day.
 *
 * From Lord, Planets-in and Day-Night match Parashara's Light; the Drishti model
 * reproduces PL's per-planet aspect matrix and net Drishti row for the reference
 * chart. Dig Bala uses the standard BPHS directional figure.
 */
final class BhavaBala
{
    private const CLASSICAL = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
    private const BENEFIC   = ['Jupiter', 'Venus', 'Mercury'];   // natural benefics (Moon by paksha)
    private const MALEFIC   = ['Sun', 'Mars', 'Saturn'];         // natural malefics
    // Sirshodaya (head-rising) signs are strong by day; the rest by night.
    private const SIRSHODAYA = [2, 4, 5, 6, 7, 10]; // Gemini, Leo, Virgo, Libra, Scorpio, Aquarius

    // PL's Sphuta-drishti base curve (virupa vs aspect-angle B), reverse-engineered
    // from PL's per-planet "Aspects on Bhavas" table; linear-interpolated.
    private const CURVE = [
        [0, 0], [30, 4], [44, 11], [54, 17], [60, 24], [74, 38], [84, 43], [90, 40],
        [104, 33], [114, 27], [120, 20], [134, 6], [144, 4], [150, 18], [164, 46],
        [174, 58], [180, 56], [194, 48], [204, 43], [210, 40], [224, 33], [234, 28],
        [240, 25], [254, 18], [264, 13], [270, 10], [284, 3], [294, 0], [360, 0],
    ];
    // Special full-aspect peaks (aspect-angle B) and the per-planet boost added to
    // the base curve when the cusp falls within ±30° of a peak.
    private const SPECIAL = [
        'Mars'    => [['peaks' => [90, 210], 'boost' => 13]],
        'Jupiter' => [['peaks' => [120, 240], 'boost' => 27]],
        'Saturn'  => [['peaks' => [60, 270], 'boost' => 37]],
    ];

    /** PL base drishti curve at aspect-angle B (0–360). */
    private static function curve(float $B): float
    {
        $B = Charts::norm($B);
        $a = self::CURVE;
        for ($i = 0, $n = count($a) - 1; $i < $n; $i++) {
            if ($B >= $a[$i][0] && $B <= $a[$i + 1][0]) {
                $span = $a[$i + 1][0] - $a[$i][0];
                $t = $span > 0 ? ($B - $a[$i][0]) / $span : 0.0;
                return $a[$i][1] + $t * ($a[$i + 1][1] - $a[$i][1]);
            }
        }
        return 0.0;
    }

    /** Sphuta drishti of a planet on a cusp, incl. special full aspects (PL model). */
    private static function drishti(string $planet, float $planetLon, float $cuspLon): float
    {
        $B = Charts::norm($cuspLon - $planetLon);
        $v = self::curve($B);
        foreach (self::SPECIAL[$planet] ?? [] as $spec) {
            foreach ($spec['peaks'] as $peak) {
                $off = abs($B - $peak);
                if ($off > 180.0) {
                    $off = 360.0 - $off;
                }
                if ($off <= 30.0) {
                    $v += $spec['boost'];
                    break;
                }
            }
        }
        return $v;
    }

    /**
     * @param array<string, array<string,mixed>> $shadbala     planet => shadbala row
     * @param array<string, float>                $siderealLon  planet => sidereal lon
     * @param array<int, list<string>>            $occupants    house => planet names (incl. nodes)
     * @return array<int, array<string,mixed>>
     */
    public static function compute(float $ascLon, int $ascSign, array $shadbala, array $siderealLon, array $occupants = [], bool $isDay = true): array
    {
        // Moon is benefic when waxing (elongation 0–180), malefic when waning.
        $elong = Charts::norm(($siderealLon['Moon'] ?? 0.0) - ($siderealLon['Sun'] ?? 0.0));
        $moonBenefic = $elong <= 180.0;

        // Per-planet Drig-Bala weight: benefic → full if Ishta>Kashta else ¼;
        // malefic → −¼.
        $weight = [];
        foreach (self::CLASSICAL as $pl) {
            $benefic = in_array($pl, self::BENEFIC, true) || ($pl === 'Moon' && $moonBenefic);
            if ($benefic) {
                $ishta = (float) ($shadbala[$pl]['ishta'] ?? 0.0);
                $kashta = (float) ($shadbala[$pl]['kashta'] ?? 0.0);
                $weight[$pl] = ($ishta > $kashta) ? 1.0 : 0.25;
            } else {
                $weight[$pl] = -0.25;
            }
        }

        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            $sign = (($ascSign + $h - 1) % 12 + 12) % 12;
            $lord = Charts::signLord($sign);
            $adhipati = (float) ($shadbala[$lord]['total_virupa'] ?? 0.0);

            // Day/night (Bhava Kaala) Bala.
            $dayStrong = in_array($sign, self::SIRSHODAYA, true);
            $dayNight = ($dayStrong === $isDay) ? 15.0 : 0.0;

            // Dig Bala: bhava strongest at the Lagna degree, falling to the 7th.
            $cuspMadhya = Charts::norm($ascLon + ($h - 1) * 30.0);
            $sep = abs($cuspMadhya - $ascLon);
            if ($sep > 180.0) {
                $sep = 360.0 - $sep;
            }
            $digbala = 60.0 - $sep / 3.0;

            // Drishti: weighted Sphuta drishti at the WHOLE-SIGN cusp (0° of the sign).
            $cusp = $sign * 30.0;
            $drishti = 0.0;
            foreach (self::CLASSICAL as $pl) {
                if (!isset($siderealLon[$pl])) {
                    continue;
                }
                $drishti += $weight[$pl] * self::drishti($pl, $siderealLon[$pl], $cusp);
            }

            // Planets-in: net benefic−malefic occupants → sign × 60 (Moon & nodes ignored).
            $occ = $occupants[$h] ?? [];
            $net = 0;
            foreach ($occ as $pl) {
                if (in_array($pl, self::BENEFIC, true)) {
                    $net++;
                } elseif (in_array($pl, self::MALEFIC, true)) {
                    $net--;
                }
            }
            $planetsIn = $net > 0 ? 60.0 : ($net < 0 ? -60.0 : 0.0);

            $total = $adhipati + $digbala + $drishti + $planetsIn + $dayNight;
            $out[$h] = [
                'house' => $h,
                'sign' => $sign,
                'lord' => $lord,
                'adhipati' => round($adhipati, 1),
                'digbala' => round($digbala, 1),
                'drishti' => round($drishti, 1),
                'planets_in' => round($planetsIn, 1),
                'day_night' => round($dayNight, 1),
                'total_virupa' => round($total, 1),
                'rupa' => round($total / 60.0, 2),
            ];
        }
        return $out;
    }
}
