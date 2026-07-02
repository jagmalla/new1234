<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Shared planet-condition service — the single source of truth for a planet's
 * dignity, combustion and benefic/malefic nature, used by the Planet Prediction
 * "ग्रह स्थिति" block (and reusable by the House/Karaka/Dasha layers so every
 * panel reports IDENTICAL facts). Pure functions over the engine's computed
 * chart (sign_index, deg_in_sign, house, sidereal_lon, retro) — no astronomy is
 * recomputed here, only classical classification.
 */
final class PlanetCondition
{
    /** Sign lord per sign index (0=Aries … 11=Pisces). */
    private const SIGN_LORD = [
        0 => 'Mars', 1 => 'Venus', 2 => 'Mercury', 3 => 'Moon', 4 => 'Sun', 5 => 'Mercury',
        6 => 'Venus', 7 => 'Mars', 8 => 'Jupiter', 9 => 'Saturn', 10 => 'Saturn', 11 => 'Jupiter',
    ];
    /** Exaltation sign index per planet. */
    private const EXALT_SIGN = [
        'Sun' => 0, 'Moon' => 1, 'Mars' => 9, 'Mercury' => 5, 'Jupiter' => 3,
        'Venus' => 11, 'Saturn' => 6, 'Rahu' => 1, 'Ketu' => 7,
    ];
    /** Deep-exaltation (परम उच्च) degree within the exaltation sign. */
    private const DEEP_EXALT_DEG = [
        'Sun' => 10.0, 'Moon' => 3.0, 'Mars' => 28.0, 'Mercury' => 15.0,
        'Jupiter' => 5.0, 'Venus' => 27.0, 'Saturn' => 20.0,
    ];
    /** Own sign indices (nodes co-lord Aquarius / Scorpio, per the common scheme). */
    private const OWN = [
        'Sun' => [4], 'Moon' => [3], 'Mars' => [0, 7], 'Mercury' => [2, 5],
        'Jupiter' => [8, 11], 'Venus' => [1, 6], 'Saturn' => [9, 10],
        'Rahu' => [10], 'Ketu' => [7],
    ];
    /** Moolatrikona: [sign index, start°, end°]. */
    private const MOOLA = [
        'Sun' => [4, 0.0, 20.0], 'Mars' => [0, 0.0, 12.0], 'Mercury' => [5, 16.0, 20.0],
        'Jupiter' => [8, 0.0, 10.0], 'Venus' => [6, 0.0, 15.0], 'Saturn' => [10, 0.0, 20.0],
        // Moon's MT (Taurus 4-30) is subsumed by its exaltation sign, so omitted.
    ];
    /** Natural (Naisargika) friendship: F=friend, N=neutral, E=enemy. */
    private const PERM = [
        'Sun'     => ['Moon' => 'F', 'Mars' => 'F', 'Jupiter' => 'F', 'Mercury' => 'N', 'Venus' => 'E', 'Saturn' => 'E'],
        'Moon'    => ['Sun' => 'F', 'Mercury' => 'F', 'Mars' => 'N', 'Jupiter' => 'N', 'Venus' => 'N', 'Saturn' => 'N'],
        'Mars'    => ['Sun' => 'F', 'Moon' => 'F', 'Jupiter' => 'F', 'Mercury' => 'E', 'Venus' => 'N', 'Saturn' => 'N'],
        'Mercury' => ['Sun' => 'F', 'Venus' => 'F', 'Moon' => 'E', 'Mars' => 'N', 'Jupiter' => 'N', 'Saturn' => 'N'],
        'Jupiter' => ['Sun' => 'F', 'Moon' => 'F', 'Mars' => 'F', 'Mercury' => 'E', 'Venus' => 'E', 'Saturn' => 'N'],
        'Venus'   => ['Mercury' => 'F', 'Saturn' => 'F', 'Sun' => 'E', 'Moon' => 'E', 'Mars' => 'N', 'Jupiter' => 'N'],
        'Saturn'  => ['Mercury' => 'F', 'Venus' => 'F', 'Sun' => 'E', 'Moon' => 'E', 'Mars' => 'E', 'Jupiter' => 'N'],
        'Rahu'    => ['Mercury' => 'F', 'Venus' => 'F', 'Saturn' => 'F', 'Jupiter' => 'N', 'Sun' => 'E', 'Moon' => 'E', 'Mars' => 'E'],
        'Ketu'    => ['Mars' => 'F', 'Venus' => 'F', 'Saturn' => 'F', 'Jupiter' => 'N', 'Mercury' => 'N', 'Sun' => 'E', 'Moon' => 'E'],
    ];
    /** Combustion orb from the Sun (degrees); [direct, retrograde]. */
    private const COMBUST_ORB = [
        'Moon' => [12.0, 12.0], 'Mars' => [17.0, 17.0], 'Mercury' => [14.0, 12.0],
        'Jupiter' => [11.0, 11.0], 'Venus' => [10.0, 8.0], 'Saturn' => [15.0, 15.0],
    ];
    private const NODES = ['Rahu', 'Ketu'];

