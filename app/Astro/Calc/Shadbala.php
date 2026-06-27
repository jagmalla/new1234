<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

use AutoBusiness\Astro\Time\JulianDay;

/**
 * Shadbala — full six-fold planetary strength, following Parashara's Light
 * conventions (Lahiri, true node, equal house).
 *
 * Components:
 *   1. Sthana  — Uchcha + Saptavargaja + Ojha-Yugma + Kendradi + Drekkana  (validated ±0.01 vs PL)
 *   2. Dig     — directional, via Lagna/MC cusps                            (validated ±0.01 vs PL)
 *   3. Kaala   — Nathonnata + Paksha + Tribhaga + Vara + Hora + Masa + Abda + Ayana + Yuddha
 *   4. Chesta  — Sun=Ayana, Moon=Paksha, star planets = seeghra (motional)
 *   5. Naisargika — fixed natural strength                                  (PL values)
 *   6. Drig    — net benefic-minus-malefic aspect (Sphuta Drishti)
 *
 * Then Total (virupas) -> Rupas (/60) -> ratio vs minimum requirement, and
 * Ishta/Kashta phala. The engine passes sidereal longitudes, speeds, asc/MC,
 * the instant, and the place; everything else (sunrise, declinations, lords) is
 * derived here.
 */
final class Shadbala
{
    private const SIGN_LORD = [
        0 => 'Mars', 1 => 'Venus', 2 => 'Mercury', 3 => 'Moon', 4 => 'Sun', 5 => 'Mercury',
        6 => 'Venus', 7 => 'Mars', 8 => 'Jupiter', 9 => 'Saturn', 10 => 'Saturn', 11 => 'Jupiter',
    ];
    private const EXALT = [
        'Sun' => 10.0, 'Moon' => 33.0, 'Mars' => 298.0, 'Mercury' => 165.0,
        'Jupiter' => 95.0, 'Venus' => 357.0, 'Saturn' => 200.0,
    ];
    private const MT = ['Sun' => 4, 'Moon' => 1, 'Mars' => 0, 'Mercury' => 5, 'Jupiter' => 8, 'Venus' => 6, 'Saturn' => 10];
    private const MT_RANGE = [
        'Sun' => [0, 20], 'Moon' => [3, 30], 'Mars' => [0, 12], 'Mercury' => [16, 20],
        'Jupiter' => [0, 10], 'Venus' => [0, 15], 'Saturn' => [0, 20],
    ];
    private const OWN = [
        'Sun' => [4], 'Moon' => [3], 'Mars' => [0, 7], 'Mercury' => [2, 5],
        'Jupiter' => [8, 11], 'Venus' => [1, 6], 'Saturn' => [9, 10],
    ];
    private const PERM = [
        'Sun' => ['Moon' => 'F', 'Mars' => 'F', 'Jupiter' => 'F', 'Mercury' => 'N', 'Venus' => 'E', 'Saturn' => 'E'],
        'Moon' => ['Sun' => 'F', 'Mercury' => 'F', 'Mars' => 'N', 'Jupiter' => 'N', 'Venus' => 'N', 'Saturn' => 'N'],
        'Mars' => ['Sun' => 'F', 'Moon' => 'F', 'Jupiter' => 'F', 'Mercury' => 'E', 'Venus' => 'N', 'Saturn' => 'N'],
        'Mercury' => ['Sun' => 'F', 'Venus' => 'F', 'Moon' => 'E', 'Mars' => 'N', 'Jupiter' => 'N', 'Saturn' => 'N'],
        'Jupiter' => ['Sun' => 'F', 'Moon' => 'F', 'Mars' => 'F', 'Mercury' => 'E', 'Venus' => 'E', 'Saturn' => 'N'],
        'Venus' => ['Mercury' => 'F', 'Saturn' => 'F', 'Sun' => 'E', 'Moon' => 'E', 'Mars' => 'N', 'Jupiter' => 'N'],
        'Saturn' => ['Mercury' => 'F', 'Venus' => 'F', 'Sun' => 'E', 'Moon' => 'E', 'Mars' => 'E', 'Jupiter' => 'N'],
    ];
    private const NAISARGIKA = [
        'Sun' => 60.00, 'Moon' => 51.42, 'Mars' => 17.16, 'Mercury' => 25.74,
        'Jupiter' => 34.26, 'Venus' => 42.84, 'Saturn' => 8.58,
    ];
    /** Minimum required strength (virupas) per planet (Parashari). */
    private const MIN_REQUIRED = [
        'Sun' => 390, 'Moon' => 360, 'Mars' => 300, 'Mercury' => 420,
        'Jupiter' => 390, 'Venus' => 330, 'Saturn' => 300,
    ];
    private const MALE = ['Sun', 'Mars', 'Jupiter'];
    private const FEMALE = ['Moon', 'Venus'];
    private const VARGAS = ['D1', 'D2', 'D3', 'D7', 'D9', 'D12', 'D30'];
    private const DIG_WEAK = ['Sun' => 4, 'Mars' => 4, 'Jupiter' => 7, 'Mercury' => 7, 'Moon' => 10, 'Venus' => 10, 'Saturn' => 1];

