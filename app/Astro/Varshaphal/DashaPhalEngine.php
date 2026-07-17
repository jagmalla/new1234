<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Varshaphal;

use AutoBusiness\Astro\Calc\Charts;

/**
 * Tajik-Neelakanthi Dasha-Phal engine (Varshatantra, Dasha-phala-adhyaya).
 *
 * Builds the Patyayini annual dasha (via {@see PatyayiniDasha}) and, for every
 * dasha lord, selects the phal by the lord's Panchavargeeya-bala tier
 * (पूर्ण/मध्य/हीन/नष्ट), applies the उपचय/इतर-स्थान upgrade rule, grades each
 * antardasha by the Vamanacharya table, and computes the additive bhavastha-
 * graha layer (planets-in-houses of the varsha chart — constant for the year).
 *
 * The client picks any dasha (and antardasha) in the pane; everything is
 * computed here so the selection just switches the shown card.
 */
final class DashaPhalEngine
{
    private const PAAP = ['Sun', 'Mars', 'Saturn'];
    private const SHUBH = ['Jupiter', 'Venus', 'Mercury', 'Moon'];

    /**
     * @param array<string,mixed> $vp     Varshaphal::compute output
     * @param array<string,mixed> $natal  natal chart (unused today; kept for parity)
     * @param array<string,mixed> $rules  DashaPhalRepository::load output
     * @param float|null $nowJd           instant to mark the running dasha
     * @return array<string,mixed>
     */
    public static function compute(array $vp, array $natal, array $rules, ?float $nowJd = null): array
    {
        $R = $rules['rules'] ?? [];
        $cfg = $rules['config'] ?? [];
        $tPurna = (float) ($cfg['dasha_bala_purna'] ?? 10);
        $tMadhya = (float) ($cfg['dasha_bala_madhya'] ?? 5);
        $tHina = (float) ($cfg['dasha_bala_hina'] ?? 2.5);

        $vc = $vp['varsha_chart'] ?? [];
        $vpl = $vc['planets'] ?? [];
        $ascSign = (int) ($vc['ascendant']['sign_index'] ?? 0);
        $table = $vp['varshesh']['table'] ?? [];

        $bala = static fn(string $p): float => (float) ($table[$p]['total20'] ?? 0.0);
        $tier = static function (float $b) use ($tPurna, $tMadhya, $tHina): int {
            return $b >= $tPurna ? 0 : ($b >= $tMadhya ? 1 : ($b >= $tHina ? 2 : 3));
        };
        $houseOf = static fn(string $p): ?int => isset($vpl[$p]) ? (int) $vpl[$p]['house'] : null;

        $periods = PatyayiniDasha::compute($vp);
        $running = $nowJd !== null ? PatyayiniDasha::runningIndex($periods, $nowJd) : ['dasha' => 0, 'antar' => 0];

        // Bhavastha-graha layer — fired once (constant for the year).
        $bhav = self::bhavastha($vpl, $ascSign, $R, $bala, $tier);

        foreach ($periods as $i => &$d) {
            $lord = $d['lord'];
            $d['lord_hi'] = DashaPhalData::PLANET_HI[$lord] ?? $lord;

            if ($lord === 'Lagna') {
                $lagnesh = Charts::signLord($ascSign);
                $b = $bala($lagnesh);
                $ti = $tier($b);
                $ruleIdx = $ti === 0 ? 1 : ($ti === 1 ? 2 : 3);   // LAGNA_D_01/02/03
                $rule = $R['LAGNA_D_0' . $ruleIdx] ?? null;
                $d['lagnesh'] = $lagnesh;
                $d['lagnesh_hi'] = DashaPhalData::PLANET_HI[$lagnesh] ?? $lagnesh;
                $d['bala'] = round($b, 2);
                $d['tier'] = $ti;
                $d['tier_hi'] = DashaPhalData::TIERS[$ti]['hi'];
                $d['phal'] = (string) ($rule['phal'] ?? '');
                $d['cat'] = (string) ($rule['cat'] ?? 'mishrit');
                $d['shloka'] = (string) ($rule['sh'] ?? '');
                $d['tone'] = self::tone($d['cat']);
                $d['dreshkana'] = self::dreshkanaNote($ascSign, (float) ($vc['ascendant']['deg_in_sign'] ?? 0.0));
                $d['upgrade'] = null;
            } else {
                $b = $bala($lord);
                $ti = $tier($b);
                $rule = $R[DashaPhalData::PREFIX[$lord] . '_0' . ($ti + 1)] ?? null;
                $d['bala'] = round($b, 2);
                $d['tier'] = $ti;
                $d['tier_hi'] = DashaPhalData::TIERS[$ti]['hi'];
                $d['phal'] = (string) ($rule['phal'] ?? '');
                $d['cat'] = (string) ($rule['cat'] ?? 'mishrit');
                $d['shloka'] = (string) ($rule['sh'] ?? '');
                $d['tone'] = self::tone($d['cat']);
                // उपचय/इतर-स्थान उन्नयन.
                $h = $houseOf($lord);
                $up = DashaPhalData::UPGRADE[$lord] ?? null;
                $isUp = $h !== null && $up !== null
                    && ($up['other'] ? !in_array($h, [6, 8, 12], true) : in_array($h, $up['houses'], true));
                $d['upgrade'] = $isUp ? [
                    'house' => $h,
                    'outcome' => DashaPhalData::TIERS[$ti]['upg'],
                    'text' => 'दशापति भाव ' . $h . ' (उपचय/इतर-स्थान) में — फल एक स्तर ऊपर; प्रभावी फल: '
                        . DashaPhalData::TIERS[$ti]['upg'] . '।',
                ] : null;
            }

            // Antardashas graded by the Vamanacharya table (relative to this dasha lord).
            $shubhSet = DashaPhalData::ANTAR_SHUBH[$lord] ?? [];
            foreach ($d['antars'] as &$a) {
                $al = $a['lord'];
                $a['lord_hi'] = DashaPhalData::PLANET_HI[$al] ?? $al;
                $a['self'] = $al === $lord;
                if ($shubhSet === []) {           // Lagna dasha — table has no row
                    $a['shubh'] = null;
                    $a['tone'] = 'info';
                } elseif ($a['self']) {
                    $a['shubh'] = null;           // पाकपति — बल से विचारें
                    $a['tone'] = 'info';
                } else {
                    $a['shubh'] = in_array($al, $shubhSet, true);
                    $a['tone'] = $a['shubh'] ? 'pos' : 'neg';
                }
                if ($al !== 'Lagna') { $a['bala_tier'] = DashaPhalData::TIERS[$tier($bala($al))]['hi']; }
            }
            unset($a);
        }
        unset($d);

        return [
            'context' => [
                'varshesh' => (string) ($vp['varshesh']['lord'] ?? ''),
                'varshesh_hi' => DashaPhalData::PLANET_HI[$vp['varshesh']['lord'] ?? ''] ?? '',
                'varsha_lagna_sign' => Charts::SIGNS[$ascSign] ?? '',
                'thresholds' => ['purna' => $tPurna, 'madhya' => $tMadhya, 'hina' => $tHina],
            ],
            'periods' => $periods,
            'running' => $running,
            'bhavastha' => $bhav,
            'antar_shubh' => DashaPhalData::ANTAR_SHUBH,
        ];
    }

