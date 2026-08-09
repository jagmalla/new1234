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
        // "कोई भरा भाव इसे नहीं देखता" — एकतरफ़ा, आगे-को-चलने वाली दृष्टि में यह
        // असाधारण हालत नहीं, आम हालत है। ढीली कसौटी पर यही अकेला बहरेपन के लिए
        // काफ़ी है; सख़्त कसौटी पर ग्रह का साथी भी कोई न हो, तभी — क्योंकि जिसके
        // साथ कोई बैठा है उस तक बात पहुँचने का एक रास्ता तो खुला ही है।
        $behra = !$sunna && (LalKitabSettings::get('apang_kasauti') !== 'sakht' || !$mate);
        $soya  = $castsAspect && !$bol;

        // most-severe first; सोया (owner-confirmed, actionable) outranks a plain बहरा
        if (($gunga || !$castsAspect) && $behra && !$mate) {
            // "मृत" को अलग अवस्था मानना है या उसे अंधे का ही गहरा रूप — इस पर
            // घराने बँटे हुए हैं, इसलिए यह सेटिंग से तय होता है।
            return LalKitabSettings::get('mrit_avastha') === 'drop'
                ? ['state' => 'अंधा', 'code' => 'ANDHA']
                : ['state' => 'मृत', 'code' => 'MRIT'];
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

    /**
     * फरमान 14 — टेवे की किस्में. Classify the WHOLE teva (not a single planet).
     * Each detected kind carries its code; the engine attaches phal/upay from
     * LalKitabData::TEVA_KISM. More than one kind can hold at once.
     *
     * @param array<string,int> $house      planet-key => house
     * @param array<int,list<string>> $occupants
     * @return list<array{code:string,by:string}>
     */
    public static function classifyTeva(array $house, array $occupants): array
    {
        $hi = static fn (string $p): string => LalKitabData::planetHi($p);
        $kinds = [];

        // अंधा — परस्पर शत्रु ग्रह भाव 10 में एक साथ।
        $pair = self::enemyPairIn($occupants[10] ?? []);
        if ($pair !== null) {
            $kinds[] = ['code' => 'ANDHA',
                'by' => 'भाव 10 में शत्रु-युति (' . $hi($pair[0]) . ' + ' . $hi($pair[1]) . ')'];
        }

        // आधा-अंधा (नुहराता) — शनि सप्तम + सूर्य चतुर्थ।
        if (($house['Saturn'] ?? 0) === 7 && ($house['Sun'] ?? 0) === 4) {
            $kinds[] = ['code' => 'ADHA_ANDHA', 'by' => 'शनि सप्तम + सूर्य चतुर्थ'];
        }

        // बालिग — बुध षष्ठ + सूर्य 1/5/11।
        if (($house['Mercury'] ?? 0) === 6 && in_array($house['Sun'] ?? 0, [1, 5, 11], true)) {
            $kinds[] = ['code' => 'BALIG', 'by' => 'बुध षष्ठ + सूर्य ' . ($house['Sun']) . 'वें'];
        }

        // नाबालिग — केन्द्र (खाली मुट्ठी) खाली, या बुध किसी पापी के साथ।
        $kendraEmpty = true;
        foreach (LalKitabData::KENDRA as $k) {
            if (!empty($occupants[$k])) { $kendraEmpty = false; break; }
        }
        $budhPapi = null;
        $mh = $house['Mercury'] ?? 0;
        if ($mh) {
            foreach (['Rahu', 'Ketu'] as $pp) {
                if (($house[$pp] ?? -1) === $mh) { $budhPapi = $pp; break; }
            }
        }
        if ($kendraEmpty || $budhPapi !== null) {
            $kinds[] = ['code' => 'NABALIG',
                'by' => $kendraEmpty
                    ? 'केन्द्र (1·4·7·10) खाली — खाली मुट्ठी'
                    : 'बुध + ' . $hi($budhPapi) . ' युति (पापी)'];
        }

        // धर्मी — शनि-गुरु युति, या चन्द्र 10/4 में। (व्याख्या-सापेक्ष)
        $satJup = isset($house['Saturn'], $house['Jupiter']) && $house['Saturn'] === $house['Jupiter'];
        $moonKendra = in_array($house['Moon'] ?? 0, [10, 4], true);
        if ($satJup || $moonKendra) {
            $kinds[] = ['code' => 'DHARMI',
                'by' => $satJup ? 'शनि-गुरु युति (भाव ' . $house['Saturn'] . ')' : 'चन्द्र ' . ($house['Moon']) . 'वें'];
        }

        // गुरु-शुक्र मुश्तरका।
        if (isset($house['Jupiter'], $house['Venus']) && $house['Jupiter'] === $house['Venus']) {
            $kinds[] = ['code' => 'GURU_SHUKRA', 'by' => 'गुरु-शुक्र युति (भाव ' . $house['Jupiter'] . ')'];
        }

        return $kinds;
    }

    /** First mutually-enemy pair among planets sharing a house (फरमान 14 list). */
    private static function enemyPairIn(array $planets): ?array
    {
        $planets = array_values($planets);
        $n = count($planets);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $planets[$i];
                $b = $planets[$j];
                $ae = in_array($b, LalKitabData::TEVA_SHATRU_YUGAL[$a] ?? [], true);
                $be = in_array($a, LalKitabData::TEVA_SHATRU_YUGAL[$b] ?? [], true);
                if ($ae || $be) { return [$a, $b]; }
            }
        }
        return null;
    }

    /**
     * स्थायी मैत्री — relation of $b as seen from $a, read from the owner's
     * `maitri` bank table (मित्र / शत्रु / सम). Used by the बुनियाद foundation
     * principle (फरमान 15). Falls back to सम when unknown.
     */
    public static function relation(string $a, string $b): string
    {
        if ($a === $b) { return 'स्व'; }
        $m = LalKitabData::bank()['maitri'][$a] ?? null;
        if (!is_array($m)) { return 'सम'; }
        $bHi = LalKitabData::planetHi($b);
        foreach (['mitra' => 'मित्र', 'shatru' => 'शत्रु', 'sam' => 'सम'] as $k => $label) {
            $names = (string) ($m[$k] ?? '');
            if ($names !== '' && mb_strpos($names, $bHi) !== false) { return $label; }
        }
        return 'सम';
    }

    /**
     * बुनियाद-असूल (फरमान 15) — every house is a building: नींव = its मालिक
     * (HOUSE_LORD), इमारत = the planet(s) whose पक्का घर it is (KARAK_BHAV),
     * राज = the planet(s) actually sitting there. Their mutual friendship shifts
     * the fruit (friends strengthen, enemies spoil).
     *
     * @return array{neenv:string,neenv_hi:string,imarat:list<array{p:string,hi:string,rel:string}>,raj:list<array{p:string,hi:string,rel_neenv:string,rel_imarat:string}>}
     */
    public static function foundation(int $house, array $occupants): array
    {
        $lord = LalKitabData::HOUSE_LORD[$house];
        $pakka = [];
        foreach (LalKitabData::KARAK_BHAV as $pl => $hs) {
            if (in_array($house, $hs, true)) { $pakka[] = $pl; }
        }
        $imarat = [];
        foreach ($pakka as $pk) {
            $imarat[] = ['p' => $pk, 'hi' => LalKitabData::planetHi($pk), 'rel' => self::relation($lord, $pk)];
        }
        $raj = [];
        foreach ($occupants[$house] ?? [] as $op) {
            $relIm = $pakka === [] ? '' : self::relation($pakka[0], $op);
            $raj[] = [
                'p' => $op,
                'hi' => LalKitabData::planetHi($op),
                'rel_neenv'  => self::relation($lord, $op),
                'rel_imarat' => $relIm,
            ];
        }
        return [
            'neenv'    => $lord,
            'neenv_hi' => LalKitabData::planetHi($lord),
            'imarat'   => $imarat,
            'raj'      => $raj,
        ];
    }
}