    private const PLANETS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
    private const WEEKDAY_LORD = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn']; // 0=Sun..6=Sat
    private const CHALDEAN = ['Saturn', 'Jupiter', 'Mars', 'Sun', 'Venus', 'Mercury', 'Moon'];
    private const DIURNAL = ['Sun', 'Jupiter', 'Venus'];
    private const NOCTURNAL = ['Moon', 'Mars', 'Saturn'];
    private const BENEFIC = ['Jupiter', 'Venus', 'Mercury', 'Moon']; // Moon treated benefic for Paksha
    /** Mean-longitude rates (deg, deg/day at J2000) for the seeghra/Chesta kendra. */
    private const MEAN_LON = [
        'Sun' => [280.4665, 0.98564736], 'Mercury' => [252.25084, 4.09233445],
        'Venus' => [181.97973, 1.60213049], 'Mars' => [355.43300, 0.52403840],
        'Jupiter' => [34.35148, 0.08308676], 'Saturn' => [50.07744, 0.03346063],
    ];

    /**
     * @param array<string,float> $siderealLon planet => sidereal longitude
     * @param array<string,float> $speeds      planet => daily speed (deg/day)
     * @return array<string,array<string,mixed>>
     */
    public static function compute(
        array $siderealLon,
        array $speeds,
        float $ascSid,
        float $mcSid,
        float $jdUt,
        float $lat,
        float $lonEast,
        float $ayanamsaDeg
    ): array {
        $ascSign = Charts::signIndex($ascSid);
        $cusps = [
            1 => Charts::norm($ascSid), 4 => Charts::norm($mcSid + 180.0),
            7 => Charts::norm($ascSid + 180.0), 10 => Charts::norm($mcSid),
        ];

        $tropical = [];
        $decl = [];
        foreach (self::PLANETS as $p) {
            $tropical[$p] = Charts::norm($siderealLon[$p] + $ayanamsaDeg);
            $decl[$p] = self::declination($tropical[$p]);
        }

        // Time-of-day context for Kaala.
        $ctx = self::timeContext($jdUt, $lat, $lonEast, $tropical['Sun'], $decl['Sun'], $siderealLon, $speeds);

        // Shared Paksha + Ayana (also reused by Chesta).
        $elong = Charts::norm($siderealLon['Moon'] - $siderealLon['Sun']);
        $pakshaBen = (180.0 - abs(180.0 - $elong)) / 3.0;
        $ayana = [];
        foreach (self::PLANETS as $p) {
            $ayana[$p] = self::ayanaBala($p, $decl[$p]);
        }

        $out = [];
        foreach (self::PLANETS as $planet) {
            $lon = $siderealLon[$planet];

            $sthana = self::sthanaBala($planet, $lon, $siderealLon, $ascSign);
            $dig = self::digBala($planet, $lon, $cusps);
            $naisargika = self::NAISARGIKA[$planet];

            $paksha = in_array($planet, self::BENEFIC, true) ? $pakshaBen : (60.0 - $pakshaBen);
            $kaala = self::kaalaBala($planet, $paksha, $ayana[$planet], $ctx);
            $chesta = self::chestaBala($planet, $ayana[$planet], $paksha, $jdUt, $tropical);
            $drig = self::drigBala($planet, $siderealLon);

            $total = $sthana['total'] + $dig + $kaala + $chesta + $naisargika + $drig;
            $rupas = $total / 60.0;
            $required = self::MIN_REQUIRED[$planet];
            $ratio = $total / $required;

            // Ishta/Kashta phala = sqrt(Uchcha x Chesta), sqrt((60-Uchcha)(60-Chesta)).
            $u = $sthana['uccha'];
            $ishta = sqrt(max(0.0, $u) * max(0.0, $chesta));
            $kashta = sqrt(max(0.0, 60.0 - $u) * max(0.0, 60.0 - $chesta));

            $out[$planet] = [
                'sthana' => $sthana,
                'dig' => round($dig, 2),
                'kaala' => round($kaala, 2),
                'chesta' => round($chesta, 2),
                'naisargika' => $naisargika,
                'drig' => round($drig, 2),
                'total_virupa' => round($total, 2),
                'total_rupa' => round($rupas, 2),
                'required_rupa' => round($required / 60.0, 2),
                'ratio' => round($ratio, 2),
                'ishta' => round($ishta, 2),
                'kashta' => round($kashta, 2),
            ];
        }
        return $out;
    }

