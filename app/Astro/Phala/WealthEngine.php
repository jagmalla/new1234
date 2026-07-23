<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\Ashtakavarga;
use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;
use AutoBusiness\Astro\Calc\VimshopakaBala;
use AutoBusiness\Astro\Calc\VimshottariDasha;
use AutoBusiness\Astro\Time\JulianDay;

/**
 * धन-योग (Wealth · Income · Savings · Property · Luck) — computed analysis.
 *
 * Implements the owner's Dhan-Karya manual end-to-end: the twelve wealth-houses
 * and their meanings, bhavesh placement, karakas, dhan/raja/vipreet/daridra
 * yogas, drishti, Ashtakavarga, Shadbala, Bhava-Bala, Vimshopaka, Navamsa and
 * the D2/D4/D9/D10 vargas, dasha, gochar and the recent/upcoming profit-loss
 * method. Uses REAL computed strengths from the engine.
 *
 * Headline deliverable: a 0–10 WEALTH SCALE derived from the manual's own
 * 15-point scorecard (§22), with a legend (0 = lifelong money struggle …
 * 10 = among the world's richest). Every verdict carries its reason.
 * Discipline: never one rule — भाव + भावेश + कारक + बल must agree.
 */
final class WealthEngine
{
    private const PLANETS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];
    private const SEVEN = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];

    private const SIGN_LORD = [
        0 => 'Mars', 1 => 'Venus', 2 => 'Mercury', 3 => 'Moon', 4 => 'Sun', 5 => 'Mercury',
        6 => 'Venus', 7 => 'Mars', 8 => 'Jupiter', 9 => 'Saturn', 10 => 'Saturn', 11 => 'Jupiter',
    ];

    private const HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चन्द्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];

    private const ASPECTS = [
        'Sun' => [7], 'Moon' => [7], 'Mercury' => [7], 'Venus' => [7],
        'Mars' => [4, 7, 8], 'Jupiter' => [5, 7, 9], 'Saturn' => [3, 7, 10],
        'Rahu' => [5, 7, 9], 'Ketu' => [5, 7, 9],
    ];

    private const BENEFIC = ['Jupiter', 'Venus', 'Mercury', 'Moon'];
    private const MALEFIC = ['Sun', 'Mars', 'Saturn', 'Rahu', 'Ketu'];

    /** 0–10 wealth-scale legend. */
    private const SCALE = [
        [0, 'जीवनभर धन-संघर्ष — अत्यंत कठिन'],
        [1, 'बहुत कठिन — सुरक्षित आय व उपाय आवश्यक'],
        [2, 'कठिन — नौकरी/निश्चित आय पर निर्भर'],
        [3, 'औसत से नीचे — बचत हेतु कठोर अनुशासन'],
        [4, 'औसत — आय ठीक, बचत हेतु योजना चाहिए'],
        [5, 'ठीक-ठाक — स्थिर आय, कुछ बचत'],
        [6, 'अच्छी — स्थिर आय, बचत व छोटी संपत्ति'],
        [7, 'समृद्ध — संपत्ति, बचत, व्यवसाय भी सफल'],
        [8, 'बहुत समृद्ध — बड़ा धन व अनेक स्रोत'],
        [9, 'अत्यंत समृद्ध — असाधारण राजयोग-धनयोग'],
        [10, 'शीर्ष धनाढ्य (विश्व के 500–1000) — दुर्लभ योग-संयोग'],
    ];

    private const REMEDY = [
        'Jupiter' => 'गुरुवार व्रत; पीली वस्तु/चने की दाल/हल्दी दान; "ॐ बृं बृहस्पतये नमः"; श्री सूक्त-पाठ।',
        'Venus' => 'शुक्रवार सफेद वस्त्र/चावल/चीनी दान; स्वच्छता व स्त्री-सम्मान; लक्ष्मी-पूजन।',
        'Saturn' => 'शनिवार काली वस्तु/सरसों-तेल/लोहा दान; मज़दूरों-वृद्धों की सेवा; हनुमान-चालीसा।',
        'Mercury' => 'बुधवार हरी वस्तु/मूँग दान; कन्याओं की सहायता; "ॐ बुं बुधाय नमः"।',
        'Sun' => 'रविवार सूर्य को जल; गुड़/गेहूँ दान; आदित्य-हृदय; पिता का सम्मान।',
        'Moon' => 'सोमवार शिव-पूजन; चाँदी/चावल/दूध दान; माता की सेवा।',
        'Mars' => 'मंगलवार हनुमान-उपासना; मसूर/गुड़ दान।',
        'Rahu' => 'राहु-मंत्र; कुत्तों को रोटी; उड़द/कंबल दान; दुर्गा-सप्तशती।',
        'Ketu' => 'गणेश-उपासना; भोजन-दान; काले-सफेद तिल दान।',
    ];

    public static function compute(
        array $chart,
        array $vargas = [],
        ?float $moonLon = null,
        ?float $birthJd = null,
        ?float $nowJd = null,
        float $tz = 0.0,
        ?\AutoBusiness\Astro\Calc\CalculationEngine $engine = null,
        ?array $birth = null
    ): array {
        if (empty($chart['planets']) || empty($chart['ascendant'])) {
            return ['ok' => false, 'error' => 'चार्ट उपलब्ध नहीं'];
        }
        $asc = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $pl = $chart['planets'];

        $house = [];
        $occ = array_fill(1, 12, []);
        foreach (self::PLANETS as $p) {
            if (!isset($pl[$p])) {
                continue;
            }
            $h = (int) ($pl[$p]['house'] ?? 0);
            if ($h >= 1 && $h <= 12) {
                $house[$p] = $h;
                $occ[$h][] = $p;
            }
        }
        $dig = [];
        foreach (self::PLANETS as $p) {
            if (isset($pl[$p])) {
                $dig[$p] = PlanetCondition::resolve($p, $pl, $asc);
            }
        }
        $sid = [];
        foreach (self::PLANETS as $p) {
            if (isset($pl[$p]['sidereal_lon'])) {
                $sid[$p] = (float) $pl[$p]['sidereal_lon'];
            }
        }
        $sid['Lagna'] = (float) ($chart['ascendant']['sidereal_lon'] ?? 0.0);
        $vim = VimshopakaBala::compute($sid);

        $ctx = [
            'asc' => $asc, 'pl' => $pl, 'house' => $house, 'occ' => $occ, 'dig' => $dig,
            'shad' => $chart['shadbala'] ?? [], 'bhava' => $chart['bhava_bala'] ?? [],
            'sav' => self::savMap($chart), 'bav' => self::bavMap($chart),
            'vim' => static fn (string $p): int => (int) ($vim['scores']['Shodashavarga'][$p] ?? 0),
            'lordOf' => static fn (int $h): string => self::SIGN_LORD[($asc + $h - 1) % 12],
        ];
        $ctx['L1'] = ($ctx['lordOf'])(1);
        $ctx['L2'] = ($ctx['lordOf'])(2);
        $ctx['L11'] = ($ctx['lordOf'])(11);
        $ctx['L10'] = ($ctx['lordOf'])(10);
        $ctx['L4'] = ($ctx['lordOf'])(4);

        $scorecard = self::scorecard($ctx, $vargas, $moonLon, $birthJd, $nowJd);
        $scale = self::scale($scorecard['total'], $scorecard['max']);
        $sources = self::sources($ctx, $vargas);
        $savings = self::savings($ctx);
        $houses = self::houses($ctx);
        $drishti = self::drishti($ctx);
        $yoga = self::yogas($ctx);
        $strength = self::strength($ctx, $vargas);
        $vargaNotes = self::vargaNotes($ctx, $vargas);
        $dasha = self::dashaSection($ctx, $moonLon, $birthJd, $nowJd, $tz);
        $timing = self::timing($ctx, $moonLon, $birthJd, $nowJd, $tz, $engine, $chart, $birth);
        $remedies = self::remedies($ctx);
        $conclusion = self::conclusion($scale, $sources, $savings, $timing);

        return [
            'ok' => true, 'lagna_hi' => self::signHi($asc),
            'scale' => $scale, 'scorecard' => $scorecard,
            'sources' => $sources, 'savings' => $savings, 'houses' => $houses,
            'drishti' => $drishti, 'yoga' => $yoga, 'strength' => $strength,
            'varga' => $vargaNotes, 'dasha' => $dasha, 'timing' => $timing,
            'remedies' => $remedies, 'conclusion' => $conclusion,
        ];
    }

    // ------------------------------------------------------------- helpers

    private static function signHi(int $s): string
    {
        return \AutoBusiness\Astro\LalKitab\LalKitabData::signHi(Charts::SIGNS[$s] ?? 'Aries');
    }

    private static function ord(int $h): string
    {
        static $o = [1 => 'लग्न', 2 => '2रे', 3 => '3रे', 4 => '4थे', 5 => '5वें', 6 => '6ठे',
            7 => '7वें', 8 => '8वें', 9 => '9वें', 10 => '10वें', 11 => '11वें', 12 => '12वें'];
        return $o[$h] ?? (string) $h;
    }

    private static function aspectsHouse(array $ctx, string $p, int $target): bool
    {
        $from = $ctx['house'][$p] ?? null;
        if ($from === null) {
            return false;
        }
        foreach (self::ASPECTS[$p] ?? [7] as $k) {
            if (((($from - 1) + ($k - 1)) % 12) + 1 === $target) {
                return true;
            }
        }
        return false;
    }

    private static function connect(array $ctx, string $a, string $b): bool
    {
        $ha = $ctx['house'][$a] ?? null;
        $hb = $ctx['house'][$b] ?? null;
        if ($ha === null || $hb === null) {
            return false;
        }
        if ($ha === $hb) {
            return true;
        }
        if (self::aspectsHouse($ctx, $a, $hb) || self::aspectsHouse($ctx, $b, $ha)) {
            return true;
        }
        $sA = (int) ($ctx['pl'][$a]['sign_index'] ?? -1);
        $sB = (int) ($ctx['pl'][$b]['sign_index'] ?? -1);
        return $sA >= 0 && $sB >= 0 && self::SIGN_LORD[$sA] === $b && self::SIGN_LORD[$sB] === $a;
    }

    private static function linked(array $ctx, string $p, int $h): bool
    {
        if (($ctx['house'][$p] ?? 0) === $h) {
            return true;
        }
        if (self::aspectsHouse($ctx, $p, $h)) {
            return true;
        }
        $lord = ($ctx['lordOf'])($h);
        return $lord !== $p && self::connect($ctx, $p, $lord);
    }

    private static function ratio(array $ctx, string $p): float
    {
        return (float) ($ctx['shad'][$p]['ratio'] ?? 0.0);
    }

    private static function savMap(array $chart): array
    {
        $signs = [];
        foreach (self::SEVEN as $p) {
            if (isset($chart['planets'][$p]['sign_index'])) {
                $signs[$p] = (int) $chart['planets'][$p]['sign_index'];
            }
        }
        $signs['Lagna'] = (int) ($chart['ascendant']['sign_index'] ?? 0);
        return Ashtakavarga::compute($signs)['sav'] ?? array_fill(0, 12, 0);
    }

    private static function bavMap(array $chart): array
    {
        $signs = [];
        foreach (self::SEVEN as $p) {
            if (isset($chart['planets'][$p]['sign_index'])) {
                $signs[$p] = (int) $chart['planets'][$p]['sign_index'];
            }
        }
        $signs['Lagna'] = (int) ($chart['ascendant']['sign_index'] ?? 0);
        return Ashtakavarga::compute($signs)['bav'] ?? [];
    }

    /** SAV points of a house (by house number from lagna). */
    private static function savHouse(array $ctx, int $h): int
    {
        return (int) ($ctx['sav'][($ctx['asc'] + $h - 1) % 12] ?? 0);
    }

    /** Bhava-Bala rupa of a house. */
    private static function bhavaRupa(array $ctx, int $h): float
    {
        return (float) ($ctx['bhava'][$h]['rupa'] ?? 0.0);
    }

    private static function isStrong(array $ctx, string $p): bool
    {
        return in_array($ctx['dig'][$p]['dignity']['tier'] ?? '', ['param_uchcha', 'exalt', 'moolatrikona', 'own', 'great_friend', 'friend'], true)
            || self::ratio($ctx, $p) >= 1.0;
    }

    private static function isAfflicted(array $ctx, string $p): bool
    {
        $tier = $ctx['dig'][$p]['dignity']['tier'] ?? '';
        $combust = ($ctx['dig'][$p]['combust']['pct'] ?? 0) >= 40;
        return in_array($tier, ['debil', 'enemy', 'great_enemy'], true) || $combust
            || in_array($ctx['house'][$p] ?? 0, [6, 8, 12], true);
    }

    private static function vargaSignOf(array $varga, string $planet): ?int
    {
        foreach ($varga['planets'] ?? [] as $p) {
            if ($p['name'] === $planet) {
                return (int) $p['sign'];
            }
        }
        return null;
    }

    private static function vargaHouseOf(array $varga, string $planet): ?int
    {
        if (empty($varga['planets'])) {
            return null;
        }
        $ascSign = (int) ($varga['asc_sign'] ?? 0);
        foreach ($varga['planets'] as $p) {
            if ($p['name'] === $planet) {
                return ((((int) $p['sign'] - $ascSign) % 12) + 12) % 12 + 1;
            }
        }
        return null;
    }

    /** 0..5 strength of a house from Bhava-Bala rank + SAV + benefic occupant/aspect. */
    private static function houseScore(array $ctx, int $h): float
    {
        $s = 0.0;
        $sav = self::savHouse($ctx, $h);
        $s += $sav >= 30 ? 2.0 : ($sav >= 28 ? 1.5 : ($sav >= 25 ? 1.0 : ($sav >= 22 ? 0.5 : 0.0)));
        $rupa = self::bhavaRupa($ctx, $h);
        $s += $rupa >= 9.0 ? 1.5 : ($rupa >= 8.33 ? 1.0 : ($rupa >= 7.0 ? 0.5 : 0.0));
        // benefic occupant / aspect
        foreach ($ctx['occ'][$h] ?? [] as $p) {
            if (in_array($p, self::BENEFIC, true) && !self::isAfflicted($ctx, $p)) {
                $s += 0.7;
            }
        }
        foreach (['Jupiter', 'Venus', 'Mercury'] as $b) {
            if (self::aspectsHouse($ctx, $b, $h) && !self::isAfflicted($ctx, $b)) {
                $s += 0.4;
            }
        }
        // lord well placed
        $lord = ($ctx['lordOf'])($h);
        if (self::isStrong($ctx, $lord) && !in_array($ctx['house'][$lord] ?? 0, [6, 8, 12], true)) {
            $s += 0.8;
        }
        return min(5.0, round($s, 1));
    }

    // --------------------------------------------------------- 15-point scorecard

    private static function scorecard(array $ctx, array $vargas, ?float $moonLon, ?float $birthJd, ?float $nowJd): array
    {
        $rows = [];
        $add = static function (string $label, float $pt, string $note) use (&$rows): void {
            $rows[] = ['label' => $label, 'pt' => round(min(5.0, max(0.0, $pt)), 1), 'note' => $note];
        };
        $L2 = $ctx['L2']; $L11 = $ctx['L11']; $L10 = $ctx['L10'];

        // 1 2nd house
        $add('2रा भाव (संचय) का बल', self::houseScore($ctx, 2), 'SAV ' . self::savHouse($ctx, 2) . ', भाव-बल ' . round(self::bhavaRupa($ctx, 2), 1) . ' रूप');
        // 2 11th house
        $add('11वाँ भाव (आय) का बल', self::houseScore($ctx, 11), 'SAV ' . self::savHouse($ctx, 11) . ', भाव-बल ' . round(self::bhavaRupa($ctx, 11), 1) . ' रूप');
        // 3 10th + dashamesh
        $add('10वाँ भाव व दशमेश', min(5.0, self::houseScore($ctx, 10) * 0.7 + (self::isStrong($ctx, $L10) ? 1.5 : 0.0)), 'दशमेश ' . self::HI[$L10] . ' ' . self::ord($ctx['house'][$L10] ?? 0) . ' भाव में');
        // 4 dhanesh placement
        $add('धनेश की स्थिति', self::bhaveshPlaceScore($ctx, $L2), 'धनेश ' . self::HI[$L2] . ' ' . self::ord($ctx['house'][$L2] ?? 0) . ' भाव में');
        // 5 labhesh placement
        $add('लाभेश की स्थिति', self::bhaveshPlaceScore($ctx, $L11), 'लाभेश ' . self::HI[$L11] . ' ' . self::ord($ctx['house'][$L11] ?? 0) . ' भाव में');
        // 6 Jupiter & Venus strength
        $jv = (self::isStrong($ctx, 'Jupiter') ? 2.5 : (self::isAfflicted($ctx, 'Jupiter') ? 0.0 : 1.0))
            + (self::isStrong($ctx, 'Venus') ? 2.5 : (self::isAfflicted($ctx, 'Venus') ? 0.0 : 1.0));
        $add('गुरु व शुक्र का बल', min(5.0, $jv), 'धन-कारक गुरु व शुक्र की गरिमा');
        // 7 dhan/raja yoga count
        $yc = self::yogaCount($ctx);
        $add('धन/राज-योगों की संख्या व शुद्धता', min(5.0, $yc * 1.2), $yc . ' शुभ धन/राज-योग');
        // 8 Ashtakvarga 11 > 12
        $s11 = self::savHouse($ctx, 11); $s12 = self::savHouse($ctx, 12);
        $add('अष्टकवर्ग: 11वें > 12वें?', $s11 > $s12 + 3 ? 5.0 : ($s11 > $s12 ? 3.5 : ($s11 === $s12 ? 2.0 : 0.5)), '11वें ' . $s11 . ' बनाम 12वें ' . $s12 . ' अंक');
        // 9 Ashtakvarga 2,10,11 30+
        $c30 = ((self::savHouse($ctx, 2) >= 30 ? 1 : 0) + (self::savHouse($ctx, 10) >= 30 ? 1 : 0) + (self::savHouse($ctx, 11) >= 30 ? 1 : 0));
        $add('अष्टकवर्ग: 2/10/11 में 30+', $c30 * (5.0 / 3.0), $c30 . '/3 अर्थ-त्रिकोण भाव 30+ अंक पर');
        // 10 Shadbala of dhanesh/labhesh/dashamesh
        $sb = 0.0;
        foreach ([$L2, $L11, $L10] as $p) {
            $sb += self::ratio($ctx, $p) >= 1.0 ? (5.0 / 3.0) : (self::ratio($ctx, $p) >= 0.8 ? (2.5 / 3.0) : 0.0);
        }
        $add('षड्बल: धनेश/लाभेश/दशमेश न्यूनतम से ऊपर?', min(5.0, $sb), 'शड्बल अनुपात ≥1 = पूर्ण बल');
        // 11 D9 of dhanesh/labhesh/dashamesh
        $add('D9 में धनेश/लाभेश/दशमेश', self::vargaDignityScore($ctx, $vargas['D9'] ?? null, [$L2, $L11, $L10]), 'नवांश में तीनों की गरिमा');
        // 12 D10 10th & 11th
        $add('D10 में 10वें व 11वें भाव', self::d10Score($ctx, $vargas['D10'] ?? null), 'दशांश में कर्म व आय-भाव');
        // 13 D2 Hora — Chandra hora
        $add('D2 होरा — ग्रह चन्द्र-होरा में', self::horaScore($ctx, $vargas['D2'] ?? null), 'चन्द्र-होरा = बचत की प्रवृत्ति');
        // 14 6/8/12 pressure low
        $duh = self::savHouse($ctx, 6) + self::savHouse($ctx, 8) + self::savHouse($ctx, 12);
        $arth = self::savHouse($ctx, 2) + self::savHouse($ctx, 10) + self::savHouse($ctx, 11);
        $add('6/8/12 का दबाव कम है?', $arth > $duh + 12 ? 5.0 : ($arth > $duh ? 3.5 : ($arth === $duh ? 2.0 : 0.5)), 'अर्थ-त्रिकोण ' . $arth . ' बनाम दुःस्थान ' . $duh . ' अंक');
        // 15 current/upcoming dasha
        $add('वर्तमान/आगामी दशा की अनुकूलता', self::dashaScore($ctx, $moonLon, $birthJd, $nowJd), 'दशा-स्वामी का धन-भावों से संबंध');

        $total = 0.0;
        foreach ($rows as $r) {
            $total += $r['pt'];
        }
        return ['rows' => $rows, 'total' => round($total, 1), 'max' => count($rows) * 5.0];
    }

    private static function bhaveshPlaceScore(array $ctx, string $p): float
    {
        $h = $ctx['house'][$p] ?? 0;
        $base = in_array($h, [1, 2, 5, 9, 10, 11], true) ? 4.0 : (in_array($h, [4, 7, 3], true) ? 3.0 : (in_array($h, [6, 8, 12], true) ? 1.0 : 2.0));
        if (self::isStrong($ctx, $p)) {
            $base += 1.0;
        }
        if (self::isAfflicted($ctx, $p) && !in_array($h, [1, 2, 5, 9, 10, 11], true)) {
            $base -= 1.0;
        }
        return max(0.0, min(5.0, $base));
    }

    private static function yogaCount(array $ctx): int
    {
        return count(array_filter(self::yogas($ctx), static fn ($y) => $y['tone'] === 'pos'));
    }

    private static function vargaDignityScore(array $ctx, ?array $varga, array $planets): float
    {
        if ($varga === null) {
            return 2.5;
        }
        $ascSign = (int) ($varga['asc_sign'] ?? 0);
        $good = 0;
        foreach ($planets as $p) {
            $s = self::vargaSignOf($varga, $p);
            if ($s === null) {
                continue;
            }
            $tier = PlanetCondition::dignity($p, $s, 0.0, [], $ascSign)['tier'] ?? '';
            if (in_array($tier, ['param_uchcha', 'exalt', 'moolatrikona', 'own', 'great_friend', 'friend'], true)) {
                $good++;
            }
        }
        return $good * (5.0 / 3.0);
    }

    private static function d10Score(array $ctx, ?array $d10): float
    {
        if ($d10 === null) {
            return 2.5;
        }
        $ascSign = (int) ($d10['asc_sign'] ?? 0);
        $tenthLord = self::SIGN_LORD[($ascSign + 9) % 12];
        $eleventhLord = self::SIGN_LORD[($ascSign + 10) % 12];
        $s = 0.0;
        foreach ([$tenthLord, $eleventhLord] as $p) {
            $h = self::vargaHouseOf($d10, $p);
            if ($h !== null && in_array($h, [1, 2, 4, 5, 7, 9, 10, 11], true)) {
                $s += 2.5;
            }
        }
        return min(5.0, $s);
    }

    private static function horaScore(array $ctx, ?array $d2): float
    {
        if ($d2 === null || empty($d2['planets'])) {
            return 2.5;
        }
        // Cancer(3) = Chandra hora (savings), Leo(4) = Surya hora (spending)
        $chandra = 0; $total = 0;
        foreach ($d2['planets'] as $p) {
            if (!in_array($p['name'], self::SEVEN, true)) {
                continue;
            }
            $total++;
            if ((int) $p['sign'] === 3) {
                $chandra++;
            }
        }
        if ($total === 0) {
            return 2.5;
        }
        return round($chandra / $total * 5.0, 1);
    }

    private static function dashaScore(array $ctx, ?float $moonLon, ?float $birthJd, ?float $nowJd): float
    {
        if ($moonLon === null || $birthJd === null || $nowJd === null) {
            return 2.5;
        }
        $chain = VimshottariDasha::runningChain($moonLon, $birthJd, $nowJd);
        $s = 0.0;
        foreach ([$chain['maha']['lord'] ?? null, $chain['antar']['lord'] ?? null] as $lord) {
            if ($lord === null) {
                continue;
            }
            $good = false;
            foreach ([2, 10, 11, 5, 9] as $hh) {
                if (self::linked($ctx, $lord, $hh)) {
                    $good = true;
                }
            }
            $bad = in_array($ctx['house'][$lord] ?? 0, [6, 8, 12], true);
            $s += ($good ? 2.5 : 1.0) - ($bad ? 1.0 : 0.0);
        }
        return max(0.0, min(5.0, $s));
    }

    // --------------------------------------------------------- 0-10 scale

    private static function scale(float $total, float $max): array
    {
        $score10 = $max > 0 ? round($total / $max * 10.0, 1) : 0.0;
        $idx = (int) round($score10);
        $idx = max(0, min(10, $idx));
        $band = self::SCALE[$idx][1];
        $tone = $score10 >= 7 ? 'pos' : ($score10 >= 4 ? 'info' : 'neg');
        return [
            'score' => $score10, 'raw' => $total, 'max' => $max, 'band' => $band, 'tone' => $tone,
            'legend' => array_map(static fn ($x) => ['n' => $x[0], 'text' => $x[1]], self::SCALE),
            'text' => 'धन-पैमाने पर स्थिति: ' . $score10 . '/10 — ' . $band
                . '। (यह 15-सूत्रीय गणना (§22) का सापेक्ष अंक है; धन की मात्रा (रुपये) नहीं, तुलनात्मक स्थिति बताता है।)',
        ];
    }

    // --------------------------------------------------------- wealth sources

    private static function sources(array $ctx, array $vargas): array
    {
        $defs = [
            ['key' => 'salary', 'name' => 'वेतन / नौकरी की आय', 'main' => 10, 'sup' => [6, 2, 11], 'kar' => ['Sun', 'Saturn', 'Mercury']],
            ['key' => 'business', 'name' => 'व्यवसाय / व्यापार का लाभ', 'main' => 7, 'sup' => [10, 11, 3], 'kar' => ['Mercury', 'Venus', 'Mars']],
            ['key' => 'savings', 'name' => 'संचित धन / बचत', 'main' => 2, 'sup' => [11, 4], 'kar' => ['Jupiter', 'Venus']],
            ['key' => 'income', 'name' => 'आय का प्रवाह (सब स्रोत)', 'main' => 11, 'sup' => [2, 10, 5], 'kar' => ['Jupiter', 'Mercury']],
            ['key' => 'property', 'name' => 'अचल संपत्ति — घर/ज़मीन', 'main' => 4, 'sup' => [2, 11, 12], 'kar' => ['Mars', 'Venus', 'Saturn']],
            ['key' => 'inherit', 'name' => 'पैतृक संपत्ति / विरासत', 'main' => 8, 'sup' => [4, 2, 9], 'kar' => ['Saturn', 'Mars', 'Ketu']],
            ['key' => 'spouse', 'name' => 'जीवनसाथी/परिवार से धन', 'main' => 8, 'sup' => [7, 11, 2], 'kar' => ['Venus', 'Jupiter']],
            ['key' => 'selfmade', 'name' => 'स्वयं की मेहनत से धन', 'main' => 10, 'sup' => [1, 3, 6, 11], 'kar' => ['Sun', 'Mars', 'Saturn']],
            ['key' => 'luck', 'name' => 'भाग्य/अचानक धन, लॉटरी-सट्टा', 'main' => 5, 'sup' => [8, 9, 11, 2], 'kar' => ['Jupiter', 'Rahu', 'Venus']],
        ];
        $out = [];
        foreach ($defs as $d) {
            $s = self::houseScore($ctx, $d['main']);   // 0..5
            $sup = 0.0;
            foreach ($d['sup'] as $hh) {
                if (self::savHouse($ctx, $hh) >= 28) {
                    $sup += 0.4;
                }
            }
            $kar = 0.0;
            foreach ($d['kar'] as $kp) {
                if (self::isStrong($ctx, $kp)) {
                    $kar += 0.6;
                }
            }
            // main-lord linked to arth houses
            $mainLord = ($ctx['lordOf'])($d['main']);
            $link = 0.0;
            foreach ([2, 11] as $hh) {
                if (self::linked($ctx, $mainLord, $hh)) {
                    $link += 0.5;
                }
            }
            $total = min(10.0, ($s / 5.0 * 5.0) + $sup + $kar + $link);
            $level = $total >= 6.5 ? 'प्रबल' : ($total >= 4.0 ? 'मध्यम' : ($total >= 2.0 ? 'सामान्य' : 'दुर्बल'));
            $tone = $total >= 6.5 ? 'pos' : ($total >= 4.0 ? 'info' : 'neg');
            // special: luck/lottery requires 5+8+11+2 convergence
            if ($d['key'] === 'luck') {
                $conv = 0;
                foreach ([['from' => 5, 'to' => 8], ['from' => 5, 'to' => 11], ['from' => 5, 'to' => 2], ['from' => 8, 'to' => 11]] as $pair) {
                    if (self::connect($ctx, ($ctx['lordOf'])($pair['from']), ($ctx['lordOf'])($pair['to']))) {
                        $conv++;
                    }
                }
                $level = $conv >= 3 ? 'प्रबल (5+8+11+2 संबंध)' : ($conv >= 1 ? 'सीमित — पूर्ण संयोग नहीं' : 'दुर्बल — जुए/सट्टे से बचें');
                $tone = $conv >= 3 ? 'pos' : ($conv >= 1 ? 'info' : 'neg');
            }
            $out[] = [
                'key' => $d['key'], 'name' => $d['name'], 'main_ord' => self::ord($d['main']),
                'level' => $level, 'tone' => $tone, 'score' => round($total, 1),
                'kar' => implode(', ', array_map(fn ($x) => self::HI[$x], $d['kar'])),
            ];
        }
        // sort strongest first
        usort($out, static fn ($a, $b) => $b['score'] <=> $a['score']);
        return $out;
    }

    // --------------------------------------------------------- savings = income - expenses

    private static function savings(array $ctx): array
    {
        $s11 = self::savHouse($ctx, 11); $s12 = self::savHouse($ctx, 12);
        $s2 = self::savHouse($ctx, 2); $s6 = self::savHouse($ctx, 6); $s8 = self::savHouse($ctx, 8);
        $incVsExp = $s11 - $s12;
        $saveVsDebt = $s2 - (($s6 + $s8) / 2.0);
        $verdict = ($incVsExp > 0 && $saveVsDebt > 0) ? 'बचत बनती है' : (($incVsExp < 0) ? 'कमाई खर्च में चली जाती है (हाथ में पानी)' : 'बचत सीमित — अनुशासन आवश्यक');
        $tone = ($incVsExp > 0 && $saveVsDebt > 0) ? 'pos' : (($incVsExp < 0) ? 'neg' : 'info');
        return [
            'verdict' => $verdict, 'tone' => $tone,
            's11' => $s11, 's12' => $s12, 's2' => $s2, 's6' => $s6, 's8' => $s8,
            'text' => 'बचत = आय − खर्च। अष्टकवर्ग से: 11वाँ (आय) ' . $s11 . ' बनाम 12वाँ (व्यय) ' . $s12
                . ($s11 > $s12 ? ' → आय अधिक, बचत संभव।' : ' → व्यय अधिक, बचत कठिन।')
                . ' 2रा (संचय) ' . $s2 . ' बनाम 6+8 (कर्ज़-हानि) औसत ' . round(($s6 + $s8) / 2.0, 1)
                . ($saveVsDebt > 0 ? ' → धन टिकता है।' : ' → कर्ज़/हानि दबाव डालते हैं।'),
        ];
    }

    // --------------------------------------------------------- houses + bhavesh

    private static function houses(array $ctx): array
    {
        static $place2 = [
            1 => 'स्वयं की पहचान/मेहनत से धन', 2 => 'धन-संचय अच्छा, बचत बनती है', 5 => 'निवेश/शेयर/रचनात्मक आय',
            9 => 'भाग्य से धन', 10 => 'नौकरी/पेशे से मुख्य आय', 11 => 'आय सीधे बचत में — उत्तम धन-योग',
            4 => 'संपत्ति/वाहन में निवेश', 7 => 'विवाह के बाद/व्यापार से धन', 3 => 'प्रयास/संचार से धन',
            6 => 'आय पर कर्ज़/EMI का दबाव, या सेवा से आय', 8 => 'विरासत/बीमा/ससुराल से धन, उतार-चढ़ाव', 12 => 'धन खर्च/विदेश/दान में — बचत कठिन',
        ];
        $mk = static function (int $h, string $L, string $role) use ($ctx, $place2): array {
            $ph = $ctx['house'][$L] ?? 0;
            return ['role' => $role, 'planet' => self::HI[$L], 'house_ord' => self::ord($ph),
                'effect' => $place2[$ph] ?? '', 'strong' => self::isStrong($ctx, $L), 'afflicted' => self::isAfflicted($ctx, $L)];
        };
        return [
            'arth_trikon' => '2रा (संचय) SAV ' . self::savHouse($ctx, 2) . ' · 10वाँ (कर्म) SAV ' . self::savHouse($ctx, 10) . ' · 11वाँ (आय) SAV ' . self::savHouse($ctx, 11) . ' — यही "अर्थ-त्रिकोण" है; तीनों 30+ = आर्थिक रूप से मज़बूत।',
            'dhanesh' => $mk(2, $ctx['L2'], 'धनेश (2रे का स्वामी)'),
            'labhesh' => $mk(11, $ctx['L11'], 'लाभेश (11वें का स्वामी)'),
            'dashamesh' => $mk(10, $ctx['L10'], 'दशमेश (10वें का स्वामी)'),
            'chaturthesh' => $mk(4, $ctx['L4'], 'चतुर्थेश (संपत्ति स्वामी)'),
        ];
    }

    // --------------------------------------------------------- drishti

    private static function drishti(array $ctx): array
    {
        $out = [];
        if (self::aspectsHouse($ctx, 'Jupiter', 2) || self::aspectsHouse($ctx, 'Jupiter', 11) || (($ctx['house']['Jupiter'] ?? 0) === 2) || (($ctx['house']['Jupiter'] ?? 0) === 11)) {
            $out[] = ['tone' => 'pos', 'text' => 'गुरु की दृष्टि/स्थिति 2रे या 11वें भाव पर — धन हेतु सर्वश्रेष्ठ; गुरु जिस भाव को देखे उसकी रक्षा व वृद्धि करता है।'];
        }
        if (self::aspectsHouse($ctx, 'Saturn', 2) || self::aspectsHouse($ctx, 'Saturn', 11)) {
            $out[] = ['tone' => 'info', 'text' => 'शनि की दृष्टि 2रे/11वें पर — आय आएगी पर देर से व मेहनत से; कंजूसी की प्रवृत्ति भी।'];
        }
        if (self::aspectsHouse($ctx, 'Mars', 2)) {
            $out[] = ['tone' => 'info', 'text' => 'मंगल की दृष्टि 2रे पर — खर्चीली प्रवृत्ति/कर्ज़, पर संपत्ति-तकनीक से कमाई।'];
        }
        if (self::aspectsHouse($ctx, 'Rahu', 11)) {
            $out[] = ['tone' => 'info', 'text' => 'राहु की दृष्टि 11वें पर — अचानक बड़ी आय, पर अनिश्चितता।'];
        }
        // papakartari on 2 or 11
        foreach ([2, 11] as $hh) {
            $prev = (($ctx['asc'] + $hh - 2) % 12 + 12) % 12; $prevH = ($hh - 1) === 0 ? 12 : $hh - 1;
            $nextH = $hh === 12 ? 1 : $hh + 1;
            $malPrev = false; $malNext = false;
            foreach ($ctx['occ'][$prevH] ?? [] as $p) {
                if (in_array($p, self::MALEFIC, true)) {
                    $malPrev = true;
                }
            }
            foreach ($ctx['occ'][$nextH] ?? [] as $p) {
                if (in_array($p, self::MALEFIC, true)) {
                    $malNext = true;
                }
            }
            if ($malPrev && $malNext) {
                $out[] = ['tone' => 'neg', 'text' => self::ord($hh) . ' भाव पर पापकर्तरी (दोनों ओर पाप ग्रह) — धन बहुत रुकता है/आते ही निकल जाता है।'];
            }
        }
        if ($out === []) {
            $out[] = ['tone' => 'info', 'text' => 'धन-भावों (2/11) पर कोई विशेष शुभ/अशुभ दृष्टि-योग नहीं — भावेश व बल निर्णायक।'];
        }
        return $out;
    }

    // --------------------------------------------------------- yogas

    private static function yogas(array $ctx): array
    {
        $asc = $ctx['asc'];
        $L1 = $ctx['L1']; $L2 = $ctx['L2']; $L11 = $ctx['L11'];
        $L9 = self::SIGN_LORD[($asc + 8) % 12]; $L10 = $ctx['L10']; $L5 = self::SIGN_LORD[($asc + 4) % 12];
        $L6 = self::SIGN_LORD[($asc + 5) % 12]; $L8 = self::SIGN_LORD[($asc + 7) % 12]; $L12 = self::SIGN_LORD[($asc + 11) % 12];
        $out = [];
        // Dhan yoga: 2L-11L relation
        if (self::connect($ctx, $L2, $L11)) {
            $out[] = ['tone' => 'pos', 'name' => 'धन योग', 'text' => 'धनेश ' . self::HI[$L2] . ' व लाभेश ' . self::HI[$L11] . ' का संबंध — संचय व आय दोनों मज़बूत।'];
        }
        // Parivartan (2-11 exchange)
        $s2 = (int) ($ctx['pl'][$L2]['sign_index'] ?? -1); $s11 = (int) ($ctx['pl'][$L11]['sign_index'] ?? -1);
        if ($s2 >= 0 && $s11 >= 0 && self::SIGN_LORD[$s2] === $L11 && self::SIGN_LORD[$s11] === $L2) {
            $out[] = ['tone' => 'pos', 'name' => 'धन-परिवर्तन योग', 'text' => 'धनेश-लाभेश की राशि-अदला-बदली — अत्यंत बलवान धन-योग।'];
        }
        // Dharma-Karma-Adhipati
        if (self::connect($ctx, $L9, $L10)) {
            $out[] = ['tone' => 'pos', 'name' => 'धर्म-कर्माधिपति राजयोग', 'text' => 'नवमेश ' . self::HI[$L9] . ' + दशमेश ' . self::HI[$L10] . ' — सर्वश्रेष्ठ राजयोग: पद व धन दोनों।'];
        }
        // Gajakesari
        if (self::gajakesari($ctx)) {
            $out[] = ['tone' => 'pos', 'name' => 'गजकेसरी योग', 'text' => 'गुरु चन्द्र से केंद्र में — बुद्धि, प्रतिष्ठा, धन, समाज में मान।'];
        }
        // Chandra-Mangala
        if (self::connect($ctx, 'Moon', 'Mars')) {
            $h = $ctx['house']['Moon'] ?? 0;
            $good = in_array($h, [2, 11, 5, 9], true);
            $out[] = ['tone' => $good ? 'pos' : 'info', 'name' => 'चन्द्र-मंगल योग', 'text' => 'चन्द्र-मंगल संबंध — तेज़ कमाई (खर्च भी)।' . ($good ? ' शुभ भाव में — उत्तम।' : ' 6/8/12 में हो तो तनाव-खर्च भी।')];
        }
        // Lagnesh in dhan houses
        if (in_array($ctx['house'][$L1] ?? 0, [2, 5, 9, 10, 11], true)) {
            $out[] = ['tone' => 'pos', 'name' => 'स्व-निर्मित धन', 'text' => 'लग्नेश ' . self::HI[$L1] . ' ' . self::ord($ctx['house'][$L1]) . ' भाव में — अपनी मेहनत से धनी बनने का योग।'];
        }
        // Vipreet Rajayoga (Harsha/Sarala/Vimala)
        foreach ([['L' => $L6, 'n' => 'हर्ष योग (6ठे का स्वामी 6/8/12 में)'], ['L' => $L8, 'n' => 'सरल योग (8वें का स्वामी 6/8/12 में)'], ['L' => $L12, 'n' => 'विमल योग (12वें का स्वामी 6/8/12 में)']] as $v) {
            if (in_array($ctx['house'][$v['L']] ?? 0, [6, 8, 12], true)) {
                $out[] = ['tone' => 'pos', 'name' => 'विपरीत राजयोग — ' . $v['n'], 'text' => 'संकट/मंदी के दौर में विशेष सफलता व लाभ का योग।'];
            }
        }
        // Daridra: labhesh/dhanesh in 6/8/12 afflicted
        foreach ([['L' => $L11, 'r' => 'लाभेश'], ['L' => $L2, 'r' => 'धनेश']] as $d) {
            if (in_array($ctx['house'][$d['L']] ?? 0, [6, 8, 12], true) && self::isAfflicted($ctx, $d['L'])) {
                // check bhanga
                $bhanga = self::aspectsHouse($ctx, 'Jupiter', $ctx['house'][$d['L']]);
                $out[] = ['tone' => $bhanga ? 'info' : 'neg', 'name' => 'दरिद्र-योग संकेत — ' . $d['r'] . ' 6/8/12 में पीड़ित', 'text' => ($d['r'] === 'लाभेश' ? 'आय बार-बार टूटती है।' : 'संचय कठिन।') . ($bhanga ? ' (गुरु-दृष्टि से दोष-भंग — प्रभाव घटा।)' : ' उपाय आवश्यक।')];
            }
        }
        // Kemadruma (Moon isolated)
        if (self::kemadruma($ctx)) {
            $out[] = ['tone' => 'neg', 'name' => 'केमद्रुम योग', 'text' => 'चन्द्र से 2रे व 12वें दोनों खाली — मानसिक अस्थिरता, धन में उतार-चढ़ाव (केंद्र में चन्द्र/शुभ दृष्टि से भंग)।'];
        }
        return $out;
    }

    private static function gajakesari(array $ctx): bool
    {
        $mh = $ctx['house']['Moon'] ?? 0; $jh = $ctx['house']['Jupiter'] ?? 0;
        if ($mh === 0 || $jh === 0) {
            return false;
        }
        $d = (($jh - $mh) % 12 + 12) % 12 + 1;
        return in_array($d, [1, 4, 7, 10], true);
    }

    private static function kemadruma(array $ctx): bool
    {
        $mh = $ctx['house']['Moon'] ?? 0;
        if ($mh === 0) {
            return false;
        }
        $second = $mh === 12 ? 1 : $mh + 1;
        $twelfth = $mh === 1 ? 12 : $mh - 1;
        $has = static function (int $h) use ($ctx): bool {
            foreach ($ctx['occ'][$h] ?? [] as $p) {
                if (in_array($p, self::SEVEN, true) && $p !== 'Moon') {
                    return true;
                }
            }
            return false;
        };
        return !$has($second) && !$has($twelfth);
    }

    // --------------------------------------------------------- strength

    private static function strength(array $ctx, array $vargas): array
    {
        $L2 = $ctx['L2']; $L11 = $ctx['L11']; $L10 = $ctx['L10'];
        $rows = [];
        foreach ([[$L2, 'धनेश'], [$L11, 'लाभेश'], [$L10, 'दशमेश'], ['Jupiter', 'गुरु (धन-कारक)'], ['Venus', 'शुक्र']] as [$p, $role]) {
            $navSignName = (string) ($ctx['pl'][$p]['navamsa_sign'] ?? '');
            $navSign = array_search($navSignName, Charts::SIGNS, true);
            $vargottama = $navSign !== false && (int) $navSign === (int) ($ctx['pl'][$p]['sign_index'] ?? -1);
            $rows[] = [
                'role' => $role, 'hi' => self::HI[$p],
                'ratio' => round(self::ratio($ctx, $p), 2), 'strong' => self::ratio($ctx, $p) >= 1.0,
                'vim' => ($ctx['vim'])($p), 'vargottama' => $vargottama,
            ];
        }
        $arth = self::bhavaRupa($ctx, 2) + self::bhavaRupa($ctx, 10) + self::bhavaRupa($ctx, 11);
        $duh = self::bhavaRupa($ctx, 6) + self::bhavaRupa($ctx, 8) + self::bhavaRupa($ctx, 12);
        return [
            'rows' => $rows,
            'bhava_text' => 'भाव-बल — अर्थ-भाव (2+10+11) = ' . round($arth, 1) . ' रूप बनाम दुःस्थान (6+8+12) = ' . round($duh, 1)
                . ' रूप → ' . ($arth > $duh ? 'धन-पक्ष प्रबल (धनी बनने का बल)।' : 'दुःस्थान-पक्ष प्रबल — आय के बावजूद आर्थिक तनाव।'),
            'sav_text' => 'अष्टकवर्ग अर्थ-त्रिकोण (2+10+11) = ' . (self::savHouse($ctx, 2) + self::savHouse($ctx, 10) + self::savHouse($ctx, 11))
                . ' बनाम दुःस्थान (6+8+12) = ' . (self::savHouse($ctx, 6) + self::savHouse($ctx, 8) + self::savHouse($ctx, 12)) . ' अंक।',
        ];
    }

    // --------------------------------------------------------- vargas

    private static function vargaNotes(array $ctx, array $vargas): array
    {
        $out = [];
        // D2 Hora — savings
        if (!empty($vargas['D2'])) {
            $sc = self::horaScore($ctx, $vargas['D2']);
            $out[] = ['chart' => 'D-2 होरा (बचत)', 'tone' => $sc >= 3 ? 'pos' : 'info',
                'text' => $sc >= 3 ? 'अधिक ग्रह चन्द्र-होरा में — धन-संचय/बचत की प्रवृत्ति उत्तम।' : 'अधिक ग्रह सूर्य-होरा में — कमाई-खर्च दोनों अधिक, संचय कम; बचत हेतु सजगता।'];
        }
        // D9 — confirmation
        if (!empty($vargas['D9'])) {
            $g = self::vargaDignityScore($ctx, $vargas['D9'], [$ctx['L2'], $ctx['L11'], $ctx['L10']]);
            $out[] = ['chart' => 'D-9 नवांश (पुष्टि)', 'tone' => $g >= 3.3 ? 'pos' : 'info',
                'text' => $g >= 3.3 ? 'नवांश में धनेश/लाभेश/दशमेश में ≥2 बली — धन का वादा टिकाऊ।' : 'नवांश में धन-भावेश विशेष बली नहीं — फल आंशिक/दशा-निर्भर।'];
        }
        // D4 — property
        if (!empty($vargas['D4'])) {
            $d4L4 = self::SIGN_LORD[((int) ($vargas['D4']['asc_sign'] ?? 0) + 3) % 12];
            $d4L4h = self::vargaHouseOf($vargas['D4'], $d4L4);
            $ok = $d4L4h !== null && in_array($d4L4h, [1, 2, 4, 5, 9, 10, 11], true);
            $out[] = ['chart' => 'D-4 चतुर्थांश (संपत्ति)', 'tone' => $ok ? 'pos' : 'info',
                'text' => $ok ? 'D-4 में चतुर्थेश शुभ भाव में — अपना घर/संपत्ति का योग।' : 'D-4 में चतुर्थेश कमज़ोर/दुःस्थान में — संपत्ति में विलंब/किराया।'];
        }
        // D10 — career income
        if (!empty($vargas['D10'])) {
            $sc = self::d10Score($ctx, $vargas['D10']);
            $out[] = ['chart' => 'D-10 दशांश (आजीविका)', 'tone' => $sc >= 3 ? 'pos' : 'info',
                'text' => $sc >= 3 ? 'दशांश में कर्म व आय-भाव के स्वामी शुभ — पेशे से अच्छी आय।' : 'दशांश में कर्म/आय-भाव सामान्य — आय हेतु अधिक प्रयास।'];
        }
        return $out;
    }

    // --------------------------------------------------------- dasha

    private static function dashaSection(array $ctx, ?float $moonLon, ?float $birthJd, ?float $nowJd, float $tz): array
    {
        if ($moonLon === null || $birthJd === null || $nowJd === null) {
            return ['has_dasha' => false];
        }
        $seq = VimshottariDasha::sequence($moonLon, $birthJd);
        $curMd = null; $curAd = null; $nextMd = null;
        foreach ($seq['mahadashas'] as $i => $md) {
            if ($nowJd < $md['end_jd']) {
                $curMd = $md; $nextMd = $seq['mahadashas'][$i + 1] ?? null;
                foreach (VimshottariDasha::antardashas($md) as $ad) {
                    if ($nowJd < $ad['end_jd']) {
                        $curAd = $ad;
                        break;
                    }
                }
                break;
            }
        }
        $flavour = function (string $lord) use ($ctx): string {
            $touch = [];
            foreach ([2 => 'धन-संचय', 11 => 'आय', 10 => 'कर्म', 5 => 'निवेश/भाग्य', 9 => 'भाग्य', 6 => 'कर्ज़/सेवा', 8 => 'अचानक/विरासत', 12 => 'व्यय/विदेश', 7 => 'व्यापार', 4 => 'संपत्ति'] as $hh => $lbl) {
                if (self::linked($ctx, $lord, $hh)) {
                    $touch[] = $lbl;
                }
            }
            $wealthy = array_intersect($touch, ['धन-संचय', 'आय', 'निवेश/भाग्य', 'भाग्य', 'कर्म']) !== [];
            $lossy = in_array($ctx['house'][$lord] ?? 0, [6, 8, 12], true);
            return self::HI[$lord] . ' — ' . ($wealthy ? 'धन-अनुकूल' : ($lossy ? 'खर्च/बाधा-प्रवण' : 'सामान्य'))
                . ($touch ? '; सक्रिय भाव: ' . implode(', ', array_slice($touch, 0, 4)) : '');
        };
        return [
            'has_dasha' => true,
            'current' => ($curMd ? self::HI[$curMd['lord']] : '') . ($curAd ? '–' . self::HI[$curAd['lord']] : ''),
            'current_flavour' => $curMd ? $flavour($curMd['lord']) : '',
            'next_md' => $nextMd ? self::HI[$nextMd['lord']] : '',
            'next_from' => $nextMd ? JulianDay::toDmy($nextMd['start_jd'], $tz) : '',
            'next_flavour' => $nextMd ? $flavour($nextMd['lord']) : '',
            'text' => 'दशा-स्वामी का धन-भावों (2/10/11/5/9) से संबंध = धन-प्रधान काल; 6/8/12 से संबंध = खर्च/बाधा। महादशा अध्याय, अन्तर्दशा घटना, प्रत्यन्तर समय।',
        ];
    }

    // --------------------------------------------------------- timing (past + future)

    private static function timing(array $ctx, ?float $moonLon, ?float $birthJd, ?float $nowJd, float $tz,
        ?\AutoBusiness\Astro\Calc\CalculationEngine $engine, array $chart, ?array $birth): array
    {
        $asc = $ctx['asc'];
        $L2 = $ctx['L2']; $L11 = $ctx['L11'];
        $L5 = self::SIGN_LORD[($asc + 4) % 12]; $L9 = self::SIGN_LORD[($asc + 8) % 12];
        $L6 = self::SIGN_LORD[($asc + 5) % 12]; $L8 = self::SIGN_LORD[($asc + 7) % 12]; $L12 = self::SIGN_LORD[($asc + 11) % 12];
        $gain = array_unique([$L2, $L11, $L5, $L9, $ctx['L10']]);
        $loss = array_unique([$L6, $L8, $L12]);
        // also afflicted labhesh/dhanesh amplify loss
        $past = []; $future = [];
        if ($moonLon !== null && $birthJd !== null && $nowJd !== null) {
            $seq = VimshottariDasha::sequence($moonLon, $birthJd);
            $back = $nowJd - 3.0 * 365.2564; $fwd = $nowJd + 4.0 * 365.2564;
            foreach ($seq['mahadashas'] as $md) {
                if ($md['end_jd'] < $back || $md['start_jd'] > $fwd) {
                    continue;
                }
                foreach (VimshottariDasha::antardashas($md) as $ad) {
                    if ($ad['end_jd'] < $back || $ad['start_jd'] > $fwd) {
                        continue;
                    }
                    $isGain = in_array($md['lord'], $gain, true) || in_array($ad['lord'], $gain, true);
                    $isLoss = in_array($md['lord'], $loss, true) || in_array($ad['lord'], $loss, true);
                    if (!$isGain && !$isLoss) {
                        continue;
                    }
                    $kind = $isGain && !$isLoss ? 'लाभ' : ($isLoss && !$isGain ? 'हानि/खर्च' : 'मिश्र');
                    $row = ['label' => self::HI[$md['lord']] . '–' . self::HI[$ad['lord']],
                        'from' => JulianDay::toDmy($ad['start_jd'], $tz), 'to' => JulianDay::toDmy($ad['end_jd'], $tz),
                        'kind' => $kind, 'start_jd' => $ad['start_jd'], 'end_jd' => $ad['end_jd']];
                    if ($ad['end_jd'] < $nowJd) {
                        $past[] = $row;
                    } elseif ($ad['start_jd'] <= $fwd) {
                        $future[] = $row;
                    }
                }
            }
            $past = array_slice(array_reverse($past), 0, 3);   // most recent first
            $future = array_slice($future, 0, 4);
        }
        // auto Jupiter/Saturn gochar per future window
        $auto = $engine !== null && $birth !== null && !empty($chart['planets']);
        foreach ($future as &$w) {
            if ($auto) {
                $mid = ($w['start_jd'] < $nowJd ? $nowJd : $w['start_jd']);
                $mid = ($mid + $w['end_jd']) / 2.0;
                try {
                    $w['gochar'] = self::gocharWealth($engine, $ctx, $mid);
                } catch (\Throwable $e) {
                    $w['gochar'] = null;
                }
            }
        }
        unset($w);
        return [
            'past' => $past, 'future' => $future, 'has_dasha' => $moonLon !== null, 'auto' => $auto,
            'note' => 'नियम: वादा (कुंडली) → अनुमति (दशा) → ट्रिगर (गोचर)। तीनों (दशा + गोचर + वर्षफल) सहमत = भरोसेमंद; दो = संभावना; एक = मत कहें। '
                . 'लाभ-काल: 2/10/11/5/9 से जुड़े ग्रह की दशा + गुरु का 2/5/9/11 पर गोचर। हानि-काल: 6/8/12 से जुड़े ग्रह की दशा + शनि/राहु का 2रे/धनेश पर कम अष्टकवर्ग-बिंदुओं के साथ गोचर।',
        ];
    }

    private static function gocharWealth(\AutoBusiness\Astro\Calc\CalculationEngine $engine, array $ctx, float $jd): array
    {
        $asc = (int) $ctx['asc'];
        $jH = Charts::houseFromAsc($engine->planetSiderealLon('Jupiter', $jd), $asc);
        $sH = Charts::houseFromAsc($engine->planetSiderealLon('Saturn', $jd), $asc);
        $jupGood = in_array($jH, [2, 5, 9, 11], true);
        $jupBad = in_array($jH, [6, 8, 12], true);
        $satGood = in_array($sH, [3, 6, 11], true);
        if ($jupGood && $satGood) {
            return ['tone' => 'pos', 'text' => 'गुरु ' . self::ord($jH) . ' (धन-शुभ) व शनि ' . self::ord($sH) . ' (मेहनत-सफल) — गोचर धन के लिए अनुकूल।'];
        }
        if ($jupGood) {
            return ['tone' => 'pos', 'text' => 'गुरु का ' . self::ord($jH) . ' भाव में गोचर — धन-वृद्धि/आय हेतु शुभ अवसर।'];
        }
        if ($jupBad) {
            return ['tone' => 'info', 'text' => 'गुरु का ' . self::ord($jH) . ' (6/8/12) भाव में गोचर — इस अवधि खर्च/बाधा; बड़ा निवेश टालें।'];
        }
        return ['tone' => 'info', 'text' => 'गुरु ' . self::ord($jH) . ' · शनि ' . self::ord($sH) . ' — गोचर सामान्य; दशा-बल निर्णायक।'];
    }

    // --------------------------------------------------------- remedies

    private static function remedies(array $ctx): array
    {
        $need = [];
        foreach (array_unique([$ctx['L2'], $ctx['L11'], 'Jupiter', 'Venus']) as $p) {
            if (self::isAfflicted($ctx, $p)) {
                $need[$p] = true;
            }
        }
        $rows = [];
        foreach (array_keys($need) as $p) {
            $rows[] = ['hi' => self::HI[$p], 'text' => self::REMEDY[$p] ?? ''];
        }
        if ($rows === []) {
            $rows[] = ['hi' => 'गुरु/लक्ष्मी', 'text' => self::REMEDY['Jupiter'] . ' साथ ही कनकधारा-स्तोत्र व लक्ष्मी-नारायण पूजन।'];
        }
        return ['rows' => $rows, 'note' => 'सबसे बड़ा उपाय — ईमानदार परिश्रम, स्पष्ट हिसाब, और आय का दसवाँ हिस्सा अनिवार्य बचत/दान। ज्योतिष दिशा दिखाता है, चलना स्वयं पड़ता है।'];
    }

    // --------------------------------------------------------- conclusion

    private static function conclusion(array $scale, array $sources, array $savings, array $timing): string
    {
        $parts = [];
        $parts[] = 'धन-पैमाना: ' . $scale['score'] . '/10 — ' . $scale['band'] . '।';
        $topTwo = array_slice($sources, 0, 2);
        $parts[] = 'सबसे प्रबल धन-स्रोत: ' . implode(', ', array_map(fn ($s) => $s['name'] . ' (' . $s['level'] . ')', $topTwo)) . '।';
        $parts[] = 'बचत: ' . $savings['verdict'] . '।';
        if (!empty($timing['future'])) {
            $g = null;
            foreach ($timing['future'] as $f) {
                if ($f['kind'] === 'लाभ') {
                    $g = $f;
                    break;
                }
            }
            if ($g) {
                $parts[] = 'निकट भविष्य में लाभ-अवधि: ' . $g['label'] . ' (' . $g['from'] . ' – ' . $g['to'] . ')।';
            }
            $l = null;
            foreach ($timing['future'] as $f) {
                if ($f['kind'] === 'हानि/खर्च') {
                    $l = $f;
                    break;
                }
            }
            if ($l) {
                $parts[] = 'सावधानी-अवधि: ' . $l['label'] . ' (' . $l['from'] . ' – ' . $l['to'] . ')।';
            }
        }
        $parts[] = 'नियम: कोई एक सूत्र अंतिम नहीं — भाव + भावेश + कारक + बल की सहमति पर ही निर्णय दृढ़; धन की मात्रा (रुपये) सापेक्ष है, निरपेक्ष नहीं।';
        return implode(' ', $parts);
    }
}
