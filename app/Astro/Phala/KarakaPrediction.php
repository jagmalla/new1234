<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\Drishti;
use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * Karaka Prediction v2 (कारक फल). For each natural karaka this now computes a
 * transparent assessment block — the karaka's own dignity/combustion/companions
 * (KST + KCF/KCM/KCP + yuti rules, with classical specials overriding the
 * generic row) and कारको भावो नाशाय — then, for every house it judges, the
 * occupants and drishti of the house counted FROM the karaka (the missing inner
 * layer), and a computed Strong/Weak on each side (karaka side vs the House v2
 * lagna-side verdict) fed into the unchanged SS/SW/WS/WW sentences with an
 * explainable कारण line. All dignity/combustion/nature facts come from the
 * shared {@see PlanetCondition} service.
 */
final class KarakaPrediction
{
    /** Display order + the main house whose House-Prediction is paired in. */
    private const PAIR = [
        'Sun' => [9], 'Moon' => [4], 'Mars' => [3], 'Mercury' => [6],
        'Jupiter' => [5], 'Venus' => [7], 'Saturn' => [8, 12],
    ];
    /** कारको भावो नाशाय: the karaka's own signified house (Mercury excluded). */
    private const SIGNIFIED = ['Sun' => 9, 'Moon' => 4, 'Mars' => 3, 'Jupiter' => 5, 'Venus' => 7];
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
    /** Generic yuti class row per companion (benefics gated by classifier). */
    private const YUTI_CLASS = [
        'Jupiter' => 'k_with_jupiter', 'Venus' => 'k_with_venus', 'Mercury' => 'k_with_shubh_mercury',
        'Moon' => 'k_with_shubh_moon', 'Saturn' => 'k_with_saturn', 'Mars' => 'k_with_mars',
        'Rahu' => 'k_with_rahu', 'Ketu' => 'k_with_ketu',
    ];

