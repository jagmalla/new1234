<?php
declare(strict_types=1);

namespace AutoBusiness\Http;

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Varshaphal;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Core\AdminGuard;

/**
 * Browser-based Calculation Engine tester (the web twin of calc_test.php).
 *
 * Renders a birth-details form and the resulting D1/D9/dasha/shadbala/
 * varshaphal/gochar so the engine can be verified on screen (and screenshotted)
 * without the command line. Read-only computation — no database, no secrets —
 * but still gated behind AdminGuard so it is staff-only in production.
 */
final class CalcController
{
    public function show(): void
    {
        AdminGuard::require();

        // Defaults = the Moga reference birth (matches calc_test.php).
        $date = (string) ($_GET['date'] ?? '1980-12-01');
        $time = (string) ($_GET['time'] ?? '12:31');
        $latIn = (string) ($_GET['lat'] ?? "30N48'00");
        $lonIn = (string) ($_GET['lon'] ?? "75E10'00");
        $tzIn = (string) ($_GET['tz'] ?? '5:30');
        $ayanamsa = (string) ($_GET['ayanamsa'] ?? 'lahiri');
        $forYear = (int) ($_GET['year'] ?? (int) date('Y'));
        $gocharIn = (string) ($_GET['gochar'] ?? date('Y-m-d'));

        $error = null;
        $chart = $vp = $gochar = null;
        $meta = [];

        try {
            $lat = self::parseAngle($latIn);
            $lon = self::parseAngle($lonIn);
            $tz = self::parseTz($tzIn);
            [$Y, $Mo, $D] = array_map('intval', explode('-', $date));
            [$H, $Mi] = array_map('intval', array_pad(explode(':', $time), 2, '0'));

            $jd = JulianDay::fromGregorian($Y, $Mo, $D, $H, $Mi, 0.0, $tz);
            $engine = new CalculationEngine(EphemerisFactory::create(), $ayanamsa);
            $chart = $engine->computeChart($jd, $lat, $lon);
            $vp = Varshaphal::compute($engine, $chart, $Y, $Mo, $D, $H, $Mi, $tz, $lat, $lon, $forYear);

            [$gy, $gm, $gd] = array_map('intval', explode('-', $gocharIn));
            $jdG = JulianDay::fromGregorian($gy, $gm, $gd, 12, 0, 0.0, $tz);
            $gochar = $engine->gochar($chart, $jdG, $lat, $lon);

            $meta = ['lat' => $lat, 'lon' => $lon, 'tz' => $tz, 'jd' => $jd];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        // Expose for the view.
        $view = [
            'in' => compact('date', 'time', 'latIn', 'lonIn', 'tzIn', 'ayanamsa', 'forYear', 'gocharIn'),
            'error' => $error,
            'chart' => $chart,
            'vp' => $vp,
            'gochar' => $gochar,
            'meta' => $meta,
        ];
        require dirname(__DIR__) . '/Http/views/calc.php';
    }

    /** Decimal or DMS-with-direction-letter angle -> signed decimal degrees. */
    public static function parseAngle(string $s): float
    {
        $s = trim($s);
        $dir = '';
        if (preg_match('/[NSEWnsew]/', $s, $m)) {
            $dir = strtoupper($m[0]);
        }
        $clean = trim(preg_replace('/[NSEWnsew]/', ' ', $s) ?? $s);

        if (preg_match('/^[-+]?\d+(\.\d+)?$/', $clean)) {
            $val = (float) $clean;
        } else {
            preg_match_all('/\d+(?:\.\d+)?/', $clean, $mm);
            $p = $mm[0];
            $val = (float) ($p[0] ?? 0) + (float) ($p[1] ?? 0) / 60.0 + (float) ($p[2] ?? 0) / 3600.0;
            if (str_starts_with($clean, '-')) {
                $val = -$val;
            }
        }
        if ($dir === 'S' || $dir === 'W') {
            return -abs($val);
        }
        if ($dir === 'N' || $dir === 'E') {
            return abs($val);
        }
        return $val;
    }

    /** Decimal hours or H:M[:S] (east positive) -> decimal hours. */
    public static function parseTz(string $s): float
    {
        $s = trim($s);
        if (preg_match('/^[-+]?\d+(\.\d+)?$/', $s)) {
            return (float) $s;
        }
        $sign = str_starts_with($s, '-') ? -1.0 : 1.0;
        $p = explode(':', ltrim($s, '+-'));
        return $sign * ((float) ($p[0] ?? 0) + (float) ($p[1] ?? 0) / 60.0 + (float) ($p[2] ?? 0) / 3600.0);
    }
}
