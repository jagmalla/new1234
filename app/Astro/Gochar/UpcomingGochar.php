<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Gochar;

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Time\JulianDay;

/**
 * आगामी गोचर (Upcoming Gochar) — a plain-Hindi summary of the main upcoming
 * transit events from "now": each graha's next sign-ingress, retrograde /
 * direct windows, current combustion, the Moon–Sun paksha, the Moon's nakshatra
 * and (when the birth Moon-sign is known) the Sade-Sati status.
 *
 * Purely time-dependent (sign / retro / combustion / nakshatra do NOT depend on
 * the observer's place), so it reuses the same ephemeris via
 * CalculationEngine::planetSiderealLon. Boundary dates (ingress cusp, speed = 0)
 * are found by bisection, not a day-by-day loop. Scan window ≈ 18 months.
 */
final class UpcomingGochar
{
    private const P_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चन्द्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];
    private const S_HI = ['मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या', 'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुम्भ', 'मीन'];
    /** Planet → emoji (for the colourful gochar cards). */
    private const P_EMO = [
        'Sun' => '☀️', 'Moon' => '🌙', 'Mars' => '🔴', 'Mercury' => '🟢',
        'Jupiter' => '🟡', 'Venus' => '⚪', 'Saturn' => '🪐', 'Rahu' => '🐉', 'Ketu' => '☄️',
    ];
    /** Sign (rashi) → zodiac emoji. */
    private const S_EMO = ['♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓'];
    private const NAK_HI = [
        'अश्विनी', 'भरणी', 'कृत्तिका', 'रोहिणी', 'मृगशिरा', 'आर्द्रा', 'पुनर्वसु', 'पुष्य', 'आश्लेषा',
        'मघा', 'पूर्वाफाल्गुनी', 'उत्तराफाल्गुनी', 'हस्त', 'चित्रा', 'स्वाति', 'विशाखा', 'अनुराधा', 'ज्येष्ठा',
        'मूल', 'पूर्वाषाढ़ा', 'उत्तराषाढ़ा', 'श्रवण', 'धनिष्ठा', 'शतभिषा', 'पूर्वाभाद्रपद', 'उत्तराभाद्रपद', 'रेवती',
    ];
    /** Combustion orb in degrees from the Sun. Admin-tunable later; matches the spec. */
    private const ORB = ['Moon' => 12.0, 'Mars' => 17.0, 'Mercury' => 14.0, 'Jupiter' => 11.0, 'Venus' => 10.0, 'Saturn' => 15.0];
    private const ORB_RETRO = ['Mercury' => 12.0, 'Venus' => 8.0];
    private const RETRO_P = ['Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
    private const ALL9 = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];
    /** Coarse ingress-scan step per planet (days) — always < time-to-cross a sign. */
    private const STEP = ['Sun' => 1.0, 'Moon' => 0.15, 'Mars' => 2.0, 'Mercury' => 1.0, 'Venus' => 1.0, 'Jupiter' => 4.0, 'Saturn' => 8.0, 'Rahu' => 8.0, 'Ketu' => 8.0];
    private const WINDOW_D = 548.0;   // ≈ 18 months

    /**
     * @param float    $nowJd        current instant (JD UT)
     * @param float    $tz           local timezone (for DD-MM-YYYY display)
     * @param int|null $birthMoonSign natal Moon sign 0..11 (enables Sade-Sati); null on the profile screen
     * @return array<string,mixed>
     */
    public static function compute(CalculationEngine $eng, float $nowJd, float $tz, ?int $birthMoonSign = null): array
    {
        $lon = static fn (string $p, float $jd): float => $eng->planetSiderealLon($p, $jd);
        $signAt = static fn (string $p, float $jd): int => Charts::signIndex(Charts::norm($lon($p, $jd)));
        // Daily motion via central difference; sign gives retro (<0) / direct (>0).
        $spd = static function (string $p, float $jd) use ($lon): float {
            $d = $lon($p, $jd + 0.5) - $lon($p, $jd - 0.5);
            while ($d > 180.0) { $d -= 360.0; }
            while ($d < -180.0) { $d += 360.0; }
            return $d;
        };
        $dmy = static fn (float $jd): string => JulianDay::toDmy($jd, $tz);
        $days = static fn (float $jd): int => (int) round($jd - $nowJd);

        // Bisect a single boundary in [lo,hi] where pred flips; returns the hi side.
        $bisect = static function (float $lo, float $hi, callable $pred): float {
            $flo = $pred($lo);
            for ($i = 0; $i < 30 && ($hi - $lo) > 0.02; $i++) {
                $mid = ($lo + $hi) / 2.0;
                if ($pred($mid) === $flo) { $lo = $mid; } else { $hi = $mid; }
            }
            return $hi;
        };

        // ---- current positions ----
        $sunLon = Charts::norm($lon('Sun', $nowJd));
        $pos = [];
        foreach (self::ALL9 as $p) {
            $l = Charts::norm($lon($p, $nowJd));
            $pos[$p] = ['lon' => $l, 'sign' => Charts::signIndex($l), 'retro' => in_array($p, ['Rahu', 'Ketu'], true) ? true : ($spd($p, $nowJd) < 0.0)];
        }

        // ---- next sign ingress per planet ----
        $ingress = [];
        foreach (self::ALL9 as $p) {
            $s0 = $pos[$p]['sign'];
            $step = self::STEP[$p];
            $found = null; $prev = $nowJd;
            for ($jd = $nowJd + $step; $jd <= $nowJd + self::WINDOW_D; $jd += $step) {
                if ($signAt($p, $jd) !== $s0) {
                    $bj = $bisect($prev, $jd, static fn (float $j): bool => $signAt($p, $j) !== $s0);
                    $found = ['sign' => $signAt($p, $bj + 0.05), 'jd' => $bj];
                    break;
                }
                $prev = $jd;
            }
            $ingress[$p] = $found;
        }

        // ---- retrograde / direct windows (Mars..Saturn) ----
        $retro = [];
        foreach (self::RETRO_P as $p) {
            $curRetro = $pos[$p]['retro'];
            $flip = static function (float $from, bool $toRetro) use ($p, $spd, $bisect, $nowJd): ?float {
                $step = 3.0; $prev = $from; $want = $toRetro;   // want negative if toRetro
                for ($jd = $from + $step; $jd <= $nowJd + self::WINDOW_D; $jd += $step) {
                    $isRetro = $spd($p, $jd) < 0.0;
                    if ($isRetro === $want) {
                        return $bisect($prev, $jd, static fn (float $j): bool => ($spd($p, $j) < 0.0) === $want);
                    }
                    $prev = $jd;
                }
                return null;
            };
            if ($curRetro) {
                $direct = $flip($nowJd, false);
                $retro[$p] = ['currently' => true, 'retro_start_jd' => null, 'direct_jd' => $direct];
            } else {
                $rs = $flip($nowJd, true);
                $direct = $rs !== null ? $flip($rs + 5.0, false) : null;
                $retro[$p] = ['currently' => false, 'retro_start_jd' => $rs, 'direct_jd' => $direct];
            }
        }

        // ---- खण्ड 1: retro happening in the SAME (or back to previous) sign ----
        $retroSame = [];
        foreach (self::RETRO_P as $p) {
            $r = $retro[$p];
            $signHi = self::S_HI[$pos[$p]['sign']];
            if ($r['currently']) {
                // Currently retro — will it slip back into the previous sign before turning direct?
                $backJd = null;
                if ($ingress[$p] !== null && $r['direct_jd'] !== null && $ingress[$p]['jd'] <= $r['direct_jd']
                    && $ingress[$p]['sign'] === (($pos[$p]['sign'] - 1) + 12) % 12) {
                    $backJd = $ingress[$p]['jd'];
                }
                $retroSame[] = ['planet' => self::P_HI[$p], 'currently' => true, 'sign_hi' => $signHi,
                    'back_date' => $backJd !== null ? $dmy($backJd) : null,
                    'back_sign_hi' => $backJd !== null ? self::S_HI[$ingress[$p]['sign']] : null,
                    'direct_date' => $r['direct_jd'] !== null ? $dmy($r['direct_jd']) : null];
            } elseif ($r['retro_start_jd'] !== null && $signAt($p, $r['retro_start_jd']) === $pos[$p]['sign']) {
                $retroSame[] = ['planet' => self::P_HI[$p], 'currently' => false, 'sign_hi' => $signHi,
                    'start_date' => $dmy($r['retro_start_jd']), 'back_date' => null];
            }
        }

        // ---- खण्ड 2: paksha (Moon–Sun) ----
        $moonLon = $pos['Moon']['lon'];
        $elong = Charts::norm($moonLon - $sunLon);   // 0..360
        $sep = min($elong, 360.0 - $elong);
        $paksha = [
            'name' => $elong < 180.0 ? 'शुक्ल पक्ष' : 'कृष्ण पक्ष',
            'waxing' => $elong < 180.0,
            'moon_combust' => $sep < self::ORB['Moon'],
            'near' => $sep < self::ORB['Moon'] ? ($elong > 180.0 || $elong < 15.0 ? 'अमावस्या निकट' : '') : '',
        ];

        // ---- खण्ड 3: currently-combust planets ----
        $combust = [];
        foreach (['Mercury', 'Venus', 'Mars', 'Jupiter', 'Saturn', 'Moon'] as $p) {
            $orb = self::ORB[$p];
            if ($pos[$p]['retro'] && isset(self::ORB_RETRO[$p])) { $orb = self::ORB_RETRO[$p]; }
            $e = Charts::norm($pos[$p]['lon'] - $sunLon);
            $s = min($e, 360.0 - $e);
            if ($s < $orb) { $combust[] = ['planet' => self::P_HI[$p], 'sign_hi' => self::S_HI[$pos[$p]['sign']], 'sep' => round($s, 1)]; }
        }

        // ---- खण्ड 7: Moon nakshatra + next change ----
        $nakSpan = 360.0 / 27.0;
        $nakIdx = (int) floor(Charts::norm($moonLon) / $nakSpan) % 27;
        $nextNakLon = ($nakIdx + 1) * $nakSpan;
        $nakChangeJd = null; $prev = $nowJd;
        for ($jd = $nowJd + 0.1; $jd <= $nowJd + 3.0; $jd += 0.1) {
            if ((int) floor(Charts::norm($lon('Moon', $jd)) / $nakSpan) % 27 !== $nakIdx) {
                $nakChangeJd = $bisect($prev, $jd, static fn (float $j): bool => (int) floor(Charts::norm($lon('Moon', $j)) / $nakSpan) % 27 !== $nakIdx);
                break;
            }
            $prev = $jd;
        }
        $nak = [
            'name' => self::NAK_HI[$nakIdx], 'sign_hi' => self::S_HI[$pos['Moon']['sign']],
            'next_name' => self::NAK_HI[($nakIdx + 1) % 27],
            'next_date' => $nakChangeJd !== null ? $dmy($nakChangeJd) : null,
        ];

        // ---- खण्ड 6: nearest upcoming event ----
        $events = [];
        foreach ($ingress as $p => $ig) {
            // Skip the Moon here — it changes sign every ~2¼ days and would always
            // win "nearest", drowning out the meaningful slow-planet events.
            if ($ig !== null && $p !== 'Moon') { $events[] = [$ig['jd'], self::P_HI[$p] . ' ' . $dmy($ig['jd']) . ' को ' . self::S_HI[$ig['sign']] . ' राशि में प्रवेश']; }
        }
        foreach ($retro as $p => $r) {
            if (!empty($r['retro_start_jd'])) { $events[] = [$r['retro_start_jd'], self::P_HI[$p] . ' ' . $dmy($r['retro_start_jd']) . ' को वक्री']; }
            if (!empty($r['direct_jd']) && $r['currently']) { $events[] = [$r['direct_jd'], self::P_HI[$p] . ' ' . $dmy($r['direct_jd']) . ' को मार्गी']; }
        }
        usort($events, static fn ($a, $b) => $a[0] <=> $b[0]);
        $nearest = null;
        if ($events !== []) {
            $nearest = ['text' => $events[0][1], 'days' => $days($events[0][0]), 'date' => $dmy($events[0][0])];
        }

        // ---- खण्ड 8: Jupiter / Saturn highlight + Sade-Sati ----
        $slow = [];
        foreach (['Jupiter', 'Saturn'] as $p) {
            $slow[] = ['planet' => self::P_HI[$p], 'sign_hi' => self::S_HI[$pos[$p]['sign']],
                'next_date' => $ingress[$p] !== null ? $dmy($ingress[$p]['jd']) : null,
                'next_sign_hi' => $ingress[$p] !== null ? self::S_HI[$ingress[$p]['sign']] : null];
        }
        $sadeSati = null;
        if ($birthMoonSign !== null) {
            $sadeSati = self::safe(static fn () => SadeSatiTimeline::compute($birthMoonSign, $nowJd, static fn (float $j): float => $lon('Saturn', $j)));
        }

        // =====================================================================
        // ENRICHED DATA for the colourful gochar "charts" (cards): combustion &
        // retrograde windows split into past / current / future, each carrying
        // its own start & end DATE and the rashi involved, plus the Moon-paksha
        // calendar (next amavasya / purnima, Moon-combust window) and the top
        // few upcoming events. All boundaries are found by bisection.
        // =====================================================================
        $emo = static fn (string $p): string => self::P_EMO[$p] ?? '•';

        // -- combustion helpers --
        $combOrb = static function (string $p, float $jd) use ($spd): float {
            $orb = self::ORB[$p] ?? 0.0;
            if (isset(self::ORB_RETRO[$p]) && $spd($p, $jd) < 0.0) { $orb = self::ORB_RETRO[$p]; }
            return $orb;
        };
        $sepFrom = static function (string $p, float $jd) use ($lon): float {
            $e = Charts::norm($lon($p, $jd) - $lon('Sun', $jd));
            return min($e, 360.0 - $e);
        };
        $isComb = static fn (string $p, float $jd): bool => $sepFrom($p, $jd) < $combOrb($p, $jd);

        // Scan a boolean predicate over [now-PAST, now+FUT] and return the flip
        // windows as [{start,end}] (start/end = null when open at a scan edge).
        $scanWindows = static function (callable $pred, float $step, float $back, float $fwd) use ($nowJd, $bisect): array {
            $lo = $nowJd - $back; $hi = $nowJd + $fwd;
            $wins = []; $prev = $lo; $prevOn = $pred($lo); $start = null;   // unknown before scan
            if ($prevOn) { $start = null; }
            for ($jd = $lo + $step; $jd <= $hi; $jd += $step) {
                $on = $pred($jd);
                if ($on !== $prevOn) {
                    $b = $bisect($prev, $jd, $pred);
                    if ($on) { $start = $b; } else { $wins[] = ['start' => $start, 'end' => $b]; $start = null; }
                    $prevOn = $on;
                }
                $prev = $jd;
            }
            if ($prevOn) { $wins[] = ['start' => $start, 'end' => null]; }
            return $wins;
        };
        // Classify windows into the most-recent past, the current one, the next future.
        $classify = static function (array $wins) use ($nowJd): array {
            $past = null; $cur = null; $fut = null;
            foreach ($wins as $w) {
                $s = $w['start']; $e = $w['end'];
                $isCur = ($s === null || $s <= $nowJd) && ($e === null || $e >= $nowJd);
                if ($isCur) { $cur = $w; }
                elseif ($e !== null && $e < $nowJd) { $past = $w; }              // keep last past (loop is time-ordered)
                elseif ($s !== null && $s > $nowJd && $fut === null) { $fut = $w; }
            }
            return ['past' => $past, 'current' => $cur, 'future' => $fut];
        };
        $signHiAt = static fn (string $p, ?float $jd): string => self::S_HI[$signAt($p, $jd ?? $nowJd)];
        $signEmoAt = static fn (string $p, ?float $jd): string => self::S_EMO[$signAt($p, $jd ?? $nowJd)];
        $card = static function (string $p, array $w, string $repJd) use ($dmy, $days, $signHiAt, $signEmoAt, $emo, $nowJd): array {
            $rep = $w[$repJd] ?? ($w['start'] ?? $w['end'] ?? $nowJd);
            return [
                'planet' => self::P_HI[$p], 'emoji' => $emo($p),
                'sign_hi' => $signHiAt($p, $rep), 'sign_emo' => $signEmoAt($p, $rep),
                'start' => $w['start'] !== null ? $dmy($w['start']) : null,
                'end' => $w['end'] !== null ? $dmy($w['end']) : null,
                'start_days' => $w['start'] !== null ? $days($w['start']) : null,
                'end_days' => $w['end'] !== null ? $days($w['end']) : null,
            ];
        };

        // -- अस्त (combustion): past / current / future per planet --
        $combParts = ['current' => [], 'past' => [], 'future' => []];
        foreach (['Mercury', 'Venus', 'Mars', 'Jupiter', 'Saturn', 'Moon'] as $p) {
            $step = $p === 'Moon' ? 0.2 : (($p === 'Mercury' || $p === 'Venus') ? 1.0 : 2.0);
            $wins = $scanWindows(static fn (float $j): bool => $isComb($p, $j), $step, 240.0, 400.0);
            $c = $classify($wins);
            if ($c['current'] !== null) {
                $cc = $card($p, $c['current'], 'end');   // sign at "now" via end rep is close; use now
                $cc['sign_hi'] = self::S_HI[$pos[$p]['sign']]; $cc['sign_emo'] = self::S_EMO[$pos[$p]['sign']];
                $cc['sep'] = round($sepFrom($p, $nowJd), 1);
                $combParts['current'][] = $cc;
            }
            if ($c['past'] !== null)   { $combParts['past'][]   = $card($p, $c['past'], 'end'); }
            if ($c['future'] !== null) { $combParts['future'][] = $card($p, $c['future'], 'start'); }
        }

        // -- वक्री (retrograde): past / current / future per planet --
        $retroParts = ['current' => [], 'past' => [], 'future' => []];
        foreach (self::RETRO_P as $p) {
            $wins = $scanWindows(static fn (float $j): bool => $spd($p, $j) < 0.0, 2.0, 260.0, 420.0);
            $c = $classify($wins);
            if ($c['current'] !== null) {
                $cc = $card($p, $c['current'], 'start');
                $cc['sign_hi'] = self::S_HI[$pos[$p]['sign']]; $cc['sign_emo'] = self::S_EMO[$pos[$p]['sign']];
                $retroParts['current'][] = $cc;
            }
            if ($c['past'] !== null)   { $retroParts['past'][]   = $card($p, $c['past'], 'start'); }
            if ($c['future'] !== null) { $retroParts['future'][] = $card($p, $c['future'], 'start'); }
        }

        // -- चन्द्र-पक्ष कैलेंडर: next amavasya / purnima + Moon-combust window --
        $nextElong = static function (float $target) use ($lon, $nowJd, $bisect): ?float {
            $f = static function (float $jd) use ($lon, $target): float {
                $x = Charts::norm(Charts::norm($lon('Moon', $jd) - $lon('Sun', $jd)) - $target);
                return $x > 180.0 ? $x - 360.0 : $x;
            };
            $prev = $nowJd; $fp = $f($nowJd);
            for ($jd = $nowJd + 0.5; $jd <= $nowJd + 31.0; $jd += 0.5) {
                $fc = $f($jd);
                if ($fp < 0.0 && $fc >= 0.0) { return $bisect($prev, $jd, static fn (float $j): bool => $f($j) >= 0.0); }
                $prev = $jd; $fp = $fc;
            }
            return null;
        };
        $tithiNum = (int) floor($elong / 12.0) + 1;   // 1..30
        $moonWins = $scanWindows(static fn (float $j): bool => $isComb('Moon', $j), 0.2, 20.0, 40.0);
        $mc = $classify($moonWins);
        $moonCombWin = $mc['current'] ?? $mc['future'];
        $pakshaCal = [
            'name' => $paksha['name'], 'waxing' => $paksha['waxing'],
            'tithi' => $tithiNum, 'elong' => round($elong, 1),
            'sign_hi' => self::S_HI[$pos['Moon']['sign']], 'sign_emo' => self::S_EMO[$pos['Moon']['sign']],
            'nak' => self::NAK_HI[$nakIdx],
            'next_amavasya' => ($j = $nextElong(0.0)) !== null ? $dmy($j) : null,
            'next_purnima' => ($j = $nextElong(180.0)) !== null ? $dmy($j) : null,
            'moon_combust_now' => !empty($paksha['moon_combust']),
            'moon_combust_start' => ($moonCombWin && $moonCombWin['start'] !== null) ? $dmy($moonCombWin['start']) : null,
            'moon_combust_end' => ($moonCombWin && $moonCombWin['end'] !== null) ? $dmy($moonCombWin['end']) : null,
        ];

        // -- राशि-परिवर्तन cards (skip Moon: changes every ~2¼ days) --
        $ingressCards = [];
        foreach ($ingress as $p => $ig) {
            if ($ig === null || $p === 'Moon') { continue; }
            $ingressCards[] = [
                'planet' => self::P_HI[$p], 'emoji' => $emo($p), 'jd' => $ig['jd'],
                'date' => $dmy($ig['jd']), 'days' => $days($ig['jd']),
                'from_hi' => self::S_HI[$pos[$p]['sign']], 'from_emo' => self::S_EMO[$pos[$p]['sign']],
                'to_hi' => self::S_HI[$ig['sign']], 'to_emo' => self::S_EMO[$ig['sign']],
            ];
        }
        usort($ingressCards, static fn ($a, $b) => $a['jd'] <=> $b['jd']);

        // -- top upcoming events (mixed: ingress + retro flips + combust + phases) --
        $evAll = [];
        foreach ($ingress as $p => $ig) {
            if ($ig !== null && $p !== 'Moon') {
                $evAll[] = ['jd' => $ig['jd'], 'emoji' => $emo($p), 'kind' => 'ingress',
                    'text' => self::P_HI[$p] . ' → ' . self::S_HI[$ig['sign']] . ' राशि', 'tone' => 'move'];
            }
        }
        foreach ($retro as $p => $r) {
            if (!empty($r['retro_start_jd'])) {
                $evAll[] = ['jd' => $r['retro_start_jd'], 'emoji' => '↩️', 'kind' => 'retro',
                    'text' => self::P_HI[$p] . ' वक्री', 'tone' => 'warn'];
            }
            if (!empty($r['direct_jd']) && $r['currently']) {
                $evAll[] = ['jd' => $r['direct_jd'], 'emoji' => '▶️', 'kind' => 'direct',
                    'text' => self::P_HI[$p] . ' मार्गी', 'tone' => 'good'];
            }
        }
        foreach ($combParts['current'] as $cc) {
            if (!empty($cc['end'])) {
                $evAll[] = ['jd' => $nowJd + (float) $cc['end_days'], 'emoji' => '🌟', 'kind' => 'combust_end',
                    'text' => $cc['planet'] . ' अस्त-मुक्त (उदय)', 'tone' => 'good'];
            }
        }
        if ($pakshaCal['next_amavasya'] !== null) {
            $jA = $nextElong(0.0);
            if ($jA !== null) { $evAll[] = ['jd' => $jA, 'emoji' => '🌑', 'kind' => 'amavasya', 'text' => 'अमावस्या', 'tone' => 'warn']; }
        }
        if ($pakshaCal['next_purnima'] !== null) {
            $jP = $nextElong(180.0);
            if ($jP !== null) { $evAll[] = ['jd' => $jP, 'emoji' => '🌕', 'kind' => 'purnima', 'text' => 'पूर्णिमा', 'tone' => 'good']; }
        }
        usort($evAll, static fn ($a, $b) => $a['jd'] <=> $b['jd']);
        $topEvents = [];
        foreach (array_slice($evAll, 0, 6) as $e) {
            $topEvents[] = ['emoji' => $e['emoji'], 'text' => $e['text'], 'tone' => $e['tone'],
                'date' => $dmy($e['jd']), 'days' => $days($e['jd'])];
        }

        return [
            'now_dmy' => $dmy($nowJd),
            'combust_parts' => $combParts,
            'retro_parts' => $retroParts,
            'paksha_cal' => $pakshaCal,
            'ingress_cards' => $ingressCards,
            'top_events' => $topEvents,
            'pos' => array_map(static fn ($x, $k) => ['planet' => self::P_HI[$k], 'sign_hi' => self::S_HI[$x['sign']], 'retro' => $x['retro']], $pos, array_keys($pos)),
            'retro_same' => $retroSame,
            'paksha' => $paksha,
            'combust' => $combust,
            'ingress' => array_values(array_filter(array_map(static function ($ig, $p) use ($dmy) {
                return $ig === null ? null : ['planet' => self::P_HI[$p], 'date' => $dmy($ig['jd']), 'sign_hi' => self::S_HI[$ig['sign']], 'jd' => $ig['jd']];
            }, $ingress, array_keys($ingress)))),
            'retro_periods' => array_map(static function ($r, $p) use ($dmy) {
                return ['planet' => self::P_HI[$p], 'currently' => $r['currently'],
                    'retro_start' => !empty($r['retro_start_jd']) ? $dmy($r['retro_start_jd']) : null,
                    'direct' => !empty($r['direct_jd']) ? $dmy($r['direct_jd']) : null];
            }, $retro, array_keys($retro)),
            'nearest' => $nearest,
            'nakshatra' => $nak,
            'slow' => $slow,
            'sade_sati' => is_array($sadeSati) && !empty($sadeSati['found']) ? [
                'active' => !empty($sadeSati['active']),
                'kind' => $sadeSati['kind'] === 'sadesati' ? 'साढ़े साती' : 'ढैया',
                'start' => $dmy((float) $sadeSati['start_jd']), 'end' => $dmy((float) $sadeSati['end_jd']),
                'sign_hi' => self::S_HI[(int) $sadeSati['sign_index']],
            ] : null,
        ];
    }

    /** @return mixed */
    private static function safe(callable $fn)
    {
        try { return $fn(); } catch (\Throwable $e) { return null; }
    }
}
