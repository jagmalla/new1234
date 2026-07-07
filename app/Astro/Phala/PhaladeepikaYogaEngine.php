<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

/**
 * Phaladeepika yoga engine — detects the reliably-computable subset of the 106
 * yogas against the D1 (rasi) chart and returns the full catalogue grouped by
 * category with a `detected` flag.
 *
 * Uses only unambiguous chart facts: planet sign/house, house-lords, benefic/
 * malefic nature, exalt/own/debil, vargottama (sign == navamsa sign), waxing
 * Moon (pakshabala), day/night birth, directional strength and distinct-sign
 * counts. Yogas needing full Shadbala or subtle multi-clause aspect chains are
 * left as reference (detected = null) — never auto-fired — so no false hits.
 *
 * detected ∈ {true (✓ इस कुंडली में), false (संगणित, नहीं बना), null (सन्दर्भ)}.
 */
final class PhaladeepikaYogaEngine
{
    private const KENDRA = [1, 4, 7, 10];
    private const TRIKONA = [5, 9];
    private const DUSTHANA = [6, 8, 12];
    private const UPACHAYA = [3, 6, 10, 11];
    private const PANAPHARA = [2, 5, 8, 11];
    private const SUSHTHANA = [1, 2, 4, 5, 7, 9, 10, 11];
    private const CLASSICAL = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
    /** 0-indexed own signs, exalt sign, debil sign, exalt-sign lord. */
    private const OWN = ['Sun' => [4], 'Moon' => [3], 'Mars' => [0, 7], 'Mercury' => [2, 5], 'Jupiter' => [8, 11], 'Venus' => [1, 6], 'Saturn' => [9, 10]];
    private const EXALT = ['Sun' => 0, 'Moon' => 1, 'Mars' => 9, 'Mercury' => 5, 'Jupiter' => 3, 'Venus' => 11, 'Saturn' => 6];
    private const DEBIL = ['Sun' => 6, 'Moon' => 7, 'Mars' => 3, 'Mercury' => 11, 'Jupiter' => 9, 'Venus' => 5, 'Saturn' => 0];
    private const EXALT_LORD = ['Sun' => 'Mars', 'Moon' => 'Venus', 'Mars' => 'Saturn', 'Mercury' => 'Mercury', 'Jupiter' => 'Moon', 'Venus' => 'Jupiter', 'Saturn' => 'Venus'];

    /**
     * @param array<string,mixed> $chart CalculationEngine::computeChart output
     * @param array<string,mixed> $rules PhaladeepikaYogaRepository::load output
     * @return array<string,mixed>
     */
    public static function compute(array $chart, array $rules, float $tz = 0.0): array
    {
        $cfg = $rules['config'] ?? [];
        $auto = ($cfg['phala_yoga_autodetect'] ?? '1') === '1';
        $ctx = self::context($chart);
        $yk = Yogakaraka::classify($chart);          // per-lagna planet roles (Adhyaya 32)

        $groups = [];
        $detectedCount = 0;
        $summary = ['shubh' => 0, 'ashubh' => 0, 'mishrit' => 0];
        foreach (($rules['yogas'] ?? []) as $y) {
            $d = $auto ? self::detect((string) $y['id'], $ctx) : null;
            $row = $y + ['detected' => $d];
            if ($d === true) {
                $detectedCount++;
                $summary[$y['type']] = ($summary[$y['type']] ?? 0) + 1;
                $row['phal_dasha'] = self::phalDasha(self::karakas((string) $y['id'], $ctx), $yk, $chart, $tz);
            }
            $groups[$y['cat']][] = $row;
        }

        return [
            'categories' => PhaladeepikaYogaData::CATEGORIES,
            'groups' => $groups,
            'detected_count' => $detectedCount,
            'active_summary' => $summary,
            'yogakaraka' => $yk,
            'total' => count($rules['yogas'] ?? []),
        ];
    }

