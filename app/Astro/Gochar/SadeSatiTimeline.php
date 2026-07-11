<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Gochar;

use AutoBusiness\Astro\Calc\Charts;

/**
 * Sade-Sati / Dhaiyya TIMELINE — current status PLUS the start/end dates of the
 * window, from the natal Moon sign and transit Saturn.
 *
 * SadeSatiEngine reads a single instant (is Saturn in a Sade-Sati house now?).
 * This helper brackets the sign-INGRESS dates of the slow Saturn so the birth
 * summary can show the running window's dates, or — when none is active — the
 * NEXT upcoming Sade-Sati / Dhaiyya with its dates.
 *
 * Houses from the Moon: 12 · 1 · 2 = Sade-Sati (phase 1 आरम्भ / 2 शिखर /
 * 3 उतरती); 4 or 8 = Dhaiyya. Saturn's mean motion is ~0.13°/day, so a coarse
 * 120-day scan never skips a multi-year window, and each needed boundary is then
 * binary-searched to ~1-day precision.
 */
final class SadeSatiTimeline
{
    private const YEAR = 365.25;

    /**
     * @param int   $moonSign natal Moon sign index (0..11)
     * @param float $nowJd    current instant (JD UT)
     * @param callable(float):float $satLonAt Saturn sidereal longitude (deg) at a JD
     * @return array<string,mixed>  ['found'=>false] when nothing within horizon
     */
    public static function compute(int $moonSign, float $nowJd, callable $satLonAt): array
    {
        $houseAt = static function (float $jd) use ($moonSign, $satLonAt): int {
            $sign = Charts::signIndex(Charts::norm($satLonAt($jd)));
            return (($sign - $moonSign) % 12 + 12) % 12 + 1;
        };
        $kindOf = static function (int $h): ?string {
            if (in_array($h, [12, 1, 2], true)) { return 'sadesati'; }
            if (in_array($h, [4, 8], true)) { return 'dhaiya'; }
            return null;
        };

        // Binary search a single boundary in [lo,hi] where pred flips; returns the
        // JD just inside the side where pred differs from pred(lo).
        $bisect = static function (float $lo, float $hi, callable $pred): float {
            $flo = $pred($lo);
            for ($i = 0; $i < 26 && ($hi - $lo) > 0.5; $i++) {
                $mid = ($lo + $hi) / 2.0;
                if ($pred($mid) === $flo) { $lo = $mid; } else { $hi = $mid; }
            }
            return $hi;
        };

        $nowHouse = $houseAt($nowJd);
        $nowKind = $kindOf($nowHouse);

        if ($nowKind !== null) {
            // ACTIVE — bracket the running window.
            $inWin = static function (float $jd) use ($houseAt, $kindOf, $nowKind, $nowHouse): bool {
                return $nowKind === 'sadesati'
                    ? $kindOf($houseAt($jd)) === 'sadesati'
                    : $houseAt($jd) === $nowHouse;   // Dhaiyya = one specific sign
            };
            $span = $nowKind === 'sadesati' ? 8.5 : 3.2;
            $startLo = $nowJd - $span * self::YEAR;
            $start = $inWin($startLo) ? $startLo : $bisect($startLo, $nowJd, $inWin);
            $endHi = $nowJd + $span * self::YEAR;
            $endNot = static fn (float $jd): bool => !$inWin($jd);
            $end = $endNot($endHi) ? $bisect($nowJd, $endHi, $endNot) : $endHi;
            return self::result(true, $nowKind, $nowHouse, $start, $end, $satLonAt);
        }

        // NOT ACTIVE — scan forward for the next notable entry (next window is at
        // most ~7.5 yr away, so a 12-yr horizon is safe).
        $step = 120.0;
        $limit = $nowJd + 12.0 * self::YEAR;
        $prev = $nowJd;
        for ($jd = $nowJd + $step; $jd <= $limit; $jd += $step) {
            $k = $kindOf($houseAt($jd));
            if ($k === null) { $prev = $jd; continue; }
            $inK = static fn (float $j): bool => $kindOf($houseAt($j)) === $k;
            $start = $bisect($prev, $jd, $inK);
            $endHi = $start + ($k === 'sadesati' ? 8.5 : 3.2) * self::YEAR;
            $endNot = static fn (float $j): bool => !$inK($j);
            $end = $endNot($endHi) ? $bisect($start, $endHi, $endNot) : $endHi;
            $house = $houseAt($start + 5.0);
            return self::result(false, $k, $house, $start, $end, $satLonAt);
        }

        return ['found' => false];
    }

    /** @return array<string,mixed> */
    private static function result(bool $active, string $kind, int $house, float $start, float $end, callable $satLonAt): array
    {
        $phase = $kind === 'sadesati' ? ([12 => 1, 1 => 2, 2 => 3][$house] ?? null) : null;
        $probe = $active ? ($start + $end) / 2.0 : $start + 5.0;
        return [
            'found' => true,
            'active' => $active,
            'kind' => $kind,                 // 'sadesati' | 'dhaiya'
            'house' => $house,               // Saturn's house from the Moon
            'phase' => $phase,               // 1|2|3 (sadesati) or null
            'sign_index' => Charts::signIndex(Charts::norm($satLonAt($probe))),
            'start_jd' => $start,
            'end_jd' => $end,
        ];
    }
}
