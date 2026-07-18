<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\Charts;

/**
 * Poorva-Shaap engine — detects the reliably-computable subset of the 105
 * santaan (progeny) rules against the D1 chart and returns the full catalogue
 * grouped by category, with the remedy attached for any dosha category that
 * has a detected rule.
 *
 * Uses only unambiguous facts: planet sign/house, house-lords (panchamesh etc.),
 * conjunction (same sign), exalt/own/debil, benefic/malefic, full drishti (Mars
 * 4/7/8, Jupiter 5/7/9, Saturn 3/7/10), and a coarse strong/weak proxy
 * (own/exalt or kendra-trikona = strong; debil or dusthana = weak), per the
 * source's implementation note 2. Rules needing D9/D60/Gulika/full Shadbala are
 * left as reference (detected = null) — never auto-fired.
 *
 * TRADITIONAL/informational reference only — not medical or fertility advice.
 */
final class ShaapEngine
{
    private const KENDRA_TRIKONA = [1, 4, 5, 7, 9, 10];
    private const DUSTHANA = [6, 8, 12];
    private const PAAP = ['Sun', 'Mars', 'Saturn', 'Rahu', 'Ketu'];
    private const OWN = ['Sun' => [4], 'Moon' => [3], 'Mars' => [0, 7], 'Mercury' => [2, 5], 'Jupiter' => [8, 11], 'Venus' => [1, 6], 'Saturn' => [9, 10]];
    private const EXALT = ['Sun' => 0, 'Moon' => 1, 'Mars' => 9, 'Mercury' => 5, 'Jupiter' => 3, 'Venus' => 11, 'Saturn' => 6];
    private const DEBIL = ['Sun' => 6, 'Moon' => 7, 'Mars' => 3, 'Mercury' => 11, 'Jupiter' => 9, 'Venus' => 5, 'Saturn' => 0];

    /**
     * @param array<string,mixed> $chart CalculationEngine::computeChart output
     * @param array<string,mixed> $rules ShaapRepository::load output
     * @return array<string,mixed>
     */
    public static function compute(array $chart, array $rules, float $tz = 0.0): array
    {
        $cfg = $rules['config'] ?? [];
        $auto = ($cfg['shaap_autodetect'] ?? '1') === '1';
        $remedies = $rules['remedies'] ?? [];
        $ctx = self::context($chart);
        $yk = Yogakaraka::classify($chart);

        // Santaan-yoga karakas: पुत्रकारक गुरु + पंचमेश + लग्नेश — the result
        // manifests in their Vimshottari dasha.
        $L5 = (string) (($chart['houses'][5]['lord']) ?? '');
        $L1 = (string) (($chart['houses'][1]['lord']) ?? '');
        $phalDasha = self::phalDasha(array_values(array_unique(array_filter(['Jupiter', $L5, $L1]))), $yk, $chart, $tz);

        $groups = [];
        $detectedCount = 0;
        $detectedCats = [];
        // शुभ सन्तान-योग (e.g. बहुपुत्र) and शाप-दोष are counted separately so
        // the General Overview never presents a benefic yoga as a "शाप".
        $shubhCats = $doshaCats = [];
        $shubhCount = $doshaCount = 0;
        foreach (($rules['rules'] ?? []) as $r) {
            $d = $auto ? self::detect((string) $r['id'], $ctx) : null;
            $row = $r + ['detected' => $d];
            if ($d === true) {
                $detectedCount++;
                $detectedCats[$r['cat']] = true;
                $row['phal_dasha'] = $phalDasha;
                if (($r['type'] ?? '') === 'shubh') { $shubhCount++; $shubhCats[$r['cat']] = true; }
                else { $doshaCount++; $doshaCats[$r['cat']] = true; }
            }
            $groups[$r['cat']][] = $row;
        }

        // Remedies for dosha categories that fired.
        $activeRemedies = [];
        foreach (array_keys($detectedCats) as $cat) {
            $rcat = ShaapData::REMEDY_FOR[$cat] ?? null;
            if ($rcat !== null && isset($remedies[$rcat])) {
                $activeRemedies[$rcat] = $remedies[$rcat] + ['cat' => $rcat];
            }
        }

        return [
            'categories' => ShaapData::CATEGORIES,
            'groups' => $groups,
            'detected_count' => $detectedCount,
            'detected_categories' => array_keys($detectedCats),
            // split counts: benefic santaan-yogas vs actual shaap-doshas
            'detected_shubh' => $shubhCount,
            'detected_dosha' => $doshaCount,
            'shubh_categories' => array_keys($shubhCats),
            'dosha_categories' => array_keys($doshaCats),
            'remedies' => array_values($activeRemedies),
            'total' => count($rules['rules'] ?? []),
        ];
    }

