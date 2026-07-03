<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

/**
 * "अष्टकवर्ग मत" — the Ashtakavarga opinion block shown inside each Bhava
 * Phaladesh (House Prediction) house card. It reuses the SAV/BAV numbers the
 * engine ALREADY computes ({@see \AutoBusiness\Astro\Calc\Ashtakavarga}) — no
 * new astronomy. Every line prints its own numbers so the verdict stays
 * explainable, and the section contributes a weighted score into the house
 * verdict chip.
 *
 * Rendered order per house (migration 012 rule tables):
 *   1. BAND      — SAV of the house → band (बलवान/शुभ/सामान्य/कमजोर/पीड़ित) text.
 *   2. COMPARE   — cross-house SAV comparisons (11>10, 1>12, TRIBHAG …).
 *   3. OCCUPANTS — each occupying planet's own BAV in the house sign.
 *   4. LORD      — the bhavesh's BAV in the sign it occupies.
 *   5. KARAKA    — the house's natural karaka's BAV (optional; config toggle).
 */
final class AshtakavargaPhala
{
    /** BAV table exists only for the seven planets (nodes/Lagna have none). */
    private const BAV_PLANETS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];

    private const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];

    /** Band key → (display label, base score). House 12 reverses the score sign. */
    private const BANDS = [
        'uttam'   => ['label' => 'बलवान',  'score' => 1.5],
        'shubh'   => ['label' => 'शुभ',    'score' => 0.75],
        'samanya' => ['label' => 'सामान्य', 'score' => 0.0],
        'durbal'  => ['label' => 'कमजोर',  'score' => -0.75],
        'pidit'   => ['label' => 'पीड़ित',  'score' => -1.5],
    ];

    /**
     * House → its natural karaka (inverse of the Karaka engine's pairing;
     * houses without a karaka row are skipped, per spec §2.5).
     */
    private const HOUSE_KARAKA = [
        3 => 'Mars', 4 => 'Moon', 5 => 'Jupiter', 6 => 'Mercury',
        7 => 'Venus', 8 => 'Saturn', 9 => 'Sun', 12 => 'Saturn',
    ];

    /** SAV sum sanity check (§1) — logged once per process if it ever fails. */
    private static bool $savChecked = false;

    /**
     * Build the section for one house.
     *
     * @param int $house 1..12
     * @param array<string,mixed> $chart computed chart (needs houses + ashtakavarga)
     * @param array<string,mixed> $av    loaded AV rule tables (band/compare/bav)
     * @param array<string,string> $cfg  house_engine_config (av_* keys)
     * @return array{lines: list<array{text:string,tone:string}>, score: float}
     */
    public static function forHouse(int $house, array $chart, array $av, array $cfg): array
    {
        $ashtaka = $chart['ashtakavarga'] ?? null;
        $houses  = $chart['houses'] ?? [];
        $planets = $chart['planets'] ?? [];
        if ($ashtaka === null || empty($houses)) {
            return ['lines' => [], 'score' => 0.0];
        }
        $sav = $ashtaka['sav'] ?? [];
        $bav = $ashtaka['bav'] ?? [];

        // §1 sanity: the twelve SAV must total 337.
        if (!self::$savChecked) {
            self::$savChecked = true;
            $sum = array_sum($sav);
            if ($sum !== 337) {
                error_log('Ashtakavarga SAV sum expected 337, got ' . $sum);
            }
        }

        $uttam   = (int) ($cfg['av_band_uttam']   ?? 30);
        $shubh   = (int) ($cfg['av_band_shubh']   ?? 28);
        $samanya = (int) ($cfg['av_band_samanya'] ?? 25);
        $durbal  = (int) ($cfg['av_band_durbal']  ?? 23);
        $samarth = (int) ($cfg['av_bav_samarth']  ?? 5);
        $lordOk  = (int) ($cfg['av_bav_lord_ok']  ?? 4);
        $weight  = (float) ($cfg['av_section_weight'] ?? 0.5);
        $karakaOn = (string) ($cfg['av_karaka_bav'] ?? '1') === '1';

        $lines = [];
        $score = 0.0;

        $houseSign = (int) ($houses[$house]['sign_index'] ?? 0);
        $savHouse  = (int) ($sav[$houseSign] ?? 0);

        // ---- 1. BAND line -----------------------------------------------------
        $bandKey = self::band($savHouse, $uttam, $shubh, $samanya, $durbal);
        $bandTxt = (string) ($av['band'][$house][$bandKey] ?? '');
        if ($bandTxt !== '') {
            $label = self::BANDS[$bandKey]['label'] ?? $bandKey;
            $lines[] = [
                'text' => 'SAV ' . $savHouse . ' बिंदु (' . $label . ') — ' . $bandTxt,
                'tone' => self::tone(self::BANDS[$bandKey]['score']),
            ];
        }
        $bandScore = (float) (self::BANDS[$bandKey]['score'] ?? 0.0);
        if ($house === 12) {
            $bandScore = -$bandScore;   // §3: 12th reverses (high SAV = more व्यय = अशुभ)
        }
        $score += $bandScore;

        // ---- 2. COMPARISON lines ---------------------------------------------
        $cmpScore = 0.0;
        foreach (($av['compare'] ?? []) as $rule) {
            if (!in_array($house, $rule['houses'], true)) {
                continue;
            }
            $expr = (string) $rule['condition_expr'];
            $extra = '';
            if ($expr === 'TRIBHAG') {
                $extra = self::tribhag($houses, $sav);   // always "fires" (informational)
            } elseif (!self::evalCond($expr, $houses, $sav)) {
                continue;
            }
            $gb = (string) ($rule['gb'] ?? '');
            $lines[] = ['text' => (string) $rule['phal_text'] . $extra, 'tone' => self::gbTone($gb)];
            $cmpScore += $gb === 'शुभ' ? 0.5 : ($gb === 'अशुभ' ? -0.5 : 0.0);
        }
        $score += max(-1.0, min(1.0, $cmpScore));   // §3 cap ±1

        // ---- 3. OCCUPANT BAV lines -------------------------------------------
        foreach (($houses[$house]['planets'] ?? []) as $pl) {
            if (!in_array($pl, self::BAV_PLANETS, true)) {
                continue;   // Rahu/Ketu have no classical BAV — skip, no line
            }
            $b = (int) ($bav[$pl][$houseSign] ?? 0);
            $key = $b >= $samarth ? 'b_occ_5plus' : ($b === 4 ? 'b_occ_4' : 'b_occ_0_3');
            self::bavLine($lines, $score, $av, $key, [
                '{planet}' => self::PLANET_HI[$pl] ?? $pl, '{bav}' => (string) $b,
            ]);
        }

        // ---- 4. LORD BAV line -------------------------------------------------
        $lord = (string) ($houses[$house]['lord'] ?? '');
        if ($lord !== '' && in_array($lord, self::BAV_PLANETS, true) && isset($planets[$lord])) {
            $lordSign = (int) ($planets[$lord]['sign_index'] ?? 0);
            $b = (int) ($bav[$lord][$lordSign] ?? 0);
            $key = $b >= $lordOk ? 'b_lord' : ($b <= 3 ? 'b_lord_low' : 'b_lord');
            self::bavLine($lines, $score, $av, $key, [
                '{lord}' => self::PLANET_HI[$lord] ?? $lord, '{bav}' => (string) $b,
            ]);
        }

        // ---- 5. KARAKA BAV line (optional) -----------------------------------
        if ($karakaOn && isset(self::HOUSE_KARAKA[$house])) {
            $kar = self::HOUSE_KARAKA[$house];
            if (in_array($kar, self::BAV_PLANETS, true) && isset($planets[$kar])) {
                $karSign = (int) ($planets[$kar]['sign_index'] ?? 0);
                $b = (int) ($bav[$kar][$karSign] ?? 0);
                if ($b >= $samarth) {   // below → no line (keep the section short)
                    self::bavLine($lines, $score, $av, 'b_karaka', [
                        '{karaka}' => self::PLANET_HI[$kar] ?? $kar, '{bav}' => (string) $b,
                    ]);
                }
            }
        }

        return ['lines' => $lines, 'score' => round($score * $weight, 3)];
    }

    /** Config-driven banding (recomputed at lookup so admin edits take effect). */
    private static function band(int $sav, int $uttam, int $shubh, int $samanya, int $durbal): string
    {
        if ($sav >= $uttam) {
            return 'uttam';
        }
        if ($sav >= $shubh) {
            return 'shubh';
        }
        if ($sav >= $samanya) {
            return 'samanya';
        }
        if ($sav >= $durbal) {
            return 'durbal';
        }
        return 'pidit';
    }

    /** Append one BAV rule line + its score. */
    private static function bavLine(array &$lines, float &$score, array $av, string $key, array $vars): void
    {
        $rule = $av['bav'][$key] ?? null;
        if ($rule === null) {
            return;
        }
        $text = strtr((string) $rule['tpl'], $vars);
        $s = (float) $rule['score'];
        $lines[] = ['text' => $text, 'tone' => self::tone($s)];
        $score += $s;
    }

    /**
     * Evaluate an AV comparison expression: clauses joined by '&&', each of the
     * form "SAVa OP SAVb" or "SAVa OP <int>" where OP is '>' or '>='.
     */
    private static function evalCond(string $expr, array $houses, array $sav): bool
    {
        foreach (explode('&&', $expr) as $clause) {
            $clause = trim($clause);
            if (!preg_match('/^SAV(\d+)\s*(>=|>)\s*(SAV(\d+)|\d+)$/', $clause, $m)) {
                return false;
            }
            $left = self::savOf((int) $m[1], $houses, $sav);
            $right = str_starts_with($m[3], 'SAV')
                ? self::savOf((int) $m[4], $houses, $sav)
                : (int) $m[3];
            $ok = $m[2] === '>=' ? $left >= $right : $left > $right;
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /** TRIBHAG: which life-third (houses 1-4 / 5-8 / 9-12) carries the most SAV. */
    private static function tribhag(array $houses, array $sav): string
    {
        $seg = [0, 0, 0];
        for ($hh = 1; $hh <= 12; $hh++) {
            $seg[intdiv($hh - 1, 4)] += self::savOf($hh, $houses, $sav);
        }
        $labels = ['पूर्वार्ध (1-4 भाव)', 'मध्य (5-8 भाव)', 'उत्तरार्ध (9-12 भाव)'];
        $win = 0;
        if ($seg[1] > $seg[$win]) {
            $win = 1;
        }
        if ($seg[2] > $seg[$win]) {
            $win = 2;
        }
        return ' — आपका सर्वाधिक योग ' . $labels[$win] . ' में है';
    }

    /** SAV of a house number, via the house's (whole-sign) sign index. */
    private static function savOf(int $houseNo, array $houses, array $sav): int
    {
        $si = (int) ($houses[$houseNo]['sign_index'] ?? 0);
        return (int) ($sav[$si] ?? 0);
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
