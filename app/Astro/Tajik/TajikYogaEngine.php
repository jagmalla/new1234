<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Tajik;

use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;
use AutoBusiness\Astro\Calc\Varga;

/**
 * Tajik 16-yoga detector (Tajik Neelkanthi, Shodasha-yogadhyaya shloka 15–77)
 * for the Varshaphal panel. Reads the already-computed varsha chart (READ
 * only — no Varshaphal math is changed) plus the migration-016 rule tables
 * ({@see TajikRepository}) and the sphuta-drishti matrix
 * ({@see TajikDrishtiService}); grades every planet pair for
 * Ithasala/Israfa and the dependent yogas, the chart-level Ikkabal/Induvar,
 * and the per-planet Kuttha/Duraph strength chip.
 *
 * Detection order (rules md भाग 5): chart-level → per pair Ithasala
 * (वर्तमान/पूर्ण/भावी) or Israfa → annotations Radda / Duphalikuttha / Manau /
 * Kamboola (16 bheda) / Gairikamboola / Khallasara → mediator yogas
 * Nakta/Yamaya → Tambira → Dutthotthadavira → Kuttha/Duraph chips.
 * A pair never carries both Ithasala and Israfa; Radda/Duphalikuttha/Manau/
 * Kamboola annotate the base record instead of duplicating it.
 */
