<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\Ashtakavarga;
use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;
use AutoBusiness\Astro\Calc\VimshottariDasha;
use AutoBusiness\Astro\Time\JulianDay;

/**
 * संतान-योग (Progeny / child-birth) — computed analysis.
 *
 * Implements the classical Parashari + Jaimini child-birth rules from the
 * owner's Santan-Jyotish reference. Everything is computed from the chart —
 * every verdict carries its reason.
 *
 * MARYADA (the doc's ethical rules, enforced in the output):
 *  • Never says "no child". A weak chart reads विलंब / प्रयास-आवश्यक, never असंभव.
 *  • Boy/girl is a disputed 50-50 शास्त्रीय संकेत, and in India sex-determination
 *    is illegal (PCPNDT Act) — so it is shown only as अनिश्चित study-material with
 *    a prominent legal/ethical caveat, never as a decision.
 *
 * Four pillars (§1.1): 5th house · Panchamesh · Jupiter (Putra-karak) · D7.
 */
final class SantanEngine
{
    private const PLANETS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];

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

    /** नक्षत्र-स्वामी order (Vimshottari). */
    private const NAK_LORD = ['Ketu', 'Venus', 'Sun', 'Moon', 'Mars', 'Rahu', 'Jupiter', 'Saturn', 'Mercury'];

    private const MALE = ['Sun', 'Mars', 'Jupiter'];
    private const FEMALE = ['Moon', 'Venus'];
    /** जल/बहु-प्रसव rashis (Cancer, Scorpio, Pisces) vs अल्प-संतान (Gemini, Leo, Virgo). */
    private const FERTILE = [3, 7, 11];
    private const BARREN = [2, 4, 5];

    /** Traditional santan remedies per afflicting planet. */
    private const REMEDY = [
        'Sun' => 'सूर्य को जल अर्पण व आदित्य-हृदय पाठ; पितृ-तर्पण/श्राद्ध; रविवार गुड़-गेहूँ दान। पिता व पूर्वजों का सम्मान।',
        'Moon' => 'सोमवार शिव-पूजन व चन्द्र-शांति; माता की सेवा; चाँदी/चावल/दूध दान।',
        'Mars' => 'मंगलवार हनुमान-उपासना व मंगल-शांति; भाई-बहन से सौहार्द; मसूर/गुड़ दान।',
        'Mercury' => 'बुधवार गणेश/विष्णु-पूजन; कन्याओं को हरी वस्तुएँ; हरी मूँग दान।',
        'Jupiter' => 'बृहस्पतिवार व्रत व गुरु-उपासना; विष्णु-सहस्रनाम; पीली वस्तु/चने की दाल/हल्दी दान; संतान-गोपाल मंत्र।',
        'Venus' => 'शुक्रवार लक्ष्मी-पूजन व शुक्र-शांति; स्त्री-सम्मान; सफेद वस्त्र/चीनी दान।',
        'Saturn' => 'शनिवार शनि-मंत्र व हनुमान-उपासना; वंचितों की सेवा; सरसों-तेल/काले तिल दान — शनि विलंब देता है, रोकता नहीं; धैर्य रखें।',
        'Rahu' => 'राहु-मंत्र व सरस्वती/नाग-उपासना; नारियल/कंबल दान — राहु उपचार/तकनीक (IVF/IUI) के मार्ग से संतान देता है।',
        'Ketu' => 'गणेश-उपासना व केतु-मंत्र; कुत्ते को भोजन; कंबल दान।',
    ];

    /**
     * @param array<string,mixed> $chart  D1 chart
     * @param array<string,mixed> $vargas divisional charts (D7, D9, D30, D60…)
     * @param string $gender 'male'|'female'|'' (for Beeja/Kshetra & karaka focus)
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
        string $gender = ''
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
        $lordOf = static fn (int $h): string => self::SIGN_LORD[($asc + $h - 1) % 12];

        $ctx = [
            'asc' => $asc, 'pl' => $pl, 'house' => $house, 'occ' => $occ, 'dig' => $dig,
            'lordOf' => $lordOf, 'L1' => $lordOf(1), 'L5' => $lordOf(5), 'L9' => $lordOf(9),
            'sav' => self::savMap($chart),
        ];

        $pillars = self::pillars($ctx, $vargas);
        $signals = [];
        $score = self::yogaSignals($ctx, $vargas, $signals);
        $promise = self::promiseLevel($score);
        $shrap = self::shrapReadings($ctx);
        $obstruction = self::obstruction($ctx, $shrap);
        $karyesh = self::karyeshList($ctx, $vargas);
        $timing = self::timing($ctx, $karyesh, $moonLon, $birthJd, $nowJd, $tz, $engine, $chart, $birth);
        $gender_ = self::genderLeaning($ctx, $vargas);
        $sphuta = self::sphuta($ctx, $gender);
        $vargaNotes = self::vargaNotes($ctx, $vargas);
        $remedies = self::remedies($shrap, $obstruction);
        $conclusion = self::conclusion($promise, $timing, $obstruction, $gender_);

        return [
            'ok' => true,
            'lagna_hi' => self::signHi($asc),
            'pillars' => $pillars,
            'signals' => $signals,
            'promise' => $promise,
            'shrap' => $shrap,
            'obstruction' => $obstruction,
            'karyesh' => $karyesh,
            'timing' => $timing,
            'gender' => $gender_,
            'sphuta' => $sphuta,
            'varga' => $vargaNotes,
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

    /** planet linked to a house (occupies / aspects / tied to its lord). */
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

    private static function isStrong(array $ctx, string $p): bool
    {
        return in_array($ctx['dig'][$p]['dignity']['tier'] ?? '', ['param_uchcha', 'exalt', 'moolatrikona', 'own', 'great_friend', 'friend'], true);
    }

    private static function isAfflicted(array $ctx, string $p): bool
    {
        $tier = $ctx['dig'][$p]['dignity']['tier'] ?? '';
        $combust = ($ctx['dig'][$p]['combust']['pct'] ?? 0) >= 40;
        return in_array($tier, ['debil', 'enemy', 'great_enemy'], true) || $combust
            || in_array($ctx['house'][$p] ?? 0, [6, 8, 12], true);
    }

    private static function isPapa(string $p, array $ctx): bool
    {
        if (in_array($p, ['Saturn', 'Rahu', 'Ketu', 'Mars'], true)) {
            return true;
        }
        if ($p === 'Sun') {
            return true;   // Sun counted as mild malefic here
        }
        return false;
    }

    /** SAV points per sign index (0..11) from the chart. */
    private static function savMap(array $chart): array
    {
        $signs = [];
        foreach (['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'] as $p) {
            if (isset($chart['planets'][$p]['sign_index'])) {
                $signs[$p] = (int) $chart['planets'][$p]['sign_index'];
            }
        }
        $signs['Lagna'] = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $av = Ashtakavarga::compute($signs);
        return $av['sav'] ?? array_fill(0, 12, 0);
    }

    private static function nakLordOf(array $ctx, string $p): string
    {
        $idx = (int) ($ctx['pl'][$p]['nakshatra']['index'] ?? 0);
        return self::NAK_LORD[$idx % 9];
    }

    /** planet's house from a varga chart (D7/D9/…) relative to that varga's lagna. */
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

    // ------------------------------------------------------------- pillars

    private static function pillars(array $ctx, array $vargas): array
    {
        $asc = $ctx['asc'];
        $fifthSign = ($asc + 4) % 12;
        $L5 = $ctx['L5'];
        $sav5 = (int) ($ctx['sav'][$fifthSign] ?? 0);

        // 5th house
        $fertile = in_array($fifthSign, self::FERTILE, true);
        $barren = in_array($fifthSign, self::BARREN, true);
        $occ5 = $ctx['occ'][5] ?? [];
        $aspOn5 = [];
        foreach (self::PLANETS as $p) {
            if (self::aspectsHouse($ctx, $p, 5)) {
                $aspOn5[] = $p;
            }
        }

        // Panchamesh
        $l5h = $ctx['house'][$L5] ?? 0;
        $l5nak = self::nakLordOf($ctx, $L5);
        $l5vargottama = false;
        if (!empty($vargas['D9'])) {
            $l5vargottama = self::vargaSignOf($vargas['D9'], $L5) === (int) ($ctx['pl'][$L5]['sign_index'] ?? -1);
        }

        // Jupiter
        $jh = $ctx['house']['Jupiter'] ?? 0;
        $jAsp5 = self::aspectsHouse($ctx, 'Jupiter', 5);
        $jStrong = self::isStrong($ctx, 'Jupiter');
        $jCombust = ($ctx['dig']['Jupiter']['combust']['pct'] ?? 0) >= 40;
        $jDebil = ($ctx['dig']['Jupiter']['dignity']['tier'] ?? '') === 'debil';

        // D7
        $d7 = $vargas['D7'] ?? null;
        $d7info = null;
        if ($d7 !== null) {
            $d7asc = (int) ($d7['asc_sign'] ?? 0);
            $d7L1 = Charts::signLord($d7asc);
            $d7info = [
                'lagna_hi' => self::signHi($d7asc),
                'lagnesh' => self::HI[$d7L1] ?? $d7L1,
                'lagnesh_house' => self::vargaHouseOf($d7, $d7L1),
                'guru_house' => self::vargaHouseOf($d7, 'Jupiter'),
                'l5_house' => self::vargaHouseOf($d7, $L5),
            ];
        }

        return [
            'fifth' => [
                'sign_hi' => self::signHi($fifthSign),
                'fertile' => $fertile, 'barren' => $barren,
                'occupants' => array_map(fn ($x) => self::HI[$x], $occ5),
                'occ_papa' => array_values(array_filter($occ5, fn ($x) => self::isPapa($x, $ctx))),
                'aspects' => array_map(fn ($x) => self::HI[$x], $aspOn5),
                'guru_aspect' => $jAsp5,
                'sav' => $sav5,
                'sav_band' => $sav5 >= 28 ? 'बलवान' : ($sav5 >= 25 ? 'सामान्य' : 'कमज़ोर'),
            ],
            'panchamesh' => [
                'planet' => $L5, 'hi' => self::HI[$L5], 'house' => $l5h, 'house_ord' => self::ord($l5h),
                'dignity' => $ctx['dig'][$L5]['dignity']['word'] ?? '',
                'strong' => self::isStrong($ctx, $L5), 'afflicted' => self::isAfflicted($ctx, $L5),
                'nak_lord' => self::HI[$l5nak] ?? $l5nak, 'vargottama' => $l5vargottama,
                'lagnesh_link' => self::connect($ctx, $L5, $ctx['L1']),
            ],
            'guru' => [
                'house' => $jh, 'house_ord' => self::ord($jh),
                'dignity' => $ctx['dig']['Jupiter']['dignity']['word'] ?? '',
                'strong' => $jStrong, 'combust' => $jCombust, 'debil' => $jDebil,
                'retro' => !empty($ctx['pl']['Jupiter']['retro']),
                'aspect5' => $jAsp5,
                'good_house' => in_array($jh, [1, 5, 7, 9], true),
            ],
            'd7' => $d7info,
        ];
    }

    // --------------------------------------------------------- yoga signals

    private static function yogaSignals(array $ctx, array $vargas, array &$signals): float
    {
        $add = static function (string $tone, float $w, string $text) use (&$signals): void {
            $signals[] = ['tone' => $tone, 'weight' => $w, 'text' => $text];
        };
        $asc = $ctx['asc'];
        $L5 = $ctx['L5'];
        $l5h = $ctx['house'][$L5] ?? 0;
        $fifthSign = ($asc + 4) % 12;
        $score = 0.0;

        // ---- STRONG santan-yogas (§2.1) ----
        if (self::isStrong($ctx, $L5) && in_array($l5h, [1, 4, 5, 7, 9, 10], true)) {
            $score += 2.0;
            $add('pos', 2.0, 'पंचमेश ' . self::HI[$L5] . ' बली व ' . self::ord($l5h) . ' (केंद्र/त्रिकोण/स्वगृह) में — प्रबल संतान-योग।');
        }
        if (self::connect($ctx, $L5, $ctx['L1'])) {
            $score += 2.0;
            $add('pos', 2.0, 'पंचमेश ' . self::HI[$L5] . ' व लग्नेश ' . self::HI[$ctx['L1']] . ' का संबंध — सर्वश्रेष्ठ संतान-योगों में से एक।');
        }
        if (self::aspectsHouse($ctx, 'Jupiter', 5) && self::isStrong($ctx, 'Jupiter')) {
            $score += 2.0;
            $add('pos', 2.0, 'बली गुरु की 5वें भाव पर दृष्टि — "संतान-रक्षक" योग; 5वें का कोई भी दोष बहुत कम हो जाता है।');
        } elseif (self::aspectsHouse($ctx, 'Jupiter', 5) || self::linked($ctx, 'Jupiter', 5)) {
            $score += 1.2;
            $add('pos', 1.2, 'गुरु का 5वें भाव/पंचमेश से संबंध — पुत्र-कारक का शुभ प्रभाव।');
        }
        if (in_array($ctx['house']['Jupiter'] ?? 0, [1, 5, 7, 9], true)
            && ($ctx['dig']['Jupiter']['combust']['pct'] ?? 0) < 40
            && ($ctx['dig']['Jupiter']['dignity']['tier'] ?? '') !== 'debil') {
            $score += 1.0;
            $add('pos', 1.0, 'गुरु ' . self::ord($ctx['house']['Jupiter']) . ' भाव में (अस्त/नीच नहीं) — संतान-सुख का शुभ आधार।');
        }
        foreach (($ctx['occ'][5] ?? []) as $p) {
            if (in_array($p, ['Jupiter', 'Venus'], true) && self::isStrong($ctx, $p)) {
                $score += 1.2;
                $add('pos', 1.2, 'शुभ ग्रह ' . self::HI[$p] . ' 5वें भाव में बली — संतान-सुख।');
            } elseif ($p === 'Moon' && self::isStrong($ctx, 'Moon')) {
                $score += 0.8;
                $add('pos', 0.8, 'बली चन्द्र 5वें भाव में — संतान हेतु शुभ।');
            }
        }
        if (in_array($fifthSign, self::FERTILE, true)) {
            $score += 1.0;
            $add('pos', 1.0, '5वें भाव की राशि ' . self::signHi($fifthSign) . ' (जल/बहु-प्रसव) — संतान-योग को बल।');
        } elseif (in_array($fifthSign, self::BARREN, true)) {
            $score -= 0.5;
            $add('neg', 0.5, '5वें भाव की राशि ' . self::signHi($fifthSign) . ' (अल्प-संतान राशि) — संतान में सीमा/विलंब का संकेत; पंचमेश व गुरु का बल निर्णायक।');
        }
        if (self::connect($ctx, 'Moon', 'Jupiter') && (self::linked($ctx, 'Jupiter', 5) || self::linked($ctx, 'Moon', 5))) {
            $score += 0.8;
            $add('pos', 0.8, 'चन्द्र-गुरु का संबंध (गजकेसरी-सम) 5वें से जुड़ा — संतान-सुख हेतु शुभ।');
        }
        // Panchamesh vargottama
        if (!empty($vargas['D9']) && self::vargaSignOf($vargas['D9'], $L5) === (int) ($ctx['pl'][$L5]['sign_index'] ?? -1)) {
            $score += 1.0;
            $add('pos', 1.0, 'पंचमेश ' . self::HI[$L5] . ' वर्गोत्तम (D1=D9 राशि) — बहुत शुभ संकेत।');
        }
        // 5th SAV
        $sav5 = (int) ($ctx['sav'][$fifthSign] ?? 0);
        if ($sav5 >= 28) {
            $score += 1.0;
            $add('pos', 1.0, '5वें भाव के अष्टकवर्ग बिंदु ' . $sav5 . ' (≥28) — संतान-योग सक्षम।');
        } elseif ($sav5 > 0 && $sav5 < 25) {
            $score -= 0.5;
            $add('neg', 0.5, '5वें भाव के अष्टकवर्ग बिंदु ' . $sav5 . ' (<25) — भाव कमज़ोर; अन्य बल आवश्यक।');
        }
        // D7 lagna strong
        if (!empty($vargas['D7'])) {
            $d7 = $vargas['D7'];
            $d7L1 = Charts::signLord((int) ($d7['asc_sign'] ?? 0));
            $d7L1h = self::vargaHouseOf($d7, $d7L1);
            if ($d7L1h !== null && in_array($d7L1h, [1, 4, 5, 7, 9, 10], true)) {
                $score += 1.0;
                $add('pos', 1.0, 'D7 (सप्तांश) लग्नेश ' . (self::HI[$d7L1] ?? $d7L1) . ' केंद्र/त्रिकोण में — संतान का मुख्य चार्ट अनुकूल।');
            }
            $d7guruH = self::vargaHouseOf($d7, 'Jupiter');
            if ($d7guruH !== null && in_array($d7guruH, [1, 5, 7, 9], true)) {
                $score += 0.6;
                $add('pos', 0.6, 'D7 में गुरु ' . self::ord($d7guruH) . ' भाव में — संतान की संख्या व सुख को बल।');
            }
        }

        // ---- BAADHAK yogas (§3.1) ----
        if (in_array($l5h, [6, 8, 12], true) && self::isAfflicted($ctx, $L5)) {
            $score -= 1.5;
            $add('neg', 1.5, 'पंचमेश ' . self::HI[$L5] . ' ' . self::ord($l5h) . ' (त्रिक) भाव में व पीड़ित — संतान में बाधा/विलंब।');
        }
        $papa5 = array_values(array_filter($ctx['occ'][5] ?? [], fn ($x) => self::isPapa($x, $ctx)));
        $benAsp5 = false;
        foreach (['Jupiter', 'Venus', 'Mercury'] as $b) {
            if (self::aspectsHouse($ctx, $b, 5) && !self::isAfflicted($ctx, $b)) {
                $benAsp5 = true;
            }
        }
        if ($papa5 !== [] && !$benAsp5) {
            $score -= 1.2;
            $add('neg', 1.2, '5वें भाव में पाप ग्रह (' . implode(', ', array_map(fn ($x) => self::HI[$x], $papa5)) . ') व कोई शुभ दृष्टि नहीं — बाधक।');
        }
        if ($jDebil = ($ctx['dig']['Jupiter']['dignity']['tier'] ?? '') === 'debil') {
            $score -= 1.0;
            $add('neg', 1.0, 'गुरु (पुत्र-कारक) नीच राशि में — कारक-भंग; संतान-योग को दुर्बल करता है (पर निषेध नहीं)।');
        }
        if (($ctx['dig']['Jupiter']['combust']['pct'] ?? 0) >= 40) {
            $score -= 0.8;
            $add('neg', 0.8, 'गुरु अस्त (सूर्य के अति निकट) — पुत्र-कारक का बल क्षीण।');
        }
        // Saturn on 5th/Panchamesh → delay
        if (self::linked($ctx, 'Saturn', 5) || self::connect($ctx, 'Saturn', $L5)) {
            $score -= 0.6;
            $add('neg', 0.6, 'शनि का 5वें भाव/पंचमेश पर प्रभाव — विलंब का सबसे सामान्य कारण (प्रायः 30+ वर्ष); शनि रोकता नहीं, समय बढ़ाता है।');
        }
        // Rahu/Ketu with Jupiter (Guru-Chandal) tied to 5th
        foreach (['Rahu', 'Ketu'] as $node) {
            if (($ctx['house'][$node] ?? 0) === 5 && ($ctx['house']['Jupiter'] ?? -1) === 5) {
                $score -= 0.8;
                $add('neg', 0.8, 'गुरु + ' . self::HI[$node] . ' 5वें भाव में (गुरु-चांडाल-सम) — गर्भ-स्थिरता में समस्या; उपाय आवश्यक।');
            }
        }
        // Mars+Saturn on 5th/8th
        if ((self::linked($ctx, 'Mars', 5) && self::linked($ctx, 'Saturn', 5))
            || (self::linked($ctx, 'Mars', 8) && self::linked($ctx, 'Saturn', 8))) {
            $score -= 0.6;
            $add('neg', 0.6, 'मंगल-शनि का 5वें/8वें पर संयुक्त प्रभाव — शल्य/जटिलता का पारंपरिक संकेत (C-section आदि)।');
        }

        return $score;
    }

    private static function promiseLevel(float $score): array
    {
        if ($score >= 5.0) {
            return ['level' => 'प्रबल', 'tone' => 'pos', 'score' => round($score, 1),
                'text' => 'संतान-योग प्रबल है — चारों स्तंभ (5वाँ भाव, पंचमेश, गुरु, D7) अनुकूल। शुभ दशा-गोचर में योग सहज फलित होगा।'];
        }
        if ($score >= 2.5) {
            return ['level' => 'मध्यम', 'tone' => 'info', 'score' => round($score, 1),
                'text' => 'संतान-योग मध्यम है — योग उपस्थित है, पर कुछ बिंदुओं पर बल कम। अनुकूल दशा-गोचर व उपायों से फल आएगा।'];
        }
        if ($score >= 0.5) {
            return ['level' => 'विलंबित / प्रयास-आवश्यक', 'tone' => 'info', 'score' => round($score, 1),
                'text' => 'संकेत मिश्रित हैं — संतान-योग है पर विलंब/प्रयास का। शास्त्र का "दोष" प्रायः "विलंब" या "उपचार की आवश्यकता" होता है, "असंभव" नहीं। उपाय व चिकित्सा दोनों सहायक।'];
        }
        return ['level' => 'दुर्बल — उपाय व चिकित्सा आवश्यक', 'tone' => 'neg', 'score' => round($score, 1),
            'text' => 'इस चार्ट में संतान-योग दुर्बल दिखता है — पर शास्त्र कभी "संतान नहीं होगी" नहीं कहता। यह प्रयास, उपाय एवं चिकित्सकीय सहायता की ओर संकेत है। दोनों जीवनसाथियों की कुंडली मिलाकर ही अंतिम निर्णय लें।'];
    }

    // ---------------------------------------------------------------- shrap

    private static function shrapReadings(array $ctx): array
    {
        $L5 = $ctx['L5'];
        $out = [];
        $near5 = static function (string $p) use ($ctx, $L5): bool {
            return ($ctx['house'][$p] ?? 0) === 5 || self::aspectsHouse($ctx, $p, 5) || self::connect($ctx, $p, $L5);
        };
        // Pitru Shrap: Sun + Rahu/Saturn tied to 5th/Panchamesh; 9th afflicted
        if ($near5('Sun') && ($near5('Rahu') || $near5('Saturn'))) {
            $out[] = ['name' => 'पितृ-शाप', 'sanket' => 'सूर्य + राहु/शनि का 5वें/पंचमेश से संबंध', 'parihar' => 'पितृ-तर्पण, श्राद्ध, गया/त्र्यंबकेश्वर श्राद्ध, नारायण-बलि।'];
        }
        // Matru Shrap
        if ($near5('Moon') && ($near5('Rahu') || $near5('Ketu'))) {
            $out[] = ['name' => 'मातृ-शाप', 'sanket' => 'चन्द्र + राहु/केतु का 5वें से संबंध', 'parihar' => 'माता की सेवा, दुर्गा-उपासना, चन्द्र-शांति।'];
        }
        // Sarpa/Naga
        if (($ctx['house']['Rahu'] ?? 0) === 5 || ($ctx['house']['Ketu'] ?? 0) === 5
            || self::connect($ctx, 'Rahu', $L5) || self::connect($ctx, 'Ketu', $L5)) {
            $out[] = ['name' => 'सर्प-शाप / नाग-दोष', 'sanket' => 'राहु/केतु 5वें में या पंचमेश से युत/दृष्ट', 'parihar' => 'नाग-प्रतिष्ठा, सर्प-संस्कार (कुक्के सुब्रह्मण्य), नाग-पंचमी व्रत।'];
        }
        // Preta Shrap
        if (self::linked($ctx, $L5, 8) || self::linked($ctx, $L5, 12)) {
            foreach (['Saturn', 'Rahu', 'Ketu', 'Mars'] as $m) {
                if (($ctx['house'][$m] ?? 0) === 8 || ($ctx['house'][$m] ?? 0) === 12) {
                    $out[] = ['name' => 'प्रेत-शाप', 'sanket' => '5वें/पंचमेश का 8वें/12वें के पाप-ग्रहों से संबंध', 'parihar' => 'प्रेत-कर्म, नारायण-बलि, दान।'];
                    break;
                }
            }
        }
        // Patni/Stri Shrap
        if (self::isAfflicted($ctx, 'Venus') && (self::linked($ctx, 'Venus', 5) || self::linked($ctx, 'Venus', 7))) {
            $out[] = ['name' => 'पत्नी/स्त्री-शाप', 'sanket' => 'शुक्र पीड़ित + 7वें-5वें का दूषित संबंध', 'parihar' => 'स्त्री-सम्मान, शुक्र-शांति, लक्ष्मी-उपासना।'];
        }
        // Brahma/Rishi Shrap
        if (self::isAfflicted($ctx, 'Jupiter') && ($near5('Ketu'))) {
            $out[] = ['name' => 'ब्रह्म/ऋषि-शाप', 'sanket' => 'गुरु पीड़ित + केतु का 5वें पर प्रभाव', 'parihar' => 'गुरु-सेवा, विष्णु-सहस्रनाम, गुरु-शांति।'];
        }
        // Balahatya / Shishu Dosh
        if (($ctx['house']['Mars'] ?? 0) === 5 && ($ctx['house']['Rahu'] ?? 0) === 5) {
            $out[] = ['name' => 'बालहत्या / शिशु-दोष', 'sanket' => '5वें भाव में मंगल-राहु का योग', 'parihar' => 'प्रायश्चित-कर्म, कन्या-दान/बाल-सेवा, संतान-गोपाल मंत्र।'];
        }
        return $out;
    }

    // ------------------------------------------------------------- obstruction

    private static function obstruction(array $ctx, array $shrap): array
    {
        $L5 = $ctx['L5'];
        $on5 = [];   // papa graha that directly afflicts the 5th house or Panchamesh
        foreach (['Saturn', 'Rahu', 'Ketu', 'Mars', 'Sun'] as $p) {
            // occupies the 5th, aspects the 5th, or sits with / aspects the Panchamesh
            $touches5 = ($ctx['house'][$p] ?? 0) === 5 || self::aspectsHouse($ctx, $p, 5);
            $touchesL5 = ($ctx['house'][$p] ?? 0) === ($ctx['house'][$L5] ?? -1)
                || self::aspectsHouse($ctx, $p, $ctx['house'][$L5] ?? 0);
            if ($touches5 || $touchesL5) {
                $on5[$p] = true;
            }
        }
        // Guru-Chandal (Rahu/Ketu with Jupiter) specifically hurts the karaka
        foreach (['Rahu', 'Ketu'] as $node) {
            if (($ctx['house'][$node] ?? 0) === ($ctx['house']['Jupiter'] ?? -2)) {
                $on5[$node] = true;
            }
        }
        static $type = [
            'Saturn' => ['विलंब (Delay)', 'शनि-प्रधान पीड़ा = विलंब। संतान होगी, पर देर से (प्रायः 30–36 वर्ष के बाद)। धैर्य व निरंतर प्रयास।'],
            'Rahu' => ['अनिश्चितता / उपचार', 'राहु-प्रधान पीड़ा = अनिश्चितता — उपचार, IVF/IUI या असामान्य परिस्थिति के माध्यम से संतान।'],
            'Ketu' => ['वैराग्य / दूरी', 'केतु-प्रधान पीड़ा = वैराग्य/detachment — संतान होकर भी दूरी, या रुचि का अभाव।'],
            'Mars' => ['शल्य (Surgery)', 'मंगल-प्रधान पीड़ा = शल्य/सर्जरी, गर्भपात का जोखिम या C-section का पारंपरिक संकेत — सावधानी व चिकित्सकीय निगरानी।'],
            'Sun' => ['अहंकार / पितृ-पक्ष', 'सूर्य-प्रधान पीड़ा = अहंकार/पितृ-पक्ष का कर्म; पुत्र-संतान में विशेष बाधा — पितृ-सेवा।'],
        ];
        $problems = [];
        $remedies = [];
        $seen = [];
        foreach (array_keys($on5) as $p) {
            $problems[] = ['hi' => self::HI[$p], 'kind' => $type[$p][0], 'text' => $type[$p][1]];
            if (!isset($seen[$p])) {
                $remedies[] = ['hi' => self::HI[$p], 'text' => self::REMEDY[$p]];
                $seen[$p] = true;
            }
        }
        $present = $problems !== [] || $shrap !== [];
        $summary = $present
            ? 'संतान-योग में नीचे दी बाधाएँ दिख रही हैं — शास्त्र में हर दोष का उपाय भी दिया गया है (नीचे)। दोष का अर्थ प्रायः "विलंब/प्रयास" है, "असंभव" नहीं।'
            : 'किसी प्रमुख पाप-ग्रह की 5वें भाव/पंचमेश/गुरु पर बड़ी पीड़ा नहीं — संतान-मार्ग में ग्रह-जन्य कोई बड़ी बाधा नहीं दिखती।';
        return ['present' => $present, 'summary' => $summary, 'problems' => $problems, 'remedies' => $remedies];
    }

    // ---------------------------------------------------------------- karyesh

    private static function karyeshList(array $ctx, array $vargas): array
    {
        $roles = [];
        $push = static function (string $p, string $r) use (&$roles): void {
            if ($p !== '') {
                $roles[$p][] = $r;
            }
        };
        $L5 = $ctx['L5'];
        $push($L5, 'पंचमेश (संतान-स्वामी)');
        foreach (($ctx['occ'][5] ?? []) as $p) {
            $push($p, '5वें भाव में स्थित');
        }
        $push('Jupiter', 'पुत्र-कारक (गुरु)');
        $push($ctx['L9'], 'नवमेश (द्वितीय संतान)');
        $push(self::nakLordOf($ctx, $L5), 'पंचमेश-नक्षत्र स्वामी');
        // D7 lagnesh
        if (!empty($vargas['D7'])) {
            $push(Charts::signLord((int) ($vargas['D7']['asc_sign'] ?? 0)), 'D7 लग्नेश');
        }
        $out = [];
        foreach ($roles as $p => $rl) {
            $h = $ctx['house'][$p] ?? 0;
            $out[] = [
                'planet' => $p, 'hi' => self::HI[$p], 'roles' => array_values(array_unique($rl)),
                'house_ord' => self::ord($h), 'dignity' => $ctx['dig'][$p]['dignity']['word'] ?? '',
                'weak' => self::isAfflicted($ctx, $p),
            ];
        }
        return $out;
    }

    // ---------------------------------------------------------------- timing

    private static function timing(array $ctx, array $karyesh, ?float $moonLon, ?float $birthJd, ?float $nowJd, float $tz,
        ?\AutoBusiness\Astro\Calc\CalculationEngine $engine, array $chart, ?array $birth): array
    {
        $karSet = [];
        foreach ($karyesh as $k) {
            $karSet[$k['planet']] = true;
        }
        $windows = [];
        if ($moonLon !== null && $birthJd !== null && $nowJd !== null) {
            $seq = VimshottariDasha::sequence($moonLon, $birthJd);
            $horizon = $nowJd + 12.0 * 365.2564;
            foreach ($seq['mahadashas'] as $md) {
                if ($md['end_jd'] < $nowJd || $md['start_jd'] > $horizon) {
                    continue;
                }
                $mdKar = isset($karSet[$md['lord']]);
                $mdBad = in_array(($ctx['house'][$md['lord']] ?? 0), [6, 8, 12], true);
                foreach (VimshottariDasha::antardashas($md) as $ad) {
                    if ($ad['end_jd'] < $nowJd || $ad['start_jd'] > $horizon) {
                        continue;
                    }
                    $adKar = isset($karSet[$ad['lord']]);
                    if (!$mdKar && !$adKar) {
                        continue;
                    }
                    $why = [];
                    if ($mdKar) {
                        $why[] = 'महादशा ' . self::HI[$md['lord']] . ' संतान-कारक';
                    }
                    if ($adKar) {
                        $why[] = 'अंतर्दशा ' . self::HI[$ad['lord']] . ' संतान-कारक';
                    }
                    $windows[] = [
                        'label' => self::HI[$md['lord']] . ' – ' . self::HI[$ad['lord']],
                        'from' => JulianDay::toDmy(max($ad['start_jd'], $nowJd), $tz),
                        'to' => JulianDay::toDmy($ad['end_jd'], $tz),
                        'from_jd' => $ad['start_jd'], 'end_jd' => $ad['end_jd'],
                        'why' => implode(', ', $why) . '।',
                        'both' => $mdKar && $adKar,
                        'md_bad' => $mdBad,
                    ];
                }
            }
            usort($windows, static function ($a, $b) {
                if ($a['both'] !== $b['both']) {
                    return $a['both'] ? -1 : 1;
                }
                return $a['from_jd'] <=> $b['from_jd'];
            });
            $windows = array_slice($windows, 0, 3);
        }

        $auto = $engine !== null && $birth !== null && !empty($chart['planets']);
        foreach ($windows as &$w) {
            if ($auto) {
                $midJd = ($w['from_jd'] < $nowJd ? $nowJd : $w['from_jd']);
                $midJd = ($midJd + $w['end_jd']) / 2.0;
                try {
                    $w['gochar'] = self::gocharDoubleTransit($engine, $ctx, $midJd);
                } catch (\Throwable $e) {
                    $w['gochar'] = null;
                }
            }
            $w['rule'] = self::windowVerdict($w);
        }
        unset($w);

        $note = 'नियम: दशा "संभावना" देती है, गोचर उसे "trigger" करता है। सबसे प्रबल संकेत — पंचमेश/गुरु/D7-लग्नेश की दशा + गुरु का 5वें पर गोचर + शनि का साथ (द्विग्रह) + अष्टकवर्ग बिंदु अच्छे। '
            . 'गर्भ-धारण (conception) का समय जन्म से ~9–10 माह पूर्व होता है — यह अंतर ध्यान रखें। '
            . 'माह की सटीकता प्रत्यंतर्दशा + चन्द्र/सूर्य के गोचर से निकालें।';

        return ['windows' => $windows, 'note' => $note, 'has_dasha' => $moonLon !== null, 'auto' => $auto];
    }

    /** गुरु-शनि द्विग्रह गोचर on the natal 5th house / Panchamesh. */
    private static function gocharDoubleTransit(\AutoBusiness\Astro\Calc\CalculationEngine $engine, array $ctx, float $jd): array
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
        $l5h = (int) ($ctx['house'][$ctx['L5']] ?? 0);
        $jup5 = $touch($jH, [5, 7, 9], 5) || ($l5h && $touch($jH, [5, 7, 9], $l5h));
        $sat5 = $touch($sH, [3, 7, 10], 5) || ($l5h && $touch($sH, [3, 7, 10], $l5h));
        $double = $jup5 && $sat5;
        if ($double) {
            $text = 'गुरु-शनि दोनों का 5वें भाव/पंचमेश पर गोचर (द्विग्रह) — संतान-योग के सक्रिय होने की सर्वाधिक संभावना।';
            $tone = 'pos';
        } elseif ($jup5) {
            $text = 'गुरु का 5वें भाव/पंचमेश पर गोचर — संतान-योग सक्रिय; शनि का साथ मिलने पर द्विग्रह पूर्ण।';
            $tone = 'pos';
        } elseif ($sat5) {
            $text = 'शनि का 5वें भाव/पंचमेश पर गोचर — अकेले विलंब; गुरु के साथ आने पर स्थिरता के साथ फल।';
            $tone = 'info';
        } else {
            $text = 'इस अवधि में गुरु-शनि का 5वें/पंचमेश पर द्विग्रह गोचर नहीं — गोचर-समर्थन दुर्बल।';
            $tone = 'info';
        }
        return ['jup_house' => $jH, 'sat_house' => $sH, 'double' => $double, 'any' => $jup5 || $sat5, 'text' => $text, 'tone' => $tone];
    }

    private static function windowVerdict(array $w): array
    {
        $g = !empty($w['gochar']['any']);
        $dbl = !empty($w['gochar']['double']);
        if (!empty($w['md_bad'])) {
            return ['label' => 'सावधानी — दशेश त्रिक में', 'tone' => 'info',
                'text' => 'इस दशा का स्वामी 6/8/12 से जुड़ा है — पूरी महादशा में संतान-योग कठिन रह सकता है, पर शुभ अंतर्दशा+गोचर फल दे सकते हैं।'];
        }
        if ($dbl) {
            return ['label' => '★ सर्वोत्तम — दशा + द्विग्रह गोचर', 'tone' => 'pos',
                'text' => 'दशा-खिड़की पर गुरु-शनि का द्विग्रह गोचर बैठ रहा — संतान (गर्भ-धारण) की सर्वाधिक प्रबल अवधि। माह प्रत्यंतर्दशा + चन्द्र/सूर्य गोचर से।'];
        }
        if ($g) {
            return ['label' => 'प्रबल — दशा + गोचर', 'tone' => 'pos',
                'text' => 'दशा-खिड़की पर गुरु/शनि में से एक का 5वें पर गोचर — प्रबल; पूर्ण द्विग्रह पर निश्चितता बढ़ेगी।'];
        }
        return ['label' => 'संभावना — दशा-खिड़की', 'tone' => 'info',
            'text' => 'संतान-कारक दशा-खिड़की खुली है; गुरु-शनि गोचर का समर्थन आने पर फल प्रबल होगा।'];
    }

    // ------------------------------------------------------------- boy/girl

    private static function genderLeaning(array $ctx, array $vargas): array
    {
        $asc = $ctx['asc'];
        $L5 = $ctx['L5'];
        $votes = ['putra' => 0, 'putri' => 0];
        $bits = [];
        $isOdd = static fn (int $s): bool => $s % 2 === 0; // sign index 0=Aries(odd/vishma)
        $vote = static function (string $side, string $why) use (&$votes, &$bits): void {
            $votes[$side === 'putra' ? 'putra' : 'putri']++;
            $bits[] = ['side' => $side, 'why' => $why];
        };

        // 1) 5th house sign odd/even
        $fifthSign = ($asc + 4) % 12;
        $vote($isOdd($fifthSign) ? 'putra' : 'putri', '5वें भाव की राशि ' . self::signHi($fifthSign) . ' — ' . ($isOdd($fifthSign) ? 'विषम (पुत्र)' : 'सम (पुत्री)'));
        // 2) Panchamesh sign
        $l5s = (int) ($ctx['pl'][$L5]['sign_index'] ?? 0);
        $vote($isOdd($l5s) ? 'putra' : 'putri', 'पंचमेश ' . self::HI[$L5] . ' की राशि — ' . ($isOdd($l5s) ? 'विषम (पुत्र)' : 'सम (पुत्री)'));
        // 3) Panchamesh planet gender
        if (in_array($L5, self::MALE, true)) {
            $vote('putra', 'पंचमेश ' . self::HI[$L5] . ' पुरुष-ग्रह (पुत्र)');
        } elseif (in_array($L5, self::FEMALE, true)) {
            $vote('putri', 'पंचमेश ' . self::HI[$L5] . ' स्त्री-ग्रह (पुत्री)');
        }
        // 4) strongest occupant in 5th
        $occ5 = $ctx['occ'][5] ?? [];
        if ($occ5 !== []) {
            $strongest = $occ5[0];
            foreach ($occ5 as $p) {
                if (($ctx['dig'][$p]['dignity']['score'] ?? 0) > ($ctx['dig'][$strongest]['dignity']['score'] ?? 0)) {
                    $strongest = $p;
                }
            }
            if (in_array($strongest, self::MALE, true)) {
                $vote('putra', '5वें में बली ग्रह ' . self::HI[$strongest] . ' पुरुष (पुत्र)');
            } elseif (in_array($strongest, self::FEMALE, true)) {
                $vote('putri', '5वें में बली ग्रह ' . self::HI[$strongest] . ' स्त्री (पुत्री)');
            }
        }
        // 5) Jupiter vs Venus/Moon dominance
        $guruStrong = self::isStrong($ctx, 'Jupiter') && self::linked($ctx, 'Jupiter', 5);
        $venMoon = (self::isStrong($ctx, 'Venus') && self::linked($ctx, 'Venus', 5))
            || (self::isStrong($ctx, 'Moon') && self::linked($ctx, 'Moon', 5));
        if ($guruStrong && !$venMoon) {
            $vote('putra', 'गुरु (पुत्र-कारक) 5वें से प्रबल संबंध (पुत्र)');
        } elseif ($venMoon && !$guruStrong) {
            $vote('putri', 'शुक्र/चन्द्र का 5वें से प्रबल संबंध (पुत्री)');
        }
        // 6) D7 lagna odd/even
        if (!empty($vargas['D7'])) {
            $d7asc = (int) ($vargas['D7']['asc_sign'] ?? 0);
            $vote($isOdd($d7asc) ? 'putra' : 'putri', 'D7 लग्न ' . self::signHi($d7asc) . ' — ' . ($isOdd($d7asc) ? 'विषम (पुत्र)' : 'सम (पुत्री)'));
            $d7guruSign = self::vargaSignOf($vargas['D7'], 'Jupiter');
            if ($d7guruSign !== null) {
                $vote($isOdd($d7guruSign) ? 'putra' : 'putri', 'D7 में गुरु ' . ($isOdd($d7guruSign) ? 'विषम राशि (पुत्र)' : 'सम राशि (पुत्री)'));
            }
        }
        // 7) Panchamesh nakshatra gender (odd nak index → treat as per common tables)
        // (kept out of the tally to avoid a disputed rule dominating; shown as note only)

        $total = $votes['putra'] + $votes['putri'];
        $diff = abs($votes['putra'] - $votes['putri']);
        if ($total === 0) {
            $lean = 'अनिश्चित';
        } elseif ($diff <= 1) {
            $lean = 'अनिश्चित (लगभग बराबर)';
        } else {
            $lean = $votes['putra'] > $votes['putri'] ? 'पुत्र की ओर झुकाव' : 'पुत्री की ओर झुकाव';
        }
        return [
            'putra' => $votes['putra'], 'putri' => $votes['putri'], 'lean' => $lean, 'bits' => $bits,
            'boy_note' => 'पुत्र-संतान की "अगली अवधि" को अलग से निश्चित नहीं किया जा सकता — ऊपर "अगली संतान-संभावना" में दी खिड़कियाँ ही गर्भ-धारण की संभावित अवधियाँ हैं। पारंपरिक मुहूर्त-मत में गर्भ-धारण के समय चन्द्र/लग्न विषम (odd) राशि में तथा सूर्य-गुरु का बल पुत्र का संकेत माना जाता है — पर यह मुहूर्त (चयन) का विषय है, भविष्यवाणी का नहीं; शास्त्र इसे 50-50 व अनिश्चित कहता है।',
            'caveat' => 'सावधानी: लिंग-निर्धारण के ये पारंपरिक सूत्र शास्त्र में भी विवादित हैं और व्यवहार में लगभग 50-50 (अनिश्चित) पाए जाते हैं। भारत में गर्भ का लिंग जानना/बताना PCPNDT Act के अंतर्गत क़ानूनन अपराध है — अतः यह केवल शास्त्र-अध्ययन हेतु है, निर्णय हेतु कदापि नहीं।',
        ];
    }

    // ---------------------------------------------------------------- sphuta

    private static function sphuta(array $ctx, string $gender): array
    {
        $lon = static fn (string $p): float => (float) ($ctx['pl'][$p]['sidereal_lon'] ?? 0.0);
        $norm = static fn (float $d): float => fmod(fmod($d, 360.0) + 360.0, 360.0);
        $beeja = $norm($lon('Sun') + $lon('Venus') + $lon('Jupiter'));   // purush
        $kshetra = $norm($lon('Moon') + $lon('Mars') + $lon('Jupiter')); // stri
        $beejaSign = (int) floor($beeja / 30.0);
        $kshetraSign = (int) floor($kshetra / 30.0);
        $beejaOdd = $beejaSign % 2 === 0;   // odd sign index = vishma
        $kshetraEven = $kshetraSign % 2 === 1;
        return [
            'beeja_sign' => self::signHi($beejaSign), 'beeja_ok' => $beejaOdd,
            'kshetra_sign' => self::signHi($kshetraSign), 'kshetra_ok' => $kshetraEven,
            'gender' => $gender,
            'note' => 'बीज-स्फुट (पुरुष: सूर्य+शुक्र+गुरु) विषम राशि में शुभ; क्षेत्र-स्फुट (स्त्री: चन्द्र+मंगल+गुरु) सम राशि में शुभ। '
                . ($gender === 'male' ? 'पुरुष जातक हेतु बीज-स्फुट प्रासंगिक।' : ($gender === 'female' ? 'स्त्री जातिका हेतु क्षेत्र-स्फुट प्रासंगिक।' : 'लिंग-अनुसार सम्बंधित स्फुट देखें।'))
                . ' यह सूक्ष्म संकेत है — अकेले निर्णायक नहीं; जीवनसाथी की कुंडली से मिलाएँ।',
        ];
    }

    // ---------------------------------------------------------------- vargas

    private static function vargaNotes(array $ctx, array $vargas): array
    {
        $out = [];
        $L5 = $ctx['L5'];
        // D7 — mukhya
        if (!empty($vargas['D7'])) {
            $d7 = $vargas['D7'];
            $d7L1 = Charts::signLord((int) ($d7['asc_sign'] ?? 0));
            $d7L1h = self::vargaHouseOf($d7, $d7L1);
            $ok = $d7L1h !== null && in_array($d7L1h, [1, 4, 5, 7, 9, 10], true);
            $out[] = ['chart' => 'D-7 सप्तांश (मुख्य)', 'tone' => $ok ? 'pos' : 'info',
                'text' => 'लग्नेश ' . (self::HI[$d7L1] ?? $d7L1) . ' ' . ($d7L1h ? self::ord($d7L1h) . ' भाव में' : '') . ($ok ? ' (केंद्र/त्रिकोण — संतान-सुख शुभ)।' : ' — संतान का विस्तार सामान्य/परीक्षणीय।')];
        }
        // D9 — bala of Panchamesh & Guru
        if (!empty($vargas['D9'])) {
            $strong = [];
            foreach ([$L5, 'Jupiter'] as $p) {
                $s = self::vargaSignOf($vargas['D9'], $p);
                if ($s !== null) {
                    $tier = PlanetCondition::dignity($p, $s, 0.0, [], (int) ($vargas['D9']['asc_sign'] ?? 0))['tier'] ?? '';
                    if (in_array($tier, ['param_uchcha', 'exalt', 'moolatrikona', 'own', 'great_friend', 'friend'], true)) {
                        $strong[] = self::HI[$p];
                    }
                }
            }
            $out[] = $strong !== []
                ? ['chart' => 'D-9 नवांश', 'tone' => 'pos', 'text' => 'नवांश में ' . implode(', ', $strong) . ' बली — संतान-योग टिकाऊ।']
                : ['chart' => 'D-9 नवांश', 'tone' => 'info', 'text' => 'नवांश में पंचमेश/गुरु विशेष बली नहीं — D1 की दशा-गोचर अधिक निर्णायक।'];
        }
        // D30 — arishta
        if (!empty($vargas['D30'])) {
            $out[] = ['chart' => 'D-30 त्रिंशांश', 'tone' => 'info', 'text' => 'अरिष्ट/गर्भ-रक्षा (विशेषकर स्त्री-चार्ट) हेतु गहन जाँच का वर्ग — D30 में 5वें/पंचमेश की पीड़ा गर्भ-सावधानी का संकेत।'];
        }
        // D60 — shrap pushti
        if (!empty($vargas['D60'])) {
            $out[] = ['chart' => 'D-60 षष्ट्यंश', 'tone' => 'info', 'text' => 'पूर्व-जन्म कर्म व शाप-योग की पुष्टि का सूक्ष्मतम वर्ग — शाप-योग यहाँ भी दोहराए तो उपाय अधिक आवश्यक।'];
        }
        return $out;
    }

    // --------------------------------------------------------------- remedies

    private static function remedies(array $shrap, array $obstruction): array
    {
        $general = [
            'संतान-गोपाल मंत्र का जाप — संतान-प्राप्ति हेतु सर्वप्रमुख पारंपरिक उपाय।',
            'गुरु-उपासना — बृहस्पतिवार व्रत, पीला वस्त्र/चने की दाल का दान, विष्णु-उपासना।',
            'हरिवंश पुराण का पाठ; पुत्रदा एकादशी व संतान-सप्तमी व्रत।',
            'गौ-सेवा, कन्या-भोजन, बाल-सेवा एवं अन्न-दान।',
        ];
        $shrapRem = [];
        foreach ($shrap as $s) {
            $shrapRem[] = ['name' => $s['name'], 'text' => $s['parihar']];
        }
        return [
            'general' => $general,
            'shrap' => $shrapRem,
            'planet' => $obstruction['remedies'] ?? [],
            'note' => 'ये श्रद्धा व सहायक उपाय हैं — चिकित्सा का विकल्प नहीं। शास्त्र का "विलंब" प्रायः चिकित्सकीय सहायता के रूप में ही फलित होता है; ज्योतिष व fertility-विशेषज्ञ दोनों का साथ लें।',
        ];
    }

    // -------------------------------------------------------------- conclusion

    private static function conclusion(array $promise, array $timing, array $obstruction, array $gender): string
    {
        $parts = [];
        $parts[] = 'संतान-योग ' . $promise['level'] . '। ' . $promise['text'];
        if (!empty($timing['windows'])) {
            $w = $timing['windows'][0];
            $parts[] = 'दशा-अनुसार निकटतम संभावित अवधि: ' . $w['label'] . ' (' . $w['from'] . ' – ' . $w['to'] . ')। गर्भ-धारण इससे ~9–10 माह पूर्व की अवधि में देखें।';
        }
        $parts[] = $obstruction['present'] ? 'बाधा हेतु ऊपर दिए उपाय करें।' : 'कोई बड़ी ग्रह-बाधा नहीं।';
        $parts[] = 'पुत्र/पुत्री: ' . $gender['lean'] . ' — पर यह अनिश्चित शास्त्रीय संकेत मात्र है (क़ानूनन लिंग-निर्धारण वर्जित)।';
        $parts[] = 'महत्वपूर्ण: शास्त्र कभी "संतान नहीं होगी" नहीं कहता; दोष प्रायः "विलंब/प्रयास" का संकेत है। अंतिम निर्णय दोनों जीवनसाथियों की कुंडली मिलाकर, तथा आवश्यकता होने पर चिकित्सक से परामर्श करके लें।';
        return implode(' ', $parts);
    }
}