    /** Dignity word + score per tier (score constants per the fix spec §4). */
    private const DIGNITY = [
        'param_uchcha' => ['उच्च (परम उच्च)', 2.25],
        'exalt'        => ['उच्च', 2.0],
        'moolatrikona' => ['मूल त्रिकोण', 1.5],
        'own'          => ['स्वराशि', 1.25],
        'great_friend' => ['अति-मित्र राशि', 1.25],
        'friend'       => ['मित्र राशि', 1.0],
        'neutral'      => ['सम राशि', 0.0],
        'enemy'        => ['शत्रु राशि', -1.0],
        'great_enemy'  => ['अति-शत्रु राशि', -1.25],
        'debil'        => ['नीच', -2.0],
        'debil_bhanga' => ['नीच (नीच भंग)', 0.0],
    ];

    /**
     * Full condition for one planet.
     *
     * @param string $planet
     * @param array<string,array<string,mixed>> $planets chart['planets']
     * @param int $ascSign ascendant sign index (for neecha-bhanga kendra test)
     * @return array{
     *   dignity: array{tier:string,word:string,score:float,neecha_bhanga:bool},
     *   combust: array{sep:float,pct:int,tier:string}|null,
     *   retro: bool, benefic: bool
     * }
     */
    public static function resolve(string $planet, array $planets, int $ascSign): array
    {
        $p = $planets[$planet] ?? [];
        $sign = (int) ($p['sign_index'] ?? 0);
        $deg = (float) ($p['deg_in_sign'] ?? 0.0);

        return [
            'dignity' => self::dignity($planet, $sign, $deg, $planets, $ascSign),
            'combust' => self::combustion($planet, $planets),
            'retro'   => (bool) ($p['retro'] ?? false),
            'benefic' => self::isBenefic($planet, $planets),
        ];
    }

    /**
     * Resolve dignity tier/word/score.
     *
     * @param array<string,array<string,mixed>> $planets
     * @return array{tier:string,word:string,score:float,neecha_bhanga:bool}
     */
    public static function dignity(string $planet, int $sign, float $deg, array $planets, int $ascSign): array
    {
        $tier = null;
        $bhanga = false;

        $exaltSign = self::EXALT_SIGN[$planet] ?? -1;
        if ($sign === $exaltSign) {
            $deep = self::DEEP_EXALT_DEG[$planet] ?? null;
            $tier = ($deep !== null && abs($deg - $deep) <= 1.0) ? 'param_uchcha' : 'exalt';
        } elseif ($exaltSign >= 0 && $sign === ($exaltSign + 6) % 12) {
            $tier = 'debil';
            $bhanga = self::neechaBhanga($planet, $sign, $planets, $ascSign);
            if ($bhanga) {
                $tier = 'debil_bhanga';
            }
        } elseif (isset(self::MOOLA[$planet])
            && $sign === self::MOOLA[$planet][0]
            && $deg >= self::MOOLA[$planet][1] && $deg <= self::MOOLA[$planet][2]) {
            $tier = 'moolatrikona';
        } elseif (in_array($sign, self::OWN[$planet] ?? [], true)) {
            $tier = 'own';
        } else {
            $lord = self::SIGN_LORD[$sign];
            $tier = $lord === $planet ? 'own' : self::compoundTier($planet, $lord, $planets);
        }

        [$word, $score] = self::DIGNITY[$tier];
        return ['tier' => $tier, 'word' => $word, 'score' => (float) $score, 'neecha_bhanga' => $bhanga];
    }

