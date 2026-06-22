<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Vimshottari Dasha — the 120-year planetary period cycle keyed to the Moon's
 * birth nakshatra. This is purely arithmetic (no ephemeris), so it is computed
 * exactly. Produces the Mahadasha sequence with start/end dates, plus the
 * running Mahadasha and its Antardasha (bhukti) sub-period for a given date.
 */
final class VimshottariDasha
{
    /** Lords in nakshatra order and their dasha lengths in years (sum = 120). */
    private const LORDS = ['Ketu', 'Venus', 'Sun', 'Moon', 'Mars', 'Rahu', 'Jupiter', 'Saturn', 'Mercury'];
    private const YEARS = ['Ketu' => 7, 'Venus' => 20, 'Sun' => 6, 'Moon' => 10, 'Mars' => 7,
        'Rahu' => 18, 'Jupiter' => 16, 'Saturn' => 19, 'Mercury' => 17];

    private const SIDEREAL_YEAR_DAYS = 365.25636;

    /**
     * @param float  $moonSiderealLon Moon's sidereal longitude at birth (deg)
     * @param float  $birthJdUt       birth instant (JD UT) — dasha anchor
     * @return array{
     *   balance: array{lord:string, years:float},
     *   mahadashas: list<array{lord:string, start_jd:float, end_jd:float, years:float}>
     * }
     */
    public static function sequence(float $moonSiderealLon, float $birthJdUt): array
    {
        $span = 360.0 / 27.0;
        $n = Charts::norm($moonSiderealLon);
        $nakIdx = (int) floor($n / $span) % 27;
        $within = $n - $nakIdx * $span;       // position inside the nakshatra
        $fraction = $within / $span;          // 0..1 elapsed

        $startLordIdx = $nakIdx % 9;
        $startLord = self::LORDS[$startLordIdx];

        // Balance of the first (birth) mahadasha = unelapsed portion.
        $balanceYears = self::YEARS[$startLord] * (1.0 - $fraction);

        $mahadashas = [];
        $cursorJd = $birthJdUt;
        for ($k = 0; $k < 9; $k++) {
            $lord = self::LORDS[($startLordIdx + $k) % 9];
            $years = $k === 0 ? $balanceYears : (float) self::YEARS[$lord];
            $endJd = $cursorJd + $years * self::SIDEREAL_YEAR_DAYS;
            $mahadashas[] = [
                'lord' => $lord,
                'start_jd' => $cursorJd,
                'end_jd' => $endJd,
                'years' => $years,
            ];
            $cursorJd = $endJd;
        }

        return [
            'balance' => ['lord' => $startLord, 'years' => $balanceYears],
            'mahadashas' => $mahadashas,
        ];
    }

    /**
     * The running Mahadasha + Antardasha at a given instant.
     *
     * @return array{maha: ?string, antar: ?string, maha_end_jd: ?float, antar_end_jd: ?float}
     */
    public static function running(float $moonSiderealLon, float $birthJdUt, float $atJdUt): array
    {
        $seq = self::sequence($moonSiderealLon, $birthJdUt);
        foreach ($seq['mahadashas'] as $md) {
            if ($atJdUt < $md['end_jd']) {
                $antar = self::antardashas($md);
                foreach ($antar as $ad) {
                    if ($atJdUt < $ad['end_jd']) {
                        return [
                            'maha' => $md['lord'], 'antar' => $ad['lord'],
                            'maha_end_jd' => $md['end_jd'], 'antar_end_jd' => $ad['end_jd'],
                        ];
                    }
                }
            }
        }
        return ['maha' => null, 'antar' => null, 'maha_end_jd' => null, 'antar_end_jd' => null];
    }

    /**
     * Antardashas within a mahadasha: each sub-lord's share is proportional to
     * its own dasha years, starting from the mahadasha lord itself.
     *
     * @param array{lord:string, start_jd:float, end_jd:float, years:float} $md
     * @return list<array{lord:string, start_jd:float, end_jd:float}>
     */
    public static function antardashas(array $md): array
    {
        $startIdx = array_search($md['lord'], self::LORDS, true);
        $totalDays = $md['end_jd'] - $md['start_jd'];
        $out = [];
        $cursor = $md['start_jd'];
        for ($k = 0; $k < 9; $k++) {
            $lord = self::LORDS[($startIdx + $k) % 9];
            $portion = $totalDays * (self::YEARS[$lord] / 120.0);
            $end = $cursor + $portion;
            $out[] = ['lord' => $lord, 'start_jd' => $cursor, 'end_jd' => $end];
            $cursor = $end;
        }
        return $out;
    }
}
