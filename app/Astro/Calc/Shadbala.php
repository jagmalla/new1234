<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Shadbala — planetary six-fold strength, following Parashara's Light conventions
 * (Lahiri, true node, equal house).
 *
 * VALIDATED so far (match PL to ±0.01 on the reference chart):
 *   1. Sthana Bala  = Uchcha + Saptavargaja + Ojha-Yugma + Kendradi + Drekkana
 *   2. Dig Bala
 *   5. Naisargika Bala
 *
 * STILL TO COME (computed as null until validated against PL):
 *   3. Kaala Bala   (sunrise/sunset + Vara/Hora/Masa/Abda lords, Paksha,
 *                    Nathonnata, Tribhaga, Ayana)
 *   4. Chesta Bala  (seeghra kendra)
 *   6. Drig Bala    (Sphuta Drishti)
 *
 * Once 3/4/6 land, computeTotals() will produce the Total -> Rupas -> Ratio ->
 * Ishta/Kashta exactly as PL shows them.
 */
final class Shadbala
{
    // --- reference tables -------------------------------------------------
    private const SIGN_LORD = [
        0 => 'Mars', 1 => 'Venus', 2 => 'Mercury', 3 => 'Moon', 4 => 'Sun', 5 => 'Mercury',
        6 => 'Venus', 7 => 'Mars', 8 => 'Jupiter', 9 => 'Saturn', 10 => 'Saturn', 11 => 'Jupiter',
    ];
    private const EXALT = [
        'Sun' => 10.0, 'Moon' => 33.0, 'Mars' => 298.0, 'Mercury' => 165.0,
        'Jupiter' => 95.0, 'Venus' => 357.0, 'Saturn' => 200.0,
    ];
    /** Moolatrikona sign index per planet. */
    private const MT = ['Sun' => 4, 'Moon' => 1, 'Mars' => 0, 'Mercury' => 5, 'Jupiter' => 8, 'Venus' => 6, 'Saturn' => 10];
    /** Moolatrikona degree range within the MT sign (D1 only). */
    private const MT_RANGE = [
        'Sun' => [0, 20], 'Moon' => [3, 30], 'Mars' => [0, 12], 'Mercury' => [16, 20],
        'Jupiter' => [0, 10], 'Venus' => [0, 15], 'Saturn' => [0, 20],
    ];
    /** Own sign indices. */
    private const OWN = [
        'Sun' => [4], 'Moon' => [3], 'Mars' => [0, 7], 'Mercury' => [2, 5],
        'Jupiter' => [8, 11], 'Venus' => [1, 6], 'Saturn' => [9, 10],
    ];
    /** Naisargika (permanent) friendship: F/N/E. */
    private const PERM = [
        'Sun' => ['Moon' => 'F', 'Mars' => 'F', 'Jupiter' => 'F', 'Mercury' => 'N', 'Venus' => 'E', 'Saturn' => 'E'],
        'Moon' => ['Sun' => 'F', 'Mercury' => 'F', 'Mars' => 'N', 'Jupiter' => 'N', 'Venus' => 'N', 'Saturn' => 'N'],
        'Mars' => ['Sun' => 'F', 'Moon' => 'F', 'Jupiter' => 'F', 'Mercury' => 'E', 'Venus' => 'N', 'Saturn' => 'N'],
        'Mercury' => ['Sun' => 'F', 'Venus' => 'F', 'Moon' => 'E', 'Mars' => 'N', 'Jupiter' => 'N', 'Saturn' => 'N'],
        'Jupiter' => ['Sun' => 'F', 'Moon' => 'F', 'Mars' => 'F', 'Mercury' => 'E', 'Venus' => 'E', 'Saturn' => 'N'],
        'Venus' => ['Mercury' => 'F', 'Saturn' => 'F', 'Sun' => 'E', 'Moon' => 'E', 'Mars' => 'N', 'Jupiter' => 'N'],
        'Saturn' => ['Mercury' => 'F', 'Venus' => 'F', 'Sun' => 'E', 'Moon' => 'E', 'Mars' => 'E', 'Jupiter' => 'N'],
    ];
    /** Naisargika (natural) bala — Parashara's Light displayed values. */
    private const NAISARGIKA = [
        'Sun' => 60.00, 'Moon' => 51.42, 'Mars' => 17.16, 'Mercury' => 25.74,
        'Jupiter' => 34.26, 'Venus' => 42.84, 'Saturn' => 8.58,
    ];
    private const MALE = ['Sun', 'Mars', 'Jupiter'];
    private const FEMALE = ['Moon', 'Venus'];
    private const VARGAS = ['D1', 'D2', 'D3', 'D7', 'D9', 'D12', 'D30'];

