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
        // Birth date is entered DD-MM-YYYY.
        $date = (string) ($_GET['date'] ?? '01-12-1980');
        $time = (string) ($_GET['time'] ?? '12:31');
        $latIn = (string) ($_GET['lat'] ?? "30N48'00");
        $lonIn = (string) ($_GET['lon'] ?? "75E10'00");
        $tzIn = (string) ($_GET['tz'] ?? '5:30');
        $ayanamsa = (string) ($_GET['ayanamsa'] ?? 'lahiri');
        $name = (string) ($_GET['name'] ?? '');
        $gender = (string) ($_GET['gender'] ?? '');
        $forYear = (int) ($_GET['year'] ?? (int) date('Y'));
        // Gochar defaults to NOW (current date + time); both are adjustable.
        $gocharIn = (string) ($_GET['gochar'] ?? date('Y-m-d'));
        $gocharTimeIn = (string) ($_GET['gochar_time'] ?? date('H:i'));

        $error = null;
        $chart = $vp = $gochar = null;
        $meta = [];

        try {
            $lat = self::parseAngle($latIn);
            $lon = self::parseAngle($lonIn);
            $tz = self::parseTz($tzIn);
            [$Y, $Mo, $D] = self::parseDate($date);   // accepts DD-MM-YYYY or YYYY-MM-DD
            [$H, $Mi] = array_map('intval', array_pad(explode(':', $time), 2, '0'));

            $jd = JulianDay::fromGregorian($Y, $Mo, $D, $H, $Mi, 0.0, $tz);
            $engine = new CalculationEngine(EphemerisFactory::create(), $ayanamsa);
            $chart = $engine->computeChart($jd, $lat, $lon);

            // Live dasha chain (running Maha/Antar/Pratyantar + next Antar) at now.
            $nowJd = JulianDay::fromGregorian(
                (int) date('Y'), (int) date('m'), (int) date('d'),
                (int) date('H'), (int) date('i'), 0.0, $tz
            );
            $dashaNow = \AutoBusiness\Astro\Calc\VimshottariDasha::runningChain(
                (float) $chart['planets']['Moon']['sidereal_lon'], $jd, $nowJd
            );
            $vp = Varshaphal::compute($engine, $chart, $Y, $Mo, $D, $H, $Mi, $tz, $lat, $lon, $forYear);

            [$gy, $gm, $gd] = array_map('intval', explode('-', $gocharIn));
            [$gH, $gMi] = array_map('intval', array_pad(explode(':', $gocharTimeIn), 2, '0'));
            $jdG = JulianDay::fromGregorian($gy, $gm, $gd, $gH, $gMi, 0.0, $tz);
            $gochar = $engine->gochar($chart, $jdG, $lat, $lon);

            $vargas = $engine->vargaCharts($chart);
            $meta = ['lat' => $lat, 'lon' => $lon, 'tz' => $tz, 'jd' => $jd];
            // Birth params handed to the browser so the interactive gochar panel
            // can rebuild the natal chart for any transit instant/place.
            $birthJs = [
                'date' => sprintf('%04d-%02d-%02d', $Y, $Mo, $D), // ISO for JS endpoints
                'time' => $time,
                'lat' => $lat, 'lon' => $lon, 'tz' => $tz, 'ayanamsa' => $ayanamsa,
            ];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        // Expose for the view.
        $view = [
            'in' => compact('date', 'time', 'latIn', 'lonIn', 'tzIn', 'ayanamsa', 'name', 'gender', 'forYear', 'gocharIn', 'gocharTimeIn'),
            'error' => $error,
            'chart' => $chart,
            'vp' => $vp,
            'gochar' => $gochar,
            'vargas' => $vargas ?? null,
            'meta' => $meta,
            'birthJs' => $birthJs ?? null,
            'dashaNow' => $dashaNow ?? null,
        ];
        require dirname(__DIR__) . '/Http/views/calc.php';
    }

    /**
     * JSON endpoint for the interactive gochar panel: rebuilds the natal chart
     * from the birth params, then returns transits for the requested instant and
     * place. Read-only, AdminGuard-gated like show().
     */
    public function gocharJson(): void
    {
        AdminGuard::require();
        header('Content-Type: application/json');

        try {
            $tz = self::parseTz((string) ($_GET['tz'] ?? '0'));
            $lat = self::parseAngle((string) ($_GET['lat'] ?? '0'));
            $lon = self::parseAngle((string) ($_GET['lon'] ?? '0'));
            [$gy, $gm, $gd] = array_map('intval', explode('-', (string) ($_GET['date'] ?? date('Y-m-d'))));
            [$gH, $gMi] = array_map('intval', array_pad(explode(':', (string) ($_GET['time'] ?? '00:00')), 2, '0'));

            // Birth params (to rebuild the natal chart for house-from references).
            $ayanamsa = (string) ($_GET['ayanamsa'] ?? 'lahiri');
            $bLat = self::parseAngle((string) ($_GET['blat'] ?? (string) $lat));
            $bLon = self::parseAngle((string) ($_GET['blon'] ?? (string) $lon));
            $bTz = self::parseTz((string) ($_GET['btz'] ?? (string) $tz));
            [$bY, $bMo, $bD] = array_map('intval', explode('-', (string) ($_GET['bdate'] ?? date('Y-m-d'))));
            [$bH, $bMi] = array_map('intval', array_pad(explode(':', (string) ($_GET['btime'] ?? '12:00')), 2, '0'));

            $engine = new CalculationEngine(EphemerisFactory::create(), $ayanamsa);
            $natalJd = JulianDay::fromGregorian($bY, $bMo, $bD, $bH, $bMi, 0.0, $bTz);
            $natal = $engine->computeChart($natalJd, $bLat, $bLon);

            $jdG = JulianDay::fromGregorian($gy, $gm, $gd, $gH, $gMi, 0.0, $tz);
            $gochar = $engine->gochar($natal, $jdG, $lat, $lon);
            $gochar['label'] = sprintf('%04d-%02d-%02d %02d:%02d', $gy, $gm, $gd, $gH, $gMi);

            echo json_encode($gochar, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * JSON endpoint for the Varshaphal question box: rebuilds the natal chart
     * from the birth params, computes the annual (solar-return) chart + Mudda
     * dasha for the requested year, and returns a render-ready payload.
     */
    public function varshaphalJson(): void
    {
        AdminGuard::require();
        header('Content-Type: application/json');

        try {
            $ayanamsa = (string) ($_GET['ayanamsa'] ?? 'lahiri');
            $lat = self::parseAngle((string) ($_GET['blat'] ?? '0'));
            $lon = self::parseAngle((string) ($_GET['blon'] ?? '0'));
            $tz = self::parseTz((string) ($_GET['btz'] ?? '0'));
            [$bY, $bMo, $bD] = array_map('intval', explode('-', (string) ($_GET['bdate'] ?? date('Y-m-d'))));
            [$bH, $bMi] = array_map('intval', array_pad(explode(':', (string) ($_GET['btime'] ?? '12:00')), 2, '0'));
            $forYear = (int) ($_GET['year'] ?? (int) date('Y'));

            $engine = new CalculationEngine(EphemerisFactory::create(), $ayanamsa);
            $natalJd = JulianDay::fromGregorian($bY, $bMo, $bD, $bH, $bMi, 0.0, $tz);
            $natal = $engine->computeChart($natalJd, $lat, $lon);
            $vp = Varshaphal::compute($engine, $natal, $bY, $bMo, $bD, $bH, $bMi, $tz, $lat, $lon, $forYear);

            // Muntha sign index = natal Lagna sign advanced one sign per year.
            $natalAscSign = (int) $natal['ascendant']['sign_index'];
            $munthaSignIndex = (($natalAscSign + (int) $vp['age_completed']) % 12 + 12) % 12;

            echo json_encode([
                'year' => $forYear,
                'age_completed' => $vp['age_completed'],
                'varsha_lagna' => $vp['varsha_lagna'],
                'muntha' => $vp['muntha'],
                'muntha_sign_index' => $munthaSignIndex,
                // Varsha Pravesh (solar-return) start date, DD-MM-YYYY at birth tz.
                'varsha_start' => JulianDay::toDmy((float) $vp['solar_return_jd'], $tz),
                'chart' => $engine->northPayload($vp['varsha_chart']),
                'ascendant_formatted' => $vp['varsha_chart']['ascendant']['formatted'],
                'mudda_dasha' => $vp['mudda_dasha'],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Parse a date entered as DD-MM-YYYY (preferred) or YYYY-MM-DD into
     * [year, month, day]. Separators -, / or . are accepted.
     *
     * @return array{0:int,1:int,2:int}
     */
    public static function parseDate(string $s): array
    {
        $parts = preg_split('/[-\/.]/', trim($s)) ?: [];
        if (count($parts) !== 3) {
            return [(int) date('Y'), 1, 1];
        }
        [$a, $b, $c] = array_map('intval', $parts);
        // A 4-digit first field means YYYY-MM-DD; otherwise DD-MM-YYYY.
        return $a > 31 ? [$a, $b, $c] : [$c, $b, $a];
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