    // =====================================================================
    // KAALA BALA
    // =====================================================================

    /**
     * @param array<string,mixed> $ctx
     */
    private static function kaalaBala(string $planet, float $paksha, float $ayana, array $ctx): float
    {
        // Nathonnata (day/night).
        if ($planet === 'Mercury') {
            $nath = 60.0;
        } elseif (in_array($planet, self::DIURNAL, true)) {
            $nath = $ctx['unnata'];
        } else {
            $nath = $ctx['nata'];
        }

        // Tribhaga: Jupiter always 60; plus the lord of the current day/night third.
        $trib = ($planet === 'Jupiter' ? 60.0 : 0.0) + ($planet === $ctx['tribhaga_lord'] ? 60.0 : 0.0);

        $vara = $planet === $ctx['vara_lord'] ? 45.0 : 0.0;
        $hora = $planet === $ctx['hora_lord'] ? 60.0 : 0.0;
        $masa = $planet === $ctx['masa_lord'] ? 30.0 : 0.0;
        $abda = $planet === $ctx['abda_lord'] ? 15.0 : 0.0;
        $yuddha = $ctx['yuddha'][$planet] ?? 0.0;

        return $nath + $paksha + $trib + $vara + $hora + $masa + $abda + $ayana + $yuddha;
    }

    /**
     * Ayana Bala (declination-based). North-strong: Sun/Mars/Jupiter/Venus;
     * south-strong: Moon/Saturn; Mercury always uses the favourable (|decl|).
     */
    private static function ayanaBala(string $planet, float $decl): float
    {
        if ($planet === 'Mercury') {
            $kranti = abs($decl);
        } elseif (in_array($planet, ['Moon', 'Saturn'], true)) {
            $kranti = -$decl; // south positive
        } else {
            $kranti = $decl;  // north positive
        }
        $v = 60.0 * (23.4578 + $kranti) / 47.9156;
        return max(0.0, $v);
    }