    /** Each planet's directionally-weak cusp (1=Asc,4=IC,7=Desc,10=MC). */
    private const DIG_WEAK = [
        'Sun' => 4, 'Mars' => 4, 'Jupiter' => 7, 'Mercury' => 7, 'Moon' => 10, 'Venus' => 10, 'Saturn' => 1,
    ];

    /**
     * @param array<string,float> $siderealLon planet => sidereal longitude
     * @return array<string,array<string,mixed>>
     */
    public static function compute(array $siderealLon, float $ascSid, float $mcSid): array
    {
        $ascSign = Charts::signIndex($ascSid);
        $cusps = [
            1 => Charts::norm($ascSid),
            4 => Charts::norm($mcSid + 180.0),
            7 => Charts::norm($ascSid + 180.0),
            10 => Charts::norm($mcSid),
        ];

        $out = [];
        foreach (self::NAISARGIKA as $planet => $naisargika) {
            if (!isset($siderealLon[$planet])) {
                continue;
            }
            $lon = $siderealLon[$planet];

            $sthana = self::sthanaBala($planet, $lon, $siderealLon, $ascSign);
            $dig = self::digBala($planet, $lon, $cusps);

            // Available subtotal (Sthana + Dig + Naisargika); Kaala/Chesta/Drig pending.
            $computed = $sthana['total'] + $dig + $naisargika;

            $out[$planet] = [
                'sthana' => $sthana,
                'dig' => round($dig, 2),
                'kaala' => null,
                'chesta' => null,
                'naisargika' => $naisargika,
                'drig' => null,
                'computed_subtotal' => round($computed, 2),
            ];
        }
        return $out;
    }

    // --- Sthana Bala ------------------------------------------------------

    /**
     * @param array<string,float> $allLon
     * @return array{uccha:float,saptavargaja:float,ojha_yugma:float,kendradi:float,drekkana:float,total:float}
     */
    private static function sthanaBala(string $planet, float $lon, array $allLon, int $ascSign): array
    {
        // Uchcha (exaltation) bala.
        $debil = Charts::norm(self::EXALT[$planet] + 180.0);
        $arc = abs($lon - $debil);
        if ($arc > 180.0) {
            $arc = 360.0 - $arc;
        }
        $uccha = $arc / 3.0;

        // Saptavargaja bala (dignity across the 7 vargas).
        $sapta = 0.0;
        foreach (self::VARGAS as $v) {
            $sapta += self::dignity($planet, self::vargaSign($v, $lon), $v, $lon, $allLon);
        }

        // Ojha-Yugma bala (D1 + D9).
        $ojha = 0.0;
        foreach (['D1', 'D9'] as $v) {
            $sv = self::vargaSign($v, $lon);
            $odd = (($sv + 1) % 2) === 1;
            if (in_array($planet, self::FEMALE, true)) {
                if (!$odd) {
                    $ojha += 15.0;
                }
            } elseif ($odd) {
                $ojha += 15.0;
            }
        }

        // Kendradi bala (by house type, whole-sign from the ascendant).
        $house = (Charts::signIndex($lon) - $ascSign + 12) % 12 + 1;
        $kendradi = in_array($house, [1, 4, 7, 10], true) ? 60.0
            : (in_array($house, [2, 5, 8, 11], true) ? 30.0 : 15.0);

        // Drekkana bala.
        $drek = (int) floor(fmod($lon, 30.0) / 10.0);
        $drekkana = 0.0;
        if (in_array($planet, self::MALE, true)) {
            $drekkana = $drek === 0 ? 15.0 : 0.0;
        } elseif (in_array($planet, self::FEMALE, true)) {
            $drekkana = $drek === 2 ? 15.0 : 0.0;
        } else { // Mercury, Saturn -> 2nd drekkana
            $drekkana = $drek === 1 ? 15.0 : 0.0;
        }

        $total = $uccha + $sapta + $ojha + $kendradi + $drekkana;
        return [
            'uccha' => round($uccha, 2),
            'saptavargaja' => round($sapta, 2),
            'ojha_yugma' => $ojha,
            'kendradi' => $kendradi,
            'drekkana' => $drekkana,
            'total' => round($total, 2),
        ];
    }

