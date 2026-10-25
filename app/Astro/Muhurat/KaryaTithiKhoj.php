<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Muhurat\PanchangNiyamData as P;
use AutoBusiness\Astro\Time\JulianDay;

/**
 * 🔍 कार्य-मुहूर्त: तिथि-खोज (अवधि में शुभ दिन + हर दिन की शुभ समय-खिड़कियाँ)
 * और एक क्षण की पूरी जाँच — मालिक की कार्यपुस्तिका के 87 कार्यों के नियमों से।
 *
 * हर दिन सूर्योदय से अगले सूर्योदय तक टुकड़ों में बँटता है ({@see KaryaKaal}),
 * हर टुकड़े पर {@see KaryaNirnay} नियम लगाता है, और लगातार शुभ/मध्यम टुकड़े
 * जुड़कर समय-खिड़कियाँ बनते हैं। दिन का दर्जा उसकी सबसे अच्छी खिड़की का दर्जा।
 */
final class KaryaTithiKhoj
{
    public const MAX_DAYS = 400;
    private const MIN_WIN = 15.0 / 1440.0;   // 15 मिनट से छोटी खिड़की नहीं

    // ───────────────────────── कार्य की तैयारी ─────────────────────────

    /** कार्य + उसकी व्याख्या + चुने विकल्प → judge के लिए एक सपाट रूप। */
    public static function prepare(string $id, array $opts): ?array
    {
        $d = KaryaNiyamData::TASKS[$id] ?? null;
        if ($d === null) { return null; }
        $v = KaryaNiyamVidhi::rule($id);
        $t = $v + [
            't+' => $d['t+'], 't-' => $d['t-'], 'v+' => $d['v+'], 'v-' => $d['v-'],
            'n+' => $d['n+'], 'n-' => $d['n-'], 'l+' => $d['l+'],
        ];
        $t['id'] = $id;
        $t['nature'] = $v['nature'] ?? 'mangal';
        $t['options'] = $v['options'] ?? [];
        $t['catSoft'] = array_values(array_diff(KaryaNiyamVidhi::CATEGORY_SOFT[$d['c']] ?? [], $v['varjit'] ?? [], $v['soft'] ?? []));
        $map = ['urdhva_bonus' => 'urdhva', 'adho_bonus' => 'adho', 'tiryak_bonus' => 'tiryak', 'nak_bonus' => 'nakBonus',
            'moon_char_bonus' => 'moonCharBonus', 'shanti_naks' => 'shantiNaks', 'amavasya_good' => 'amavasyaGood',
            'purnima_good' => 'purnimaGood', 'vastu_naag' => 'vastuNaag'];
        foreach ($map as $from => $to) { if (isset($v[$from])) { $t[$to] = $v[$from]; } }
        $per = $v['personal'] ?? [];
        $t['tribal'] = !empty($per['tribal']);
        $t['guruBal'] = !empty($per['guru_bal']);
        $t['taraMitra'] = !empty($per['tara_mitra']);
        if (in_array($id, ['5.03', '6.01'], true)) { $t['diwaliOk'] = true; }

        // विकल्प
        $o = static fn (string $k): string => (string) ($opts[$k] ?? '');
        $has = static fn (string $k): bool => in_array($k, $t['options'], true);
        if ($has('kray') && $o('kray') === 'sell') {
            $sell = $v['sell_naks'] ?? [];
            $t['n+'] = $sell;
            $t['n-'] = array_values(array_diff($t['n-'], $sell));
        }
        if ($has('griha') && in_array($o('griha'), ['old', 'return', 'rent'], true)) {
            $t['maas']['hard'] = false;
            $t['maas']['months_bad'] = [];
            $t['soft'] = array_values(array_unique(array_merge($t['soft'] ?? [], array_intersect($t['varjit'] ?? [], KaryaNiyamVidhi::M6))));
            $t['varjit'] = array_values(array_diff($t['varjit'] ?? [], KaryaNiyamVidhi::M6));
            if ($o('griha') !== 'rent') { $t['n+'] = array_values(array_unique(array_merge($t['n+'], $v['old_naks'] ?? []))); }
            if ($o('griha') === 'rent') { $t['lagna'] = ['moon_hard' => true]; }
        }
        if ($has('dhatu')) {
            $t['vaarBonus'] = $o('dhatu') === 'silver' ? [2 => 'चाँदी हेतु सोमवार', 6 => 'चाँदी हेतु शुक्रवार'] : [1 => 'सोने हेतु रविवार', 5 => 'सोने हेतु गुरुवार'];
        }
        if ($has('fasal') && $o('fasal') === 'kand') { $t['paksha'] = 'krishna'; }
        if ($has('naukri') && $o('naukri') === 'sarkari') { $t['vaarBonus'] = [1 => 'सरकारी पद हेतु रविवार']; $t['hora'] = ['Sun']; }
        if ($has('vyapar') && $o('vyapar') === 'loha') { $t['v-'] = array_values(array_diff($t['v-'], [7])); $t['vaarBonus'] = [7 => 'लोहा/तेल/मशीनरी हेतु शनिवार']; }
        if ($has('havan') && $o('havan') === 'nitya') { $t['agnivasExempt'] = 'नित्य/संस्कार/पर्व हवन'; }
        if ($has('vrat')) {
            if ($o('vrat') === 'udyapan') { $t['paksha'] = 'shukla_pref'; $t['agnivas'] = true; }
            else { $t['paksha'] = 'shukla'; }
        }
        if ($has('devata') && isset(KaryaNiyamVidhi::DEVATA_TITHI[$o('devata')])) {
            $t['devata'] = $o('devata');
            $t['devataTithi'] = KaryaNiyamVidhi::DEVATA_TITHI[$o('devata')];
        }
        if ($has('graha') && isset(KaryaKaal::HI[$o('graha')])) {
            $g = $o('graha');
            $t['hora'] = [$g];
            $vv = array_search($g, KaryaKaal::VAAR_LORD, true);
            if ($vv !== false) { $t['vaarBonus'] = [$vv => KaryaKaal::HI[$g] . ' का वार']; }
        }
        if ($has('dastavej')) { $t['pushkar'] = $o('dastavej') === 'vasiyat' ? '' : 'good'; }
        return $t;
    }

