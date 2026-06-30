<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Whole-house (Rashi) Graha Drishti per Brihat Parashara Hora Shastra.
 *
 * Every planet aspects the 7th house from where it sits. In addition:
 *   Mars            -> 4th and 8th
 *   Jupiter/Rahu/Ketu -> 5th and 9th
 *   Saturn          -> 3rd and 10th
 * (Sun, Moon, Mercury, Venus aspect the 7th only.)
 *
 * Houses are counted forward, inclusive of the planet's own house (1st), so the
 * Nth aspect of a planet in house H lands on house ((H-1)+(N-1)) mod 12 + 1.
 * e.g. Saturn in house 3 aspects houses 5, 9 and 12.
 *
 * This is the SINGLE source of truth for drishti used by the D1 chart ring, the
 * House Details table and the Copy text.
 */
final class Drishti
{
    /** Extra aspects (Nth house) beyond the universal 7th. */
    private const SPECIAL = [
        'Mars'    => [4, 8],
        'Jupiter' => [5, 9],
        'Saturn'  => [3, 10],
        'Rahu'    => [5, 9],
        'Ketu'    => [5, 9],
    ];

    /** Display order so a house's aspect list is always Su, Mo, Ma … Ke. */
    private const ORDER = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];

    /** Full planet name -> short abbreviation. */
    public const ABBR = [
        'Sun' => 'Su', 'Moon' => 'Mo', 'Mars' => 'Ma', 'Mercury' => 'Me', 'Jupiter' => 'Ju',
        'Venus' => 'Ve', 'Saturn' => 'Sa', 'Rahu' => 'Ra', 'Ketu' => 'Ke',
    ];

    /** Short abbreviation -> full planet name (reverse of ABBR). */
    public const FULL = [
        'Su' => 'Sun', 'Mo' => 'Moon', 'Ma' => 'Mars', 'Me' => 'Mercury', 'Ju' => 'Jupiter',
        'Ve' => 'Venus', 'Sa' => 'Saturn', 'Ra' => 'Rahu', 'Ke' => 'Ketu',
    ];

    /**
     * For each of the 12 houses, the list of short planet names aspecting it.
     *
     * @param array<string,int> $planetHouses planet name => house (1..12)
     * @return array<int,list<string>> house (1..12) => ['Ju','Ma', …]
     */
    public static function byHouse(array $planetHouses): array
    {
        $out = array_fill(1, 12, []);

        foreach (self::ORDER as $planet) {
            if (!isset($planetHouses[$planet])) {
                continue;
            }
            $from = (int) $planetHouses[$planet];
            if ($from < 1 || $from > 12) {
                continue;
            }
            $aspects = array_merge([7], self::SPECIAL[$planet] ?? []);
            foreach ($aspects as $nth) {
                $target = (($from - 1) + ($nth - 1)) % 12 + 1;
                $out[$target][] = self::ABBR[$planet];
            }
        }

        return $out;
    }

    /**
     * Convenience: drishti-by-house from a computed chart payload.
     *
     * @return array<int,list<string>>
     */
    public static function forChart(array $chart): array
    {
        $houses = [];
        foreach (($chart['planets'] ?? []) as $name => $p) {
            $houses[(string) $name] = (int) ($p['house'] ?? 0);
        }
        return self::byHouse($houses);
    }
}