    /**
     * @param array<string,mixed> $chart computed chart
     * @param array<string,mixed> $rules KarakaPredictionRepository::load()
     * @param array<int,float> $houseScores House v2 per-house verdict scores (lagna side)
     * @return list<array<string,mixed>>
     */
    public static function generate(array $chart, array $rules, array $houseScores = []): array
    {
        $houses = $chart['houses'] ?? [];
        $planets = $chart['planets'] ?? [];
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $sent = $rules['sent'] ?? [];
        $cfg = $rules['config'] ?? [];
        $useBhavo = (string) ($cfg['karaka_use_bhavo_nashaya'] ?? '1') === '1';
        $thr = (float) ($cfg['karaka_strong_threshold'] ?? 0.5);
        $occW = (float) ($cfg['karaka_occupant_weight'] ?? 0.5);

        $out = [];
        foreach (self::PAIR as $planet => $pairedHouses) {
            $map = $rules['map'][$planet] ?? null;
            if ($map === null || !isset($planets[$planet])) {
                continue;
            }
            $title = self::cleanTitle((string) $map['title']);
            $signifies = (string) $map['signifies'];
            $karakaHi = self::PLANET_HI[$planet] ?? $planet;
            $sign = (int) ($planets[$planet]['sign_index'] ?? 0);
            $deg = (float) ($planets[$planet]['deg_in_sign'] ?? 0.0);
            $karakaHouse = (int) ($planets[$planet]['house'] ?? 0);

            // ---- §2 karaka assessment: own score (dignity + combust + yuti) ----
            $dig = PlanetCondition::dignity($planet, $sign, $deg, $planets, $ascSign);
            $comb = PlanetCondition::combustion($planet, $planets);
            $ownScore = (float) $dig['score'];

            $combLine = null;
            if ($comb !== null) {
                $factor = $planet === 'Moon' ? 2.5 : 2.0;
                $ownScore -= $factor * ($comb['pct'] / 100.0);
                $ckey = ['full' => 'KCF', 'moderate' => 'KCM', 'partial' => 'KCP'][$comb['tier']] ?? 'KCP';
                $combLine = self::fill($sent, $ckey, [
                    '{karaka}' => $karakaHi, '{sep}' => number_format((float) $comb['sep'], 1),
                    '{pct}' => (string) $comb['pct'], '{loss_text}' => (string) ($rules['loss'][$planet] ?? ''),
                ]);
            }

            $yutiLines = [];
            $yutiScore = 0.0;
            foreach (self::companions($planet, $planets) as $c) {
                if ($c === 'Sun') {
                    continue; // Sun's effect is the combustion line — no double count
                }
                $row = self::yutiRow($planet, $c, $rules, $planets);
                if ($row !== null) {
                    $yutiLines[] = [
                        'text' => strtr($row['tpl'], ['{karaka}' => $karakaHi, '{signifies}' => $signifies]),
                        'good' => ($row['gb'] ?? '') === 'शुभ',
                    ];
                    $yutiScore += (float) $row['score'];
                }
            }
            $ownScore += max(-1.5, min(1.0, $yutiScore));
            $ownVerdict = self::strength($ownScore);

            // KST status line (strength word from own score).
            $status = self::fill($sent, 'KST', [
                '{karaka}' => $karakaHi, '{house}' => self::HOUSE_HI[$karakaHouse] ?? (string) $karakaHouse,
                '{rashi}' => self::RASHI_HI[$sign] ?? '', '{dignity_word}' => (string) $dig['word'],
                '{retro}' => !empty($planets[$planet]['retro']) ? ' (वक्री)' : '',
                '{signifies}' => $signifies, '{strength_word}' => $ownVerdict['word'] . ' है',
            ]);
            if (!empty($dig['neecha_bhanga'])) {
                $status .= ' (नीच भंग: ' . (string) $dig['reason'] . ')';
            }

            // §2.4 karako bhavo nashaya.
            $bhavoNashaya = null;
            if ($useBhavo) {
                if ($planet === 'Saturn' && $karakaHouse === 8) {
                    $bhavoNashaya = self::fill($sent, 'KBN_SAT8', ['{karaka}' => $karakaHi]);
                } elseif (isset(self::SIGNIFIED[$planet]) && $karakaHouse === self::SIGNIFIED[$planet]) {
                    $bhavoNashaya = self::fill($sent, 'KBN', [
                        '{karaka}' => $karakaHi, '{house}' => self::HOUSE_HI[$karakaHouse] ?? (string) $karakaHouse,
                    ]);
                }
            }

            // ---- §3/§4 per judged house: occupants/drishti + computed S/W ----
            $lines = [];
            $primaryTag = null;
            foreach ($map['houses'] as $n) {
                $mean = $rules['meaning'][$planet][$n] ?? null;
                if ($mean === null) {
                    continue;
                }
                $innerMeaning = (string) $mean['meaning'];
                // Target = the house N signs from the karaka's occupied house.
                $karakaSignIdx = ($sign + $n - 1) % 12;
                $target = (($karakaSignIdx - $ascSign + 12) % 12) + 1;
                $TH = $houses[$target] ?? [];

                // occupants of target (excluding the karaka itself)
                $occLines = [];
                $occScore = 0.0;
                foreach (($TH['planets'] ?? []) as $op) {
                    if ($op === $planet) {
                        continue;
                    }
                    $ben = PlanetCondition::isBenefic((string) $op, $planets);
                    $occLines[] = [
                        'text' => self::fill($sent, $ben ? 'KOB' : 'KOM', [
                            '{karaka}' => $karakaHi, '{n}' => (string) $n,
                            '{planet}' => self::PLANET_HI[$op] ?? $op, '{inner_meaning}' => $innerMeaning,
                        ]),
                        'good' => $ben,
                    ];
                    $occScore += $ben ? 0.5 : (in_array($op, ['Rahu', 'Ketu'], true) ? -0.75 : -0.5);
                }

                // drishti on target — net stronger side, printed once.
                [$drishtiLine, $drishtiScore] = self::targetDrishti($TH, $planet, $n, $karakaHi, $planets, $sent);

                // computed sides
                $karakaSide = $ownScore + $occW * ($occScore + $drishtiScore);
                $lagnaSide = (float) ($houseScores[$n] ?? 0.0);
                $ks = self::sw($karakaSide, $thr);
                $ls = self::sw($lagnaSide, $thr);
                $key = $ls . $ks;
                if (in_array($n, $pairedHouses, true) && $primaryTag === null) {
                    $primaryTag = $key;
                }

                $sentence = self::fill($sent, $key, [
                    '{title}' => $title, '{house}' => self::HOUSE_HI[$n] ?? (string) $n,
                    '{karaka}' => $karakaHi, '{karaka_meaning}' => $innerMeaning,
                    '{house_significance}' => (string) $mean['lagna'],
                ]);
                $reason = sprintf(
                    'कारण — लग्न पक्ष: %s (भाव %s का स्कोर %s); कारक पक्ष: %s (कारक %s%s%s)।',
                    $ls === 'S' ? 'बलवान' : 'निर्बल', self::HOUSE_HI[$n] ?? (string) $n, self::num($lagnaSide),
                    $ks === 'S' ? 'बलवान' : 'निर्बल', (string) $dig['word'],
                    $comb !== null ? ', अस्त ' . $comb['pct'] . '%' : '',
                    $occLines !== [] ? ', ' . self::HOUSE_HI[$target] . ' भाव में ' . count($occLines) . ' ग्रह' : ''
                );

                $lines[] = [
                    'house' => $n, 'occupants' => $occLines, 'drishti' => $drishtiLine,
                    'sentence' => $sentence, 'reason' => $reason,
                ];
            }

            $out[] = [
                'planet' => $planet,
                'title' => $title,
                'paired_houses' => $pairedHouses,
                'signifies' => $signifies,
                'assess' => [
                    'status' => $status,
                    'combust' => $combLine,
                    'yuti' => $yutiLines,
                    'bhavo_nashaya' => $bhavoNashaya,
                    'verdict' => $ownVerdict,
                ],
                'karaka_lines' => $lines,
                'combined' => self::combined($title, $primaryTag ?? 'SS', $ownScore),
            ];
        }

        return $out;
    }

