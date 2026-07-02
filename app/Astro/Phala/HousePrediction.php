<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\Drishti;
use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * House Prediction v2 (नियम-आधारित फलादेश). For each house it emits, in a fixed
 * order: the intro (sign/quality/element/lord), ONE composite line per occupying
 * planet (dignity + combustion + yuti + house-suitability, closed by a single
 * verdict — never contradictory bullets), a house-level two-malefics line,
 * drishti lines, the lord-placement line (with the lord's own combustion/
 * debilitation folded in), and a house verdict chip. Dignity/combustion/maitri
 * all come from the shared {@see PlanetCondition} service; wording from the
 * editable rule tables. Element-reaction lines are OFF unless config enables
 * them, and never act as a verdict.
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
    /** Dignity tier -> template key. */
    private const DIG_TPL = [
        'param_uchcha' => 'dignity_exalted', 'exalt' => 'dignity_exalted',
        'moolatrikona' => 'dignity_moolatrikona', 'own' => 'dignity_own',
        'debil' => 'dignity_debilitated', 'debil_bhanga' => 'dignity_debilitated',
        'great_friend' => 'compound_adhimitra', 'friend' => 'planet_friend_sign',
        'neutral' => 'planet_neutral_sign', 'enemy' => 'planet_enemy_sign',
        'great_enemy' => 'compound_adhishatru',
    ];

    /**
     * @param array<string,mixed> $chart  computed chart (needs houses + planets)
     * @param array<string,mixed> $rules  loaded rule tables (HousePredictionRepository::load)
     * @return array<int,array{house:int,rashi:string,rashi_hi:string,intro:string,lines:list<string>,chip:array{word:string,tier:string}}>
     */
    public static function generate(array $chart, array $rules): array
    {
        // Feed the owner-editable dignity/orb bands into the shared service.
        PlanetCondition::configure(
            !empty($rules['dignity']) ? $rules['dignity'] : null,
            !empty($rules['orbs']) ? $rules['orbs'] : null,
            isset($rules['config']['moon_amavasya_orb']) ? (float) $rules['config']['moon_amavasya_orb'] : null
        );

        $out = [];
        $houses = $chart['houses'] ?? [];
        $planets = $chart['planets'] ?? [];
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $useElement = (string) ($rules['config']['use_element_reaction'] ?? '0') === '1';

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
            $houseHi = self::HOUSE_HI[$h] ?? (string) $h;
            $rashiHi = self::RASHI_HI[$rashiEn] ?? $rashiEn;
            $lordHi = self::PLANET_HI[$lordEn] ?? $lordEn;

            $intro = sprintf(
                '%s भाव में %s राशि है — यह %s स्वभाव तथा %s तत्व की राशि है, और इसका स्वामी %s है।',
                $houseHi, $rashiHi, $qualityHi, $elementHi, $lordHi
            );

            $lines = [];
            $score = 0.0;
            $occupants = $H['planets'] ?? [];

            // (2) ONE composite line per occupying planet.
            foreach ($occupants as $pl) {
                [$line, $s] = self::planetLine($pl, $h, $houseHi, $rashiHi, $planets, $ascSign, $rules, $useElement, $rashiElement);
                if ($line !== '') {
                    $lines[] = $line;
                    $score += $s;
                }
            }

            // (3) House-level two-malefics line (once).
            $malefics = 0;
            foreach ($occupants as $pl) {
                if (!PlanetCondition::isBenefic($pl, $planets)) {
                    $malefics++;
                }
            }
            if ($malefics >= 2 && isset($rules['yuti']['two_malefics_in_house'])) {
                $lines[] = strtr($rules['yuti']['two_malefics_in_house']['tpl'], ['{house}' => $houseHi]);
                $score += (float) $rules['yuti']['two_malefics_in_house']['score'];
            }

            // (4) Drishti lines (kept).
            foreach (($H['drishti'] ?? []) as $ab) {
                $full = Drishti::FULL[$ab] ?? $ab;
                $plHi = self::PLANET_HI[$full] ?? $full;
                $base = ['{planet}' => $plHi, '{house}' => $houseHi, '{rashi}' => $rashiHi, '{lord}' => $lordHi];
                if ($full === $lordEn) {
                    self::push($lines, $rules, 'lord_protects', $base);
                    $score += 0.4;
                    continue;
                }
                $nature = (string) ($rules['planet'][$full]['nature'] ?? '');
                if ($nature === 'Benefic') {
                    self::push($lines, $rules, 'drishti_benefic', $base);
                    $score += 0.4;
                } elseif ($nature === 'Malefic') {
                    self::push($lines, $rules, 'drishti_malefic', $base);
                    $score -= 0.4;
                }
            }

            // (5) Lord-placement line (kept) + the lord's own combustion/debilitation.
            $lordHouse = (int) ($planets[$lordEn]['house'] ?? 0);
            if ($lordHouse >= 1) {
                $key = in_array($lordHouse, self::TRIK, true) ? 'lord_in_difficult' : 'lord_in_good';
                $lordLine = self::fill($rules, $key, ['{planet}' => $lordHi, '{house}' => $houseHi, '{rashi}' => $rashiHi, '{lord}' => $lordHi]);
                $lordLine .= self::lordConditionSuffix($lordEn, $planets, $ascSign);
                if ($lordLine !== '') {
                    $lines[] = $lordLine;
                }
                $score += in_array($lordHouse, self::TRIK, true) ? -0.4 : 0.4;
            }

            $out[$h] = [
                'house' => $h,
                'rashi' => $rashiEn,
                'rashi_hi' => $rashiHi,
                'intro' => $intro,
                'lines' => $lines,
                'chip' => self::houseChip($score),
            ];
        }

        return $out;
    }

    /**
     * Build one composite line for a planet in a house: dignity + combustion +
     * yuti + house-suitability, closed by a single verdict word.
     *
     * @param array<string,array<string,mixed>> $planets
     * @param array<string,mixed> $rules
     * @return array{0:string,1:float}
     */
    private static function planetLine(string $pl, int $h, string $houseHi, string $rashiHi, array $planets, int $ascSign, array $rules, bool $useElement, string $rashiElement): array
    {
        $plHi = self::PLANET_HI[$pl] ?? $pl;
        $sign = (int) ($planets[$pl]['sign_index'] ?? 0);
        $deg = (float) ($planets[$pl]['deg_in_sign'] ?? 0.0);
        $clauses = [];
        $score = 0.0;

        // (a) dignity
        $dig = PlanetCondition::dignity($pl, $sign, $deg, $planets, $ascSign);
        $score += $dig['score'];
        $digKey = self::DIG_TPL[$dig['tier']] ?? 'planet_neutral_sign';
        $clauses[] = self::fill($rules, $digKey, [
            '{planet}' => $plHi, '{house}' => $houseHi, '{rashi}' => $rashiHi, '{deep}' => $dig['deep'] ?? '',
        ]);
        if (!empty($dig['neecha_bhanga'])) {
            $clauses[] = self::fill($rules, 'dignity_neecha_bhanga', ['{reason}' => $dig['reason'] ?? '']);
        }

        // (b) combustion
        $comb = PlanetCondition::combustion($pl, $planets);
        if ($comb !== null) {
            $factor = $pl === 'Moon' ? 2.5 : 2.0;
            $score -= $factor * ($comb['pct'] / 100.0);
            $clauses[] = self::fill($rules, 'combust_' . $comb['tier'], [
                '{planet}' => $plHi, '{sep}' => number_format((float) $comb['sep'], 1), '{pct}' => (string) $comb['pct'],
            ]);
            if (!empty($comb['amavasya'])) {
                $clauses[] = self::fill($rules, 'moon_amavasya', ['{planet}' => $plHi]);
            }
        }

        // (c) yuti (same-house companions). Sun handled by combustion — never a yuti line.
        $yutiScore = 0.0;
        $house = (int) ($planets[$pl]['house'] ?? 0);
        $plBenefic = PlanetCondition::isBenefic($pl, $planets);
        foreach ($planets as $c => $cp) {
            if ($c === $pl || $c === 'Sun' || (int) ($cp['house'] ?? -1) !== $house) {
                continue;
            }
            $cBenefic = PlanetCondition::isBenefic((string) $c, $planets);
            $cHi = self::PLANET_HI[$c] ?? $c;
            if ($plBenefic && !$cBenefic) {
                $key = null;
                if ($pl === 'Moon' && ($c === 'Rahu' || $c === 'Ketu')) {
                    $key = 'moon_with_' . strtolower((string) $c);          // grahan precedence
                } elseif (in_array($c, ['Rahu', 'Ketu', 'Saturn', 'Mars'], true)) {
                    $key = 'benefic_with_' . strtolower((string) $c);
                }
                if ($key !== null && isset($rules['yuti'][$key])) {
                    $clauses[] = strtr($rules['yuti'][$key]['tpl'], ['{planet}' => $plHi, '{benefic}' => $cHi]);
                    $yutiScore += (float) $rules['yuti'][$key]['score'];
                }
            } elseif (!$plBenefic && $cBenefic && isset($rules['yuti']['malefic_with_benefic'])) {
                $clauses[] = strtr($rules['yuti']['malefic_with_benefic']['tpl'], ['{planet}' => $plHi, '{benefic}' => $cHi]);
                $yutiScore += (float) $rules['yuti']['malefic_with_benefic']['score'];
            }
        }
        $score += max(-1.5, min(1.0, $yutiScore));

        // (d) house-suitability (planet_house_nature) — score + a short bracketed tag.
        $nat = $rules['nature'][$pl] ?? null;
        if ($nat !== null) {
            if (in_array($h, $nat['good'], true)) {
                $score += 0.75;
                $clauses[] = '(यह भाव ' . $plHi . ' के लिए स्वाभाविक रूप से अनुकूल है।)';
            } elseif (in_array($h, $nat['bad'], true)) {
                $score -= 0.75;
                $clauses[] = '(यह भाव ' . $plHi . ' के लिए कुछ प्रतिकूल है।)';
            }
        }

        // (optional) element flavor — ONLY when enabled; never a verdict.
        if ($useElement) {
            $pe = (string) ($rules['planet'][$pl]['element'] ?? '');
            $react = $rules['react'][$pe][$rashiElement] ?? null;
            if ($react !== null) {
                $clauses[] = self::fill($rules, 'element_flavor', [
                    '{planet}' => $plHi, '{rashi}' => $rashiHi,
                    '{p_element}' => self::ELEMENT_HI[$pe] ?? $pe,
                    '{r_element}' => self::ELEMENT_HI[$rashiElement] ?? $rashiElement,
                    '{reaction}' => (string) $react['gb'],
                ]);
            }
        }

        $clauses = array_values(array_filter($clauses, static fn($c) => $c !== ''));
        $text = implode(' ', $clauses) . ' — कुल मिलाकर ' . self::verdict($score) . '।';
        return [trim($text), $score];
    }

    /** "…और वह स्वयं नीच/अस्त है…" appended to the lord line when the lord is weak. */
    private static function lordConditionSuffix(string $lord, array $planets, int $ascSign): string
    {
        if (!isset($planets[$lord])) {
            return '';
        }
        $notes = [];
        $dig = PlanetCondition::dignity($lord, (int) $planets[$lord]['sign_index'], (float) $planets[$lord]['deg_in_sign'], $planets, $ascSign);
        if ($dig['tier'] === 'debil') {
            $notes[] = 'नीच';
        }
        if (PlanetCondition::combustion($lord, $planets) !== null) {
            $notes[] = 'अस्त';
        }
        if ($notes === []) {
            return '';
        }
        return ' और वह स्वयं ' . implode('/', $notes) . ' है, जिससे भाव और दुर्बल होता है।';
    }

    /** @return array{word:string,tier:string} */
    private static function houseChip(float $score): array
    {
        if ($score > 0.5) {
            return ['word' => 'शुभ', 'tier' => 'shubh'];
        }
        if ($score < -0.5) {
            return ['word' => 'अशुभ', 'tier' => 'ashubh'];
        }
        return ['word' => 'मिश्रित', 'tier' => 'mishrit'];
    }

    /** Verdict word for a single planet line (§3 bands). */
    private static function verdict(float $s): string
    {
        if ($s >= 1.5) {
            return 'विशेष शुभ';
        }
        if ($s >= 0.5) {
            return 'शुभ';
        }
        if ($s > -0.5) {
            return 'मिश्रित/सामान्य';
        }
        if ($s > -1.5) {
            return 'प्रतिकूल';
        }
        return 'अत्यंत प्रतिकूल';
    }

    /** Fill a template's placeholders; '' when the template is missing. */
    private static function fill(array $rules, string $key, array $vars): string
    {
        $tpl = $rules['tpl'][$key] ?? '';
        return $tpl === '' ? '' : strtr($tpl, $vars);
    }

    /** Fill + append (skips missing templates). */
    private static function push(array &$lines, array $rules, string $key, array $vars): void
    {
        $s = self::fill($rules, $key, $vars);
        if ($s !== '') {
            $lines[] = $s;
        }
    }
}
