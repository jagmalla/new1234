<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Time;

/**
 * Calendar <-> Julian Day conversions (Meeus, "Astronomical Algorithms").
 *
 * All astronomy in this engine is driven by Universal Time (UT). calc_test and
 * the calculation engine convert a local birth time to UT using an explicit
 * timezone offset (hours east of Greenwich) so no timezone database is needed.
 */
final class JulianDay
{
    /** Reference epoch used by the analytic ephemeris: 2000 Jan 0.0 UT. */
    public const EPOCH_2000_JAN0 = 2451543.5;

    /**
     * Julian Day (UT) for a Gregorian date/time.
     *
     * @param float $tzOffsetHours hours east of UTC (e.g. India +5.5, BC -8/-7)
     */
    public static function fromGregorian(
        int $year,
        int $month,
        int $day,
        int $hour = 0,
        int $minute = 0,
        float $second = 0.0,
        float $tzOffsetHours = 0.0
    ): float {
        // Local clock time -> UT.
        $dayFraction = ($hour + $minute / 60.0 + $second / 3600.0 - $tzOffsetHours) / 24.0;

        $y = $year;
        $m = $month;
        if ($m <= 2) {
            $y -= 1;
            $m += 12;
        }
        $a = intdiv($y, 100);
        $b = 2 - $a + intdiv($a, 4); // Gregorian calendar correction

        return floor(365.25 * ($y + 4716))
            + floor(30.6001 * ($m + 1))
            + ($day + $dayFraction)
            + $b - 1524.5;
    }

    /** Schlyter "day number" d = days since 2000 Jan 0.0 UT (includes time). */
    public static function dayNumber(float $jdUt): float
    {
        return $jdUt - self::EPOCH_2000_JAN0;
    }

    /** Julian centuries from J2000.0 (JD 2451545.0). */
    public static function centuriesSinceJ2000(float $jdUt): float
    {
        return ($jdUt - 2451545.0) / 36525.0;
    }

    /**
     * JD (UT) -> calendar date at the given timezone (hours east +).
     *
     * @return array{0:int,1:int,2:int}  [year, month, day]
     */
    public static function toGregorian(float $jdUt, float $tzHours = 0.0): array
    {
        $z = (int) floor($jdUt + 0.5 + $tzHours / 24.0);
        $a = $z;
        if ($z >= 2299161) {
            $alpha = (int) floor(($z - 1867216.25) / 36524.25);
            $a = $z + 1 + $alpha - (int) floor($alpha / 4);
        }
        $b = $a + 1524;
        $c = (int) floor(($b - 122.1) / 365.25);
        $d = (int) floor(365.25 * $c);
        $e = (int) floor(($b - $d) / 30.6001);
        $day = $b - $d - (int) floor(30.6001 * $e);
        $month = $e < 14 ? $e - 1 : $e - 13;
        $year = $month > 2 ? $c - 4716 : $c - 4715;
        return [$year, $month, $day];
    }

    /** JD (UT) -> "DD-MM-YYYY" at the given timezone. */
    public static function toDmy(float $jdUt, float $tzHours = 0.0): string
    {
        [$y, $m, $d] = self::toGregorian($jdUt, $tzHours);
        return sprintf('%02d-%02d-%04d', $d, $m, $y);
    }
}