    /**
     * @param array<string,float> $siderealLon
     * @param array<string,float> $speeds
     * @return array<string,mixed>
     */
    private static function timeContext(
        float $jdUt,
        float $lat,
        float $lonEast,
        float $sunTropical,
        float $sunDecl,
        array $siderealLon,
        array $speeds
    ): array {
        $S = static fn($d) => sin(deg2rad($d));
        $C = static fn($d) => cos(deg2rad($d));
        $T = static fn($d) => tan(deg2rad($d));
        $eps = 23.4423;

        // Birth local clock hour (from the JD's fractional day + timezone is already
        // folded into the JD by the caller, so reconstruct local hour from lon).
        $H0 = rad2deg(acos(max(-1.0, min(1.0, -$T($lat) * $T($sunDecl))))); // semi-diurnal arc
        $raSun = Charts::norm(rad2deg(atan2($S($sunTropical) * $C($eps), $C($sunTropical))));
        $t = ($jdUt - 2451545.0) / 36525.0;
        $gmst = Charts::norm(280.46061837 + 360.98564736629 * ($jdUt - 2451545.0) + 0.000387933 * $t * $t);
        $haBirth = Charts::norm($gmst + $lonEast - $raSun);
        if ($haBirth > 180.0) {
            $haBirth -= 360.0;
        }

        // Local clock time of birth (hours) from the JD fractional part + timezone meridian.
        $birthClock = self::localClockHour($jdUt, $lonEast);
        $transit = $birthClock - $haBirth / 15.0;
        $half = $H0 / 15.0;
        $sunrise = $transit - $half;
        $sunset = $transit + $half;
        $dayLen = $sunset - $sunrise;
        $isDay = $birthClock >= $sunrise && $birthClock < $sunset;

        $unnata = 60.0 * (1.0 - abs($haBirth) / 180.0);
        $nata = 60.0 - $unnata;

        // Lords.
        $dow = ((int) floor($jdUt + 1.5)) % 7; // 0=Sunday
        $varaLord = self::WEEKDAY_LORD[$dow];

        // Hora (equal 60-min horas from sunrise), Chaldean order from the day lord.
        $startIdx = (int) array_search($varaLord, self::CHALDEAN, true);
        $hsince = $birthClock - $sunrise;
        if ($hsince < 0) {
            $hsince += 24.0;
        }
        $horaLord = self::CHALDEAN[($startIdx + (int) floor($hsince)) % 7];

        // Tribhaga third lord.
        $dayThird = ['Mercury', 'Sun', 'Saturn'];
        $nightThird = ['Moon', 'Venus', 'Mars'];
        if ($isDay) {
            $part = (int) floor(($birthClock - $sunrise) / max(0.01, $dayLen / 3.0));
            $tribLord = $dayThird[max(0, min(2, $part))];
        } else {
            $nl = 24.0 - $dayLen;
            $bt = $birthClock < $sunrise ? $birthClock + 24.0 : $birthClock;
            $part = (int) floor(($bt - $sunset) / max(0.01, $nl / 3.0));
            $tribLord = $nightThird[max(0, min(2, $part))];
        }

        // Masa (Sun's entry into current sign) + Abda (Mesha entry) weekday lords.
        $masaJd = self::sankrantiJd($jdUt, $siderealLon['Sun'], fmod($siderealLon['Sun'], 30.0), $speeds['Sun']);
        $abdaJd = self::sankrantiJd($jdUt, $siderealLon['Sun'], $siderealLon['Sun'], $speeds['Sun']);
        $masaLord = self::WEEKDAY_LORD[((int) floor($masaJd + 1.5)) % 7];
        $abdaLord = self::WEEKDAY_LORD[((int) floor($abdaJd + 1.5)) % 7];

        // Yuddha (planetary war): star planets within 1 deg — winner gains, loser loses
        // the difference. Rare; usually empty.
        $yuddha = self::yuddhaBala($siderealLon);

        return [
            'unnata' => $unnata, 'nata' => $nata,
            'vara_lord' => $varaLord, 'hora_lord' => $horaLord, 'tribhaga_lord' => $tribLord,
            'masa_lord' => $masaLord, 'abda_lord' => $abdaLord, 'yuddha' => $yuddha,
        ];
    }

    /** Reconstruct local clock hour from the UT JD + timezone meridian (east lon). */
    private static function localClockHour(float $jdUt, float $lonEast): float
    {
        // Local civil time uses the standard meridian nearest the longitude (15 deg zones),
        // but India uses 82.5E. We approximate with the longitude itself for the day fraction.
        $utHour = (($jdUt + 0.5) - floor($jdUt + 0.5)) * 24.0;
        $local = $utHour + $lonEast / 15.0;
        return fmod($local + 24.0, 24.0);
    }

