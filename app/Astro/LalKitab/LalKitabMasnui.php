<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\LalKitab;

/**
 * मसनूई (कृत्रिम) ग्रह — Lal Kitab's manufactured planets.
 *
 * When two planets share a house they stop acting as themselves and together
 * behave like a third planet. The teva is then read for that third planet
 * sitting in that house — but the two originals are not erased: the manufactured
 * planet is a *dominant additional layer* over them, which is why the remedy
 * never targets the manufactured planet and always adjusts the two sources.
 *
 * Four things decide what a formation actually does:
 *
 *   1. Placement — read the phal of the manufactured planet in that house.
 *   2. Dignity   — is it उच्च / नीच / पक्का / कच्चा *there*? (House-based; the
 *                  teva's signs never move.) सूर्य+शुक्र make a मसनूई गुरु which
 *                  is superb in the 5th (गुरु's pakka ghar) and poor in the 10th
 *                  (गुरु's neech).
 *   3. Clash     — if the real planet of the same kind sits elsewhere, the two
 *                  meet as equals ("दो गुरुओं की लड़ाई") and the stronger दृष्टि
 *                  decides.
 *   4. Timing    — the formation bites hardest during the two source planets'
 *                  own stretches of the 35-year cycle.
 *
 * Remedies follow one rule of thumb: to build a benefic, combine the two
 * sources' articles; to break a malefic, separate them, or bring in a third
 * planet that is a friend to both and hostile to neither.
 */
