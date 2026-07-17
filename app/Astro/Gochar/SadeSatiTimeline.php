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

    // ===================================================================
    //  FULL TIMELINE — every Sade-Sati & Dhaiyya period (spec खण्ड 3–6)
    // ===================================================================

    private const YEAR_D = 365.25;

    /**
     * Saturn's sign-occupancy windows over [fromJd, toJd], retrograde-merged so
     * each sign is ONE continuous window (first entry → final exit). Boundaries
     * use the LAST forward crossing of each sign-cusp (the permanent ingress).
     *
     * @param callable(float):float $satLonAt Saturn sidereal longitude at a JD
     * @return list<array{sign:int,start:float,end:float}>
     */
    public static function saturnWindows(float $fromJd, float $toJd, callable $satLonAt): array
    {
        $signAt = static fn (float $jd): int => Charts::signIndex(Charts::norm($satLonAt($jd)));
        $step = 15.0;   // Saturn moves < 2.6°/15d, so a sign (30°) is never skipped

        // Refine a forward ingress into $target between a (not target) and b (target).
        $refine = static function (float $a, float $b, int $target) use ($signAt): float {
            for ($i = 0; $i < 22 && ($b - $a) > 0.5; $i++) {
                $m = ($a + $b) / 2.0;
                if ($signAt($m) === $target) { $b = $m; } else { $a = $m; }
            }
            return $b;
        };

        // Collect forward ingresses (sign increments by 1). Retrograde re-entries
        // add a duplicate forward ingress into the same sign a few months later;
        // we keep the LAST one (merging the wobble).
        $ingress = [];
        $prevSign = $signAt($fromJd);
        $prevJd = $fromJd;
        for ($jd = $fromJd + $step; $jd <= $toJd; $jd += $step) {
            $s = $signAt($jd);
            if ($s !== $prevSign) {
                if ($s === ($prevSign + 1) % 12) {
                    $ingress[] = [$refine($prevJd, $jd, $s), $s];
                }
                $prevSign = $s;
            }
            $prevJd = $jd;
        }
        $merged = [];
        foreach ($ingress as $ig) {
            $n = count($merged);
            if ($n > 0 && $merged[$n - 1][1] === $ig[1] && ($ig[0] - $merged[$n - 1][0]) < 730.0) {
                $merged[$n - 1] = $ig;   // same sign within 2 yr → keep the last (permanent)
            } else {
                $merged[] = $ig;
            }
        }

        $windows = [];
        $curSign = $signAt($fromJd);
        $curStart = $fromJd;
        foreach ($merged as $ig) {
            $windows[] = ['sign' => $curSign, 'start' => $curStart, 'end' => $ig[0]];
            $curSign = $ig[1];
            $curStart = $ig[0];
        }
        $windows[] = ['sign' => $curSign, 'start' => $curStart, 'end' => $toJd];
        return $windows;
    }

    /**
     * Every Sade-Sati cycle and Shani-Dhaiyya period from (birth − 3 yr) to
     * (search + 15 yr), classified against BOTH the natal Moon and Lagna, with the
     * 5-layer detail per phase.
     *
     * @param callable(float):float  $satLonAt Saturn sidereal longitude at a JD
     * @param array<int,int>         $satBav   Saturn BAV bindus per sign 0..11
     * @param callable(float):float|null $jupLonAt Jupiter sidereal longitude at a JD
     * @return array<string,mixed>
     */
    public static function fullTimeline(int $moonSign, int $lagnaSign, float $birthJd, float $searchJd, callable $satLonAt, array $satBav, ?callable $jupLonAt = null): array
    {
        $windows = self::saturnWindows($birthJd - 3.0 * self::YEAR_D, $searchJd + 15.0 * self::YEAR_D, $satLonAt);
        // Classical rule (per the client): साढ़े साती is judged from the natal
        // MOON (Saturn transiting 12/1/2 from Chandra); शनि ढैया is judged from
        // the LAGNA (4/8 from Lagna). We take each from its correct reference and
        // merge them into ONE timeline (no Moon/Lagna toggle).
        $moonP = self::classify($windows, $moonSign, $searchJd, $satBav, $jupLonAt);
        $lagnaP = self::classify($windows, $lagnaSign, $searchJd, $satBav, $jupLonAt);
        $merged = [];
        foreach ($moonP as $p) { if ($p['type'] === 'SADE_SATI') { $merged[] = $p; } }
        foreach ($lagnaP as $p) { if ($p['type'] === 'DHAIYA_KANTAK' || $p['type'] === 'DHAIYA_ASHTAM') { $merged[] = $p; } }
        usort($merged, static fn ($a, $b) => $a['start_jd'] <=> $b['start_jd']);
        $cyc = 0;
        foreach ($merged as &$mp) { if ($mp['type'] === 'SADE_SATI') { $mp['cycle'] = ++$cyc; } }
        unset($mp);
        return ['periods' => $merged, 'moon_sign' => $moonSign, 'lagna_sign' => $lagnaSign];
    }

    /**
     * Turn Saturn windows into Sade-Sati cycles (12→1→2 grouped) and standalone
     * Dhaiyya periods, relative to $refSign, each with 5-layer detail.
     *
     * @param list<array{sign:int,start:float,end:float}> $windows
     * @param array<int,int> $satBav
     * @return list<array<string,mixed>>
     */
    private static function classify(array $windows, int $refSign, float $searchJd, array $satBav, ?callable $jupLonAt): array
    {
        $houseOf = static fn (int $sign): int => (($sign - $refSign) % 12 + 12) % 12 + 1;
        $statusOf = static function (float $s, float $e) use ($searchJd): string {
            if ($searchJd < $s) { return 'FUTURE'; }
            if ($searchJd > $e) { return 'PAST'; }
            return 'ACTIVE';
        };

        $periods = [];
        $cycleNo = 0;
        $i = 0;
        $n = count($windows);
        while ($i < $n) {
            $w = $windows[$i];
            $house = $houseOf($w['sign']);

            if (in_array($house, [12, 1, 2], true)) {
                // Gather the consecutive Sade-Sati run into one cycle.
                $run = [];
                while ($i < $n && in_array($houseOf($windows[$i]['sign']), [12, 1, 2], true)) {
                    $run[] = $windows[$i];
                    $i++;
                }
                $cycleNo++;
                $phases = [];
                foreach ($run as $rw) {
                    $ph = [12 => 1, 1 => 2, 2 => 3][$houseOf($rw['sign'])];
                    $phases[] = self::phaseBlock('SADE', $ph, $rw, $refSign, $searchJd, $satBav, $jupLonAt, $statusOf);
                }
                $start = $run[0]['start'];
                $end = $run[count($run) - 1]['end'];
                $periods[] = self::periodWrap('SADE_SATI', $cycleNo, $start, $end, $phases, $refSign, $searchJd, $statusOf);
                continue;
            }

            if ($house === 4 || $house === 8) {
                $cycleNo++;
                $ph = self::phaseBlock($house === 4 ? 'KANTAK' : 'ASHTAM', 0, $w, $refSign, $searchJd, $satBav, $jupLonAt, $statusOf);
                $periods[] = self::periodWrap($house === 4 ? 'DHAIYA_KANTAK' : 'DHAIYA_ASHTAM', $cycleNo, $w['start'], $w['end'], [$ph], $refSign, $searchJd, $statusOf);
            }
            $i++;
        }
        return $periods;
    }

    /** @return array<string,mixed> */
    private static function periodWrap(string $type, int $cycle, float $start, float $end, array $phases, int $refSign, float $searchJd, callable $statusOf): array
    {
        $status = $statusOf($start, $end);
        $percent = null;
        if ($status === 'ACTIVE' && $end > $start) {
            $percent = (int) round((($searchJd - $start) / ($end - $start)) * 100);
            $percent = max(0, min(100, $percent));
        }
        $rel = SadeSatiTimelineData::MOON_REL[$refSign] ?? ['सम', ''];
        return [
            'type' => $type,
            'cycle' => $cycle,
            'start_jd' => $start,
            'end_jd' => $end,
            'status' => $status,
            'percent' => $percent,
            'phases' => $phases,
            'moon_rel' => $rel,
            'remedy' => SadeSatiTimelineData::REMEDY,
        ];
    }

    /** @return array<string,mixed> */
    private static function phaseBlock(string $kind, int $phaseNum, array $w, int $refSign, float $searchJd, array $satBav, ?callable $jupLonAt, callable $statusOf): array
    {
        $sign = (int) $w['sign'];
        $bindu = (int) ($satBav[$sign] ?? 0);
        $bav = SadeSatiTimelineData::bavNote($bindu);

        if ($kind === 'SADE') {
            $base = SadeSatiTimelineData::PHASE[$phaseNum];
            $name = $base['name'];
        } else {
            $base = SadeSatiTimelineData::DHAIYA[$kind === 'KANTAK' ? 4 : 8];
            $name = $base['name'];
        }

        // Layer 4 — Jupiter's concurrent gochar at the phase midpoint.
        $jup = null;
        if ($jupLonAt !== null) {
            $mid = ($w['start'] + $w['end']) / 2.0;
            $jSign = Charts::signIndex(Charts::norm($jupLonAt($mid)));
            $jHouse = (($jSign - $refSign) % 12 + 12) % 12 + 1;
            if (in_array($jHouse, [1, 5, 9], true)) {
                $jup = ['pos', 'गुरु का गोचर चन्द्र/लग्न से ' . $jHouse . 'वें (शुभ) — मानसिक शान्ति व अवसर, कष्ट का असर मन्द।'];
            } elseif (in_array($jHouse, [6, 8, 12], true)) {
                $jup = ['mix', 'गुरु का गोचर ' . $jHouse . 'वें — राहत कम, अतिरिक्त सावधानी आवश्यक।'];
            } else {
                $asp = (($sign - $jSign) % 12 + 12) % 12 + 1;   // Saturn sign's house from Jupiter
                if (in_array($asp, [5, 7, 9], true)) {
                    $jup = ['pos', 'गुरु की शनि-राशि पर दृष्टि — कष्ट में उल्लेखनीय राहत, संकट में सहायता।'];
                }
            }
        }

        $tone = $bindu <= 3 ? 'neg' : ($bindu >= 5 ? 'mix' : 'neg');
        return [
            'phase' => $phaseNum,       // 1/2/3 for Sade-Sati, 0 for Dhaiyya
            'name' => $name,
            'sign' => $sign,
            'start_jd' => $w['start'],
            'end_jd' => $w['end'],
            'status' => $statusOf($w['start'], $w['end']),
            'bindu' => $bindu,
            'tone' => $tone,
            'layers' => [
                'base' => $base['fal'],
                'short' => $base['short'],
                'area' => $base['area'],
                'bav' => $bav,          // [tone, text]
                'jupiter' => $jup,      // [tone, text] | null
            ],
        ];
    }
}
