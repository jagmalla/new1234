<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Vimshopaka Bala (BPHS) — a planet's strength across the divisional charts,
 * scored out of 20, in the four classical varga groups (Shadvarga, Saptavarga,
 * Dashavarga, Shodashavarga).
 *
 * For each group every varga carries a fixed Vimshopaka weight (the weights of a
 * group sum to 20). Within each varga the planet earns a fraction of that weight
 * from its DIGNITY in the divisional sign (Own/Moolatrikona/Exaltation = full,
 * down to Debilitation). Summing the weighted dignities gives a score out of 20.
 *
 * Divisional placements come from {@see Varga::sign} — the same shared logic the
 * charts use — so this never re-derives positions.
 */
final class VimshopakaBala
{
    /** Vimshopaka weights per varga for each group (each group sums to 20). */
    private const GROUPS = [
        'Shadvarga'     => ['D1' => 6.0, 'D2' => 2.0, 'D3' => 4.0, 'D9' => 5.0, 'D12' => 2.0, 'D30' => 1.0],
        'Saptavarga'    => ['D1' => 5.0, 'D2' => 2.0, 'D3' => 3.0, 'D7' => 2.5, 'D9' => 4.5, 'D12' => 2.0, 'D30' => 1.0],
        'Dashavarga'    => ['D1' => 3.0, 'D2' => 1.5, 'D3' => 1.5, 'D7' => 1.5, 'D9' => 1.5, 'D10' => 1.5, 'D12' => 1.5, 'D16' => 1.5, 'D30' => 1.5, 'D60' => 5.0],
        'Shodashavarga' => ['D1' => 3.5, 'D2' => 1.0, 'D3' => 1.0, 'D4' => 0.5, 'D7' => 0.5, 'D9' => 3.0, 'D10' => 0.5, 'D12' => 0.5, 'D16' => 2.0, 'D20' => 0.5, 'D24' => 0.5, 'D27' => 0.5, 'D30' => 1.0, 'D40' => 0.5, 'D45' => 0.5, 'D60' => 4.0],
    ];

    /**
     * Dignity value (out of 20) awarded per varga. Divisional dignity uses the
     * NATURAL (Naisargika) friendship of the planet with the divisional sign's
     * lord — friend/neutral/enemy — plus own/exaltation (full) and debilitation
     * (lowest). (PL does not fold in temporal friendship here, so the compound
     * Great-Friend/Great-Enemy tiers don't arise.)
     */
    private const VAL = [
        'exalt' => 20.0, 'own' => 20.0, 'friend' => 15.0,
        'neutral' => 10.0, 'enemy' => 7.0, 'debil' => 2.0,
    ];