    /**
     * Combustion (अस्त). Null when not combust / never combust (Sun, nodes).
     *
     * @param array<string,array<string,mixed>> $planets
     * @return array{sep:float,pct:int,tier:string}|null
     */
    public static function combustion(string $planet, array $planets): ?array
    {
        if (!isset(self::COMBUST_ORB[$planet]) || !isset($planets['Sun'], $planets[$planet])) {
            return null;
        }
        $retro = (bool) ($planets[$planet]['retro'] ?? false);
        $orb = self::COMBUST_ORB[$planet][$retro ? 1 : 0];

        $sep = self::sep((float) $planets[$planet]['sidereal_lon'], (float) $planets['Sun']['sidereal_lon']);
        if ($sep >= $orb) {
            return null;
        }
        $pct = (int) round(($orb - $sep) / $orb * 100.0);
        $pct = max(0, min(100, $pct));
        $tier = $pct >= 75 ? 'full' : ($pct >= 40 ? 'moderate' : 'partial');
        return ['sep' => round($sep, 1), 'pct' => $pct, 'tier' => $tier];
    }

    /**
     * Natural benefic? Jupiter/Venus always; waxing Moon; Mercury unless it sits
     * with ONLY malefics; the rest are malefic.
     *
     * @param array<string,array<string,mixed>> $planets
     */
    public static function isBenefic(string $planet, array $planets): bool
    {
        if (in_array($planet, ['Jupiter', 'Venus'], true)) {
            return true;
        }
        if ($planet === 'Moon') {
            return self::moonWaxing($planets);
        }
        if ($planet === 'Mercury') {
            // Benefic unless every co-tenant of its house is a malefic.
            $house = (int) ($planets['Mercury']['house'] ?? 0);
            $companions = self::companionsInHouse('Mercury', $house, $planets);
            if ($companions === []) {
                return true;
            }
            foreach ($companions as $c) {
                if (self::isBeneficBase($c, $planets)) {
                    return true; // at least one benefic companion → Mercury benefic
                }
            }
            return false;
        }
        return false; // Sun, Mars, Saturn, Rahu, Ketu
    }

    /**
     * Mutual naisargika maitri between two planets (for companion lines):
     * both friends → friend, both enemies → enemy, otherwise neutral. Nodes use
     * their own friendship row one-directionally (config graha_node_maitri_mode).
     */
    public static function naisargikaMaitri(string $a, string $b): string
    {
        $aNode = in_array($a, self::NODES, true);
        $bNode = in_array($b, self::NODES, true);
        if ($aNode || $bNode) {
            $node = $aNode ? $a : $b;
            $other = $aNode ? $b : $a;
            return self::relWord(self::PERM[$node][$other] ?? 'N');
        }
        $ab = self::PERM[$a][$b] ?? 'N';
        $ba = self::PERM[$b][$a] ?? 'N';
        if ($ab === 'F' && $ba === 'F') {
            return 'friend';
        }
        if ($ab === 'E' && $ba === 'E') {
            return 'enemy';
        }
        return 'neutral';
    }

    /**
     * Other planets sharing $planet's house (excludes itself).
     *
     * @param array<string,array<string,mixed>> $planets
     * @return list<string>
     */
    public static function companions(string $planet, array $planets): array
    {
        $house = (int) ($planets[$planet]['house'] ?? 0);
        return self::companionsInHouse($planet, $house, $planets);
    }

    // ---- internals ----------------------------------------------------------