    /**
     * @param list<string> $karakas
     * @return array{planets:list<array<string,mixed>>, note:string}
     */
    private static function phalDasha(array $karakas, array $yk, array $chart, float $tz): array
    {
        $roles = $yk['roles'] ?? [];
        $out = [];
        foreach ($karakas as $p) {
            $period = Yogakaraka::dashaPeriod($chart, $p);
            $out[] = [
                'planet' => $p, 'planet_hi' => Yogakaraka::planetHi($p),
                'role' => $roles[$p]['role'] ?? 'neutral', 'role_hi' => $roles[$p]['role_hi'] ?? 'सम',
                'start_jd' => $period[0] ?? null,
                'dasha' => $period !== null
                    ? \AutoBusiness\Astro\Time\JulianDay::toDmy($period[0], $tz) . ' – ' . \AutoBusiness\Astro\Time\JulianDay::toDmy($period[1], $tz)
                    : null,
            ];
        }
        // chronological — the chips read as a timeline (dasha-less planets last)
        usort($out, static fn ($a, $b) => ($a['start_jd'] ?? PHP_FLOAT_MAX) <=> ($b['start_jd'] ?? PHP_FLOAT_MAX));
        return ['planets' => $out, 'note' => 'दोष/योग का प्रभाव पुत्रकारक गुरु व पंचमेश-लग्नेश की महादशा/अन्तर्दशा में सम्भावित।'];
    }

    /** @return array<string,mixed> */
    private static function context(array $chart): array
    {
        $P = $chart['planets'] ?? [];
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $sign = static fn(string $p): int => (int) ($P[$p]['sign_index'] ?? -99);
        $house = static fn(string $p): int => (int) ($P[$p]['house'] ?? 0);
        $lordOfHouse = static fn(int $h): string => (string) (($chart['houses'][$h]['lord']) ?? '');
        $isExalt = static fn(string $p): bool => $sign($p) === (self::EXALT[$p] ?? -1);
        $isDebil = static fn(string $p): bool => $sign($p) === (self::DEBIL[$p] ?? -1);
        $isOwn = static fn(string $p): bool => in_array($sign($p), self::OWN[$p] ?? [], true);

        $elong = fmod(((float) ($P['Moon']['sidereal_lon'] ?? 0.0)) - ((float) ($P['Sun']['sidereal_lon'] ?? 0.0)) + 360.0, 360.0);
        $moonWax = $elong >= 90.0 && $elong <= 270.0;
        $paap = self::PAAP; if (!$moonWax) { $paap[] = 'Moon'; }

        $strong = static fn(string $p): bool => $isOwn($p) || $isExalt($p) || in_array($house($p), self::KENDRA_TRIKONA, true);
        $weak = static fn(string $p): bool => $p !== '' && ($isDebil($p) || in_array($house($p), self::DUSTHANA, true));

        // Malefic tenant in a house (conjunction-level affliction).
        $paapInHouse = static function (int $h) use ($P, $house, $paap): bool {
            foreach ($paap as $q) { if (isset($P[$q]) && $house($q) === $h) { return true; } }
            return false;
        };
        $paapConj = static function (string $p) use ($P, $sign, $paap): bool {
            if (!isset($P[$p])) { return false; }
            foreach ($paap as $q) { if ($q !== $p && isset($P[$q]) && $sign($q) === $sign($p)) { return true; } }
            return false;
        };
        // Full drishti of $p onto house $h.
        $aspHouse = static function (string $p, int $h) use ($P, $house): bool {
            if (!isset($P[$p]) || $house($p) === 0) { return false; }
            $rel = (($h - $house($p)) % 12 + 12) % 12 + 1;
            $set = [7];
            if ($p === 'Mars') { $set = [4, 7, 8]; }
            elseif ($p === 'Jupiter') { $set = [5, 7, 9]; }
            elseif ($p === 'Saturn') { $set = [3, 7, 10]; }
            return in_array($rel, $set, true);
        };
        $conj = static fn(string $a, string $b): bool => isset($P[$a], $P[$b]) && $sign($a) === $sign($b);
        $present = static fn(string $p): bool => isset($P[$p]);

        return compact('P', 'ascSign', 'sign', 'house', 'lordOfHouse', 'isExalt', 'isDebil', 'isOwn',
            'strong', 'weak', 'paapInHouse', 'paapConj', 'aspHouse', 'conj', 'present');
    }

