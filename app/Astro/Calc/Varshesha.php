<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Varshesha (Varsha Lord / year-lord) selection for the Tajik annual chart.
 *
 * Among the five office-bearers (Panchadhikari), the Varshesha is the STRONGEST
 * (by Panchavargeeya Bala) that also casts a FRIENDLY Tajika aspect (sneha
 * drishti — 3rd/5th/9th/11th) on the Varsha Lagna; if none of the five aspects
 * the Varsha Lagna, the Muntha lord is the Varshesha by default (Tajika
 * Neelakanthi, Varshesha-adhikara — the same selection mainstream software such
 * as Parashara's Light uses). A stronger office-bearer that does NOT aspect the
 * annual ascendant is therefore skipped.
 *
 * The five offices:
 *   1. Muntha lord            (lord of the Muntha sign)
 *   2. Varsha Lagna lord      (lord of the annual ascendant)
 *   3. Janma Lagna lord       (lord of the birth ascendant)
 *   4. Trirashi lord          (triplicity lord of the Varsha Lagna, by day/night)
 *   5. Dina-ratri lord        (lord of the Sun's sign by a day Varsha Pravesh,
 *                              of the Moon's sign by a night one)
 *
 * Panchavargeeya Bala = Kshetra (30) + Uchcha (20) + Hadda (15) + Drekkana (10)
 * + Navamsa (5) vishwas. Kshetra / Hadda / Navamsa grade the planet's compound
 * (panchadha) relation to the varga lord; Uchcha is distance from debilitation;
 * Drekkana rewards the gender-appropriate decanate.
 */
final class Varshesha
{
    private const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि',
    ];

    /** Egyptian terms (hadda): per sign, list of [upperDeg, lord]. */
    private const HADDA = [
        0  => [[6,'Jupiter'],[12,'Venus'],[20,'Mercury'],[25,'Mars'],[30,'Saturn']],
        1  => [[8,'Venus'],[14,'Mercury'],[22,'Jupiter'],[27,'Saturn'],[30,'Mars']],
        2  => [[6,'Mercury'],[12,'Jupiter'],[17,'Venus'],[24,'Mars'],[30,'Saturn']],
        3  => [[7,'Mars'],[13,'Venus'],[19,'Mercury'],[26,'Jupiter'],[30,'Saturn']],
        4  => [[6,'Jupiter'],[11,'Venus'],[18,'Saturn'],[24,'Mercury'],[30,'Mars']],
        5  => [[7,'Mercury'],[17,'Venus'],[21,'Jupiter'],[28,'Mars'],[30,'Saturn']],
        6  => [[6,'Saturn'],[14,'Mercury'],[21,'Jupiter'],[28,'Venus'],[30,'Mars']],
        7  => [[7,'Mars'],[11,'Venus'],[19,'Mercury'],[24,'Jupiter'],[30,'Saturn']],
        8  => [[12,'Jupiter'],[17,'Venus'],[21,'Mercury'],[26,'Saturn'],[30,'Mars']],
        9  => [[7,'Mercury'],[14,'Jupiter'],[22,'Venus'],[26,'Saturn'],[30,'Mars']],
        10 => [[7,'Mercury'],[13,'Venus'],[20,'Jupiter'],[25,'Mars'],[30,'Saturn']],
        11 => [[12,'Venus'],[16,'Jupiter'],[19,'Mercury'],[28,'Mars'],[30,'Saturn']],
    ];
    /** Deep-exaltation longitude per planet (exalt sign*30 + deep degree). */
    private const DEEP_EXALT = [
        'Sun' => 10.0, 'Moon' => 33.0, 'Mars' => 298.0, 'Mercury' => 165.0,
        'Jupiter' => 95.0, 'Venus' => 357.0, 'Saturn' => 200.0,
    ];

    /**
     * @param array<string,mixed> $varshaChart CalculationEngine::computeChart output
     * @param int $munthaSign      Muntha sign index
     * @param int $varshaLagnaSign annual ascendant sign index
     * @param int $janmaLagnaSign  birth ascendant sign index
     * @return array{lord:string, lord_hi:string, bala:float, bala20:float,
     *   candidates:list<array<string,mixed>>, offices:list<array<string,mixed>>,
     *   table:array<string,array<string,float>>}
     */
    public static function compute(array $varshaChart, int $munthaSign, int $varshaLagnaSign, int $janmaLagnaSign): array
    {
        $planets = $varshaChart['planets'] ?? [];
        $isDay = (bool) ($varshaChart['is_day'] ?? true);

        // Full Panchavargeeya strength table (all 7 planets, PL "Varshaphala
        // strengths" layout) — computed once, reused for every office-bearer.
        $table = [];
        foreach (array_keys(self::PLANET_HI) as $pl) {
            if (isset($planets[$pl])) {
                $table[$pl] = self::components($pl, $planets);
            }
        }

        // ---- the five office-bearers (Panchadhikari), in tie-break priority ----
        $offices = [
            ['key' => 'muntha',       'office' => 'मुन्थेश (मुन्था स्वामी)',        'office_en' => 'Muntha Pati',       'planet' => Charts::signLord($munthaSign)],
            ['key' => 'varsha_lagna', 'office' => 'वर्ष-लग्नेश (वर्ष लग्न स्वामी)', 'office_en' => 'Varsha Lagna Pati', 'planet' => Charts::signLord($varshaLagnaSign)],
            ['key' => 'janma_lagna',  'office' => 'जन्म-लग्नेश (जन्म लग्न स्वामी)',  'office_en' => 'Janma Lagna Pati',  'planet' => Charts::signLord($janmaLagnaSign)],
            ['key' => 'trirashi',     'office' => 'त्रिराशि-पति',                    'office_en' => 'Trirashi Pati',     'planet' => self::trirashiLord($varshaLagnaSign, $isDay)],
            ['key' => 'dinaratri',    'office' => 'दिन-रात्रि पति',                  'office_en' => 'Dinaratri Pati',    'planet' => self::dinaratriLord($planets, $isDay)],
        ];

        $officeRows = [];   // all five rows, PL-style (a planet may repeat)
        $candidates = [];   // merged per planet (existing consumers)
        $seen = [];
        foreach ($offices as $o) {
            $pl = $o['planet'];
            $bala = (float) ($table[$pl]['total'] ?? self::panchavargeeya($pl, $planets));
            $officeRows[] = [
                'key' => $o['key'],
                'office' => $o['office'],
                'office_en' => $o['office_en'],
                'planet' => $pl,
                'planet_hi' => self::PLANET_HI[$pl] ?? $pl,
                'bala' => round($bala, 2),
                // PL prints total ÷ 4 (out of 20) — reuse the table's value so
                // both cards show the identical figure.
                'bala20' => (float) ($table[$pl]['total20'] ?? round($bala / 4.0, 2)),
            ];
            if (isset($seen[$pl])) {
                // same planet holds more than one office — merge the labels.
                $candidates[$seen[$pl]]['office'] .= ' · ' . $o['office'];
                continue;
            }
            $seen[$pl] = count($candidates);
            $candidates[] = [
                'office' => $o['office'],
                'planet' => $pl,
                'planet_hi' => self::PLANET_HI[$pl] ?? $pl,
                'bala' => round($bala, 2),
            ];
        }

        // ---- Varshesha selection (Tajika Neelakanthi, Varshesha-adhikara) ----
        // The year lord is NOT simply the strongest office-bearer. It is the
        // STRONGEST office-bearer that also casts a Tajika aspect (drishti) on the
        // Varsha Lagna. If NONE of the five aspects the Varsha Lagna, the Muntha
        // lord becomes the year lord by default. (This is why mainstream software
        // such as Parashara's Light can pick a weaker Muntha lord over a stronger
        // Lagna lord that does not aspect the annual ascendant.)
        //
        // A planet is eligible only with a FRIENDLY Tajika aspect (sneha drishti)
        // on the Varsha Lagna — the 3rd, 5th, 9th and 11th houses (3-11 and 5-9,
        // mutual). The inimical 4-10 drishti and the 2/6/7/8/12 (no drishti) do
        // NOT qualify, so a Muntha/Lagna lord in the 6th/7th/10th is skipped —
        // matching Parashara's Light (e.g. 2027-28: Venus 7th, Mercury 6th,
        // Saturn 10th all fail → the Muntha lord Venus becomes the year lord).
        $houseFromLagna = static function (string $pl) use ($planets, $varshaLagnaSign): ?int {
            if (!isset($planets[$pl]['sign_index'])) { return null; }
            return (((int) $planets[$pl]['sign_index'] - $varshaLagnaSign) % 12 + 12) % 12 + 1;
        };
        $aspectsLagna = static function (?int $house): bool {
            return $house !== null && in_array($house, [3, 5, 9, 11], true);
        };
        foreach ($candidates as &$c) {
            $c['house'] = $houseFromLagna($c['planet']);
            $c['aspects_lagna'] = $aspectsLagna($c['house']);
        }
        unset($c);

        // Strongest office-bearer that aspects the Varsha Lagna (earlier office —
        // Muntha, then Varsha/Janma Lagna… — wins an exact tie).
        $winIdx = null;
        foreach ($candidates as $i => $c) {
            if (!$c['aspects_lagna']) { continue; }
            if ($winIdx === null || $c['bala'] > $candidates[$winIdx]['bala']) { $winIdx = $i; }
        }
        if ($winIdx === null) {
            // None aspects the lagna → the Muntha lord is the year lord (offices[0]
            // is always the Muntha lord, so it is candidates[0] here).
            $munthaLord = Charts::signLord($munthaSign);
            foreach ($candidates as $i => $c) {
                if ($c['planet'] === $munthaLord) { $winIdx = $i; break; }
            }
            if ($winIdx === null) {          // safety net: strongest overall
                $winIdx = 0;
                foreach ($candidates as $i => $c) {
                    if ($c['bala'] > $candidates[$winIdx]['bala']) { $winIdx = $i; }
                }
            }
        }
        $win = $candidates[$winIdx];
        foreach ($candidates as $i => &$c) { $c['is_varshesh'] = $i === $winIdx; }
        unset($c);
        foreach ($officeRows as &$r) {
            $r['is_varshesh'] = $r['planet'] === $win['planet'];
            $r['house'] = $houseFromLagna($r['planet']);
            $r['aspects_lagna'] = $aspectsLagna($r['house']);
        }
        unset($r);

        return [
            'lord' => $win['planet'],
            'lord_hi' => $win['planet_hi'],
            'bala' => $win['bala'],
            'bala20' => (float) ($table[$win['planet']]['total20'] ?? round($win['bala'] / 4.0, 2)),
            'candidates' => $candidates,
            'offices' => $officeRows,
            'table' => $table,
        ];
    }

    /**
     * Dina-ratri pati — the lord of the reigning luminary's sign: the dispositor
     * of the SUN by a day Varsha Pravesh, of the MOON by a night one (Sun rules
     * the day, Moon the night). Matches Parashara's Light (e.g. a night entry
     * with the Moon in Taurus → Venus), unlike the weekday lord.
     *
     * @param array<string,array<string,mixed>> $planets
     */
    private static function dinaratriLord(array $planets, bool $isDay): string
    {
        $luminary = $isDay ? 'Sun' : 'Moon';
        $lon = (float) ($planets[$luminary]['sidereal_lon'] ?? 0.0);
        $sign = (int) ($planets[$luminary]['sign_index'] ?? Charts::signIndex($lon));
        return Charts::signLord($sign);
    }

    /** Triplicity (Trirashi) lord of a sign, by day/night. */
    private static function trirashiLord(int $sign, bool $isDay): string
    {
        $element = $sign % 4; // 0 fire, 1 earth, 2 air, 3 water
        $day   = ['Sun', 'Venus', 'Saturn', 'Mars'];
        $night = ['Jupiter', 'Moon', 'Mercury', 'Mars'];
        return ($isDay ? $day : $night)[$element];
    }

    /**
     * Panchavargeeya Bala (vishwas) for one planet in the annual chart.
     *
     * @param array<string,array<string,mixed>> $planets
     */
    private static function panchavargeeya(string $planet, array $planets): float
    {
        return self::components($planet, $planets)['total'];
    }

    /**
     * The five Panchavargeeya components for one planet (the "Varshaphala
     * strengths" table of Parashara's Light): Kshetra/Griha 30 + Uchcha 20 +
     * Hadda 15 + Drekkana 10 + Navamsa 5 = 80 vishwas; PL's printed Total is
     * the sum ÷ 4 (out of 20).
     *
     * @param array<string,array<string,mixed>> $planets
     * @return array{kshetra:float,uchcha:float,hadda:float,drekkana:float,navamsa:float,total:float,total20:float}
     */
    public static function components(string $planet, array $planets): array
    {
        $p = $planets[$planet] ?? [];
        $lon = (float) ($p['sidereal_lon'] ?? 0.0);
        $sign = (int) ($p['sign_index'] ?? Charts::signIndex($lon));
        $deg = (float) ($p['deg_in_sign'] ?? Charts::degInSign($lon));

        // 1) Kshetra/Griha bala (max 30) — relation to the rashi lord.
        $kshetra = self::relationFraction($planet, Charts::signLord($sign), $planets) * 30.0;

        // 2) Uchcha bala (max 20) — distance from the deep debilitation point.
        $debil = fmod(self::DEEP_EXALT[$planet] + 180.0, 360.0);
        $dist = abs(Charts::norm($lon) - $debil);
        if ($dist > 180.0) { $dist = 360.0 - $dist; }
        $uchcha = $dist / 9.0;

        // 3) Hadda bala (max 15) — relation to the term (hadda) lord.
        $hadda = self::relationFraction($planet, self::haddaLord($sign, $deg), $planets) * 15.0;

        // 4) Drekkana bala (max 10) — relation to the CHALDEAN decanate lord
        // (Mars,Sun,Venus,Mercury,Moon,Saturn,Jupiter cycling from Aries 0°) —
        // the Tajik drekkana convention Parashara's Light uses here.
        $decan = (int) floor($deg / 10.0);
        $chaldean = ['Mars', 'Sun', 'Venus', 'Mercury', 'Moon', 'Saturn', 'Jupiter'];
        $drekkana = self::relationFraction($planet, $chaldean[($sign * 3 + $decan) % 7], $planets) * 10.0;

        // 5) Navamsa bala (max 5) — relation to the navamsa lord.
        $navLord = Charts::signLord(Charts::navamsaSignIndex($lon));
        $navamsa = self::relationFraction($planet, $navLord, $planets) * 5.0;

        $total = $kshetra + $uchcha + $hadda + $drekkana + $navamsa;
        return [
            'kshetra' => round($kshetra, 2), 'uchcha' => round($uchcha, 2),
            'hadda' => round($hadda, 2), 'drekkana' => round($drekkana, 2),
            'navamsa' => round($navamsa, 2),
            'total' => round($total, 2), 'total20' => round($total / 4.0, 2),
        ];
    }

    private static function haddaLord(int $sign, float $deg): string
    {
        foreach (self::HADDA[$sign] as [$upper, $lord]) {
            if ($deg < $upper) { return $lord; }
        }
        return self::HADDA[$sign][4][1];
    }

    /**
     * TAJIK positional maitri of $planet toward a varga lord, as a fraction of
     * full strength — the Varshaphal convention (Tajik Neelkanthi shloka 13,
     * matched cell-by-cell against Parashara's Light "Varshaphala strengths"):
     * lord is the planet itself → own 1.0; lord placed 3/5/9/11 from the
     * planet → मित्र 0.75; 2/6/8/12 → सम 0.50; 1/4/7/10 → शत्रु 0.25.
     *
     * @param array<string,array<string,mixed>> $planets
     */
    private static function relationFraction(string $planet, string $lord, array $planets): float
    {
        if ($planet === $lord) { return 1.0; }
        $sa = (int) ($planets[$planet]['sign_index'] ?? 0);
        $sb = (int) ($planets[$lord]['sign_index'] ?? 0);
        $dist = (($sb - $sa) % 12 + 12) % 12 + 1; // house of the lord from the planet, 1..12
        if (in_array($dist, [3, 5, 9, 11], true)) { return 0.75; }
        if (in_array($dist, [2, 6, 8, 12], true)) { return 0.5; }
        return 0.25; // 1, 4, 7, 10 — Tajik वैर
    }
}
