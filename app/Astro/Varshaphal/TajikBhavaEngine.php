<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Varshaphal;

use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * Tajik-Neelakanthi Bhava-Phal engine (Varshatantra, Bhava-vichara-adhyaya).
 *
 * Returns the full 262-rule catalogue grouped by bhava, and auto-marks the
 * subset whose conditions are RELIABLY computable against the annual (varsha)
 * chart — planet-in-house, house-lord placement, varshesh/panchadhikari office,
 * combustion (अस्त/दग्ध), retrograde, dignity (उच्च/नीच/स्व), Panchavargeeya-bala
 * tier, pad (janma rashi) and Tajik whole-sign drishti (मित्र ३/५/९/११ ·
 * श्रुत १/४/७/१०). Rules needing itthashala/hadda/sahams alone are left as
 * reference (matched = null) — never auto-fired — so no false positives.
 *
 * Each output rule carries matched ∈ {true, false, null}:
 *   true  — condition is computable AND satisfied this year (इस वर्ष लागू)
 *   false — computable but not satisfied (संगणित, पर लागू नहीं)
 *   null  — reference-only (शास्त्र-सन्दर्भ; ज्योतिषी स्वयं विचार करें)
 */
final class TajikBhavaEngine
{
    public const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];
    private const PAAP = ['Sun', 'Mars', 'Saturn', 'Rahu', 'Ketu'];
    private const SHUBH = ['Jupiter', 'Venus', 'Mercury', 'Moon'];
    /** Tajik full-drishti house distances (conjunction + मित्र + श्रुत). */
    private const ASPECT = [1, 3, 4, 5, 7, 9, 10, 11];
    private const STRONG20 = 10.0;   // Panchavargeeya bala (of 20) — बलवान
    private const WEAK20 = 5.0;       // निर्बल/नष्टबली

    /**
     * @param array<string,mixed> $vp     Varshaphal::compute output
     * @param array<string,mixed> $natal  natal chart (for pad = janma rashi)
     * @param array<string,mixed> $rules  TajikBhavaRepository::load output
     * @return array<string,mixed>
     */
    public static function compute(array $vp, array $natal, array $rules): array
    {
        $cfg = $rules['config'] ?? [];
        $ctx = self::context($vp, $natal);
        $autofire = ($cfg['tajik_bhava_autofire'] ?? '1') === '1';

        $groups = [];
        $matchedCount = 0;
        foreach (($rules['rules'] ?? []) as $r) {
            $m = $autofire ? self::matches((string) $r['id'], $ctx) : null;
            if ($m === true) { $matchedCount++; }
            $groups[(int) $r['h']][] = $r + ['matched' => $m];
        }
        ksort($groups);

        return [
            'context' => $ctx['summary'],
            'groups' => $groups,
            'matched_count' => $matchedCount,
            'total' => count($rules['rules'] ?? []),
            'bhava_hi' => TajikBhavaData::BHAVA_HI,
        ];
    }

    /**
     * Build the computed context (closures + a display summary) once.
     *
     * @return array<string,mixed>
     */
    private static function context(array $vp, array $natal): array
    {
        $vc = $vp['varsha_chart'] ?? [];
        $vpl = $vc['planets'] ?? [];
        $ascSign = (int) ($vc['ascendant']['sign_index'] ?? 0);
        $varshesh = (string) ($vp['varshesh']['lord'] ?? '');
        $table = $vp['varshesh']['table'] ?? [];

        // Panchadhikari planets (distinct across the five offices).
        $pancha = [];
        foreach (($vp['varshesh']['offices'] ?? []) as $o) { $pancha[(string) $o['planet']] = true; }

        // Muntha house from the varsha lagna.
        $natalAscSign = (int) ($natal['ascendant']['sign_index'] ?? 0);
        $age = (int) ($vp['age_completed'] ?? 0);
        $munthaSign = (($natalAscSign + $age) % 12 + 12) % 12;
        $munthaHouse = (($munthaSign - $ascSign) % 12 + 12) % 12 + 1;

        $houseOf = static fn(string $p): ?int => isset($vpl[$p]) ? (int) $vpl[$p]['house'] : null;
        $signOf = static fn(string $p): ?int => isset($vpl[$p]) ? (int) $vpl[$p]['sign_index'] : null;
        $natalSign = static fn(string $p): ?int => isset($natal['planets'][$p]) ? (int) $natal['planets'][$p]['sign_index'] : null;
        $lordOfHouse = static fn(int $h): string => Charts::signLord((($ascSign + $h - 1) % 12 + 12) % 12);
        $bala20 = static fn(string $p): float => (float) ($table[$p]['total20'] ?? 0.0);
        $dist = static fn(int $a, int $b): int => (($b - $a) % 12 + 12) % 12 + 1;

        $combust = static fn(string $p): bool => PlanetCondition::combustion($p, $vpl) !== null;
        $retro = static fn(string $p): bool => (bool) ($vpl[$p]['retro'] ?? false);
        $dignity = static function (string $p) use ($vpl, $ascSign): string {
            if (!isset($vpl[$p])) { return 'neutral'; }
            return PlanetCondition::dignity($p, (int) $vpl[$p]['sign_index'], (float) ($vpl[$p]['deg_in_sign'] ?? 0.0), $vpl, $ascSign)['tier'] ?? 'neutral';
        };

        // Tajik drishti / conjunction of a benefic/malefic GROUP onto a house.
        $influence = static function (int $h, array $group, ?string $exclude) use ($vpl, $houseOf, $dist): bool {
            foreach ($group as $q) {
                if ($q === $exclude || !isset($vpl[$q])) { continue; }
                $qh = $houseOf($q);
                if ($qh !== null && in_array($dist($qh, $h), self::ASPECT, true)) { return true; }
            }
            return false;
        };
        $paapInfl = static fn(int $h, ?string $ex = null): bool => $influence($h, self::PAAP, $ex);
        $shubhInfl = static fn(int $h, ?string $ex = null): bool => $influence($h, self::SHUBH, $ex);
        // पाप-पीड़ित/पापयुक्त-दृष्ट: another malefic aspects/conjoins the planet's house.
        $afflicted = static fn(string $p): bool => ($houseOf($p) !== null) && $paapInfl($houseOf($p), $p);

        $tierHi = static fn(float $b): string => $b >= self::STRONG20 ? 'बलवान' : ($b < self::WEAK20 ? 'निर्बल' : 'मध्यम');

        // Display summary (top of the pane).
        $summary = [
            'varshesh' => $varshesh,
            'varshesh_hi' => self::PLANET_HI[$varshesh] ?? $varshesh,
            'varsha_lagna_sign' => Charts::SIGNS[$ascSign] ?? '',
            'muntha_sign' => Charts::SIGNS[$munthaSign] ?? '',
            'muntha_house' => $munthaHouse,
            'munthesh' => Charts::signLord($munthaSign),
            'pancha' => array_keys($pancha),
            'lagnesh' => $lordOfHouse(1),
            'lagnesh_tier' => $tierHi($bala20($lordOfHouse(1))),
        ];

        return [
            'summary' => $summary,
            'varshesh' => $varshesh,
            'isVarshesh' => static fn(string $p): bool => $p === $varshesh,
            'isPancha' => static fn(string $p): bool => isset($pancha[$p]),
            'houseOf' => $houseOf, 'signOf' => $signOf, 'natalSign' => $natalSign,
            'lordOfHouse' => $lordOfHouse, 'bala20' => $bala20,
            'combust' => $combust, 'retro' => $retro, 'dignity' => $dignity,
            'paapInfl' => $paapInfl, 'shubhInfl' => $shubhInfl, 'afflicted' => $afflicted,
            'munthaHouse' => $munthaHouse, 'natalAscSign' => $natalAscSign,
            'strong' => static fn(string $p): bool => $bala20($p) >= self::STRONG20,
            'weak' => static fn(string $p): bool => $bala20($p) < self::WEAK20,
            'exaltOwn' => static fn(string $t): bool => in_array($t, ['param_uchcha', 'exalt', 'moolatrikona', 'own'], true),
        ];
    }

    /**
     * The curated, high-confidence predicate map. Returns null for any rule not
     * auto-evaluated (kept as reference). Every predicate uses only unambiguous,
     * already-verified primitives.
     */
    private static function matches(string $id, array $ctx): ?bool
    {
        /** @var callable $houseOf */ $houseOf = $ctx['houseOf'];
        /** @var callable $signOf */ $signOf = $ctx['signOf'];
        /** @var callable $natalSign */ $natalSign = $ctx['natalSign'];
        /** @var callable $lordOfHouse */ $lordOfHouse = $ctx['lordOfHouse'];
        /** @var callable $isVarshesh */ $isVarshesh = $ctx['isVarshesh'];
        /** @var callable $isPancha */ $isPancha = $ctx['isPancha'];
        /** @var callable $combust */ $combust = $ctx['combust'];
        /** @var callable $retro */ $retro = $ctx['retro'];
        /** @var callable $dignity */ $dignity = $ctx['dignity'];
        /** @var callable $paapInfl */ $paapInfl = $ctx['paapInfl'];
        /** @var callable $shubhInfl */ $shubhInfl = $ctx['shubhInfl'];
        /** @var callable $afflicted */ $afflicted = $ctx['afflicted'];
        /** @var callable $strong */ $strong = $ctx['strong'];
        /** @var callable $weak */ $weak = $ctx['weak'];
        /** @var callable $exaltOwn */ $exaltOwn = $ctx['exaltOwn'];
        $mh = (int) $ctx['munthaHouse'];
        $hIn = static fn(?int $h, array $set): bool => $h !== null && in_array($h, $set, true);

        switch ($id) {
            // ---- Bhava 1 (तनु): lagnesh strength + adhikaari nashtabali ----
            case 'B01_04': return $strong($lordOfHouse(1));
            case 'B01_05': return !$strong($lordOfHouse(1)) && !$weak($lordOfHouse(1));
            case 'B01_06': return $weak($lordOfHouse(1));
            case 'B01_07': return $isPancha('Sun') && $weak('Sun');
            case 'B01_08': return $isPancha('Moon') && $weak('Moon');
            case 'B01_09': return $isPancha('Mars') && $weak('Mars');
            case 'B01_10': return $isPancha('Mercury') && $weak('Mercury');
            case 'B01_11': return $isPancha('Jupiter') && $weak('Jupiter');
            case 'B01_12': return $isPancha('Venus') && $weak('Venus');
            case 'B01_13': return $isPancha('Saturn') && $weak('Saturn');
            case 'B01_14': // varsha lagna has a malefic tenant AND no benefic influence
                $paapIn1 = false;
                foreach (self::PAAP as $q) { if ($houseOf($q) === 1) { $paapIn1 = true; break; } }
                return $paapIn1 && !$shubhInfl(1);

            // ---- Bhava 2 (धन) ----
            case 'B02_07': return $houseOf('Jupiter') !== null && $afflicted('Jupiter') && $hIn($houseOf('Jupiter'), [2, 8]);
            case 'B02_21': return $houseOf('Saturn') === 2;

            // ---- Bhava 3 (सहज) ----
            case 'B03_05': return $houseOf('Jupiter') === 3 && $weak('Jupiter');
            case 'B03_08': return $houseOf('Jupiter') === 3 && !$weak('Jupiter'); // general benefic reading

            // ---- Bhava 4 (सुख) ----
            case 'B04_01': return $houseOf('Sun') === 4 && $afflicted('Sun');
            case 'B04_02': return $houseOf('Moon') === 4 && $afflicted('Moon');
            case 'B04_03': return $houseOf('Sun') === 4 && $houseOf('Moon') === 4 && $afflicted('Sun') && $afflicted('Moon');
            case 'B04_06': return $houseOf($lordOfHouse(4)) === 4;

            // ---- Bhava 5 (सुत) ----
            case 'B05_01': return $isVarshesh('Jupiter') && $hIn($houseOf('Jupiter'), [5, 11]);
            case 'B05_02':
                foreach (['Sun', 'Mars', 'Mercury', 'Venus'] as $p) {
                    if ($isVarshesh($p) && $hIn($houseOf($p), [5, 11])) { return true; }
                }
                return false;
            case 'B05_15': return $houseOf('Mars') === 5 && $retro('Mars');

            // ---- Bhava 6 (रिपु) ----
            case 'B06_04': return $isVarshesh('Sun') && $houseOf('Sun') === 6 && $afflicted('Sun');
            case 'B06_06': return $isVarshesh('Mercury') && $houseOf('Mercury') === 6 && ($retro('Mercury') || $afflicted('Mercury'));
            case 'B06_32': return self::natalSixthLordMars($ctx);

            // ---- Bhava 7 (स्त्री) ----
            case 'B07_01': return $isVarshesh('Venus') && $houseOf('Venus') === 7 && $strong('Venus');
            case 'B07_17': return $houseOf('Sun') === 7 && $houseOf('Mars') === 7 && $mh === 7;

            // ---- Bhava 8 (मृत्यु) ----
            case 'B08_14': return $houseOf('Mars') === 8;
            case 'B08_15': return $houseOf('Mars') === 10;
            case 'B08_16': return $isVarshesh('Jupiter') && $hIn($houseOf('Jupiter'), [2, 8]) && $afflicted('Jupiter');
            case 'B08_17': return $houseOf('Saturn') === 7;
            case 'B08_23': return $houseOf('Mars') === 12 && $houseOf('Sun') === 2;
            case 'B08_29': return $houseOf('Mars') === 8 && $hIn($signOf('Mars'), [0, 4, 8]); // मेष/सिंह/धनु
            case 'B08_34': return $ctx['varshesh'] !== 'Mars' && $houseOf('Mars') === 8 && $houseOf($ctx['varshesh']) === 8;
            case 'B08_35': return $hIn($houseOf('Moon'), [6, 8, 12]);
            case 'B08_42':
                $jl = Charts::signLord((int) $ctx['natalAscSign']);
                return $houseOf($jl) === 8 && $afflicted($jl);

            // ---- Bhava 9 (भाग्य) ----
            case 'B09_01': return $isVarshesh('Mars') && $strong('Mars') && $hIn($houseOf('Mars'), [3, 9]);
            case 'B09_14': return !$isPancha('Saturn') && $houseOf('Saturn') === 9;

            // ---- Bhava 10 (राज्य) ----
            case 'B10_01': return $isVarshesh($ctx['varshesh']) && $strong($ctx['varshesh']) && $houseOf($ctx['varshesh']) === 10;
            case 'B10_05': return $houseOf('Sun') === 10 && $signOf('Sun') === 6 && $afflicted('Sun'); // तुला (Libra) नीच
            case 'B10_16': return $combust($lordOfHouse(9));
            case 'B10_17': return $combust($lordOfHouse(10));

            // ---- Bhava 11 (आय) ----
            case 'B11_06': return $isVarshesh('Jupiter') && $exaltOwn($dignity('Jupiter')) && $houseOf('Jupiter') === 7;

            // ---- Bhava 12 (व्यय) ----
            case 'B12_07': return $isVarshesh('Saturn') && $exaltOwn($dignity('Saturn')) && $houseOf('Saturn') === 10;
            case 'B12_08': return $isVarshesh('Sun') && $exaltOwn($dignity('Sun')) && $houseOf('Sun') === 10;
            case 'B12_09': return $isVarshesh('Mars') && $exaltOwn($dignity('Mars')) && $houseOf('Mars') === 10;
            case 'B12_10': return $isVarshesh('Mercury') && $exaltOwn($dignity('Mercury')) && $houseOf('Mercury') === 10;
            case 'B12_15': return $hIn($houseOf($lordOfHouse(8)), [6, 8, 12]);

            default: return null;   // reference-only
        }
    }

    /** B06_32 — natal sixth-lord is Mars AND Mars sits in varsha house 6. */
    private static function natalSixthLordMars(array $ctx): bool
    {
        $natalAscSign = (int) $ctx['natalAscSign'];
        $sixthSign = (($natalAscSign + 5) % 12 + 12) % 12;
        /** @var callable $houseOf */ $houseOf = $ctx['houseOf'];
        return Charts::signLord($sixthSign) === 'Mars' && $houseOf('Mars') === 6;
    }
}
