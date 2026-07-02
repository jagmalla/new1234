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
    /**
     * Default dignity data (0-indexed signs) mirroring migrations/009 planet_dignity.
     * Per planet: exalt sign, deep-exalt°, debil sign, moolatrikona [sign, from°, to°),
     * own sign list. Exalt/MT/own can partition one sign by degree (e.g. Mercury in
     * Virgo: 0-15 exalt, 15-20 MT, 20-30 own). Nodes: exalt/debil only, no MT/own.
     * Overridable at runtime via {@see configure()} so the owner-editable DB table wins.
     */
    private const DIG_DEFAULT = [
        'Sun'     => ['ex' => 0,  'deep' => 10.0, 'de' => 6,  'mt' => [4, 0.0, 20.0],  'own' => [4]],
        'Moon'    => ['ex' => 1,  'deep' => 3.0,  'de' => 7,  'mt' => [1, 3.0, 30.0],  'own' => [3]],
        'Mars'    => ['ex' => 9,  'deep' => 28.0, 'de' => 3,  'mt' => [0, 0.0, 12.0],  'own' => [0, 7]],
        'Mercury' => ['ex' => 5,  'deep' => 15.0, 'de' => 11, 'mt' => [5, 15.0, 20.0], 'own' => [2, 5]],
        'Jupiter' => ['ex' => 3,  'deep' => 5.0,  'de' => 9,  'mt' => [8, 0.0, 10.0],  'own' => [8, 11]],
        'Venus'   => ['ex' => 11, 'deep' => 27.0, 'de' => 5,  'mt' => [6, 0.0, 15.0],  'own' => [1, 6]],
        'Saturn'  => ['ex' => 6,  'deep' => 20.0, 'de' => 0,  'mt' => [10, 0.0, 20.0], 'own' => [9, 10]],
        'Rahu'    => ['ex' => 1,  'deep' => null, 'de' => 7,  'mt' => null, 'own' => []],
        'Ketu'    => ['ex' => 7,  'deep' => null, 'de' => 1,  'mt' => null, 'own' => []],
    ];
    /** Runtime dignity + orb overrides (from DB); null = use defaults. */
    private static ?array $digOverride = null;
    private static ?array $orbOverride = null;
    private static float $moonAmavasyaOrb = 12.0;
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
     * Inject owner-editable dignity bands / combustion orbs (from the DB tables
     * planet_dignity + combustion_orbs). Pass null to keep built-in defaults.
     *
     * @param array<string,array<string,mixed>>|null $dignity planet => {ex,deep,de,mt:[sign,from,to]|null,own:int[]}
     * @param array<string,array{0:float,1:float}>|null $orbs   planet => [direct, retro]
     */
    public static function configure(?array $dignity = null, ?array $orbs = null, ?float $moonAmavasyaOrb = null): void
    {
        self::$digOverride = $dignity;
        self::$orbOverride = $orbs;
        if ($moonAmavasyaOrb !== null) {
            self::$moonAmavasyaOrb = $moonAmavasyaOrb;
        }
    }

    /**
     * Resolve dignity — FIRST MATCH WINS, degree-aware (exalt/MT/own may split
     * one sign). Order: exalt → moolatrikona → own → debil(+neecha-bhanga) →
     * panchadha maitri toward the sign lord.
     *
     * @param array<string,array<string,mixed>> $planets
     * @return array{tier:string,word:string,score:float,neecha_bhanga:bool,deep:string,reason:string}
     */
    public static function dignity(string $planet, int $sign, float $deg, array $planets, int $ascSign): array
    {
        $d = (self::$digOverride[$planet] ?? null) ?? self::DIG_DEFAULT[$planet] ?? null;
        $tier = null;
        $bhanga = false;
        $deepNote = '';
        $reason = '';

        if ($d !== null) {
            $mt = $d['mt'] ?? null;
            $exaltSameAsMt = $mt !== null && (int) $mt[0] === (int) $d['ex'];

            // 1) EXALTED — in exalt sign, unless that sign's upper part is MT/own by degree.
            if ($sign === (int) $d['ex'] && !($exaltSameAsMt && $deg >= (float) $mt[1])) {
                $tier = 'exalt';
                if ($d['deep'] !== null && abs($deg - (float) $d['deep']) <= 3.0) {
                    $tier = 'param_uchcha';
                    $deepNote = ' (परम उच्च के निकट)';
                }
            // 2) MOOLATRIKONA — [from, to)
            } elseif ($mt !== null && $sign === (int) $mt[0] && $deg >= (float) $mt[1] && $deg < (float) $mt[2]) {
                $tier = 'moolatrikona';
            // 3) OWN
            } elseif (in_array($sign, $d['own'] ?? [], true)) {
                $tier = 'own';
            // 4) DEBILITATED (+ neecha bhanga)
            } elseif ($sign === (int) $d['de']) {
                $tier = 'debil';
                [$bhanga, $reason] = self::neechaBhanga($planet, $sign, $planets, $ascSign);
                if ($bhanga) {
                    $tier = 'debil_bhanga';
                }
            }
        }

        // 5) PANCHADHA MAITRI toward the sign lord
        if ($tier === null) {
            $lord = self::SIGN_LORD[$sign];
            $tier = $lord === $planet ? 'own' : self::compoundTier($planet, $lord, $planets);
        }

        [$word, $score] = self::DIGNITY[$tier];
        return [
            'tier' => $tier, 'word' => $word, 'score' => (float) $score,
            'neecha_bhanga' => $bhanga, 'deep' => $deepNote, 'reason' => $reason,
        ];
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
        $orbs = (self::$orbOverride[$planet] ?? null) ?? self::COMBUST_ORB[$planet];
        $orb = $orbs[$retro ? 1 : 0];

        $sep = self::sep((float) $planets[$planet]['sidereal_lon'], (float) $planets['Sun']['sidereal_lon']);
        if ($sep >= $orb) {
            return null;
        }
        $pct = (int) round(($orb - $sep) / $orb * 100.0);
        $pct = max(0, min(100, $pct));
        $tier = $pct >= 75 ? 'full' : ($pct >= 40 ? 'moderate' : 'partial');
        // Moon very close to the Sun → amavasya-weakness (independent of band).
        $amavasya = $planet === 'Moon' && $sep < self::$moonAmavasyaOrb;
        return ['sep' => round($sep, 1), 'pct' => $pct, 'tier' => $tier, 'orb' => $orb, 'amavasya' => $amavasya];
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
     * Neecha-bhanga (cancellation of debilitation). Returns [cancelled, reason].
     * Any one: (a) the dispositor (lord of the debilitation sign) is in a kendra
     * from Lagna or Moon; (b) the planet that is exalted in that sign is in a
     * kendra from Lagna/Moon; (c) either of those aspects the debilitated planet.
     *
     * @param array<string,array<string,mixed>> $planets
     * @return array{0:bool,1:string}
     */
    private static function neechaBhanga(string $planet, int $debilSign, array $planets, int $ascSign): array
    {
        $moonSign = (int) ($planets['Moon']['sign_index'] ?? $ascSign);
        $kendra = static function (string $who) use ($planets, $ascSign, $moonSign): bool {
            if (!isset($planets[$who])) {
                return false;
            }
            $s = (int) $planets[$who]['sign_index'];
            $fromLagna = (($s - $ascSign + 12) % 12) + 1;
            $fromMoon  = (($s - $moonSign + 12) % 12) + 1;
            return in_array($fromLagna, [1, 4, 7, 10], true) || in_array($fromMoon, [1, 4, 7, 10], true);
        };

        // (a) dispositor of the debilitation sign in a kendra
        $lord = self::SIGN_LORD[$debilSign];
        if ($kendra($lord)) {
            return [true, self::hi($lord) . ' (राशि-स्वामी) केन्द्र में है'];
        }
        // (b) the planet exalted in that sign, in a kendra
        foreach (self::DIG_DEFAULT as $pl => $d) {
            if ((int) $d['ex'] === $debilSign && $kendra($pl)) {
                return [true, self::hi($pl) . ' (जो इस राशि में उच्च होता है) केन्द्र में है'];
            }
        }
        return [false, ''];
    }

    private static function hi(string $planet): string
    {
        return ['Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
            'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु'][$planet] ?? $planet;
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
