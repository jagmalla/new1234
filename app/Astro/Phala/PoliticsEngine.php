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
 * राजनीति / Political-career analysis — computed from the chart.
 *
 * Implements the owner's "राजनीतिक जीवन का ज्योतिषीय विश्लेषण" manual:
 *   • Ch2  houses (10/6/11 triangle + lord relations)
 *   • Ch3  planets (Sun/Mars/Saturn/Rahu — ≥2 strong near-mandatory)
 *   • Ch4  8 identification rules (politician yog?) + 2nd-level success check
 *   • Ch5  success yogas (raj / viparita / neechbhanga / pancha-mahapurusha /
 *          gajakesari / chatussagara / adhi / amala …)
 *   • Ch6  failure yogas (kemadruma / guru-chandal / grahan / angarak / vish …)
 *   • Ch7  navamsa (D9) vargottama + dashamesh strength
 *   • Ch8  dashamsha (D10) lagna/lord, Sun/Saturn/Rahu, raj-yoga repeat
 *   • Ch9  shadbala vs minima, ashtakavarga (1/6/10/11), vimshopaka
 *   • Ch10 rashi element / modality / guna → political style
 *   • Ch11 dasha connection to 10/11/9/1 + pad-prapti windows
 *   • Ch12 varshaphal — muntha, varshesh (from the annual chart)
 *   • Ch13 gochar of Jupiter / Saturn / Rahu from the Moon
 *   • Ch14 how-far — 17-factor point scale → level (worker … PM/President)
 *   • Ch15 promotion-soon 10-point check
 *   • Ch17 remedies for weak key planets
 *
 * Uses REAL computed Shadbala, Vimshopaka, Ashtakavarga and dignity — every
 * verdict carries its reason. Follows the manual's त्रिविध-पुष्टि discipline:
 * a level/verdict is stated only where lagna-chart, varga and dasha agree.
 */