final class LalKitabMasnui
{
    private const PLANET_ORDER = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];

    /**
     * The eleven pairs, keyed by the two source planets sorted alphabetically.
     *
     *   makes    — the manufactured planet
     *   read     — whose phal to actually read (differs only for सूर्य+शनि)
     *   inherent — the standing the pair itself carries, before the house is
     *              looked at ('उच्च' | 'नीच' | 'बद' | null)
     *   flavour  — a second planet whose nature colours the result
     *   note     — what the sheet says in words
     *
     * @var array<string,array<string,mixed>>
     */
    public const PAIRS = [
        'Mercury|Venus' => ['makes' => 'Sun', 'read' => 'Sun', 'inherent' => null, 'flavour' => null,
            'note' => 'बुध व शुक्र मिलकर मसनूई सूर्य बनाते हैं।'],
        'Mercury|Sun'   => ['makes' => 'Mars', 'read' => 'Mars', 'inherent' => null, 'flavour' => null,
            'note' => 'सूर्य व बुध मिलकर मसनूई मंगल बनाते हैं।'],
        'Sun|Venus'     => ['makes' => 'Jupiter', 'read' => 'Jupiter', 'inherent' => null, 'flavour' => null,
            'note' => 'सूर्य व शुक्र मिलकर मसनूई गुरु बनाते हैं।'],
        'Jupiter|Rahu'  => ['makes' => 'Mercury', 'read' => 'Mercury', 'inherent' => null, 'flavour' => null,
            'note' => 'गुरु व राहु मिलकर मसनूई बुध बनाते हैं — यही गुरु-चांडाल की जोड़ी भी है।'],
        'Ketu|Rahu'     => ['makes' => 'Venus', 'read' => 'Venus', 'inherent' => null, 'flavour' => null,
            'note' => 'राहु व केतु मिलकर मसनूई शुक्र बनाते हैं।'],
        'Jupiter|Venus' => ['makes' => 'Saturn', 'read' => 'Saturn', 'inherent' => null, 'flavour' => 'Ketu',
            'note' => 'शुक्र व गुरु मिलकर मसनूई शनि बनाते हैं, जिसका स्वभाव केतु जैसा रहता है।'],
        'Mars|Mercury'  => ['makes' => 'Saturn', 'read' => 'Saturn', 'inherent' => null, 'flavour' => 'Rahu',
            'note' => 'मंगल व बुध मिलकर मसनूई शनि बनाते हैं, जिसका स्वभाव राहु जैसा रहता है।'],
        'Mars|Saturn'   => ['makes' => 'Rahu', 'read' => 'Rahu', 'inherent' => 'उच्च', 'flavour' => null,
            'note' => 'मंगल व शनि मिलकर उच्च का मसनूई राहु बनाते हैं — भाव 8 की सांझी गद्दी वाली जोड़ी।'],
        'Saturn|Venus'  => ['makes' => 'Ketu', 'read' => 'Ketu', 'inherent' => 'उच्च', 'flavour' => null,
            'note' => 'शनि व शुक्र मिलकर उच्च का मसनूई केतु बनाते हैं।'],
        'Moon|Saturn'   => ['makes' => 'Ketu', 'read' => 'Ketu', 'inherent' => 'नीच', 'flavour' => null,
            'note' => 'चन्द्र व शनि मिलकर नीच का मसनूई केतु बनाते हैं।'],
        'Saturn|Sun'    => ['makes' => 'Mars', 'read' => 'Rahu', 'inherent' => 'बद', 'flavour' => null,
            'note' => 'सूर्य व शनि मिलकर मसनूई "मंगल बद" बनाते हैं — पर फलादेश नीच राहु जैसा पढ़ा जाता है।'],
    ];

    /** @return string the catalog key for two planets, order-independent */
    public static function key(string $a, string $b): string
    {
        $p = [$a, $b];
        sort($p);
        return implode('|', $p);
    }

    /**
     * Every मसनूई formation in the chart. A house holding three planets yields
     * one entry per qualifying pair.
     *
     * @param array<int,list<string>> $occupants  house => planet keys
     * @return list<array<string,mixed>>
     */
    public static function detect(array $occupants): array
    {
        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            $ps = array_values(array_intersect(self::PLANET_ORDER, $occupants[$h] ?? []));
            $n = count($ps);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $k = self::key($ps[$i], $ps[$j]);
                    if (!isset(self::PAIRS[$k])) {
                        continue;
                    }
                    $out[] = ['house' => $h, 'pair' => [$ps[$i], $ps[$j]], 'key' => $k]
                        + self::PAIRS[$k];
                }
            }
        }
        return $out;
    }

    /**
     * How the manufactured planet stands in the house it was made in — read from
     * the house, since the teva's rashis are fixed.
     *
     * @return array{status:string,why:list<string>,weight:int}
     */
    public static function dignity(string $planet, int $house): array
    {
        $why = [];
        $status = 'सामान्य';
        $w = 0;
        if (in_array($house, LalKitabData::UCH_BHAV[$planet] ?? [], true)) {
            $status = 'उच्च';
            $w += 2;
            $why[] = $house . 'वाँ भाव इस ग्रह का उच्च भाव है';
        } elseif (in_array($house, LalKitabData::NEECH_BHAV[$planet] ?? [], true)) {
            $status = 'नीच';
            $w -= 2;
            $why[] = $house . 'वाँ भाव इस ग्रह का नीच भाव है';
        } elseif (in_array($house, LalKitabData::SWA_BHAV[$planet] ?? [], true)) {
            $status = 'स्वगृही';
            $w += 1;
            $why[] = $house . 'वाँ भाव इस ग्रह की अपनी राशि का है';
        }
        // पक्का घर is intensity, not merit — it multiplies whatever is already there.
        $pakka = in_array($house, LalKitabData::KARAK_BHAV[$planet] ?? [], true);
        $kachcha = in_array($house, LalKitabData::KACHCHA_BHAV[$planet] ?? [], true);
        if ($pakka) {
            $why[] = 'यह इस ग्रह का पक्का घर है — फल पूरी ताकत व स्थिरता से मिलेगा (शुभ हो तो पूरा शुभ, अशुभ हो तो पूरा अशुभ)';
        }
        if ($kachcha) {
            $why[] = 'यह इस ग्रह का कच्चा घर है — फल अधूरा, कमज़ोर या देर से मिलेगा';
        }
        return ['status' => $status, 'why' => $why, 'weight' => $w,
                'pakka' => $pakka, 'kachcha' => $kachcha];
    }

    /**
     * A third planet fit to stand between the two sources: friendly to at least
     * one, hostile to neither, and not disliked by either. Ordered so a mutual
     * friend comes first.
     *
     * @return list<array{planet:string,both:bool}>
     */
    public static function catalysts(string $a, string $b): array
    {
        $mt = LalKitabData::section('maitri');
        $names = static function (string $p, string $col) use ($mt): array {
            $out = [];
            foreach (explode(',', (string) ($mt[$p][$col] ?? '')) as $bit) {
                $bit = trim($bit);
                foreach (LalKitabData::PLANET_HI as $en => $hi) {
                    if ($hi === $bit) {
                        $out[] = $en;
                    }
                }
            }
            return $out;
        };
        $mitraA = $names($a, 'mitra');
        $mitraB = $names($b, 'mitra');
        $shatruA = $names($a, 'shatru');
        $shatruB = $names($b, 'shatru');

        $out = [];
        foreach (self::PLANET_ORDER as $p) {
            if ($p === $a || $p === $b) {
                continue;
            }
            if (in_array($p, $shatruA, true) || in_array($p, $shatruB, true)) {
                continue;                       // one of them dislikes it
            }
            $back = $names($p, 'shatru');
            if (in_array($a, $back, true) || in_array($b, $back, true)) {
                continue;                       // it dislikes one of them
            }
            $isA = in_array($p, $mitraA, true);
            $isB = in_array($p, $mitraB, true);
            if (!$isA && !$isB) {
                continue;                       // neutral to both is no help
            }
            $out[] = ['planet' => $p, 'both' => $isA && $isB];
        }
        usort($out, static fn ($x, $y) => ($y['both'] <=> $x['both']));
        return $out;
    }

    /**
     * Which of the two sources to send away and which to keep, when a formation
     * has to be broken. The one already afflicted in this chart goes first;
     * failing that the naturally harsher planet goes.
     *
     * @param array<string,string> $verdictOf  planet key => शुभ|अशुभ|मध्यम
     * @return array{remove:string,keep:string,why:string}
     */
    public static function splitPlan(string $a, string $b, array $verdictOf): array
    {
        $harsh = ['Saturn' => 5, 'Rahu' => 5, 'Ketu' => 4, 'Mars' => 3, 'Sun' => 2];
        $badA = ($verdictOf[$a] ?? '') === 'अशुभ';
        $badB = ($verdictOf[$b] ?? '') === 'अशुभ';
        if ($badA !== $badB) {
            $remove = $badA ? $a : $b;
            $keep = $badA ? $b : $a;
            $why = LalKitabData::planetHi($remove) . ' इस कुंडली में स्वयं अशुभ है, इसलिए उसे हटाया जाता है';
        } else {
            $remove = ($harsh[$a] ?? 0) >= ($harsh[$b] ?? 0) ? $a : $b;
            $keep = $remove === $a ? $b : $a;
            $why = LalKitabData::planetHi($remove) . ' स्वभाव से कठोर ग्रह है, इसलिए उसकी वस्तु जाती है';
        }
        return ['remove' => $remove, 'keep' => $keep, 'why' => $why];
    }

    /**
     * Whether the real planet of the manufactured kind also sits in the chart,
     * and how the two look at each other.
     *
     * @param array<string,int> $house  planet key => house
     * @return array<string,mixed>|null
     */
    public static function clash(string $makes, int $atHouse, array $house): ?array
    {
        $realHouse = $house[$makes] ?? null;
        if ($realHouse === null || $realHouse === $atHouse) {
            return null;
        }
        $fromMasnui = LalKitabData::DRISHTI[$atHouse][$realHouse] ?? 0;
        $fromReal   = LalKitabData::DRISHTI[$realHouse][$atHouse] ?? 0;
        if ($fromMasnui > $fromReal) {
            $stronger = 'masnui';
        } elseif ($fromReal > $fromMasnui) {
            $stronger = 'real';
        } else {
            $stronger = 'none';
        }
        return [
            'real_house'   => $realHouse,
            'from_masnui'  => $fromMasnui,
            'from_real'    => $fromReal,
            'stronger'     => $stronger,
            'linked'       => $fromMasnui > 0 || $fromReal > 0,
        ];
    }
}
