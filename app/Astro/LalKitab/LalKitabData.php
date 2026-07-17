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
