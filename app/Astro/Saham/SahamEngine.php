<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Saham;

use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * Saham (Tajik Neelkanthi) engine — computes the 50 classical Sahams from a
 * Varshaphal (annual) chart and grades each one's sahamesh (lord). No new
 * astronomy: it reads the Varshaphal engine's longitudes / lagna / houses /
 * day-night flag and reuses the shared {@see PlanetCondition} dignity &
 * combustion services plus the varsha Shadbala. Formulae are data-driven
 * (migration 015 saham_definitions); this class only resolves tokens, applies
 * sekata / the three special handlers, and selects the phal.
 */
final class SahamEngine
{
    private const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];
    private const RASHI_HI = [
        'मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या',
        'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुंभ', 'मीन',
    ];

    /**
     * @param array<string,mixed> $vp     Varshaphal::compute output
     * @param array<string,mixed> $rules  SahamRepository::load (defs/phal/config)
     * @param string|null $activeMuddaLord the running Mudda mahadasha lord (marks related sahams)
     * @return array{sahams: list<array<string,mixed>>, is_day: bool, active_lord: ?string}
     */
    public static function compute(array $vp, array $rules, ?string $activeMuddaLord = null): array
    {
        $chart = $vp['varsha_chart'] ?? [];
        $planets = $chart['planets'] ?? [];
        $ascLon = (float) ($chart['ascendant']['sidereal_lon'] ?? 0.0);
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $isDay = (bool) ($chart['is_day'] ?? true);
        $lagnaLord = Charts::signLord($ascSign);
        $shadbala = $chart['shadbala'] ?? [];
        $mudda = $vp['mudda_dasha'] ?? [];
        $defs = $rules['defs'] ?? [];        // ordered by seq (dependency-safe)
        $phal = $rules['phal'] ?? [];

        $norm = static fn(float $d): float => fmod(fmod($d, 360.0) + 360.0, 360.0);
        $lon = static fn(string $p): float => (float) ($planets[$p]['sidereal_lon'] ?? 0.0);
        $houseStart = static fn(int $hh): float => (float) (((($ascSign + $hh - 1) % 12) + 12) % 12) * 30.0;
        $houseLord = static fn(int $hh): string => Charts::signLord((($ascSign + $hh - 1) % 12 + 12) % 12);

        $computed = [];   // saham_key => longitude (for PUNYA/GURU/VIDYA deps)
        $resolve = function (string $tok) use ($lon, $ascLon, $lagnaLord, $planets, $houseStart, $houseLord, &$computed): float {
            switch ($tok) {
                case 'SUN': return $lon('Sun');
                case 'MOON': return $lon('Moon');
                case 'MARS': return $lon('Mars');
                case 'MERCURY': return $lon('Mercury');
                case 'JUPITER': return $lon('Jupiter');
                case 'VENUS': return $lon('Venus');
                case 'SATURN': return $lon('Saturn');
                case 'LAGNA': return $ascLon;
                case 'LAGNA_LORD': return $lon($lagnaLord);
                case 'H2': return $houseStart(2);
                case 'H6': return $houseStart(6);
                case 'H8': return $houseStart(8);
                case 'H9': return $houseStart(9);
                case 'H11': return $houseStart(11);
                case 'H2_LORD': return $lon($houseLord(2));
                case 'H9_LORD': return $lon($houseLord(9));
                case 'SUN_SIGN_LORD': return $lon(Charts::signLord((int) ($planets['Sun']['sign_index'] ?? 0)));
                case 'MOON_SIGN_LORD': return $lon(Charts::signLord((int) ($planets['Moon']['sign_index'] ?? 0)));
                case 'SUN_EXALT': return 10.0;
                case 'MOON_EXALT': return 33.0;
                case 'KARKARDHA': return 105.0;
                case 'PUNYA': return $computed['punya'] ?? 0.0;
                case 'GURU_SAHAM': return $computed['guru'] ?? 0.0;
                case 'VIDYA_SAHAM': return $computed['gyan'] ?? 0.0;
                default: return 0.0;
            }
        };
        // C lies on the zodiacal arc walking forward from B (शोध्य) to A (शुद्धाश्रय)?
        $inArc = static fn(float $c, float $b, float $a): bool => $norm($c - $b) <= $norm($a - $b);

        $out = [];
        foreach ($defs as $d) {
            $key = (string) $d['saham_key'];
            if ($isDay) { $A = $d['a_day']; $B = $d['b_day']; $C = $d['c_day']; }
            else { $A = $d['a_night']; $B = $d['b_night']; $C = $d['c_night']; }
            $sekata = (int) $d['sekata'] === 1;

            // ---- special handlers (§1.4) ----
            if ($key === 'samarthya' && $lagnaLord === 'Mars') {           // Mars IS lagna lord
                $A = 'JUPITER'; $B = 'MARS'; $C = 'LAGNA';
            }
            if ($key === 'manmatha' && $lagnaLord === 'Moon') {            // Moon IS lagna lord
                $A = 'SUN'; $B = 'LAGNA_LORD'; $C = 'LAGNA'; $sekata = false;
            }

            $la = $resolve((string) $A); $lb = $resolve((string) $B); $lc = $resolve((string) $C);
            $raw = $norm($la - $lb + $lc);
            $raw_presekata = $raw;
            if ($sekata && !$inArc($lc, $lb, $la)) {
                $raw = $norm($raw + 30.0);
            }
            $computed[$key] = $raw;

            $si = (int) floor($raw / 30.0) % 12;
            $degIn = $raw - $si * 30.0;
            $house = (($si - $ascSign) % 12 + 12) % 12 + 1;
            $sahamesh = Charts::signLord($si);

            $out[$key] = [
                'key' => $key, 'seq' => (int) $d['seq'], 'name_hi' => (string) $d['name_hi'],
                'nature' => (string) $d['nature'], 'lon' => round($raw, 2), 'lon_presekata' => round($raw_presekata, 2),
                'sign_index' => $si, 'rashi_hi' => self::RASHI_HI[$si] ?? '', 'deg' => self::dms($degIn),
                'house' => $house, 'sahamesh' => $sahamesh, 'sahamesh_hi' => self::PLANET_HI[$sahamesh] ?? $sahamesh,
                'signifies' => (string) ($phal[$key]['signifies'] ?? ''),
                'explain' => (string) ($phal[$key]['explain'] ?? ''),
            ];
        }

        // ---- sahamesh strength → verdict → phal → timing ----
        foreach ($out as $key => &$s) {
            $lord = $s['sahamesh'];
            $ratio = (float) ($shadbala[$lord]['ratio'] ?? 0.0);
            $passShad = $ratio >= 1.0;
            $lordSign = (int) ($planets[$lord]['sign_index'] ?? 0);
            $lordDeg = (float) ($planets[$lord]['deg_in_sign'] ?? 0.0);
            $dig = PlanetCondition::dignity($lord, $lordSign, $lordDeg, $planets, $ascSign);
            $debil = ($dig['tier'] ?? '') === 'debil';
            $comb = PlanetCondition::combustion($lord, $planets);
            $combPct = $comb !== null ? (float) $comb['pct'] : 0.0;
            $condB = !($debil || $combPct >= 40.0);

            $verdict = ($passShad && $condB) ? 'anukul' : ((!$passShad && !$condB) ? 'pratikul' : 'mishrit');
            $ashubh = $s['nature'] === 'ashubh';
            $anukul = (string) ($phal[$key]['phal_anukul'] ?? '');
            $pratikul = (string) ($phal[$key]['phal_pratikul'] ?? '');
            if ($verdict === 'mishrit') {
                $s['phal'] = array_values(array_filter([$anukul, $pratikul]));
            } elseif (!$ashubh) {
                $s['phal'] = [$verdict === 'anukul' ? $anukul : $pratikul];
            } else {
                $s['phal'] = [$verdict === 'anukul' ? $pratikul : $anukul];  // ashubh: strong lord = relief
            }
            // Outcome tone is the same for every nature (strong lord = favourable).
            $s['verdict'] = $verdict;
            $s['tone'] = $verdict === 'anukul' ? 'pos' : ($verdict === 'pratikul' ? 'neg' : 'info');
            $s['verdict_hi'] = $verdict === 'anukul' ? 'अनुकूल' : ($verdict === 'pratikul' ? 'प्रतिकूल' : 'मिश्रित');
            $s['facts'] = [
                'shadbala' => round($ratio, 2), 'pass' => $passShad,
                'debil' => $debil, 'combust' => (int) round($combPct),
            ];

            // Timing: the sahamesh's Mudda-dasha period (reliable). The classical
            // degree×udayamana÷300 day count is a documented refinement.
            $mp = null;
            foreach ($mudda as $md) {
                if (($md['lord'] ?? '') === $lord) { $mp = ['lord' => $lord, 'start_jd' => (float) $md['start_jd'], 'end_jd' => (float) $md['end_jd']]; break; }
            }
            $s['timing_mudda'] = $mp;

            // Related to the active Mudda mahadasha planet? (as sahamesh, or placed in the saham's sign)
            $activeInSign = $activeMuddaLord !== null && (int) ($planets[$activeMuddaLord]['sign_index'] ?? -1) === $s['sign_index'];
            $s['related'] = $activeMuddaLord !== null && ($lord === $activeMuddaLord || $activeInSign);
            $s['related_why'] = $s['related']
                ? ($lord === $activeMuddaLord ? 'सहमेश' : 'ग्रह-स्थिति') : '';
        }
        unset($s);

        return ['sahams' => array_values($out), 'is_day' => $isDay, 'active_lord' => $activeMuddaLord];
    }

    /** Degrees → D°M' string. */
    private static function dms(float $deg): string
    {
        $d = (int) floor($deg);
        $m = (int) round(($deg - $d) * 60.0);
        if ($m === 60) { $d++; $m = 0; }
        return $d . '°' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . "'";
    }
}
