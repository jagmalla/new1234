<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\LalKitab;

/**
 * लाल किताब टेवा — the house-based grammar of the fixed-Aries chart.
 *
 * Every rule here reads from the *house* a planet occupies, never from its real
 * transiting rashi, because the teva's signs never move (house 1 is always मेष).
 * These are pure functions over two inputs:
 *   $house      planet-key => house 1..12
 *   $occupants  house 1..12 => list of planet-keys
 *
 * The engine (LalKitabEngine) composes these; keeping them here makes each rule
 * testable in isolation and keeps the giant engine readable.
 */
final class LalKitabTeva
{
    /**
     * House-based dignity of a planet.
     *
     * @return array{status:string,tier:string,bhang:bool,pakka:bool,kachcha:bool,swagrahi:bool,weight:int,why:list<string>}
     */
    public static function dignity(string $planet, int $house, array $occupants = []): array
    {
        $why = [];
        $status = 'सम';
        $tier = '';
        $weight = 0;
        $bhang = false;

        if (in_array($house, LalKitabData::UCH_BHAV[$planet] ?? [], true)) {
            $status = 'उच्च';
            // उच्च-भंग: exalted but no benefit (Rahu-6 / Ketu-12, or a matching condition)
            if (in_array($house, LalKitabData::UCH_BHANG[$planet] ?? [], true)
                || self::bhangHolds($planet, $occupants)) {
                $bhang = true;
                $why[] = 'उच्च भाव में, पर उच्च का शुभ फल नहीं (भंग)';
            } else {
                $weight += 2;
                $why[] = $house . 'वाँ इसका उच्च भाव है';
            }
        } elseif (in_array($house, LalKitabData::NEECH_BHAV[$planet] ?? [], true)) {
            $status = 'नीच';
            $weight -= 2;
            $why[] = $house . 'वाँ इसका नीच भाव है';
        } elseif (in_array($house, LalKitabData::NEECH_BHAV_SECONDARY[$planet] ?? [], true)) {
            $status = 'नीच';
            $tier = 'गौण';
            $weight -= 1;
            $why[] = $house . 'वाँ इसका गौण नीच भाव है (आधा असर)';
        } elseif (in_array($house, LalKitabData::SWA_BHAV[$planet] ?? [], true)) {
            $status = 'स्वगृही';
            $weight += 1;
            $why[] = $house . 'वाँ इसकी अपनी राशि का भाव है';
        }

        // पक्का घर is intensity, not merit — amplifies whichever way the result leans.
        $pakka = in_array($house, LalKitabData::KARAK_BHAV[$planet] ?? [], true);
        $kachcha = in_array($house, LalKitabData::KACHCHA_BHAV[$planet] ?? [], true);
        if ($pakka) {
            $why[] = 'पक्का घर — फल पूरी ताकत व स्थिरता से (शुभ हो तो पूरा शुभ, अशुभ हो तो पूरा अशुभ)';
        }
        if ($kachcha) {
            $why[] = 'कच्चा घर — फल अधूरा, कमज़ोर या देर से';
        }

        return [
            'status'   => $status,
            'tier'     => $tier,
            'bhang'    => $bhang,
            'pakka'    => $pakka,
            'kachcha'  => $kachcha,
            'swagrahi' => $status === 'स्वगृही',
            'weight'   => $weight,
            'why'      => $why,
        ];
    }

