<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Varshesha (Varsha Lord / year-lord) selection for the Tajik annual chart.
 *
 * Among the five office-bearers (Panchadhikari) the one with the greatest
 * Panchavargeeya Bala is the Varshesha — the convention used by mainstream
 * Vedic software (Parashara's Light, Jagannatha Hora).
 *
 * The five offices:
 *   1. Muntha lord            (lord of the Muntha sign)
 *   2. Varsha Lagna lord      (lord of the annual ascendant)
 *   3. Janma Lagna lord       (lord of the birth ascendant)
 *   4. Trirashi lord          (triplicity lord of the Varsha Lagna, by day/night)
 *   5. Dina-ratri lord        (Sun by a day birth, Moon by a night birth)
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
    private const MALE = ['Sun', 'Mars', 'Jupiter'];
    private const NEUTER = ['Mercury', 'Saturn'];
    private const FEMALE = ['Moon', 'Venus'];

    /**
     * @param array<string,mixed> $varshaChart CalculationEngine::computeChart output
     * @param int $munthaSign      Muntha sign index
     * @param int $varshaLagnaSign annual ascendant sign index
     * @param int $janmaLagnaSign  birth ascendant sign index
     * @return array{lord:string, lord_hi:string, bala:float, candidates:list<array<string,mixed>>}
     */
    public static function compute(array $varshaChart, int $munthaSign, int $varshaLagnaSign, int $janmaLagnaSign): array
    {
        $planets = $varshaChart['planets'] ?? [];
        $isDay = (bool) ($varshaChart['is_day'] ?? true);

        // ---- the five office-bearers (Panchadhikari), in tie-break priority ----
        $offices = [
            ['office' => 'मुन्थेश (मुन्था स्वामी)',       'planet' => Charts::signLord($munthaSign)],
            ['office' => 'वर्ष-लग्नेश (वर्ष लग्न स्वामी)', 'planet' => Charts::signLord($varshaLagnaSign)],
            ['office' => 'जन्म-लग्नेश (जन्म लग्न स्वामी)',  'planet' => Charts::signLord($janmaLagnaSign)],
            ['office' => 'त्रिराशि-पति',                   'planet' => self::trirashiLord($varshaLagnaSign, $isDay)],
            ['office' => 'दिन-रात्रि पति',                 'planet' => $isDay ? 'Sun' : 'Moon'],
        ];

        $candidates = [];
        $seen = [];
        foreach ($offices as $o) {
            $pl = $o['planet'];
            $bala = self::panchavargeeya($pl, $planets);
            $offices_for = $o['office'];
            if (isset($seen[$pl])) {
                // same planet holds more than one office — merge the labels.
                $candidates[$seen[$pl]]['office'] .= ' · ' . $offices_for;
                continue;
            }
            $seen[$pl] = count($candidates);
            $candidates[] = [
                'office' => $offices_for,
                'planet' => $pl,
                'planet_hi' => self::PLANET_HI[$pl] ?? $pl,
                'bala' => round($bala, 2),
            ];
        }

        // Varshesha = greatest Panchavargeeya bala (first office wins on a tie).
        $winIdx = 0;
        foreach ($candidates as $i => $c) {
            if ($c['bala'] > $candidates[$winIdx]['bala']) { $winIdx = $i; }
        }
        $win = $candidates[$winIdx];
        foreach ($candidates as $i => &$c) { $c['is_varshesh'] = $i === $winIdx; }
        unset($c);

        return [
            'lord' => $win['planet'],
            'lord_hi' => $win['planet_hi'],
            'bala' => $win['bala'],
            'candidates' => $candidates,
        ];
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
        $p = $planets[$planet] ?? [];
        $lon = (float) ($p['sidereal_lon'] ?? 0.0);
        $sign = (int) ($p['sign_index'] ?? Charts::signIndex($lon));
        $deg = (float) ($p['deg_in_sign'] ?? Charts::degInSign($lon));

        // 1) Kshetra bala (max 30) — relation to the rashi lord.
        $kshetra = self::relationFraction($planet, Charts::signLord($sign), $planets) * 30.0;

        // 2) Uchcha bala (max 20) — distance from the deep debilitation point.
        $debil = fmod(self::DEEP_EXALT[$planet] + 180.0, 360.0);
        $dist = abs(Charts::norm($lon) - $debil);
        if ($dist > 180.0) { $dist = 360.0 - $dist; }
        $uchcha = $dist / 9.0;

        // 3) Hadda bala (max 15) — relation to the term (hadda) lord.
        $hadda = self::relationFraction($planet, self::haddaLord($sign, $deg), $planets) * 15.0;

        // 4) Drekkana bala (max 10) — gender-appropriate decanate.
        $decan = (int) floor($deg / 10.0);       // 0,1,2
        $want = in_array($planet, self::MALE, true) ? 0 : (in_array($planet, self::NEUTER, true) ? 1 : 2);
        $drekkana = $decan === $want ? 10.0 : 0.0;

        // 5) Navamsa bala (max 5) — relation to the navamsa lord.
        $navLord = Charts::signLord(Charts::navamsaSignIndex($lon));
        $navamsa = self::relationFraction($planet, $navLord, $planets) * 5.0;

        return $kshetra + $uchcha + $hadda + $drekkana + $navamsa;
    }

    private static function haddaLord(int $sign, float $deg): string
    {
        foreach (self::HADDA[$sign] as [$upper, $lord]) {
            if ($deg < $upper) { return $lord; }
        }
        return self::HADDA[$sign][4][1];
    }

    /**
     * Compound (panchadha maitri) relation of $planet to a varga lord, as a
     * fraction of full strength: own 1.0, great-friend .75, friend .5,
     * neutral .25, enemy .125, great-enemy .0625.
     *
     * @param array<string,array<string,mixed>> $planets
     */
    private static function relationFraction(string $planet, string $lord, array $planets): float
    {
        if ($planet === $lord) { return 1.0; }
        $natural = PlanetCondition::naturalRelationDirected($planet, $lord); // F/N/E
        $temp = self::temporaryRelation($planet, $lord, $planets);           // F/E

        // panchadha combination table
        if ($natural === 'F') { $comp = $temp === 'F' ? 'gf' : 'n'; }
        elseif ($natural === 'N') { $comp = $temp === 'F' ? 'f' : 'e'; }
        else { $comp = $temp === 'F' ? 'n' : 'ge'; } // natural enemy

        return ['gf' => 0.75, 'f' => 0.5, 'n' => 0.25, 'e' => 0.125, 'ge' => 0.0625][$comp];
    }

    /** Temporary (tatkalika) friendship inside the chart: 2,3,4,10,11,12 = friend. */
    private static function temporaryRelation(string $a, string $b, array $planets): string
    {
        $sa = (int) ($planets[$a]['sign_index'] ?? 0);
        $sb = (int) ($planets[$b]['sign_index'] ?? 0);
        $dist = (($sb - $sa) % 12 + 12) % 12 + 1; // 1..12
        return in_array($dist, [2, 3, 4, 10, 11, 12], true) ? 'F' : 'E';
    }
}