    /**
     * Additive bhavastha-graha layer (shlokas 50-61): which BHAV_xx rules fire
     * from the varsha chart's planet-in-house placements. Constant for the year.
     *
     * @param array<string,array<string,mixed>> $vpl
     * @return list<array{id:string,house:int,phal:string,cat:string,note:string}>
     */
    private static function bhavastha(array $vpl, int $ascSign, array $R, callable $bala, callable $tier): array
    {
        $house = [];
        foreach ($vpl as $p => $info) { $house[(int) $info['house']][] = $p; }
        $in = static fn(string $p, int $h): bool => isset($vpl[$p]) && (int) $vpl[$p]['house'] === $h;
        $groupIn = static function (int $h, array $group) use ($house): bool {
            foreach ($house[$h] ?? [] as $p) { if (in_array($p, $group, true)) { return true; } }
            return false;
        };
        $paapIn = static fn(int $h): bool => $groupIn($h, self::PAAP);
        $shubhIn = static fn(int $h): bool => $groupIn($h, self::SHUBH);
        // Waxing (पुष्ट/पूर्ण) Moon vs क्षीण Moon.
        $sunLon = (float) ($vpl['Sun']['sidereal_lon'] ?? 0.0);
        $moonLon = (float) ($vpl['Moon']['sidereal_lon'] ?? 0.0);
        $elong = fmod($moonLon - $sunLon + 360.0, 360.0);
        $moonFull = $elong >= 90.0 && $elong <= 270.0;
        $moonWith = static function (int $h, array $group) use ($in, $groupIn): bool {
            return $in('Moon', $h) && $groupIn($h, $group);
        };

        // rule_id => predicate.
        $P = [
            'BHAV_01' => static fn() => $in('Sun', 1) || $in('Mars', 1) || $in('Saturn', 1),
            'BHAV_02' => static fn() => $moonWith(1, self::PAAP),
            'BHAV_03' => static fn() => $in('Moon', 1) && $moonFull && $groupIn(1, ['Jupiter', 'Venus', 'Mercury']),
            'BHAV_04' => static fn() => $in('Mercury', 1) && $in('Venus', 1),
            'BHAV_05' => static fn() => $shubhIn(2),
            'BHAV_06' => static fn() => $paapIn(2),
            'BHAV_07' => static fn() => $in('Saturn', 2),
            'BHAV_08' => static fn() => $paapIn(3),
            'BHAV_09' => static fn() => $shubhIn(3),
            'BHAV_10' => static fn() => $in('Moon', 3),
            'BHAV_11' => static fn() => $moonWith(4, self::PAAP),
            'BHAV_12' => static fn() => $in('Moon', 4) && $moonFull && $groupIn(4, ['Jupiter', 'Venus', 'Mercury']),
            'BHAV_13' => static fn() => $shubhIn(4),
            'BHAV_14' => static fn() => $paapIn(4),
            'BHAV_15' => static fn() => $groupIn(5, ['Mercury', 'Jupiter', 'Venus']) || ($in('Moon', 5) && $moonFull),
            'BHAV_16' => static fn() => $in('Venus', 5),
            'BHAV_17' => static fn() => $paapIn(5) || ($in('Moon', 5) && !$moonFull),
            'BHAV_18' => static fn() => $paapIn(6),
            'BHAV_19' => static fn() => $in('Mars', 6),
            'BHAV_20' => static fn() => $shubhIn(6),
            'BHAV_21' => static fn() => $in('Moon', 6) && !$moonFull && $paapIn(6),
            'BHAV_22' => static fn() => $moonWith(7, self::PAAP),
            'BHAV_23' => static fn() => $paapIn(7),
            'BHAV_24' => static fn() => $shubhIn(7),
            'BHAV_25' => static fn() => $moonWith(8, self::PAAP),
            'BHAV_26' => static fn() => $shubhIn(8),
            'BHAV_27' => static fn() => $paapIn(9),
            'BHAV_28' => static fn() => $in('Sun', 9),
            'BHAV_29' => static fn() => $shubhIn(9),
            'BHAV_30' => static fn() => $in('Saturn', 10),
            'BHAV_31' => static fn() => $in('Sun', 10) && $in('Mars', 10),
            'BHAV_32' => static fn() => $groupIn(10, ['Jupiter', 'Venus', 'Mercury', 'Moon']),
            'BHAV_33' => static function () use ($house, $bala, $tier) {
                foreach ($house[11] ?? [] as $p) { if ($p !== 'Rahu' && $p !== 'Ketu' && $tier($bala($p)) <= 1) { return true; } }
                return false;
            },
            'BHAV_34' => static function () use ($house, $bala, $tier) {
                foreach ($house[11] ?? [] as $p) { if (in_array($p, self::PAAP, true) && $tier($bala($p)) >= 2) { return true; } }
                return false;
            },
            'BHAV_35' => static fn() => $paapIn(12),
            'BHAV_36' => static fn() => $shubhIn(12),
            'BHAV_37' => static fn() => $in('Saturn', 12),
        ];

        // 1-based house for each BHAV rule (from the shloka grouping).
        $hmap = [
            1 => [1, 2, 3, 4], 2 => [5, 6, 7], 3 => [8, 9, 10], 4 => [11, 12, 13, 14],
            5 => [15, 16, 17], 6 => [18, 19, 20, 21], 7 => [22, 23, 24], 8 => [25, 26],
            9 => [27, 28, 29], 10 => [30, 31, 32], 11 => [33, 34], 12 => [35, 36, 37],
        ];
        $houseFor = [];
        foreach ($hmap as $h => $nums) { foreach ($nums as $nn) { $houseFor[sprintf('BHAV_%02d', $nn)] = $h; } }

        $out = [];
        foreach ($P as $id => $pred) {
            if (!$pred()) { continue; }
            $r = $R[$id] ?? null;
            if ($r === null) { continue; }
            $out[] = [
                'id' => $id, 'house' => $houseFor[$id] ?? 0,
                'phal' => (string) $r['phal'], 'cat' => (string) $r['cat'],
                'note' => (string) $r['note'], 'tone' => self::tone((string) $r['cat']),
            ];
        }
        return $out;
    }

    private static function tone(string $cat): string
    {
        return $cat === 'shubh' ? 'pos' : ($cat === 'mishrit' ? 'info' : 'neg');
    }

    /** Lagna-dasha drekkana modifier (shlokas 44-45, impl note 4). */
    private static function dreshkanaNote(int $sign, float $deg): string
    {
        $drek = (int) floor($deg / 10.0) + 1;   // 1..3
        $mod = $sign % 3;                        // 0 chara, 1 sthira, 2 dvisvabhava
        $kind = $mod === 0 ? 'चर' : ($mod === 1 ? 'स्थिर' : 'द्विस्वभाव');
        $gradeMap = match ($mod) {
            0 => [1 => 'शुभ', 2 => 'मध्यम', 3 => 'अधम'],
            1 => [1 => 'अनिष्ट', 2 => 'शुभ', 3 => 'सम'],
            default => [1 => 'अधम', 2 => 'मध्यम', 3 => 'शुभ'],
        };
        $grade = $gradeMap[$drek] ?? 'सम';
        return $kind . ' लग्न · ' . $drek . 'रा द्रेष्काण → ' . $grade . ' (द्रेष्काण-भेद फल)।';
    }
}