    /** विकल्प-मान साफ़ करें (केवल परिभाषित मान)। */
    public static function cleanOpts(string $id, array $in): array
    {
        $out = [];
        foreach (KaryaNiyamVidhi::rule($id)['options'] ?? [] as $k) {
            $val = (string) ($in[$k] ?? '');
            $choices = KaryaNiyamVidhi::OPTIONS[$k][1] ?? [];
            if (array_key_exists($val, $choices)) { $out[$k] = $val; }
            else { $out[$k] = (string) array_key_first($choices); }
        }
        return $out;
    }

    // ───────────────────────── पर्व ─────────────────────────

    /** @return array<string, string> आज के पर्व/आवर्ती योग — कूट => नाम */
    public static function festivals(KaryaKaal $kk, array $day): array
    {
        $at = ['rise' => $day['rise'] + 1 / 1440, 'pradosh' => $day['set'] + 1 / 24, 'night' => ($day['set'] + $day['next']) / 2];
        $info = [];
        foreach ($at as $k => $jd) {
            $p = $kk->panch($jd);
            $info[$k] = [$kk->maas($jd, $p['tithi']), $p];
        }
        $out = [];
        foreach (KaryaNiyamVidhi::FESTIVALS as $key => [$name, $pm, $pk, $tps, $when]) {
            [$m, $p] = $info[$when];
            if ($m['adhik']) { continue; }
            $pkNow = $p['tithi'] <= 15 ? 's' : 'k';
            $tpNow = ($p['tithi'] - 1) % 15 + 1;
            if ($m['pm'] === $pm && $pkNow === $pk && in_array($tpNow, $tps, true)) { $out[$key] = $name; }
        }
        $R = KaryaNiyamVidhi::RECURRING;
        [$m, $p] = $info['rise'];
        $vaar = $day['vaar'];
        $tpPr = ($info['pradosh'][1]['tithi'] - 1) % 15 + 1;
        if ($tpPr === 13) { $out['pradosh'] = $R['pradosh']; if ($vaar === 7) { $out['shani_pradosh'] = $R['shani_pradosh']; } }
        if ($info['night'][1]['tithi'] === 29) { $out['masik_shivratri'] = $R['masik_shivratri']; }
        if ($p['tithi'] === 30) { $out['amavasya'] = $R['amavasya']; if ($vaar === 7) { $out['shani_amavasya'] = $R['shani_amavasya']; } }
        if ($p['tithi'] === 15) { $out['purnima'] = $R['purnima']; }
        if (!$m['adhik'] && $m['pm'] === 4) { $out['shravan'] = $R['shravan']; if ($vaar === 2) { $out['shravan_somvar'] = $R['shravan_somvar']; } }
        foreach ($day['iv']['nak'] as [$nk]) {
            if ($nk === 8 && $vaar === 5) { $out['pushya_guru'] = $R['pushya_guru']; }
            if ($nk === 8 && $vaar === 1) { $out['pushya_ravi'] = $R['pushya_ravi']; }
        }
        foreach ($day['eclipseNear'] as $e) { if ($e['days'] === 0) { $out['grahan'] = ($e['type'] === 'surya' ? 'सूर्य' : 'चंद्र') . ' ग्रहण'; } }
        foreach ($day['sankranti'] as [$tk, $sg]) {
            if ($tk >= $day['rise'] && $tk < $day['next']) {
                if ($sg === 9) { $out['makar'] = $R['makar']; }
                if ($sg === 0) { $out['baisakhi'] = $R['baisakhi']; }
            }
        }
        if (!$m['adhik'] && $m['am'] === 5 && $p['tithi'] >= 15) { $out['pitru_paksha'] = $R['pitru_paksha']; }
        if (!$m['adhik'] && $m['am'] === 5 && $p['tithi'] === 30) { $out['sarvapitru'] = $R['sarvapitru']; }
        return $out;
    }

