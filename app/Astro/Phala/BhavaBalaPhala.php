<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

/**
 * "भाव बल मत" — the Bhava-Bala opinion block shown inside each Bhava Phaladesh
 * house card, directly BELOW the "अष्टकवर्ग मत" (Ashtakavarga) block. It reuses
 * the Bhava Bala virupa totals the engine ALREADY computes (the BB:xxx numbers
 * in the chart ring), the EXISTING Shadbala "meets required minimum" check for
 * lord strength, and the running Vimshottari chain — no new calculation.
 *
 * Rendered order per house (migration 013 rule tables):
 *   1. BAND       — the house's BB virupa → band (बलवान/शुभ/मध्यम/दुर्बल/अति दुर्बल).
 *   2. RANKING    — this house's rank among all 12 BB; plus a once-per-chart
 *                   spread line (rendered on house 1 only).
 *   3. COMPARISON — cross-house BB comparisons (11>12, 2>12, 4>10 …).
 *   4. LORD-MATCH — house-strong × lord-strong (Shadbala) matrix.
 *   5. TIMING     — when the running Maha/Antar lord rules or occupies the house.
 */
final class BhavaBalaPhala
{
    private const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];

    /** Band key → (display label, base score). House 12 reverses the score sign. */
    private const BANDS = [
        'balwan'     => ['label' => 'बलवान',     'score' => 1.5],
        'shubh'      => ['label' => 'शुभ',       'score' => 0.75],
        'madhyam'    => ['label' => 'मध्यम',      'score' => 0.0],
        'durbal'     => ['label' => 'दुर्बल',     'score' => -0.75],
        'ati_durbal' => ['label' => 'अति दुर्बल', 'score' => -1.5],
    ];

    /**
     * Build the section for one house.
     *
     * @param int $house 1..12
     * @param array<string,mixed> $chart computed chart (houses + shadbala + dasha)
     * @param array<string,mixed> $bb    loaded BB rule tables
     * @param array<string,string> $cfg  house_engine_config (bb_* keys)
     * @param array<string,string> $tpl  house_pred_templates (timing sentences)
     * @return array{lines: list<array{text:string,tone:string}>, score: float}
     */
    public static function forHouse(int $house, array $chart, array $bb, array $cfg, array $tpl): array
    {
        $houses = $chart['houses'] ?? [];
        if (empty($houses) || !isset($houses[$house])) {
            return ['lines' => [], 'score' => 0.0];
        }
        $shadbala = $chart['shadbala'] ?? [];
        $running = $chart['dasha']['running'] ?? [];

        $balwan  = (int) ($cfg['bb_band_balwan']  ?? 480);
        $shubhV  = (int) ($cfg['bb_band_shubh']   ?? 450);
        $madhyam = (int) ($cfg['bb_band_madhyam'] ?? 420);
        $durbal  = (int) ($cfg['bb_band_durbal']  ?? 390);
        $spreadHi = (int) ($cfg['bb_spread_high'] ?? 150);
        $spreadLo = (int) ($cfg['bb_spread_low']  ?? 80);
        $weight  = (float) ($cfg['bb_section_weight'] ?? 0.5);

        $lines = [];
        $score = 0.0;

        $virupa = (float) ($houses[$house]['bb_virupa'] ?? 0.0);

        // ---- 1. BAND line -----------------------------------------------------
        $bandKey = self::band($virupa, $balwan, $shubhV, $madhyam, $durbal);
        $bandTxt = (string) ($bb['band'][$house][$bandKey] ?? '');
        if ($bandTxt !== '') {
            $label = self::BANDS[$bandKey]['label'] ?? $bandKey;
            $rupa = number_format($virupa / 60.0, 1);
            $lines[] = [
                'text' => 'भाव बल ' . self::vnum($virupa) . ' विरूपा (' . $rupa . ' रूपा — ' . $label . ') — ' . $bandTxt,
                'tone' => self::tone(self::BANDS[$bandKey]['score']),
            ];
        }
        $bandScore = (float) (self::BANDS[$bandKey]['score'] ?? 0.0);
        if ($house === 12) {
            $bandScore = -$bandScore;   // §3: 12th reverses (high व्यय-बल is not "good")
        }
        $score += $bandScore;

        // ---- 2. RANKING (+ once-per-chart spread on house 1) ------------------
        [$rank, $maxV, $minV] = self::rankOf($house, $houses);
        $rankKey = $rank <= 3 ? 'r_top3' : ($rank <= 9 ? 'r_mid6' : 'r_bottom3');
        if (isset($bb['rank'][$rankKey])) {
            $lines[] = [
                'text' => 'बारहों भावों में ' . $rank . 'वाँ स्थान — ' . (string) $bb['rank'][$rankKey]['text'],
                'tone' => self::tone((float) $bb['rank'][$rankKey]['score']),
            ];
            $score += (float) $bb['rank'][$rankKey]['score'];
        }
        if ($house === 1) {
            $spread = $maxV - $minV;
            $spreadKey = $spread >= $spreadHi ? 'r_spread_high' : ($spread <= $spreadLo ? 'r_spread_low' : null);
            if ($spreadKey !== null && isset($bb['rank'][$spreadKey])) {
                $lines[] = [
                    'text' => 'सबसे बली और सबसे निर्बल भाव का अंतर ' . self::vnum($spread) . ' विरूपा — ' . (string) $bb['rank'][$spreadKey]['text'],
                    'tone' => 'info',
                ];
                $score += (float) $bb['rank'][$spreadKey]['score'];
            }
        }

        // ---- 3. COMPARISON lines ---------------------------------------------
        $cmpScore = 0.0;
        foreach (($bb['compare'] ?? []) as $rule) {
            if (!in_array($house, $rule['houses'], true)) {
                continue;
            }
            if (!self::evalCond((string) $rule['condition_expr'], $houses)) {
                continue;
            }
            $gb = (string) ($rule['gb'] ?? '');
            $lines[] = ['text' => (string) $rule['phal_text'], 'tone' => self::gbTone($gb)];
            $cmpScore += $gb === 'शुभ' ? 0.5 : ($gb === 'अशुभ' ? -0.5 : 0.0);
        }
        $score += max(-1.0, min(1.0, $cmpScore));   // §3 cap ±1

        // ---- 4. LORD-MATCH line ----------------------------------------------
        $lord = (string) ($houses[$house]['lord'] ?? '');
        $lordStrong = null;
        if ($lord !== '' && isset($shadbala[$lord])) {
            $ratio = (float) ($shadbala[$lord]['ratio'] ?? 0.0);
            $lordStrong = $ratio >= 1.0;
            $houseStrong = $virupa >= $shubhV;
            $lmKey = 'lm_' . ($houseStrong ? 's' : 'w') . ($lordStrong ? 's' : 'w');
            if (isset($bb['lord'][$lmKey])) {
                $lordHi = self::PLANET_HI[$lord] ?? $lord;
                $rupaVal = number_format((float) ($shadbala[$lord]['total_rupa'] ?? 0.0), 1);
                $poorna = $lordStrong ? 'पूर्ण' : 'अपूर्ण';
                $lines[] = [
                    'text' => 'भाव ' . self::vnum($virupa) . ' विरूपा, भावेश ' . $lordHi . ' षड्बल ' . $rupaVal
                        . ' रूपा (' . $poorna . ') — ' . (string) $bb['lord'][$lmKey]['text'],
                    'tone' => self::tone((float) $bb['lord'][$lmKey]['score']),
                ];
                $score += (float) $bb['lord'][$lmKey]['score'];
            }
        }

        // ---- 5. TIMING line (Maha precedence over Antar; lord before occupant) -
        $timing = self::timingLine($house, $lord, $bandKey, $houses, $running, $tpl);
        if ($timing !== null) {
            $lines[] = $timing;
        }

        return ['lines' => $lines, 'score' => round($score * $weight, 3)];
    }

    /** Config-driven banding (recomputed at lookup so admin edits take effect). */
    private static function band(float $v, int $balwan, int $shubh, int $madhyam, int $durbal): string
    {
        if ($v >= $balwan) {
            return 'balwan';
        }
        if ($v >= $shubh) {
            return 'shubh';
        }
        if ($v >= $madhyam) {
            return 'madhyam';
        }
        if ($v >= $durbal) {
            return 'durbal';
        }
        return 'ati_durbal';
    }

    /**
     * Rank of a house among all twelve by BB (1 = strongest). Ties share the
     * better rank (1 + number of houses with strictly greater BB).
     *
     * @return array{0:int,1:float,2:float} rank, max virupa, min virupa
     */
    private static function rankOf(int $house, array $houses): array
    {
        $v = (float) ($houses[$house]['bb_virupa'] ?? 0.0);
        $rank = 1;
        $max = $v;
        $min = $v;
        for ($hh = 1; $hh <= 12; $hh++) {
            $x = (float) ($houses[$hh]['bb_virupa'] ?? 0.0);
            if ($x > $v) {
                $rank++;
            }
            $max = max($max, $x);
            $min = min($min, $x);
        }
        return [$rank, $max, $min];
    }

    /**
     * Evaluate a BB comparison: clauses joined by '&&', each "BBa OP BBb" with
     * OP in {'>','>='}.
     */
    private static function evalCond(string $expr, array $houses): bool
    {
        foreach (explode('&&', $expr) as $clause) {
            $clause = trim($clause);
            if (!preg_match('/^BB(\d+)\s*(>=|>)\s*BB(\d+)$/', $clause, $m)) {
                return false;
            }
            $left = (float) ($houses[(int) $m[1]]['bb_virupa'] ?? 0.0);
            $right = (float) ($houses[(int) $m[3]]['bb_virupa'] ?? 0.0);
            $ok = $m[2] === '>=' ? $left >= $right : $left > $right;
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /**
     * The dasha timing line: bb_dasha_lord when the running Maha/Antar lord IS
     * this house's lord (Maha wins ties), else bb_dasha_occupant when it merely
     * occupies the house. Never both.
     *
     * @return array{text:string,tone:string}|null
     */
    private static function timingLine(int $house, string $lord, string $bandKey, array $houses, array $running, array $tpl): ?array
    {
        $maha = $running['maha'] ?? null;
        $antar = $running['antar'] ?? null;
        if ($maha === null && $antar === null) {
            return null;
        }

        // (a) running lord rules this house → bb_dasha_lord
        if ($lord !== '' && ($maha === $lord || $antar === $lord)) {
            $tplStr = (string) ($tpl['bb_dasha_lord'] ?? '');
            if ($tplStr === '') {
                return null;
            }
            $word = in_array($bandKey, ['balwan', 'shubh'], true) ? 'खिलेंगे'
                : (in_array($bandKey, ['durbal', 'ati_durbal'], true) ? 'परीक्षा लेंगे' : 'सक्रिय रहेंगे');
            $text = strtr($tplStr, ['{lord}' => self::PLANET_HI[$lord] ?? $lord, '{phal_word}' => $word]);
            return ['text' => $text, 'tone' => 'info'];
        }

        // (b) else running lord occupies this house → bb_dasha_occupant (Maha first)
        $occupants = $houses[$house]['planets'] ?? [];
        foreach ([$maha, $antar] as $pl) {
            if ($pl !== null && in_array($pl, $occupants, true)) {
                $tplStr = (string) ($tpl['bb_dasha_occupant'] ?? '');
                if ($tplStr === '') {
                    return null;
                }
                return ['text' => strtr($tplStr, ['{planet}' => self::PLANET_HI[$pl] ?? $pl]), 'tone' => 'info'];
            }
        }
        return null;
    }

    /** Whole-number virupa for display. */
    private static function vnum(float $v): string
    {
        return number_format($v, 0);
    }

    private static function tone(float $score): string
    {
        return $score > 0 ? 'pos' : ($score < 0 ? 'neg' : 'info');
    }

    private static function gbTone(string $gb): string
    {
        return $gb === 'शुभ' ? 'pos' : ($gb === 'अशुभ' ? 'neg' : 'info');
    }
}