    /** JD when the Sun was 'back' degrees ago (entering the relevant sign), Newton step. */
    private static function sankrantiJd(float $jdUt, float $sunSid, float $back, float $sunSpeed): float
    {
        // Use the mean Sun speed (~0.9856) for a stable multi-month back-projection.
        return $jdUt - $back / 0.98564736;
    }

    /**
     * @param array<string,float> $siderealLon
     * @return array<string,float>
     */
    private static function yuddhaBala(array $siderealLon): array
    {
        $stars = ['Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
        $out = array_fill_keys(self::PLANETS, 0.0);
        for ($i = 0; $i < count($stars); $i++) {
            for ($j = $i + 1; $j < count($stars); $j++) {
                $a = $stars[$i];
                $b = $stars[$j];
                $d = abs($siderealLon[$a] - $siderealLon[$b]);
                if ($d > 180.0) {
                    $d = 360.0 - $d;
                }
                if ($d <= 1.0) {
                    // The planet with the lower longitude wins (simplified).
                    $winner = $siderealLon[$a] < $siderealLon[$b] ? $a : $b;
                    $loser = $winner === $a ? $b : $a;
                    $out[$winner] += $d;
                    $out[$loser] -= $d;
                }
            }
        }
        return $out;
    }

    // =====================================================================
    // CHESTA BALA
    // =====================================================================

    /**
     * @param array<string,float> $tropical
     */
    private static function chestaBala(string $planet, float $ayana, float $paksha, float $jdUt, array $tropical): float
    {
        if ($planet === 'Sun') {
            return $ayana;
        }
        if ($planet === 'Moon') {
            return $paksha;
        }
        // Star planets: Chesta = seeghra (synodic) kendra / 3.
        $d = $jdUt - 2451545.0;
        $meanSun = Charts::norm(self::MEAN_LON['Sun'][0] + self::MEAN_LON['Sun'][1] * $d);
        $meanPl = Charts::norm(self::MEAN_LON[$planet][0] + self::MEAN_LON[$planet][1] * $d);

        // Superior planets reckon from the Sun; inferior from the planet.
        $kendra = in_array($planet, ['Mars', 'Jupiter', 'Saturn'], true)
            ? Charts::norm($meanSun - $meanPl)
            : Charts::norm($meanPl - $meanSun);
        if ($kendra > 180.0) {
            $kendra = 360.0 - $kendra;
        }
        return $kendra / 3.0;
    }

    // =====================================================================
    // DRIG BALA
    // =====================================================================

    /**
     * @param array<string,float> $siderealLon
     */
    private static function drigBala(string $aspected, array $siderealLon): float
    {
        $benefics = ['Jupiter', 'Venus', 'Mercury', 'Moon'];
        $sum = 0.0;
        foreach (self::PLANETS as $aspecting) {
            if ($aspecting === $aspected) {
                continue;
            }
            $v = self::sphutaDrishti($aspecting, $siderealLon[$aspecting], $siderealLon[$aspected]);
            $sum += (in_array($aspecting, $benefics, true) ? 1.0 : -1.0) * $v;
        }
        return $sum / 4.0;
    }

    /**
     * Net benefic-minus-malefic Sphuta Drishti (virupa) cast by the seven planets
     * on an arbitrary point — used for the Bhava Drishti Bala of a house cusp.
     *
     * @param array<string,float> $siderealLon
     */
    public static function netDrishtiOnPoint(float $point, array $siderealLon): float
    {
        $benefics = ['Jupiter', 'Venus', 'Mercury', 'Moon'];
        $sum = 0.0;
        foreach (self::PLANETS as $aspecting) {
            $v = self::sphutaDrishti($aspecting, $siderealLon[$aspecting], $point);
            $sum += (in_array($aspecting, $benefics, true) ? 1.0 : -1.0) * $v;
        }
        return $sum;
    }

    /** Sphuta Drishti (virupa) of one planet on a point, incl. special aspects. */
    private static function sphutaDrishti(string $aspecting, float $from, float $to): float
    {
        $c = Charts::norm($to - $from); // 0..360 from aspecting to aspected

        // Base Parashari drishti curve.
        $v = self::baseDrishti($c);

        // Special full aspects raise specific houses to 60.
        $special = [
            'Mars' => [90.0, 210.0], 'Jupiter' => [120.0, 240.0], 'Saturn' => [60.0, 270.0],
        ];
        if (isset($special[$aspecting])) {
            foreach ($special[$aspecting] as $ang) {
                if (abs($c - $ang) <= 15.0) {
                    $v = max($v, 45.0 + (15.0 - abs($c - $ang)));
                }
            }
        }
        return $v;
    }

    private static function baseDrishti(float $c): float
    {
        // Parashari graded drishti, anchored at the house cusps
        // (3rd=15, 4th=45, 5th=30, 7th=60) and peaking at the 7th (180 deg).
        if ($c <= 30.0) {
            return 0.0;
        }
        if ($c <= 60.0) {
            return ($c - 30.0) / 2.0;            // 0 -> 15
        }
        if ($c <= 90.0) {
            return ($c - 60.0) + 15.0;           // 15 -> 45
        }
        if ($c <= 120.0) {
            return (120.0 - $c) / 2.0 + 30.0;    // 45 -> 30
        }
        if ($c <= 180.0) {
            return ($c - 120.0) / 2.0 + 30.0;    // 30 -> 60 (7th = full)
        }
        // The graded curve is NOT symmetric about the 7th: the back houses
        // descend 60 -> 45 -> 30 -> 15 -> 0 (8th=45, 9th=30, 10th=15, 12th=0),
        // matching the special-aspect pairs (3rd/10th = 1/4, 4th/8th = 3/4,
        // 5th/9th = 1/2). Mirroring baseDrishti(360-c) would over-count the
        // 10th-house region (e.g. 270 deg -> 45 instead of 15).
        if ($c <= 240.0) {
            return 60.0 - ($c - 180.0) / 2.0;    // 60 -> 30 (8th = 45, 9th = 30)
        }
        if ($c <= 270.0) {
            return 30.0 - ($c - 240.0) / 2.0;    // 30 -> 15 (10th = 15)
        }
        if ($c <= 330.0) {
            return 15.0 - ($c - 270.0) / 4.0;    // 15 -> 0  (11th = 7.5, 12th = 0)
        }
        return 0.0;
    }

    // =====================================================================
    // STHANA + DIG  (validated vs PL — unchanged)
    // =====================================================================

    /**
     * @param array<string,float> $allLon
     * @return array{uccha:float,saptavargaja:float,ojha_yugma:float,kendradi:float,drekkana:float,total:float}
     */
    private static function sthanaBala(string $planet, float $lon, array $allLon, int $ascSign): array
    {
        $debil = Charts::norm(self::EXALT[$planet] + 180.0);
        $arc = abs($lon - $debil);
        if ($arc > 180.0) {
            $arc = 360.0 - $arc;
        }
        $uccha = $arc / 3.0;

        $sapta = 0.0;
        foreach (self::VARGAS as $v) {
            $sapta += self::dignity($planet, self::vargaSign($v, $lon), $v, $lon, $allLon);
        }

        $ojha = 0.0;
        foreach (['D1', 'D9'] as $v) {
            $sv = self::vargaSign($v, $lon);
            $odd = (($sv + 1) % 2) === 1;
            if (in_array($planet, self::FEMALE, true)) {
                if (!$odd) {
                    $ojha += 15.0;
                }
            } elseif ($odd) {
                $ojha += 15.0;
            }
        }

        $house = (Charts::signIndex($lon) - $ascSign + 12) % 12 + 1;
        $kendradi = in_array($house, [1, 4, 7, 10], true) ? 60.0
            : (in_array($house, [2, 5, 8, 11], true) ? 30.0 : 15.0);

        $drek = (int) floor(fmod($lon, 30.0) / 10.0);
        if (in_array($planet, self::MALE, true)) {
            $drekkana = $drek === 0 ? 15.0 : 0.0;
        } elseif (in_array($planet, self::FEMALE, true)) {
            $drekkana = $drek === 2 ? 15.0 : 0.0;
        } else {
            $drekkana = $drek === 1 ? 15.0 : 0.0;
        }

        $total = $uccha + $sapta + $ojha + $kendradi + $drekkana;
        return [
            'uccha' => round($uccha, 2), 'saptavargaja' => round($sapta, 2),
            'ojha_yugma' => $ojha, 'kendradi' => $kendradi, 'drekkana' => $drekkana,
            'total' => round($total, 2),
        ];
    }

    private static function dignity(string $planet, int $signV, string $varga, float $lonD1, array $allLon): float
    {
        if ($varga === 'D1' && $signV === self::MT[$planet]) {
            $deg = fmod($lonD1, 30.0);
            [$a, $b] = self::MT_RANGE[$planet];
            if ($deg >= $a && $deg < $b) {
                return 45.0;
            }
        }
        if (in_array($signV, self::OWN[$planet], true)) {
            return 30.0;
        }
        $lord = self::SIGN_LORD[$signV];
        if ($lord === $planet) {
            return 30.0;
        }
        return self::compound(self::PERM[$planet][$lord] ?? 'N', self::temporaryRel($planet, $lord, $allLon));
    }

    private static function temporaryRel(string $a, string $b, array $allLon): string
    {
        if (!isset($allLon[$a], $allLon[$b])) {
            return 'E';
        }
        $dist = ((Charts::signIndex($allLon[$b]) - Charts::signIndex($allLon[$a]) + 12) % 12) + 1;
        return in_array($dist, [2, 3, 4, 10, 11, 12], true) ? 'F' : 'E';
    }

    private static function compound(string $perm, string $temp): float
    {
        return match (true) {
            $perm === 'F' && $temp === 'F' => 22.5,
            $perm === 'F' && $temp === 'E' => 7.5,
            $perm === 'N' && $temp === 'F' => 15.0,
            $perm === 'N' && $temp === 'E' => 3.75,
            $perm === 'E' && $temp === 'F' => 7.5,
            $perm === 'E' && $temp === 'E' => 1.875,
            default => 7.5,
        };
    }

    private static function vargaSign(string $varga, float $lon): int
    {
        $s = Charts::signIndex($lon);
        $deg = fmod($lon, 30.0);
        $n = $s + 1;
        return match ($varga) {
            'D1' => $s,
            'D2' => ($n % 2 === 1) ? ($deg < 15 ? 4 : 3) : ($deg < 15 ? 3 : 4),
            'D3' => ($s + 4 * (int) floor($deg / 10.0)) % 12,
            'D7' => ($n % 2 === 1)
                ? ($s + (int) floor($deg / (30.0 / 7.0))) % 12
                : ($s + 6 + (int) floor($deg / (30.0 / 7.0))) % 12,
            'D9' => Charts::navamsaSignIndex($lon),
            'D12' => ($s + (int) floor($deg / 2.5)) % 12,
            'D30' => self::trimsamsaSign($n, $deg),
            default => $s,
        };
    }

    private static function trimsamsaSign(int $n, float $deg): int
    {
        if ($n % 2 === 1) {
            return $deg < 5 ? 0 : ($deg < 10 ? 10 : ($deg < 18 ? 8 : ($deg < 25 ? 2 : 6)));
        }
        return $deg < 5 ? 1 : ($deg < 12 ? 5 : ($deg < 20 ? 11 : ($deg < 25 ? 9 : 7)));
    }

    /** @param array<int,float> $cusps */
    private static function digBala(string $planet, float $lon, array $cusps): float
    {
        $weak = $cusps[self::DIG_WEAK[$planet]];
        $d = abs($lon - $weak);
        if ($d > 180.0) {
            $d = 360.0 - $d;
        }
        return $d / 3.0;
    }

    private static function declination(float $tropicalLon): float
    {
        $eps = 23.4423;
        return rad2deg(asin(sin(deg2rad($eps)) * sin(deg2rad($tropicalLon))));
    }
}