    /**
     * Pick the yuti row for (karaka, companion): a classical special overrides
     * the generic class row (never both); benefic classes gated by the shared
     * benefic classifier.
     *
     * @param array<string,mixed> $rules
     * @param array<string,array<string,mixed>> $planets
     * @return array{tpl:string,gb:string,score:float}|null
     */
    private static function yutiRow(string $karaka, string $c, array $rules, array $planets): ?array
    {
        $special = $rules['yuti_special'][$karaka . '|' . $c] ?? null;
        if ($special !== null) {
            return $special;
        }
        $key = self::YUTI_CLASS[$c] ?? null;
        if ($key === null) {
            return null;
        }
        // Benefic-class rows apply only when the companion actually classifies benefic.
        if (($c === 'Mercury' || $c === 'Moon') && !PlanetCondition::isBenefic($c, $planets)) {
            return null;
        }
        return $rules['yuti_generic'][$key] ?? null;
    }

    /**
     * Net drishti on the target house: print the stronger side once.
     * @return array{0:?string,1:float}
     */
    private static function targetDrishti(array $TH, string $karaka, int $n, string $karakaHi, array $planets, array $sent): array
    {
        $benList = [];
        $malList = [];
        foreach (($TH['drishti'] ?? []) as $ab) {
            $full = Drishti::FULL[$ab] ?? $ab;
            if (PlanetCondition::isBenefic((string) $full, $planets)) {
                $benList[] = $full;
            } else {
                $malList[] = $full;
            }
        }
        if (count($benList) > count($malList) && $benList !== []) {
            return [self::fill($sent, 'KDB', ['{karaka}' => $karakaHi, '{n}' => (string) $n, '{planet}' => self::PLANET_HI[$benList[0]] ?? $benList[0]]), 0.25];
        }
        if (count($malList) > count($benList) && $malList !== []) {
            return [self::fill($sent, 'KDM', ['{karaka}' => $karakaHi, '{n}' => (string) $n, '{planet}' => self::PLANET_HI[$malList[0]] ?? $malList[0]]), -0.25];
        }
        return [null, 0.0];
    }

