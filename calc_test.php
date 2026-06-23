<?php
declare(strict_types=1);

/**
 * calc_test.php — standalone verification harness for the Calculation Engine.
 *
 * Prints, in plain readable text, the D1 chart (with planet degrees), the D9
 * (Navamsa) chart, Vimshottari dasha periods, (partial) Shadbala, the Varshaphal
 * annual chart, and current Gochar (transits) — so the math can be checked
 * against known astrology software before the rest of the engine is trusted.
 *
 * HOW TO RUN (from the project root):
 *
 *   php calc_test.php \
 *       --date=1990-01-01 --time=12:00 \
 *       --lat=28.6139 --lon=77.2090 --tz=5.5 \
 *       --ayanamsa=lahiri --year=2026 --gochar=today
 *
 * Arguments (all optional; sensible defaults shown):
 *   --date=YYYY-MM-DD     birth date            (default 1980-12-01)
 *   --time=HH:MM          birth time, local     (default 12:31)
 *   --lat=VALUE           latitude              (default 30N48'00, Moga)
 *   --lon=VALUE           longitude             (default 75E10'00, Moga)
 *   --tz=VALUE            offset east of UTC    (default 5:30, IST)
 *
 * Latitude/longitude accept EITHER decimal degrees OR degrees-minutes-seconds
 * with a direction letter, e.g. all of these are accepted:
 *     --lat=30.9467        --lat="30N56'48"     --lat=30:56:48N
 *     --lon=75.1389        --lon="75E08'20"     --lon=75:08:20E
 * (Wrap any value containing an apostrophe in double quotes for the shell.)
 *
 * Timezone accepts decimal hours (5.5) or H:M[:S] (5:30) — EAST IS POSITIVE.
 * India = +5:30 (enter 5.5 or 5:30). NOTE: some astrology software displays the
 * timezone with the OPPOSITE sign (e.g. "-5:30" for India, meaning "subtract to
 * reach GMT"); for this tool always use the east-positive value (+5:30).
 *   --ayanamsa=NAME       lahiri|raman|kp|...   (default lahiri)
 *   --year=YYYY           Varshaphal year       (default current year)
 *   --gochar=YYYY-MM-DD   transit date or 'today'(default today)
 *
 * Longitude is EAST-positive: western locations (e.g. Surrey BC) use a NEGATIVE
 * longitude and a negative tz (e.g. --lon=-122.85 --tz=-8).
 */

require __DIR__ . '/bootstrap.php';

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Varshaphal;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;

// --- Parse CLI arguments ----------------------------------------------------
$opts = getopt('', ['date::', 'time::', 'lat::', 'lon::', 'tz::', 'ayanamsa::', 'year::', 'gochar::', 'gochar-time::']);
// Defaults = the reference birth used for verification:
//   Moga, Punjab, India — 1 Dec 1980, 12:31:00, IST (no DST).
//   Longitude 75E10'00 = 75.1667, Latitude 30N48'00 = 30.8, TZ +5:30.
$date = $opts['date'] ?? '1980-12-01';
$time = $opts['time'] ?? '12:31';
$lat = parseAngle((string) ($opts['lat'] ?? "30N48'00"), 'lat');
$lon = parseAngle((string) ($opts['lon'] ?? "75E10'00"), 'lon');
$tz = parseTz((string) ($opts['tz'] ?? '5:30'));
$ayanamsa = (string) ($opts['ayanamsa'] ?? 'lahiri');
$forYear = (int) ($opts['year'] ?? (int) date('Y'));
$gochar = $opts['gochar'] ?? 'today';

[$Y, $Mo, $D] = array_map('intval', explode('-', $date));
[$H, $Mi] = array_map('intval', explode(':', $time));

$jdBirth = JulianDay::fromGregorian($Y, $Mo, $D, $H, $Mi, 0.0, $tz);

$engine = new CalculationEngine(EphemerisFactory::create(), $ayanamsa);
$chart = $engine->computeChart($jdBirth, $lat, $lon);

