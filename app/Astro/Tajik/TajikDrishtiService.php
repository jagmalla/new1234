<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Tajik;

/**
 * Tajik sphuta drishti (Tajik Neelkanthi, Drishti-phaladhyaya shloka 9–14).
 *
 * For every ordered planet pair (drashta → drishya) in the varsha chart the
 * aspect strength in KALA (0–60) is interpolated between the dhruvanka anchors:
 *   diff = norm360(drishya − drashta); r = floor(diff/30); d = diff mod 30
 *   kala = gat + d × (aishya − gat) / 30        [gat = dhruvanka(r), aishya = dhruvanka(r+1)]
 * The sign of (aishya − gat) IS the classical dhan/rin rule (shloka 11–12).
 * Each cell also carries the drishti TYPE (sneha/vair/none), the Tajik
 * positional maitri (shloka 13: 3/5/9/11 = मित्र, 1/4/7/10 = शत्रु, else सम)
 * and the vaam/dakshin flag (p.78–79: aspect landing in houses 7–12 from the
 * drashta = वाम, stronger than dakshin).
 *
 * Pure function over the computed chart — no astronomy is recomputed here.
 */
final class TajikDrishtiService
{
    public const PLANETS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];

    /** House-from-drashta sets (1-based, counted inclusively). */
    public const SNEHA_HOUSES = [3, 5, 9, 11];
    public const VAIR_HOUSES = [1, 4, 7, 10];

    /**
     * Full 7×7 sphuta-drishti matrix.
     *
     * @param array<string,array<string,mixed>> $planets chart['planets']
     * @param array<int,int> $dhruvanka rashi_diff 0..11 => kala anchor
     * @return array<string,array<string,array{kala:float,house:int,type:string,type_hi:string,maitri:string,vaam:bool}>>
     *         matrix[drashta][drishya]
     */
    public static function matrix(array $planets, array $dhruvanka): array
    {
        $out = [];
        foreach (self::PLANETS as $a) {
            foreach (self::PLANETS as $b) {
                if ($a === $b || !isset($planets[$a], $planets[$b])) {
                    continue;
                }
                $out[$a][$b] = self::cell(
                    (float) $planets[$a]['sidereal_lon'],
                    (float) $planets[$b]['sidereal_lon'],
                    $dhruvanka
                );
            }
        }
        return $out;
    }

    /**
     * One drashta→drishya cell.
     *
     * @param array<int,int> $dhruvanka
     * @return array{kala:float,house:int,type:string,type_hi:string,maitri:string,vaam:bool}
     */
    public static function cell(float $drashtaLon, float $drishyaLon, array $dhruvanka): array
    {
        $diff = self::norm($drishyaLon - $drashtaLon);
        $r = (int) floor($diff / 30.0) % 12;
        $d = $diff - $r * 30.0;
        $gat = (float) ($dhruvanka[$r] ?? 0);
        $aishya = (float) ($dhruvanka[($r + 1) % 12] ?? 0);
        $kala = $gat + $d * ($aishya - $gat) / 30.0;   // dhan when aishya>gat, rin otherwise

        $house = $r + 1;
        [$type, $typeHi] = self::type($house);
        return [
            'kala' => round($kala, 1),
            'house' => $house,
            'type' => $type,
            'type_hi' => $typeHi,
            'maitri' => in_array($house, self::SNEHA_HOUSES, true) ? 'मित्र'
                : (in_array($house, self::VAIR_HOUSES, true) ? 'शत्रु' : 'सम'),
            'vaam' => $house >= 7,   // aspect toward houses 7–12 = वाम (stronger)
        ];
    }

    /** Whole-sign drishti exists between the two houses? (2/6/8/12 = none). */
    public static function hasDrishti(int $signA, int $signB, bool $ekarksha = true): bool
    {
        $house = ((($signB - $signA) % 12) + 12) % 12 + 1;
        if ($house === 1) {
            return $ekarksha;   // एकर्क्ष — valid for yoga-formation per config
        }
        return !in_array($house, [2, 6, 8, 12], true);
    }

    /** Hindi drishti-type label for a whole-sign house relation (1..12). */
    public static function typeForHouse(int $house): string
    {
        return self::type($house)[1];
    }

    /** @return array{0:string,1:string} [key, hindi] */
    private static function type(int $house): array
    {
        return match (true) {
            $house === 5, $house === 9 => ['sneha', 'प्रत्यक्ष स्नेह'],
            $house === 3, $house === 11 => ['sneha', 'गुप्त स्नेह'],
            $house === 4, $house === 10 => ['vair', 'गुप्त वैर'],
            $house === 7 => ['vair', 'प्रत्यक्ष वैर'],
            $house === 1 => ['vair', 'प्रत्यक्ष वैर (एकर्क्ष)'],
            default => ['none', 'दृष्टि नहीं'],
        };
    }

    private static function norm(float $deg): float
    {
        $d = fmod($deg, 360.0);
        return $d < 0 ? $d + 360.0 : $d;
    }
}
