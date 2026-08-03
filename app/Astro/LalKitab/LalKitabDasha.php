<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\LalKitab;

/**
 * लाल किताब दशा — the 35-year planetary cycle (ग्रहों का 35-साला चक्र).
 *
 * Lal Kitab does not use Vimshottari (120 years, Moon/nakshatra based). Its
 * timing runs on a FIXED, UNIVERSAL 35-year cycle that begins at birth in the
 * same order for every native and repeats unchanged for life:
 *
 *      शनि 6 · राहु 6 · केतु 3 · गुरु 6 · सूर्य 2 · चन्द्र 1 · शुक्र 3 · मंगल 6 · बुध 2   = 35
 *
 *   चक्र 1 = आयु 1–35 · चक्र 2 = 36–70 · चक्र 3 = 71–105 · …
 *
 * ── Why this class exists (data-correction note) ──────────────────────────
 * The `grah_chakra` sheet's "प्रभाव" year-lists encode this same cycle, but
 * corrupted in two ways: शनि was rotated from the FRONT of the cycle to the
 * BACK, and राहु's span was cut from 6 years to 4 so the arithmetic appeared
 * to close — yielding an invalid 33-year first cycle. Verified: rotating शनि
 * to the back and shortening राहु to 4 reproduces the sheet exactly, and no
 * other planet differs. The owner confirmed the sheet is in error and supplied
 * the authentic order above, which is what this class implements. The sheet's
 * "प्रभाव" column must therefore NOT be used for dasha timing.
 *
 * (`grah_chakra`'s अशुभ / विशेष columns are a separate matter and are still
 * consumed elsewhere as caution-years; they are not touched here.)
 */
final class LalKitabDasha
{
    /** Cycle length in years. */
    public const CYCLE = 35;

    /**
     * The authentic order and duration, from the start of the cycle.
     * @var list<array{0:string,1:int}>  [planet-en, years]
     */
    public const ORDER = [
        ['Saturn', 6],   // शनि   1–6
        ['Rahu', 6],     // राहु   7–12
        ['Ketu', 3],     // केतु  13–15
        ['Jupiter', 6],  // गुरु  16–21
        ['Sun', 2],      // सूर्य 22–23
        ['Moon', 1],     // चन्द्र 24
        ['Venus', 3],    // शुक्र 25–27
        ['Mars', 6],     // मंगल 28–33
        ['Mercury', 2],  // बुध  34–35
    ];

    /**
     * One cycle's periods as [planet, fromYear, toYear] with years 1..35.
     * @return list<array{planet:string,from:int,to:int,years:int}>
     */
    public static function template(): array
    {
        $out = [];
        $y = 1;
        foreach (self::ORDER as [$p, $d]) {
            $out[] = ['planet' => $p, 'from' => $y, 'to' => $y + $d - 1, 'years' => $d];
            $y += $d;
        }
        return $out;
    }

    /**
     * Which planet rules a given age, plus that period's span and cycle number.
     *
     * @param int $age native's age in years (>= 0)
     * @return array{planet:string,hi:string,from:int,to:int,years:int,cycle:int,
     *               index:int,elapsed:int,remaining:int}
     */
    public static function at(int $age): array
    {
        if ($age < 0) { $age = 0; }
        // Year-of-life 1 covers age 0 (birth → first birthday).
        $year = $age + 1;
        $cycle = (int) floor(($year - 1) / self::CYCLE) + 1;
        $base = ($cycle - 1) * self::CYCLE;          // years already completed in whole cycles
        $inCycle = $year - $base;                     // 1..35

        $y = 1;
        foreach (self::template() as $i => $t) {
            if ($inCycle >= $t['from'] && $inCycle <= $t['to']) {
                $from = $base + $t['from'];
                $to = $base + $t['to'];
                return [
                    'planet'    => $t['planet'],
                    'hi'        => LalKitabData::planetHi($t['planet']),
                    'from'      => $from,
                    'to'        => $to,
                    'years'     => $t['years'],
                    'cycle'     => $cycle,
                    'index'     => $i,
                    'elapsed'   => $year - $from,
                    'remaining' => $to - $year,
                ];
            }
            $y += $t['years'];
        }
        // Unreachable while ORDER sums to CYCLE.
        $t = self::template()[0];
        return ['planet' => $t['planet'], 'hi' => LalKitabData::planetHi($t['planet']),
            'from' => $base + 1, 'to' => $base + $t['years'], 'years' => $t['years'],
            'cycle' => $cycle, 'index' => 0, 'elapsed' => 0, 'remaining' => $t['years'] - 1];
    }

    /**
     * The dasha sequence covering a span of life-years, in order.
     *
     * @param int $fromAge first age to cover
     * @param int $toAge   last age to cover
     * @return list<array{planet:string,hi:string,from:int,to:int,years:int,cycle:int,active:bool}>
     */
    public static function timeline(int $fromAge, int $toAge, ?int $activeAge = null): array
    {
        if ($toAge < $fromAge) { $toAge = $fromAge; }
        $out = [];
        $seen = [];
        for ($a = max(0, $fromAge); $a <= $toAge; $a++) {
            $d = self::at($a);
            $key = $d['from'] . '-' . $d['to'];
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $out[] = [
                'planet' => $d['planet'],
                'hi'     => $d['hi'],
                'from'   => $d['from'],
                'to'     => $d['to'],
                'years'  => $d['years'],
                'cycle'  => $d['cycle'],
                'active' => $activeAge !== null && ($activeAge + 1) >= $d['from'] && ($activeAge + 1) <= $d['to'],
            ];
        }
        return $out;
    }

    /**
     * Every period of one whole cycle for a native of the given age, so the
     * reader can see the full 35-year wheel they are currently inside.
     *
     * @return list<array{planet:string,hi:string,from:int,to:int,years:int,active:bool}>
     */
    public static function currentCycle(int $age): array
    {
        $now = self::at($age);
        $base = ($now['cycle'] - 1) * self::CYCLE;
        $year = $age + 1;
        $out = [];
        foreach (self::template() as $t) {
            $from = $base + $t['from'];
            $to = $base + $t['to'];
            $out[] = [
                'planet' => $t['planet'],
                'hi'     => LalKitabData::planetHi($t['planet']),
                'from'   => $from,
                'to'     => $to,
                'years'  => $t['years'],
                'active' => $year >= $from && $year <= $to,
            ];
        }
        return $out;
    }
}
