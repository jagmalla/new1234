<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * Builds the computed "ग्रह स्थिति" block shown at the top of each planet tab
 * (above the untouched (A) Bhavesh Phal and (B) Graha-in-Bhava sections):
 * a status line (dignity + retrograde + verdict), a combustion line, one line
 * per companion in the same house (nature × mutual maitri, with named pair
 * yogas overriding the generic line), and a verdict chip. All facts come from
 * the shared {@see PlanetCondition} service; all wording from the editable
 * rule tables ({@see GrahaConditionRepository}).
 */
final class GrahaCondition
{
    private const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];
    private const RASHI_HI = [
        'मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या',
        'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुंभ', 'मीन',
    ];
    private const HOUSE_HI = [
        1 => 'प्रथम', 2 => 'द्वितीय', 3 => 'तृतीय', 4 => 'चतुर्थ', 5 => 'पंचम', 6 => 'षष्ठ',
        7 => 'सप्तम', 8 => 'अष्टम', 9 => 'नवम', 10 => 'दशम', 11 => 'एकादश', 12 => 'द्वादश',
    ];
    private const ORDER = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];

    /**
     * @param array<string,mixed> $chart computed chart
     * @param array<string,mixed> $rules GrahaConditionRepository::load()
     * @return array<string,array<string,mixed>> planet => block
     */
    public static function generate(array $chart, array $rules): array
    {
        $planets = $chart['planets'] ?? [];
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);

        $out = [];
        foreach (self::ORDER as $p) {
            if (isset($planets[$p])) {
                $out[$p] = self::one($p, $planets, $ascSign, $rules);
            }
        }
        return $out;
    }

    /**
     * @param array<string,array<string,mixed>> $planets
     * @param array<string,mixed> $rules
     * @return array<string,mixed>
     */
    private static function one(string $p, array $planets, int $ascSign, array $rules): array
    {
        $tpl = $rules['tpl'] ?? [];
        $cond = PlanetCondition::resolve($p, $planets, $ascSign);
        $hi = static fn(string $n): string => self::PLANET_HI[$n] ?? $n;

        $sign = (int) ($planets[$p]['sign_index'] ?? 0);
        $house = (int) ($planets[$p]['house'] ?? 0);

        // --- companion lines + companion score (each companion once) ---
        $lines = [];
        $companionScore = 0.0;
        $companions = PlanetCondition::companions($p, $planets);
        foreach ($companions as $c) {
            $pk = GrahaConditionRepository::pairKey($p, $c);
            $yoga = $rules['yogas'][$pk] ?? null;

            if ($yoga !== null) {
                // Named pair yoga overrides the generic matrix line for this pair.
                $combustNote = '';
                if (($yoga['key'] ?? '') === 'budhaditya') {
                    $merc = PlanetCondition::combustion('Mercury', $planets);
                    if ($merc !== null && isset($tpl['budhaditya_combust_note'])) {
                        $combustNote = strtr($tpl['budhaditya_combust_note'], ['{pct}' => (string) $merc['pct']]);
                    }
                }
                $text = strtr((string) $yoga['tpl'], [
                    '{planet}' => $hi($p), '{companion}' => $hi($c), '{combust_note}' => $combustNote,
                ]);
                $text = trim(preg_replace('/\s+/', ' ', $text));
                $lines[] = ['text' => $text, 'kind' => 'yoga', 'good' => ($yoga['good_bad'] ?? '') === 'शुभ', 'name' => $yoga['name'] ?? ''];
                $companionScore += (float) $yoga['score'];
                continue;
            }

            // Companion Sun with no named yoga: combustion already covers Sun's
            // influence — never print a generic line or double-count it.
            if ($c === 'Sun') {
                continue;
            }

            $benefic = PlanetCondition::isBenefic($c, $planets);
            $maitri = PlanetCondition::naisargikaMaitri($p, $c);
            $key = 'g_' . ($benefic ? 'ben' : 'mal') . '_' . $maitri;
            $m = $rules['matrix'][$key] ?? null;
            if ($m === null) {
                continue;
            }
            $text = strtr((string) $m['tpl'], ['{planet}' => $hi($p), '{companion}' => $hi($c)]);
            $lines[] = ['text' => $text, 'kind' => 'matrix', 'good' => ((float) $m['score']) >= 0, 'name' => ''];
            $companionScore += (float) $m['score'];
        }

        if ($companions === [] && isset($tpl['graha_alone'])) {
            $lines[] = ['text' => strtr($tpl['graha_alone'], ['{planet}' => $hi($p)]), 'kind' => 'alone', 'good' => null, 'name' => ''];
        }

        // --- score assembly (§4) ---
        $companionScore = max(-1.5, min(1.5, $companionScore));
        $combustScore = 0.0;
        if ($cond['combust'] !== null) {
            $factor = $p === 'Moon' ? 2.5 : 2.0;
            $combustScore = -$factor * ($cond['combust']['pct'] / 100.0);
        }
        $total = (float) $cond['dignity']['score'] + $combustScore + $companionScore;
        $verdict = self::verdict($total);

        // --- status line ---
        $status = isset($tpl['graha_status']) ? strtr($tpl['graha_status'], [
            '{planet}' => $hi($p),
            '{house}' => self::HOUSE_HI[$house] ?? (string) $house,
            '{rashi}' => self::RASHI_HI[$sign] ?? '',
            '{dignity_word}' => (string) $cond['dignity']['word'],
            '{retro}' => $cond['retro'] ? ' (वक्री)' : '',
            '{strength_word}' => $verdict['word'],
        ]) : '';

        // --- combustion line ---
        $combust = null;
        if ($cond['combust'] !== null) {
            $ct = $cond['combust'];
            $ckey = 'graha_combust_' . $ct['tier'];
            if (isset($tpl[$ckey])) {
                $combust = strtr($tpl[$ckey], [
                    '{planet}' => $hi($p),
                    '{sep}' => number_format($ct['sep'], 1),
                    '{pct}' => (string) $ct['pct'],
                    '{loss_text}' => (string) ($rules['loss'][$p] ?? ''),
                ]);
            }
        }

        return [
            'planet'   => $p,
            'status'   => $status,
            'combust'  => $combust,
            'lines'    => $lines,
            'verdict'  => $verdict,
            'dignity'  => $cond['dignity'],
            'score'    => round($total, 2),
        ];
    }

    /**
     * Verdict chip from the total score (§4 bands).
     * @return array{word:string,tier:string}
     */
    private static function verdict(float $total): array
    {
        if ($total >= 1.5) {
            return ['word' => 'विशेष शुभ', 'tier' => 'vshubh'];
        }
        if ($total >= 0.5) {
            return ['word' => 'शुभ', 'tier' => 'shubh'];
        }
        if ($total > -0.5) {
            return ['word' => 'मिश्रित', 'tier' => 'mishrit'];
        }
        if ($total > -1.5) {
            return ['word' => 'प्रतिकूल', 'tier' => 'pratikul'];
        }
        return ['word' => 'अत्यंत प्रतिकूल', 'tier' => 'ati'];
    }
}