    private const SIGN_LORD = [
        0 => 'Mars', 1 => 'Venus', 2 => 'Mercury', 3 => 'Moon', 4 => 'Sun', 5 => 'Mercury',
        6 => 'Venus', 7 => 'Mars', 8 => 'Jupiter', 9 => 'Saturn', 10 => 'Saturn', 11 => 'Jupiter',
    ];
    /** Exaltation sign index per planet (nodes per the common Parashari scheme). */
    private const EXALT_SIGN = [
        'Sun' => 0, 'Moon' => 1, 'Mars' => 9, 'Mercury' => 5, 'Jupiter' => 3,
        'Venus' => 11, 'Saturn' => 6, 'Rahu' => 1, 'Ketu' => 7,
    ];
    /** Own sign indices (nodes co-lord Aquarius / Scorpio). */
    private const OWN = [
        'Sun' => [4], 'Moon' => [3], 'Mars' => [0, 7], 'Mercury' => [2, 5],
        'Jupiter' => [8, 11], 'Venus' => [1, 6], 'Saturn' => [9, 10],
        'Rahu' => [10], 'Ketu' => [7],
    ];
    /** Natural (permanent) friendship: F=friend, N=neutral, E=enemy. */
    private const PERM = [
        'Sun'     => ['Moon' => 'F', 'Mars' => 'F', 'Jupiter' => 'F', 'Mercury' => 'N', 'Venus' => 'E', 'Saturn' => 'E'],
        'Moon'    => ['Sun' => 'F', 'Mercury' => 'F', 'Mars' => 'N', 'Jupiter' => 'N', 'Venus' => 'N', 'Saturn' => 'N'],
        'Mars'    => ['Sun' => 'F', 'Moon' => 'F', 'Jupiter' => 'F', 'Mercury' => 'E', 'Venus' => 'N', 'Saturn' => 'N'],
        'Mercury' => ['Sun' => 'F', 'Venus' => 'F', 'Moon' => 'E', 'Mars' => 'N', 'Jupiter' => 'N', 'Saturn' => 'N'],
        'Jupiter' => ['Sun' => 'F', 'Moon' => 'F', 'Mars' => 'F', 'Mercury' => 'E', 'Venus' => 'E', 'Saturn' => 'N'],
        'Venus'   => ['Mercury' => 'F', 'Saturn' => 'F', 'Sun' => 'E', 'Moon' => 'E', 'Mars' => 'N', 'Jupiter' => 'N'],
        'Saturn'  => ['Mercury' => 'F', 'Venus' => 'F', 'Sun' => 'E', 'Moon' => 'E', 'Mars' => 'E', 'Jupiter' => 'N'],
        'Rahu'    => ['Mercury' => 'F', 'Venus' => 'F', 'Saturn' => 'F', 'Jupiter' => 'N', 'Sun' => 'E', 'Moon' => 'E', 'Mars' => 'E'],
        'Ketu'    => ['Mars' => 'F', 'Venus' => 'F', 'Saturn' => 'F', 'Jupiter' => 'N', 'Mercury' => 'N', 'Sun' => 'E', 'Moon' => 'E'],
    ];

    private const PLANETS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];

    /**
     * @param array<string,float> $siderealLon planet => sidereal longitude (all 9)
     * @return array{groups:list<string>, planets:list<string>, scores:array<string,array<string,int>>}
     *         scores[group][planet] = integer 0..20
     */
    public static function compute(array $siderealLon): array
    {
        $signD1 = [];
        foreach (self::PLANETS as $p) {
            if (isset($siderealLon[$p])) {
                $signD1[$p] = Charts::signIndex((float) $siderealLon[$p]);
            }
        }

        $scores = [];
        foreach (self::GROUPS as $group => $weights) {
            foreach (self::PLANETS as $p) {
                if (!isset($siderealLon[$p])) {
                    continue;
                }
                $lon = (float) $siderealLon[$p];
                $sum = 0.0;
                foreach ($weights as $varga => $w) {
                    $signV = Varga::sign($varga, $lon);
                    $sum += $w * (self::dignityValue($p, $signV, $signD1) / 20.0);
                }
                $scores[$group][$p] = (int) round($sum);
            }
        }

        return [
            'groups'  => array_keys(self::GROUPS),
            'planets' => self::PLANETS,
            'scores'  => $scores,
        ];
    }

    /**
     * Dignity value (out of 20) of $planet in divisional sign $signV.
     * @param array<string,int> $signD1 planet => D1 sign (unused; kept for signature stability)
     */
    private static function dignityValue(string $planet, int $signV, array $signD1): float
    {
        if ((self::EXALT_SIGN[$planet] ?? -1) === $signV) {
            return self::VAL['exalt'];
        }
        if (isset(self::EXALT_SIGN[$planet]) && (self::EXALT_SIGN[$planet] + 6) % 12 === $signV) {
            return self::VAL['debil'];
        }
        if (in_array($signV, self::OWN[$planet] ?? [], true)) {
            return self::VAL['own'];
        }

        $lord = self::SIGN_LORD[$signV];
        if ($lord === $planet) {
            return self::VAL['own'];
        }

        $rel = self::PERM[$planet][$lord] ?? 'N';
        return match ($rel) {
            'F' => self::VAL['friend'],
            'E' => self::VAL['enemy'],
            default => self::VAL['neutral'],
        };
    }
}
