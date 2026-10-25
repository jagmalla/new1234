<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

use AutoBusiness\Astro\Time\JulianDay;

/**
 * 🌅 सूर्योदय / सूर्यास्त — स्थान व तिथि से (NOAA सौर-सूत्र, ±1 मिनट)।
 *
 * मुहूर्त में वार सूर्योदय से बदलता है, और राहुकाल, यमगण्ड, गुलिक, चौघड़िया,
 * होरा, अभिजित — सब दिनमान (सूर्यास्त − सूर्योदय) के भाग हैं। पहले यह 06:00/18:00
 * मानकर चलता था; अब असली समय। क्षितिज-अपवर्तन सहित (−0.833°)।
 */
final class SunTimes
{
    /**
     * @return array{rise: float, set: float, polar: bool} JD (UT); ध्रुवीय दिन/रात
     *         में (सूर्य न उगे/न डूबे) 06:00–18:00 स्थानीय मानकर polar = true।
     */
    public static function forDate(int $y, int $m, int $d, float $lat, float $lonEast, float $tz): array
    {
        $jd0 = JulianDay::fromGregorian($y, $m, $d, 0, 0, 0.0, 0.0);      // उस तिथि का 00h UT
        $noonLocal = JulianDay::fromGregorian($y, $m, $d, 12, 0, 0.0, $tz);
        $rise = self::event($noonLocal, $jd0, $lat, $lonEast, -1);
        $set = self::event($noonLocal, $jd0, $lat, $lonEast, 1);
        if ($rise === null || $set === null) {
            return [
                'rise' => JulianDay::fromGregorian($y, $m, $d, 6, 0, 0.0, $tz),
                'set' => JulianDay::fromGregorian($y, $m, $d, 18, 0, 0.0, $tz),
                'polar' => true,
            ];
        }
        // एक बार और — घटना के अपने क्षण की सौर-स्थिति से (दोपहर की जगह)
        $rise = self::event($rise, $jd0, $lat, $lonEast, -1) ?? $rise;
        $set = self::event($set, $jd0, $lat, $lonEast, 1) ?? $set;
        return ['rise' => $rise, 'set' => $set, 'polar' => false];
    }

    /** sign −1 = उदय, +1 = अस्त; JD UT या null (उस दिन घटना नहीं)। */
    private static function event(float $jdAt, float $jd0, float $lat, float $lonEast, int $sign): ?float
    {
        $t = ($jdAt - 2451545.0) / 36525.0;
        $l0 = fmod(280.46646 + $t * (36000.76983 + $t * 0.0003032), 360.0);
        $mAn = 357.52911 + $t * (35999.05029 - 0.0001537 * $t);
        $e = 0.016708634 - $t * (0.000042037 + 0.0000001267 * $t);
        $mR = deg2rad($mAn);
        $c = sin($mR) * (1.914602 - $t * (0.004817 + 0.000014 * $t))
            + sin(2 * $mR) * (0.019993 - 0.000101 * $t) + sin(3 * $mR) * 0.000289;
        $omega = deg2rad(125.04 - 1934.136 * $t);
        $lambda = deg2rad($l0 + $c - 0.00569 - 0.00478 * sin($omega));
        $eps0 = 23.0 + (26.0 + (21.448 - $t * (46.815 + $t * (0.00059 - $t * 0.001813))) / 60.0) / 60.0;
        $eps = deg2rad($eps0 + 0.00256 * cos($omega));
        $decl = asin(sin($eps) * sin($lambda));
        $yv = tan($eps / 2) ** 2;
        $l0R = deg2rad($l0);
        $eot = 4.0 * rad2deg($yv * sin(2 * $l0R) - 2 * $e * sin($mR) + 4 * $e * $yv * sin($mR) * cos(2 * $l0R)
            - 0.5 * $yv * $yv * sin(4 * $l0R) - 1.25 * $e * $e * sin(2 * $mR));
        $latR = deg2rad($lat);
        $cosH = cos(deg2rad(90.833)) / (cos($latR) * cos($decl)) - tan($latR) * tan($decl);
        if ($cosH < -1.0 || $cosH > 1.0) {
            return null;
        }
        $ha = rad2deg(acos($cosH));
        $noonMin = 720.0 - 4.0 * $lonEast - $eot;
        $min = $noonMin + $sign * 4.0 * $ha;
        return $jd0 + $min / 1440.0;
    }
}
