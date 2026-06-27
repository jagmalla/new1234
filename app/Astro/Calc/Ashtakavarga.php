<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Ashtakavarga — the bindu (benefic point) system. For each of the seven planets
 * a Bhinnashtakavarga (BAV) is built from the classical benefic-place tables:
 * each of the eight contributors (the seven planets + the Lagna) drops a bindu
 * into the signs that fall in its benefic houses. The Sarvashtakavarga (SAV) is
 * the sign-by-sign sum of the seven planetary BAVs (total = 337).
 *
 * Tables are the standard Parashari set; per-planet BAV totals are
 * Sun 48, Moon 49, Mars 39, Mercury 54, Jupiter 56, Venus 52, Saturn 39.
 */
final class Ashtakavarga
{
    /** Contributor order used by the tables. */
    private const CONTRIBUTORS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Lagna'];

    /**
     * BENEFIC[planet][contributor] = houses (1..12 from the contributor) that
     * give a bindu in that planet's Ashtakavarga.
     *
     * @var array<string, array<string, list<int>>>
     */
    private const BENEFIC = [
        'Sun' => [
            'Sun' => [1, 2, 4, 7, 8, 9, 10, 11], 'Moon' => [3, 6, 10, 11],
            'Mars' => [1, 2, 4, 7, 8, 9, 10, 11], 'Mercury' => [3, 5, 6, 9, 10, 11, 12],
            'Jupiter' => [5, 6, 9, 11], 'Venus' => [6, 7, 12],
            'Saturn' => [1, 2, 4, 7, 8, 9, 10, 11], 'Lagna' => [3, 4, 6, 10, 11, 12],
        ],
        'Moon' => [
            'Sun' => [3, 6, 7, 8, 10, 11], 'Moon' => [1, 3, 6, 7, 10, 11],
            'Mars' => [2, 3, 5, 6, 9, 10, 11], 'Mercury' => [1, 3, 4, 5, 7, 8, 10, 11],
            'Jupiter' => [1, 2, 4, 7, 8, 10, 11], 'Venus' => [3, 4, 5, 7, 9, 10, 11],
            'Saturn' => [3, 5, 6, 11], 'Lagna' => [3, 6, 10, 11],
        ],
        'Mars' => [
            'Sun' => [3, 5, 6, 10, 11], 'Moon' => [3, 6, 11],
            'Mars' => [1, 2, 4, 7, 8, 10, 11], 'Mercury' => [3, 5, 6, 11],
            'Jupiter' => [6, 10, 11, 12], 'Venus' => [6, 8, 11, 12],
            'Saturn' => [1, 4, 7, 8, 9, 10, 11], 'Lagna' => [1, 3, 6, 10, 11],
        ],
        'Mercury' => [
            'Sun' => [5, 6, 9, 11, 12], 'Moon' => [2, 4, 6, 8, 10, 11],
            'Mars' => [1, 2, 4, 7, 8, 9, 10, 11], 'Mercury' => [1, 3, 5, 6, 9, 10, 11, 12],
            'Jupiter' => [6, 8, 11, 12], 'Venus' => [1, 2, 3, 4, 5, 8, 9, 11],
            'Saturn' => [1, 2, 4, 7, 8, 9, 10, 11], 'Lagna' => [1, 2, 4, 6, 8, 10, 11],
        ],
        'Jupiter' => [
            'Sun' => [1, 2, 3, 4, 7, 8, 9, 10, 11], 'Moon' => [2, 5, 7, 9, 11],
            'Mars' => [1, 2, 4, 7, 8, 10, 11], 'Mercury' => [1, 2, 4, 5, 6, 9, 10, 11],
            'Jupiter' => [1, 2, 3, 4, 7, 8, 10, 11], 'Venus' => [2, 5, 6, 9, 10, 11],
            'Saturn' => [3, 5, 6, 12], 'Lagna' => [1, 2, 4, 5, 6, 7, 9, 10, 11],
        ],
        'Venus' => [
            'Sun' => [8, 11, 12], 'Moon' => [1, 2, 3, 4, 5, 8, 9, 11, 12],
            'Mars' => [3, 5, 6, 9, 11, 12], 'Mercury' => [3, 5, 6, 9, 11],
            'Jupiter' => [5, 8, 9, 10, 11], 'Venus' => [1, 2, 3, 4, 5, 8, 9, 10, 11],
            'Saturn' => [3, 4, 5, 8, 9, 10, 11], 'Lagna' => [1, 2, 3, 4, 5, 8, 9, 11],
        ],
        'Saturn' => [
            'Sun' => [1, 2, 4, 7, 8, 10, 11], 'Moon' => [3, 6, 11],
            'Mars' => [3, 5, 6, 10, 11, 12], 'Mercury' => [6, 8, 9, 10, 11, 12],
            'Jupiter' => [5, 6, 11, 12], 'Venus' => [6, 11, 12],
            'Saturn' => [3, 5, 6, 11], 'Lagna' => [1, 3, 4, 6, 10, 11],
        ],
    ];

    /**
     * @param array<string,int> $signs  sign index (0..11) for each of the seven
     *                                   planets and 'Lagna'.
     * @return array{bav: array<string, list<int>>, sav: list<int>,
     *               sav_total: int, bav_totals: array<string,int>}
     */
    public static function compute(array $signs): array
    {
        $bav = [];
        $sav = array_fill(0, 12, 0);
        $bavTotals = [];

        foreach (self::BENEFIC as $planet => $table) {
            $points = array_fill(0, 12, 0);
            foreach (self::CONTRIBUTORS as $contributor) {
                $base = $signs[$contributor] ?? null;
                if ($base === null) {
                    continue;
                }
                foreach ($table[$contributor] as $house) {
                    $sign = (($base + $house - 1) % 12 + 12) % 12;
                    $points[$sign]++;
                }
            }
            $bav[$planet] = $points;
            $bavTotals[$planet] = array_sum($points);
            for ($s = 0; $s < 12; $s++) {
                $sav[$s] += $points[$s];
            }
        }

        return [
            'bav' => $bav,
            'sav' => $sav,
            'sav_total' => array_sum($sav),
            'bav_totals' => $bavTotals,
        ];
    }
}