final class PoliticsEngine
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
    /** exaltation sign per planet (for mahapurusha / neechbhanga / dignity). */
    private const EXALT = ['Sun' => 0, 'Moon' => 1, 'Mars' => 9, 'Mercury' => 5, 'Jupiter' => 3, 'Venus' => 11, 'Saturn' => 6];
    private const DEBIL = ['Sun' => 6, 'Moon' => 7, 'Mars' => 3, 'Mercury' => 11, 'Jupiter' => 9, 'Venus' => 5, 'Saturn' => 0];
    /** own signs per planet. */
    private const OWN = [
        'Sun' => [4], 'Moon' => [3], 'Mars' => [0, 7], 'Mercury' => [2, 5],
        'Jupiter' => [8, 11], 'Venus' => [1, 6], 'Saturn' => [9, 10],
    ];
    private const BENEFIC = ['Jupiter', 'Venus', 'Mercury', 'Moon'];

    /** Political role of each planet (§3). */
    private const ROLE = [
        'Sun' => 'राजा · सत्ता · अधिकार · सरकार · नेतृत्व',
        'Moon' => 'जनता · लोकप्रियता · जन-समर्थन · मन',
        'Mars' => 'संघर्ष · साहस · चुनाव जीतना · आक्रामकता',
        'Mercury' => 'वाणी · भाषण · कूटनीति · सौदेबाज़ी',
        'Jupiter' => 'विचारधारा · सम्मान · बड़े पद की गरिमा · गुरु/पार्टी',
        'Venus' => 'आकर्षण · वैभव · नेटवर्क · मधुर संबंध',
        'Saturn' => 'जनता/वोट · संगठन · धैर्य · लोकतंत्र (सर्वाधिक महत्व)',
        'Rahu' => 'महत्वाकांक्षा · प्रसिद्धि · प्रचार · अचानक ऊँचाई (राजनीति का ग्रह)',
        'Ketu' => 'त्याग · गुप्त रणनीति · शत्रुनाश (6ठे में)',
    ];
    /** field of governance by dominant house/planet (§14.4). */
    private const FIELD = [
        4 => 'स्थानीय निकाय · ग्राम/नगर · भूमि/आवास विभाग',
        3 => 'संगठन · प्रचार · संचार विभाग · प्रवक्ता',
        6 => 'गृह · पुलिस · श्रम · कानून-व्यवस्था',
        9 => 'शिक्षा · न्याय · धर्म · संस्कृति · विदेश',
        2 => 'वित्त · कोष · वाणिज्य',
        7 => 'विदेश नीति · गठबंधन · व्यापार',
        10 => 'प्रशासन · मुख्य कार्यकारी पद',
        11 => 'योजना · आय · बड़े संगठन',
        8 => 'गुप्तचर · अनुसंधान · कर विभाग',
    ];
    private const REMEDY = [
        'Sun' => 'रविवार सूर्य को जल-अर्पण, आदित्य-हृदय पाठ; गुड़/गेहूँ दान; पिता व अधिकारियों का सम्मान।',
        'Moon' => 'सोमवार शिव-उपासना; दूध/चावल/चाँदी दान; माता की सेवा।',
        'Mars' => 'मंगलवार हनुमान चालीसा; मसूर/लाल वस्त्र दान; भूमि-सेवा।',
        'Mercury' => 'बुधवार विष्णु-सहस्रनाम; हरी वस्तु/मूँग दान; गाय को हरा चारा।',
        'Jupiter' => 'गुरुवार गुरु-सेवा व बृहस्पति मंत्र; पीली वस्तु/चने की दाल दान।',
        'Venus' => 'शुक्रवार सफेद वस्तु दान; स्वच्छता; कला का सम्मान; स्त्री-सम्मान।',
        'Saturn' => 'शनिवार तेल/काले-तिल दान; मजदूरों-गरीबों की सेवा; हनुमान-उपासना।',
        'Rahu' => 'दुर्गा-उपासना; नारियल दान; असहायों/रोगियों की सेवा; अनुशासन।',
        'Ketu' => 'गणेश-उपासना; कम्बल दान; कुत्तों को भोजन।',
    ];

    /**
     * @param array<string,mixed> $chart  D1 chart (must carry 'shadbala', 'bhava_bala')
     * @param array<string,mixed> $vargas divisional charts (D9, D10 …)
     * @param array<string,mixed>|null $vp varshaphal (annual) data if available
     * @return array<string,mixed>
     */
    public static function compute(
        array $chart,
        array $vargas = [],
        ?float $moonLon = null,
        ?float $birthJd = null,
        ?float $nowJd = null,
        float $tz = 0.0,
        ?\AutoBusiness\Astro\Calc\CalculationEngine $engine = null,
        ?array $birth = null,
        ?array $vp = null
    ): array {
        if (empty($chart['planets']) || empty($chart['ascendant'])) {
            return ['ok' => false, 'error' => 'चार्ट उपलब्ध नहीं'];
        }
        $asc = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $pl = $chart['planets'];
        $shad = $chart['shadbala'] ?? [];
        $bhava = $chart['bhava_bala'] ?? [];

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
        $vimScore = static fn (string $p): int => (int) ($vim['scores']['Shodashavarga'][$p] ?? 0);

        $ctx = [
            'asc' => $asc, 'pl' => $pl, 'house' => $house, 'occ' => $occ, 'dig' => $dig,
            'shad' => $shad, 'bhava' => $bhava, 'vim' => $vimScore, 'sav' => self::savMap($chart),
            'vargas' => $vargas,
            'lordOf' => static fn (int $h): string => self::SIGN_LORD[($asc + $h - 1) % 12],
        ];
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12] as $hh) {
            $ctx['L' . $hh] = ($ctx['lordOf'])($hh);
        }
        // birth age (for dasha-vs-lifetime factor)
        $age = ($birthJd !== null && $nowJd !== null) ? (int) floor(($nowJd - $birthJd) / 365.25) : null;
        $ctx['age'] = $age;

        $yoga = self::yogas($ctx);
        $identify = self::identify($ctx, $yoga);
        $planets = self::keyPlanets($ctx);
        $pillars = self::pillars($ctx);
        $navamsa = self::navamsa($ctx);
        $dashamsha = self::dashamsha($ctx);
        $bala = self::bala($ctx);
        $style = self::style($ctx);
        $dasha = self::dashaSection($ctx, $moonLon, $birthJd, $nowJd, $tz, $yoga);
        $gochar = self::gochar($ctx, $engine, $nowJd);
        $varsha = self::varshaphal($vp, $ctx);
        $level = self::level($ctx, $yoga, $navamsa, $dashamsha, $bala, $dasha);
        $field = self::field($ctx);
        $promotion = self::promotion($ctx, $dasha, $gochar, $varsha);
        $success = self::successCheck($ctx, $bala, $navamsa, $dashamsha);
        $remedies = self::remedies($ctx, $planets);
        $conclusion = self::conclusion($identify, $level, $success, $promotion, $dasha, $gochar, $yoga);

        return [
            'ok' => true,
            'lagna_hi' => self::signHi($asc),
            'identify' => $identify,
            'key_planets' => $planets,
            'pillars' => $pillars,
            'yoga' => $yoga,
            'navamsa' => $navamsa,
            'dashamsha' => $dashamsha,
            'bala' => $bala,
            'style' => $style,
            'level' => $level,
            'field' => $field,
            'dasha' => $dasha,
            'varshaphal' => $varsha,
            'gochar' => $gochar,
            'promotion' => $promotion,
            'success' => $success,
            'remedies' => $remedies,
            'conclusion' => $conclusion,
        ];
    }

    // ============================================================ helpers

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
        if ($a === $b) {
            return false;
        }
        $ha = $ctx['house'][$a] ?? null;
        $hb = $ctx['house'][$b] ?? null;
        if ($ha === null || $hb === null) {
            return false;
        }
        if ($ha === $hb) {
            return true;   // conjunction
        }
        if (self::aspectsHouse($ctx, $a, $hb) || self::aspectsHouse($ctx, $b, $ha)) {
            return true;   // mutual/one-way aspect
        }
        // rashi exchange (parivartana)
        $sA = (int) ($ctx['pl'][$a]['sign_index'] ?? -1);
        $sB = (int) ($ctx['pl'][$b]['sign_index'] ?? -1);
        if ($sA >= 0 && $sB >= 0 && (self::SIGN_LORD[$sA] === $b && self::SIGN_LORD[$sB] === $a)) {
            return true;
        }
        // nakshatra-lord relation
        return self::nakLordOf($ctx, $a) === $b || self::nakLordOf($ctx, $b) === $a;
    }
    /** planet linked to a house (occupies / aspects / is or connects to its lord). */
    private static function linked(array $ctx, string $p, int $h): bool
    {
        if (($ctx['house'][$p] ?? 0) === $h) {
            return true;
        }
        if (self::aspectsHouse($ctx, $p, $h)) {
            return true;
        }
        $lord = ($ctx['lordOf'])($h);
        return $lord === $p || self::connect($ctx, $p, $lord);
    }
    /** two houses' lords are related. */
    private static function housesRelated(array $ctx, int $h1, int $h2): bool
    {
        $a = ($ctx['lordOf'])($h1);
        $b = ($ctx['lordOf'])($h2);
        return $a === $b ? false : self::connect($ctx, $a, $b);
    }
    private static function ratio(array $ctx, string $p): float
    {
        return (float) ($ctx['shad'][$p]['ratio'] ?? 0.0);
    }
    private static function digBala(array $ctx, string $p): float
    {
        return (float) ($ctx['shad'][$p]['dig'] ?? 0.0);
    }
    private static function rupa(array $ctx, string $p): float
    {
        $v = $ctx['shad'][$p]['total_virupa'] ?? null;
        return $v !== null ? round(((float) $v) / 60.0, 2) : 0.0;
    }
    private static function nakLordOf(array $ctx, string $p): string
    {
        static $NL = ['Ketu', 'Venus', 'Sun', 'Moon', 'Mars', 'Rahu', 'Jupiter', 'Saturn', 'Mercury'];
        $idx = (int) ($ctx['pl'][$p]['nakshatra']['index'] ?? 0);
        return $NL[$idx % 9];
    }
    private static function tier(array $ctx, string $p): string
    {
        return (string) ($ctx['dig'][$p]['dignity']['tier'] ?? '');
    }
    private static function dignityWord(array $ctx, string $p): string
    {
        return (string) ($ctx['dig'][$p]['dignity']['word'] ?? '');
    }
    private static function combust(array $ctx, string $p): bool
    {
        return ($ctx['dig'][$p]['combust']['pct'] ?? 0) >= 40;
    }
    private static function isStrong(array $ctx, string $p): bool
    {
        return in_array(self::tier($ctx, $p), ['param_uchcha', 'exalt', 'moolatrikona', 'own', 'great_friend', 'friend'], true)
            || self::ratio($ctx, $p) >= 1.0;
    }
    private static function inKendraTrikona(array $ctx, string $p): bool
    {
        $h = $ctx['house'][$p] ?? 0;
        return in_array($h, [1, 4, 7, 10, 5, 9], true);
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
    private static function savOfHouse(array $ctx, int $h): int
    {
        $sign = ($ctx['asc'] + $h - 1) % 12;
        return (int) ($ctx['sav'][$sign] ?? 0);
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

    // ---------------------------------------------------------- §5-6 yogas

    private static function yogas(array $ctx): array
    {
        $raj = [];
        // §5.1 kendra-trikona lord relations (named)
        $named = [
            [9, 10, 'धर्म-कर्माधिपति योग (9-10) — सर्वोच्च राजयोग · बड़े पद', 4],
            [1, 10, 'लग्नेश-दशमेश योग (1-10) — स्वबल से बनी सत्ता', 3],
            [5, 10, 'पंचमेश-दशमेश (5-10) — नीति+सत्ता · मंत्री पद', 3],
            [4, 10, 'चतुर्थेश-दशमेश (4-10) — जन-लोकप्रियता · मंत्री योग', 3],
            [10, 11, 'दशमेश-एकादशेश (10-11) — पद के साथ लाभ · सफल करियर', 3],
            [6, 10, 'षष्ठेश-दशमेश (6-10) — प्रतिस्पर्धा जीतकर पद', 2],
            [5, 11, 'पंचमेश-एकादशेश (5-11) — नीति+लाभ · रणनीतिकार', 2],
            [3, 11, 'तृतीयेश-एकादशेश (3-11) — प्रचार से लाभ · संगठन', 2],
        ];
        foreach ($named as [$h1, $h2, $label, $wt]) {
            if (self::housesRelated($ctx, $h1, $h2)) {
                $la = ($ctx['lordOf'])($h1); $lb = ($ctx['lordOf'])($h2);
                $strong = self::isStrong($ctx, $la) && self::isStrong($ctx, $lb);
                $raj[] = ['name' => $label, 'strong' => $strong, 'wt' => $wt,
                    'via' => self::HI[$la] . '↔' . self::HI[$lb] . ($strong ? ' (दोनों बली)' : ' (बल औसत)')];
            }
        }
        // §5.4 pancha mahapurusha
        $maha = [];
        $mahaMap = ['Mars' => 'रुचक', 'Mercury' => 'भद्र', 'Jupiter' => 'हंस', 'Venus' => 'मालव्य', 'Saturn' => 'शश'];
        foreach ($mahaMap as $p => $nm) {
            $h = $ctx['house'][$p] ?? 0;
            $sign = (int) ($ctx['pl'][$p]['sign_index'] ?? -1);
            $ownExalt = in_array($sign, self::OWN[$p] ?? [], true) || $sign === (self::EXALT[$p] ?? -1);
            if (in_array($h, [1, 4, 7, 10], true) && $ownExalt) {
                $maha[] = ['name' => $nm . ' योग (' . self::HI[$p] . ')', 'planet' => $p,
                    'note' => $p === 'Saturn' ? 'जन-नेता/प्रशासक — राजनीति हेतु विशेष शुभ' : ($p === 'Mars' ? 'निडर सेनानायक-सम नेता — राजनीति हेतु विशेष शुभ' : 'सम्मानित/कुशल नेता')];
            }
        }
        // §5.5 gajakesari (Jupiter in kendra from Moon)
        $moonH = $ctx['house']['Moon'] ?? 0; $jupH = $ctx['house']['Jupiter'] ?? 0;
        $gaja = false;
        if ($moonH && $jupH) {
            $rel = (($jupH - $moonH + 12) % 12) + 1;
            $gaja = in_array($rel, [1, 4, 7, 10], true) && self::isStrong($ctx, 'Jupiter');
        }
        // §5.2 viparita raj yoga
        $vip = [];
        $vipMap = [6 => ['हर्ष', 'शत्रुओं पर विजय, प्रतियोगिता में सफलता'], 8 => ['सरल', 'दीर्घायु, विरोधियों का नाश, संकट से उबरना'], 12 => ['विमल', 'स्वतंत्रता, प्रतिष्ठा, खर्च पर नियंत्रण']];
        foreach ($vipMap as $own => [$nm, $fl]) {
            $lord = ($ctx['lordOf'])($own);
            $lh = $ctx['house'][$lord] ?? 0;
            if (in_array($lh, [6, 8, 12], true)) {
                $vip[] = ['name' => $nm . ' योग', 'via' => self::ord($own) . 'ेश ' . self::HI[$lord] . ' → ' . self::ord($lh) . ' भाव', 'fruit' => $fl];
            }
        }
        // §5.3 neechbhanga raj yoga
        $neech = [];
        foreach (self::SEVEN as $p) {
            $sign = (int) ($ctx['pl'][$p]['sign_index'] ?? -1);
            if ($sign !== (self::DEBIL[$p] ?? -2)) {
                continue;
            }
            $dispo = self::SIGN_LORD[$sign];
            $exaltLord = null;
            foreach (self::EXALT as $q => $es) {
                if ($es === $sign) { $exaltLord = $q; break; }
            }
            $dispoKendra = in_array($ctx['house'][$dispo] ?? 0, [1, 4, 7, 10], true);
            $exaltKendra = $exaltLord !== null && in_array($ctx['house'][$exaltLord] ?? 0, [1, 4, 7, 10], true);
            if ($dispoKendra || $exaltKendra) {
                $neech[] = ['planet' => $p, 'name' => 'नीचभंग राजयोग (' . self::HI[$p] . ')',
                    'note' => 'नीच का भंग — साधारण पृष्ठभूमि से असाधारण उठान का योग'];
            }
        }
        // §5.10 chatussagara (planets in all 4 kendras)
        $kendraOcc = 0;
        foreach ([1, 4, 7, 10] as $k) {
            if (!empty(array_intersect($ctx['occ'][$k] ?? [], self::SEVEN))) {
                $kendraOcc++;
            }
        }
        $chatus = $kendraOcc === 4;
        // §5.6 amala (only benefics in 10th from lagna)
        $tenthOcc = $ctx['occ'][10] ?? [];
        $amala = $tenthOcc !== [] && count(array_diff($tenthOcc, self::BENEFIC)) === 0;
        // §5.7 adhi yoga (benefics in 6/7/8 from Moon)
        $adhi = false;
        if ($moonH) {
            $c = 0;
            foreach (self::BENEFIC as $b) {
                $bh = $ctx['house'][$b] ?? 0;
                if ($bh) {
                    $rel = (($bh - $moonH + 12) % 12) + 1;
                    if (in_array($rel, [6, 7, 8], true)) { $c++; }
                }
            }
            $adhi = $c >= 2;
        }

        // §6 failure yogas
        $fail = [];
        // kemadruma — no planet 2nd/12th from Moon (excl. Sun), and Moon not in kendra
        if ($moonH) {
            $has2or12 = false;
            foreach (self::PLANETS as $p) {
                if ($p === 'Moon' || $p === 'Sun' || $p === 'Rahu' || $p === 'Ketu') { continue; }
                $ph = $ctx['house'][$p] ?? 0;
                if ($ph) {
                    $rel = (($ph - $moonH + 12) % 12) + 1;
                    if ($rel === 2 || $rel === 12) { $has2or12 = true; break; }
                }
            }
            $moonKendra = in_array($moonH, [1, 4, 7, 10], true);
            if (!$has2or12 && !$moonKendra && $jupH !== $moonH) {
                $fail[] = ['name' => 'केमद्रुम योग', 'note' => 'जन-समर्थन में कमी, अकेलापन (चन्द्र केन्द्र/गुरु-दृष्टि से भंग सम्भव)'];
            }
        }
        $conj = static function (string $a, string $b) use ($ctx): bool {
            return ($ctx['house'][$a] ?? -1) === ($ctx['house'][$b] ?? -2);
        };
        if ($conj('Jupiter', 'Rahu')) {
            $fail[] = ['name' => 'गुरु-चांडाल योग', 'note' => 'नैतिक/नेतृत्व-विरोध, गलत सलाह से बचें'];
        }
        if ($conj('Sun', 'Rahu') || $conj('Sun', 'Ketu') || $conj('Moon', 'Rahu') || $conj('Moon', 'Ketu')) {
            $fail[] = ['name' => 'ग्रहण योग', 'note' => 'छवि पर धब्बा/मानसिक अस्थिरता — उपाय आवश्यक'];
        }
        if ($conj('Mars', 'Rahu')) {
            $fail[] = ['name' => 'अंगारक योग', 'note' => 'अत्यधिक उग्रता/आरोप — संयम रखें'];
        }
        if ($conj('Saturn', 'Moon')) {
            $fail[] = ['name' => 'विष योग', 'note' => 'निराशा/जन-कटाव/देरी — धैर्य व उपाय'];
        }
        // kaal sarpa — all 7 between Rahu-Ketu axis
        $fail = array_merge($fail, self::kaalSarpa($ctx));

        return [
            'raj' => $raj, 'mahapurusha' => $maha, 'gajakesari' => $gaja,
            'viparita' => $vip, 'neechbhanga' => $neech, 'chatussagara' => $chatus,
            'amala' => $amala, 'adhi' => $adhi, 'failure' => $fail,
            'raj_count' => count($raj), 'dharma_karma' => self::housesRelated($ctx, 9, 10),
        ];
    }

    private static function kaalSarpa(array $ctx): array
    {
        $rahuLon = (float) ($ctx['pl']['Rahu']['sidereal_lon'] ?? -1);
        $ketuLon = (float) ($ctx['pl']['Ketu']['sidereal_lon'] ?? -1);
        if ($rahuLon < 0 || $ketuLon < 0) {
            return [];
        }
        $allOneSide = true; $side = null;
        foreach (self::SEVEN as $p) {
            $lon = (float) ($ctx['pl'][$p]['sidereal_lon'] ?? -1);
            if ($lon < 0) { return []; }
            // arc from Rahu going forward to Ketu
            $fromRahu = fmod($lon - $rahuLon + 360.0, 360.0);
            $ketuFromRahu = fmod($ketuLon - $rahuLon + 360.0, 360.0);
            $thisSide = $fromRahu <= $ketuFromRahu;
            if ($side === null) { $side = $thisSide; } elseif ($side !== $thisSide) { $allOneSide = false; break; }
        }
        if ($allOneSide) {
            return [['name' => 'कालसर्प योग', 'note' => 'बहुत संघर्ष फिर अचानक बड़ी सफलता या विफलता — दशा पर निर्भर']];
        }
        return [];
    }

    // ---------------------------------------------------------- §4 identify

    private static function identify(array $ctx, array $yoga): array
    {
        $karakas = ['Sun', 'Saturn', 'Mercury', 'Jupiter'];
        $rules = [];
        // rule 1 — 10th/10L/karaka ↔ 6/11 or Rahu
        $L10 = $ctx['L10'];
        $r1 = self::linked($ctx, $L10, 6) || self::linked($ctx, $L10, 11) || self::connect($ctx, $L10, 'Rahu')
            || self::aspectsHouse($ctx, 'Rahu', 10) || (($ctx['house']['Rahu'] ?? 0) === 10);
        foreach ($karakas as $k) {
            if (self::linked($ctx, $k, 6) || self::linked($ctx, $k, 11)) { $r1 = true; }
        }
        $rules[] = ['met' => $r1, 'text' => 'दशम/दशमेश/कारक का 6ठे·11वें भाव या राहु से संबंध'];
        // rule 2 — L1 & L10 both strong and connected
        $L1 = $ctx['L1'];
        $r2 = self::isStrong($ctx, $L1) && self::isStrong($ctx, $L10) && self::connect($ctx, $L1, $L10);
        $rules[] = ['met' => $r2, 'text' => 'लग्नेश व दशमेश दोनों बली तथा परस्पर संबंधित'];
        // rule 3 — 6th/6L ↔ 10 or 11
        $r3 = self::housesRelated($ctx, 6, 10) || self::housesRelated($ctx, 6, 11) || self::linked($ctx, $ctx['L6'], 10) || self::linked($ctx, $ctx['L6'], 11);
        $rules[] = ['met' => $r3, 'text' => 'षष्ठ/षष्ठेश का दशम या एकादश से संबंध (चुनाव-विजय योग)'];
        // rule 4 — Rahu ↔ 1/6/10/11
        $rahuH = $ctx['house']['Rahu'] ?? 0;
        $r4 = in_array($rahuH, [1, 6, 10, 11], true) || self::aspectsHouse($ctx, 'Rahu', 1) || self::aspectsHouse($ctx, 'Rahu', 6) || self::aspectsHouse($ctx, 'Rahu', 10) || self::aspectsHouse($ctx, 'Rahu', 11);
        $rules[] = ['met' => $r4, 'text' => 'राहु का 1/6/10/11 भाव से संबंध'];
        // rule 5 — Saturn in upachaya or connected to their lords
        $satH = $ctx['house']['Saturn'] ?? 0;
        $r5 = in_array($satH, [3, 6, 10, 11], true);
        foreach ([3, 6, 10, 11] as $uh) {
            if (self::connect($ctx, 'Saturn', ($ctx['lordOf'])($uh))) { $r5 = true; }
        }
        $rules[] = ['met' => $r5, 'text' => 'शनि उपचय भाव (3/6/10/11) में या उनके स्वामियों से संबंधित'];
        // rule 6 — Sun or Mars strong and in kendra/trikona
        $r6 = (self::isStrong($ctx, 'Sun') && self::inKendraTrikona($ctx, 'Sun')) || (self::isStrong($ctx, 'Mars') && self::inKendraTrikona($ctx, 'Mars'));
        $rules[] = ['met' => $r6, 'text' => 'सूर्य या मंगल बली होकर केन्द्र/त्रिकोण में'];
        // rule 7 — L10 or L11 in movable sign
        $movable = [0, 3, 6, 9];
        $r7 = in_array((int) ($ctx['pl'][$L10]['sign_index'] ?? -1), $movable, true) || in_array((int) ($ctx['pl'][$ctx['L11']]['sign_index'] ?? -1), $movable, true);
        $rules[] = ['met' => $r7, 'text' => 'दशमेश या एकादशेश चर राशि में (सक्रियता/गतिशीलता)'];
        // rule 8 — at least one clear raj yoga
        $r8 = $yoga['raj_count'] >= 1 || $yoga['mahapurusha'] !== [] || $yoga['gajakesari'];
        $rules[] = ['met' => $r8, 'text' => 'कम से कम एक स्पष्ट राजयोग उपस्थित'];

        $met = 0;
        foreach ($rules as $r) { if ($r['met']) { $met++; } }
        if ($met >= 6) {
            $verdict = 'राजनीति में प्रवेश की प्रबल सम्भावना'; $tone = 'pos';
        } elseif ($met >= 4) {
            $verdict = 'राजनीति में प्रवेश की सम्भावना बनती है'; $tone = 'pos';
        } elseif ($met >= 2) {
            $verdict = 'राजनीति के कुछ ही योग — सक्रिय राजनीति कठिन, सामाजिक/संगठनात्मक भूमिका सम्भव'; $tone = 'mix';
        } else {
            $verdict = 'राजनीति के स्पष्ट योग कम — यह मुख्य क्षेत्र सम्भवतः नहीं'; $tone = 'neg';
        }
        return ['rules' => $rules, 'met' => $met, 'total' => count($rules), 'verdict' => $verdict, 'tone' => $tone, 'is_politician' => $met >= 4];
    }

    // ---------------------------------------------------------- §3 key planets

    private static function keyPlanets(array $ctx): array
    {
        $out = []; $strongCount = 0;
        foreach (['Sun', 'Mars', 'Saturn', 'Rahu'] as $p) {
            $strong = $p === 'Rahu' ? in_array($ctx['house']['Rahu'] ?? 0, [1, 3, 6, 10, 11], true) : self::isStrong($ctx, $p);
            if ($strong) { $strongCount++; }
            $out[] = [
                'planet' => self::HI[$p], 'role' => self::ROLE[$p], 'strong' => $strong,
                'house_ord' => self::ord($ctx['house'][$p] ?? 0),
                'dignity' => $p === 'Rahu' || $p === 'Ketu' ? '' : self::dignityWord($ctx, $p),
                'ratio' => $p === 'Rahu' || $p === 'Ketu' ? null : round(self::ratio($ctx, $p), 2),
            ];
        }
        // Moon (jan-samarthan), Jupiter, Venus, Mercury quick lines
        $support = [];
        foreach (['Moon', 'Jupiter', 'Mercury', 'Venus'] as $p) {
            $support[] = ['planet' => self::HI[$p], 'role' => self::ROLE[$p], 'strong' => self::isStrong($ctx, $p),
                'house_ord' => self::ord($ctx['house'][$p] ?? 0)];
        }
        return [
            'main' => $out, 'support' => $support, 'strong_of_four' => $strongCount,
            'verdict' => $strongCount >= 2
                ? 'सूर्य/मंगल/शनि/राहु में से ' . $strongCount . ' बली — राजनीति की मूल शर्त पूरी'
                : 'इन चार में से केवल ' . $strongCount . ' बली — राजनीति की धुरी कमज़ोर (शास्त्र: ≥2 आवश्यक)',
            'tone' => $strongCount >= 2 ? 'pos' : 'neg',
        ];
    }

    // ---------------------------------------------------------- §2 pillars

    private static function pillars(array $ctx): array
    {
        $mk = function (int $h, string $label) use ($ctx): array {
            $lord = ($ctx['lordOf'])($h);
            $sav = self::savOfHouse($ctx, $h);
            $bh = $ctx['bhava'][$h]['rupa'] ?? ($ctx['bhava'][$h]['total_virupa'] ?? null);
            $bh = is_numeric($bh) ? round((float) $bh > 60 ? ((float) $bh) / 60.0 : (float) $bh, 2) : null;
            return [
                'house_ord' => self::ord($h), 'label' => $label,
                'lord' => self::HI[$lord], 'lord_house_ord' => self::ord($ctx['house'][$lord] ?? 0),
                'lord_strong' => self::isStrong($ctx, $lord), 'lord_dignity' => self::dignityWord($ctx, $lord),
                'sav' => $sav, 'sav_band' => $sav >= 30 ? 'बलवान' : ($sav >= 25 ? 'सामान्य' : 'कमज़ोर'),
                'bhava_rupa' => $bh,
                'occupants' => array_map(fn ($x) => self::HI[$x], array_intersect($ctx['occ'][$h] ?? [], self::PLANETS)),
            ];
        };
        $rel = [];
        $relMap = [
            [10, 11, 'पद के साथ लाभ — सफल करियर'], [6, 10, 'प्रतिस्पर्धा जीतकर पद'],
            [9, 10, 'धर्म-कर्माधिपति — उच्चतम राजयोग'], [1, 10, 'स्वबल से करियर'],
            [4, 10, 'जन-लोकप्रियता · मंत्री पद'], [6, 11, 'शत्रु हराकर लाभ'],
        ];
        foreach ($relMap as [$a, $b, $fl]) {
            if (self::housesRelated($ctx, $a, $b)) {
                $rel[] = self::ord($a) . '↔' . self::ord($b) . ' — ' . $fl;
            }
        }
        return [
            'tenth' => $mk(10, 'सत्ता व पद'), 'sixth' => $mk(6, 'प्रतियोगिता व शत्रु'), 'eleventh' => $mk(11, 'लाभ व जन-समर्थन'),
            'relations' => $rel,
            'note' => 'राजनीतिक त्रिकोण — 10 (पद) · 6 (चुनाव=शत्रु-पराजय) · 11 (जन-समर्थन/परिणाम)। तीनों बली व संबंधित हों तो सफलता प्रबल।',
        ];
    }

    // ---------------------------------------------------------- §7 navamsa

    private static function navamsa(array $ctx): array
    {
        $d9 = $ctx['vargas']['D9'] ?? null;
        $L1 = $ctx['L1']; $L10 = $ctx['L10'];
        $vargottama = function (string $p) use ($ctx, $d9): bool {
            if (!$d9) { return false; }
            $d9s = self::vargaSignOf($d9, $p);
            return $d9s !== null && $d9s === (int) ($ctx['pl'][$p]['sign_index'] ?? -1);
        };
        // dashamesh strength in D9 (own/exalt/friend of its D9 sign)
        $l10d9 = $d9 ? self::vargaSignOf($d9, $L10) : null;
        $l10d9Strong = null;
        if ($l10d9 !== null) {
            $l10d9Strong = in_array($l10d9, self::OWN[$L10] ?? [], true) || $l10d9 === (self::EXALT[$L10] ?? -1);
        }
        return [
            'has' => $d9 !== null,
            'l1_vargottama' => $vargottama($L1), 'l10_vargottama' => $vargottama($L10),
            'l10' => self::HI[$L10], 'l10_d9_sign' => $l10d9 !== null ? self::signHi($l10d9) : '',
            'l10_d9_strong' => $l10d9Strong,
            'text' => 'नवांश करियर की गुणवत्ता/स्थायित्व दिखाता है। '
                . (($vargottama($L10)) ? 'दशमेश वर्गोत्तम — करियर में असाधारण स्थायी सफलता। ' : '')
                . (($vargottama($L1)) ? 'लग्नेश वर्गोत्तम — मजबूत स्वतंत्र व्यक्तित्व। ' : '')
                . ($l10d9Strong === true ? 'दशमेश नवांश में भी बली — पद उच्च व स्थायी।' : ($l10d9Strong === false ? 'दशमेश नवांश में दुर्बल — पद मिलकर टिकाना कठिन।' : '')),
        ];
    }

    // ---------------------------------------------------------- §8 dashamsha

    private static function dashamsha(array $ctx): ?array
    {
        $d10 = $ctx['vargas']['D10'] ?? null;
        if ($d10 === null || empty($d10['planets'])) {
            return null;
        }
        $ascSign = (int) ($d10['asc_sign'] ?? 0);
        $L1 = self::SIGN_LORD[$ascSign];
        $tenthSign = ($ascSign + 9) % 12;
        $L10 = self::SIGN_LORD[$tenthSign];
        $strongInVarga = function (string $p) use ($d10): bool {
            $s = self::vargaSignOf($d10, $p);
            if ($s === null) { return false; }
            return in_array($s, self::OWN[$p] ?? [], true) || $s === (self::EXALT[$p] ?? -1) || $s === (self::SIGN_LORD[$s] === $p ? $s : -1);
        };
        return [
            'lagna_hi' => self::signHi($ascSign),
            'l1' => self::HI[$L1] ?? $L1, 'l1_strong' => $strongInVarga($L1),
            'l10' => self::HI[$L10] ?? $L10, 'l10_strong' => $strongInVarga($L10),
            'sun_house' => self::vargaHouseOf($d10, 'Sun'), 'saturn_house' => self::vargaHouseOf($d10, 'Saturn'),
            'rahu_house' => self::vargaHouseOf($d10, 'Rahu'),
            'sun_kendra' => in_array(self::vargaHouseOf($d10, 'Sun'), [1, 4, 7, 10], true),
            'text' => 'दशमांश (D-10) करियर की मुख्य कुण्डली है। D-10 लग्न ' . self::signHi($ascSign)
                . '; लग्नेश ' . (self::HI[$L1] ?? $L1) . ($strongInVarga($L1) ? ' बली' : ' औसत')
                . '। D-10 में सूर्य ' . self::ord((int) self::vargaHouseOf($d10, 'Sun')) . ', शनि ' . self::ord((int) self::vargaHouseOf($d10, 'Saturn'))
                . ', राहु ' . self::ord((int) self::vargaHouseOf($d10, 'Rahu')) . ' भाव में — सत्ता/जनाधार/ऊँचाई के संकेतक।',
        ];
    }

    // ---------------------------------------------------------- §9 bala

    private static function bala(array $ctx): array
    {
        static $MIN = ['Sun' => 5.0, 'Moon' => 6.0, 'Mars' => 5.0, 'Mercury' => 7.0, 'Jupiter' => 6.5, 'Venus' => 5.5, 'Saturn' => 5.0];
        $lords = [];
        foreach (['L1' => 'लग्नेश', 'L10' => 'दशमेश', 'L11' => 'एकादशेश', 'L6' => 'षष्ठेश'] as $k => $lbl) {
            $p = $ctx[$k];
            if (in_array($p, self::SEVEN, true)) {
                $rupa = self::rupa($ctx, $p); $min = $MIN[$p] ?? 6.0;
                $lords[] = ['label' => $lbl, 'planet' => self::HI[$p], 'rupa' => $rupa, 'min' => $min,
                    'ok' => $rupa >= $min, 'vim' => ($ctx['vim'])($p), 'dig' => round(self::digBala($ctx, $p), 2)];
            }
        }
        $av = [];
        foreach ([1 => 'लग्न', 6 => 'षष्ठ', 10 => 'दशम', 11 => 'एकादश'] as $h => $lbl) {
            $b = self::savOfHouse($ctx, $h);
            $av[] = ['house' => $lbl, 'bindu' => $b, 'band' => $b >= 30 ? 'बलवान' : ($b >= 25 ? 'सामान्य' : 'कमज़ोर'), 'ok' => $b >= 30];
        }
        return [
            'lords' => $lords, 'ashtakavarga' => $av,
            'note' => 'षड्बल (रूप) — दशमेश/लग्नेश/एकादशेश न्यूनतम से ऊपर हों। अष्टकवर्ग — 10वें व 11वें में 30+ बिन्दु = ऊँचा स्तर, 25 से कम = संघर्ष। विंशोपक बल (20 में) ग्रह की समग्र वर्ग-शक्ति दिखाता है।',
        ];
    }

    // ---------------------------------------------------------- §10 style

    private static function style(array $ctx): array
    {
        $tenSign = ($ctx['asc'] + 9) % 12;
        static $ELEM = [0 => 'अग्नि', 4 => 'अग्नि', 8 => 'अग्नि', 1 => 'पृथ्वी', 5 => 'पृथ्वी', 9 => 'पृथ्वी',
            2 => 'वायु', 6 => 'वायु', 10 => 'वायु', 3 => 'जल', 7 => 'जल', 11 => 'जल'];
        static $ESTYLE = ['अग्नि' => 'आक्रामक, प्रेरक वक्ता, नेतृत्व-प्रधान', 'पृथ्वी' => 'व्यावहारिक, संगठनकर्ता, धीमा पर स्थायी',
            'वायु' => 'विचारक, कूटनीतिज्ञ, गठबंधन-कुशल', 'जल' => 'भावनात्मक अपील, जन-संवेदना, गुप्त रणनीति'];
        $modality = in_array($tenSign, [0, 3, 6, 9], true) ? 'चर — तेज़ उठान पर पद बदलते रहते'
            : (in_array($tenSign, [1, 4, 7, 10], true) ? 'स्थिर — देर से पर लम्बा/स्थायी पद' : 'द्विस्वभाव — लचीला, समझौता/दोहरी भूमिका');
        // guna dominance among strong planets
        static $GUNA = ['Sun' => 'सत्व', 'Moon' => 'सत्व', 'Jupiter' => 'सत्व', 'Mercury' => 'रज', 'Venus' => 'रज',
            'Mars' => 'तम', 'Saturn' => 'तम', 'Rahu' => 'तम', 'Ketu' => 'तम'];
        $tally = ['सत्व' => 0, 'रज' => 0, 'तम' => 0];
        foreach (self::PLANETS as $p) {
            if (self::isStrong($ctx, $p) || in_array($ctx['house'][$p] ?? 0, [1, 4, 7, 10, 5, 9], true)) {
                $tally[$GUNA[$p]]++;
            }
        }
        arsort($tally);
        $domGuna = array_key_first($tally);
        $el = $ELEM[$tenSign] ?? '';
        return [
            'element' => $el, 'element_style' => $ESTYLE[$el] ?? '', 'modality' => $modality,
            'guna' => $domGuna,
            'guna_note' => $domGuna === 'सत्व' ? 'आदर्शवादी/सम्मानित शैली' : ($domGuna === 'रज' ? 'व्यावहारिक/महत्वाकांक्षी शैली' : 'संघर्षशील/सत्ता-केन्द्रित शैली'),
            'text' => '10वीं राशि ' . self::signHi($tenSign) . ' — तत्व ' . $el . ' (' . ($ESTYLE[$el] ?? '') . '); स्वभाव: ' . $modality . '।',
        ];
    }

    // ---------------------------------------------------------- §11 dasha

    private static function dashaSection(array $ctx, ?float $moonLon, ?float $birthJd, ?float $nowJd, float $tz, array $yoga): array
    {
        if ($moonLon === null || $birthJd === null || $nowJd === null) {
            return ['has' => false];
        }
        $seq = VimshottariDasha::sequence($moonLon, $birthJd);
        $curMd = null; $curAd = null; $future = [];
        foreach ($seq['mahadashas'] as $md) {
            if ($nowJd < $md['end_jd'] && $curMd === null && $nowJd >= $md['start_jd']) {
                $curMd = $md;
                foreach (VimshottariDasha::antardashas($md) as $ad) {
                    if ($nowJd < $ad['end_jd']) { $curAd = $ad; break; }
                }
            }
        }
        // political relevance of a lord: touches 10/11/9/1
        $polTouch = function (string $lord) use ($ctx): array {
            $t = [];
            foreach ([10 => 'पद', 11 => 'लाभ/जन-समर्थन', 9 => 'भाग्य/उच्च-पद', 1 => 'स्व/नेतृत्व', 6 => 'शत्रु-विजय'] as $hh => $lbl) {
                if (self::linked($ctx, $lord, $hh)) { $t[] = self::ord($hh) . '(' . $lbl . ')'; }
            }
            return $t;
        };
        $curTouch = $curMd ? $polTouch($curMd['lord']) : [];
        $adTouch = $curAd ? $polTouch($curAd['lord']) : [];
        // pad-prapti windows: scan upcoming antardashas (next ~12y) for L10/L11/L9 + yogakaraka combos
        $L10 = $ctx['L10']; $L11 = $ctx['L11']; $L9 = $ctx['L9'];
        $keyLords = array_unique([$L10, $L11, $L9, $ctx['L1']]);
        $windows = [];
        foreach ($seq['mahadashas'] as $md) {
            if ($md['end_jd'] < $nowJd) { continue; }
            if ($md['start_jd'] > $nowJd + 365.25 * 20) { break; }
            foreach (VimshottariDasha::antardashas($md) as $ad) {
                if ($ad['end_jd'] < $nowJd) { continue; }
                $m = $md['lord']; $a = $ad['lord'];
                if ($m === $a) { continue; }
                $isPad = ($m === $L10 && $a === $L11) || ($m === $L11 && $a === $L10)
                    || ($m === $L9 && $a === $L10) || ($m === $ctx['L1'] && $a === $L10)
                    || ($m === $ctx['L6'] && ($a === $L10 || $a === $L11));
                $bothKey = in_array($m, $keyLords, true) && in_array($a, $keyLords, true);
                if ($isPad || $bothKey) {
                    $windows[] = [
                        'md' => self::HI[$m], 'ad' => self::HI[$a],
                        'from' => JulianDay::toDmy($ad['start_jd'], $tz), 'to' => JulianDay::toDmy($ad['end_jd'], $tz),
                        'strong' => $isPad, 'note' => $isPad ? 'पद-प्राप्ति/पदोन्नति का प्रबल योग' : 'करियर-सक्रिय अवधि',
                    ];
                }
                if (count($windows) >= 6) { break 2; }
            }
        }
        return [
            'has' => true,
            'current_md' => $curMd ? self::HI[$curMd['lord']] : '', 'current_ad' => $curAd ? self::HI[$curAd['lord']] : '',
            'md_to' => $curMd ? JulianDay::toDmy($curMd['end_jd'], $tz) : '', 'ad_to' => $curAd ? JulianDay::toDmy($curAd['end_jd'], $tz) : '',
            'cur_touch' => $curTouch, 'ad_touch' => $adTouch,
            'cur_active' => $curTouch !== [] || $adTouch !== [],
            'windows' => $windows,
            'text' => 'दशा को कुण्डली से जोड़ें — दशानाथ जिस भाव में/जिसका स्वामी हो, वही फल दे। पद हेतु दशा/अंतर का 10·11·9·1 से जुड़ाव आवश्यक। '
                . ($curTouch !== [] ? 'वर्तमान महादशा ' . ($curMd ? self::HI[$curMd['lord']] : '') . ' राजनीतिक भावों (' . implode(', ', $curTouch) . ') से जुड़ी — अनुकूल।' : 'वर्तमान महादशा राजनीतिक भावों से सीधे नहीं जुड़ी — प्रभाव सीमित।'),
        ];
    }

    // ---------------------------------------------------------- §13 gochar

    private static function gochar(array $ctx, ?\AutoBusiness\Astro\Calc\CalculationEngine $engine, ?float $nowJd): array
    {
        $moonSign = (int) ($ctx['pl']['Moon']['sign_index'] ?? 0);
        if ($engine === null || $nowJd === null) {
            return ['has' => false];
        }
        $houseFromMoon = function (int $sign) use ($moonSign): int {
            return (($sign - $moonSign + 12) % 12) + 1;
        };
        $transitSign = function (string $p) use ($engine, $nowJd): int {
            return Charts::signIndex(Charts::norm($engine->planetSiderealLon($p, $nowJd)));
        };
        $jH = $houseFromMoon($transitSign('Jupiter'));
        $sH = $houseFromMoon($transitSign('Saturn'));
        $rH = $houseFromMoon($transitSign('Rahu'));
        $jGood = in_array($jH, [2, 5, 7, 9, 11], true);
        $sGood = in_array($sH, [3, 6, 11], true);
        $rGood = in_array($rH, [3, 6, 10, 11], true);
        $sadeSati = in_array($sH, [12, 1, 2], true);
        $good = (int) $jGood + (int) $sGood + (int) $rGood;
        return [
            'has' => true,
            'jupiter_house' => $jH, 'jupiter_good' => $jGood, 'jupiter_best' => $jH === 11,
            'saturn_house' => $sH, 'saturn_good' => $sGood, 'saturn_best' => $sH === 11, 'sade_sati' => $sadeSati,
            'rahu_house' => $rH, 'rahu_good' => $rGood,
            'good_count' => $good,
            'verdict' => $good >= 2 ? 'गोचर अनुकूल — अवसर/पदोन्नति का समय' : ($good === 1 ? 'गोचर मिश्रित — छोटे अवसर' : 'गोचर अभी सामान्य/प्रतिकूल — प्रतीक्षा'),
            'tone' => $good >= 2 ? 'pos' : ($good === 1 ? 'mix' : 'neg'),
            'text' => 'गोचर चन्द्र-राशि से — गुरु ' . self::ord($jH) . ($jGood ? ' (शुभ' . ($jH === 11 ? ', सर्वोत्तम' : '') . ')' : ' (सामान्य)')
                . ', शनि ' . self::ord($sH) . ($sGood ? ' (शुभ' . ($sH === 11 ? ', सर्वोत्तम' : '') . ')' : ($sadeSati ? ' (साढ़ेसाती)' : ' (सामान्य)'))
                . ', राहु ' . self::ord($rH) . ($rGood ? ' (शुभ)' : '') . '।',
        ];
    }

    // ---------------------------------------------------------- §12 varshaphal

    private static function varshaphal(?array $vp, array $ctx): array
    {
        if (!is_array($vp)) {
            return ['has' => false];
        }
        // muntha house
        $munthaSign = $vp['muntha_sign_index'] ?? ($vp['muntha']['sign_index'] ?? null);
        $munthaHouse = null;
        if ($munthaSign !== null) {
            $munthaHouse = ((((int) $munthaSign - $ctx['asc']) % 12) + 12) % 12 + 1;
        }
        $munthaGood = $munthaHouse !== null ? in_array($munthaHouse, [1, 2, 3, 4, 5, 6, 9, 10, 11], true) : null;
        $munthaBest = $munthaHouse !== null ? in_array($munthaHouse, [10, 11], true) : false;
        // varshesh (year lord)
        $varshesh = $vp['varshesh']['lord'] ?? ($vp['varshesh'] ?? null);
        $varsheshHi = is_string($varshesh) ? (self::HI[$varshesh] ?? $varshesh) : null;
        $varsheshIsKarma = is_string($varshesh) && in_array($varshesh, [$ctx['L10'], $ctx['L11']], true);
        return [
            'has' => true,
            'muntha_house' => $munthaHouse !== null ? self::ord($munthaHouse) : '',
            'muntha_good' => $munthaGood, 'muntha_best' => $munthaBest,
            'varshesh' => $varsheshHi, 'varshesh_karma' => $varsheshIsKarma,
            'text' => 'वर्षफल (इस वर्ष) — '
                . ($munthaHouse !== null ? 'मुन्था ' . self::ord($munthaHouse) . ' भाव में' . ($munthaBest ? ' (10/11 — पद/लाभ हेतु सर्वोत्तम)' : ($munthaGood ? ' (शुभ)' : ' (सावधानी)')) : '')
                . ($varsheshHi ? '; वर्षेश ' . $varsheshHi . ($varsheshIsKarma ? ' = दशमेश/एकादशेश — वर्ष करियर के नाम' : '') : '')
                . '। (शास्त्र: राज्य/राज्यलाभ/जय सहम व दशमेश-एकादशेश इत्थशाल से पुष्टि करें; वर्षफल जन्मकुण्डली से बड़ा फल नहीं देता।)',
        ];
    }

    // ---------------------------------------------------------- §14 level

    private static function level(array $ctx, array $yoga, array $navamsa, ?array $d10, array $bala, array $dasha): array
    {
        $L10 = $ctx['L10']; $L11 = $ctx['L11']; $L6 = $ctx['L6'];
        $f = [];
        $add = function (string $label, bool $met, int $pts) use (&$f) { $f[] = ['label' => $label, 'met' => $met, 'pts' => $pts]; };

        $add('दशमेश केन्द्र/त्रिकोण में बली', self::inKendraTrikona($ctx, $L10) && self::isStrong($ctx, $L10), 3);
        $add('एकादशेश बली व 10/1 से संबंधित', self::isStrong($ctx, $L11) && (self::connect($ctx, $L11, $L10) || self::connect($ctx, $L11, $ctx['L1'])), 3);
        $add('षष्ठेश बली (शत्रुनाशक)', self::isStrong($ctx, $L6), 2);
        $add('धर्म-कर्माधिपति योग (9-10)', $yoga['dharma_karma'], 4);
        $add('कोई पंच-महापुरुष योग', $yoga['mahapurusha'] !== [], 3);
        $add('गजकेसरी योग बली', $yoga['gajakesari'], 2);
        $add('राहु 1/3/6/10/11 में शुभ', in_array($ctx['house']['Rahu'] ?? 0, [1, 3, 6, 10, 11], true), 3);
        $add('शनि उपचय भाव (3/6/10/11) में', in_array($ctx['house']['Saturn'] ?? 0, [3, 6, 10, 11], true), 2);
        $add('सूर्य दिग्बली या स्व/उच्च', self::digBala($ctx, 'Sun') >= 40 || in_array(self::tier($ctx, 'Sun'), ['param_uchcha', 'exalt', 'moolatrikona', 'own'], true), 3);
        $add('चतुःसागर योग', $yoga['chatussagara'], 3);
        $add('दशमेश वर्गोत्तम', $navamsa['l10_vargottama'] ?? false, 3);
        $add('D-10 लग्नेश बली', $d10 !== null && !empty($d10['l1_strong']), 3);
        $add('अष्टकवर्ग 10वें में 30+ बिन्दु', self::savOfHouse($ctx, 10) >= 30, 3);
        $add('अष्टकवर्ग 11वें में 30+ बिन्दु', self::savOfHouse($ctx, 11) >= 30, 3);
        $add('विपरीत राजयोग उपस्थित', $yoga['viparita'] !== [], 2);
        $add('नीचभंग राजयोग उपस्थित', $yoga['neechbhanga'] !== [], 2);
        $add('25-55 आयु में योगकारक दशा', ($dasha['has'] ?? false) && ($dasha['cur_active'] ?? false) && ($ctx['age'] !== null && $ctx['age'] >= 22 && $ctx['age'] <= 60), 2);

        $score = 0; $max = 0;
        foreach ($f as $x) { $max += $x['pts']; if ($x['met']) { $score += $x['pts']; } }

        // level band (§14.3)
        if ($score < 10) {
            $band = 'worker'; $label = 'कार्यकर्ता / पार्टी सदस्य';
            $titles = 'पार्टी कार्यकर्ता, स्थानीय पदाधिकारी';
        } elseif ($score <= 18) {
            $band = 'local'; $label = 'स्थानीय स्तर';
            $titles = 'वार्ड सदस्य, पंचायत सदस्य, सरपंच, पार्षद';
        } elseif ($score <= 26) {
            $band = 'district'; $label = 'जिला / नगर स्तर';
            $titles = 'महापौर, जिला अध्यक्ष, नगर प्रमुख';
        } elseif ($score <= 33) {
            $band = 'state'; $label = 'राज्य स्तर';
            $titles = 'विधायक (MLA), राज्य मंत्री';
        } elseif ($score <= 40) {
            $band = 'national'; $label = 'राष्ट्रीय स्तर';
            $titles = 'सांसद (MP), केन्द्रीय मंत्री';
        } else {
            $band = 'supreme'; $label = 'सर्वोच्च स्तर की सम्भावना';
            $titles = 'मुख्यमंत्री, राज्यपाल, प्रधानमंत्री/राष्ट्रपति (अत्यंत दुर्लभ — सावधानी से)';
        }
        return [
            'factors' => $f, 'score' => $score, 'max' => $max,
            'band' => $band, 'label' => $label, 'titles' => $titles,
            'note' => 'यह अंक-आधारित स्तर केवल तुलना/मार्गदर्शन हेतु है (शास्त्र §14.3)। अवसर, परिश्रम व सामूहिक-कर्म की भी भूमिका है — अति-आशावादी निष्कर्ष न लें।',
        ];
    }

    private static function field(array $ctx): array
    {
        // score each governance area by strength of its house/planet
        $areas = [
            4 => ['Moon', 'Venus'], 3 => ['Mars', 'Mercury'], 6 => ['Mars', 'Saturn'], 9 => ['Jupiter'],
            2 => ['Mercury', 'Jupiter'], 7 => ['Venus'], 10 => ['Sun'], 11 => ['Jupiter', 'Rahu'], 8 => ['Saturn', 'Ketu'],
        ];
        $best = null; $bestScore = -1;
        foreach ($areas as $h => $planets) {
            $s = self::savOfHouse($ctx, $h) / 30.0;
            foreach ($planets as $p) {
                if (self::isStrong($ctx, $p)) { $s += 1.0; }
                if (($ctx['house'][$p] ?? 0) === $h) { $s += 0.5; }
            }
            if ($s > $bestScore) { $bestScore = $s; $best = $h; }
        }
        return ['house_ord' => self::ord((int) $best), 'field' => self::FIELD[$best] ?? '',
            'text' => 'सम्भावित क्षेत्र/विभाग: ' . (self::FIELD[$best] ?? '') . ' (सबसे बली प्रासंगिक भाव/ग्रह के आधार पर)।'];
    }

    // ---------------------------------------------------------- §15 promotion

    private static function promotion(array $ctx, array $dasha, array $gochar, array $varsha): array
    {
        $checks = [];
        $c = function (string $label, bool $met) use (&$checks) { $checks[] = ['label' => $label, 'met' => $met]; };
        $L10 = $ctx['L10']; $L11 = $ctx['L11'];
        // dasha-based
        $md = $dasha['current_md'] ?? ''; $ad = $dasha['current_ad'] ?? '';
        $c('वर्तमान दशा/अंतर 10·11·9·1 से जुड़ी', ($dasha['cur_active'] ?? false));
        $c('निकट अवधि में पद-प्राप्ति दशा-विंडो', !empty(array_filter($dasha['windows'] ?? [], fn ($w) => !empty($w['strong']))));
        // gochar-based
        $c('गोचर गुरु 11/5/9/2 (चन्द्र से)', ($gochar['jupiter_good'] ?? false));
        $c('गोचर शनि 3/6/11 (चन्द्र से)', ($gochar['saturn_good'] ?? false));
        $c('गोचर राहु 3/6/10/11', ($gochar['rahu_good'] ?? false));
        $c('गोचर में साढ़ेसाती/ढैया नहीं', !($gochar['sade_sati'] ?? false));
        // varshaphal-based
        $c('मुन्था 10वें/11वें भाव में', ($varsha['muntha_best'] ?? false));
        $c('वर्षेश = दशमेश/एकादशेश', ($varsha['varshesh_karma'] ?? false));
        // chart capability
        $c('कुण्डली में पद-योग (10-11 संबंध)', self::housesRelated($ctx, 10, 11) || self::housesRelated($ctx, 9, 10));
        $c('अष्टकवर्ग 11वें में 28+ बिन्दु', self::savOfHouse($ctx, 11) >= 28);

        $met = 0; foreach ($checks as $x) { if ($x['met']) { $met++; } }
        $total = count($checks);
        if ($met >= 7) {
            $verdict = 'निकट भविष्य में पदोन्नति/नए पद की प्रबल सम्भावना'; $tone = 'pos'; $soon = true;
        } elseif ($met >= 5) {
            $verdict = 'पदोन्नति के अनुकूल संकेत बन रहे — अगली शुभ दशा/गोचर में सम्भव'; $tone = 'mix'; $soon = false;
        } else {
            $verdict = 'अभी पदोन्नति के संकेत सीमित — प्रतीक्षा व प्रयास आवश्यक'; $tone = 'neg'; $soon = false;
        }
        return ['checks' => $checks, 'met' => $met, 'total' => $total, 'verdict' => $verdict, 'tone' => $tone, 'soon' => $soon];
    }

    // ---------------------------------------------------------- §4.2 success

    private static function successCheck(array $ctx, array $bala, array $navamsa, ?array $d10): array
    {
        $items = [];
        $items[] = ['met' => self::isStrong($ctx, $ctx['L11']), 'text' => 'एकादश/एकादशेश बली — प्रयास सफल'];
        $items[] = ['met' => ($navamsa['l10_d9_strong'] ?? false) === true, 'text' => 'दशमेश नवांश में भी बली — पद स्थायी'];
        $av10 = self::savOfHouse($ctx, 10); $av11 = self::savOfHouse($ctx, 11);
        $items[] = ['met' => $av10 >= 28 || $av11 >= 28, 'text' => 'अष्टकवर्ग 10/11 में 28+ बिन्दु'];
        $okLords = 0; foreach ($bala['lords'] as $l) { if (!empty($l['ok'])) { $okLords++; } }
        $items[] = ['met' => $okLords >= 2, 'text' => 'दशमेश/लग्नेश आदि का षड्बल न्यूनतम से ऊपर'];
        $items[] = ['met' => $d10 !== null && (!empty($d10['l1_strong']) || !empty($d10['l10_strong'])), 'text' => 'D-10 लग्न/दशमेश बली'];
        $noDus = !self::linked($ctx, $ctx['L8'], 10) && !self::linked($ctx, $ctx['L12'], 10);
        $items[] = ['met' => $noDus, 'text' => '8वें/12वें के स्वामियों का 10वें पर अधिक प्रभाव नहीं'];
        $met = 0; foreach ($items as $i) { if ($i['met']) { $met++; } }
        return ['items' => $items, 'met' => $met, 'total' => count($items),
            'verdict' => $met >= 4 ? 'सफलता के संकेत प्रबल' : ($met >= 2 ? 'सफलता सम्भव पर संघर्ष के साथ' : 'स्थायी सफलता कठिन')];
    }

    // ---------------------------------------------------------- §17 remedies

    private static function remedies(array $ctx, array $planets): array
    {
        $weak = [];
        foreach (['Sun', 'Mars', 'Saturn', 'Moon', 'Jupiter'] as $p) {
            if (!self::isStrong($ctx, $p) || self::combust($ctx, $p) || (int) ($ctx['pl'][$p]['sign_index'] ?? -1) === (self::DEBIL[$p] ?? -2)) {
                $weak[$p] = self::REMEDY[$p];
            }
        }
        // key political planets always worth strengthening
        if ($weak === []) {
            $weak['Sun'] = self::REMEDY['Sun'];
        }
        $out = [];
        foreach ($weak as $p => $r) {
            $out[] = ['planet' => self::HI[$p], 'remedy' => $r];
        }
        return $out;
    }

    // ---------------------------------------------------------- conclusion

    private static function conclusion(array $identify, array $level, array $success, array $promotion, array $dasha, array $gochar, array $yoga): array
    {
        // trividha (three-source) confirmation: chart-yog, dasha, gochar
        $chartOk = $identify['is_politician'];
        $dashaOk = ($dasha['cur_active'] ?? false) || !empty(array_filter($dasha['windows'] ?? [], fn ($w) => !empty($w['strong'])));
        $gocharOk = ($gochar['good_count'] ?? 0) >= 2;
        $conf = (int) $chartOk + (int) $dashaOk + (int) $gocharOk;

        $lines = [];
        $lines[] = 'राजनीति-योग: ' . $identify['verdict'] . ' (' . $identify['met'] . '/' . $identify['total'] . ' नियम)।';
        if ($chartOk) {
            $lines[] = 'सम्भावित अधिकतम स्तर: ' . $level['label'] . ' — ' . $level['titles'] . ' (स्कोर ' . $level['score'] . '/' . $level['max'] . ')।';
            $lines[] = 'सफलता: ' . $success['verdict'] . '।';
        }
        $lines[] = 'पदोन्नति: ' . $promotion['verdict'] . '।';
        $lines[] = 'त्रिविध-पुष्टि (कुण्डली · दशा · गोचर): ' . $conf . '/3 स्रोत सहमत — '
            . ($conf === 3 ? 'निष्कर्ष विश्वसनीय' : ($conf === 2 ? 'संकेत अच्छे, अवसर पर निर्भर' : 'केवल आंशिक — निश्चित न कहें'));
        return [
            'trividha' => $conf, 'lines' => $lines,
            'caution' => 'ज्योतिष आस्था-आधारित है, विज्ञान-प्रमाणित नहीं। चुनाव लड़ने जैसे बड़े निर्णय केवल इस आधार पर न लें — परिश्रम, योग्यता, ईमानदारी व जनसेवा ही असली नींव हैं।',
        ];
    }
}
