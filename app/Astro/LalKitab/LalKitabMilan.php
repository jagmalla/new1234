<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\LalKitab;

/**
 * लाल किताब कुंडली-मिलान — Lal Kitab compatibility between two charts.
 *
 * Lal Kitab has no 36-guna Ashtakoot system; its marriage assessment is
 * qualitative and rests on three real pillars, all of which we already compute
 * per chart in {@see LalKitabEngine::compute()}:
 *
 *   1. मंगल मिलान  — is each partner मांगलिक (Mars in 1/4/7/8/12)? Both-or-neither
 *                    balances; a one-sided मंगल दोष is the concern.
 *   2. पितृ-ऋण मिलान — the ancestral debts (paitrik rin) present in each chart.
 *                    A debt shared by both compounds; a one-sided debt is that
 *                    partner's remedy to clear.
 *   3. ग्रह-स्थिति मिलान — planet-by-planet, is a planet शुभ or अशुभ in each chart?
 *                    Where one partner's planet is strong and the other's weak,
 *                    the strong side supports; where both are weak, both suffer.
 *
 * This class consumes two {@see LalKitabEngine::compute()} outputs and produces a
 * colour-coded report (शुभ/मिश्र/अशुभ) with a remedy for every negative finding —
 * no invented rules, every verdict traces back to the per-chart computation.
 */
