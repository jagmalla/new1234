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
 * करियर / नौकरी-व्यवसाय (Job · Work · Business) — computed analysis.
 *
 * Implements the owner's Career-Prediction manual (Karma Bhava · Navamsha ·
 * Dashamsha · Shadbala · Shodashvarga · Bhavat-Bhavam/7th · Kendra-Trikona ·
 * Dasha · Gochar · Varshaphal). Uses REAL computed Shadbala, Vimshopaka and
 * Ashtakavarga from the engine — every verdict carries its reason.
 *
 * Follows the manual's discipline: convergence of independent indicators; a
 * profession is named only where the majority point the same way; never one rule.
 */
final class CareerEngine
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

    /** Short planet → profession fields (from §10.1). */
    private const PLANET_PROF = [
        'Sun' => 'सरकारी सेवा/प्रशासन, नेतृत्व, राजनीति, चिकित्सा, स्वर्ण/ऊर्जा, उच्च-पद',
        'Moon' => 'जनसंपर्क, नर्सिंग/देखभाल, आतिथ्य/होटल, खाद्य-डेयरी, जल/नौवहन, FMCG, पर्यटन',
        'Mars' => 'सेना/पुलिस, इंजीनियरिंग, सर्जरी, रियल-एस्टेट/निर्माण, धातु/मशीनरी, खेल, सुरक्षा',
        'Mercury' => 'व्यापार/लेखा (CA), बैंकिंग-वित्त, लेखन/पत्रकारिता, IT-सॉफ्टवेयर, विपणन, शिक्षण, ज्योतिष',
        'Jupiter' => 'शिक्षण/प्राध्यापन, विधि/न्याय, बैंकिंग/वित्तीय-सलाह, परामर्श, धर्म/ट्रस्ट, प्रबंधन',
        'Venus' => 'कला/मनोरंजन/फिल्म, फैशन/सौंदर्य, आभूषण/विलासिता, आतिथ्य/इवेंट, वस्त्र, वाहन-शोरूम',
        'Saturn' => 'न्याय/प्रशासन, श्रम-प्रधान उद्योग/निर्माण, खनन/तेल, लोहा-इस्पात, कृषि/भूमि, दीर्घ-सेवा',
        'Rahu' => 'IT/इंटरनेट, विमानन/तकनीक, विदेश/MNC, आयात-निर्यात, फार्मा/रसायन, अनुसंधान, सट्टा',
        'Ketu' => 'अनुसंधान/विज्ञान, ज्योतिष/अध्यात्म, गणित/प्रोग्रामिंग, चिकित्सा-सटीकता, फॉरेंसिक, NGO',
    ];

    /** 10th-lord-in-house → field of activity (§10.3). */
    private const HOUSE10_FIELD = [
        1 => 'स्व-निर्मित/स्व-रोज़गार, व्यक्तिगत ब्रांड, उद्यमिता',
        2 => 'पारिवारिक व्यवसाय, बैंकिंग/वित्त, खाद्य, वाणी-आधारित कार्य, आभूषण',
        3 => 'मीडिया/संचार, बिक्री/विपणन, लेखन/प्रकाशन, परिवहन, साहस-कार्य',
        4 => 'रियल-एस्टेट/वाहन, शिक्षा-क्षेत्र, कृषि, जन-उपयोगिता, गृह-भूमि सेवा',
        5 => 'शिक्षा/मनोरंजन, सट्टा/शेयर-बाज़ार, खेल, राजनीति, रचनात्मक कला, सलाह',
        6 => 'नौकरी/सेवा, चिकित्सा/स्वास्थ्य, विधि/मुकदमा, रक्षा/पुलिस, बैंकिंग-ऋण, प्रतिस्पर्धा',
        7 => 'व्यापार/साझेदारी, परामर्श, विदेशी सहयोग, ग्राहक-सेवा, कूटनीति',
        8 => 'बीमा/अनुसंधान, गूढ़-विद्या, सर्जरी, खनन, कर, विरासत — रुकावट/परिवर्तन सहित',
        9 => 'शिक्षण/विधि/धर्म, विदेश-संबंध, प्रकाशन, दीर्घ-यात्रा, गुरु/पिता से भाग्य',
        10 => 'अपने क्षेत्र में शक्तिशाली करियर, उच्च-पद, अधिकार',
        11 => 'कॉर्पोरेट/बड़े संगठन, नेटवर्क-आय, वरिष्ठ-पद, संघ/एसोसिएशन, उच्च-लाभ',
        12 => 'विदेश/MNC, अस्पताल, back-office/offshore, निर्यात, एकांत-अनुसंधान, आध्यात्मिक संस्थान',
    ];

    /** sign in 10th (or of 10th lord) → career fields (§10.2). */
    private const SIGN_FIELD = [
        0 => 'रक्षा/पुलिस, इंजीनियरिंग, खेल, सर्जरी, उद्यमिता, मशीनरी',
        1 => 'बैंकिंग/वित्त, विलासिता, खाद्य, कृषि, संगीत/कला, रियल-एस्टेट',
        2 => 'मीडिया/पत्रकारिता, IT/टेलीकॉम, बिक्री/विपणन, लेखन, व्यापार, परिवहन',
        3 => 'आतिथ्य, नर्सिंग/स्वास्थ्य, खाद्य-पेय, नौवहन, रियल-एस्टेट, मनोविज्ञान',
        4 => 'सरकार/प्रशासन, राजनीति, मनोरंजन, कॉर्पोरेट-नेतृत्व, स्वर्ण, शेयर-बाज़ार',
        5 => 'लेखा/ऑडिट, चिकित्सा/फार्मेसी, विश्लेषण, संपादन, सेवा-उद्योग',
        6 => 'विधि/न्याय, फैशन/डिज़ाइन, कूटनीति, व्यापार/साझेदारी, HR, इवेंट',
        7 => 'अनुसंधान/जाँच, सर्जरी, बीमा, गूढ़-विद्या, खनन/रसायन, फार्मा',
        8 => 'विधि, शिक्षण/अकादमिक, धर्म/दर्शन, प्रकाशन, विदेश-व्यापार, बैंकिंग',
        9 => 'सिविल-सेवा/प्रशासन, बड़े उद्योग, निर्माण/अवसंरचना, खनन, राजनीति',
        10 => 'तकनीक/नवाचार, सामाजिक-संगठन/NGO, विमानन, इलेक्ट्रॉनिक्स, अनुसंधान, ज्योतिष',
        11 => 'अस्पताल/उपचार, अध्यात्म/आश्रम, फिल्म/कल्पना-कला, रसायन/जल, विदेश, समुद्री-कार्य',
    ];

    /** planet remedies (career-neutral, safe). */
    private const REMEDY = [
        'Sun' => 'रविवार सूर्य को जल अर्पण, आदित्य-हृदय पाठ; गुड़/गेहूँ दान; पिता व अधिकारियों का सम्मान।',
        'Moon' => 'सोमवार शिव-पूजन; चाँदी/चावल/दूध दान; माता की सेवा।',
        'Mars' => 'मंगलवार हनुमान-उपासना; मसूर/गुड़ दान; भाई-बंधुओं से सौहार्द।',
        'Mercury' => 'बुधवार गणेश/विष्णु-पूजन; हरी मूँग/हरी वस्तु दान; कन्याओं का सम्मान।',
        'Jupiter' => 'बृहस्पतिवार व्रत व गुरु-उपासना; पीली वस्तु/चने की दाल/हल्दी दान।',
        'Venus' => 'शुक्रवार लक्ष्मी-पूजन; सफेद वस्त्र/चीनी दान; स्त्री-सम्मान।',
        'Saturn' => 'शनिवार शनि-मंत्र व हनुमान-उपासना; सरसों-तेल/काले तिल दान; श्रमिकों-वंचितों की सेवा।',
        'Rahu' => 'राहु-मंत्र व सरस्वती-उपासना; नारियल/कंबल/उड़द दान; अनुशासन।',
        'Ketu' => 'गणेश-उपासना व केतु-मंत्र; कुत्ते को भोजन; कंबल दान।',
    ];

    /**
     * @param array<string,mixed> $chart D1 chart (must carry 'shadbala')
     * @param array<string,mixed> $vargas divisional charts (D9, D10, D24, D16, D60…)
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
        ?array $birth = null
    ): array {
        if (empty($chart['planets']) || empty($chart['ascendant'])) {
            return ['ok' => false, 'error' => 'चार्ट उपलब्ध नहीं'];
        }
        $asc = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $pl = $chart['planets'];
        $shad = $chart['shadbala'] ?? [];

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
        // Vimshopaka (Shodashvarga 20-pt) from sidereal longitudes
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
            'shad' => $shad, 'vim' => $vimScore, 'sav' => self::savMap($chart),
            'lordOf' => static fn (int $h): string => self::SIGN_LORD[($asc + $h - 1) % 12],
        ];
        $ctx['L1'] = ($ctx['lordOf'])(1);
        $ctx['L10'] = ($ctx['lordOf'])(10);

        $reference = self::reference($ctx);
        $tenth = self::tenthHouse($ctx, $vargas);
        $navamsha = self::navamsha($ctx, $vargas, $reference);
        $dashamsha = self::dashamsha($ctx, $vargas);
        $shadbala = self::shadbalaSection($ctx);
        $vimshopaka = self::vimshopakaSection($ctx, $tenth, $navamsha);
        $bhavat = self::bhavatBhavam($ctx, $vargas);
        $yoga = self::careerYogas($ctx);
        $saturn = self::saturnAndShatru($ctx);
        $profession = self::profession($ctx, $vargas, $tenth, $navamsha, $shadbala);
        $jvb = self::jobVsBusiness($ctx, $vargas, $shadbala);
        $dasha = self::dashaSection($ctx, $moonLon, $birthJd, $nowJd, $tz);
        $timing = self::timing($ctx, $moonLon, $birthJd, $nowJd, $tz, $engine, $chart, $birth);
        $remedies = self::remedies($ctx);
        $conclusion = self::conclusion($tenth, $profession, $jvb, $timing);

        return [
            'ok' => true,
            'lagna_hi' => self::signHi($asc),
            'reference' => $reference,
            'tenth' => $tenth,
            'navamsha' => $navamsha,
            'dashamsha' => $dashamsha,
            'shadbala' => $shadbala,
            'vimshopaka' => $vimshopaka,
            'bhavat' => $bhavat,
            'yoga' => $yoga,
            'saturn' => $saturn,
            'profession' => $profession,
            'job_business' => $jvb,
            'dasha' => $dasha,
            'timing' => $timing,
            'remedies' => $remedies,
            'conclusion' => $conclusion,
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

    private static function element(int $sign): string
    {
        static $e = [0 => 'अग्नि', 4 => 'अग्नि', 8 => 'अग्नि', 1 => 'पृथ्वी', 5 => 'पृथ्वी', 9 => 'पृथ्वी',
            2 => 'वायु', 6 => 'वायु', 10 => 'वायु', 3 => 'जल', 7 => 'जल', 11 => 'जल'];
        return $e[$sign] ?? '';
    }

    private static function modality(int $sign): string
    {
        // 0,3,6,9 movable; 1,4,7,10 fixed; 2,5,8,11 dual
        if (in_array($sign, [0, 3, 6, 9], true)) {
            return 'चर (बदलता करियर)';
        }
        if (in_array($sign, [1, 4, 7, 10], true)) {
            return 'स्थिर (एक ही करियर)';
        }
        return 'द्विस्वभाव (दो/बहु-कौशल)';
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

    private static function digBala(array $ctx, string $p): float
    {
        return (float) ($ctx['shad'][$p]['dig'] ?? 0.0);
    }

    private static function nakLordOf(array $ctx, string $p): string
    {
        static $NL = ['Ketu', 'Venus', 'Sun', 'Moon', 'Mars', 'Rahu', 'Jupiter', 'Saturn', 'Mercury'];
        $idx = (int) ($ctx['pl'][$p]['nakshatra']['index'] ?? 0);
        return $NL[$idx % 9];
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

    private static function vargaSignOf(array $varga, string $planet): ?int
    {
        foreach ($varga['planets'] ?? [] as $p) {
            if ($p['name'] === $planet) {
                return (int) $p['sign'];
            }
        }
        return null;
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

    private static function isStrong(array $ctx, string $p): bool
    {
        return in_array($ctx['dig'][$p]['dignity']['tier'] ?? '', ['param_uchcha', 'exalt', 'moolatrikona', 'own', 'great_friend', 'friend'], true)
            || self::ratio($ctx, $p) >= 1.0;
    }

    // --------------------------------------------------------- reference point

    private static function reference(array $ctx): array
    {
        $cands = [
            'lagna' => ['label' => 'लग्न', 'r' => self::ratio($ctx, $ctx['L1']), 'via' => 'लग्नेश ' . self::HI[$ctx['L1']]],
            'moon' => ['label' => 'चन्द्र', 'r' => self::ratio($ctx, 'Moon'), 'via' => 'चन्द्र'],
            'sun' => ['label' => 'सूर्य', 'r' => self::ratio($ctx, 'Sun'), 'via' => 'सूर्य'],
        ];
        $bestKey = 'lagna';
        foreach ($cands as $k => $c) {
            if ($c['r'] > $cands[$bestKey]['r']) {
                $bestKey = $k;
            }
        }
        // reference house from which the 10th is read
        $refHouse = $bestKey === 'lagna' ? 1 : ($ctx['house'][$bestKey === 'moon' ? 'Moon' : 'Sun'] ?? 1);
        $refSign = $bestKey === 'lagna' ? $ctx['asc'] : (int) ($ctx['pl'][$bestKey === 'moon' ? 'Moon' : 'Sun']['sign_index'] ?? 0);
        return [
            'key' => $bestKey, 'label' => $cands[$bestKey]['label'], 'via' => $cands[$bestKey]['via'],
            'ratio' => round($cands[$bestKey]['r'], 2), 'ref_sign' => $refSign,
            'text' => 'सबसे बली संदर्भ-बिंदु: ' . $cands[$bestKey]['label'] . ' (शड्बल अनुपात ' . round($cands[$bestKey]['r'], 2)
                . ')। इसी से 10वाँ भाव (कर्म) प्रधान रूप से पढ़ा जाता है — शास्त्र: लग्न/चन्द्र/सूर्य में से सबसे बली से आजीविका देखें।',
            'lagna_r' => round($cands['lagna']['r'], 2), 'moon_r' => round($cands['moon']['r'], 2), 'sun_r' => round($cands['sun']['r'], 2),
        ];
    }

    // --------------------------------------------------------- 10th house

    private static function tenthHouse(array $ctx, array $vargas): array
    {
        $asc = $ctx['asc'];
        $tenthSign = ($asc + 9) % 12;
        $L10 = $ctx['L10'];
        $l10h = $ctx['house'][$L10] ?? 0;
        $l10sign = (int) ($ctx['pl'][$L10]['sign_index'] ?? 0);
        $occ10 = $ctx['occ'][10] ?? [];
        $aspOn10 = [];
        foreach (self::PLANETS as $p) {
            if (self::aspectsHouse($ctx, $p, 10)) {
                $aspOn10[] = $p;
            }
        }
        $sav10 = (int) ($ctx['sav'][$tenthSign] ?? 0);

        // field description: occupants override the lord (§2.1)
        $fieldPlanets = $occ10 !== [] ? $occ10 : [$L10];
        $fields = [];
        foreach ($fieldPlanets as $p) {
            $fields[] = self::PLANET_PROF[$p] ?? '';
        }

        return [
            'sign_hi' => self::signHi($tenthSign), 'element' => self::element($tenthSign), 'modality' => self::modality($tenthSign),
            'sign_field' => self::SIGN_FIELD[$tenthSign] ?? '',
            'occupants' => array_map(fn ($x) => self::HI[$x], $occ10),
            'occ_field' => $occ10 !== [] ? implode(' | ', array_map(fn ($x) => self::HI[$x] . ': ' . (self::PLANET_PROF[$x] ?? ''), $occ10)) : '',
            'aspects' => array_map(fn ($x) => self::HI[$x], $aspOn10),
            'lord' => $L10, 'lord_hi' => self::HI[$L10], 'lord_house' => $l10h, 'lord_house_ord' => self::ord($l10h),
            'lord_sign_hi' => self::signHi($l10sign),
            'lord_dignity' => $ctx['dig'][$L10]['dignity']['word'] ?? '',
            'lord_nak_lord' => self::HI[self::nakLordOf($ctx, $L10)] ?? '',
            'lord_combust' => ($ctx['dig'][$L10]['combust']['pct'] ?? 0) >= 40,
            'lord_retro' => !empty($ctx['pl'][$L10]['retro']),
            'lord_field' => self::HOUSE10_FIELD[$l10h] ?? '',
            'lord_ratio' => round(self::ratio($ctx, $L10), 2), 'lord_vim' => ($ctx['vim'])($L10),
            'sav' => $sav10, 'sav_band' => $sav10 >= 30 ? 'बलवान' : ($sav10 >= 25 ? 'सामान्य' : 'कमज़ोर'),
            'upachaya_note' => 'यह उपचय भाव है — यहाँ पाप ग्रह (मंगल/शनि/राहु/सूर्य) कठोर-परिश्रमी सांसारिक सफलता देते हैं; शुभ ग्रह सम्मानित पर कम आक्रामक करियर।',
        ];
    }

    // --------------------------------------------------------- navamsha (Karmajiva)

    private static function navamsha(array $ctx, array $vargas, array $reference): array
    {
        // 10th lord from the STRONGEST reference (Lagna/Moon/Sun)
        $refSign = (int) $reference['ref_sign'];
        $tenthFromRef = ($refSign + 9) % 12;
        $refL10 = self::SIGN_LORD[$tenthFromRef];
        // navamsa sign of that lord → dispositor = livelihood source
        $navSignName = (string) ($ctx['pl'][$refL10]['navamsa_sign'] ?? '');
        $navSign = array_search($navSignName, Charts::SIGNS, true);
        $navSign = $navSign === false ? 0 : (int) $navSign;
        $dispositor = self::SIGN_LORD[$navSign];
        $dispStrong = self::isStrong($ctx, $dispositor);
        // vargottama check of the reference 10th lord
        $d9sign = $vargas['D9'] ?? null ? self::vargaSignOf($vargas['D9'], $refL10) : null;
        $vargottama = $d9sign !== null && $d9sign === (int) ($ctx['pl'][$refL10]['sign_index'] ?? -1);

        return [
            'ref' => $reference['label'], 'ref_l10' => self::HI[$refL10] ?? $refL10,
            'nav_sign_hi' => self::signHi($navSign), 'dispositor' => $dispositor, 'dispositor_hi' => self::HI[$dispositor] ?? $dispositor,
            'disp_strong' => $dispStrong, 'vargottama' => $vargottama,
            'source' => self::PLANET_PROF[$dispositor] ?? '',
            'text' => 'कर्मजीव नियम (बृहत् जातक): ' . ($reference['label']) . ' से 10वें भाव का स्वामी ' . (self::HI[$refL10] ?? $refL10)
                . ' नवांश में ' . self::signHi($navSign) . ' राशि में है; उसका स्वामी ' . (self::HI[$dispositor] ?? $dispositor)
                . ' = आजीविका का स्रोत। ' . ($dispStrong ? 'यह ग्रह बली है — आजीविका स्थिर व समृद्ध।' : 'यह ग्रह दुर्बल — इस क्षेत्र में परिश्रम/संघर्ष के साथ आय।')
                . ($vargottama ? ' (10वें स्वामी वर्गोत्तम — करियर विशेष स्थिर।)' : ''),
        ];
    }

    // --------------------------------------------------------- dashamsha (D10)

    private static function dashamsha(array $ctx, array $vargas): ?array
    {
        $d10 = $vargas['D10'] ?? null;
        if ($d10 === null || empty($d10['planets'])) {
            return null;
        }
        $ascSign = (int) ($d10['asc_sign'] ?? 0);
        $d10L1 = self::SIGN_LORD[$ascSign];
        $d10L1h = self::vargaHouseOf($d10, $d10L1);
        $tenthSign = ($ascSign + 9) % 12;
        $d10L10 = self::SIGN_LORD[$tenthSign];
        $d10L10h = self::vargaHouseOf($d10, $d10L10);
        // occupants of D10 10th, and of D10 6th/7th
        $hOf = [];
        foreach ($d10['planets'] as $p) {
            $hOf[$p['name']] = ((((int) $p['sign'] - $ascSign) % 12) + 12) % 12 + 1;
        }
        $occD10 = static function (int $h) use ($hOf): array {
            $o = [];
            foreach ($hOf as $n => $hh) {
                if ($hh === $h && in_array($n, self::PLANETS, true)) {
                    $o[] = self::HI[$n];
                }
            }
            return $o;
        };
        $sixthCnt = 0; $seventhCnt = 0;
        foreach ($hOf as $n => $hh) {
            if (!in_array($n, self::SEVEN, true)) {
                continue;
            }
            if ($hh === 6) { $sixthCnt++; }
            if ($hh === 7) { $seventhCnt++; }
        }
        // natal 10th lord placement inside D10
        $natalL10inD10 = self::vargaHouseOf($d10, $ctx['L10']);
        $sunH = $hOf['Sun'] ?? 0; $satH = $hOf['Saturn'] ?? 0;

        return [
            'lagna_hi' => self::signHi($ascSign),
            'lagnesh' => self::HI[$d10L1] ?? $d10L1, 'lagnesh_house' => $d10L1h,
            'tenth_lord' => self::HI[$d10L10] ?? $d10L10, 'tenth_lord_house' => $d10L10h,
            'tenth_occ' => $occD10(10),
            'sixth_planets' => $sixthCnt, 'seventh_planets' => $seventhCnt,
            'natal_l10_house' => $natalL10inD10,
            'sun_kendra' => in_array($sunH, [1, 4, 7, 10], true),
            'saturn_house' => $satH,
            'text' => 'D-10 (दशांश — कर्म का मुख्य वर्ग): लग्न ' . self::signHi($ascSign) . ', लग्नेश ' . (self::HI[$d10L1] ?? $d10L1)
                . ($d10L1h ? ' ' . self::ord($d10L1h) . ' भाव में' : '') . '। '
                . ($natalL10inD10 ? 'जन्म-10वें स्वामी D-10 में ' . self::ord($natalL10inD10) . ' भाव में — ' . (in_array($natalL10inD10, [1, 4, 5, 7, 9, 10, 11], true) ? 'D-1 का वादा फलित।' : 'बार-बार पेशेवर उतार-चढ़ाव संभव।') : ''),
        ];
    }

    // --------------------------------------------------------- shadbala

    private static function shadbalaSection(array $ctx): array
    {
        $rank = [];
        foreach (self::SEVEN as $p) {
            $rank[$p] = self::ratio($ctx, $p);
        }
        arsort($rank);
        $rows = [];
        foreach ($rank as $p => $r) {
            $rows[] = ['hi' => self::HI[$p], 'planet' => $p, 'ratio' => round($r, 2), 'strong' => $r >= 1.0, 'dig' => round(self::digBala($ctx, $p), 1)];
        }
        $strongest = array_key_first($rank);
        // Dig-bala holder in the 10th
        $dig10 = null; $digMax = -1;
        foreach ($ctx['occ'][10] ?? [] as $p) {
            if (in_array($p, self::SEVEN, true) && self::digBala($ctx, $p) > $digMax) {
                $digMax = self::digBala($ctx, $p);
                $dig10 = $p;
            }
        }
        return [
            'rows' => $rows, 'strongest' => $strongest, 'strongest_hi' => self::HI[$strongest],
            'strongest_field' => self::PLANET_PROF[$strongest] ?? '',
            'strongest_nak' => self::HI[self::nakLordOf($ctx, $strongest)] ?? '',
            'dig10' => $dig10, 'dig10_hi' => $dig10 ? self::HI[$dig10] : '',
            'text' => 'सर्वाधिक बली ग्रह ' . self::HI[$strongest] . ' (शड्बल ' . round($rank[$strongest], 2) . ') = चार्ट का "करियर-इंजन"। '
                . 'इसका क्षेत्र: ' . (self::PLANET_PROF[$strongest] ?? '') . '। '
                . ($dig10 ? '10वें भाव में दिग्बली ' . self::HI[$dig10] . ' — यह अपने स्वभाव के अनुसार करियर की दिशा प्रबल रूप से तय करता है।' : ''),
        ];
    }

    private static function vimshopakaSection(array $ctx, array $tenth, array $navamsha): array
    {
        $cands = array_unique(array_filter([$ctx['L10'], $navamsha['dispositor'] ?? null]));
        foreach ($ctx['occ'][10] ?? [] as $p) {
            $cands[] = $p;
        }
        $cands = array_values(array_unique($cands));
        $rows = [];
        foreach ($cands as $p) {
            if (!in_array($p, self::SEVEN, true)) {
                continue;
            }
            $v = ($ctx['vim'])($p);
            $band = $v >= 18 ? 'उत्कृष्ट' : ($v >= 15 ? 'बहुत अच्छा' : ($v >= 10 ? 'सामान्य' : 'दुर्बल'));
            $rows[] = ['hi' => self::HI[$p], 'score' => $v, 'band' => $band];
        }
        return [
            'rows' => $rows,
            'text' => 'षोडशवर्ग (Shodashvarga) विम्शोपक बल — करियर-ग्रहों की 16 वर्गों में निरंतरता (20 में से): 18+ उत्कृष्ट, 15–17 बहुत अच्छा, 10–15 सामान्य, <10 दुर्बल। जो ग्रह सभी वर्गों में बली रहे वही करियर का फल पूर्णता से देता है।',
        ];
    }

    // --------------------------------------------------------- Bhavat Bhavam / 7th

    private static function bhavatBhavam(array $ctx, array $vargas): array
    {
        $asc = $ctx['asc'];
        $L7 = self::SIGN_LORD[($asc + 6) % 12];
        $L10 = $ctx['L10'];
        $seventhSign = ($asc + 6) % 12;
        $occ7 = $ctx['occ'][7] ?? [];
        $sav7 = (int) ($ctx['sav'][$seventhSign] ?? 0);
        $link = self::connect($ctx, $L7, $L10);
        return [
            'lord' => self::HI[$L7] ?? $L7, 'lord_house_ord' => self::ord($ctx['house'][$L7] ?? 0),
            'occupants' => array_map(fn ($x) => self::HI[$x], $occ7),
            'sav' => $sav7, 'l7_l10_link' => $link,
            'text' => 'भावात् भावम्: 10वें से 10वाँ = 7वाँ भाव — करियर का "छिपा दूसरा इंजन" (व्यापार, बाज़ार, ग्राहक, साझेदारी)। '
                . ($link ? '7वें व 10वें स्वामी का संबंध — साझेदारी/ग्राहक-आधारित करियर का प्रबल योग।' : '7वें व 10वें स्वामी में सीधा संबंध नहीं।')
                . ' जब 10वाँ कमज़ोर पर 7वाँ+11वाँ बली हों, तब व्यक्ति पद के बजाय व्यापार/नेटवर्क से समृद्ध होता है।',
        ];
    }

    // --------------------------------------------------------- career yogas

    private static function careerYogas(array $ctx): array
    {
        $asc = $ctx['asc'];
        $L9 = self::SIGN_LORD[($asc + 8) % 12];
        $L10 = $ctx['L10'];
        $L1 = $ctx['L1'];
        $l10h = $ctx['house'][$L10] ?? 0;
        $out = [];
        // 10th lord placement class
        if (in_array($l10h, [1, 4, 7, 10], true)) {
            $out[] = ['tone' => 'pos', 'text' => '10वें स्वामी ' . self::HI[$L10] . ' केंद्र (' . self::ord($l10h) . ') में — करियर जीवन का केंद्र; स्थिर व दृश्य पद।'];
        } elseif (in_array($l10h, [5, 9], true)) {
            $out[] = ['tone' => 'pos', 'text' => '10वें स्वामी ' . self::HI[$L10] . ' त्रिकोण (' . self::ord($l10h) . ') में — भाग्य/ज्ञान/नैतिकता से करियर की उन्नति।'];
        } elseif (in_array($l10h, [3, 6, 11], true)) {
            $out[] = ['tone' => 'pos', 'text' => '10वें स्वामी ' . self::HI[$L10] . ' उपचय (' . self::ord($l10h) . ') में — समय व परिश्रम से वृद्धि' . ($l10h === 6 ? ' (6ठा — नौकरी/सेवा हेतु उत्तम)' : ($l10h === 11 ? ' (11वाँ — उच्च-लाभ व वरिष्ठ-पद)' : '')) . '।'];
        } elseif (in_array($l10h, [8, 12], true)) {
            $out[] = ['tone' => 'info', 'text' => '10वें स्वामी ' . self::HI[$L10] . ' दुःस्थान (' . self::ord($l10h) . ') में — ' . ($l10h === 12 ? 'विदेश/MNC/अस्पताल/back-office/अनुसंधान; पीड़ित हो तो हानि।' : 'अनुसंधान/बीमा/गूढ़; अचानक उतार-चढ़ाव।')];
        }
        // Dharma-Karma-Adhipati yoga
        if (self::connect($ctx, $L9, $L10)) {
            $out[] = ['tone' => 'pos', 'text' => '★ धर्म-कर्म-अधिपति योग (नवमेश ' . self::HI[$L9] . ' + दशमेश ' . self::HI[$L10] . ') — सर्वोच्च करियर-योग: नेतृत्व, मंत्री-पद, उच्च-प्रबंधन, नाम व अधिकार।'];
        }
        // Lagnesh-10L
        if ($L1 !== $L10 && self::connect($ctx, $L1, $L10)) {
            $out[] = ['tone' => 'pos', 'text' => 'लग्नेश ' . self::HI[$L1] . ' + दशमेश ' . self::HI[$L10] . ' का संबंध — स्व-निर्मित करियर; पहचान व पेशा एकाकार।'];
        }
        // Pancha Mahapurusha (planet in own/exalt in kendra)
        static $mp = ['Mars' => 'रुचक (सेना/इंजीनियरिंग/खेल/सर्जरी)', 'Mercury' => 'भद्र (व्यापार/लेखन/विश्लेषण)',
            'Jupiter' => 'हंस (शिक्षण/विधि/सलाह/वित्त)', 'Venus' => 'मालव्य (कला/मीडिया/विलासिता)', 'Saturn' => 'शश (न्याय/प्रशासन/उद्योग/रियल-एस्टेट)'];
        foreach ($mp as $p => $desc) {
            $h = $ctx['house'][$p] ?? 0;
            $tier = $ctx['dig'][$p]['dignity']['tier'] ?? '';
            if (in_array($h, [1, 4, 7, 10], true) && in_array($tier, ['param_uchcha', 'exalt', 'moolatrikona', 'own'], true)) {
                $out[] = ['tone' => 'pos', 'text' => '★ पंच-महापुरुष योग — ' . $desc . ': इस ग्रह का क्षेत्र करियर पर राज-योग बल से छा जाता है।'];
            }
        }
        // Rahu-Ketu on 10-4 axis
        if (in_array($ctx['house']['Rahu'] ?? 0, [10, 4], true)) {
            $out[] = ['tone' => 'info', 'text' => 'राहु-केतु का 10-4 अक्ष पर — अपरंपरागत/विदेश-तकनीक/जन-स्तर का करियर; कार्य हेतु स्थान-परिवर्तन।'];
        }
        return $out;
    }

    // --------------------------------------------------------- Saturn (satrun) & 6th

    private static function saturnAndShatru(array $ctx): array
    {
        $sat = 'Saturn';
        $satH = $ctx['house'][$sat] ?? 0;
        $satRatio = round(self::ratio($ctx, $sat), 2);
        $on10 = self::linked($ctx, $sat, 10) || self::connect($ctx, $sat, $ctx['L10']) || self::linked($ctx, $sat, 1);
        $lines = [];
        $lines[] = 'शनि (कर्म व सेवा का कारक, "करियर की घड़ी") ' . self::ord($satH) . ' भाव में, शड्बल अनुपात ' . $satRatio . '।';
        if ($on10) {
            $lines[] = 'शनि का 10वें/दशमेश/लग्न पर प्रभाव — दीर्घ, अनुशासित वेतनभोगी सेवा; धीमी पर स्थिर उन्नति; श्रम-प्रधान/पुराने संगठन।';
        }
        if (self::ratio($ctx, $sat) >= 1.0 && in_array($satH, [1, 4, 7, 10], true)
            && in_array($ctx['dig'][$sat]['dignity']['tier'] ?? '', ['param_uchcha', 'exalt', 'moolatrikona', 'own'], true)) {
            $lines[] = '★ शश योग — शनि केंद्र में बली: न्याय/प्रशासन/उद्योग/रियल-एस्टेट में उच्च-पद।';
        }
        // 6th house (shatru / service / competition)
        $asc = $ctx['asc'];
        $L6 = self::SIGN_LORD[($asc + 5) % 12];
        $occ6 = $ctx['occ'][6] ?? [];
        $sixthStrong = self::isStrong($ctx, $L6) || $occ6 !== [];
        $lines[] = '6ठा भाव (शत्रु/सेवा/प्रतिस्पर्धा): स्वामी ' . (self::HI[$L6] ?? $L6) . ' ' . self::ord($ctx['house'][$L6] ?? 0) . ' भाव में'
            . ($occ6 ? ', स्थित: ' . implode(', ', array_map(fn ($x) => self::HI[$x], $occ6)) : '') . '। '
            . 'बली 6ठा भाव व शत्रु-भाव का बल = प्रतिस्पर्धा में विजय, नौकरी/सेवा-रेखा तथा मुकदमे/प्रतिद्वंद्वियों पर बढ़त।';
        return [
            'saturn_house_ord' => self::ord($satH), 'saturn_ratio' => $satRatio, 'saturn_on_10' => $on10,
            'sixth_strong' => $sixthStrong, 'lines' => $lines,
        ];
    }

    // --------------------------------------------------------- profession

    private static function profession(array $ctx, array $vargas, array $tenth, array $navamsha, array $shadbala): array
    {
        // dominant planets: 10th occupants, 10th lord, navamsha dispositor, strongest shadbala, dig-bala-10
        $weight = [];
        $bump = static function (string $p, float $w) use (&$weight): void {
            if ($p !== '' && in_array($p, self::PLANETS, true)) {
                $weight[$p] = ($weight[$p] ?? 0) + $w;
            }
        };
        foreach ($ctx['occ'][10] ?? [] as $p) {
            $bump($p, 3.0);
        }
        $bump($ctx['L10'], 2.5);
        $bump($navamsha['dispositor'] ?? '', 2.0);
        $bump($shadbala['strongest'] ?? '', 2.0);
        if (!empty($shadbala['dig10'])) {
            $bump($shadbala['dig10'], 1.5);
        }
        arsort($weight);
        $top = array_slice(array_keys($weight), 0, 3);
        $fields = [];
        foreach ($top as $p) {
            $fields[] = ['hi' => self::HI[$p], 'field' => self::PLANET_PROF[$p] ?? ''];
        }
        // planet-pair blend if the top two are a classic pair
        $pairTxt = self::pairBlend($top);
        return [
            'top' => $top, 'fields' => $fields, 'pair' => $pairTxt,
            'sign_flavour' => $tenth['sign_field'] ?? '',
            'house_field' => $tenth['lord_field'] ?? '',
            'text' => 'प्रमुख करियर-ग्रह (10वें के स्थित ग्रह + दशमेश + नवांश-स्वामी + सर्वाधिक बली): ' . implode(', ', array_map(fn ($x) => self::HI[$x], $top))
                . '। इन्हें 10वें की राशि-प्रवृत्ति व दशमेश के भाव-क्षेत्र के साथ मिलाकर सटीक पेशा बनता है।',
        ];
    }

    private static function pairBlend(array $top): string
    {
        if (count($top) < 2) {
            return '';
        }
        static $pairs = [
            'Sun|Mercury' => 'बुधादित्य — बुद्धि सहित प्रशासन: सिविल-सेवा, कॉर्पोरेट-प्रबंधन, सरकारी लेखा',
            'Sun|Mars' => 'पुलिस/सेना अधिकारी, सरकारी सर्जन, खेल-प्रशासन, ऊर्जा-क्षेत्र नेतृत्व',
            'Sun|Jupiter' => 'न्यायाधीश, वरिष्ठ नौकरशाह, मंत्री, विश्वविद्यालय-प्रमुख',
            'Sun|Saturn' => 'सरकारी श्रम/PSU उद्योग, तेल-खनन, अनुशासित प्रशासन, न्यायिक-कर्मी',
            'Moon|Mercury' => 'पत्रकारिता, जनसंचार, FMCG विपणन, यात्रा-लेखन, परामर्श, उपभोक्ता-खुदरा',
            'Moon|Venus' => 'होटल/आतिथ्य, खाद्य-पेय, सौंदर्य-उद्योग, जन-मनोरंजन, फैशन-खुदरा, विवाह-व्यवसाय',
            'Mars|Mercury' => 'तकनीकी-लेखन, इंजीनियरिंग-वाणिज्य, सॉफ्टवेयर-इंजी., खेल-पत्रकारिता, रियल-एस्टेट दलाली',
            'Mars|Saturn' => 'भारी-इंजीनियरिंग, निर्माण, खनन, रक्षा-हार्डवेयर, लोहा-इस्पात, मशीनरी',
            'Mars|Venus' => 'ऑटोमोबाइल, वस्त्र-मिल, कॉस्मेटिक-सर्जरी, खेल-मनोरंजन',
            'Mercury|Jupiter' => 'विधि, CA, शिक्षण, प्रकाशन, बैंकिंग, कॉर्पोरेट-प्रशिक्षण, परामर्श',
            'Mercury|Venus' => 'मीडिया/विज्ञापन, ग्राफिक-डिज़ाइन, एनिमेशन, संगीत-निर्माण, लक्ज़री-मार्केटिंग',
            'Mercury|Saturn' => 'ऑडिट/अनुपालन, डेटा-साइंस, मुद्रण, सांख्यिकी, बीमा back-office',
            'Jupiter|Venus' => 'वित्त/धन-सलाह, लक्ज़री-शिक्षा, कला-अकादमी, उच्च-परामर्श',
            'Jupiter|Saturn' => 'न्यायपालिका, नीति-निर्माण, संस्थागत-धर्म, अवसंरचना-वित्त, अर्थशास्त्र',
            'Venus|Saturn' => 'रियल-एस्टेट विकास, लक्ज़री-निर्माण, सिनेमा-प्रोडक्शन, कॉस्मेटिक-फैक्ट्री',
            'Rahu|Mercury' => 'सॉफ्टवेयर/IT, डिजिटल-मार्केटिंग, शेयर-डेरिवेटिव, गेमिंग, साइबर-सुरक्षा, e-commerce',
            'Rahu|Venus' => 'फिल्म/ग्लैमर, लक्ज़री-आयात, इवेंट, कॉस्मेटिक-व्यवसाय',
            'Rahu|Mars' => 'विस्फोटक/रसायन, अत्याधुनिक रक्षा-तकनीक, जोखिमपूर्ण उद्यम, अपराध-अन्वेषण',
            'Ketu|Mercury' => 'प्रोग्रामिंग/एल्गोरिद्म, सांख्यिकी, ज्योतिष, क्रिप्टोग्राफी, अनुसंधान',
            'Ketu|Jupiter' => 'आध्यात्मिक-शिक्षण, दर्शन, ज्योतिष-वेदांत, मठ-प्रशासन',
        ];
        $a = $top[0]; $b = $top[1];
        foreach ([[$a, $b], [$b, $a]] as [$x, $y]) {
            if (isset($pairs[$x . '|' . $y])) {
                return $pairs[$x . '|' . $y];
            }
        }
        return '';
    }

    // --------------------------------------------------------- job vs business

    private static function jobVsBusiness(array $ctx, array $vargas, array $shadbala): array
    {
        $asc = $ctx['asc'];
        $L6 = self::SIGN_LORD[($asc + 5) % 12];
        $L7 = self::SIGN_LORD[($asc + 6) % 12];
        $L10 = $ctx['L10'];
        $L1 = $ctx['L1'];
        $job = 0.0; $biz = 0.0;
        $jbits = []; $bbits = [];

        // JOB indicators
        if (self::isStrong($ctx, $L6) && (self::connect($ctx, $L6, $L10) || self::linked($ctx, $L6, 10))) {
            $job += 2; $jbits[] = '6ठा (सेवा) भाव 10वें से जुड़ा व बली — सेवा-योग।';
        }
        if (self::linked($ctx, 'Saturn', 10) || self::connect($ctx, 'Saturn', $L10) || self::linked($ctx, 'Saturn', 1)) {
            $job += 1.5; $jbits[] = 'शनि (सेवा-कारक) का 10वें/दशमेश/लग्न पर प्रभाव — दीर्घ वेतनभोगी सेवा।';
        }
        if (($ctx['house'][$L10] ?? 0) === 6 || ($ctx['house'][$L6] ?? 0) === 10) {
            $job += 2; $jbits[] = '10वें-6ठे स्वामी का परस्पर स्थान (सेवा-योग)।';
        }
        if (self::isStrong($ctx, 'Sun') && (self::linked($ctx, 'Saturn', 10) || self::linked($ctx, $L6, 10))) {
            $job += 1; $jbits[] = 'सूर्य + शनि/6ठे का संबंध — सरकारी नौकरी का संकेत।';
        }
        if (in_array($ctx['asc'], [1, 4, 7, 10], true) || in_array(($ctx['pl'][$L10]['sign_index'] ?? -1), [1, 4, 7, 10], true)) {
            $job += 0.6; $jbits[] = 'लग्न/10वें में स्थिर राशि — स्थिरता/नौकरी की ओर झुकाव।';
        }
        if (self::ratio($ctx, $L1) < 0.8 && self::ratio($ctx, 'Mars') < 0.8) {
            $job += 0.8; $jbits[] = 'लग्नेश व मंगल दुर्बल — जोखिम-क्षमता कम, सुरक्षा (नौकरी) की प्रवृत्ति।';
        }

        // BUSINESS indicators
        if (self::isStrong($ctx, $L7) && (self::connect($ctx, $L7, $L10) || self::linked($ctx, $L7, 10))) {
            $biz += 2; $bbits[] = '7वाँ (व्यापार) भाव 10वें से जुड़ा व बली — व्यापार-योग।';
        }
        if (self::isStrong($ctx, 'Mercury') && (self::linked($ctx, 'Mercury', 10) || self::linked($ctx, 'Mercury', 7) || self::linked($ctx, 'Mercury', 11))) {
            $biz += 1.5; $bbits[] = 'बली बुध (व्यापार-कारक) का 10वें/7वें/11वें से संबंध — व्यापार-कौशल।';
        }
        if (self::ratio($ctx, $L1) >= 1.0 && self::ratio($ctx, 'Mars') >= 1.0) {
            $biz += 1.5; $bbits[] = 'बली लग्नेश + बली मंगल — जोखिम-क्षमता व उद्यमिता।';
        }
        if (($ctx['house'][$L10] ?? 0) === 7 || ($ctx['house'][$L7] ?? 0) === 10) {
            $biz += 2; $bbits[] = '10वें-7वें स्वामी का परस्पर स्थान (व्यापार-योग)।';
        }
        if (in_array($ctx['house']['Rahu'] ?? 0, [3, 6, 10, 11], true)) {
            $biz += 0.8; $bbits[] = 'राहु उपचय (3/6/10/11) में — साहसी, अपरंपरागत, विस्तार-योग्य उद्यम।';
        }
        // Chandra-Mangala yoga
        if (self::connect($ctx, 'Moon', 'Mars')) {
            $biz += 1; $bbits[] = 'चन्द्र-मंगल योग — उद्यम से धनार्जन का शास्त्रीय योग (व्यवसायियों में सामान्य)।';
        }

        // 6L vs 7L strength (deciding, §11.3)
        $r6 = self::ratio($ctx, $L6) + ($ctx['vim'])($L6) / 20.0;
        $r7 = self::ratio($ctx, $L7) + ($ctx['vim'])($L7) / 20.0;
        if ($r6 > $r7 + 0.2) {
            $job += 1; $jbits[] = '6ठे स्वामी का बल 7वें से अधिक — जीवन में नौकरी/सेवा प्रधान।';
        } elseif ($r7 > $r6 + 0.2) {
            $biz += 1; $bbits[] = '7वें स्वामी का बल 6ठे से अधिक — जीवन में व्यापार प्रधान।';
        }

        $diff = $job - $biz;
        if (abs($diff) < 0.8) {
            $verdict = 'दोनों (नौकरी पहले, व्यवसाय बाद में)'; $tone = 'info';
            $text = '6ठा व 7वाँ दोनों पक्ष लगभग समान बली — प्रायः पहले नौकरी, फिर व्यवसाय; परिवर्तन प्रायः 7वें स्वामी/बुध/7वें-स्थित ग्रह की दशा में। हाइब्रिड "पेशेवर प्रैक्टिस" (डॉक्टर/वकील/CA/सलाहकार) भी उत्तम।';
        } elseif ($diff > 0) {
            $verdict = 'नौकरी / सेवा (Job)'; $tone = 'pos';
            $text = 'सेवा-पक्ष (6ठा भाव, शनि, स्थिरता) प्रबल — वेतनभोगी नौकरी/सेवा अधिक अनुकूल व सुखद रहेगी।';
        } else {
            $verdict = 'व्यवसाय / व्यापार (Business)'; $tone = 'pos';
            $text = 'व्यापार-पक्ष (7वाँ भाव, बुध, लग्न-मंगल बल) प्रबल — स्वतंत्र व्यवसाय/साझेदारी अधिक अनुकूल। पीड़ित 7वें में एकल-स्वामित्व श्रेष्ठ।';
        }
        return [
            'verdict' => $verdict, 'tone' => $tone, 'text' => $text,
            'job_score' => round($job, 1), 'biz_score' => round($biz, 1),
            'job_bits' => $jbits, 'biz_bits' => $bbits,
        ];
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
                $curMd = $md;
                $nextMd = $seq['mahadashas'][$i + 1] ?? null;
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
            $h = $ctx['house'][$lord] ?? 0;
            $touch = [];
            foreach ([10 => 'कर्म/पद', 6 => 'सेवा/प्रतिस्पर्धा', 7 => 'व्यापार/साझेदारी', 11 => 'लाभ/आय', 9 => 'भाग्य', 2 => 'धन', 12 => 'विदेश/व्यय', 8 => 'परिवर्तन/शोध'] as $hh => $lbl) {
                if (self::linked($ctx, $lord, $hh)) {
                    $touch[] = $lbl;
                }
            }
            return self::HI[$lord] . ' — क्षेत्र: ' . (self::PLANET_PROF[$lord] ?? '') . ($touch ? '; सक्रिय भाव: ' . implode(', ', array_slice($touch, 0, 4)) : '');
        };
        return [
            'has_dasha' => true,
            'current_md' => $curMd ? self::HI[$curMd['lord']] : '',
            'current_ad' => $curAd ? self::HI[$curAd['lord']] : '',
            'current_flavour' => $curMd ? $flavour($curMd['lord']) : '',
            'next_md' => $nextMd ? self::HI[$nextMd['lord']] : '',
            'next_from' => $nextMd ? JulianDay::toDmy($nextMd['start_jd'], $tz) : '',
            'next_flavour' => $nextMd ? $flavour($nextMd['lord']) : '',
            'text' => 'दशा-स्वामी करियर को अपने रंग में रंगता है — करियर उसके क्षेत्र, स्वामित्व-भाव व नक्षत्र की ओर झुकता है। '
                . ($nextMd ? 'आगामी ' . self::HI[$nextMd['lord']] . ' महादशा (' . JulianDay::toDmy($nextMd['start_jd'], $tz) . ' से) करियर को ' . (self::PLANET_PROF[$nextMd['lord']] ?? '') . ' की ओर मोड़ेगी — इसी अनुसार दिशा चुनें।' : ''),
        ];
    }

    // --------------------------------------------------------- timing (promotion/downfall)

    private static function timing(array $ctx, ?float $moonLon, ?float $birthJd, ?float $nowJd, float $tz,
        ?\AutoBusiness\Astro\Calc\CalculationEngine $engine, array $chart, ?array $birth): array
    {
        $asc = $ctx['asc'];
        // career-event karyeshes: 10L, 11L, 9L, planets-in-10, AmK(approx skipped)
        $L10 = $ctx['L10'];
        $L11 = self::SIGN_LORD[($asc + 10) % 12];
        $L9 = self::SIGN_LORD[($asc + 8) % 12];
        $L8 = self::SIGN_LORD[($asc + 7) % 12];
        $L12 = self::SIGN_LORD[($asc + 11) % 12];
        $riseSet = array_unique(array_merge([$L10, $L11, $L9], $ctx['occ'][10] ?? []));
        $fallSet = [];
        foreach (self::SEVEN as $p) {
            $combust = ($ctx['dig'][$p]['combust']['pct'] ?? 0) >= 40;
            $debil = ($ctx['dig'][$p]['dignity']['tier'] ?? '') === 'debil';
            if (($debil || $combust) && self::linked($ctx, $p, 10)) {
                $fallSet[$p] = true;
            }
        }
        if (self::linked($ctx, $L8, 10)) { $fallSet[$L8] = true; }
        if (self::linked($ctx, $L12, 10)) { $fallSet[$L12] = true; }

        $windows = []; $warnings = [];
        if ($moonLon !== null && $birthJd !== null && $nowJd !== null) {
            $seq = VimshottariDasha::sequence($moonLon, $birthJd);
            $horizon = $nowJd + 12.0 * 365.2564;
            foreach ($seq['mahadashas'] as $md) {
                if ($md['end_jd'] < $nowJd || $md['start_jd'] > $horizon) {
                    continue;
                }
                foreach (VimshottariDasha::antardashas($md) as $ad) {
                    if ($ad['end_jd'] < $nowJd || $ad['start_jd'] > $horizon) {
                        continue;
                    }
                    $riseHit = in_array($md['lord'], $riseSet, true) || in_array($ad['lord'], $riseSet, true);
                    $fallHit = isset($fallSet[$md['lord']]) || isset($fallSet[$ad['lord']]);
                    if ($riseHit) {
                        $windows[] = ['type' => 'rise', 'md' => $md, 'ad' => $ad,
                            'label' => self::HI[$md['lord']] . ' – ' . self::HI[$ad['lord']],
                            'from_jd' => $ad['start_jd'], 'end_jd' => $ad['end_jd'],
                            'from' => JulianDay::toDmy(max($ad['start_jd'], $nowJd), $tz), 'to' => JulianDay::toDmy($ad['end_jd'], $tz),
                            'why' => 'दशा-स्वामी ' . (in_array($md['lord'], $riseSet, true) ? self::HI[$md['lord']] : self::HI[$ad['lord']]) . ' कर्म/लाभ/भाग्य-कारक — पदोन्नति/विस्तार-योग्य।'];
                    }
                    if ($fallHit) {
                        $warnings[] = ['label' => self::HI[$md['lord']] . ' – ' . self::HI[$ad['lord']],
                            'from' => JulianDay::toDmy(max($ad['start_jd'], $nowJd), $tz), 'to' => JulianDay::toDmy($ad['end_jd'], $tz),
                            'from_jd' => $ad['start_jd'],
                            'why' => 'दशा-स्वामी 10वें से जुड़ा नीच/अस्त या 8वें/12वें का — इस अवधि में करियर-परीक्षा/ठहराव/परिवर्तन; सतर्कता व दस्तावेज़ीकरण।'];
                    }
                }
            }
            usort($windows, fn ($a, $b) => $a['from_jd'] <=> $b['from_jd']);
            usort($warnings, fn ($a, $b) => $a['from_jd'] <=> $b['from_jd']);
            $windows = array_slice($windows, 0, 3);
            $warnings = array_slice($warnings, 0, 2);
        }

        // auto Saturn-Jupiter double transit on the 10th per rise window
        $auto = $engine !== null && $birth !== null && !empty($chart['planets']);
        foreach ($windows as &$w) {
            if ($auto) {
                $mid = ($w['from_jd'] < $nowJd ? $nowJd : $w['from_jd']);
                $mid = ($mid + $w['end_jd']) / 2.0;
                try {
                    $w['gochar'] = self::gocharDouble($engine, $ctx, $mid);
                } catch (\Throwable $e) {
                    $w['gochar'] = null;
                }
            }
            $w['verdict'] = self::riseVerdict($w);
        }
        unset($w);

        // Sade-Sati note (Saturn transit over natal Moon sign) — a downfall/stress flag
        $sadeSati = null;
        if ($auto && $nowJd !== null) {
            try {
                $satSign = (int) floor(Charts::norm($engine->planetSiderealLon('Saturn', $nowJd)) / 30.0);
                $moonSign = (int) ($ctx['pl']['Moon']['sign_index'] ?? 0);
                $d = (($satSign - $moonSign) % 12 + 12) % 12;
                if (in_array($d, [11, 0, 1], true)) {
                    $sadeSati = 'अभी साढ़े-साती चल रही है (शनि जन्म-चन्द्र राशि के ' . ($d === 0 ? 'ऊपर' : ($d === 11 ? 'पूर्व' : 'पश्चात्')) . ')। कमज़ोर दशा के साथ यह करियर-पुनर्गठन/दबाव देती है — धैर्य व एकीकरण का काल।';
                } elseif ($d === 7) {
                    $sadeSati = 'अभी अष्टम शनि (शनि जन्म-चन्द्र से 8वें) — अचानक संरचनात्मक झटके/साझेदारी-तनाव संभव; बड़े जोखिम टालें।';
                }
            } catch (\Throwable $e) {
            }
        }

        $note = 'नियम: वादा (जन्म-कुंडली) → अनुमति (दशा) → ट्रिगर (गोचर)। सर्वाधिक विश्वसनीय — शनि-गुरु का द्विग्रह गोचर 10वें भाव/दशमेश पर, अनुकूल दशा के भीतर। '
            . 'माह सूर्य के 10वें/11वें गोचर व शुभ प्रत्यंतर्दशा से।';

        return ['windows' => $windows, 'warnings' => $warnings, 'sade_sati' => $sadeSati, 'note' => $note, 'has_dasha' => $moonLon !== null, 'auto' => $auto];
    }

    private static function gocharDouble(\AutoBusiness\Astro\Calc\CalculationEngine $engine, array $ctx, float $jd): array
    {
        $asc = (int) $ctx['asc'];
        $jH = Charts::houseFromAsc($engine->planetSiderealLon('Jupiter', $jd), $asc);
        $sH = Charts::houseFromAsc($engine->planetSiderealLon('Saturn', $jd), $asc);
        $touch = static function (int $from, array $off, int $t): bool {
            if ($from === $t) {
                return true;
            }
            foreach ($off as $k) {
                if (((($from - 1) + ($k - 1)) % 12) + 1 === $t) {
                    return true;
                }
            }
            return false;
        };
        $l10h = (int) ($ctx['house'][$ctx['L10']] ?? 0);
        $jup10 = $touch($jH, [5, 7, 9], 10) || ($l10h && $touch($jH, [5, 7, 9], $l10h));
        $sat10 = $touch($sH, [3, 7, 10], 10) || ($l10h && $touch($sH, [3, 7, 10], $l10h));
        $double = $jup10 && $sat10;
        if ($double) {
            $text = 'शनि-गुरु दोनों का 10वें भाव/दशमेश पर गोचर (द्विग्रह) — ठोस करियर-घटना (पदोन्नति/परिवर्तन/लॉन्च) की सर्वाधिक संभावना।'; $tone = 'pos';
        } elseif ($jup10) {
            $text = 'गुरु का 10वें/दशमेश पर गोचर — अवसर/पदोन्नति/नए ग्राहक; शनि का साथ मिलने पर घटना ठोस।'; $tone = 'pos';
        } elseif ($sat10) {
            $text = 'शनि का 10वें/दशमेश पर गोचर — भारी उत्तरदायित्व/दबाव जो प्रायः रैंक में बदलता है (गुरु के साथ श्रेष्ठ)।'; $tone = 'info';
        } else {
            $text = 'इस अवधि में शनि-गुरु का 10वें पर द्विग्रह गोचर नहीं — गोचर-समर्थन दुर्बल।'; $tone = 'info';
        }
        return ['double' => $double, 'any' => $jup10 || $sat10, 'text' => $text, 'tone' => $tone];
    }

    private static function riseVerdict(array $w): array
    {
        $g = !empty($w['gochar']['any']);
        $dbl = !empty($w['gochar']['double']);
        if ($dbl) {
            return ['label' => '★ प्रबल पदोन्नति/विस्तार — दशा + द्विग्रह गोचर', 'tone' => 'pos'];
        }
        if ($g) {
            return ['label' => 'अनुकूल — दशा + गोचर', 'tone' => 'pos'];
        }
        return ['label' => 'संभावना — दशा-खिड़की', 'tone' => 'info'];
    }

    // --------------------------------------------------------- remedies

    private static function remedies(array $ctx): array
    {
        $need = [];
        // afflicted career planets: 10L, Sun, Saturn if debil/combust/6-8-12
        foreach (array_unique([$ctx['L10'], 'Sun', 'Saturn', 'Mercury']) as $p) {
            $tier = $ctx['dig'][$p]['dignity']['tier'] ?? '';
            $combust = ($ctx['dig'][$p]['combust']['pct'] ?? 0) >= 40;
            $bad = in_array($ctx['house'][$p] ?? 0, [6, 8, 12], true);
            if (in_array($tier, ['debil', 'enemy', 'great_enemy'], true) || $combust || ($bad && $p === $ctx['L10'])) {
                $need[$p] = true;
            }
        }
        $rows = [];
        foreach (array_keys($need) as $p) {
            $rows[] = ['hi' => self::HI[$p], 'text' => self::REMEDY[$p] ?? ''];
        }
        return [
            'rows' => $rows,
            'note' => 'ये सहायक शास्त्रीय उपाय हैं — परिश्रम व कौशल-विकास के साथ करें। पीड़ित करियर-ग्रह की दशा में उपाय विशेष लाभकारी।',
        ];
    }

    // --------------------------------------------------------- conclusion

    private static function conclusion(array $tenth, array $profession, array $jvb, array $timing): string
    {
        $parts = [];
        $topHi = implode(', ', array_map(fn ($x) => self::HI[$x], $profession['top'] ?? []));
        $parts[] = 'करियर का मुख्य आधार 10वाँ भाव (' . ($tenth['sign_hi'] ?? '') . ', ' . ($tenth['modality'] ?? '') . ') व दशमेश ' . ($tenth['lord_hi'] ?? '') . ' (' . ($tenth['lord_house_ord'] ?? '') . ' भाव) है।';
        $parts[] = 'प्रमुख करियर-ग्रह: ' . $topHi . ($profession['pair'] ? ' — मिश्र क्षेत्र: ' . $profession['pair'] : '') . '।';
        $parts[] = 'नौकरी या व्यवसाय: ' . ($jvb['verdict'] ?? '') . ' — ' . ($jvb['text'] ?? '');
        if (!empty($timing['windows'])) {
            $w = $timing['windows'][0];
            $parts[] = 'निकटतम उन्नति-खिड़की: ' . $w['label'] . ' (' . $w['from'] . ' – ' . $w['to'] . ')।';
        }
        if (!empty($timing['warnings'])) {
            $parts[] = 'सतर्कता-काल: ' . $timing['warnings'][0]['label'] . ' (' . $timing['warnings'][0]['from'] . ' – ' . $timing['warnings'][0]['to'] . ')।';
        }
        $parts[] = 'नियम: कोई एक सूत्र अंतिम नहीं — D-1 + नवांश + D-10 + शड्बल की सहमति पर ही निर्णय दृढ़ माना जाए।';
        return implode(' ', $parts);
    }
}
