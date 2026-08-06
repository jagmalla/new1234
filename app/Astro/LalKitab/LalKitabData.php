<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\LalKitab;

/**
 * Baked Lal Kitab (लाल किताब) knowledge bank.
 *
 * The bulky text banks (planet-in-house remedies, prediction sutras, parental
 * debts, Sade-Sati / Dhaiya remedies, manglik remedies, conjunction remedies,
 * house subjects and planet classifications) live in the sibling
 * {@see lalkitab_data.json} file — extracted from the owner's Lal Kitab source
 * workbook (39 sheets). This class loads + caches that JSON and exposes the
 * fixed Lal Kitab constants (the chart is a fixed-Aries teva: house 1 is always
 * Aries and the twelve house-lords never change).
 *
 * Edit the JSON to change any predictive text; this file only holds structure.
 */
final class LalKitabData
{
    /** Fixed Lal Kitab house lords — house 1 is always Aries, so lords are fixed. */
    public const HOUSE_LORD = [
        1 => 'Mars', 2 => 'Venus', 3 => 'Mercury', 4 => 'Moon',
        5 => 'Sun', 6 => 'Mercury', 7 => 'Venus', 8 => 'Mars',
        9 => 'Jupiter', 10 => 'Saturn', 11 => 'Saturn', 12 => 'Jupiter',
    ];

    /** Fixed Lal Kitab sign of each house (Aries..Pisces), 0-based sign index. */
    public const HOUSE_SIGN = [
        1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5,
        7 => 6, 8 => 7, 9 => 8, 10 => 9, 11 => 10, 12 => 11,
    ];

    /** English planet key => Hindi display name. */
    public const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चन्द्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि',
        'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];

    /** English sign key (Charts::SIGNS) => Hindi rashi name. */
    public const SIGN_HI = [
        'Aries' => 'मेष', 'Taurus' => 'वृष', 'Gemini' => 'मिथुन', 'Cancer' => 'कर्क',
        'Leo' => 'सिंह', 'Virgo' => 'कन्या', 'Libra' => 'तुला', 'Scorpio' => 'वृश्चिक',
        'Sagittarius' => 'धनु', 'Capricorn' => 'मकर', 'Aquarius' => 'कुम्भ', 'Pisces' => 'मीन',
    ];

    /** Exaltation sign index per planet (Lal Kitab / classical). */
    public const EXALT = [
        'Sun' => 0, 'Moon' => 1, 'Mars' => 9, 'Mercury' => 5,
        'Jupiter' => 3, 'Venus' => 11, 'Saturn' => 6, 'Rahu' => 1, 'Ketu' => 7,
    ];

    /** Debilitation sign index per planet. */
    public const DEBIL = [
        'Sun' => 6, 'Moon' => 7, 'Mars' => 3, 'Mercury' => 11,
        'Jupiter' => 9, 'Venus' => 5, 'Saturn' => 0, 'Rahu' => 7, 'Ketu' => 1,
    ];

    /**
     * पक्का घर — each planet's own "pukka" house(s) in the Lal Kitab teva. A
     * planet sitting in its pukka ghar gives its results with full force and
     * added stability (सूर्य-1, चन्द्र-4, मंगल-3/8, बुध-7, गुरु-9, शुक्र-7,
     * शनि-10, राहु-12, केतु-6).
     */
    public const PUKKA_GHAR = [
        'Sun' => [1], 'Moon' => [4], 'Mars' => [3, 8], 'Mercury' => [7],
        'Jupiter' => [9], 'Venus' => [7], 'Saturn' => [10], 'Rahu' => [12], 'Ketu' => [6],
    ];

    /**
     * House-based dignity — the Lal Kitab teva is fixed (house 1 is always मेष),
     * so a planet's उच्च / नीच / स्वगृही standing is read from the *house* it sits
     * in, never from its real transiting rashi. Confirmed by the owner, and
     * corroborated by the bank's own भंग rules, which are all stated in houses
     * ("सूर्य मेष में हो और सामने सातवें भाव (तुला) में …").
     *
     * नीच is the seventh house from उच्च, which holds for all nine planets.
     *
     * These are used by the मसनूई module. The per-planet cards still classify
     * from the real rashi via {@see EXALT} / {@see DEBIL}; switching them over is
     * a separate change the owner has not yet authorised.
     */
    public const UCH_BHAV = [
        'Sun' => [1], 'Moon' => [2], 'Mars' => [10], 'Mercury' => [6], 'Jupiter' => [4],
        'Venus' => [12], 'Saturn' => [7], 'Rahu' => [3, 6], 'Ketu' => [9, 12],
    ];

    /** Debilitation house(s) — always the seventh from the exaltation house. */
    public const NEECH_BHAV = [
        'Sun' => [7], 'Moon' => [8], 'Mars' => [4], 'Mercury' => [12], 'Jupiter' => [10],
        'Venus' => [6], 'Saturn' => [1], 'Rahu' => [9, 12], 'Ketu' => [3, 6],
    ];