// --- Header -----------------------------------------------------------------
line('=', 72);
echo "AUTO BUSINESS — CALCULATION ENGINE TEST\n";
line('=', 72);
printf("Birth     : %s %s  (UTC%+.1f)\n", $date, $time, $tz);
printf("Place     : lat %.4f, lon %.4f (east+)\n", $lat, $lon);
printf("Ephemeris : %s\n", $chart['meta']['ephemeris']);
printf("Ayanamsa  : %s = %s\n", $chart['meta']['ayanamsa_name'], dms($chart['meta']['ayanamsa_deg']));
printf("JD (UT)   : %.5f\n", $jdBirth);

// --- Ascendant --------------------------------------------------------------
section('ASCENDANT (LAGNA) & MC');
$asc = $chart['ascendant'];
printf("Lagna : %-18s  nakshatra %s (pada %d)\n",
    $asc['formatted'], $asc['nakshatra']['name'], $asc['nakshatra']['pada']);
printf("        Navamsa Lagna: %s\n", $asc['navamsa_sign']);
printf("MC    : %s\n", $chart['mc']['formatted']);

// --- D1 (Rasi) --------------------------------------------------------------
section('D1 (RASI) — PLANETARY POSITIONS');
printf("%-9s %-16s %-5s %-22s %-3s\n", 'Planet', 'Position', 'House', 'Nakshatra (pada)', 'R');
line('-', 60);
foreach ($chart['planets'] as $name => $p) {
    printf("%-9s %-16s %-5d %-18s(%d) %-3s\n",
        $name, $p['formatted'], $p['house'],
        $p['nakshatra']['name'], $p['nakshatra']['pada'],
        $p['retro'] ? 'R' : '');
}

// --- D9 (Navamsa) -----------------------------------------------------------
section('D9 (NAVAMSA)');
printf("%-9s %-14s\n", 'Planet', 'Navamsa Sign');
line('-', 28);
printf("%-9s %-14s\n", 'Lagna', $asc['navamsa_sign']);
foreach ($chart['planets'] as $name => $p) {
    printf("%-9s %-14s\n", $name, $p['navamsa_sign']);
}

// --- Vimshottari Dasha ------------------------------------------------------
section('VIMSHOTTARI DASHA');
printf("Birth nakshatra balance: %s for %.2f years\n",
    $chart['dasha']['balance']['lord'], $chart['dasha']['balance']['years']);
$run = $chart['dasha']['running'];
printf("Running now (birth)    : %s Mahadasha / %s Antardasha\n\n",
    $run['maha'] ?? '-', $run['antar'] ?? '-');
printf("%-9s %-13s %-13s %-8s\n", 'Maha', 'Start', 'End', 'Years');
line('-', 46);
foreach ($chart['dasha']['mahadashas'] as $md) {
    printf("%-9s %-13s %-13s %-8.2f\n",
        $md['lord'], jdDate($md['start_jd']), jdDate($md['end_jd']), $md['years']);
}

// --- Shadbala ---------------------------------------------------------------
section('SHADBALA (SIX-FOLD STRENGTH) — Parashara\'s Light rules');
printf("%-8s %7s %6s %7s %7s %7s %6s | %7s %6s %5s %6s %6s\n",
    'Planet', 'Sthana', 'Dig', 'Kaala', 'Chesta', 'Naisrg', 'Drig', 'Total', 'Rupas', 'Ratio', 'Ishta', 'Kashta');
line('-', 92);
foreach ($chart['shadbala'] as $name => $b) {
    printf("%-8s %7.1f %6.1f %7.1f %7.1f %7.1f %6.1f | %7.1f %6.2f %5.2f %6.1f %6.1f\n",
        $name, $b['sthana']['total'], $b['dig'], $b['kaala'], $b['chesta'],
        $b['naisargika'], $b['drig'],
        $b['total_virupa'], $b['total_rupa'], $b['ratio'], $b['ishta'], $b['kashta']);
}
echo "\nSthana, Dig, Naisargika match Parashara's Light to +/-0.01. Kaala/Chesta/Drig\n";
echo "follow PL's method; Total/Rupas/Ratio are close to PL (Chesta & Drig are the\n";
echo "most program-specific components).\n";

