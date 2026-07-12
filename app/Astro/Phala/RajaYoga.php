<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * राजयोग — BPHS Adhyaya 36 (Rajayogadhyaya). Detects the classical Raja Yogas
 * from the D1 chart, applies the Bhanga (cancellation) rules, and dedups against
 * the Adhyaya-32 Yogakaraka engine so one planet-relationship is never reported
 * twice.
 *
 * Scope note (per the spec's Gap Analysis): rules that need Jaimini chara-karakas
 * are computed here (AK/AmK/PK derived from degrees). Rules that additionally
 * need Arudha padas, Hora/Ghati lagna, D3, Argala or local-noon are surfaced as
 * "requires additional data" rather than silently skipped.
 *
 * ⚠ The granth's own caveat (p.279): never pronounce a verdict from one yoga —
 * weigh strength and dasha. The UI shows this caveat.
 */
final class RajaYoga
{
    private const OWN = [
        'Sun' => [4], 'Moon' => [3], 'Mars' => [0, 7], 'Mercury' => [2, 5],
        'Jupiter' => [8, 11], 'Venus' => [1, 6], 'Saturn' => [9, 10],
    ];
    private const SEVEN = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];

    /** @param array<string,mixed> $chart @return array<string,mixed> */
    public static function compute(array $chart): array
    {
        $P = $chart['planets'] ?? [];
        $H = $chart['houses'] ?? [];
        $asc = (int) ($chart['ascendant']['sign_index'] ?? 0);
        if ($P === [] || $H === []) {
            return ['active' => [], 'bhanga' => [], 'unavailable' => [], 'summary' => '', 'caveat' => self::CAVEAT];
        }

        $yk = Yogakaraka::classify($chart);
        $roles = $yk['roles'] ?? [];
        $ykPlanets = [];
        foreach ($roles as $pl => $r) {
            if (($r['role'] ?? '') === 'yogakaraka') { $ykPlanets[] = $pl; }
        }

        $hi = static fn (string $p): string => Yogakaraka::planetHi($p);
        $lord = static fn (int $h): ?string => $H[$h]['lord'] ?? null;
        $houseOf = static fn (string $p): int => (int) ($P[$p]['house'] ?? 0);
        $signOf = static fn (string $p): int => (int) ($P[$p]['sign_index'] ?? 0);
        $retro = static fn (string $p): bool => !empty($P[$p]['retro']);
        $cond = static fn (string $p): array => PlanetCondition::resolve($p, $P, $asc);
        $tier = static fn (string $p): string => (string) ($cond($p)['dignity']['tier'] ?? '');
        $isExalt = static fn (string $p): bool => $tier($p) === 'exalt';
        $isDebil = static fn (string $p): bool => in_array($tier($p), ['debil', 'debil_bhanga'], true);
        $isStrong = static fn (string $p): bool => in_array($tier($p), ['exalt', 'own', 'moolatrikona'], true);
        $isCombust = static fn (string $p): bool => ($cond($p)['combust'] ?? null) !== null;
        $roleOf = static fn (string $p): string => (string) ($roles[$p]['role'] ?? '');
        $isBenefic = static fn (string $p): bool => in_array($roleOf($p), ['benefic', 'yogakaraka'], true);
        $isMalefic = static fn (string $p): bool => $roleOf($p) === 'malefic';

        $aspHouse = static fn (string $p, int $h): bool => in_array($p, $H[$h]['drishti'] ?? [], true);
        $aspP = static fn (string $a, string $b): bool => $aspHouse($a, $houseOf($b));
        $mutual = static fn (string $a, string $b): bool => $aspP($a, $b) && $aspP($b, $a);
        $conj = static fn (string $a, string $b): bool => $a !== $b && $houseOf($a) === $houseOf($b) && $houseOf($a) > 0;
        $inOwnOf = static fn (string $a, string $b): bool => in_array($signOf($a), self::OWN[$b] ?? [], true);

        // Sambandha between two planets → [type_hi, weight, houses[]] | null.
        $sambandha = static function (string $a, string $b) use ($inOwnOf, $conj, $mutual, $aspP, $houseOf): ?array {
            if ($inOwnOf($a, $b) && $inOwnOf($b, $a)) { return ['स्थान सम्बन्ध (राशि परिवर्तन)', 1.0, [$houseOf($a), $houseOf($b)]]; }
            if ($conj($a, $b)) { return ['एकत्र (युति)', 1.0, [$houseOf($a)]]; }
            if ($mutual($a, $b)) { return ['परस्पर दृष्टि', 0.9, [$houseOf($a), $houseOf($b)]]; }
            if ($aspP($a, $b) || $aspP($b, $a)) { return ['एक दृष्टि (एकपक्षीय)', 0.6, [$houseOf($a), $houseOf($b)]]; }
            return null;
        };

        // House-parivartana: lord(a) sits in house b AND lord(b) sits in house a.
        $parivartana = static function (int $a, int $b) use ($lord, $houseOf): bool {
            $la = $lord($a); $lb = $lord($b);
            return $la !== null && $lb !== null && $houseOf($la) === $b && $houseOf($lb) === $a;
        };

        // Chara karakas (7-planet scheme) from degree-in-sign, descending.
        $karaka = [];
        $degs = [];
        foreach (self::SEVEN as $p) { $degs[$p] = (float) ($P[$p]['deg_in_sign'] ?? 0.0); }
        arsort($degs);
        $order = array_keys($degs);
        $AK = $order[0] ?? null; $AmK = $order[1] ?? null; $PK = $order[4] ?? null;

        $hits = [];
        $add = static function (string $id, int $shloka, string $cat, string $name, string $matched, array $planets, ?array $sb, string $result) use (&$hits): void {
            $hits[] = [
                'id' => 'BPHS36-' . $id, 'shloka' => $shloka, 'cat' => $cat, 'name' => $name,
                'matched' => $matched, 'planets' => array_values(array_unique($planets)),
                'sambandha' => $sb ? $sb[0] : '', 'strength' => $sb ? (float) $sb[1] : 0.7,
                'sb_houses' => $sb ? ($sb[2] ?? []) : [], 'result' => $result, 'bhanga' => [],
            ];
        };

        $L1 = $lord(1); $L4 = $lord(4); $L5 = $lord(5); $L7 = $lord(7);
        $L9 = $lord(9); $L10 = $lord(10); $L6 = $lord(6); $L8 = $lord(8); $L12 = $lord(12);
        $pn = static fn (?string $p): string => $p ? $hi($p) : '—';

        // ---------------- RY (Parashari) ----------------
        if ($L5 && $L9 && $L5 !== $L9 && $mutual($L5, $L9)) {
            $add('RY-01', 2, 'RY', 'पंचमेश-नवमेश दृष्टि योग',
                'पंचमेश ' . $pn($L5) . ' व नवमेश ' . $pn($L9) . ' परस्पर पूर्ण दृष्टि में',
                [$L5, $L9], ['परस्पर दृष्टि', 0.9, [$houseOf($L5), $houseOf($L9)]],
                'राज्य (अधिकार) — त्रिकोणेशों का शुभ सम्बन्ध, बड़ा भाग्य व प्रतिष्ठा।');
        }
        if ($L5 && $L9 && $L5 !== $L9) {
            $sd = $conj($L5, $L9) ? ['एकत्र (युति)', 1.0, [$houseOf($L5)]]
                : ((($houseOf($L9) - $houseOf($L5) + 12) % 12 === 6) ? ['समसप्तक', 0.9, [$houseOf($L5), $houseOf($L9)]] : null);
            if ($sd !== null) {
                $add('RY-02', 3, 'RY', 'पंचमेश-नवमेश युति/समसप्तक योग',
                    'पंचमेश ' . $pn($L5) . ' व नवमेश ' . $pn($L9) . ' ' . ($sd[0] === 'समसप्तक' ? 'परस्पर सप्तम' : 'एक साथ'),
                    [$L5, $L9], $sd, 'राजकुल में जन्म हो तो राजा; अन्यथा तत्सदृश अधिकार।');
            }
        }
        if ($parivartana(4, 10) && ($L4 && $L10) && (($L5 && ($sambandha($L4, $L5) || $sambandha($L10, $L5))) || ($L9 && ($sambandha($L4, $L9) || $sambandha($L10, $L9))))) {
            $add('RY-03', 4, 'RY', 'चतुर्थ-दशम परिवर्तन योग',
                'चतुर्थेश ' . $pn($L4) . ' दशम में व दशमेश ' . $pn($L10) . ' चतुर्थ में (परिवर्तन), पंचमेश/नवमेश से सम्बन्धित',
                [$L4, $L10], ['स्थान सम्बन्ध (राशि परिवर्तन)', 1.0, [4, 10]],
                'राज्य — सुख व कर्म भावों का बलवान् परिवर्तन।');
        }
        if ($L4 && $L10) {
            foreach ([[$L5, 'पंचमेश'], [$L9, 'नवमेश']] as [$lp, $lpn]) {
                if ($lp && ($conj($L4, $lp) || $conj($L10, $lp))) {
                    $add('RY-05', 10, 'RY', 'सुख-कर्म-मन्त्री योग',
                        'चतुर्थेश/दशमेश ' . $lpn . ' ' . $pn($lp) . ' से युत',
                        [$L4, $L10, $lp], ['एकत्र (युति)', 1.0, []],
                        'राज्य — केन्द्र व त्रिकोणेशों की युति से अधिकार।');
                    break;
                }
            }
        }
        // RY-06 (subset of Ch.32): lords of 1,5,9 all in kendra {1,4,7,10} and mutually related.
        if ($L1 && $L5 && $L9) {
            $k = [1, 4, 7, 10];
            if (in_array($houseOf($L1), $k, true) && in_array($houseOf($L5), $k, true) && in_array($houseOf($L9), $k, true)
                && $sambandha($L1, $L5) && $sambandha($L5, $L9) && $sambandha($L1, $L9)) {
                $add('RY-06', 11, 'RY', 'त्रिकोणेश-केन्द्र योग',
                    'लग्नेश ' . $pn($L1) . ', पंचमेश ' . $pn($L5) . ' व नवमेश ' . $pn($L9) . ' — तीनों केन्द्र (1/4/7/10) में परस्पर सम्बन्धी',
                    [$L1, $L5, $L9], ['केन्द्र-त्रिकोण', 0.9, []], 'राजा — त्रिकोणेशों का केन्द्र में एकत्र सम्बन्ध।');
            }
        }
        if ($mutual('Venus', 'Moon') || (($houseOf('Venus') - $houseOf('Moon') + 12) % 12 + 1) === 3 || (($houseOf('Moon') - $houseOf('Venus') + 12) % 12 + 1) === 3) {
            $rel = $sambandha('Venus', 'Moon');
            if ($rel !== null) {
                $add('RY-09', 14, 'RY', 'शुक्र-चन्द्र राजयोग',
                    'शुक्र व चन्द्र परस्पर दृष्टि/3-11 सम्बन्ध में', ['Venus', 'Moon'], $rel,
                    'राजयोग — सौम्य ग्रहों का शुभ सम्बन्ध, ऐश्वर्य व लोकप्रियता।');
            }
        }
        // RY-11/12/13 — count of exalted grahas.
        $exalted = array_values(array_filter(self::SEVEN, $isExalt));
        if (count($exalted) >= 3) {
            $ec = count($exalted);
            $id = $ec >= 6 ? 'RY-13' : ($ec >= 4 ? 'RY-12' : 'RY-11');
            $shl = $ec >= 6 ? 18 : ($ec >= 4 ? 17 : 16);
            $res = $ec >= 6 ? 'चक्रवर्ती सम्राट-तुल्य — षड्-उच्च योग।' : ($ec >= 4 ? 'हीन वंश में भी राजा — बहु-उच्च योग।' : 'राजवंश में राजा; अन्यथा अति सुखी-धनी।');
            $add($id, $shl, 'RY', ($ec >= 6 ? 'षड्-उच्च' : ($ec >= 4 ? 'चतुः/पंच-उच्च' : 'त्रि-उच्च')) . ' राजयोग',
                $ec . ' ग्रह उच्च के — ' . implode(', ', array_map($hi, $exalted)),
                $exalted, ['उच्चता', 1.0, []], $res);
        }
        // RY-20 — benefics in 1,2,4 + a malefic in 3.
        $ben124 = [];
        foreach (self::SEVEN as $p) { if ($isBenefic($p) && in_array($houseOf($p), [1, 2, 4], true)) { $ben124[] = $p; } }
        $mal3 = array_values(array_filter(self::SEVEN, static fn ($p) => $isMalefic($p) && $houseOf($p) === 3));
        if ($ben124 !== [] && $mal3 !== []) {
            $add('RY-20', 39, 'RY', 'शुभ-1-2-4 / पाप-3 योग',
                'शुभ ग्रह (' . implode(', ', array_map($hi, $ben124)) . ') 1/2/4 में तथा पाप (' . implode(', ', array_map($hi, $mal3)) . ') तृतीय में',
                array_merge($ben124, $mal3), ['स्थान-बल', 0.8, []], 'राजा या राजतुल्य — शुभ-पाप का शास्त्रोक्त स्थान-विभाजन।');
        }
        // RY-24 — Vipreet Rajayoga (dusthana lords in dusthanas OR exchange).
        $vip = [];
        foreach ([[6, $L6, 'हर्ष'], [8, $L8, 'सरल'], [12, $L12, 'विमल']] as [$hh, $lp, $nm]) {
            if ($lp && in_array($houseOf($lp), [6, 8, 12], true)) { $vip[] = [$nm, $lp, $hh]; }
        }
        $vipExch = $parivartana(6, 8) || $parivartana(6, 12) || $parivartana(8, 12);
        if ($vip !== [] || $vipExch) {
            $names = array_map(static fn ($v) => $v[0], $vip);
            $vplanets = array_values(array_unique(array_filter([$L6, $L8, $L12])));
            $strong = $L1 && $isStrong($L1) && ($aspHouse($L1, 1) || $houseOf($L1) === 1);
            $add('RY-24', 43, 'RY', 'विपरीत राजयोग' . ($names ? ' (' . implode('/', $names) . ')' : ''),
                ($vipExch ? '6/8/12 भावेशों का परिवर्तन' : 'दुःस्थान-स्वामी दुःस्थानों में') . ($strong ? ' + बलवान् लग्नेश' : ''),
                $vplanets, ['विपरीत (दुःस्थान)', $strong ? 0.85 : 0.7, []],
                'विपरीत राजयोग — कष्ट/शत्रु के नाश से अप्रत्याशित उत्थान व अधिकार।');
        }
        // RY-25 — all benefics in 1/4/5/7/9/10 + all malefics in 3/6/11 + strong lagnesh.
        $good = [1, 4, 5, 7, 9, 10]; $bad = [3, 6, 11];
        $bens = array_values(array_filter(self::SEVEN, $isBenefic));
        $mals = array_values(array_filter(self::SEVEN, $isMalefic));
        if ($bens !== [] && $mals !== []
            && count(array_filter($bens, static fn ($p) => in_array($houseOf($p), $good, true))) === count($bens)
            && count(array_filter($mals, static fn ($p) => in_array($houseOf($p), $bad, true))) === count($mals)
            && $L1 && $isStrong($L1)) {
            $add('RY-25', 44, 'RY', 'शुभ-केन्द्रत्रिकोण / पाप-त्रिषडाय योग',
                'सभी शुभ केन्द्र-त्रिकोण (1/4/5/7/9/10) में, सभी पाप 3/6/11 में, लग्नेश बली',
                array_merge($bens, $mals, [$L1]), ['स्थान-बल', 0.9, []], 'हीन वंश में भी राजा — आदर्श ग्रह-विन्यास।');
        }
        // RY-26 — strong 10th lord aspects lagna.
        if ($L10 && $isStrong($L10) && $aspHouse($L10, 1)) {
            $bonus = $L1 && $sambandha($L1, $L10);
            $add('RY-26', 45, 'RY', 'दशमेश-लग्न दृष्टि योग',
                'बली/उच्चादिगत दशमेश ' . $pn($L10) . ' लग्न को देखे' . ($bonus ? ' + लग्नेश-सम्बन्ध' : ''),
                array_filter([$L10, $bonus ? $L1 : null]), ['एक दृष्टि', $bonus ? 0.85 : 0.7, [1, $houseOf($L10)]],
                'राजयोग — कर्मेश का बल लग्न पर, प्रतिष्ठा व पद।');
        }
        // RY-30 — lagnesh in 10 + 10th lord in 1 (parivartana).
        if ($parivartana(1, 10)) {
            $add('RY-30', 61, 'RY', 'राज्यसम्बन्ध योग',
                'लग्नेश ' . $pn($L1) . ' दशम में व दशमेश ' . $pn($L10) . ' लग्न में — परस्पर परिवर्तन',
                array_filter([$L1, $L10]), ['स्थान सम्बन्ध (राशि परिवर्तन)', 0.9, [1, 10]],
                'प्रबल राज्यसम्बन्ध योग — राज्यपक्ष में महत्त्वपूर्ण स्थिति व अधिकार।');
        }

        // ---------------- KA / MN (chara-karaka based) ----------------
        if ($AK && $L5 && $sambandha($AK, $L5) && $L1 && $sambandha($L1, $L5)) {
            $add('KA-02', 22, 'KA', 'आत्मकारक-पंचमेश राजयोग',
                'आत्मकारक ' . $pn($AK) . ' व पंचमेश ' . $pn($L5) . ' सम्बन्धी, लग्नेश-पंचमेश भी सम्बन्धी',
                array_filter([$AK, $L5, $L1]), ['कारक-सम्बन्ध', 0.85, []], 'राजयोग — आत्मकारक व त्रिकोणेश का शुभ योग।');
        }
        if ($AK) {
            $akH = $houseOf($AK);
            $benAK = array_values(array_filter(self::SEVEN, static fn ($p) => $isBenefic($p) && in_array((($signOf($p) - $signOf($AK) + 12) % 12) + 1, [2, 4, 5], true)));
            if ($benAK !== []) {
                $add('KA-05', 28, 'KA', 'कारक से शुभ-2-4-5 योग',
                    'आत्मकारक ' . $pn($AK) . ' से 2/4/5 में शुभ ग्रह (' . implode(', ', array_map($hi, $benAK)) . ')',
                    array_merge([$AK], $benAK), ['कारक-स्थान', 0.8, []], 'अवश्य राजयोग — कारक से शुभ का बल।');
            }
            $malAK = array_values(array_filter(self::SEVEN, static fn ($p) => $isMalefic($p) && in_array((($signOf($p) - $signOf($AK) + 12) % 12) + 1, [3, 6], true)));
            if ($malAK !== []) {
                $add('KA-06', 29, 'KA', 'कारक से पाप-3-6 योग',
                    'आत्मकारक ' . $pn($AK) . ' से 3/6 में पाप ग्रह (' . implode(', ', array_map($hi, $malAK)) . ')',
                    array_merge([$AK], $malAK), ['कारक-स्थान', 0.75, []], 'राजकुल में जन्म हो तो राजा — कारक से उपचय-पाप बल।');
            }
            if (in_array($akH, [5, 7, 9, 10], true)) {
                $near = array_values(array_filter(self::SEVEN, static fn ($p) => $p !== $AK && $isBenefic($p) && $conj($p, $AK)));
                if ($near !== []) {
                    $add('KA-09', 62, 'KA', 'राजाश्रय धन योग',
                        'आत्मकारक ' . $pn($AK) . ' शुभयुक्त होकर ' . $akH . 'वें भाव में',
                        array_merge([$AK], $near), ['युति', 0.75, [$akH]], 'शासन/राज्य से धन-लाभ।');
                }
            }
        }
        if ($AmK && $AK && $sambandha($AmK, $AK)) {
            $add('MN-03', 53, 'MN', 'अमात्य-आत्मकारक योग',
                'अमात्यकारक ' . $pn($AmK) . ' व आत्मकारक ' . $pn($AK) . ' परस्पर सम्बन्धी',
                [$AmK, $AK], ['कारक-सम्बन्ध', 0.8, []], 'तीव्र बुद्धि; मन्त्री-तुल्य पद।');
        }
        if ($AmK && $isStrong($AmK)) {
            $add('MN-04', 54, 'MN', 'बली अमात्यकारक योग',
                'अमात्यकारक ' . $pn($AmK) . ' स्वक्षेत्री/उच्च व बलवान्', [$AmK], ['बल', 0.75, []],
                'मन्त्री योग — प्रशासनिक/सलाहकार पद।');
        }
        if ($AmK && in_array($houseOf($AmK), [1, 5, 9], true) && $isStrong($AmK)) {
            $add('MN-05', 55, 'MN', 'अमात्य-त्रिकोण योग',
                'अमात्यकारक ' . $pn($AmK) . ' त्रिकोण (1/5/9) में बलवान्', [$AmK], ['त्रिकोण-बल', 0.75, [$houseOf($AmK)]],
                'मन्त्री योग — त्रिकोणस्थ अमात्यकारक।');
        }
        if ($L10 && $L5 && $AmK) {
            $rel1 = $sambandha($L10, $L5); $rel2 = $sambandha($L10, $AmK) ?: $sambandha($L5, $AmK);
            if ($rel1 && $rel2) {
                $add('MN-01', 51, 'MN', 'प्रधान मन्त्री योग',
                    'दशमेश ' . $pn($L10) . ', पंचमेश ' . $pn($L5) . ' व अमात्यकारक ' . $pn($AmK) . ' परस्पर सम्बन्धी',
                    array_filter([$L10, $L5, $AmK]), ['कारक-सम्बन्ध', 0.8, []], 'राज दरबार में विशेष पद।');
            }
        }

        // ---------------- Bhanga (BH) on each hit ----------------
        foreach ($hits as &$hh0) {
            $b = [];
            $rHouses = $hh0['sb_houses'] ?? [];
            if (array_intersect($rHouses, [6, 8, 12]) !== []) { $b[] = 'BH-04: सम्बन्ध 6/8/12 भाव में — फल में कमी।'; }
            foreach ($hh0['planets'] as $p) {
                if (!isset($P[$p])) { continue; }
                if ($retro($p)) {
                    if ($isExalt($p)) { $b[] = 'BH-07: ' . $hi($p) . ' उच्च किन्तु वक्री — नीचवत् फल (योगभंग)।'; }
                    elseif ($isDebil($p)) { /* protective — noted below */ }
                    else { $b[] = 'BH-06: योगकारक ' . $hi($p) . ' वक्री — बल में कमी।'; }
                }
                if (in_array($p, $ykPlanets, true)) {
                    $ratio = (float) ($chart['shadbala'][$p]['ratio'] ?? 1.0);
                    if ($ratio < 0.85) { $b[] = 'BH-01: योगकारक ' . $hi($p) . ' षड्बल में हीन (' . round($ratio, 2) . ') — दुर्बल योग।'; }
                }
                if ($isCombust($p)) { $b[] = 'BH-03: ' . $hi($p) . ' अस्त (सूर्य-सामीप्य) — फल क्षीण।'; }
                // Malefic aspecting/conjunct a participant.
                foreach (self::SEVEN as $m) {
                    if ($m !== $p && $isMalefic($m) && ($conj($m, $p) || $aspP($m, $p)) && !in_array($m, $hh0['planets'], true)) {
                        $b[] = 'BH-03: ' . $hi($p) . ' पर पाप ' . $hi($m) . ' की दृष्टि/युति।';
                        break;
                    }
                }
            }
            $hh0['bhanga'] = array_values(array_unique($b));
            if ($hh0['bhanga'] !== []) { $hh0['strength'] = round($hh0['strength'] * 0.5, 2); }
        }
        unset($hh0);

        // ---------------- Dedup ----------------
        // Collapse the same unordered planet-pair to the strongest yoga.
        $bestByPair = [];
        foreach ($hits as $idx => $h0) {
            if (count($h0['planets']) !== 2) { continue; }
            $key = implode('|', self::sortPair($h0['planets']));
            if (!isset($bestByPair[$key]) || $hits[$bestByPair[$key]]['strength'] < $h0['strength']) {
                $bestByPair[$key] = $idx;
            }
        }
        $suppressed = [];
        foreach ($hits as $idx => $h0) {
            if (count($h0['planets']) !== 2) { continue; }
            $key = implode('|', self::sortPair($h0['planets']));
            if (($bestByPair[$key] ?? $idx) !== $idx) { $suppressed[$idx] = $hits[$bestByPair[$key]]['name']; }
        }

        $active = []; $bhanga = [];
        foreach ($hits as $idx => $h0) {
            if (isset($suppressed[$idx])) {
                $h0['dedup'] = $suppressed[$idx];
                // merged — do not list separately
                continue;
            }
            if ($h0['bhanga'] !== []) { $bhanga[] = $h0; } else { $active[] = $h0; }
        }
        usort($active, static fn ($a, $b) => $b['strength'] <=> $a['strength']);
        usort($bhanga, static fn ($a, $b) => $b['strength'] <=> $a['strength']);

        // Advanced rules needing data we do not compute yet (spec Gap Analysis).
        $unavailable = [
            ['name' => 'महाराज योग व अन्य अरुढ़-आधारित योग', 'need' => 'अरुढ़ पद (AL/UL/A9)'],
            ['name' => 'त्रिलग्न (होरा/घटी लग्न) राजयोग', 'need' => 'होरा लग्न · घटी लग्न · D3'],
            ['name' => 'नृपनिकटता / कारकांश योग', 'need' => 'कारकांश लग्न (AK का नवांश)'],
            ['name' => 'सेनापति योग', 'need' => 'अरुढ़ पद व सर्वत्र-पाप गणना'],
        ];

        $count = count($ykPlanets);
        $base = $count < 2
            ? ($count . ' योगकारक ग्रह — शास्त्रोक्त न्यूनतम 2 से कम, अतः पूर्ण राजयोग का बल कम (श्लोक 21)।')
            : ($count . ' योगकारक ग्रह (' . implode(', ', array_map($hi, $ykPlanets)) . ') — अधिकार-सम्पन्न।');
        $summary = $base . ' सक्रिय राजयोग: ' . count($active) . ', भंग/दुर्बल: ' . count($bhanga) . '। '
            . ($count >= 2 ? 'जितने अधिक व बली, उतना बड़ा अधिकार।' : 'निम्न योगों को ग्रह-बल व दशा-क्रम के साथ ही तौलें।');

        return [
            'yogakaraka_planets' => array_map($hi, $ykPlanets),
            'yogakaraka_count' => $count,
            'karakas' => ['AK' => $AK ? $hi($AK) : null, 'AmK' => $AmK ? $hi($AmK) : null, 'PK' => $PK ? $hi($PK) : null],
            'active' => $active,
            'bhanga' => $bhanga,
            'unavailable' => $unavailable,
            'summary' => $summary,
            'caveat' => self::CAVEAT,
        ];
    }

    /** @param list<string> $pair @return list<string> */
    private static function sortPair(array $pair): array
    {
        sort($pair);
        return $pair;
    }

    private const CAVEAT = 'योग देखते ही झट से निर्णायक फल न कहें — अन्य योग, ग्रह-बल व दशा-क्रम की तुलना अवश्य करें। (बृ.पा.हो.शा. अध्याय 36, पृ. 279)';
}