    /**
     * The planets that FORM a given detected yoga (its karakas) — the yoga
     * fructifies in their Vimshottari dasha.
     *
     * @return list<string>
     */
    private static function karakas(string $id, array $c): array
    {
        /** @var callable $sign */ $sign = $c['sign'];
        /** @var callable $house */ $house = $c['house'];
        /** @var callable $houseFrom */ $houseFrom = $c['houseFrom'];
        /** @var callable $lordOfHouse */ $lordOfHouse = $c['lordOfHouse'];
        /** @var callable $ownExalt */ $ownExalt = $c['ownExalt'];
        $benefics = $c['benefics']; $malefics = $c['malefics'];
        $moonSign = $sign('Moon'); $sunSign = $sign('Sun');
        $inHouse = static function (int $h, array $who) use ($house): array {
            return array_values(array_filter($who, static fn($p) => $house($p) === $h));
        };
        $atFrom = static function (int $fromSign, array $rels, array $who) use ($sign): array {
            $targets = array_map(static fn($r) => ($fromSign + $r - 1) % 12, $rels);
            return array_values(array_filter($who, static fn($p) => in_array($sign($p), $targets, true)));
        };
        $classical = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];

        $out = match (true) {
            $id === 'PMP01' => ['Mars'],
            $id === 'PMP02' => ['Mercury'],
            $id === 'PMP03' => ['Jupiter'],
            $id === 'PMP04' => ['Venus'],
            $id === 'PMP05' => ['Saturn'],
            in_array($id, ['CH01', 'CH03'], true) => array_merge(['Moon'], $atFrom($moonSign, [2, 12], ['Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'])),
            $id === 'CH02' => array_merge(['Moon'], $atFrom($moonSign, [12], ['Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'])),
            $id === 'CH04' => ['Moon'],
            in_array($id, ['SU01', 'SU02', 'SU03'], true) => array_merge(['Sun'], $atFrom($sunSign, [2, 12], $benefics)),
            in_array($id, ['SU04', 'SU05', 'SU06'], true) => array_merge(['Sun'], $atFrom($sunSign, [2, 12], ['Mars', 'Saturn'])),
            $id === 'LG01' => array_merge($inHouse(2, $benefics), $inHouse(12, $benefics)),
            $id === 'LG02' => array_merge($inHouse(2, $malefics), $inHouse(12, $malefics)),
            in_array($id, ['CY01', 'CY02'], true) => ['Jupiter', 'Moon'],
            in_array($id, ['CY03', 'CY04', 'CY05'], true) => ['Moon', 'Sun'],
            in_array($id, ['MB01', 'MB02'], true) => ['Sun', 'Moon'],
            $id === 'VA01' => $benefics,
            $id === 'VA02' => array_merge($inHouse(10, $benefics), $atFrom($moonSign, [10], $benefics)),
            $id === 'ML01' => $benefics,
            $id === 'ML02' => $classical,
            $id === 'AD01' => ['Mercury', 'Jupiter', 'Venus'],
            str_starts_with($id, 'NB') => $classical,
            str_starts_with($id, 'BV') => (function () use ($id, $lordOfHouse, $inHouse, $benefics) {
                $n = (int) substr($id, 2);
                return array_values(array_unique(array_merge($inHouse($n, $benefics), [$lordOfHouse($n)])));
            })(),
            str_starts_with($id, 'DR') => [$lordOfHouse((int) substr($id, 2))],
            $id === 'KP03' => [$lordOfHouse(9), $lordOfHouse(10)],
            $id === 'RY01' => array_values(array_filter($classical, static fn($p) => $ownExalt($p) && in_array($house($p), [1, 4, 7, 10], true))),
            $id === 'RY03' => array_values(array_filter(['Mercury', 'Jupiter', 'Moon', 'Venus', 'Saturn', 'Sun', 'Mars'],
                static fn($p) => $house($p) === ['Mercury' => 1, 'Jupiter' => 1, 'Moon' => 4, 'Venus' => 4, 'Saturn' => 7, 'Sun' => 10, 'Mars' => 10][$p])),
            $id === 'NBR04' => array_values(array_filter($classical, static fn($p) => $sign($p) === (self::DEBIL[$p] ?? -1))),
            default => [],
        };
        return array_values(array_unique(array_filter($out, static fn($p) => $p !== '')));
    }

    /**
     * Build the फल-दशा info for a yoga: each karaka planet + its Adhyaya-32 role
     * and (if found) its Vimshottari Mahadasha window.
     *
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
                'role' => $roles[$p]['role'] ?? 'neutral',
                'role_hi' => $roles[$p]['role_hi'] ?? 'सम',
                'dasha' => $period !== null
                    ? \AutoBusiness\Astro\Time\JulianDay::toDmy($period[0], $tz) . ' – ' . \AutoBusiness\Astro\Time\JulianDay::toDmy($period[1], $tz)
                    : null,
            ];
        }
        return ['planets' => $out, 'note' => 'योग का फल इन ग्रहों की महादशा/अन्तर्दशा में प्रकट होने की सम्भावना (बलाबल-सापेक्ष)।'];
    }

    /** @return array<string,mixed> */
    private static function context(array $chart): array
    {
        $P = $chart['planets'] ?? [];
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $ascNav = (int) ($chart['ascendant']['navamsa_sign'] ?? -1);
        $isDay = (bool) ($chart['is_day'] ?? true);

        $sign = static fn(string $p): int => (int) ($P[$p]['sign_index'] ?? -99);
        $house = static fn(string $p): int => (int) ($P[$p]['house'] ?? 0);
        $nav = static fn(string $p): int => (int) ($P[$p]['navamsa_sign'] ?? -1);
        $retro = static fn(string $p): bool => (bool) ($P[$p]['retro'] ?? false);

        // Waxing (pakshabali) Moon.
        $elong = fmod(((float) ($P['Moon']['sidereal_lon'] ?? 0.0)) - ((float) ($P['Sun']['sidereal_lon'] ?? 0.0)) + 360.0, 360.0);
        $moonWax = $elong >= 90.0 && $elong <= 270.0;

        $isOwn = static fn(string $p): bool => in_array($sign($p), self::OWN[$p] ?? [], true);
        $isExalt = static fn(string $p): bool => $sign($p) === (self::EXALT[$p] ?? -1);
        $isDebil = static fn(string $p): bool => $sign($p) === (self::DEBIL[$p] ?? -1);
        $ownExalt = static fn(string $p): bool => $isOwn($p) || $isExalt($p);
        $benefic = static fn(string $p): bool => in_array($p, ['Jupiter', 'Venus', 'Mercury'], true) || ($p === 'Moon' && $moonWax);
        $benefics = array_values(array_filter(['Jupiter', 'Venus', 'Mercury', 'Moon'], $benefic));
        $malefics = ['Sun', 'Mars', 'Saturn'];
        if (!$moonWax) { $malefics[] = 'Moon'; }

        // House of planet p counted from a reference sign (1..12).
        $houseFrom = static fn(string $p, int $fromSign): int => (($sign($p) - $fromSign) % 12 + 12) % 12 + 1;
        $lordOfHouse = static fn(int $h): string => (string) (($chart['houses'][$h]['lord']) ?? '');

        return [
            'P' => $P, 'ascSign' => $ascSign, 'ascNav' => $ascNav, 'isDay' => $isDay, 'moonWax' => $moonWax,
            'sign' => $sign, 'house' => $house, 'nav' => $nav, 'retro' => $retro,
            'isOwn' => $isOwn, 'isExalt' => $isExalt, 'isDebil' => $isDebil, 'ownExalt' => $ownExalt,
            'benefic' => $benefic, 'benefics' => $benefics, 'malefics' => $malefics,
            'houseFrom' => $houseFrom, 'lordOfHouse' => $lordOfHouse,
            'present' => array_keys($P),
        ];
    }

    /** Curated detection map; null = reference-only. */
    private static function detect(string $id, array $c): ?bool
    {
        /** @var callable $sign */ $sign = $c['sign'];
        /** @var callable $house */ $house = $c['house'];
        /** @var callable $nav */ $nav = $c['nav'];
        /** @var callable $isOwn */ $isOwn = $c['isOwn'];
        /** @var callable $isExalt */ $isExalt = $c['isExalt'];
        /** @var callable $isDebil */ $isDebil = $c['isDebil'];
        /** @var callable $ownExalt */ $ownExalt = $c['ownExalt'];
        /** @var callable $benefic */ $benefic = $c['benefic'];
        /** @var callable $houseFrom */ $houseFrom = $c['houseFrom'];
        /** @var callable $lordOfHouse */ $lordOfHouse = $c['lordOfHouse'];
        $benefics = $c['benefics']; $malefics = $c['malefics'];
        $ascSign = (int) $c['ascSign']; $moonSign = $sign('Moon'); $sunSign = $sign('Sun');
        $inK = static fn(int $h): bool => in_array($h, self::KENDRA, true);

        // Occupants of the sign that is `rel` houses from `fromSign`, among $who.
        $occAt = function (int $fromSign, int $rel, array $who) use ($sign): bool {
            $target = ($fromSign + $rel - 1) % 12;
            foreach ($who as $p) { if ($sign($p) === $target) { return true; } }
            return false;
        };
        $mahapurusha = static fn(string $p): bool => ($isOwn($p) || $isExalt($p)) && $inK($house($p));

        switch ($id) {
            // ---- पंच महापुरुष ----
            case 'PMP01': return $mahapurusha('Mars');
            case 'PMP02': return $mahapurusha('Mercury');
            case 'PMP03': return $mahapurusha('Jupiter');
            case 'PMP04': return $mahapurusha('Venus');
            case 'PMP05': return $mahapurusha('Saturn');
            case 'PMP-BHANGA':
                foreach (['Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'] as $p) {
                    if ($mahapurusha($p) && ($sign($p) === $sunSign || $sign($p) === $moonSign)) { return true; }
                }
                return false;

            // ---- चन्द्र योग (from Moon; exclude Sun + nodes) ----
            case 'CH01': case 'CH02': case 'CH03': case 'CH04': {
                $who = ['Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
                $has2 = $occAt($moonSign, 2, $who);
                $has12 = $occAt($moonSign, 12, $who);
                if ($id === 'CH01') { return $has2 && !$has12; }
                if ($id === 'CH02') { return $has12 && !$has2; }
                if ($id === 'CH03') { return $has2 && $has12; }
                // CH04 Kemadruma (with bhanga): empty 2 & 12, and no planet with
                // the Moon / in kendra from lagna / in kendra from Moon.
                $conjMoon = false; $kL = false; $kM = false;
                foreach ($who as $p) {
                    if ($sign($p) === $moonSign) { $conjMoon = true; }
                    if ($inK($house($p))) { $kL = true; }
                    if ($inK($houseFrom($p, $moonSign))) { $kM = true; }
                }
                return !$has2 && !$has12 && !($conjMoon || $kL || $kM);
            }

            // ---- सूर्य योग (from Sun; exclude Moon) ----
            case 'SU01': return $occAt($sunSign, 2, $benefics);
            case 'SU02': return $occAt($sunSign, 12, $benefics);
            case 'SU03': return $occAt($sunSign, 2, $benefics) && $occAt($sunSign, 12, $benefics);
            case 'SU04': return $occAt($sunSign, 2, ['Mars', 'Saturn']);
            case 'SU05': return $occAt($sunSign, 12, ['Mars', 'Saturn']);
            case 'SU06': return $occAt($sunSign, 2, ['Mars', 'Saturn']) && $occAt($sunSign, 12, ['Mars', 'Saturn']);

            // ---- लग्न योग (Kartari) ----
            case 'LG01': {
                $b2 = false; $b12 = false;
                foreach ($benefics as $p) { if ($house($p) === 2) { $b2 = true; } if ($house($p) === 12) { $b12 = true; } }
                return $b2 && $b12;
            }
            case 'LG02': {
                $m2 = false; $m12 = false;
                foreach ($malefics as $p) { if ($house($p) === 2) { $m2 = true; } if ($house($p) === 12) { $m12 = true; } }
                return $m2 && $m12;
            }

            // ---- अन्य चन्द्र / चन्द्र-सूर्य योग ----
            case 'CY01': return $inK($houseFrom('Jupiter', $moonSign));                 // गजकेसरी
            case 'CY02': return in_array($houseFrom('Jupiter', $moonSign), [6, 8, 12], true) && !$inK($house('Moon'));
            case 'CY03': return $inK($houseFrom('Moon', $sunSign));                     // अधम
            case 'CY04': return in_array($houseFrom('Moon', $sunSign), self::PANAPHARA, true);
            case 'CY05': return in_array($houseFrom('Moon', $sunSign), [3, 6, 9, 12], true);

            // ---- महाभाग्य ----
            case 'MB01': return $c['isDay'] && $sign('Sun') % 2 === 0 && $sign('Moon') % 2 === 0 && $ascSign % 2 === 0;
            case 'MB02': return !$c['isDay'] && $sign('Sun') % 2 === 1 && $sign('Moon') % 2 === 1 && $ascSign % 2 === 1;

            // ---- धन/यश ----
            case 'VA01': {                                                               // वसुमान्
                foreach ($benefics as $p) { if (!in_array($house($p), self::UPACHAYA, true)) { return false; } }
                return $benefics !== [];
            }
            case 'VA02': {                                                               // अमला
                foreach ($benefics as $p) { if ($house($p) === 10 || $houseFrom($p, $moonSign) === 10) { return true; } }
                return false;
            }

            // ---- माला ----
            case 'ML01': {
                foreach ($benefics as $p) { if (!in_array($house($p), [5, 6, 7], true)) { return false; } }
                return $benefics !== [];
            }
            case 'ML02': {
                foreach (self::CLASSICAL as $p) { if (!in_array($house($p), [6, 8, 12], true)) { return false; } }
                return true;
            }

            // ---- अधियोग ----
            case 'AD01': {
                foreach (['Mercury', 'Jupiter', 'Venus'] as $p) {
                    if (!in_array($houseFrom($p, $moonSign), [6, 7, 8], true) && !in_array($house($p), [6, 7, 8], true)) { return false; }
                }
                return true;
            }

            // ---- नाभस योग (distinct sign count of 7 planets) ----
            case 'NB01': case 'NB02': case 'NB03': case 'NB04': case 'NB05': case 'NB06': case 'NB07': {
                $signs = [];
                foreach (self::CLASSICAL as $p) { $signs[$sign($p)] = true; }
                // NB01 veena=7 … NB07 gola=1 distinct signs → required = 8 − k.
                return count($signs) === 8 - (int) substr($id, 2);
            }

            // ---- भाव योग: benefic in house N AND its lord own/exalt in sushthana ----
            case 'BV01': case 'BV02': case 'BV03': case 'BV04': case 'BV05': case 'BV06':
            case 'BV07': case 'BV08': case 'BV09': case 'BV10': case 'BV11': case 'BV12': {
                $n = (int) substr($id, 2);
                $hasBenefic = false;
                foreach ($benefics as $p) { if ($house($p) === $n) { $hasBenefic = true; } }
                $lord = $lordOfHouse($n);
                return $hasBenefic && $lord !== '' && $ownExalt($lord) && in_array($house($lord), self::SUSHTHANA, true);
            }

            // ---- दरिद्र / दुःस्थान-शुभ: lord of house N in a dusthana ----
            case 'DR01': case 'DR02': case 'DR03': case 'DR04': case 'DR05': case 'DR06':
            case 'DR07': case 'DR08': case 'DR09': case 'DR10': case 'DR11': case 'DR12': {
                $n = (int) substr($id, 2);
                $lord = $lordOfHouse($n);
                return $lord !== '' && in_array($house($lord), self::DUSTHANA, true);
            }

            // ---- केन्द्र-त्रिकोण / राजयोग ----
            case 'KP03': {                                                              // नवमेश+दशमेश एक साथ शुभ भाव में
                $l9 = $lordOfHouse(9); $l10 = $lordOfHouse(10);
                return $l9 !== '' && $l10 !== '' && $house($l9) === $house($l10) && in_array($house($l9), self::SUSHTHANA, true);
            }
            case 'RY01': {                                                              // 3+ ग्रह स्व/उच्च केन्द्र में
                $n = 0;
                foreach (self::CLASSICAL as $p) { if ($ownExalt($p) && $inK($house($p))) { $n++; } }
                return $n >= 3;
            }
            case 'RY03': {                                                              // 2+ दिग्बली ग्रह
                $dig = ['Mercury' => 1, 'Jupiter' => 1, 'Moon' => 4, 'Venus' => 4, 'Saturn' => 7, 'Sun' => 10, 'Mars' => 10];
                $n = 0;
                foreach ($dig as $p => $h) { if ($house($p) === $h) { $n++; } }
                return $n >= 2;
            }

            // ---- नीचभंग राजयोग (practical NBR03/04) ----
            case 'NBR04': {
                foreach (self::CLASSICAL as $p) {
                    if (!$isDebil($p)) { continue; }
                    $rashisha = \AutoBusiness\Astro\Calc\Charts::signLord($sign($p));   // lord of the debil sign
                    $ucchanatha = self::EXALT_LORD[$p] ?? '';
                    foreach ([$rashisha, $ucchanatha] as $q) {
                        if ($q === '') { continue; }
                        if ($inK($house($q)) || $inK($houseFrom($q, $moonSign))) { return true; }
                    }
                }
                return false;
            }

            default: return null;   // reference-only
        }
    }
}
