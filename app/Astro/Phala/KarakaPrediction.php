<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\Drishti;

/**
 * Karaka Prediction — every house is judged twice: from the Lagna (outer event)
 * and from its natural KARAKA planet (inner experience). For each karaka this
 * counts the judged house FROM the karaka's own position, scores that house with
 * the engine's house-strength / occupant / aspect data, compares it to the same
 * house from the Lagna, and phrases the result with the Tab-3 comparison
 * templates. Presented paired with the House Prediction of the karaka's main
 * house (Sun↔9th father, Moon↔4th mother, … Venus↔7th marriage).
 */
final class KarakaPrediction
{
    /** Display order + the main house whose House-Prediction is paired in. */
    private const PAIR = [
        'Sun' => [9], 'Moon' => [4], 'Mars' => [3], 'Mercury' => [6],
        'Jupiter' => [5], 'Venus' => [7], 'Saturn' => [8, 12],
    ];
    private const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];
    private const HOUSE_HI = [
        1 => 'प्रथम', 2 => 'द्वितीय', 3 => 'तृतीय', 4 => 'चतुर्थ', 5 => 'पंचम', 6 => 'षष्ठ',
        7 => 'सप्तम', 8 => 'अष्टम', 9 => 'नवम', 10 => 'दशम', 11 => 'एकादश', 12 => 'द्वादश',
    ];
    private const BENEFIC = ['Jupiter', 'Venus', 'Mercury', 'Moon'];

    /**
     * @param array<string,mixed> $chart computed chart
     * @param array<string,mixed> $rules KarakaPredictionRepository::load()
     * @return list<array{planet:string,title:string,paired_houses:list<int>,signifies:string,karaka_lines:list<array{house:int,sentence:string}>,combined:string}>
     */
    public static function generate(array $chart, array $rules): array
    {
        $houses = $chart['houses'] ?? [];
        $planets = $chart['planets'] ?? [];
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);

        // Pre-score all 12 lagna-houses once.
        $bbAvg = 0.0;
        for ($i = 1; $i <= 12; $i++) {
            $bbAvg += (float) ($houses[$i]['bb_virupa'] ?? 0);
        }
        $bbAvg /= 12.0;
        $score = [];
        for ($i = 1; $i <= 12; $i++) {
            $score[$i] = self::houseScore($houses[$i] ?? [], $bbAvg);
        }

        $out = [];
        foreach (self::PAIR as $planet => $pairedHouses) {
            $map = $rules['map'][$planet] ?? null;
            if ($map === null || !isset($planets[$planet])) {
                continue;
            }
            $title = self::cleanTitle((string) $map['title']);
            $karakaSign = (int) ($planets[$planet]['sign_index'] ?? 0);

            $lines = [];
            $primaryTag = null;
            foreach ($map['houses'] as $n) {
                $mean = $rules['meaning'][$planet][$n] ?? null;
                if ($mean === null) {
                    continue; // no interpretation text — skip gracefully
                }
                // house n from the Lagna, and n-th from the karaka (as a lagna-house)
                $lagnaHouse = $n;
                $karakaSignIdx = ($karakaSign + $n - 1) % 12;
                $karakaHouse = (($karakaSignIdx - $ascSign + 12) % 12) + 1;

                $ls = $score[$lagnaHouse] >= 0;
                $ks = $score[$karakaHouse] >= 0;
                $key = ($ls ? 'S' : 'W') . ($ks ? 'S' : 'W');
                if (in_array($n, $pairedHouses, true) && $primaryTag === null) {
                    $primaryTag = $key;
                }

                $tpl = $rules['sent'][$key] ?? '';
                if ($tpl === '') {
                    continue;
                }
                $lines[] = [
                    'house' => $n,
                    'sentence' => strtr($tpl, [
                        '{title}' => $title,
                        '{house}' => self::HOUSE_HI[$n] ?? (string) $n,
                        '{karaka}' => self::PLANET_HI[$planet] ?? $planet,
                        '{karaka_meaning}' => (string) $mean['meaning'],
                        '{house_significance}' => (string) $mean['lagna'],
                    ]),
                ];
            }

            $out[] = [
                'planet' => $planet,
                'title' => $title,
                'paired_houses' => $pairedHouses,
                'signifies' => (string) $map['signifies'],
                'karaka_lines' => $lines,
                'combined' => self::combined($title, $primaryTag ?? 'SS'),
            ];
        }

        return $out;
    }

    /** Composite house strength: Bhava Bala vs average + benefic/malefic occupants & aspects. */
    private static function houseScore(array $H, float $bbAvg): float
    {
        $s = ((float) ($H['bb_virupa'] ?? $bbAvg) - $bbAvg) * 0.04;
        foreach (($H['planets'] ?? []) as $p) {
            $s += in_array($p, self::BENEFIC, true) ? 1.5 : -1.5;
        }
        foreach (($H['drishti'] ?? []) as $ab) {
            $full = Drishti::FULL[$ab] ?? $ab;
            $s += in_array($full, self::BENEFIC, true) ? 0.7 : -0.7;
        }
        return $s;
    }

    /** Software synthesis line combining the Lagna (outer) + karaka (inner) verdict. */
    private static function combined(string $title, string $tag): string
    {
        return match ($tag) {
            'SS' => 'समग्र रूप से, ' . $title . ' का यह क्षेत्र बाहर और भीतर — दोनों दृष्टियों से शुभ, बलवान तथा संतुलित है।',
            'SW' => 'समग्र रूप से, बाहरी रूप से यह विषय प्राप्त तो है, परन्तु इसका वास्तविक आंतरिक सुख अपेक्षाकृत कम अनुभव होता है — ' . $title . '।',
            'WS' => 'समग्र रूप से, बाहरी परिस्थिति सीमित होते हुए भी भीतर संतोष व अनुभूति बनी रहती है — ' . $title . '।',
            default => 'समग्र रूप से, ' . $title . ' के इस क्षेत्र में बाहरी और आंतरिक — दोनों पक्षों को सुदृढ़ करने की आवश्यकता है; सावधानी व उपाय लाभकारी रहेंगे।',
        };
    }

    /** "शुक्र: विवाह / जीवनसाथी (Venus: Marriage / Spouse)" -> "शुक्र: विवाह / जीवनसाथी" */
    private static function cleanTitle(string $t): string
    {
        return trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $t));
    }
}