// --- Varshaphal -------------------------------------------------------------
section("VARSHAPHAL (ANNUAL CHART) — year {$forYear}");
$vp = Varshaphal::compute($engine, $chart, $Y, $Mo, $D, $H, $Mi, $tz, $lat, $lon, $forYear);
printf("Solar return (Varsha Pravesh): %s\n", jdDateTime($vp['solar_return_jd']));
printf("Age completed   : %d years\n", $vp['age_completed']);
printf("Varsha Lagna    : %-14s (lord %s)\n", $vp['varsha_chart']['ascendant']['formatted'], $vp['varsha_lagna']['lord']);
printf("Muntha          : %-14s (lord %s)\n", $vp['muntha']['sign'], $vp['muntha']['lord']);
printf("Varshesh cand.  : Lagna lord %s, Muntha lord %s\n",
    $vp['varshesh_candidates']['varsha_lagna_lord'], $vp['varshesh_candidates']['muntha_lord']);
echo "\nAnnual planetary positions (sidereal):\n";
foreach ($vp['varsha_chart']['planets'] as $name => $p) {
    printf("  %-9s %-16s house %d %s\n", $name, $p['formatted'], $p['house'], $p['retro'] ? 'R' : '');
}
echo "\nMudda dasha (annual):\n";
printf("  %-9s %-13s %-13s %-8s\n", 'Lord', 'Start', 'End', 'Days');
foreach ($vp['mudda_dasha'] as $md) {
    printf("  %-9s %-13s %-13s %-8.1f\n",
        $md['lord'], jdDate($md['start_jd']), jdDate($md['end_jd']), $md['days']);
}

// --- Gochar (transits) — defaults to the current date AND time --------------
$gocharDate = ($gochar === 'today' || $gochar === 'now') ? date('Y-m-d') : $gochar;
$gocharTime = (string) ($opts['gochar-time'] ?? date('H:i'));
[$gy, $gm, $gd] = array_map('intval', explode('-', $gocharDate));
[$gH, $gMi] = array_map('intval', array_pad(explode(':', $gocharTime), 2, '0'));
$jdGochar = JulianDay::fromGregorian($gy, $gm, $gd, $gH, $gMi, 0.0, $tz);
$gocharData = $engine->gochar($chart, $jdGochar, $lat, $lon);

section("GOCHAR (TRANSITS) — {$gocharDate} {$gocharTime} (birth location)");
echo "Transit Lagna: " . $gocharData['ascendant']['formatted'] . "\n\n";
printf("%-9s %-16s %-4s %-16s %-16s\n", 'Planet', 'Transit', 'R', 'House/Lagna', 'House/Moon');
line('-', 64);
foreach ($gocharData['transits'] as $name => $t) {
    printf("%-9s %-16s %-4s %-16d %-16d\n",
        $name, $t['formatted'], $t['retro'] ? 'R' : '',
        $t['house_from_lagna'], $t['house_from_moon']);
}

line('=', 72);
echo "Done. Compare the D1/D9 positions, dasha dates, and ascendant against your\n";
echo "reference astrology software (use the SAME ayanamsa). Sub-arcminute matches\n";
echo "require Swiss Ephemeris (set SWETEST_PATH); the pure-PHP provider is ~1-2'.\n";
line('=', 72);

// --- input parsing helpers --------------------------------------------------

/**
 * Parse a latitude/longitude given as either decimal degrees ("30.9467",
 * "-122.85") or DMS with a direction letter ("30N56'48", "75:08:20E"). North
 * and East are positive; South and West negative.
 */