    /** Own-sign house(s) — the house whose fixed rashi this planet rules. */
    public const SWA_BHAV = [
        'Sun' => [5], 'Moon' => [4], 'Mars' => [1, 8], 'Mercury' => [3, 6], 'Jupiter' => [9, 12],
        'Venus' => [2, 7], 'Saturn' => [10, 11], 'Rahu' => [12], 'Ketu' => [6],
    ];

    /**
     * पक्का घर — a house's कारक planet, which is that planet's pukka ghar; the two
     * are one table rather than two. Sitting here means the result arrives at full
     * force and stability — for a benefic *and* for a malefic alike, so this is a
     * measure of intensity, not of auspiciousness.
     */
    public const KARAK_BHAV = [
        'Sun' => [1], 'Moon' => [4], 'Mars' => [3, 8], 'Mercury' => [6, 7],
        'Jupiter' => [2, 5, 9, 11, 12], 'Venus' => [7], 'Saturn' => [8, 10],
        'Rahu' => [12], 'Ketu' => [6],
    ];

    /** कच्चा घर — supplied by the owner as an independent list, not derivable. */
    public const KACHCHA_BHAV = [
        'Sun' => [4, 7], 'Moon' => [7, 12], 'Mars' => [4], 'Mercury' => [9, 12],
        'Jupiter' => [8, 12], 'Venus' => [1, 9], 'Saturn' => [4, 12],
        'Rahu' => [1, 4, 8], 'Ketu' => [4, 10],
    ];

    /**
     * भाव-दृष्टि — house => [seen house => strength %]. Lal Kitab aspects run one
     * way and forwards only, which is why houses 7–12 cast none: nothing is left
     * ahead of them. The sole exception is the 8th, whose "टक्कर की दृष्टि" looks
     * back at the 2nd.
     *
     * Supplied by the owner and treated as authoritative over the bank's
     * `bhav_drishti` sheet, which is a plain "every house sees the 6th ahead"
     * rule that contradicts these at houses 2, 3 and 5. Currently read by the
     * मसनूई module only.
     */
    public const DRISHTI = [
        1 => [7 => 100], 2 => [6 => 25], 3 => [9 => 50, 11 => 50], 4 => [10 => 100],
        5 => [9 => 50], 6 => [12 => 25], 7 => [], 8 => [2 => 100],
        9 => [], 10 => [], 11 => [], 12 => [],
    ];

    /** Short life-area label of each house (1..12) — for plain-language फल. */
    public const HOUSE_TOPIC = [
        1 => 'शरीर, स्वास्थ्य व मान-सम्मान',
        2 => 'धन, कुटुम्ब व वाणी',
        3 => 'भाई-बहन, पराक्रम व साहस',
        4 => 'माता, सुख, भूमि-वाहन व मन',
        5 => 'संतान, विद्या व बुद्धि',
        6 => 'रोग, शत्रु, ऋण व मुकदमा',
        7 => 'विवाह, दाम्पत्य व साझेदारी',
        8 => 'आयु, आकस्मिक बाधा व गुप्त बातें',
        9 => 'भाग्य, धर्म, पिता व यात्रा',
        10 => 'कर्म, व्यवसाय व प्रतिष्ठा',
        11 => 'आय, लाभ व इच्छापूर्ति',
        12 => 'व्यय, हानि, विदेश व शयन-सुख',
    ];

    /** @var array<string,mixed>|null cached decoded JSON */
    private static ?array $bank = null;

    /** @return array<string,mixed> the full decoded knowledge bank (empty on failure). */
    public static function bank(): array
    {
        if (self::$bank !== null) {
            return self::$bank;
        }
        $file = \AB_ROOT . '/app/Astro/LalKitab/lalkitab_data.json';
        $json = @file_get_contents($file);
        if ($json === false) {
            self::$bank = [];
            error_log('LalKitab data bank missing: ' . $file);
            return self::$bank;
        }
        $data = json_decode($json, true);
        self::$bank = is_array($data) ? $data : [];
        return self::$bank;
    }

    /** One top-level section of the bank (e.g. "bhavgat_upay"). */
    public static function section(string $key): array
    {
        $b = self::bank();
        return isset($b[$key]) && is_array($b[$key]) ? $b[$key] : [];
    }

    public static function planetHi(string $p): string
    {
        return self::PLANET_HI[$p] ?? $p;
    }

    public static function signHi(string $s): string
    {
        return self::SIGN_HI[$s] ?? $s;
    }

    /** Hindi ordinal house label ("पहले", "दूसरे" ...). */
    public static function houseOrdinalHi(int $h): string
    {
        static $o = [
            1 => 'पहले', 2 => 'दूसरे', 3 => 'तीसरे', 4 => 'चौथे', 5 => 'पाँचवें',
            6 => 'छठे', 7 => 'सातवें', 8 => 'आठवें', 9 => 'नौवें', 10 => 'दसवें',
            11 => 'ग्यारहवें', 12 => 'बारहवें',
        ];
        return $o[$h] ?? ((string) $h);
    }
}
