<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

use AutoBusiness\Astro\Time\JulianDay;

/**
 * Varshaphal — the Vedic annual (Tajik) chart, used by the Year Prediction agent
 * (Module 3c, Tajik Neelkanthi book).
 *
 * It finds the solar-return instant (Varsha Pravesh) — when the Sun returns to
 * its natal sidereal longitude in the requested year — casts a chart for that
 * moment at the birth place, and derives the Muntha (progressed point), the
 * Varsha Lagna lord, the Muntha lord, and the Mudda dasha (the annual dasha,
 * Vimshottari proportions compressed into one year).
 */
final class Varshaphal
{
    private const SOLAR_DEG_PER_DAY = 0.985647;

    /**
     * @return array<string,mixed>
     */
    public static function compute(
        CalculationEngine $engine,
        array $natalChart,
        int $birthYear,
        int $birthMonth,
        int $birthDay,
        int $birthHour,
        int $birthMinute,
        float $tzOffsetHours,
        float $lat,
        float $lonEast,
        int $forYear
    ): array {
        $natalSunSid = (float) $natalChart['planets']['Sun']['sidereal_lon'];

        // Solar return: Newton iteration on (sidereal Sun - natal Sun).
        $jd = JulianDay::fromGregorian($forYear, $birthMonth, $birthDay, $birthHour, $birthMinute, 0.0, $tzOffsetHours);
        for ($i = 0; $i < 10; $i++) {
            $sun = $engine->keySigns($jd, $lat, $lonEast)['sun'];
            $diff = self::signed($sun - $natalSunSid);
            if (abs($diff) < 1e-5) {
                break;
            }
            $jd -= $diff / self::SOLAR_DEG_PER_DAY;
        }

        // Cast the annual chart at the return moment.
        $varshaChart = $engine->computeChart($jd, $lat, $lonEast);

        $natalAscSign = (int) $natalChart['ascendant']['sign_index'];
        $age = $forYear - $birthYear;
        $munthaSign = (($natalAscSign + $age) % 12 + 12) % 12;

        $varshaLagnaSign = (int) $varshaChart['ascendant']['sign_index'];

        // Mudda dasha for the year, from the annual Moon's nakshatra.
        $mudda = self::muddaDasha((float) $varshaChart['planets']['Moon']['sidereal_lon'], $jd);

        return [
            'solar_return_jd' => $jd,
            'age_completed' => $age,
            'varsha_chart' => $varshaChart,
            'muntha' => [
                'sign' => Charts::SIGNS[$munthaSign],
                'lord' => Charts::signLord($munthaSign),
            ],
            'varsha_lagna' => [
                'sign' => Charts::SIGNS[$varshaLagnaSign],
                'lord' => Charts::signLord($varshaLagnaSign),
            ],
            // Varshesh (year lord) selection has five classical office-bearers;
            // here we surface the strongest candidates (Lagna lord + Muntha lord)
            // for the Tajik agent to interpret. Full Panchadhikari selection is a
            // documented refinement.
            'varshesh_candidates' => [
                'varsha_lagna_lord' => Charts::signLord($varshaLagnaSign),
                'muntha_lord' => Charts::signLord($munthaSign),
            ],
            'mudda_dasha' => $mudda,
        ];
    }

    /**
     * Mudda (annual) dasha: the year is divided among the nine planets in
     * Vimshottari proportions, starting from the lord of the annual Moon's
     * nakshatra.
     *
     * @return list<array{lord:string, start_jd:float, end_jd:float, days:float}>
     */
    private static function muddaDasha(float $moonSid, float $startJd): array
    {
        $lords = ['Ketu', 'Venus', 'Sun', 'Moon', 'Mars', 'Rahu', 'Jupiter', 'Saturn', 'Mercury'];
        $years = ['Ketu' => 7, 'Venus' => 20, 'Sun' => 6, 'Moon' => 10, 'Mars' => 7,
            'Rahu' => 18, 'Jupiter' => 16, 'Saturn' => 19, 'Mercury' => 17];

        $span = 360.0 / 27.0;
        $nakIdx = (int) floor(Charts::norm($moonSid) / $span) % 27;
        $startLordIdx = $nakIdx % 9;

        $yearDays = 365.25636; // one sidereal year shared across 120 dasha-years
        $out = [];
        $cursor = $startJd;
        for ($k = 0; $k < 9; $k++) {
            $lord = $lords[($startLordIdx + $k) % 9];
            $days = $yearDays * ($years[$lord] / 120.0);
            $end = $cursor + $days;
            $out[] = ['lord' => $lord, 'start_jd' => $cursor, 'end_jd' => $end, 'days' => round($days, 2)];
            $cursor = $end;
        }
        return $out;
    }

    private static function signed(float $deg): float
    {
        $d = fmod($deg + 180.0, 360.0);
        if ($d < 0) {
            $d += 360.0;
        }
        return $d - 180.0;
    }
}