function parseAngle(string $s, string $type): float
{
    $s = trim($s);

    // Direction letter (N/S/E/W), anywhere in the string.
    $dir = '';
    if (preg_match('/[NSEWnsew]/', $s, $m)) {
        $dir = strtoupper($m[0]);
    }
    // Replace the direction letter with a SPACE (it may sit between the degree
    // and minute digits, e.g. "30N56'48"), so the numbers don't get merged.
    $clean = trim(preg_replace('/[NSEWnsew]/', ' ', $s) ?? $s);

    if (preg_match('/^[-+]?\d+(\.\d+)?$/', $clean)) {
        $val = (float) $clean;                 // plain decimal
    } else {
        // DMS: pull the numeric groups in order (deg, min, sec).
        preg_match_all('/\d+(?:\.\d+)?/', $clean, $mm);
        $p = $mm[0];
        $deg = (float) ($p[0] ?? 0);
        $min = (float) ($p[1] ?? 0);
        $sec = (float) ($p[2] ?? 0);
        $val = $deg + $min / 60.0 + $sec / 3600.0;
        if (str_starts_with($clean, '-')) {
            $val = -$val;
        }
    }

    if ($dir === 'S' || $dir === 'W') {
        $val = -abs($val);
    } elseif ($dir === 'N' || $dir === 'E') {
        $val = abs($val);
    }
    return $val;
}

/**
 * Parse a timezone offset as decimal hours ("5.5", "-8") or H:M[:S] ("5:30").
 * East of UTC is positive.
 */
function parseTz(string $s): float
{
    $s = trim($s);
    if (preg_match('/^[-+]?\d+(\.\d+)?$/', $s)) {
        return (float) $s;
    }
    $sign = str_starts_with($s, '-') ? -1.0 : 1.0;
    $p = explode(':', ltrim($s, '+-'));
    $h = (float) ($p[0] ?? 0);
    $m = (float) ($p[1] ?? 0);
    $sec = (float) ($p[2] ?? 0);
    return $sign * ($h + $m / 60.0 + $sec / 3600.0);
}

// --- formatting helpers -----------------------------------------------------
function line(string $ch, int $n): void
{
    echo str_repeat($ch, $n) . "\n";
}

function section(string $title): void
{
    echo "\n";
    line('-', 72);
    echo $title . "\n";
    line('-', 72);
}

function dms(float $deg): string
{
    $d = (int) floor($deg);
    $m = (int) floor(($deg - $d) * 60.0);
    $s = (int) round((($deg - $d) * 60.0 - $m) * 60.0);
    return sprintf("%d°%02d'%02d\"", $d, $m, $s);
}

/** Julian Day (UT) -> calendar date string. */
function jdDate(float $jd): string
{
    return jdToString($jd, 'Y-m-d');
}

function jdDateTime(float $jd): string
{
    return jdToString($jd, 'Y-m-d H:i') . ' UT';
}

function jdToString(float $jd, string $fmt): string
{
    // Inverse Julian Day (Meeus), in UT.
    $jd += 0.5;
    $z = (int) floor($jd);
    $f = $jd - $z;
    if ($z < 2299161) {
        $a = $z;
    } else {
        $alpha = (int) floor(($z - 1867216.25) / 36524.25);
        $a = $z + 1 + $alpha - (int) floor($alpha / 4);
    }
    $b = $a + 1524;
    $c = (int) floor(($b - 122.1) / 365.25);
    $d = (int) floor(365.25 * $c);
    $e = (int) floor(($b - $d) / 30.6001);
    $day = $b - $d - (int) floor(30.6001 * $e) + $f;
    $month = $e < 14 ? $e - 1 : $e - 13;
    $year = $month > 2 ? $c - 4716 : $c - 4715;
    $dayInt = (int) floor($day);
    $frac = $day - $dayInt;
    $hours = $frac * 24.0;
    $h = (int) floor($hours);
    $min = (int) floor(($hours - $h) * 60.0);
    return (new DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $dayInt, $h, $min)))->format($fmt);
}
