<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Zodiac/varga helpers shared by the calculation engine: sign + nakshatra names,
 * D1 (Rasi) and D9 (Navamsa) mapping, and degree formatting.
 */
final class Charts
{
    public const SIGNS = [
        'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
        'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces',
    ];

    /** Rashi lords, indexed by sign 0..11 (Aries..Pisces). */
    public const SIGN_LORDS = [
        'Mars', 'Venus', 'Mercury', 'Moon', 'Sun', 'Mercury',
        'Venus', 'Mars', 'Jupiter', 'Saturn', 'Saturn', 'Jupiter',
    ];

    public static function signLord(int $signIndex): string
    {
        return self::SIGN_LORDS[(($signIndex % 12) + 12) % 12];
    }

    /** 27 nakshatras (each 13°20'). */
    public const NAKSHATRAS = [
        'Ashwini', 'Bharani', 'Krittika', 'Rohini', 'Mrigashira', 'Ardra',
        'Punarvasu', 'Pushya', 'Ashlesha', 'Magha', 'Purva Phalguni', 'Uttara Phalguni',
        'Hasta', 'Chitra', 'Swati', 'Vishakha', 'Anuradha', 'Jyeshtha',
        'Mula', 'Purva Ashadha', 'Uttara Ashadha', 'Shravana', 'Dhanishta', 'Shatabhisha',
        'Purva Bhadrapada', 'Uttara Bhadrapada', 'Revati',
    ];

    /** Sign index 0..11 from a sidereal longitude. */
    public static function signIndex(float $siderealLon): int
    {
        return (int) floor(self::norm($siderealLon) / 30.0) % 12;
    }

    public static function signName(float $siderealLon): string
    {
        return self::SIGNS[self::signIndex($siderealLon)];
    }

    /** Degrees within the current sign (0..30). */
    public static function degInSign(float $siderealLon): float
    {
        return fmod(self::norm($siderealLon), 30.0);
    }

    /**
     * Navamsa (D9) sign for a sidereal longitude. Each sign is split into nine
     * 3°20' parts; the starting navamsa sign depends on the sign's element
     * (movable from itself, fixed from the 9th, dual from the 5th).
     */
    public static function navamsaSignIndex(float $siderealLon): int
    {
        $sign = self::signIndex($siderealLon);
        $deg = self::degInSign($siderealLon);
        $part = (int) floor($deg / (30.0 / 9.0)); // 0..8

        // Starting sign by element: movable (0,3,6,9) -> same sign;
        // fixed (1,4,7,10) -> 9th from it; dual (2,5,8,11) -> 5th from it.
        $mod = $sign % 3;
        $start = match ($mod) {
            0 => $sign,                 // movable
            1 => ($sign + 8) % 12,      // fixed (9th)
            default => ($sign + 4) % 12 // dual (5th)
        };
        return ($start + $part) % 12;
    }

    /** @return array{index:int, name:string, pada:int} nakshatra of a sidereal lon */
    public static function nakshatra(float $siderealLon): array
    {
        $span = 360.0 / 27.0; // 13.3333
        $n = self::norm($siderealLon);
        $idx = (int) floor($n / $span) % 27;
        $within = $n - $idx * $span;
        $pada = (int) floor($within / ($span / 4.0)) + 1; // 1..4
        return ['index' => $idx, 'name' => self::NAKSHATRAS[$idx], 'pada' => $pada];
    }

    /** "12°34' Scorpio" style formatting from a sidereal longitude. */
    public static function format(float $siderealLon): string
    {
        $deg = self::degInSign($siderealLon);
        $d = (int) floor($deg);
        $m = (int) floor(($deg - $d) * 60.0);
        return sprintf("%2d°%02d' %s", $d, $m, self::signName($siderealLon));
    }

    /** House number (1..12) of a longitude relative to the ascendant sign. */
    public static function houseFromAsc(float $siderealLon, int $ascSignIndex): int
    {
        return (self::signIndex($siderealLon) - $ascSignIndex + 12) % 12 + 1;
    }

    public static function norm(float $deg): float
    {
        $deg = fmod($deg, 360.0);
        return $deg < 0 ? $deg + 360.0 : $deg;
    }
}
