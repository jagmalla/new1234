<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\Drishti;

/**
 * Generates a per-house Hindi reading by COMBINING the editable rule tables
 * ({@see HousePredictionRepository}) with the chart facts the engine already
 * computed (rashi in the house, house lord, occupying planets, drishti on the
 * house, where the lord sits). Every rule sentence comes from a Tab-6 template;
 * a rule with no matching data is skipped gracefully.
 */
final class HousePrediction
{
    private const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];
    private const RASHI_HI = [
        'Aries' => 'मेष', 'Taurus' => 'वृषभ', 'Gemini' => 'मिथुन', 'Cancer' => 'कर्क',
        'Leo' => 'सिंह', 'Virgo' => 'कन्या', 'Libra' => 'तुला', 'Scorpio' => 'वृश्चिक',
        'Sagittarius' => 'धनु', 'Capricorn' => 'मकर', 'Aquarius' => 'कुंभ', 'Pisces' => 'मीन',
    ];
    private const HOUSE_HI = [
        1 => 'प्रथम', 2 => 'द्वितीय', 3 => 'तृतीय', 4 => 'चतुर्थ', 5 => 'पंचम', 6 => 'षष्ठ',
        7 => 'सप्तम', 8 => 'अष्टम', 9 => 'नवम', 10 => 'दशम', 11 => 'एकादश', 12 => 'द्वादश',
    ];
    private const QUALITY_HI = ['Movable' => 'चर', 'Fixed' => 'स्थिर', 'Dual' => 'द्विस्वभाव'];
    private const ELEMENT_HI = ['Fire' => 'अग्नि', 'Earth' => 'पृथ्वी', 'Air' => 'वायु', 'Water' => 'जल', 'Ether/Sky' => 'आकाश'];
    private const TRIK = [6, 8, 12];

    /**
     * @param array<string,mixed> $chart  computed chart (needs houses + planets)
     * @param array<string,mixed> $rules  loaded rule tables (HousePredictionRepository::load)
     * @return array<int,array{house:int,rashi:string,intro:string,lines:list<string>}>
     */
    public static function generate(array $chart, array $rules): array
    {
        $out = [];
        $houses = $chart['houses'] ?? [];
        $planets = $chart['planets'] ?? [];

        for ($h = 1; $h <= 12; $h++) {
            $H = $houses[$h] ?? null;
            if ($H === null) {
                continue;
            }
            $rashiEn = (string) ($H['sign'] ?? '');
            $rashiNum = (int) ($H['rashi_num'] ?? 0);
            $lordEn = (string) ($H['lord'] ?? '');
            $ri = $rules['rashi'][$rashiNum] ?? null;
            $rashiElement = (string) ($ri['element'] ?? '');
            $qualityHi = self::QUALITY_HI[$ri['quality'] ?? ''] ?? ($ri['quality'] ?? '');
            $elementHi = self::ELEMENT_HI[$rashiElement] ?? $rashiElement;

            $ctx = [
                'house' => self::HOUSE_HI[$h] ?? (string) $h,
                'rashi' => self::RASHI_HI[$rashiEn] ?? $rashiEn,
                'lord'  => self::PLANET_HI[$lordEn] ?? $lordEn,
            ];

            // Intro: the rashi, its quality + element, and its lord (framing).
            $intro = sprintf(
                '%s भाव में %s राशि है — यह %s स्वभाव तथा %s तत्व की राशि है, और इसका स्वामी %s है।',
                $ctx['house'], $ctx['rashi'], $qualityHi, $elementHi, $ctx['lord']
            );

            $lines = [];

            // --- Each planet occupying the house ---
            foreach (($H['planets'] ?? []) as $pl) {
                $plHi = self::PLANET_HI[$pl] ?? $pl;
                $base = ['house' => $ctx['house'], 'rashi' => $ctx['rashi'], 'lord' => $ctx['lord'], 'planet' => $plHi];

                // (a) friendship of the planet with the rashi (house) lord
                if ($pl !== $lordEn) {
                    $rel = $rules['friend'][$pl][$lordEn] ?? null;
                    $key = ['F' => 'planet_friend_sign', 'E' => 'planet_enemy_sign', 'N' => 'planet_neutral_sign'][$rel] ?? null;
                    self::add($lines, $rules, $key, $base);
                }

                // (b) element reaction: planet element vs rashi element
                $pe = (string) ($rules['planet'][$pl]['element'] ?? '');
                $react = $rules['react'][$pe][$rashiElement] ?? null;
                if ($react !== null) {
                    $gb = (string) $react['gb'];
                    if ($gb === 'शुभ') {
                        self::add($lines, $rules, 'element_good', $base);
                    } elseif ($gb === 'अशुभ') {
                        self::add($lines, $rules, 'element_bad', $base);
                    }
                    // 'सम' (neutral) reaction — skipped gracefully.
                }

                // (c) does the planet work well / badly in this house?
                $nat = $rules['nature'][$pl] ?? null;
                if ($nat !== null) {
                    if (in_array($h, $nat['good'], true)) {
                        self::add($lines, $rules, 'planet_works_well', $base);
                    } elseif (in_array($h, $nat['bad'], true)) {
                        self::add($lines, $rules, 'planet_works_badly', $base);
                    }
                }
            }

            // --- Drishti on the house (shared drishti list, abbreviations) ---
            foreach (($H['drishti'] ?? []) as $ab) {
                $full = Drishti::FULL[$ab] ?? $ab;
                $plHi = self::PLANET_HI[$full] ?? $full;
                $base = ['house' => $ctx['house'], 'rashi' => $ctx['rashi'], 'lord' => $ctx['lord'], 'planet' => $plHi];
                if ($full === $lordEn) {
                    // The house's own lord aspects its own house — protective.
                    self::add($lines, $rules, 'lord_protects', $base);
                    continue;
                }
                $nature = (string) ($rules['planet'][$full]['nature'] ?? '');
                if ($nature === 'Benefic') {
                    self::add($lines, $rules, 'drishti_benefic', $base);
                } elseif ($nature === 'Malefic') {
                    self::add($lines, $rules, 'drishti_malefic', $base);
                }
            }

            // --- Where this house's lord sits (from the chart; Bhavesh Phal already has the text) ---
            $lordHouse = (int) ($planets[$lordEn]['house'] ?? 0);
            if ($lordHouse >= 1) {
                $key = in_array($lordHouse, self::TRIK, true) ? 'lord_in_difficult' : 'lord_in_good';
                self::add($lines, $rules, $key, ['house' => $ctx['house'], 'rashi' => $ctx['rashi'], 'lord' => $ctx['lord'], 'planet' => $ctx['lord']]);
            }

            $out[$h] = [
                'house' => $h,
                'rashi' => $rashiEn,
                'rashi_hi' => self::RASHI_HI[$rashiEn] ?? $rashiEn,
                'intro' => $intro,
                'lines' => $lines,
            ];
        }

        return $out;
    }

    /** Fill a template's placeholders and append; skip when the template is missing. */
    private static function add(array &$lines, array $rules, ?string $key, array $vars): void
    {
        if ($key === null) {
            return;
        }
        $tpl = $rules['tpl'][$key] ?? '';
        if ($tpl === '') {
            return;
        }
        $lines[] = strtr($tpl, [
            '{planet}' => $vars['planet'] ?? '',
            '{house}'  => $vars['house'] ?? '',
            '{rashi}'  => $vars['rashi'] ?? '',
            '{lord}'   => $vars['lord'] ?? '',
        ]);
    }
}
