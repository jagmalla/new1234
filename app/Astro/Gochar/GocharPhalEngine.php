<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Gochar;

use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * Gochar (transit) prediction engine — Gochar Vichar Ch.1–3.
 *
 * LAYER 1 (from Chandra Lagna): every transiting planet's house counted from
 *   the natal Moon → shubh/ashubh + the house text; the Ksheen-Chandra rule
 *   (Moon 2/5/9 ashubh only when weak) and the Ch.1 special modifiers.
 * LAYER 2 (modifiers, as annotations on each Layer-1 event): Vedha (blocks a
 *   shubh result; Viparita-vedha turns an ashubh one shubh), Ashtakavarga BAV
 *   bindu intensity, and the degree-timing third.
 * LAYER 3 (transit over natal planets): a transiting planet crossing a natal
 *   planet's trigger positions → the combination text (Rahu reads as Saturn,
 *   Ketu as Mars, Mercury by proxy).
 *
 * Pure computation over the already-built natal chart + a transit snapshot;
 * no astronomy is recomputed here.
 */
final class GocharPhalEngine
{
    private const BODIES = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];
    private const CLASSICAL = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];

    /**
     * @param array<string,mixed> $natal   CalculationEngine::computeChart output
     * @param array<string,array<string,mixed>> $transits per-planet transit snapshot
     *        (sign_index, deg_in_sign, sidereal_lon, retro). house_from_moon is
     *        derived here so callers need not supply it.
     * @param array<string,mixed> $rules   GocharRepository::load output
     * @return array<string,mixed>
     */
    public static function compute(array $natal, array $transits, array $rules, ?float $transitJd = null): array
    {
        $cfg = $rules['config'] ?? [];
        $housePhal = $rules['house_phal'] ?? [];
        $natalPlanets = $natal['planets'] ?? [];
        $moonSign = (int) ($natalPlanets['Moon']['sign_index'] ?? 0);
        $bav = $natal['ashtakavarga']['bav'] ?? [];

        $tSign = static fn(string $p): int => (int) ($transits[$p]['sign_index'] ?? 0);
        $tDeg = static fn(string $p): float => (float) ($transits[$p]['deg_in_sign'] ?? 0.0);
        $tLon = static fn(string $p): float => (float) ($transits[$p]['sidereal_lon'] ?? 0.0);
        $houseFromMoon = static fn(int $sign): int => (($sign - $moonSign) % 12 + 12) % 12 + 1;

        // Transit Moon "ksheen" = within the orb (72°) of the transit Sun.
        $ksheenOrb = (float) ($cfg['gochar_ksheen_orb'] ?? 72);
        $sep = static function (float $a, float $b): float {
            $d = fmod(abs($a - $b), 360.0);
            return $d > 180.0 ? 360.0 - $d : $d;
        };
        $transitMoonKsheen = isset($transits['Moon'], $transits['Sun'])
            && $sep($tLon('Moon'), $tLon('Sun')) <= $ksheenOrb;

        // House occupancy of every transiting body (for vedha lookups).
        $bodyHouse = [];
        foreach (self::BODIES as $p) {
            if (isset($transits[$p])) { $bodyHouse[$p] = $houseFromMoon($tSign($p)); }
        }

        // ---------------- LAYER 1 + LAYER 2 ----------------
        $layer1 = [];
        foreach (self::BODIES as $p) {
            if (!isset($transits[$p])) { continue; }
            $house = $bodyHouse[$p];
            $entry = $housePhal[$p][$house] ?? null;
            if ($entry === null) { continue; }

            $shubh = (bool) $entry['shubh'];
            // C1: Ketu in the 5th — the auspicious list says shubh but the phal
            // text is malefic; the doc resolves in favour of the text (ashubh).
            if ($p === 'Ketu' && $house === 5) { $shubh = false; }

            $notes = [];

            // Ksheen-Chandra: Moon in 2/5/9 is ashubh only when weak.
            if ($p === 'Moon' && in_array($house, [2, 5, 9], true)) {
                if ($transitMoonKsheen) {
                    $notes[] = ['t' => 'neg', 'text' => 'क्षीण चन्द्र (सूर्य से ' . (int) $ksheenOrb . '° के भीतर) — इस भाव का अशुभ फल लागू।'];
                } else {
                    $shubh = true;
                    $notes[] = ['t' => 'pos', 'text' => 'बली/प्रकाशमय चन्द्र — इन भावों (2/5/9) में अशुभता नहीं, शुभ फल।'];
                }
            }

            // Ch.1 special modifiers.
            foreach (self::specialMods($p, $house, $transits, $bodyHouse, $moonSign) as $n) { $notes[] = $n; }

            // LAYER 2 — Vedha (may block a shubh / invert an ashubh).
            $vedhaTone = $shubh ? 'pos' : 'neg';
            if (($cfg['gochar_vedha'] ?? '1') === '1') {
                $v = self::vedha($p, $house, $bodyHouse);
                if ($v !== null) {
                    if ($shubh) {
                        $vedhaTone = 'block';
                        $notes[] = ['t' => 'neg', 'text' => 'वेध — ' . self::hi($v) . ' के वेध से इस शुभ फल में रुकावट (फल रुका)।'];
                    } else {
                        $vedhaTone = 'pos';
                        $notes[] = ['t' => 'pos', 'text' => 'विपरीत वेध — ' . self::hi($v) . ' के वेध से अशुभता का नाश; फल शुभ हो जाता है।'];
                    }
                }
            }

            // LAYER 2 — Ashtakavarga BAV bindu intensity (7 classical only).
            if (($cfg['gochar_av_bindu'] ?? '1') === '1' && isset($bav[$p])) {
                $b = (int) ($bav[$p][$tSign($p)] ?? 0);
                $notes[] = ['t' => $b > 4 ? 'pos' : ($b === 0 ? 'neg' : ($b < 4 ? 'neg' : 'info')),
                    'text' => 'अष्टकवर्ग बिन्दु ' . $b . '/8 — ' . (
                        $b === 0 ? 'फल बहुत ही अशुभ।'
                        : ($b > 4 ? 'शुभ फल प्रबल / अशुभ ग्रह की अशुभता बहुत कुछ दूर।'
                        : 'बिन्दु कम — अशुभता अधिक।'))];
            }

            // LAYER 2 — Degree-timing third.
            [$from, $to] = GocharRepository::DEGREE_TIMING[$p] ?? [0, 30];
            $active = $tDeg($p) >= $from && $tDeg($p) < $to;
            $whole = $from === 0 && $to === 30;
            $notes[] = ['t' => 'info', 'text' => $whole
                ? 'तृतीयांश: सम्पूर्ण राशि में समान फल।'
                : ('तृतीयांश ' . (int) $from . '°–' . (int) $to . '°: ' . ($active ? 'अभी सक्रिय — विशेष प्रभावी।' : 'फल इसी अंश-खण्ड में प्रबल होगा।'))];

            if ((bool) ($transits[$p]['retro'] ?? false) && !in_array($p, ['Rahu', 'Ketu'], true)) {
                $notes[] = ['t' => 'info', 'text' => 'वक्री — मार्गी/वक्री सन्धि पर विशेष प्रभाव (Ch.2 N9)।'];
            }

            $tone = $vedhaTone === 'block' ? 'block' : ($shubh ? 'pos' : 'neg');
            $layer1[] = [
                'planet' => $p, 'planet_hi' => self::hi($p),
                'house' => $house, 'shubh' => $shubh, 'tone' => $tone,
                'sign_index' => $tSign($p), 'deg' => self::dms($tDeg($p)),
                'retro' => (bool) ($transits[$p]['retro'] ?? false),
                'text' => (string) $entry['text'],
                'shubh_houses' => GocharRepository::SHUBH[$p] ?? [],
                'notes' => $notes,
            ];
        }

        // ---------------- LAYER 3 ----------------
        $layer3 = [];
        if (($cfg['gochar_natal_layer'] ?? '1') === '1') {
            $layer3 = self::layer3($transits, $natalPlanets, $rules['natal_combo'] ?? [], $tSign);
        }

        // ---------------- LAYER 4 — Shani Sade Sati / Paya (additive overlay) ----------------
        // Detection + severity + Paya from the natal Moon vs transit Saturn. This
        // does NOT alter any Layer-1 Saturn text (C16); the Paya score is kept
        // separate (C15).
        $shaniSpecial = null;
        if (isset($transits['Saturn']) && ($cfg['sadesati_show'] ?? '1') === '1') {
            $ssRules = SadeSatiRepository::load((string) ($cfg['lang'] ?? 'hi'));
            $running = null; $age = 0.0;
            $natalJd = (float) ($natal['meta']['jd_ut'] ?? 0.0);
            $moonLon = (float) ($natalPlanets['Moon']['sidereal_lon'] ?? 0.0);
            if ($transitJd !== null && $natalJd > 0.0) {
                $age = ($transitJd - $natalJd) / 365.2425;
                $chain = \AutoBusiness\Astro\Calc\VimshottariDasha::running($moonLon, $natalJd, $transitJd);
                $running = $chain['maha'] ?? null;   // running Mahadasha lord at the transit date
            }
            $shaniSpecial = SadeSatiEngine::compute($natal, $transits['Saturn'], $running, $age, $ssRules);
        }

        return [
            'moon_sign' => $moonSign,
            'moon_ksheen' => $transitMoonKsheen,
            'layer1' => $layer1,
            'layer3' => $layer3,
            'shani_special' => $shaniSpecial,
            'has_av' => $bav !== [],
        ];
    }

    /**
     * LAYER 3 — transit bodies crossing natal planets' trigger positions.
     *
     * @param array<string,array<string,mixed>> $transits
     * @param array<string,array<string,mixed>> $natalPlanets
     * @param array<string,array<string,list<array{cond:string,text:string}>>> $combo
     * @return list<array<string,mixed>>
     */
    private static function layer3(array $transits, array $natalPlanets, array $combo, callable $tSign): array
    {
        $groups = [];
        foreach (self::BODIES as $t) {
            if (!isset($transits[$t])) { continue; }
            $triggers = GocharRepository::TRIGGER[$t] ?? [];
            $src = GocharRepository::TEXT_SOURCE[$t] ?? $t;   // Rahu→Saturn, Ketu→Mars
            $events = [];
            foreach (self::CLASSICAL as $n) {
                if (!isset($natalPlanets[$n])) { continue; }
                $nSign = (int) $natalPlanets[$n]['sign_index'];
                $pos = (($tSign($t) - $nSign) % 12 + 12) % 12 + 1;   // house of transit from natal
                if (!in_array($pos, $triggers, true)) { continue; }
                $rows = $combo[$src][$n] ?? [];
                if ($rows === []) { continue; }
                $events[] = [
                    'natal' => $n, 'natal_hi' => self::hi($n),
                    'pos' => $pos, 'geometry' => self::geometry($pos),
                    'rows' => $rows,
                ];
            }
            if ($events === []) { continue; }

            // Informational "(All)" notes for the chhaya/proxy planets.
            $allNotes = [];
            foreach ([$t, 'Rahu/Ketu'] as $ak) {
                foreach (($combo[$ak]['(All)'] ?? []) as $r) {
                    if (($t === 'Rahu' || $t === 'Ketu') || $ak === $t) { $allNotes[] = $r['text']; }
                }
            }
            $groups[] = [
                'transit' => $t, 'transit_hi' => self::hi($t),
                'nature' => in_array($t, GocharRepository::ASURI, true) ? 'आसुरी' : 'दैवी',
                'reads_as' => $src !== $t ? self::hi($src) : null,   // Rahu→शनि, Ketu→मंगल
                'all_notes' => $allNotes,
                'events' => $events,
            ];
        }
        return $groups;
    }

    /**
     * Ch.1 special modifiers for a Layer-1 event.
     *
     * @param array<string,array<string,mixed>> $transits
     * @param array<string,int> $bodyHouse
     * @return list<array{t:string,text:string}>
     */
    private static function specialMods(string $p, int $house, array $transits, array $bodyHouse, int $moonSign): array
    {
        $out = [];
        if ($p === 'Mars' && $house === 6) {
            $dig = PlanetCondition::dignity('Mars', (int) $transits['Mars']['sign_index'], (float) $transits['Mars']['deg_in_sign'], $transits, $moonSign);
            $good = in_array($dig['tier'] ?? '', ['param_uchcha', 'exalt', 'moolatrikona', 'own', 'great_friend', 'friend'], true);
            $out[] = ['t' => $good ? 'pos' : 'neg', 'text' => $good
                ? 'षष्ठस्थ मंगल उच्च/स्व/मित्र राशि में — स्वास्थ्य ठीक।'
                : 'षष्ठस्थ मंगल उच्चादि राशि में नहीं — स्वास्थ्य दोष सम्भव।'];
        }
        if ($p === 'Moon' && $house === 7 && isset($bodyHouse['Venus'])
            && in_array($bodyHouse['Venus'], GocharRepository::SHUBH['Venus'], true)) {
            $out[] = ['t' => 'pos', 'text' => 'चन्द्र सप्तम + शुक्र भी शुभ फल दे रहा — फल विशेष उत्तम।'];
        }
        if ($p === 'Mars' && $house === 10) {
            $out[] = ['t' => 'info', 'text' => 'वाराही संहिता — धन प्राप्ति गोचर अवधि के उत्तरार्ध में।'];
        }
        if ($p === 'Rahu' && in_array($house, [1, 5, 9], true)) {
            $out[] = ['t' => 'info', 'text' => 'राहु शुभ, परन्तु चन्द्र-शत्रुता/दृष्टि के कारण मानसिक व्यथा भी।'];
        }
        return $out;
    }

    /**
     * Vedha check: returns the blocking transit planet, or null. Self-vedha
     * (vedha house == the planet's own house) and the father/son exception
     * pairs are skipped.
     *
     * @param array<string,int> $bodyHouse
     */
    private static function vedha(string $p, int $house, array $bodyHouse): ?string
    {
        $vHouse = GocharRepository::VEDHA[$p][$house - 1] ?? $house;
        if ($vHouse === $house) { return null; }   // self-vedha
        foreach ($bodyHouse as $q => $qh) {
            if ($q === $p || $qh !== $vHouse) { continue; }
            foreach (GocharRepository::VEDHA_EXCEPT as [$a, $b]) {
                if (($p === $a && $q === $b) || ($p === $b && $q === $a)) { continue 2; }
            }
            return $q;
        }
        return null;
    }

    /** Aspect/conjunction label for a transit-from-natal house position. */
    private static function geometry(int $pos): string
    {
        return match ($pos) {
            1 => 'युति',
            7 => 'सप्तम दृष्टि',
            6 => 'अष्टम दृष्टि',
            10 => 'चतुर्थ दृष्टि',
            5 => 'नवम दृष्टि',
            9 => 'पंचम दृष्टि',
            4 => 'दशम दृष्टि',
            11 => 'तृतीय दृष्टि',
            default => $pos . 'वें से',
        };
    }

    private static function hi(string $planet): string
    {
        return GocharRepository::PLANET_HI[$planet] ?? $planet;
    }

    /** Degrees -> D°MM'. */
    private static function dms(float $deg): string
    {
        $d = (int) floor($deg);
        $m = (int) round(($deg - $d) * 60.0);
        if ($m === 60) { $d++; $m = 0; }
        return $d . '°' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . "'";
    }
}
