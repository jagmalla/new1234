<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Divisional (varga) chart sign computations, following the common
 * Parashari / Jagannatha Hora conventions. Each method returns the sign index
 * (0 = Aries .. 11 = Pisces) a longitude falls in for that divisional chart.
 *
 * Used by the chart view to render D1, D3, D4, D7, D9, D10, D12, D20, D30, D40.
 */
final class Varga
{
    /** Divisions offered as charts, in display order, with friendly labels. */
    public const CHARTS = [
        'D1' => 'Rasi (D1)',
        'D9' => 'Navamsa (D9)',
        'D3' => 'Drekkana (D3)',
        'D4' => 'Chaturthamsa (D4)',
        'D7' => 'Saptamsa (D7)',
        'D10' => 'Dasamsa (D10)',
        'D12' => 'Dwadasamsa (D12)',
        'D20' => 'Vimsamsa (D20)',
        'D30' => 'Trimsamsa (D30)',
        'D40' => 'Khavedamsa (D40)',
    ];

    /** Sign index (0..11) of a sidereal longitude in the given divisional chart. */
    public static function sign(string $varga, float $lon): int
    {
        $lon = Charts::norm($lon);
        $s = (int) floor($lon / 30.0) % 12;
        $deg = fmod($lon, 30.0);
        $n = $s + 1; // 1-indexed sign number (odd/even tests)

        return match ($varga) {
            'D1' => $s,
            'D3' => ($s + 4 * (int) floor($deg / 10.0)) % 12,
            'D4' => ($s + 3 * (int) floor($deg / 7.5)) % 12,
            'D7' => ($n % 2 === 1)
                ? ($s + (int) floor($deg / (30.0 / 7.0))) % 12
                : ($s + 6 + (int) floor($deg / (30.0 / 7.0))) % 12,
            'D9' => Charts::navamsaSignIndex($lon),
            'D10' => ($n % 2 === 1)
                ? ($s + (int) floor($deg / 3.0)) % 12
                : ($s + 8 + (int) floor($deg / 3.0)) % 12,
            'D12' => ($s + (int) floor($deg / 2.5)) % 12,
            'D20' => self::cyclicByModality($s, (int) floor($deg / 1.5), [0, 8, 4]),
            'D30' => self::trimsamsa($n, $deg),
            'D40' => (($n % 2 === 1 ? 0 : 6) + (int) floor($deg / 0.75)) % 12,
            default => $s,
        };
    }

    /**
     * Divisional degree (0..30) of a longitude *within its divisional sign* —
     * the position the planet would show on the divisional chart, following PL's
     * convention. A planet sits at the same fractional position inside its
     * divisional part as it does inside that part in D1, expanded to a full sign:
     * a planet 1/3 of the way through its navamsa part shows ~10° in D9.
     *
     * For the equal-part divisions this is fmod(deg, 30/N) / (30/N) * 30. D30
     * (Trimsamsa) uses unequal portions, handled explicitly so the degree maps
     * onto the same portion that {@see sign()} picks.
     */
    public static function degree(string $varga, float $lon): float
    {
        $lon = Charts::norm($lon);
        $deg = fmod($lon, 30.0);

        // Equal-part divisions: number of parts per sign.
        $n = match ($varga) {
            'D1' => 1, 'D3' => 3, 'D4' => 4, 'D7' => 7, 'D9' => 9,
            'D10' => 10, 'D12' => 12, 'D20' => 20, 'D40' => 40,
            default => 0,
        };
        if ($n > 0) {
            $w = 30.0 / $n;
            return fmod($deg, $w) / $w * 30.0;
        }

        if ($varga === 'D30') {
            $signNum = ((int) floor($lon / 30.0) % 12) + 1; // 1-indexed
            // Cumulative portion boundaries (odd vs even sign) — mirror trimsamsa().
            $bounds = ($signNum % 2 === 1) ? [0, 5, 10, 18, 25, 30] : [0, 5, 12, 20, 25, 30];
            for ($i = 0; $i < 5; $i++) {
                if ($deg < $bounds[$i + 1] || $i === 4) {
                    $start = (float) $bounds[$i];
                    $width = (float) ($bounds[$i + 1] - $bounds[$i]);
                    return ($deg - $start) / $width * 30.0;
                }
            }
        }

        return $deg;
    }

    /**
     * Start sign by modality (movable/fixed/dual) + part offset.
     * @param array{int,int,int} $starts [movable, fixed, dual] start signs
     */
    private static function cyclicByModality(int $s, int $part, array $starts): int
    {
        $start = $starts[$s % 3];
        return ($start + $part) % 12;
    }

    /** Trimsamsa (D30): unequal Mars/Saturn/Jupiter/Mercury/Venus portions. */
    private static function trimsamsa(int $n, float $deg): int
    {
        if ($n % 2 === 1) { // odd sign
            return $deg < 5 ? 0 : ($deg < 10 ? 10 : ($deg < 18 ? 8 : ($deg < 25 ? 2 : 6)));
        }
        return $deg < 5 ? 1 : ($deg < 12 ? 5 : ($deg < 20 ? 11 : ($deg < 25 ? 9 : 7)));
    }
}