    /**
     * Panchadha (compound) relation tier of $planet toward its sign lord:
     * natural (PERM) + temporal (sign distance) → great_friend/friend/neutral/
     * enemy/great_enemy.
     *
     * @param array<string,array<string,mixed>> $planets
     */
    private static function compoundTier(string $planet, string $lord, array $planets): string
    {
        $nat = self::PERM[$planet][$lord] ?? 'N';               // F | N | E
        $temp = self::temporal($planet, $lord, $planets);        // F | E
        // Compound matrix (natural, temporal):
        //   F+F=great_friend · F+E=neutral · N+F=friend · N+E=enemy
        //   E+F=neutral · E+E=great_enemy
        return match ($nat . $temp) {
            'FF' => 'great_friend',
            'FE' => 'neutral',
            'NF' => 'friend',
            'NE' => 'enemy',
            'EF' => 'neutral',
            'EE' => 'great_enemy',
            default => 'neutral',
        };
    }

    /**
     * Temporal (Tatkalika) friendship of $a toward $b: b in the 2/3/4/10/11/12
     * sign from a → friend (F), else enemy (E).
     *
     * @param array<string,array<string,mixed>> $planets
     */
    private static function temporal(string $a, string $b, array $planets): string
    {
        $sa = (int) ($planets[$a]['sign_index'] ?? 0);
        $sb = (int) ($planets[$b]['sign_index'] ?? 0);
        $rel = (($sb - $sa + 12) % 12) + 1; // 1..12
        return in_array($rel, [2, 3, 4, 10, 11, 12], true) ? 'F' : 'E';
    }

    /**
     * Simplified neecha-bhanga: the dispositor (lord of the debilitation sign)
     * sits in a kendra (1/4/7/10) from the Lagna, or is itself exalted/own.
     *
     * @param array<string,array<string,mixed>> $planets
     */
    private static function neechaBhanga(string $planet, int $debilSign, array $planets, int $ascSign): bool
    {
        $lord = self::SIGN_LORD[$debilSign];
        if (!isset($planets[$lord])) {
            return false;
        }
        $lordHouse = (($planets[$lord]['sign_index'] - $ascSign + 12) % 12) + 1;
        if (in_array($lordHouse, [1, 4, 7, 10], true)) {
            return true;
        }
        $ls = (int) $planets[$lord]['sign_index'];
        if ($ls === (self::EXALT_SIGN[$lord] ?? -1) || in_array($ls, self::OWN[$lord] ?? [], true)) {
            return true;
        }
        return false;
    }

    /** Angular separation 0..180. */
    private static function sep(float $a, float $b): float
    {
        $d = fmod(abs($a - $b), 360.0);
        return $d > 180.0 ? 360.0 - $d : $d;
    }

    /** Moon waxing = ahead of the Sun by 0..180° (bright fortnight). */
    private static function moonWaxing(array $planets): bool
    {
        if (!isset($planets['Moon'], $planets['Sun'])) {
            return true;
        }
        $d = fmod(((float) $planets['Moon']['sidereal_lon'] - (float) $planets['Sun']['sidereal_lon']) + 360.0, 360.0);
        return $d > 0.0 && $d < 180.0;
    }

    /** Base benefic/malefic WITHOUT Mercury's association rule (avoids recursion). */
    private static function isBeneficBase(string $planet, array $planets): bool
    {
        if (in_array($planet, ['Jupiter', 'Venus'], true)) {
            return true;
        }
        if ($planet === 'Moon') {
            return self::moonWaxing($planets);
        }
        if ($planet === 'Mercury') {
            return true; // treat bare Mercury as benefic for a companion's benefic test
        }
        return false;
    }

    /**
     * @param array<string,array<string,mixed>> $planets
     * @return list<string>
     */
    private static function companionsInHouse(string $planet, int $house, array $planets): array
    {
        $out = [];
        foreach ($planets as $name => $p) {
            if ($name !== $planet && (int) ($p['house'] ?? -1) === $house) {
                $out[] = (string) $name;
            }
        }
        return $out;
    }

    private static function relWord(string $fne): string
    {
        return ['F' => 'friend', 'E' => 'enemy', 'N' => 'neutral'][$fne] ?? 'neutral';
    }
}
