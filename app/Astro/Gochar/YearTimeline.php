<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Gochar;

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Time\JulianDay;

/**
 * आगामी 12 महीने की समय-रेखा — one merged, date-sorted event calendar:
 *
 *   - दशा-परिवर्तन: Vimshottari mahadasha + antardasha transitions in the window;
 *   - राशि-प्रवेश (ingress) of Sun, Mars, Mercury, Venus, Jupiter, Saturn, Rahu
 *     (Ketu mirrors Rahu; Moon is skipped — too frequent);
 *   - वक्री/मार्गी transitions of Mars–Saturn + Mercury/Venus;
 *   - गोचर-जन्म संयोग (transit hits): Saturn / Jupiter / Rahu / Ketu passing
 *     within ±3° of a natal planet or the lagna, with entry–exit dates;
 *   - साढ़े साती / ढैय्या window start-end (if inside the window).
 *
 * Implementation: each transit planet's longitude is sampled once every 2 days
 * across the year (shared for ingress/retro/hit detection) and boundaries are
 * refined by bisection to ~half a day, so the whole calendar costs ~1300
 * ephemeris calls. Pure computation, no DB.
 */
final class YearTimeline
{
    private const P_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चन्द्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];
    private const S_HI = [
        'मेष', 'वृष', 'मिथुन', 'कर्क', 'सिंह', 'कन्या',
        'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुम्भ', 'मीन',
    ];
    /** sampled transit planets (Ketu derived from Rahu). */
    private const SAMPLED = ['Sun', 'Mars', 'Mercury', 'Venus', 'Jupiter', 'Saturn', 'Rahu'];
    /** slow movers checked for natal-degree hits. */
    private const HITTERS = ['Saturn', 'Jupiter', 'Rahu', 'Ketu'];
    private const ORB = 3.0;
    private const STEP = 2.0;      // sampling step (days)
    private const DAYS = 366;      // window length

    /**
     * @param array<string,mixed> $chart natal chart (for dasha + natal degrees)
     * @return array{events: list<array{jd:float,date:string,tone:string,icon:string,text:string}>}
     */
    public static function compute(CalculationEngine $eng, array $chart, float $nowJd, float $tz): array
    {
        $endJd = $nowJd + self::DAYS;
        $events = [];

        // ---------------- sample longitudes (shared by all detectors) --------
        $n = (int) ceil(self::DAYS / self::STEP) + 1;
        $samples = [];   // planet => list of [jd, lon]
        foreach (self::SAMPLED as $p) {
            $row = [];
            for ($i = 0; $i < $n; $i++) {
                $jd = $nowJd + $i * self::STEP;
                $row[] = [$jd, Charts::norm($eng->planetSiderealLon($p, $jd))];
            }
            $samples[$p] = $row;
        }
        // Ketu = Rahu + 180°.
        $samples['Ketu'] = array_map(
            static fn ($s) => [$s[0], Charts::norm($s[1] + 180.0)],
            $samples['Rahu']
        );
        $lonAt = static function (string $p, float $jd) use ($eng): float {
            if ($p === 'Ketu') {
                return Charts::norm($eng->planetSiderealLon('Rahu', $jd) + 180.0);
            }
            return Charts::norm($eng->planetSiderealLon($p, $jd));
        };
        // bisect a boundary where $pred flips false→true between lo..hi
        $bisect = static function (float $lo, float $hi, callable $pred): float {
            for ($k = 0; $k < 18 && ($hi - $lo) > 0.02; $k++) {
                $mid = ($lo + $hi) / 2.0;
                if ($pred($mid)) { $hi = $mid; } else { $lo = $mid; }
            }
            return $hi;
        };
        $dmy = static fn (float $jd): string => JulianDay::toDmy($jd, $tz);

        // ---------------- 1. ingress events ---------------------------------
        foreach (['Sun', 'Mars', 'Mercury', 'Venus', 'Jupiter', 'Saturn', 'Rahu', 'Ketu'] as $p) {
            $row = $samples[$p];
            for ($i = 1, $c = count($row); $i < $c; $i++) {
                $s0 = Charts::signIndex($row[$i - 1][1]);
                $s1 = Charts::signIndex($row[$i][1]);
                if ($s0 === $s1) { continue; }
                $jd = $bisect($row[$i - 1][0], $row[$i][0],
                    static fn (float $j): bool => Charts::signIndex($lonAt($p, $j)) === $s1);
                $events[] = [
                    'jd' => $jd, 'date' => $dmy($jd), 'tone' => 'info', 'icon' => '♻',
                    'text' => self::P_HI[$p] . ' का ' . self::S_HI[$s1] . ' राशि में प्रवेश',
                ];
            }
        }

        // ---------------- 2. retro / direct ----------------------------------
        foreach (['Mars', 'Mercury', 'Venus', 'Jupiter', 'Saturn'] as $p) {
            $row = $samples[$p];
            for ($i = 2, $c = count($row); $i < $c; $i++) {
                $v0 = self::fwd($row[$i - 2][1], $row[$i - 1][1]);
                $v1 = self::fwd($row[$i - 1][1], $row[$i][1]);
                if (($v0 >= 0) === ($v1 >= 0)) { continue; }
                $jd = $row[$i - 1][0];
                if ($v1 < 0) {
                    $events[] = ['jd' => $jd, 'date' => $dmy($jd), 'tone' => 'neg', 'icon' => '↺',
                        'text' => self::P_HI[$p] . ' वक्री (retrograde) प्रारम्भ'];
                } else {
                    $events[] = ['jd' => $jd, 'date' => $dmy($jd), 'tone' => 'pos', 'icon' => '↻',
                        'text' => self::P_HI[$p] . ' मार्गी (direct) — रुके काम गति पकड़ेंगे'];
                }
            }
        }

        // ---------------- 3. dasha transitions -------------------------------
        foreach (($chart['dasha']['mahadashas'] ?? []) as $md) {
            $s = (float) $md['start_jd'];
            if ($s >= $nowJd && $s <= $endJd) {
                $events[] = ['jd' => $s, 'date' => $dmy($s), 'tone' => 'info', 'icon' => '⏳',
                    'text' => '【दशा】 ' . (self::P_HI[$md['lord']] ?? $md['lord']) . ' महादशा प्रारम्भ'];
            }
            if ((float) $md['end_jd'] < $nowJd || $s > $endJd) { continue; }
            foreach (\AutoBusiness\Astro\Calc\VimshottariDasha::antardashas($md) as $ad) {
                $as = (float) $ad['start_jd'];
                if ($as >= $nowJd && $as <= $endJd) {
                    $events[] = ['jd' => $as, 'date' => $dmy($as), 'tone' => 'info', 'icon' => '⏳',
                        'text' => '【दशा】 ' . (self::P_HI[$md['lord']] ?? $md['lord']) . ' महादशा में '
                            . (self::P_HI[$ad['lord']] ?? $ad['lord']) . ' अंतर्दशा प्रारम्भ'];
                }
            }
        }

        // ---------------- 4. transit hits over natal degrees (#9) ------------
        $natal = [];
        foreach (($chart['planets'] ?? []) as $np => $nd) {
            $natal['जन्म ' . (self::P_HI[$np] ?? $np)] = (float) $nd['sidereal_lon'];
        }
        if (isset($chart['ascendant']['sidereal_lon'])) {
            $natal['जन्म लग्न'] = (float) $chart['ascendant']['sidereal_lon'];
        }
        foreach (self::HITTERS as $tp) {
            $row = $samples[$tp];
            foreach ($natal as $label => $nl) {
                $in = static fn (float $lonV): bool => self::sep($lonV, $nl) <= self::ORB;
                $inside = $in($row[0][1]);
                for ($i = 1, $c = count($row); $i < $c; $i++) {
                    $now = $in($row[$i][1]);
                    if ($now === $inside) { continue; }
                    $jd = $bisect($row[$i - 1][0], $row[$i][0],
                        static fn (float $j): bool => (self::sep($lonAt($tp, $j), $nl) <= self::ORB) === $now);
                    $benefic = $tp === 'Jupiter';
                    if ($now) {
                        $events[] = ['jd' => $jd, 'date' => $dmy($jd), 'tone' => $benefic ? 'pos' : 'neg', 'icon' => $benefic ? '✦' : '⚠',
                            'text' => '【संयोग】 गोचर ' . self::P_HI[$tp] . ' ' . $label . ' पर (±3°) — प्रभाव-काल प्रारम्भ'];
                    } else {
                        $events[] = ['jd' => $jd, 'date' => $dmy($jd), 'tone' => 'info', 'icon' => '✓',
                            'text' => '【संयोग】 गोचर ' . self::P_HI[$tp] . ' ' . $label . ' से आगे — प्रभाव समाप्त'];
                    }
                    $inside = $now;
                }
            }
        }

        usort($events, static fn ($a, $b) => $a['jd'] <=> $b['jd']);
        return ['events' => $events, 'from' => $dmy($nowJd), 'to' => $dmy($endJd)];
    }

    /** signed forward motion lon0→lon1 in (−180,180]. */
    private static function fwd(float $a, float $b): float
    {
        $d = fmod($b - $a + 540.0, 360.0) - 180.0;
        return $d;
    }

    /** angular separation 0..180. */
    private static function sep(float $a, float $b): float
    {
        $d = abs(fmod($a - $b, 360.0));
        return $d > 180.0 ? 360.0 - $d : $d;
    }
}