    // ───────────────────────── जन्म-कुंडली (व्यक्तिगत बल) ─────────────────────────

    /** @return array{moonSign:int, nak:int, ascSign:int, am:int, gender:string, birthJd:float}|null */
    public static function natal(CalculationEngine $eng, array $b): ?array
    {
        if (empty($b['date']) || !preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', (string) $b['date'], $dm)) { return null; }
        [$hh, $mm] = array_map('intval', explode(':', (string) ($b['time'] ?? '12:00')) + [0, 0]);
        $tz = (float) ($b['tz'] ?? 5.5);
        $jd = JulianDay::fromGregorian((int) $dm[1], (int) $dm[2], (int) $dm[3], $hh, $mm, 0.0, $tz);
        $lat = (float) ($b['lat'] ?? 28.61); $lon = (float) ($b['lon'] ?? 77.21);
        $p = $eng->siderealPositions($jd);
        $moon = $p['Moon']['lon'];
        // जन्म का अमान्त मास — पिछली अमावस्या पर सूर्य की राशि से
        $el = KaryaKaal::norm($moon - $p['Sun']['lon']);
        $nm = $jd - $el / 12.19;
        for ($i = 0; $i < 6; $i++) {
            $q = $eng->siderealPositions($nm);
            $nm -= KaryaKaal::wrap180($q['Moon']['lon'] - $q['Sun']['lon']) / 12.19;
        }
        $sunAtNm = (int) floor($eng->siderealPositions($nm)['Sun']['lon'] / 30.0) % 12;
        $g = strtolower((string) ($b['gender'] ?? ''));
        return [
            'moonSign' => (int) floor($moon / 30.0) % 12,
            'nak' => min(27, (int) floor($moon / KaryaKaal::NAK) + 1),
            'ascSign' => (int) floor($eng->ascendantSidereal($jd, $lat, $lon) / 30.0) % 12,
            'am' => ($sunAtNm + 1) % 12,
            'gender' => str_starts_with($g, 'f') ? 'f' : (str_starts_with($g, 'm') ? 'm' : ''),
            'birthJd' => $jd,
        ];
    }

    // ───────────────────────── खोज ─────────────────────────

    /**
     * @param array{0:int,1:int,2:int} $from [Y, M, D]
     * @param array{lat: float, lon: float, tz: float, zone: ?string} $place
     */
    public static function scan(CalculationEngine $eng, string $id, array $from, array $to, array $place, array $opts, ?array $natal, bool $personal): array
    {
        $t = self::prepare($id, $opts);
        if ($t === null) { return ['ok' => false, 'error' => 'अज्ञात कार्य।']; }
        $zone = self::zone($place['zone'] ?? null);
        $a = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $from[0], $from[1], $from[2]));
        $b = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $to[0], $to[1], $to[2]));
        if ($b < $a) { return ['ok' => false, 'error' => 'अन्तिम तिथि प्रारम्भ-तिथि से पहले है।']; }
        $total = (int) $a->diff($b)->days + 1;
        $days = min($total, self::MAX_DAYS);
        $tz0 = (float) $place['tz'];
        $jdA = JulianDay::fromGregorian($from[0], $from[1], $from[2], 0, 0, 0.0, $tz0);
        $kk = new KaryaKaal($eng, (float) $place['lat'], (float) $place['lon'], $zone, $tz0, $jdA - 1.0, $jdA + $days + 2.0);
        $need = ['hora' => !empty($t['hora']), 'chogh' => !empty($t['chogh']), 'ritu' => str_starts_with((string) ($t['time'] ?? ''), 'ritu:')];

        $rows = [];
        $count = ['शुभ' => 0, 'मध्यम' => 0, 'अशुभ' => 0];
        for ($i = 0; $i < $days; $i++) {
            $dt = $a->modify('+' . $i . ' days');
            $day = $kk->day((int) $dt->format('Y'), (int) $dt->format('n'), (int) $dt->format('j'), $need);
            $row = self::judgeDay($kk, $t, $day, $natal, $personal, $opts);
            $count[$row['grade']]++;
            $rows[] = $row;
        }
        return [
            'ok' => true, 'task' => $id, 'task_name' => KaryaNiyamData::TASKS[$id]['name'],
            'rows' => $rows, 'count' => $count,
            'capped' => $total > $days, 'total' => $total, 'scanned' => $days, 'max_days' => self::MAX_DAYS,
            'place' => ['lat' => $place['lat'], 'lon' => $place['lon'], 'tz' => $tz0, 'zone' => $zone?->getName()],
            'personal' => $natal !== null && $personal,
        ];
    }

    /** एक दिन — टुकड़ों पर निर्णय → समय-खिड़कियाँ → दिन का दर्जा। */
    private static function judgeDay(KaryaKaal $kk, array $t, array $day, ?array $natal, bool $personal, array $opts, bool $keepSegs = false): array
    {
        $fest = self::festivals($kk, $day);
        $ctx = ['fest' => $fest, 'eclipses' => $kk->eclipses(), 'natal' => $natal, 'personal' => $personal, 'opts' => $opts];
        $segs = [];
        foreach ($day['segs'] as [$a, $b]) {
            $f = $kk->facts($day, ($a + $b) / 2.0);
            $segs[] = [$a, $b, KaryaNirnay::judge($t, $f, $day, $ctx), $f];
        }
        // लगातार शुभ/मध्यम टुकड़े → खिड़की
        $wins = [];
        $cur = null;
        $badDur = [];
        foreach ($segs as [$a, $b, $j, $f]) {
            // पंचांग शुद्ध पर केवल लग्न-शुद्धि अपूर्ण — दिन छिपे नहीं, "मध्यम" दिखे (लग्न
            // की कमी साफ़ लिखी), ताकि ज्योतिषी लग्न देखकर निर्णय ले सके।
            if ($j['lagnaOnly'] && $j['raw'] >= KaryaNirnay::MADHYAM) {
                $j['hard'] = false;
                $j['grade'] = 'मध्यम';
                $j['score'] = min(65, $j['raw']);
                $j['raw'] = min(65, $j['raw']);
                $j['warn'] = array_merge(array_map(static fn ($x) => 'लग्न-शुद्धि अपूर्ण — ' . $x, $j['bad']), $j['warn']);
            }
            if ($j['hard'] || $j['grade'] === 'अशुभ') {
                foreach ($j['hard'] ? $j['bad'] : ['अंक कम: ' . implode(', ', array_slice($j['warn'], 0, 2))] as $lb) {
                    $key = explode(':', $lb)[0];
                    $badDur[$key] = ($badDur[$key] ?? 0) + ($b - $a);
                }
            }
            $ok = !$j['hard'] && $j['grade'] !== 'अशुभ';
            if ($ok && $cur !== null && $cur['grade'] === $j['grade'] && abs($cur['b'] - $a) < 1e-6) {
                $cur['b'] = $b;
                $cur['max'] = max($cur['max'], $j['score']);
                $cur['rank'] = max($cur['rank'], $j['raw']);
                $cur['min'] = min($cur['min'], $j['score']);
                $cur['lagna'][$f['ascSign']] = true;
                $cur['nak'][$f['nak']] = true;
                $cur['tithi'][$f['tithi']] = true;
                // बल/सावधानी उसी सबसे अच्छे क्षण के — जिसका अंक दिखता है और जिस पर "पूरा विवरण" जाँचता है
                if ($j['score'] > $cur['peakScore'] || ($j['score'] === $cur['peakScore'] && $b - $a > $cur['peakLen'])) {
                    $cur['peakScore'] = $j['score']; $cur['peak'] = ($a + $b) / 2; $cur['peakLen'] = $b - $a;
                    $cur['good'] = $j['good']; $cur['warn'] = $j['warn'];
                }
                continue;
            }
            if ($cur !== null) { $wins[] = $cur; }
            $cur = $ok ? ['a' => $a, 'b' => $b, 'grade' => $j['grade'], 'max' => $j['score'], 'min' => $j['score'], 'rank' => $j['raw'],
                'lagna' => [$f['ascSign'] => true], 'nak' => [$f['nak'] => true], 'tithi' => [$f['tithi'] => true],
                'good' => $j['good'], 'warn' => $j['warn'],
                'peakScore' => $j['score'], 'peak' => ($a + $b) / 2, 'peakLen' => $b - $a, 'abujh' => $j['abujh']] : null;
        }
        if ($cur !== null) { $wins[] = $cur; }
        $wins = array_values(array_filter($wins, static fn ($w) => $w['b'] - $w['a'] >= self::MIN_WIN));
        usort($wins, static fn ($x, $y) => [$x['grade'] === 'शुभ' ? 1 : 0, $x['rank'], $x['b'] - $x['a']]
            <=> [$y['grade'] === 'शुभ' ? 1 : 0, $y['rank'], $y['b'] - $y['a']]);
        $wins = array_reverse($wins);

        $tz = $day['tz'];
        $fmt = static fn (float $jd): string => gmdate('H:i', (int) round(($jd + $tz / 24.0 - 2440587.5) * 86400));
        $civil = static fn (float $jd): int => (int) floor($jd + $tz / 24.0 + 0.5);
        $d0 = $civil($day['rise']);
        $outWins = [];
        foreach (array_slice($wins, 0, 3) as $w) {
            $outWins[] = [
                'from' => $fmt($w['a']), 'to' => $fmt($w['b']) . ($civil($w['b']) > $d0 ? ' (+1)' : ''),
                'next_day' => $civil($w['a']) > $d0,
                'mins' => (int) round(($w['b'] - $w['a']) * 1440),
                'grade' => $w['grade'], 'score' => $w['max'], 'score_min' => $w['min'], 'rank' => $w['rank'],
                'peak' => $fmt($w['peak']), 'peak_jd' => $w['peak'],
                'lagna' => implode(', ', array_map([KaryaNirnay::class, 'signHi'], array_keys($w['lagna']))),
                'nak' => implode(', ', array_map(static fn ($n) => P::NAK[$n]['name'], array_keys($w['nak']))),
                'tithi' => implode(', ', array_map(static fn ($x) => P::TITHI[$x]['name'], array_keys($w['tithi']))),
                'good' => array_slice(array_values(array_unique($w['good'])), 0, 5),
                'warn' => array_slice(array_values(array_unique($w['warn'])), 0, 4),
            ];
        }
        $top = $wins[0] ?? null;
        $grade = $top['grade'] ?? 'अशुभ';
        arsort($badDur);
        $reason = $top !== null
            ? $outWins[0]['from'] . '–' . $outWins[0]['to'] . ' · ' . $outWins[0]['lagna'] . ' लग्न · ' . $outWins[0]['nak']
                . ($outWins[0]['good'] ? ' · ★ ' . implode(', ', array_slice($outWins[0]['good'], 0, 3)) : '')
            : 'बाधा: ' . implode(' · ', array_slice(array_keys($badDur), 0, 3));
        $panch = $keepSegs ? self::panchSummary($kk, $day) : [];
        return [
            'date' => $day['date'], 'key' => sprintf('%04d%02d%02d', $day['y'], $day['m'], $day['d']),
            'weekday' => P::VAAR[$day['vaar']]['name'],
            'grade' => $grade, 'tone' => $grade === 'शुभ' ? 'pos' : ($grade === 'मध्यम' ? 'info' : 'neg'),
            'score' => $top !== null ? (int) $top['max'] : max(0, 30 - 5 * count($badDur)),
            'rank' => $top !== null ? (int) $top['rank'] : 0,
            'reason' => $reason, 'windows' => $outWins,
            'fest' => array_values($fest), 'abujh' => $top['abujh'] ?? null,
            'sunrise' => $fmt($day['rise']), 'sunset' => $fmt($day['set']),
        ] + ($keepSegs ? ['_segs' => $segs, '_ctx' => $ctx, 'tithi' => $panch['tithi'], 'nak' => $panch['nak']] : []);
    }

    /** दिन का संक्षिप्त पंचांग (तिथि/नक्षत्र के अन्त-समय सहित)। */
    private static function panchSummary(KaryaKaal $kk, array $day): array
    {
        $tz = $day['tz'];
        $fmt = static fn (float $jd): string => gmdate('H:i', (int) round(($jd + $tz / 24.0 - 2440587.5) * 86400));
        $civil = static fn (float $jd): int => (int) floor($jd + $tz / 24.0 + 0.5);
        $d0 = $civil($day['rise']);
        $till = static function (float $b) use ($day, $fmt, $civil, $d0): string {
            if ($b >= $day['next']) { return 'पूरे दिन'; }
            return $fmt($b) . ($civil($b) > $d0 ? ' (+1)' : '') . ' तक';
        };
        $t = []; $n = []; $y = []; $k = [];
        foreach ($day['iv']['tithi'] as [$v, , $b]) { $t[] = P::TITHI[$v]['name'] . ($v <= 15 ? ' शु.' : ' कृ.') . ' ' . $till($b); }
        foreach ($day['iv']['nak'] as [$v, , $b]) { $n[] = P::NAK[$v]['name'] . ' ' . $till($b); }
        foreach ($day['iv']['yoga'] as [$v, , $b]) { $y[] = P::YOGA[$v]['name'] . ' ' . $till($b); }
        foreach ($day['iv']['karana'] as [$v, $a2, $b]) { if ($b > $day['rise']) { $k[] = KaryaKaal::karanaName($v)[0] . ' ' . $till($b); } }
        return ['tithi' => implode(' → ', $t), 'nak' => implode(' → ', $n), 'yoga' => implode(' → ', $y), 'karana' => implode(' → ', $k)];
    }

    // ───────────────────────── एक क्षण की जाँच ─────────────────────────

    /**
     * एक तिथि (और वैकल्पिक समय) की पूरी जाँच — हर नियम की पंक्ति, उस दिन की
     * शुभ खिड़कियाँ, पंचांग व दिन के काल। समय न हो तो दिन की सबसे अच्छी खिड़की।
     */
    public static function check(CalculationEngine $eng, string $id, array $date, ?array $hm, array $place, array $opts, ?array $natal, bool $personal): array
    {
        $t = self::prepare($id, $opts);
        if ($t === null) { return ['ok' => false, 'error' => 'अज्ञात कार्य।']; }
        $zone = self::zone($place['zone'] ?? null);
        $tz0 = (float) $place['tz'];
        $jd0 = JulianDay::fromGregorian($date[0], $date[1], $date[2], 0, 0, 0.0, $tz0);
        $kk = new KaryaKaal($eng, (float) $place['lat'], (float) $place['lon'], $zone, $tz0, $jd0 - 2.0, $jd0 + 3.0);
        $need = ['hora' => true, 'chogh' => true, 'ritu' => true];
        $dt = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $date[0], $date[1], $date[2]));
        $day = $kk->day($date[0], $date[1], $date[2], $need);
        $at = null;
        if ($hm !== null) {
            $tzD = $kk->tzFor($date[0], $date[1], $date[2]);
            $at = JulianDay::fromGregorian($date[0], $date[1], $date[2], $hm[0], $hm[1], 0.0, $tzD);
            if ($at < $day['rise']) {   // सूर्योदय से पहले का क्षण पिछले वार का
                $p = $dt->modify('-1 day');
                $day = $kk->day((int) $p->format('Y'), (int) $p->format('n'), (int) $p->format('j'), $need);
            }
        }
        $row = self::judgeDay($kk, $t, $day, $natal, $personal, $opts, true);
        if ($at === null) {
            $at = $row['windows'][0]['peak_jd'] ?? ($day['rise'] + 1.0 / 24.0);
        }
        $f = $kk->facts($day, $at);
        $j = KaryaNirnay::judge($t, $f, $day, $row['_ctx'], true);
        $tz = $day['tz'];
        $fmt = static fn (float $jd): string => gmdate('H:i', (int) round(($jd + $tz / 24.0 - 2440587.5) * 86400));
        $win = static function (string $k) use ($day, $fmt): string {
            $o = [];
            foreach ($day['win'][$k] ?? [] as [$a, $b]) { $o[] = $fmt($a) . '–' . $fmt($b); }
            return implode(', ', $o);
        };
        $panch = self::panchSummary($kk, $day);
        $m = $f['maas'];
        unset($row['_segs'], $row['_ctx']);
        return [
            'ok' => true, 'task' => $id, 'task_name' => KaryaNiyamData::TASKS[$id]['name'],
            'date' => $day['date'], 'weekday' => P::VAAR[$day['vaar']]['name'],
            'time' => $fmt($at) . ((int) floor($at + $tz / 24.0 + 0.5) > (int) floor($day['rise'] + $tz / 24.0 + 0.5) ? ' (+1)' : ''),
            'verdict' => ['grade' => $j['grade'], 'tone' => $j['tone'], 'score' => $j['score'], 'hard' => $j['hard'],
                'bad' => $j['bad'], 'warn' => $j['warn'], 'good' => $j['good'], 'abujh' => $j['abujh'], 'checks' => $j['checks']],
            'day' => $row,
            'panchang' => [
                'सूर्योदय · सूर्यास्त' => $fmt($day['rise']) . ' · ' . $fmt($day['set']),
                'मास' => ($m['adhik'] ? 'अधिक ' : '') . KaryaNirnay::MAAS_HI[$m['pm']] . ' (पूर्णिमान्त) · ' . ($f['uttarayan'] ? 'उत्तरायण' : 'दक्षिणायन'),
                'तिथि' => $panch['tithi'], 'नक्षत्र' => $panch['nak'], 'योग' => $panch['yoga'], 'करण' => $panch['karana'],
                'चंद्र · सूर्य' => KaryaNirnay::signHi($f['moonSign']) . ' · ' . KaryaNirnay::signHi($f['sunSign']),
                'लग्न (इस समय)' => KaryaNirnay::signHi($f['ascSign']),
                'राहुकाल' => $win('rahukaal'), 'यमगण्ड' => $win('yamaganda'), 'गुलिक' => $win('gulika'),
                'दुर्मुहूर्त' => $win('durmuhurt'), 'अभिजित' => $win('abhijit') . ($day['vaar'] === 4 ? ' (बुधवार निष्फल)' : ''),
                'भद्रा' => $win('bhadra') ?: '—', 'वर्ज्यम्' => $win('varjyam') ?: '—',
                'दिशाशूल' => P::VAAR[$day['vaar']]['shool'] . ' (परिहार: ' . P::VAAR[$day['vaar']]['parihar'] . ')',
            ],
            'info' => self::taskInfo($id),
            'place' => ['lat' => $place['lat'], 'lon' => $place['lon'], 'tz' => $tz],
            'personal' => $natal !== null && $personal,
        ];
    }

    // ───────────────────────── नियम-पत्र ─────────────────────────

    /** कार्य का नियम-पत्र — श्रेणी-सिद्धांत, शब्दशः नियम, विकल्प, टिप्पणी। */
    public static function taskInfo(string $id): array
    {
        $d = KaryaNiyamData::TASKS[$id] ?? null;
        if ($d === null) { return []; }
        $v = KaryaNiyamVidhi::rule($id);
        $cat = KaryaNiyamData::CATEGORIES[$d['c']] ?? [];
        $opts = [];
        foreach ($v['options'] ?? [] as $k) {
            if (isset(KaryaNiyamVidhi::OPTIONS[$k])) { $opts[$k] = ['label' => KaryaNiyamVidhi::OPTIONS[$k][0], 'choices' => KaryaNiyamVidhi::OPTIONS[$k][1]]; }
        }
        $extra = [];
        if (!empty($v['gem'])) {
            foreach (P::RATNA as $r) { if ($r['graha'] === KaryaKaal::HI[$v['gem']]) { $extra['रत्न-विधान'] = $r; } }
            $extra['सामान्य नियम'] = P::RATNA_NOTE;
        }
        if (($v['nature'] ?? '') === 'shatkarma') {
            $names = ['4.01' => 'शांति', '4.02' => 'पौष्टिक (बरकत)', '4.03' => 'वशीकरण', '4.04' => 'स्तम्भन', '4.05' => 'विद्वेषण', '4.06' => 'उच्चाटन', '4.07' => 'मारण'];
            foreach (P::SHATKARMA as $r) { if ($r['karm'] === ($names[$id] ?? '')) { $extra['षट्कर्म'] = $r; } }
            $extra['टिप्पणी'] = P::SHATKARMA_NOTE;
        }
        return [
            'id' => $id, 'name' => $d['name'],
            'category' => ['n' => $d['c'], 'emoji' => $cat['emoji'] ?? '', 'name' => $cat['name'] ?? '',
                'siddhant' => $cat['siddhant'] ?? '', 'pradhan_bal' => $cat['pradhan_bal'] ?? '', 'bhav' => $cat['bhav'] ?? '',
                'karak' => $cat['karak'] ?? '', 'varjit' => $cat['varjit'] ?? ''],
            'txt' => $d['txt'], 'note' => $v['note'] ?? '', 'options' => $opts, 'sado' => KaryaNiyamData::SADO, 'extra' => $extra,
        ];
    }

    private static function zone(?string $name): ?\DateTimeZone
    {
        if ($name === null || $name === '') { return null; }
        try { return new \DateTimeZone($name); } catch (\Throwable) { return null; }
    }
}
