<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;
use AutoBusiness\Astro\Calc\VimshottariDasha;
use AutoBusiness\Astro\Time\JulianDay;

/**
 * विदेश यात्रा एवं स्थायी निवास (Foreign travel & settlement) — computed analysis.
 *
 * Implements the classical Vedic rules for going abroad and settling abroad,
 * exactly as laid out in the owner's two reference documents
 * ("विदेश यात्रा एवं विदेश में स्थायी निवास" and "Green Card / PR कब मिलेगा?"):
 *
 *   • वादा (promise)   — 12th (videsh), 9th (bhagya/long stay), 4th (weak = settle
 *                        abroad), 7th, 10th; karakas Rahu / Moon / Saturn / Venus;
 *                        lagnesh in 12th; 9L–12L link; movable-sign predominance.
 *   • कारण (reason)    — career (10th/Saturn), study (9th+Jupiter/Mercury),
 *                        marriage (7L→9/12/Venus), business (7th), fortune (Rahu/Moon).
 *   • settle vs return — the 4th lord's strength decides (strong = घर वापसी;
 *                        weak/afflicted/6-8-12 or Sat-Rahu-Ketu on 4th = स्थायी प्रवास).
 *   • PR timing        — Vimshottari MD/AD windows whose lord is a PR कार्येश
 *                        (12L, 4L, 9L, Sun/10L, Rahu, Mercury) linked to 12/4/Sun.
 *   • obstruction      — afflicted कार्येश (अस्त/वक्री/नीच/6-8-12/शत्रु) + remedy.
 *
 * Everything is COMPUTED from the chart — nothing is invented. Every verdict
 * carries its reason so it can be audited against the rulebook.
 */