    /** @return list<string> */
    private static function companions(string $planet, array $planets): array
    {
        $house = (int) ($planets[$planet]['house'] ?? 0);
        $out = [];
        foreach ($planets as $name => $p) {
            if ($name !== $planet && (int) ($p['house'] ?? -1) === $house) {
                $out[] = (string) $name;
            }
        }
        return $out;
    }

    /** Strong/Weak from a side score: S if ≥ +thr, W if ≤ −thr, else sign decides. */
    private static function sw(float $score, float $thr): string
    {
        if ($score >= $thr) {
            return 'S';
        }
        if ($score <= -$thr) {
            return 'W';
        }
        return $score >= 0 ? 'S' : 'W';
    }

    /** @return array{word:string,tier:string} */
    private static function strength(float $s): array
    {
        if ($s >= 1.5) {
            return ['word' => 'अत्यंत बलवान', 'tier' => 'vshubh'];
        }
        if ($s >= 0.5) {
            return ['word' => 'बलवान', 'tier' => 'shubh'];
        }
        if ($s > -0.5) {
            return ['word' => 'सामान्य', 'tier' => 'mishrit'];
        }
        if ($s > -1.5) {
            return ['word' => 'निर्बल', 'tier' => 'pratikul'];
        }
        return ['word' => 'अत्यंत निर्बल', 'tier' => 'ati'];
    }

    private static function fill(array $sent, string $key, array $vars): string
    {
        $tpl = $sent[$key] ?? '';
        return $tpl === '' ? '' : strtr($tpl, $vars);
    }

    private static function num(float $v): string
    {
        return number_format($v, 1);
    }

    /** समग्र strip — शुभ/संतुलित/अशुभ now from the computed karaka score. */
    private static function combined(string $title, string $tag, float $ownScore): string
    {
        $word = $ownScore > 0.5 ? 'शुभ' : ($ownScore < -0.5 ? 'अशुभ' : 'संतुलित');
        return match ($tag) {
            'SS' => 'समग्र रूप से, ' . $title . ' का यह क्षेत्र बाहर और भीतर — दोनों दृष्टियों से शुभ, बलवान तथा संतुलित है। (कारक-बल: ' . $word . ')',
            'SW' => 'समग्र रूप से, बाहरी रूप से यह विषय प्राप्त तो है, परन्तु इसका वास्तविक आंतरिक सुख अपेक्षाकृत कम अनुभव होता है — ' . $title . '। (कारक-बल: ' . $word . ')',
            'WS' => 'समग्र रूप से, बाहरी परिस्थिति सीमित होते हुए भी भीतर संतोष व अनुभूति बनी रहती है — ' . $title . '। (कारक-बल: ' . $word . ')',
            default => 'समग्र रूप से, ' . $title . ' के इस क्षेत्र में बाहरी और आंतरिक — दोनों पक्षों को सुदृढ़ करने की आवश्यकता है; सावधानी व उपाय लाभकारी रहेंगे। (कारक-बल: ' . $word . ')',
        };
    }

    /** "शुक्र: विवाह / जीवनसाथी (Venus: Marriage / Spouse)" -> "शुक्र: विवाह / जीवनसाथी" */
    private static function cleanTitle(string $t): string
    {
        return trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $t));
    }
}