final class TajikYogaEngine
{
    public const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि',
    ];
    /** Classical speed order fallback (fastest first) — shloka 17 tika. */
    private const SPEED_ORDER = ['Moon' => 7, 'Mercury' => 6, 'Venus' => 5, 'Sun' => 4, 'Mars' => 3, 'Jupiter' => 2, 'Saturn' => 1];
    /** Exalt/debil sign per planet (0=Aries..11=Pisces) for the Tajik pada check. */
    private const EXALT = ['Sun' => 0, 'Moon' => 1, 'Mars' => 9, 'Mercury' => 5, 'Jupiter' => 3, 'Venus' => 11, 'Saturn' => 6];
    private const DEBIL = ['Sun' => 6, 'Moon' => 7, 'Mars' => 3, 'Mercury' => 11, 'Jupiter' => 9, 'Venus' => 5, 'Saturn' => 0];
    private const ADHIKAR_RANK = ['उत्तम' => 3, 'मध्यम' => 2, 'सम' => 1, 'अधम' => 0];

    /**
     * @param array<string,mixed> $vp    Varshaphal::compute output (read-only)
     * @param array<string,mixed> $rules TajikRepository::load output
     * @param string|null $activeMuddaLord running Mudda mahadasha lord
     * @return array<string,mixed>
     */
    public static function compute(array $vp, array $rules, ?string $activeMuddaLord = null): array
    {
        $chart = $vp['varsha_chart'] ?? [];
        $planets = $chart['planets'] ?? [];
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $cfg = $rules['config'] ?? [];
        $defs = $rules['yogas'] ?? [];
        $orbMode = (string) ($cfg['tajik_orb_mode'] ?? 'faster');
        $ekarksha = ($cfg['tajik_ekarksha_drishti'] ?? '1') === '1';
        $hillaja = ($cfg['tajik_hillaja_mata'] ?? '1') === '1';
        $poornaDeg = ((float) ($cfg['tajik_poorna_orb_kala'] ?? 30)) / 60.0;   // kala → degrees

        $seven = array_values(array_filter(TajikDrishtiService::PLANETS, static fn($p) => isset($planets[$p])));
        $matrix = TajikDrishtiService::matrix($planets, $rules['dhruvanka'] ?? []);

        // ---- shared per-planet facts -------------------------------------
        $sign = static fn(string $p): int => (int) ($planets[$p]['sign_index'] ?? 0);
        $deg = static fn(string $p): float => (float) ($planets[$p]['deg_in_sign'] ?? 0.0);
        $house = static fn(string $p): int => (int) ($planets[$p]['house'] ?? 0);
        $speed = static function (string $p) use ($planets): float {
            $s = abs((float) ($planets[$p]['speed'] ?? 0.0));
            // Zero/missing speed (ephemeris edge) → classical order decides.
            return $s > 1e-6 ? $s : (float) (self::SPEED_ORDER[$p] ?? 0) * 1e-6;
        };
        $faster = static function (string $a, string $b) use ($speed): string {
            $sa = $speed($a); $sb = $speed($b);
            if (abs($sa - $sb) > 1e-9) { return $sa > $sb ? $a : $b; }
            return (self::SPEED_ORDER[$a] ?? 0) >= (self::SPEED_ORDER[$b] ?? 0) ? $a : $b;
        };
        $orbOf = static fn(string $p): float => (float) (($rules['deeptamsha'][$p] ?? 9.0));
        $orb = static function (string $a, string $b) use ($orbOf, $faster, $orbMode): float {
            return $orbMode === 'mean' ? ($orbOf($a) + $orbOf($b)) / 2.0 : $orbOf($faster($a, $b));
        };

        $adhikar = [];   // planet => ['grade' => उत्तम.., 'why' => ..]
        foreach ($seven as $p) {
            $adhikar[$p] = self::adhikar($p, $sign($p), $deg($p), $rules['hadda'] ?? []);
        }
        $nirbal = [];    // planet => ['weak' => bool, 'why' => list<string>]
        foreach ($seven as $p) {
            $nirbal[$p] = self::nirbal($p, $planets, $ascSign);
        }

        // ---- chart-level yogas (mutually exclusive) ----------------------
        $chartYogas = [];
        $allKP = true; $allApo = true;
        foreach ($seven as $p) {
            $hh = $house($p);
            if (in_array($hh, [3, 6, 9, 12], true)) { $allKP = false; } else { $allApo = false; }
        }
        if ($allKP) { $chartYogas[] = self::rec('ikkabal', $defs, ['participants' => $seven]); }
        if ($allApo) { $chartYogas[] = self::rec('induvar', $defs, ['participants' => $seven]); }

        // ---- pair yogas ---------------------------------------------------
        $records = [];
        $tambiraSeen = [];
        for ($i = 0; $i < count($seven); $i++) {
            for ($j = $i + 1; $j < count($seven); $j++) {
                $a = $seven[$i]; $b = $seven[$j];
                $fast = $faster($a, $b); $slow = $fast === $a ? $b : $a;
                $fd = $deg($fast); $sd = $deg($slow);
                $o = $orb($a, $b);
                $gap = abs($fd - $sd);
                $seen = TajikDrishtiService::hasDrishti($sign($fast), $sign($slow), $ekarksha);
                // Sphuta kala from the (longitude-based) dhruvanka matrix; the
                // drishti LABEL follows the whole-sign relation that governs
                // yoga formation, so the card never contradicts itself.
                $cell = $matrix[$fast][$slow] ?? null;
                if ($cell !== null) {
                    $relHouse = ((($sign($slow) - $sign($fast)) % 12) + 12) % 12 + 1;
                    $cell['type_hi'] = TajikDrishtiService::typeForHouse($relHouse);
                }

                $base = null;
                if ($seen && $fd < $sd && $gap <= $o) {
                    // ITHASALA — वर्तमान / पूर्ण
                    $base = self::rec('ithasala', $defs, [
                        'participants' => [$fast, $slow],
                        'subtype' => $gap <= $poornaDeg ? 'पूर्ण' : 'वर्तमान',
                        'detail' => self::pairLine($fast, $slow, $fd, $sd, $gap, $o, $cell),
                        'score' => 1,
                    ]);
                } elseif ($fd > $sd && (30.0 - $fd) + $sd <= $o
                    && TajikDrishtiService::hasDrishti(($sign($fast) + 1) % 12, $sign($slow), $ekarksha)) {
                    // भावी इत्थशाल — fast at the sign's end, aspect forms in the next sign.
                    $base = self::rec('ithasala', $defs, [
                        'participants' => [$fast, $slow],
                        'subtype' => 'भावी',
                        'detail' => self::pairLine($fast, $slow, $fd, $sd, (30.0 - $fd) + $sd, $o, $cell),
                        'score' => 1,
                    ]);
                } elseif ($seen && $fd > $sd && $gap <= $o) {
                    // ISRAFA (never together with ithasala for the same pair)
                    $soft = $hillaja && PlanetCondition::isBenefic($slow, $planets);
                    $base = self::rec('israfa', $defs, [
                        'participants' => [$fast, $slow],
                        'detail' => self::pairLine($fast, $slow, $fd, $sd, $gap, $o, $cell),
                        'score' => $soft ? 0 : -1,
                    ]);
                    if ($soft) {
                        $base['tags'][] = ['name_hi' => 'हिल्लाज-मत', 'tone' => 'pos',
                            'text' => 'शुभ-ग्रह-जनित मुसरिफ — कार्य-नाश नहीं होता।'];
                    }
                }

                if ($base !== null && $base['yoga_key'] === 'ithasala') {
                    self::annotateIthasala($base, $fast, $slow, $defs, $rules, $planets, $matrix,
                        $adhikar, $nirbal, $deg, $sign, $orb, $orbOf, $ekarksha);
                }

                if ($base !== null) {
                    $records[] = $base;
                    continue;
                }

                // -- no ithasala/israfa for this pair: mediator + transfer yogas --
                if (!$seen) {
                    $med = self::naktaYamaya($fast, $slow, $seven, $defs, $planets, $sign, $deg, $orb, $faster, $ekarksha);
                    if ($med !== null) { $records[] = $med; }
                }
                $tam = self::tambira($fast, $slow, $seven, $defs, $planets, $sign, $deg, $house, $orbOf, $adhikar);
                if ($tam !== null) {
                    $tk = implode('|', $tam['participants']);
                    if (!isset($tambiraSeen[$tk])) { $tambiraSeen[$tk] = true; $records[] = $tam; }
                }
                if (($nirbal[$fast]['weak'] ?? false) && ($nirbal[$slow]['weak'] ?? false)) {
                    $dut = self::dutthottha($fast, $slow, $seven, $defs, $sign, $deg, $orb, $faster, $adhikar, $nirbal, $ekarksha);
                    if ($dut !== null) { $records[] = $dut; }
                }
            }
        }

        // ---- per-planet Kuttha / Duraph chip -------------------------------
        $chips = [];
        foreach ($seven as $p) {
            $chips[$p] = self::chip($p, $house($p), $adhikar[$p], $nirbal[$p], $defs);
        }

        return [
            'chart_yogas' => $chartYogas,
            'yogas' => $records,
            'chips' => $chips,
            'drishti' => $matrix,
            'adhikar' => $adhikar,
            'roles' => [
                'mudda' => $activeMuddaLord,
                'munthesh' => (string) ($vp['muntha']['lord'] ?? ''),
                'lagnesh' => (string) ($vp['varsha_lagna']['lord'] ?? ''),
                'varshesh' => (string) ($vp['varshesh']['lord'] ?? ''),
            ],
            'is_day' => (bool) ($chart['is_day'] ?? true),
        ];
    }

    // ---- dependent yogas (annotations on an ithasala) -----------------------

    /**
     * Radda / Duphalikuttha / Manau / Kamboola / Gairikamboola / Khallasara.
     * They modify the base ithasala record's tags + score (rules md §3).
     *
     * @param array<string,mixed> $base (by ref)
     */
    private static function annotateIthasala(
        array &$base, string $fast, string $slow, array $defs, array $rules, array $planets,
        array $matrix, array $adhikar, array $nirbal, callable $deg, callable $sign,
        callable $orb, callable $orbOf, bool $ekarksha
    ): void {
        // RADDA — a निर्बल participant cannot carry the teja.
        $weakSide = ($nirbal[$fast]['weak'] ?? false) ? $fast : (($nirbal[$slow]['weak'] ?? false) ? $slow : null);
        if ($weakSide !== null) {
            $base['tags'][] = ['name_hi' => ($defs['radda']['name_hi'] ?? 'रद्द'), 'tone' => 'neg',
                'text' => self::hi($weakSide) . ' निर्बल (' . implode(', ', $nirbal[$weakSide]['why']) . ') — '
                    . ($defs['radda']['phal_text'] ?? '')];
            $base['score'] -= 2;
        }
        // DUPHALIKUTTHA — pada-yukta slow rescues a pada-hina (but clean) fast.
        if (in_array($adhikar[$slow]['grade'], ['उत्तम', 'मध्यम'], true)
            && $adhikar[$fast]['grade'] === 'सम'
            && !(bool) ($planets[$fast]['retro'] ?? false)
            && $sign($fast) !== (self::DEBIL[$fast] ?? -1)) {
            $base['tags'][] = ['name_hi' => ($defs['duphalikuttha']['name_hi'] ?? 'दुफालिकुत्थ'), 'tone' => 'pos',
                'text' => self::hi($slow) . ' पद-युक्त (' . $adhikar[$slow]['why'] . ') — ' . ($defs['duphalikuttha']['phal_text'] ?? '')];
            $base['score'] += 2;
        }
        // MANAU — Mars/Saturn robs the fast planet's teja by a 1/4/7/10 drishti.
        foreach (['Mars', 'Saturn'] as $krura) {
            if ($krura === $fast || $krura === $slow || !isset($planets[$krura])) { continue; }
            $rel = ((($sign($fast) - $sign($krura)) % 12) + 12) % 12 + 1;   // house of fast from krura
            if (in_array($rel, [1, 4, 7, 10], true) && abs($deg($krura) - $deg($fast)) <= $orbOf($krura)) {
                $base['tags'][] = ['name_hi' => ($defs['manau']['name_hi'] ?? 'मणऊ'), 'tone' => 'neg',
                    'text' => self::hi($krura) . ' की वैर-दृष्टि (' . $rel . ') दीप्तांश-भीतर — ' . ($defs['manau']['phal_text'] ?? '')];
                $base['score'] -= 2;
                break;
            }
        }
        // KAMBOOLA — the Moon joins the ithasala with either participant.
        if ($fast !== 'Moon' && $slow !== 'Moon' && isset($planets['Moon'])) {
            $moonJoins = null;
            foreach ([$fast, $slow] as $x) {
                if (TajikDrishtiService::hasDrishti($sign('Moon'), $sign($x), $ekarksha)
                    && $deg('Moon') < $deg($x)
                    && abs($deg('Moon') - $deg($x)) <= $orb('Moon', $x)) {
                    $moonJoins = $x;
                    break;
                }
            }
            if ($moonJoins !== null) {
                $lordsGrade = self::minGrade($adhikar[$fast]['grade'], $adhikar[$slow]['grade']);
                $bhedaKey = $adhikar['Moon']['grade'] . '|' . $lordsGrade;
                $bheda = (string) ($rules['kamboola_bheda'][$bhedaKey] ?? '');
                $base['tags'][] = ['name_hi' => ($defs['kamboola']['name_hi'] ?? 'कम्बूल'), 'tone' => 'pos',
                    'text' => 'चन्द्र का ' . self::hi($moonJoins) . ' से इत्थशाल — ' . ($defs['kamboola']['phal_text'] ?? '')
                        . ($bheda !== '' ? ' भेद: ' . $bheda : '')];
                $base['score'] += 1;
            } else {
                $moonDeg = $deg('Moon');
                if ($adhikar['Moon']['grade'] === 'सम') {
                    // GAIRIKAMBOOLA — शून्य-पद Moon at the sign's end applying to an
                    // own-sign/exalted planet in its entry sign.
                    $entrySign = ($sign('Moon') + 1) % 12;
                    $gk = false;
                    foreach (TajikDrishtiService::PLANETS as $t) {
                        if ($t === 'Moon' || !isset($planets[$t]) || $sign($t) !== $entrySign) { continue; }
                        $own = Charts::signLord($entrySign) === $t || (self::EXALT[$t] ?? -1) === $entrySign;
                        if ($own && (30.0 - $moonDeg) + $deg($t) <= $orbOf('Moon')) {
                            $base['tags'][] = ['name_hi' => ($defs['gairikamboola']['name_hi'] ?? 'गैरिकम्बूल'), 'tone' => 'pos',
                                'text' => 'राश्यन्त-स्थ चन्द्र ' . self::hi($t) . ' (स्वगृही/उच्च) से मिलने जा रहा — '
                                    . ($defs['gairikamboola']['phal_text'] ?? '')];
                            $base['score'] += 1;
                            $gk = true;
                            break;
                        }
                    }
                    // KHALLASARA — शून्य-पद Moon joins neither by ithasala nor by yuti.
                    if (!$gk && $sign('Moon') !== $sign($fast) && $sign('Moon') !== $sign($slow)) {
                        $base['tags'][] = ['name_hi' => ($defs['khallasara']['name_hi'] ?? 'खल्लासर'), 'tone' => 'neg',
                            'text' => ($defs['khallasara']['phal_text'] ?? '')];
                        $base['score'] -= 1;
                    }
                }
            }
        }
    }

    /** NAKTA / YAMAYA — a mediator bridges two planets that share no drishti. */
    private static function naktaYamaya(
        string $a, string $b, array $seven, array $defs, array $planets,
        callable $sign, callable $deg, callable $orb, callable $faster, bool $ekarksha
    ): ?array {
        foreach ($seven as $t) {
            if ($t === $a || $t === $b) { continue; }
            // Mediator in its own sign between the two (not conjunct-sign with either),
            // aspecting BOTH within orb — the orb+drishti tests are the real filters.
            if ($sign($t) === $sign($a) || $sign($t) === $sign($b)) { continue; }
            if (!TajikDrishtiService::hasDrishti($sign($t), $sign($a), $ekarksha)
                || !TajikDrishtiService::hasDrishti($sign($t), $sign($b), $ekarksha)) { continue; }
            if (abs($deg($t) - $deg($a)) > $orb($t, $a) || abs($deg($t) - $deg($b)) > $orb($t, $b)) { continue; }

            $fasterThanBoth = $faster($t, $a) === $t && $faster($t, $b) === $t;
            $slowerThanBoth = $faster($t, $a) !== $t && $faster($t, $b) !== $t;
            if (!$fasterThanBoth && !$slowerThanBoth) { continue; }

            $key = $fasterThanBoth ? 'nakta' : 'yamaya';
            return self::rec($key, $defs, [
                'participants' => [$a, $b],
                'mediator' => $t,
                'detail' => self::hi($a) . ' ' . self::dms($deg($a)) . ' ✕ ' . self::hi($b) . ' ' . self::dms($deg($b))
                    . ' — परस्पर दृष्टि नहीं · मध्यस्थ ' . self::hi($t) . ' ' . self::dms($deg($t))
                    . ' (' . ($fasterThanBoth ? 'शीघ्रगामी' : 'मन्दगामी') . ') दोनों को दीप्तांश-भीतर देखता है',
                'score' => 1,
            ]);
        }
        return null;
    }

    /** TAMBIRA — the stronger participant at the sign's end hands over to an adhikar-yukta planet in the next sign. */
    private static function tambira(
        string $a, string $b, array $seven, array $defs, array $planets,
        callable $sign, callable $deg, callable $house, callable $orbOf, array $adhikar
    ): ?array {
        // Stronger participant by Kuttha rank (lagna > kendra > panaphara), tie → adhikar.
        $rank = static function (string $p) use ($house, $adhikar): int {
            $hh = $house($p);
            $r = $hh === 1 ? 3 : (in_array($hh, [4, 7, 10], true) ? 2 : (in_array($hh, [2, 5, 8, 11], true) ? 1 : 0));
            return $r * 10 + (self::ADHIKAR_RANK[$adhikar[$p]['grade']] ?? 0);
        };
        $s = $rank($a) >= $rank($b) ? $a : $b;
        $o = $orbOf($s);
        if (30.0 - $deg($s) > $o) { return null; }
        $next = ($sign($s) + 1) % 12;
        foreach ($seven as $r) {
            if ($r === $s || $sign($r) !== $next) { continue; }
            if (!in_array($adhikar[$r]['grade'], ['उत्तम', 'मध्यम'], true)) { continue; }
            if ((30.0 - $deg($s)) + $deg($r) > $o) { continue; }
            return self::rec('tambira', $defs, [
                'participants' => [$s, $r],
                'detail' => self::hi($s) . ' राश्यन्त ' . self::dms($deg($s)) . ' → अगली राशि में '
                    . self::hi($r) . ' ' . self::dms($deg($r)) . ' (' . $adhikar[$r]['why'] . ') — दीप्तांश ' . rtrim(rtrim(number_format($o, 1), '0'), '.') . '°',
                'score' => 1,
            ]);
        }
        return null;
    }

    /** DUTTHOTTHADAVIRA — both participants निर्बल; a strong pada-yukta third helps by ithasala. */
    private static function dutthottha(
        string $a, string $b, array $seven, array $defs,
        callable $sign, callable $deg, callable $orb, callable $faster, array $adhikar, array $nirbal, bool $ekarksha
    ): ?array {
        foreach ($seven as $t) {
            if ($t === $a || $t === $b) { continue; }
            if (($nirbal[$t]['weak'] ?? true) || !in_array($adhikar[$t]['grade'], ['उत्तम', 'मध्यम'], true)) { continue; }
            foreach ([$a, $b] as $x) {
                if (!TajikDrishtiService::hasDrishti($sign($t), $sign($x), $ekarksha)) { continue; }
                if (abs($deg($t) - $deg($x)) > $orb($t, $x)) { continue; }
                // ithasala: the faster of the two applies (smaller in-sign degrees).
                $f = $faster($t, $x); $sl = $f === $t ? $x : $t;
                if ($deg($f) >= $deg($sl)) { continue; }
                return self::rec('dutthotthadavira', $defs, [
                    'participants' => [$a, $b],
                    'mediator' => $t,
                    'detail' => self::hi($a) . ' व ' . self::hi($b) . ' दोनों निर्बल — बली '
                        . self::hi($t) . ' (' . $adhikar[$t]['why'] . ') का ' . self::hi($x) . ' से इत्थशाल',
                    'score' => 1,
                ]);
            }
        }
        return null;
    }

    // ---- per-planet strength chip -------------------------------------------

    /** @return array{chip:string,word:string,tone:string,why:list<string>} */
    private static function chip(string $p, int $house, array $adhikar, array $nirbal, array $defs): array
    {
        $why = [];
        $duraph = false;
        if (in_array($house, [6, 8, 12], true)) { $duraph = true; $why[] = 'लग्न से ' . $house . ' में'; }
        if ($nirbal['weak']) { $duraph = true; $why = array_merge($why, $nirbal['why']); }
        if ($duraph) {
            return ['chip' => 'duraph', 'word' => ($defs['duraph']['name_hi'] ?? 'दुरफ'), 'tone' => 'neg', 'why' => $why];
        }
        if ($house === 1) { $why[] = 'लग्न-स्थ (सर्वाधिक बली)'; }
        elseif (in_array($house, [4, 7, 10], true)) { $why[] = 'केन्द्र-स्थ'; }
        elseif (in_array($house, [2, 5, 8, 11], true)) { $why[] = 'पणफर-स्थ'; }
        if (in_array($adhikar['grade'], ['उत्तम', 'मध्यम'], true)) { $why[] = $adhikar['why']; }
        if ($why !== []) {
            return ['chip' => 'kuttha', 'word' => ($defs['kuttha']['name_hi'] ?? 'कुत्थ'), 'tone' => 'pos', 'why' => $why];
        }
        return ['chip' => 'sam', 'word' => 'सम', 'tone' => 'info', 'why' => ['न विशेष बली, न निर्बल']];
    }

    // ---- resolvers ------------------------------------------------------------

    /**
     * Tajik pada/adhikar: उत्तम = own sign / exaltation; मध्यम = own hadda /
     * own drekkana / own navamsha; अधम = debilitation / enemy sign; सम = none.
     *
     * @param array<int,list<array{0:float,1:string}>> $hadda
     * @return array{grade:string,why:string}
     */
    public static function adhikar(string $p, int $sign, float $deg, array $hadda): array
    {
        if (Charts::signLord($sign) === $p) { return ['grade' => 'उत्तम', 'why' => 'स्वगृह']; }
        if ((self::EXALT[$p] ?? -1) === $sign) { return ['grade' => 'उत्तम', 'why' => 'स्वोच्च']; }

        $lon = $sign * 30.0 + $deg;
        if (self::haddaLord($sign, $deg, $hadda) === $p) { return ['grade' => 'मध्यम', 'why' => 'स्वहद्दा']; }
        if (Charts::signLord(Varga::sign('D3', $lon)) === $p) { return ['grade' => 'मध्यम', 'why' => 'स्वद्रेष्काण']; }
        if (Charts::signLord(Charts::navamsaSignIndex($lon)) === $p) { return ['grade' => 'मध्यम', 'why' => 'स्वनवांश']; }

        if ((self::DEBIL[$p] ?? -1) === $sign) { return ['grade' => 'अधम', 'why' => 'नीच-राशि']; }
        if (PlanetCondition::naturalRelationDirected($p, Charts::signLord($sign)) === 'E') {
            return ['grade' => 'अधम', 'why' => 'शत्रु-राशि'];
        }
        return ['grade' => 'सम', 'why' => 'पद-हीन'];
    }

    /** Hadda (Egyptian term) lord of a degree within a sign. */
    public static function haddaLord(int $sign, float $deg, array $hadda): ?string
    {
        foreach (($hadda[$sign] ?? []) as $band) {
            if ($deg < (float) $band[0]) { return (string) $band[1]; }
        }
        return null;
    }

    /**
     * निर्बल (weak) — for Radda/Duraph: combust (≥40%), debilitated, enemy sign,
     * retrograde, or sitting on Rahu's mouth/tail (tight node conjunction ≤3°).
     *
     * @return array{weak:bool,why:list<string>}
     */
    public static function nirbal(string $p, array $planets, int $ascSign): array
    {
        $why = [];
        $sgn = (int) ($planets[$p]['sign_index'] ?? 0);
        $dg = (float) ($planets[$p]['deg_in_sign'] ?? 0.0);
        if ((bool) ($planets[$p]['retro'] ?? false)) { $why[] = 'वक्री'; }
        $comb = PlanetCondition::combustion($p, $planets);
        if ($comb !== null && (int) $comb['pct'] >= 40) { $why[] = 'अस्त ' . (int) $comb['pct'] . '%'; }
        $digTier = PlanetCondition::dignity($p, $sgn, $dg, $planets, $ascSign)['tier'] ?? '';
        if ($digTier === 'debil') { $why[] = 'नीच'; }
        if (in_array($digTier, ['enemy', 'great_enemy'], true)) { $why[] = 'शत्रु-राशि'; }
        foreach (['Rahu', 'Ketu'] as $node) {
            if (!isset($planets[$node])) { continue; }
            $sep = abs(fmod((float) $planets[$p]['sidereal_lon'] - (float) $planets[$node]['sidereal_lon'], 360.0));
            $sep = min($sep, 360.0 - $sep);
            if ($sep <= 3.0) { $why[] = ($node === 'Rahu' ? 'राहु-मुख' : 'राहु-पुच्छ') . ' पर'; break; }
        }
        return ['weak' => $why !== [], 'why' => $why];
    }

    // ---- record helpers --------------------------------------------------------

    /** Base yoga record from the editable definitions. @return array<string,mixed> */
    private static function rec(string $key, array $defs, array $extra): array
    {
        $d = $defs[$key] ?? [];
        $rec = [
            'yoga_key' => $key,
            'name_hi' => (string) ($d['name_hi'] ?? $key),
            'nature' => (string) ($d['nature'] ?? 'शुभ'),
            'lakshan' => (string) ($d['lakshan'] ?? ''),
            'phal' => (string) ($d['phal_text'] ?? ''),
            'subtype' => null, 'participants' => [], 'mediator' => null,
            'detail' => '', 'tags' => [],
            'score' => (($d['nature'] ?? 'शुभ') === 'अशुभ') ? -1 : 1,
        ];
        $rec = array_merge($rec, $extra);
        // {between} token in the ithasala phal → the two participants.
        if (str_contains($rec['phal'], '{between}')) {
            $names = array_map(static fn($x) => self::hi($x), $rec['participants']);
            $rec['phal'] = str_replace('{between}', implode('–', $names), $rec['phal']);
        }
        return $rec;
    }

    private static function minGrade(string $a, string $b): string
    {
        return (self::ADHIKAR_RANK[$a] ?? 0) <= (self::ADHIKAR_RANK[$b] ?? 0) ? $a : $b;
    }

    /** "शुक्र 12°04′ → शनि 18°30′ · अन्तर 6°26′ ≤ दीप्तांश 7° · दृष्टि 45.0 कला (प्रत्यक्ष स्नेह)" */
    private static function pairLine(string $fast, string $slow, float $fd, float $sd, float $gap, float $orb, ?array $cell): string
    {
        $s = self::hi($fast) . ' ' . self::dms($fd) . ' → ' . self::hi($slow) . ' ' . self::dms($sd)
            . ' · अन्तर ' . self::dms($gap) . ' ≤ दीप्तांश ' . rtrim(rtrim(number_format($orb, 1), '0'), '.') . '°';
        if ($cell !== null) {
            $s .= ' · दृष्टि ' . $cell['kala'] . ' कला (' . $cell['type_hi'] . ')';
        }
        return $s;
    }

    public static function hi(string $planet): string
    {
        return self::PLANET_HI[$planet] ?? $planet;
    }

    /** Degrees → D°MM′. */
    public static function dms(float $deg): string
    {
        $d = (int) floor($deg);
        $m = (int) round(($deg - $d) * 60.0);
        if ($m === 60) { $d++; $m = 0; }
        return $d . '°' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . '′';
    }
}