final class VideshEngine
{
    private const PLANETS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];

    /** sign index (0=Aries..11=Pisces) => ruling planet. */
    private const SIGN_LORD = [
        0 => 'Mars', 1 => 'Venus', 2 => 'Mercury', 3 => 'Moon', 4 => 'Sun', 5 => 'Mercury',
        6 => 'Venus', 7 => 'Mars', 8 => 'Jupiter', 9 => 'Saturn', 10 => 'Saturn', 11 => 'Jupiter',
    ];

    /** Movable / chara signs — "स्थान-परिवर्तन" nature. */
    private const CHARA = [0, 3, 6, 9];

    private const HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चन्द्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];

    /** Extra full-aspect offsets per planet (7th is universal). */
    private const ASPECTS = [
        'Sun' => [7], 'Moon' => [7], 'Mercury' => [7], 'Venus' => [7],
        'Mars' => [4, 7, 8], 'Jupiter' => [5, 7, 9], 'Saturn' => [3, 7, 10],
        'Rahu' => [5, 7, 9], 'Ketu' => [5, 7, 9],
    ];

    /** Traditional, safe upaya per planet (mantra + daan + conduct — no gem claims). */
    private const REMEDY = [
        'Sun' => 'रविवार सूर्य को जल अर्पण करें, आदित्य-हृदय स्तोत्र पढ़ें, गुड़/गेहूँ दान करें; पिता व सरकारी अधिकारियों का सम्मान करें। मंत्र: "ॐ घृणिः सूर्याय नमः"।',
        'Moon' => 'सोमवार शिव-पूजन करें, चाँदी/चावल/दूध का दान करें, माता की सेवा करें। मंत्र: "ॐ सोम सोमाय नमः"।',
        'Mars' => 'मंगलवार हनुमान-चालीसा का पाठ करें, मसूर/गुड़ दान करें, भाई-बंधुओं से सौहार्द रखें। मंत्र: "ॐ अं अंगारकाय नमः"।',
        'Mercury' => 'बुधवार विष्णु/गणेश-पूजन करें, हरी मूँग व हरी वस्तुएँ दान करें, कन्याओं को हरी चूड़ियाँ दें। मंत्र: "ॐ बुं बुधाय नमः"।',
        'Jupiter' => 'गुरुवार केसर/हल्दी/चने की दाल दान करें, गुरु-पूजन करें, पीले वस्त्र धारण करें। मंत्र: "ॐ बृं बृहस्पतये नमः"।',
        'Venus' => 'शुक्रवार लक्ष्मी-पूजन करें, सफेद वस्त्र/चावल/चीनी दान करें, स्त्री-सम्मान रखें। मंत्र: "ॐ शुं शुक्राय नमः"।',
        'Saturn' => 'शनिवार शनि-मंत्र व हनुमान-उपासना करें, सरसों-तेल/काले तिल/लोहा दान करें, मज़दूरों-वंचितों की सहायता करें। मंत्र: "ॐ शं शनैश्चराय नमः"।',
        'Rahu' => 'राहु-मंत्र जपें, सरस्वती-पूजन करें, नारियल/कंबल/उड़द दान करें; अनुशासन व सत्य का पालन करें। मंत्र: "ॐ रां राहवे नमः"।',
        'Ketu' => 'गणेश-पूजन करें, कुत्ते को भोजन दें, कंबल व काले-सफेद तिल दान करें। मंत्र: "ॐ कें केतवे नमः"।',
    ];

    /**
     * @param array<string,mixed> $chart  D1 chart from CalculationEngine
     * @param array<string,mixed> $vargas divisional charts (D9, D4, D10…)
     * @param float|null $moonLon natal Moon sidereal longitude (for dasha timing)
     * @param float|null $birthJd natal Julian Day (UT)
     * @param float|null $nowJd   current Julian Day (UT)
     * @param float $tz           birth timezone (hours) for date display
     * @return array<string,mixed>
     */
    public static function compute(
        array $chart,
        array $vargas = [],
        ?float $moonLon = null,
        ?float $birthJd = null,
        ?float $nowJd = null,
        float $tz = 0.0
    ): array {
        if (empty($chart['planets']) || empty($chart['ascendant'])) {
            return ['ok' => false, 'error' => 'चार्ट उपलब्ध नहीं'];
        }
        $asc = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $pl = $chart['planets'];

        // planet => house (from lagna), and house => occupants
        $house = [];
        $occ = array_fill(1, 12, []);
        foreach (self::PLANETS as $p) {
            if (!isset($pl[$p])) {
                continue;
            }
            $h = (int) ($pl[$p]['house'] ?? 0);
            if ($h < 1 || $h > 12) {
                continue;
            }
            $house[$p] = $h;
            $occ[$h][] = $p;
        }

        $lordOf = static fn (int $h): string => self::SIGN_LORD[self::signOfHouse($asc, $h)];
        $L1  = $lordOf(1);
        $L4  = $lordOf(4);
        $L7  = $lordOf(7);
        $L9  = $lordOf(9);
        $L10 = $lordOf(10);
        $L12 = $lordOf(12);

        // dignity per planet (tier/word/score) via the shared service
        $dig = [];
        foreach (self::PLANETS as $p) {
            if (isset($pl[$p])) {
                $dig[$p] = PlanetCondition::resolve($p, $pl, $asc);
            }
        }

        $ctx = [
            'asc' => $asc, 'pl' => $pl, 'house' => $house, 'occ' => $occ,
            'dig' => $dig, 'lordOf' => $lordOf,
            'L1' => $L1, 'L4' => $L4, 'L7' => $L7, 'L9' => $L9, 'L10' => $L10, 'L12' => $L12,
        ];

        $signals = [];
        $score = self::promiseSignals($ctx, $signals);
        $promise = self::promiseLevel($score);
        $reasons = self::reasons($ctx);
        $settle  = self::settleVerdict($ctx, $vargas);
        $karyesh = self::karyeshList($ctx);
        $obstruction = self::obstruction($ctx, $karyesh);
        $timing  = self::timing($ctx, $karyesh, $moonLon, $birthJd, $nowJd, $tz);
        $vargaNotes = self::vargaNotes($ctx, $vargas);

        $conclusion = self::conclusion($promise, $reasons, $settle, $timing, $obstruction);

        return [
            'ok'          => true,
            'lagna_hi'    => self::signHi($asc),
            'signals'     => $signals,
            'promise'     => $promise,
            'reasons'     => $reasons,
            'settle'      => $settle,
            'karyesh'     => $karyesh,
            'timing'      => $timing,
            'obstruction' => $obstruction,
            'varga'       => $vargaNotes,
            'conclusion'  => $conclusion,
        ];
    }

    // ----------------------------------------------------------------- helpers

    private static function signOfHouse(int $asc, int $h): int
    {
        return ($asc + $h - 1) % 12;
    }

    private static function signHi(int $s): string
    {
        return \AutoBusiness\Astro\LalKitab\LalKitabData::signHi(Charts::SIGNS[$s] ?? 'Aries');
    }

    private static function ord(int $h): string
    {
        static $o = [1 => 'लग्न (1)', 2 => '2रे', 3 => '3रे', 4 => '4थे', 5 => '5वें', 6 => '6ठे',
            7 => '7वें', 8 => '8वें', 9 => '9वें', 10 => '10वें', 11 => '11वें', 12 => '12वें'];
        return $o[$h] ?? (string) $h;
    }

    /** Does planet $p (in $ctx) cast a full aspect on $targetHouse? */
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

    /** planet $p linked to house $h: occupies, aspects, or tied to its lord. */
    private static function linked(array $ctx, string $p, int $h): bool
    {
        if (($ctx['house'][$p] ?? 0) === $h) {
            return true;
        }
        if (self::aspectsHouse($ctx, $p, $h)) {
            return true;
        }
        $lord = ($ctx['lordOf'])($h);
        if ($lord === $p) {
            return false; // being the lord alone is not a "connection" for this test
        }
        return self::connect($ctx, $p, $lord);
    }

    /** two planets connected: conjunction, mutual aspect, or rashi-exchange. */
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
        // parivartan (rashi exchange): a sits in a sign b rules and vice-versa
        $signA = (int) ($ctx['pl'][$a]['sign_index'] ?? -1);
        $signB = (int) ($ctx['pl'][$b]['sign_index'] ?? -1);
        return $signA >= 0 && $signB >= 0
            && self::SIGN_LORD[$signA] === $b && self::SIGN_LORD[$signB] === $a;
    }

    /**
     * A कार्येश is "afflicted" for the foreign context when it is debilitated
     * (not नीच-भंग), combust, or in a true dusthana (6th/8th). The 12th is the
     * VIDESH house itself, so a karyesh there is on-topic — NOT counted as weak.
     */
    private static function isWeak(array $ctx, string $p): bool
    {
        $tier = $ctx['dig'][$p]['dignity']['tier'] ?? '';
        $combust = ($ctx['dig'][$p]['combust']['pct'] ?? 0) >= 40;
        $bad68 = in_array($ctx['house'][$p] ?? 0, [6, 8], true);
        return in_array($tier, ['debil', 'enemy', 'great_enemy'], true) || $combust || $bad68;
    }

    private static function isStrong(array $ctx, string $p): bool
    {
        $tier = $ctx['dig'][$p]['dignity']['tier'] ?? '';
        return in_array($tier, ['param_uchcha', 'exalt', 'moolatrikona', 'own', 'great_friend', 'friend'], true);
    }

    // --------------------------------------------------------------- promise

    /** Collect weighted evidence for a foreign-settlement yoga; returns total. */
    private static function promiseSignals(array $ctx, array &$signals): float
    {
        $add = static function (string $tone, float $w, string $text) use (&$signals): void {
            $signals[] = ['tone' => $tone, 'weight' => $w, 'text' => $text];
        };
        $H = $ctx['house'];
        $score = 0.0;

        // 1) lagnesh in 12/9/7 — the person himself in the foreign axis
        $l1h = $H[$ctx['L1']] ?? 0;
        if (in_array($l1h, [12, 9, 7], true)) {
            $score += 2.0;
            $add('pos', 2.0, 'लग्नेश ' . self::HI[$ctx['L1']] . ' ' . self::ord($l1h) . ' भाव में — व्यक्ति स्वयं विदेश/यात्रा-भाव से जुड़ा।');
        }
        // 2) dwadashesh (12L) linked to lagna / 4 / 9
        foreach ([1 => 'लग्न', 4 => 'चतुर्थ (घर)', 9 => 'नवम (भाग्य)'] as $hh => $lbl) {
            if (self::linked($ctx, $ctx['L12'], $hh)) {
                $score += 1.5;
                $add('pos', 1.5, 'द्वादशेश ' . self::HI[$ctx['L12']] . ' का ' . $lbl . ' भाव से संबंध — विदेश जीवन का अंग बनता है।');
                break;
            }
        }
        // 3) benefic/any occupant in the 12th
        foreach ($ctx['occ'][12] ?? [] as $p) {
            if (in_array($p, ['Venus', 'Jupiter'], true) && self::isStrong($ctx, $p)) {
                $score += 1.5;
                $add('pos', 1.5, self::HI[$p] . ' 12वें भाव में बली — विदेश में सुखी व सम्मानित निवास।');
            } elseif ($p === 'Rahu') {
                $score += 1.5;
                $add('pos', 1.5, 'राहु 12वें भाव में — विदेशी भूमि व संस्कृति का प्रबल आकर्षण।');
            } else {
                $score += 0.7;
                $struggle = ($ctx['dig'][$p]['dignity']['tier'] ?? '') === 'debil'
                    || ($ctx['dig'][$p]['combust']['pct'] ?? 0) >= 40;
                $add('info', 0.7, self::HI[$p] . ' 12वें भाव में — विदेश-निवास का संकेत' . ($struggle ? ' (पर संघर्ष के साथ)।' : '।'));
            }
        }
        // 4) 9th occupied / 9L–12L connection — fortune abroad
        if (!empty($ctx['occ'][9])) {
            $add('info', 0.5, '9वें (भाग्य/दीर्घ-यात्रा) भाव में ' . implode(', ', array_map(fn ($x) => self::HI[$x], $ctx['occ'][9])) . ' — विदेश-यात्रा का बल।');
            $score += 0.5;
        }
        if (self::connect($ctx, $ctx['L9'], $ctx['L12'])) {
            $score += 2.0;
            $add('pos', 2.0, 'नवमेश ' . self::HI[$ctx['L9']] . ' व द्वादशेश ' . self::HI[$ctx['L12']] . ' का संबंध — विदेश में भाग्योदय।');
        }
        // 5) Rahu — chief foreign karaka
        $rh = $H['Rahu'] ?? 0;
        if (in_array($rh, [1, 12, 9, 7, 10], true)) {
            $score += 1.5;
            $add('pos', 1.5, 'विदेश का प्रमुख कारक राहु ' . self::ord($rh) . ' भाव में — म्लेच्छ/विदेशी योग सक्रिय।');
        }
        if (self::connect($ctx, 'Rahu', $ctx['L1']) || self::connect($ctx, 'Rahu', $ctx['L4'])) {
            $score += 1.0;
            $add('pos', 1.0, 'राहु का लग्नेश/चतुर्थेश से संबंध — विदेशी जीवनशैली की ओर खिंचाव।');
        }
        // 6) Moon — travel mind
        $moonSign = (int) ($ctx['pl']['Moon']['sign_index'] ?? -1);
        if (in_array($moonSign, self::CHARA, true)) {
            $score += 0.8;
            $add('info', 0.8, 'चंद्र चर राशि (' . self::signHi($moonSign) . ') में — स्थान-परिवर्तन की मानसिकता।');
        }
        if (in_array($H['Moon'] ?? 0, [12, 9], true)) {
            $score += 0.7;
            $add('info', 0.7, 'चंद्र ' . self::ord($H['Moon']) . ' भाव में — समुद्र-पार/विदेश-यात्रा का कारक।');
        }
        // 7) Saturn — karma far from home
        if (self::linked($ctx, 'Saturn', 12) || self::linked($ctx, 'Saturn', 4)) {
            $score += 1.2;
            $add('pos', 1.2, 'शनि का 12वें/चतुर्थ भाव से संबंध — कर्मभूमि जन्मभूमि से दूर बनती है।');
        }
        // 8) 4th affliction — the "settle abroad" signature
        $s4 = self::fourthAffliction($ctx);
        if ($s4['afflicted']) {
            $score += 2.0;
            $add('pos', 2.0, 'चतुर्थ/चतुर्थेश पीड़ित — ' . $s4['why'] . ' जन्मभूमि का सुख क्षीण, स्थायी प्रवास का बल।');
        }
        // 9) movable-sign predominance
        $charaCount = 0;
        foreach (['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'] as $p) {
            if (in_array((int) ($ctx['pl'][$p]['sign_index'] ?? -1), self::CHARA, true)) {
                $charaCount++;
            }
        }
        if ($charaCount >= 5) {
            $score += 1.0;
            $add('info', 1.0, $charaCount . ' ग्रह चर राशियों में — बार-बार स्थान-परिवर्तन, अंततः दूर बसने की प्रवृत्ति।');
        }
        // 10) marriage / career routes
        if (in_array($H[$ctx['L7']] ?? 0, [9, 12], true)) {
            $score += 0.8;
            $add('info', 0.8, 'सप्तमेश ' . self::HI[$ctx['L7']] . ' ' . self::ord($H[$ctx['L7']]) . ' भाव में — विवाह/साझेदारी के माध्यम से विदेश।');
        }
        if (in_array($H[$ctx['L10']] ?? 0, [9, 12], true) || ($H[$ctx['L12']] ?? 0) === 10) {
            $score += 0.8;
            $add('info', 0.8, 'दशमेश-द्वादशेश का संबंध — करियर/नौकरी के कारण विदेश (Work Visa)।');
        }

        return $score;
    }

    private static function promiseLevel(float $score): array
    {
        if ($score >= 6.0) {
            $lvl = 'प्रबल'; $tone = 'pos';
            $t = 'जन्मकुंडली में विदेश-निवास का प्रबल वादा है — योग स्पष्ट व बहु-आयामी हैं।';
        } elseif ($score >= 3.5) {
            $lvl = 'मध्यम'; $tone = 'info';
            $t = 'विदेश-यात्रा/निवास की अच्छी संभावना है; अनुकूल दशा-गोचर में योग फलित होगा।';
        } elseif ($score >= 1.5) {
            $lvl = 'क्षीण'; $tone = 'info';
            $t = 'विदेश-योग सीमित है — यात्रा/अल्प-प्रवास संभव, स्थायी निवास के लिए संकेत दुर्बल।';
        } else {
            $lvl = 'नगण्य'; $tone = 'neg';
            $t = 'जन्मकुंडली में विदेश-निवास का स्पष्ट वादा नहीं मिलता; प्रबल दशा-गोचर पर ही अल्प-यात्रा।';
        }
        return ['level' => $lvl, 'tone' => $tone, 'score' => round($score, 1), 'text' => $t];
    }

    /** 4th house / 4th lord affliction test (key settle-abroad signal). */
    private static function fourthAffliction(array $ctx): array
    {
        $why = [];
        $L4 = $ctx['L4'];
        $l4h = $ctx['house'][$L4] ?? 0;
        if (in_array($l4h, [6, 8, 12], true)) {
            $why[] = 'चतुर्थेश ' . self::HI[$L4] . ' ' . self::ord($l4h) . ' (त्रिक) भाव में';
        }
        if (($ctx['dig'][$L4]['dignity']['tier'] ?? '') === 'debil') {
            $why[] = 'चतुर्थेश नीच का';
        }
        if (($ctx['dig'][$L4]['combust']['pct'] ?? 0) >= 40) {
            $why[] = 'चतुर्थेश अस्त';
        }
        // malefics occupying / aspecting the 4th
        foreach (['Saturn', 'Rahu', 'Ketu', 'Mars'] as $m) {
            if (($ctx['house'][$m] ?? 0) === 4 || self::aspectsHouse($ctx, $m, 4)) {
                $why[] = self::HI[$m] . ' का चतुर्थ भाव पर प्रभाव';
            }
        }
        return ['afflicted' => $why !== [], 'why' => implode('; ', $why) . ($why ? '।' : '')];
    }

    // --------------------------------------------------------------- reasons

    private static function reasons(array $ctx): array
    {
        $H = $ctx['house'];
        $cand = [];   // each: [weight, reason, why]

        // career / job (10th–12th link, or Saturn tying karma to the 12th)
        $w = 0.0;
        if (in_array($H[$ctx['L10']] ?? 0, [9, 12], true)) { $w += 2; }
        if (($H[$ctx['L12']] ?? 0) === 10) { $w += 1.5; }
        if (self::linked($ctx, 'Saturn', 10) && self::linked($ctx, 'Saturn', 12)) { $w += 1; }
        if ($w > 0) {
            $cand[] = [$w, 'नौकरी / करियर', 'दशमेश-द्वादशेश/शनि का संबंध — Work Visa के आधार पर विदेश गमन व वहीं PR का मार्ग।'];
        }
        // higher education (Jupiter→9/12 + Mercury/5th active)
        if ((self::linked($ctx, 'Jupiter', 9) || self::linked($ctx, 'Jupiter', 12))
            && (in_array($H['Mercury'] ?? 0, [9, 12, 5], true) || !empty($ctx['occ'][5]))) {
            $cand[] = [1.8, 'उच्च शिक्षा', 'गुरु का 9वें/12वें से संबंध व बुध/पंचम की सक्रियता — Student Visa से विदेश।'];
        }
        // marriage / spouse
        $w = 0.0;
        if (in_array($H[$ctx['L7']] ?? 0, [9, 12], true)) { $w += 1.8; }
        if (self::linked($ctx, 'Venus', 12) && in_array($ctx['L7'], ['Venus'], true)) { $w += 0.6; }
        if ($w > 0) {
            $cand[] = [$w, 'विवाह / जीवनसाथी', 'सप्तमेश का 9वें/12वें से संबंध — Spouse के माध्यम से विदेश/PR।'];
        }
        // business / partnership (needs an occupied 7th AND its lord tied abroad)
        if (!empty($ctx['occ'][7]) && self::linked($ctx, $ctx['L7'], 12)) {
            $cand[] = [1.2, 'व्यापार / साझेदारी', 'सप्तम भाव व सप्तमेश का 12वें से संबंध — विदेश में व्यापार/साझेदारी।'];
        }
        // fortune / wanderlust (Rahu/Moon on the 9-12 axis)
        $w = 0.0;
        if (in_array($H['Rahu'] ?? 0, [1, 9, 12], true)) { $w += 1.4; }
        if (in_array($H['Moon'] ?? 0, [9, 12], true)) { $w += 1.0; }
        if ($w > 0) {
            $cand[] = [$w, 'भाग्य व स्वाभाविक भ्रमण', 'राहु/चंद्र का 9वें-12वें से संबंध — भाग्यवश विदेश-गमन व दीर्घ प्रवास।'];
        }

        if ($cand === []) {
            return [['reason' => 'स्पष्ट विशेष कारण नहीं', 'why' => 'किसी एक प्रबल मार्ग (नौकरी/शिक्षा/विवाह) का स्पष्ट योग नहीं; सामान्य अवसर पर ही यात्रा।']];
        }
        // strongest reasons first, keep the top 3
        usort($cand, static fn ($a, $b) => $b[0] <=> $a[0]);
        return array_map(static fn ($c) => ['reason' => $c[1], 'why' => $c[2]], array_slice($cand, 0, 3));
    }

    // ----------------------------------------------------------- settle/return

    private static function settleVerdict(array $ctx, array $vargas): array
    {
        $L4 = $ctx['L4'];
        $l4h = $ctx['house'][$L4] ?? 0;
        $why = [];
        $settleScore = 0; // + = settle abroad, − = return home

        $aff = self::fourthAffliction($ctx);
        if ($aff['afflicted']) {
            $settleScore += 2;
            $why[] = $aff['why'];
        }
        if (self::isStrong($ctx, $L4) && in_array($l4h, [1, 4, 5, 7, 9, 10], true) && !$aff['afflicted']) {
            $settleScore -= 2;
            $why[] = 'चतुर्थेश ' . self::HI[$L4] . ' बली व केंद्र/त्रिकोण में — जन्मभूमि का खिंचाव प्रबल (वापसी-योग)।';
        }
        // benefic aspect on 4th → home pull
        foreach (['Jupiter', 'Venus'] as $b) {
            if (self::aspectsHouse($ctx, $b, 4) || ($ctx['house'][$b] ?? 0) === 4) {
                $settleScore -= 1;
                $why[] = self::HI[$b] . ' की चतुर्थ भाव पर शुभ दृष्टि — घर/जड़ों से लगाव।';
            }
        }
        // D4 confirmation
        $d4note = self::d4SettleNote($ctx, $vargas);
        if ($d4note !== null) {
            $settleScore += $d4note['dir'];
            $why[] = $d4note['text'];
        }

        if ($settleScore >= 2) {
            $verdict = 'स्थायी विदेश निवास'; $tone = 'pos';
            $text = 'चतुर्थ भाव दुर्बल/पीड़ित है और विदेश-कारक प्रबल — व्यक्ति के विदेश में ही स्थायी रूप से बसने का प्रबल योग। PR मिलने पर लौटने की संभावना कम।';
        } elseif ($settleScore <= -1) {
            $verdict = 'घर वापसी की संभावना'; $tone = 'info';
            $text = 'चतुर्थेश बली व शुभ है — विदेश जाकर भी अंततः जन्मभूमि लौटने का योग; प्रायः विदेश-कारक दशा समाप्त होते ही, या बली चतुर्थेश की दशा आने पर वापसी।';
        } else {
            $verdict = 'मिश्र — परिस्थिति पर निर्भर'; $tone = 'info';
            $text = 'संकेत मिश्रित हैं — कुछ वर्ष विदेश-प्रवास के बाद वापसी या पुनः प्रवास दोनों संभव; दशा-क्रम निर्णायक रहेगा।';
        }
        return ['verdict' => $verdict, 'tone' => $tone, 'text' => $text, 'why' => $why];
    }

    /** D4 (chaturthamsa) — is permanent home shown abroad? */
    private static function d4SettleNote(array $ctx, array $vargas): ?array
    {
        $d4 = $vargas['D4'] ?? null;
        if ($d4 === null || empty($d4['planets'])) {
            return null;
        }
        $ascSign = (int) ($d4['asc_sign'] ?? 0);
        $hOf = [];
        foreach ($d4['planets'] as $p) {
            $hOf[$p['name']] = ((((int) $p['sign'] - $ascSign) % 12) + 12) % 12 + 1;
        }
        $d4L4 = self::SIGN_LORD[($ascSign + 3) % 12];
        $d4L12 = self::SIGN_LORD[($ascSign + 11) % 12];
        // 4th lord of D4 in D4-12th, or 12th lord in D4-4th → home abroad
        if (($hOf[$d4L4] ?? 0) === 12 || ($hOf[$d4L12] ?? 0) === 4 || ($hOf[$d4L4] ?? 0) === 12) {
            return ['dir' => 1, 'text' => 'D-4 (चतुर्थांश) में भी चतुर्थेश/द्वादशेश का संबंध — "स्थायी घर विदेश में" की पुष्टि।'];
        }
        if (in_array($hOf[$d4L4] ?? 0, [1, 4, 5, 9, 10], true)) {
            return ['dir' => -1, 'text' => 'D-4 में चतुर्थेश केंद्र/त्रिकोण में — स्वदेश में भी स्थायित्व का बल।'];
        }
        return null;
    }

    // ---------------------------------------------------------------- karyesh

    private static function karyeshList(array $ctx): array
    {
        $roles = [];   // planet => list of role labels
        $push = static function (string $p, string $role) use (&$roles): void {
            $roles[$p][] = $role;
        };
        $push($ctx['L12'], 'द्वादशेश (विदेश-निवास)');
        $push($ctx['L4'], 'चतुर्थेश (स्थायी घर)');
        $push($ctx['L9'], 'नवमेश (विदेश-भाग्य)');
        $push('Sun', 'सरकारी स्वीकृति');
        if ($ctx['L10'] !== 'Sun') {
            $push($ctx['L10'], 'दशमेश (सरकारी कर्म)');
        }
        $push('Rahu', 'विदेश-कारक');
        $push('Mercury', 'दस्तावेज़/आवेदन');

        $out = [];
        foreach ($roles as $p => $rl) {
            $h = $ctx['house'][$p] ?? 0;
            $out[] = [
                'planet' => $p,
                'hi' => self::HI[$p],
                'roles' => array_values(array_unique($rl)),
                'house' => $h,
                'house_ord' => self::ord($h),
                'dignity' => $ctx['dig'][$p]['dignity']['word'] ?? '',
                'weak' => self::isWeak($ctx, $p),
            ];
        }
        return $out;
    }

    // ------------------------------------------------------------- obstruction

    private static function obstruction(array $ctx, array $karyesh): array
    {
        $problems = [];
        $remedies = [];
        $seen = [];
        foreach ($karyesh as $k) {
            $p = $k['planet'];
            $reasons = [];
            if (($ctx['dig'][$p]['combust']['pct'] ?? 0) >= 40 && $p !== 'Sun') {
                $reasons[] = 'अस्त (सूर्य के अति निकट) — फ़ाइल दबती है, प्रयास दिखते नहीं';
            }
            if (!empty($ctx['pl'][$p]['retro']) && !in_array($p, ['Rahu', 'Ketu'], true)) {
                $reasons[] = 'वक्री — प्रक्रिया पीछे लौटती है, दस्तावेज़ दोबारा माँगे जाते हैं';
            }
            if (($ctx['dig'][$p]['dignity']['tier'] ?? '') === 'debil') {
                $reasons[] = 'नीच राशि में — दीर्घ विलंब व अतिरिक्त शर्तें';
            }
            if (in_array($ctx['house'][$p] ?? 0, [6, 8], true)) {
                // 6th/8th are true dusthanas; the 12th is the videsh house itself,
                // so a karyesh there is on-topic and NOT treated as an obstruction.
                $reasons[] = self::ord($ctx['house'][$p]) . ' (त्रिक — रोग/बाधा) भाव में — कार्येश दबा-सा, विलंब';
            }
            if ($reasons !== []) {
                $problems[] = ['hi' => self::HI[$p], 'roles' => $k['roles'], 'reason' => implode('; ', $reasons) . '।'];
                if (!isset($seen[$p])) {
                    $remedies[] = ['hi' => self::HI[$p], 'text' => self::REMEDY[$p] ?? ''];
                    $seen[$p] = true;
                }
            }
        }
        // Ketu on 4th / 12th — paperwork ambiguity
        foreach ([4, 12] as $hh) {
            if (($ctx['house']['Ketu'] ?? 0) === $hh || self::aspectsHouse($ctx, 'Ketu', $hh)) {
                $problems[] = ['hi' => 'केतु', 'roles' => ['बाधा-कारक'], 'reason' => 'केतु का ' . self::ord($hh) . ' भाव पर प्रभाव — कागज़ों में अस्पष्टता/अटकाव, धैर्य की परीक्षा।'];
                if (!isset($seen['Ketu'])) {
                    $remedies[] = ['hi' => 'केतु', 'text' => self::REMEDY['Ketu']];
                    $seen['Ketu'] = true;
                }
                break;
            }
        }

        $present = $problems !== [];
        $summary = $present
            ? 'नीचे दिए ग्रह विदेश/PR-योग में बाधा डाल रहे हैं — इनके उपाय करने पर मार्ग सुगम होगा।'
            : 'किसी प्रमुख कार्येश पर बड़ी पीड़ा नहीं — विदेश/PR-मार्ग में ग्रह-जन्य कोई बड़ी बाधा नहीं दिखती।';
        return ['present' => $present, 'summary' => $summary, 'problems' => $problems, 'remedies' => $remedies];
    }

    // ---------------------------------------------------------------- timing

    private static function timing(array $ctx, array $karyesh, ?float $moonLon, ?float $birthJd, ?float $nowJd, float $tz): array
    {
        $karSet = [];
        foreach ($karyesh as $k) {
            $karSet[$k['planet']] = true;
        }
        // planets with a real 12/4/Sun link carry the PR signal most strongly
        $strongKar = [];
        foreach (array_keys($karSet) as $p) {
            if (self::linked($ctx, $p, 12) || self::linked($ctx, $p, 4)
                || $p === 'Sun' || ($ctx['house'][$p] ?? 0) === 12) {
                $strongKar[$p] = true;
            }
        }
        if ($strongKar === []) {
            $strongKar = $karSet;
        }

        $windows = [];
        if ($moonLon !== null && $birthJd !== null && $nowJd !== null) {
            $seq = VimshottariDasha::sequence($moonLon, $birthJd);
            $horizon = $nowJd + 15.0 * 365.2564;   // next ~15 years
            foreach ($seq['mahadashas'] as $md) {
                if ($md['end_jd'] < $nowJd || $md['start_jd'] > $horizon) {
                    continue;
                }
                $mdKar = isset($strongKar[$md['lord']]);
                foreach (VimshottariDasha::antardashas($md) as $ad) {
                    if ($ad['end_jd'] < $nowJd || $ad['start_jd'] > $horizon) {
                        continue;
                    }
                    $adKar = isset($strongKar[$ad['lord']]);
                    // a window is relevant when the MD or the AD lord is a strong
                    // (12/4/Sun-linked) कार्येश; both together = the प्रबल PR योग.
                    if (!$mdKar && !$adKar) {
                        continue;
                    }
                    $why = [];
                    if ($mdKar) {
                        $why[] = 'महादशा ' . self::HI[$md['lord']] . ' कार्येश';
                    }
                    if ($adKar) {
                        $why[] = 'अंतर्दशा ' . self::HI[$ad['lord']] . ' कार्येश';
                    }
                    $windows[] = [
                        'label' => self::HI[$md['lord']] . ' – ' . self::HI[$ad['lord']],
                        'from' => JulianDay::toDmy(max($ad['start_jd'], $nowJd), $tz),
                        'to' => JulianDay::toDmy($ad['end_jd'], $tz),
                        'from_jd' => $ad['start_jd'],
                        'why' => implode(', ', $why) . '।',
                        'both' => $mdKar && $adKar,
                    ];
                }
            }
            // best windows first: both-karyesh, then earliest
            usort($windows, static function ($a, $b) {
                if ($a['both'] !== $b['both']) {
                    return $a['both'] ? -1 : 1;
                }
                return $a['from_jd'] <=> $b['from_jd'];
            });
            $windows = array_slice($windows, 0, 4);
        }

        $note = 'आदर्श PR-योग: द्वादशेश की महादशा/अंतर्दशा + चतुर्थेश या सूर्य की अंतर्दशा/प्रत्यंतर्दशा, '
            . 'और उसी अवधि में गुरु-शनि का 12वें/चतुर्थ भाव पर द्विग्रह गोचर तथा वर्षफल में मुंथा/वर्षेश की पुष्टि। '
            . 'ऊपर दी अवधियाँ केवल दशा-आधारित खिड़कियाँ हैं — अंतिम माह गोचर व वर्षफल से मिलाएँ।';

        return ['windows' => $windows, 'note' => $note, 'has_dasha' => $moonLon !== null];
    }

    // ----------------------------------------------------------------- vargas

    private static function vargaNotes(array $ctx, array $vargas): array
    {
        $out = [];
        // D9 — strength confirmation of the foreign karyeshes
        $d9 = $vargas['D9'] ?? null;
        if ($d9 !== null && !empty($d9['planets'])) {
            $ascSign = (int) ($d9['asc_sign'] ?? 0);
            $strong = [];
            foreach ($d9['planets'] as $p) {
                if (!in_array($p['name'], [$ctx['L12'], $ctx['L9'], 'Rahu'], true)) {
                    continue;
                }
                $tier = PlanetCondition::dignity($p['name'], (int) $p['sign'], (float) $p['deg'], [], $ascSign)['tier'] ?? '';
                if (in_array($tier, ['param_uchcha', 'exalt', 'moolatrikona', 'own', 'great_friend', 'friend'], true)) {
                    $strong[] = self::HI[$p['name']];
                }
            }
            $out[] = $strong !== []
                ? ['chart' => 'D-9 नवांश', 'text' => 'नवांश में ' . implode(', ', $strong) . ' बली — विदेश-योग की पुष्टि (वादा टिकाऊ)।', 'tone' => 'pos']
                : ['chart' => 'D-9 नवांश', 'text' => 'नवांश में विदेश-कारक विशेष बली नहीं — योग फलित होने में D1 की दशा-गोचर अधिक निर्णायक।', 'tone' => 'info'];
        }
        // D4 handled inside settle; add a short line here too
        $d4 = self::d4SettleNote($ctx, $vargas);
        if ($d4 !== null) {
            $out[] = ['chart' => 'D-4 चतुर्थांश', 'text' => $d4['text'], 'tone' => $d4['dir'] > 0 ? 'pos' : 'info'];
        }
        return $out;
    }

    // -------------------------------------------------------------- conclusion

    private static function conclusion(array $promise, array $reasons, array $settle, array $timing, array $obstruction): string
    {
        $parts = [];
        $parts[] = 'विदेश-योग ' . $promise['level'] . ' है। ' . $promise['text'];
        if ($reasons) {
            $parts[] = 'मुख्य कारण: ' . implode(', ', array_map(static fn ($r) => $r['reason'], array_slice($reasons, 0, 2))) . '।';
        }
        $parts[] = $settle['verdict'] . ' — ' . $settle['text'];
        if (!empty($timing['windows'])) {
            $w = $timing['windows'][0];
            $parts[] = 'दशा-अनुसार निकटतम प्रबल PR-खिड़की: ' . $w['label'] . ' (' . $w['from'] . ' – ' . $w['to'] . ')। अंतिम पुष्टि गोचर व वर्षफल से करें।';
        }
        $parts[] = $obstruction['present']
            ? 'बाधा: ऊपर दिए ग्रहों के उपाय करने पर मार्ग सुगम होगा।'
            : 'ग्रह-जन्य कोई बड़ी बाधा नहीं।';
        return implode(' ', $parts);
    }
}