    /** Dignity value of a planet in a varga sign (Parashari Saptavargaja scale). */
    private static function dignity(string $planet, int $signV, string $varga, float $lonD1, array $allLon): float
    {
        // Moolatrikona (45) only in D1, by exact degree.
        if ($varga === 'D1' && $signV === self::MT[$planet]) {
            $deg = fmod($lonD1, 30.0);
            [$a, $b] = self::MT_RANGE[$planet];
            if ($deg >= $a && $deg < $b) {
                return 45.0;
            }
        }
        if (in_array($signV, self::OWN[$planet], true)) {
            return 30.0;
        }
        $lord = self::SIGN_LORD[$signV];
        if ($lord === $planet) {
            return 30.0;
        }
        return self::compound(
            self::PERM[$planet][$lord] ?? 'N',
            self::temporaryRel($planet, $lord, $allLon)
        );
    }

    /** Temporary friendship from the D1 sign positions of the two planets. */
    private static function temporaryRel(string $a, string $b, array $allLon): string
    {
        if (!isset($allLon[$a], $allLon[$b])) {
            return 'E';
        }
        $sa = Charts::signIndex($allLon[$a]);
        $sb = Charts::signIndex($allLon[$b]);
        $dist = (($sb - $sa + 12) % 12) + 1;
        return in_array($dist, [2, 3, 4, 10, 11, 12], true) ? 'F' : 'E';
    }

    /** Compound (permanent + temporary) friendship -> Saptavargaja value. */
    private static function compound(string $perm, string $temp): float
    {
        return match (true) {
            $perm === 'F' && $temp === 'F' => 22.5, // great friend
            $perm === 'F' && $temp === 'E' => 7.5,  // neutral
            $perm === 'N' && $temp === 'F' => 15.0, // friend
            $perm === 'N' && $temp === 'E' => 3.75, // enemy
            $perm === 'E' && $temp === 'F' => 7.5,  // neutral
            $perm === 'E' && $temp === 'E' => 1.875,// great enemy
            default => 7.5,
        };
    }

    /** Sign index of a planet in a divisional chart. */
    private static function vargaSign(string $varga, float $lon): int
    {
        $s = Charts::signIndex($lon);
        $deg = fmod($lon, 30.0);
        $n = $s + 1; // 1-indexed sign number

        return match ($varga) {
            'D1' => $s,
            'D2' => ($n % 2 === 1) ? ($deg < 15 ? 4 : 3) : ($deg < 15 ? 3 : 4),
            'D3' => ($s + 4 * (int) floor($deg / 10.0)) % 12,
            'D7' => ($n % 2 === 1)
                ? ($s + (int) floor($deg / (30.0 / 7.0))) % 12
                : ($s + 6 + (int) floor($deg / (30.0 / 7.0))) % 12,
            'D9' => Charts::navamsaSignIndex($lon),
            'D12' => ($s + (int) floor($deg / 2.5)) % 12,
            'D30' => self::trimsamsaSign($n, $deg),
            default => $s,
        };
    }

    private static function trimsamsaSign(int $n, float $deg): int
    {
        if ($n % 2 === 1) { // odd: Mars, Saturn, Jupiter, Mercury, Venus
            return $deg < 5 ? 0 : ($deg < 10 ? 10 : ($deg < 18 ? 8 : ($deg < 25 ? 2 : 6)));
        }
        // even: Venus, Mercury, Jupiter, Saturn, Mars
        return $deg < 5 ? 1 : ($deg < 12 ? 5 : ($deg < 20 ? 11 : ($deg < 25 ? 9 : 7)));
    }

    // --- Dig Bala ---------------------------------------------------------

    /** @param array<int,float> $cusps */
    private static function digBala(string $planet, float $lon, array $cusps): float
    {
        $weak = $cusps[self::DIG_WEAK[$planet]];
        $d = abs($lon - $weak);
        if ($d > 180.0) {
            $d = 360.0 - $d;
        }
        return $d / 3.0;
    }
}
