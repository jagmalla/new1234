<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Gochar;

use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * Functional-role + strength support (Update 05, Ch.5 "Dasha ka Swaroop").
 *
 * functionalRole() derives a planet's classical functional nature for a given
 * lagna from house-lordship (Ch.5 D1): trikona (1/5/9) lords are benefic,
 * 3/6/11 lords malefic, a planet owning BOTH a kendra (1/4/7/10) and a trikona
 * is Yogakaraka, the 2nd/7th lords are Marak; the kendradhipati-dosha flips a
 * natural benefic/malefic that owns only a kendra. This matches the
 * Lagna_Benefics table's yogakaraka column for the clear cases and is the
 * single service Sade Sati severity (S8/S13) resolves "yogakaraka / benefic
 * dasha-lord" through (C20/C22).
 *
 * isStrong() is the operational बलवान/निर्बल definition (D4/D5) over the natal
 * chart's own dignity / Ashtakavarga / kendra / combustion facts.
 */
final class DashaSupport
{
    private const KENDRA = [1, 4, 7, 10];
    private const TRIKONA = [1, 5, 9];
    private const DUSTHANA = [3, 6, 8, 11];   // functional-malefic ownership (11 badhaka-class)
    private const NAT_BENEFIC = ['Jupiter', 'Venus', 'Mercury', 'Moon'];

    /** Houses (1..12 from the lagna) a planet owns. */
    private static function ownedHouses(string $planet, int $lagnaSign): array
    {
        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            $sign = (($lagnaSign + $h - 1) % 12 + 12) % 12;
            if (Charts::signLord($sign) === $planet) { $out[] = $h; }
        }
        return $out;
    }

    /**
     * Functional role of a planet for a lagna.
     * @return array{role:string, yogakaraka:bool, benefic:bool, marak:bool, owns:list<int>}
     */
    public static function functionalRole(string $planet, int $lagnaSign): array
    {
        if (in_array($planet, ['Rahu', 'Ketu'], true)) {
            return ['role' => 'छाया', 'yogakaraka' => false, 'benefic' => false, 'marak' => false, 'owns' => []];
        }
        $owns = self::ownedHouses($planet, $lagnaSign);
        $ownsKendra = array_intersect($owns, self::KENDRA) !== [];
        $ownsTrikona = array_intersect(array_diff($owns, [1]), [5, 9]) !== [] || in_array(1, $owns, true);
        $ownsTrikonaStrict = array_intersect($owns, [5, 9]) !== [];
        $ownsDusthana = array_intersect($owns, self::DUSTHANA) !== [];
        $marak = array_intersect($owns, [2, 7]) !== [];

        // Yogakaraka: owns a kendra AND a trikona (the classic combination).
        $yogakaraka = $ownsKendra && $ownsTrikonaStrict;

        // Functional benefic/malefic.
        $benefic = false;
        if ($yogakaraka) {
            $benefic = true;
        } elseif ($ownsTrikonaStrict && !$ownsDusthana) {
            $benefic = true;                     // pure trikona lord
        } elseif ($ownsDusthana && !$ownsTrikonaStrict) {
            $benefic = false;                    // 3/6/8/11 lord
        } else {
            // kendra-only / 2-12 lord: kendradhipati dosha decides.
            $natBen = in_array($planet, self::NAT_BENEFIC, true);
            $kendraOnly = $ownsKendra && !$ownsTrikonaStrict && !$ownsDusthana;
            $benefic = $kendraOnly ? !$natBen : $natBen;
        }

        $role = $yogakaraka ? 'योगकारक' : ($marak && !$yogakaraka ? 'मारक' : ($benefic ? 'शुभ' : 'पापी'));
        return ['role' => $role, 'yogakaraka' => $yogakaraka, 'benefic' => $benefic, 'marak' => $marak, 'owns' => $owns];
    }

    /**
     * Operational strength (D4/D5) of a planet in a chart: a small signed score
     * and a boolean. Uses dignity tier, Ashtakavarga BAV bindu, kendra
     * placement and combustion — the facts the engine already computes.
     *
     * @param array<string,mixed> $chart CalculationEngine::computeChart
     * @return array{score:int, strong:bool, weak:bool}
     */
    public static function isStrong(string $planet, array $chart): array
    {
        $planets = $chart['planets'] ?? [];
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $p = $planets[$planet] ?? null;
        if ($p === null) { return ['score' => 0, 'strong' => false, 'weak' => false]; }
        $sign = (int) $p['sign_index']; $deg = (float) ($p['deg_in_sign'] ?? 0.0);
        $house = (int) ($p['house'] ?? 0);
        $s = 0;

        $tier = PlanetCondition::dignity($planet, $sign, $deg, $planets, $ascSign)['tier'] ?? 'neutral';
        if (in_array($tier, ['param_uchcha', 'exalt', 'moolatrikona', 'own', 'great_friend', 'friend'], true)) { $s += 2; }
        if (in_array($tier, ['debil', 'enemy', 'great_enemy'], true)) { $s -= 2; }

        $bav = $chart['ashtakavarga']['bav'][$planet][$sign] ?? null;
        if ($bav !== null) { $s += ((int) $bav) > 4 ? 1 : (((int) $bav) < 4 ? -1 : 0); }

        if (in_array($house, self::KENDRA, true)) { $s += 1; }
        if (in_array($house, [6, 8, 12], true)) { $s -= 1; }

        $comb = PlanetCondition::combustion($planet, $planets);
        if ($comb !== null && (int) $comb['pct'] >= 40) { $s -= 1; }

        $ratio = (float) ($chart['shadbala'][$planet]['ratio'] ?? 1.0);
        if ($ratio >= 1.0) { $s += 1; } else { $s -= 1; }

        return ['score' => $s, 'strong' => $s >= 2, 'weak' => $s <= -2];
    }
}