    /** Does any उच्च-भंग condition hold for this planet, given the occupants? */
    private static function bhangHolds(string $planet, array $occupants): bool
    {
        foreach (LalKitabData::BHANG_COND[$planet] ?? [] as $c) {
            $there = $occupants[$c['house']] ?? [];
            foreach ($c['planets'] as $p) {
                if (in_array($p, $there, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Houses this house aspects: [house => pct]. One-way forward. */
    public static function aspectsFrom(int $house): array
    {
        return LalKitabData::DRISHTI[$house] ?? [];
    }

    /**
     * Occupied houses whose aspect lands on $house, with strength.
     * @return list<array{house:int,pct:int,planets:list<string>}>
     */
    public static function seenBy(int $house, array $occupants): array
    {
        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            if ($h === $house || empty($occupants[$h])) {
                continue;
            }
            $pct = LalKitabData::DRISHTI[$h][$house] ?? 0;
            if ($pct > 0) {
                $out[] = ['house' => $h, 'pct' => $pct, 'planets' => $occupants[$h]];
            }
        }
        return $out;
    }

    /**
     * The planet (if any) striking whatever sits in $house — the occupant of the
     * house that is eighth-behind. One-way; only spoils the struck planet.
     * @return array{house:int,planets:list<string>}|null
     */
    public static function struckBy(int $house, array $occupants): ?array
    {
        // house X strikes TAKKAR[X]; find X such that TAKKAR[X] === $house
        foreach (LalKitabData::TAKKAR as $from => $to) {
            if ($to === $house && !empty($occupants[$from])) {
                return ['house' => $from, 'planets' => $occupants[$from]];
            }
        }
        return null;
    }

    /**
     * The three collisions landing on $house — but only from occupied attackers,
     * and only when $house itself is occupied (an empty house is never struck).
     * @return array{vishwasghat:list<int>,sajhi:list<int>,achanak:list<int>}
     */
    public static function collisions(int $house, array $occupants): array
    {
        $occ = static fn (array $hs) => array_values(array_filter($hs, static fn ($h) => !empty($occupants[$h])));
        if (empty($occupants[$house])) {
            return ['vishwasghat' => [], 'sajhi' => [], 'achanak' => []];
        }
        return [
            'vishwasghat' => $occ(LalKitabData::VISHWASGHAT[$house] ?? []),
            'sajhi'       => $occ(LalKitabData::SAJHI_CHOT[$house] ?? []),
            'achanak'     => $occ(LalKitabData::ACHANAK_CHOT[$house] ?? []),
        ];
    }

    /**
     * Impaired-planet ladder. Signals (all from the one-way aspect table):
     *   बोल   — the house it aspects is occupied
     *   सुनना — some occupied house aspects it
     *   साथी  — a house-mate
     * States nest: सोया ⊂ गूंगा ⊂ अंधा ⊂ मृत, so no planet is in two at once.
     *
     * @return array{state:string,code:string}   state '' = normal
     */
    public static function impairedState(string $planet, int $house, array $occupants, array $dignity): array
    {
        $aspected = array_keys(self::aspectsFrom($house));      // houses this planet looks at
        $castsAspect = $aspected !== [];

        $bol = false;                                           // its voice reaches someone
        foreach ($aspected as $t) {
            if (!empty($occupants[$t])) { $bol = true; break; }
        }
        $sunna = self::seenBy($house, $occupants) !== [];       // someone reaches it
        $mate  = count($occupants[$house] ?? []) > 1;           // has a house-mate

        // neighbours = 2nd and 12th from it
        $n1 = (($house - 1 + 1) % 12) + 1;
        $n2 = (($house - 1 + 11) % 12) + 1;
        $neighboursEmpty = empty($occupants[$n1]) && empty($occupants[$n2]);

        // A planet that casts no aspect at all (mostly 7–12) cannot be सोया/गूंगा,
        // but can still be बहरा / अंधा / मृत / लंगड़ा.
        $gunga = $castsAspect && !$bol && $neighboursEmpty;
        $behra = !$sunna;
        $soya  = $castsAspect && !$bol;

        // most-severe first; सोया (owner-confirmed, actionable) outranks a plain बहरा
        if (($gunga || !$castsAspect) && $behra && !$mate) {
            return ['state' => 'मृत', 'code' => 'MRIT'];
        }
        if ($gunga && $behra) {
            return ['state' => 'अंधा', 'code' => 'ANDHA'];
        }
        if (!empty($dignity['kachcha']) && ($dignity['status'] ?? '') === 'नीच') {
            return ['state' => 'लंगड़ा', 'code' => 'LANGDA'];
        }
        if ($gunga) {
            return ['state' => 'गूंगा', 'code' => 'GUNGA'];
        }
        if ($soya) {
            return ['state' => 'सोया', 'code' => 'SOYA'];
        }
        if ($behra) {
            return ['state' => 'बहरा', 'code' => 'BEHRA'];
        }
        return ['state' => '', 'code' => ''];
    }

    /**
     * बुनियाद check — is this house's root house afflicted (holds a नीच or पापी
     * planet)? If so the branch planet's good fruit rots. पापी = Rahu/Ketu/Saturn.
     * @return array{root:int,by:list<string>}|null
     */
    public static function buniyadSpoiled(int $house, array $occupants, array $statusOf): ?array
    {
        $root = LalKitabData::BUNIYAD[$house] ?? null;
        if ($root === null) {
            return null;
        }
        $bad = [];
        foreach ($occupants[$root] ?? [] as $p) {
            $isPapi = in_array($p, ['Rahu', 'Ketu', 'Saturn'], true);
            $isNeech = ($statusOf[$p] ?? '') === 'नीच';
            if ($isPapi || $isNeech) {
                $bad[] = $p;
            }
        }
        return $bad === [] ? null : ['root' => $root, 'by' => $bad];
    }

    /**
     * Is the "सांझी गद्दी की पक्की दुश्मनी" active? Only house 8, and only when
     * Mars and Saturn are both there (or two mutual enemies sit there).
     */
    public static function jointSeatEnmity(array $occupants): bool
    {
        $eight = $occupants[8] ?? [];
        return in_array('Mars', $eight, true) && in_array('Saturn', $eight, true);
    }
}