final class LalKitabMilan
{
    private const PLANETS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];

    /**
     * @param array<string,mixed> $lkA  boy's  LalKitabEngine::compute() output
     * @param array<string,mixed> $lkB  girl's LalKitabEngine::compute() output
     * @param string $nameA
     * @param string $nameB
     * @return array<string,mixed>
     */
    public static function match(array $lkA, array $lkB, string $nameA = 'वर', string $nameB = 'कन्या'): array
    {
        if (empty($lkA['ok']) || empty($lkB['ok'])) {
            return ['ok' => false, 'error' => 'दोनों कुंडलियाँ आवश्यक हैं'];
        }

        $mangal = self::mangalAxis($lkA['manglik'] ?? [], $lkB['manglik'] ?? [], $nameA, $nameB);
        $rin    = self::rinAxis($lkA['shrap'] ?? [], $lkB['shrap'] ?? [], $nameA, $nameB);
        $graha  = self::grahaAxis($lkA['planets'] ?? [], $lkB['planets'] ?? [], $nameA, $nameB);

        $axes = ['mangal' => $mangal, 'rin' => $rin, 'graha' => $graha];

        // Indicative compatibility %: each axis contributes pos=1, mix=0.5, neg=0.
        $w = ['pos' => 1.0, 'mix' => 0.5, 'neg' => 0.0];
        $sum = 0.0;
        foreach ($axes as $ax) {
            $sum += $w[$ax['tone']] ?? 0.5;
        }
        $pct = (int) round($sum / count($axes) * 100);

        // Tier from the axis tones (any hard अशुभ pulls the verdict down).
        $tones = array_column($axes, 'tone');
        if (in_array('neg', $tones, true)) {
            $tier = 'ashubh';
        } elseif (in_array('mix', $tones, true)) {
            $tier = 'mishrit';
        } else {
            $tier = 'shubh';
        }

        // Combined remedy list — every axis' remedies, de-duplicated.
        $remedies = [];
        foreach ($axes as $ax) {
            foreach ($ax['remedies'] as $rm) {
                $key = ($rm['who'] ?? '') . '|' . ($rm['text'] ?? '');
                $remedies[$key] = $rm;
            }
        }
        $remedies = array_values($remedies);

        $verdict = self::verdict($tier, $mangal, $rin, $graha, $pct);

        return [
            'ok'       => true,
            'nameA'    => $nameA,
            'nameB'    => $nameB,
            'axes'     => $axes,
            'pairs'    => $graha['pairs'],
            'percent'  => $pct,
            'tier'     => $tier,          // shubh | mishrit | ashubh
            'tier_hi'  => $tier === 'shubh' ? 'शुभ मिलान' : ($tier === 'ashubh' ? 'अशुभ — सावधानी व उपाय आवश्यक' : 'मिश्र मिलान'),
            'verdict'  => $verdict,
            'remedies' => $remedies,
        ];
    }

    // ---------------------------------------------------------------- मंगल axis

    /**
     * @param array<string,mixed> $mA boy manglik reading
     * @param array<string,mixed> $mB girl manglik reading
     * @return array<string,mixed>
     */
    private static function mangalAxis(array $mA, array $mB, string $nA, string $nB): array
    {
        $aM = !empty($mA['is']);
        $bM = !empty($mB['is']);
        $remedies = [];
        $detail = [];

        $detail[] = $nA . ': मंगल ' . ($mA['mars_ord'] ?? '') . ' भाव में — ' . ($aM ? 'मांगलिक' : 'गैर-मांगलिक');
        $detail[] = $nB . ': मंगल ' . ($mB['mars_ord'] ?? '') . ' भाव में — ' . ($bM ? 'मांगलिक' : 'गैर-मांगलिक');

        if ($aM && $bM) {
            $tone = 'pos';
            $reason = 'दोनों मांगलिक — मंगल दोष परस्पर सम होकर शमित हो जाता है। यह मिलान शुभ माना जाता है।';
        } elseif (!$aM && !$bM) {
            $tone = 'pos';
            $reason = 'दोनों में से किसी की कुंडली में मंगल दोष-भाव (1,4,7,8,12) में नहीं — मंगल की दृष्टि से मिलान निर्दोष है।';
        } else {
            $tone = 'neg';
            $who = $aM ? $nA : $nB;
            $mm  = $aM ? $mA : $mB;
            $reason = 'असंतुलन — केवल ' . $who . ' मांगलिक है। लाल किताब में एकपक्षीय मंगल दोष '
                . ($mm['mars_effect'] ?? 'दाम्पत्य') . ' प्रभाव डाल सकता है; विवाह से पूर्व मंगल का उपाय आवश्यक।';
            if (trim((string) ($mm['upay'] ?? '')) !== '') {
                $remedies[] = ['who' => $who, 'hi' => 'मंगल', 'text' => (string) $mm['upay']];
            } else {
                $remedies[] = ['who' => $who, 'hi' => 'मंगल', 'text' => 'मंगल-शांति: मीठी रोटी/गुड़ का दान, हनुमान उपासना एवं मंगलवार व्रत।'];
            }
        }

        return [
            'key'    => 'mangal',
            'label'  => 'मंगल मिलान (Mangal Dosha)',
            'tone'   => $tone,
            'reason' => $reason,
            'detail' => $detail,
            'remedies' => $remedies,
        ];
    }

    // ---------------------------------------------------------------- ऋण axis

    /**
     * @param list<array<string,mixed>> $sA boy shrap readings
     * @param list<array<string,mixed>> $sB girl shrap readings
     * @return array<string,mixed>
     */
    private static function rinAxis(array $sA, array $sB, string $nA, string $nB): array
    {
        $presA = self::presentRin($sA);   // rin-name => row
        $presB = self::presentRin($sB);

        $shared = array_intersect_key($presA, $presB);
        $onlyA  = array_diff_key($presA, $presB);
        $onlyB  = array_diff_key($presB, $presA);

        $detail = [];
        $remedies = [];

        foreach ($shared as $name => $row) {
            $detail[] = '⚠ साझा ऋण — ' . $name . ' (दोनों की कुंडली में) : ' . self::matchStr($row);
            if (trim((string) ($row['upay'] ?? '')) !== '') {
                $remedies[] = ['who' => 'दोनों', 'hi' => $name, 'text' => (string) $row['upay']];
            }
        }
        foreach ($onlyA as $name => $row) {
            $detail[] = $nA . ' में ' . $name . ' : ' . self::matchStr($row);
            if (trim((string) ($row['upay'] ?? '')) !== '') {
                $remedies[] = ['who' => $nA, 'hi' => $name, 'text' => (string) $row['upay']];
            }
        }
        foreach ($onlyB as $name => $row) {
            $detail[] = $nB . ' में ' . $name . ' : ' . self::matchStr($row);
            if (trim((string) ($row['upay'] ?? '')) !== '') {
                $remedies[] = ['who' => $nB, 'hi' => $name, 'text' => (string) $row['upay']];
            }
        }

        if ($shared !== []) {
            $tone = 'neg';
            $reason = 'दोनों की कुंडली में एक ही पितृ-ऋण है — विवाह पर यह ऋण दुगना प्रभाव डालता है। दोनों को मिलकर इसका उपाय करना आवश्यक है।';
        } elseif ($onlyA !== [] || $onlyB !== []) {
            $tone = 'mix';
            $reason = 'किसी एक की कुंडली में पितृ-ऋण है — संबंधित व्यक्ति उपाय कर ले तो मिलान अनुकूल रहता है।';
        } else {
            $tone = 'pos';
            $reason = 'किसी भी कुंडली में सक्रिय पितृ-ऋण नहीं मिला — ऋण-मिलान की दृष्टि से यह शुभ है।';
        }

        return [
            'key'    => 'rin',
            'label'  => 'पितृ-ऋण मिलान (Ancestral Debt)',
            'tone'   => $tone,
            'reason' => $reason,
            'detail' => $detail !== [] ? $detail : ['किसी भी कुंडली में सक्रिय पितृ-ऋण नहीं।'],
            'remedies' => $remedies,
        ];
    }

    /** Rin rows that are actually present in a chart, keyed by rin name. */
    private static function presentRin(array $shrap): array
    {
        $out = [];
        foreach ($shrap as $row) {
            if (!empty($row['present'])) {
                $out[(string) ($row['rin'] ?? '')] = $row;
            }
        }
        unset($out['']);
        return $out;
    }

    private static function matchStr(array $row): string
    {
        $m = $row['matched'] ?? [];
        return is_array($m) && $m !== [] ? implode(', ', $m) : (string) ($row['pehchan'] ?? '');
    }

    // ---------------------------------------------------------------- ग्रह axis

    /**
     * Planet-by-planet harmony: for each planet compare its शुभ/अशुभ state in
     * both charts.
     *
     * @param list<array<string,mixed>> $pA
     * @param list<array<string,mixed>> $pB
     * @return array<string,mixed>
     */
    private static function grahaAxis(array $pA, array $pB, string $nA, string $nB): array
    {
        $byA = self::byPlanet($pA);
        $byB = self::byPlanet($pB);

        $pairs = [];
        $bad = 0; $good = 0; $mixc = 0;
        $remedies = [];

        foreach (self::PLANETS as $en) {
            $a = $byA[$en] ?? null;
            $b = $byB[$en] ?? null;
            if ($a === null || $b === null) {
                continue;
            }
            $hi = (string) ($a['hi'] ?? LalKitabData::planetHi($en));
            $aBad = self::isWeak($a);
            $bBad = self::isWeak($b);

            if ($aBad && $bBad) {
                $tone = 'neg'; $bad++;
                $note = 'दोनों की कुंडली में ' . $hi . ' अशुभ/कमज़ोर — इसके कारक क्षेत्र में दोनों को कष्ट। दोनों उपाय करें।';
                self::addPlanetRemedy($remedies, $nA, $a);
                self::addPlanetRemedy($remedies, $nB, $b);
            } elseif ($aBad || $bBad) {
                $tone = 'mix'; $mixc++;
                $strong = $aBad ? $nB : $nA;
                $weakWho = $aBad ? $nA : $nB;
                $note = $strong . ' की कुंडली में ' . $hi . ' शुभ है, जो ' . $weakWho
                    . ' के कमज़ोर ' . $hi . ' को सहारा देगा; फिर भी ' . $weakWho . ' उपाय कर ले।';
                self::addPlanetRemedy($remedies, $weakWho, $aBad ? $a : $b);
            } else {
                $tone = 'pos'; $good++;
                $note = 'दोनों में ' . $hi . ' शुभ — इसके कारक क्षेत्र में परस्पर सहयोग व अनुकूलता।';
            }

            $pairs[] = [
                'planet'  => $en,
                'hi'      => $hi,
                'a_house' => (int) ($a['house'] ?? 0),
                'a_status'=> self::statusLabel($a),
                'a_bad'   => $aBad,
                'b_house' => (int) ($b['house'] ?? 0),
                'b_status'=> self::statusLabel($b),
                'b_bad'   => $bBad,
                'tone'    => $tone,
                'note'    => $note,
            ];
        }

        if ($bad >= 3) {
            $tone = 'neg';
            $reason = $bad . ' ग्रह दोनों की कुंडली में एक साथ अशुभ हैं — इनके कारक क्षेत्रों में परस्पर तनाव संभव; उपाय आवश्यक।';
        } elseif ($bad > 0 || $mixc > 0) {
            $tone = 'mix';
            $reason = 'कुछ ग्रह एक पक्ष में शुभ व दूसरे में अशुभ हैं — शुभ पक्ष सहारा देगा, कमज़ोर पक्ष उपाय कर ले।';
        } else {
            $tone = 'pos';
            $reason = 'दोनों कुंडलियों के ग्रह परस्पर अनुकूल — ग्रह-स्थिति की दृष्टि से मिलान शुभ।';
        }

        return [
            'key'    => 'graha',
            'label'  => 'ग्रह-स्थिति मिलान (Planet Harmony)',
            'tone'   => $tone,
            'reason' => $reason,
            'pairs'  => $pairs,
            'good'   => $good,
            'mix'    => $mixc,
            'bad'    => $bad,
            'remedies' => $remedies,
        ];
    }

    /** @return array<string,array<string,mixed>> planet-en => reading */
    private static function byPlanet(array $planets): array
    {
        $out = [];
        foreach ($planets as $p) {
            $out[(string) ($p['planet'] ?? '')] = $p;
        }
        unset($out['']);
        return $out;
    }

    /** A planet is "weak" for matching if it is अशुभ or नीच in its chart. */
    private static function isWeak(array $p): bool
    {
        return !empty($p['is_ashubh']) || ($p['status'] ?? '') === 'नीच';
    }

    /**
     * Label reflects the शुभ/अशुभ verdict (what matching cares about), so it never
     * contradicts the red/green colour. Positive dignity is appended when notable.
     */
    private static function statusLabel(array $p): string
    {
        if (self::isWeak($p)) {
            return ($p['status'] ?? '') === 'नीच' ? 'नीच · अशुभ' : 'अशुभ';
        }
        $st = (string) ($p['status'] ?? '');
        if (in_array($st, ['उच्च', 'स्वगृही'], true) || !empty($p['pukka'])) {
            return ($st !== '' && $st !== 'सम' ? $st . ' · ' : '') . 'शुभ';
        }
        return 'शुभ';
    }

    /** Pull the top remedy for a weak planet from its per-chart reading. */
    private static function addPlanetRemedy(array &$remedies, string $who, array $p): void
    {
        $hi = (string) ($p['hi'] ?? '');
        $text = '';
        $rem = $p['remedies'] ?? [];
        if (is_array($rem) && $rem !== []) {
            $text = (string) $rem[0];
        } elseif (trim((string) ($p['sheeghra'] ?? '')) !== '') {
            $text = (string) $p['sheeghra'];
        }
        if ($text === '') {
            return;
        }
        $remedies[] = ['who' => $who, 'hi' => $hi, 'text' => $text];
    }

    // ---------------------------------------------------------------- verdict

    private static function verdict(string $tier, array $mangal, array $rin, array $graha, int $pct): string
    {
        $parts = [];
        $parts[] = 'लाल किताब अनुकूलता (सांकेतिक): लगभग ' . $pct . '%.';
        if ($tier === 'shubh') {
            $parts[] = 'मंगल, पितृ-ऋण एवं ग्रह-स्थिति — तीनों दृष्टि से मिलान अनुकूल है। यह संबंध शुभ माना जा सकता है।';
        } elseif ($tier === 'mishrit') {
            $parts[] = 'मिलान मुख्यतः अनुकूल है, पर कुछ बिंदुओं पर सावधानी चाहिए। ऊपर सुझाए उपाय कर लेने पर संबंध शुभ रहेगा।';
        } else {
            $parts[] = 'कुछ महत्वपूर्ण बिंदुओं पर दोष है। विवाह से पूर्व सुझाए गए उपाय अवश्य करें; उपाय के बाद ही निर्णय शुभ रहेगा।';
        }
        return implode(' ', $parts);
    }
}
