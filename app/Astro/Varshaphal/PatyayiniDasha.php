<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Varshaphal;

/**
 * Patyayini (Patyamsa) annual dasha — Tajik-Neelakanthi Dashaphaladhyaya,
 * shlokas 1-4.
 *
 * Take the bhuktamsha (degrees-within-sign, 0-30) of the varsha Lagna and the
 * seven planets; order them by DESCENDING amsha = the dasha order. The
 * shuddhamsha of each is its amsha minus the next (lower) one; the smallest
 * keeps its own amsha — so the shuddhamsha telescope-sum equals the greatest
 * amsha. Each dasha span = shuddhamsha / (greatest amsha) × year-length.
 * Antardashas divide each dasha the same way, starting with the dasha lord
 * (pakapati) then the remaining lords in dasha order (shloka 46-47).
 */
final class PatyayiniDasha
{
    private const BODIES = ['Lagna', 'Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
    private const YEAR_DAYS = 365.2425;

    /**
     * @param array<string,mixed> $vp Varshaphal::compute output
     * @return list<array{lord:string, amsha:float, shuddhamsha:float,
     *   start_jd:float, end_jd:float, days:float, antars:list<array<string,mixed>>}>
     */
    public static function compute(array $vp): array
    {
        $vc = $vp['varsha_chart'] ?? [];
        $start = (float) ($vp['solar_return_jd'] ?? 0.0);

        // Bhuktamsha (deg-in-sign) of Lagna + 7 planets.
        $amsha = ['Lagna' => (float) ($vc['ascendant']['deg_in_sign'] ?? 0.0)];
        foreach (['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'] as $p) {
            $amsha[$p] = (float) ($vc['planets'][$p]['deg_in_sign'] ?? 0.0);
        }

        // Descending amsha = dasha order (tie → keep BODIES order for stability).
        $order = self::BODIES;
        usort($order, static function (string $a, string $b) use ($amsha): int {
            if ($amsha[$b] <=> $amsha[$a]) { return $amsha[$b] <=> $amsha[$a]; }
            return array_search($a, self::BODIES, true) <=> array_search($b, self::BODIES, true);
        });

        // Shuddhamsha (telescoping) + total = greatest amsha.
        $n = count($order);
        $shud = [];
        for ($i = 0; $i < $n; $i++) {
            $shud[$order[$i]] = $i + 1 < $n ? $amsha[$order[$i]] - $amsha[$order[$i + 1]] : $amsha[$order[$i]];
        }
        $total = array_sum($shud);
        if ($total <= 0.0) { $total = 1.0; }

        $spanDays = [];
        foreach ($order as $p) { $spanDays[$p] = $shud[$p] / $total * self::YEAR_DAYS; }

        $out = [];
        $cursor = $start;
        foreach ($order as $idx => $lord) {
            $d = $spanDays[$lord];
            $dEnd = $cursor + $d;
            // Antardashas: start at the dasha lord, then the rest in dasha order.
            $antarOrder = array_merge(array_slice($order, $idx), array_slice($order, 0, $idx));
            $antars = [];
            $ac = $cursor;
            foreach ($antarOrder as $al) {
                $ad = $shud[$al] / $total * $d;   // same proportion within the dasha
                $antars[] = ['lord' => $al, 'start_jd' => $ac, 'end_jd' => $ac + $ad, 'days' => $ad];
                $ac += $ad;
            }
            $out[] = [
                'lord' => $lord, 'amsha' => round($amsha[$lord], 3), 'shuddhamsha' => round($shud[$lord], 3),
                'start_jd' => $cursor, 'end_jd' => $dEnd, 'days' => $d, 'antars' => $antars,
            ];
            $cursor = $dEnd;
        }
        return $out;
    }

    /**
     * Index of the dasha (and antar) running at $jd, or the first if out of range.
     *
     * @param list<array<string,mixed>> $periods
     * @return array{dasha:int, antar:int}
     */
    public static function runningIndex(array $periods, float $jd): array
    {
        foreach ($periods as $di => $d) {
            if ($jd >= (float) $d['start_jd'] && $jd < (float) $d['end_jd']) {
                foreach ($d['antars'] as $ai => $a) {
                    if ($jd >= (float) $a['start_jd'] && $jd < (float) $a['end_jd']) {
                        return ['dasha' => $di, 'antar' => $ai];
                    }
                }
                return ['dasha' => $di, 'antar' => 0];
            }
        }
        return ['dasha' => 0, 'antar' => 0];
    }
}