    /** Curated detection map; null = reference-only. */
    private static function detect(string $id, array $c): ?bool
    {
        /** @var callable $sign */ $sign = $c['sign'];
        /** @var callable $house */ $house = $c['house'];
        /** @var callable $L */ $L = $c['lordOfHouse'];
        /** @var callable $isExalt */ $isExalt = $c['isExalt'];
        /** @var callable $isDebil */ $isDebil = $c['isDebil'];
        /** @var callable $strong */ $strong = $c['strong'];
        /** @var callable $weak */ $weak = $c['weak'];
        /** @var callable $paapIn */ $paapIn = $c['paapInHouse'];
        /** @var callable $paapConj */ $paapConj = $c['paapConj'];
        /** @var callable $asp */ $asp = $c['aspHouse'];
        /** @var callable $conj */ $conj = $c['conj'];
        /** @var callable $has */ $has = $c['present'];
        $ascSign = (int) $c['ascSign'];
        $L1 = $L(1); $L5 = $L(5); $L7 = $L(7); $L8 = $L(8); $L10 = $L(10);
        $L3 = $L(3); $L12 = $L(12); $L4 = $L(4); $L2 = $L(2); $L9 = $L(9);
        $hIn = static fn(?int $h, array $s): bool => $h !== null && in_array($h, $s, true);

        switch ($id) {
            // ---- सन्तान हानि (सामान्य) ----
            case 'SH01': return $weak('Jupiter') && $weak($L1) && $weak($L7) && $weak($L5);
            case 'SH02':
                foreach (['Sun', 'Mars', 'Saturn'] as $p) { if ($house($p) === 5 && $strong($p)) { return true; } }
                return false;

            // ---- सर्पशाप ----
            case 'SP01': return $house('Rahu') === 5 && ($asp('Mars', 5) || in_array($sign('Rahu'), [0, 7], true));
            case 'SP04': return $conj('Jupiter', 'Mars') && $house('Rahu') === 1 && $hIn($house($L5), [6, 8, 12]);
            case 'SP07': return ($house('Sun') === 5 || $house('Mars') === 5 || $house('Saturn') === 5) && $weak($L1) && $weak($L5);
            case 'SP08': return $conj($L1, 'Rahu') && $conj($L5, 'Mars') && $conj('Jupiter', 'Rahu');

            // ---- पितृशाप ----
            case 'PT03': return ($house($L10) === 5 || $house($L5) === 10) && $paapIn(1) && $paapIn(5);
            case 'PT04':
                foreach (['Sun', 'Mars', 'Saturn'] as $p) { if (!$hIn($house($p), [1, 5])) { return false; } }
                return $hIn($house('Rahu'), [8, 12]) && $hIn($house('Jupiter'), [8, 12]);
            case 'PT06': return $house($L12) === 1 && $house($L8) === 5 && $house($L10) === 8;

            // ---- मातृशाप ----
            case 'MT03': return $hIn($house($L5), [6, 8, 12]) && $isDebil($L1) && $paapConj('Moon');
            case 'MT10': return $house($L8) === 5 && $house($L5) === 8 && $hIn($house('Moon'), [6, 8, 12]) && $hIn($house($L4), [6, 8, 12]);
            case 'MT11': return $ascSign === 3 && $conj('Mars', 'Rahu') && $house('Mars') === 1 && $conj('Moon', 'Saturn') && $house('Moon') === 5;

            // ---- भ्रातृशाप ----
            case 'BH05': return $house($L1) === 8 && $house('Mars') === 5 && $house($L5) === 8;
            case 'BH10': return $conj($L8, $L3) && $house($L8) === 5 && $conj('Saturn', 'Mars') && $house('Saturn') === 8;

            // ---- मातुलशाप ----
            case 'ML01': return $house('Mercury') === 5 && $house('Jupiter') === 5 && $house('Mars') === 5 && $house('Rahu') === 5 && $house('Saturn') === 1;

            // ---- ब्रह्मशाप ----
            case 'BR04': return $isDebil('Jupiter') && $hIn($house('Rahu'), [1, 5]) && $hIn($house($L5), [6, 8, 12]);
            case 'BR07': return ($house('Saturn') === 1 && $house('Jupiter') === 1 && $house('Rahu') === 9) || $house('Jupiter') === 12;

            // ---- पत्नीशाप ----
            case 'PN02': return $house($L7) === 8 && $house($L5) === 8 && $paapConj('Jupiter');
            case 'PN11': return $house('Rahu') === 1 && $house('Saturn') === 5 && $house('Mars') === 9 && $house($L5) === 8 && $house($L7) === 8;

            // ---- प्रेतशाप ----
            case 'PR04': return $house('Rahu') === 1 && $house('Saturn') === 5 && $house('Jupiter') === 8;
            case 'PR07': return $house('Saturn') === 1 && $house('Rahu') === 5 && $house('Sun') === 8 && $house('Mars') === 12;

            // ---- निःसन्तान ग्रहयोग ----
            case 'NS03': return $hIn($house($L1), [6, 8, 12]) && $hIn($house($L5), [6, 8, 12]) && $isDebil('Jupiter') && ($house('Mercury') === 5 || $house('Saturn') === 5);
            case 'NS04': return $house($L5) === 8 && $house($L8) === 5;

            // ---- विलम्ब पुत्र (मिश्र) ----
            case 'VP02': return $house('Saturn') === 1 && $house('Jupiter') === 8 && $house('Mars') === 12;
            case 'VP03': return $house('Saturn') === 5 && $house('Mercury') === 5 && $house('Jupiter') === 5;

            // ---- दत्तकपुत्र (मिश्र) ----
            case 'DP08': return $L5 === 'Moon' && $hIn($house('Saturn'), [1, 5]) && $strong('Jupiter');
            case 'DP09': return $L5 === 'Sun' && $house('Sun') === 1 && $house('Saturn') === 5 && $house('Mercury') === 5;

            // ---- बहुपुत्र (शुभ) ----
            case 'BP02': {
                $lord = $L5 !== '' ? Charts::signLord($sign($L5)) : '';
                return in_array($lord, ['Jupiter', 'Venus', 'Mercury'], true) && $hIn($house('Jupiter'), [1, 4, 7, 10]);
            }
            case 'BP03': return $house($L1) === 5 && $house($L5) === 1 && $hIn($house('Jupiter'), [1, 4, 5, 7, 9, 10]);
            case 'BP10': {
                if ($isExalt($L5)) { return true; }
                // parivartana: lagnesh in panchamesh's sign and vice-versa.
                return $L1 !== '' && $L5 !== '' && Charts::signLord($sign($L1)) === $L5 && Charts::signLord($sign($L5)) === $L1;
            }
            case 'BP13': return $house($L1) === 7 && $house($L7) === 1 && $house($L2) === 1;

            default: return null;   // reference-only
        }
    }
}
