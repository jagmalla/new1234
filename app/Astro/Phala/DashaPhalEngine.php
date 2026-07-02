<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Astro\Calc\Drishti;
use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * Calculated Dasha Prediction engine (दशा फल v2). Given the already-computed
 * chart and a maha/antar lord pair, it works out where each dasha lord actually
 * sits in THIS chart (house from Lagna, dignity, retrograde, conjunctions,
 * aspects, owned houses — all via the shared services), scores each with a
 * deterministic verdict, and selects only the applicable owner-authored text +
 * remedies from the migration-011 tables. Falls back to the existing Bhavesh
 * Phal (144) / Graha-in-Bhava (108) and keeps the classical 81-combo row as a
 * reference. Pure and unit-testable.
 */
final class DashaPhalEngine
{
    private const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];
    private const ORD_HI = [
        1 => '1वें (लग्न)', 2 => '2रे (धन)', 3 => '3रे (पराक्रम)', 4 => '4थे (सुख)', 5 => '5वें (विद्या/संतान)',
        6 => '6ठे (रोग/शत्रु)', 7 => '7वें (दांपत्य)', 8 => '8वें (आयु)', 9 => '9वें (भाग्य)', 10 => '10वें (कर्म)',
        11 => '11वें (लाभ)', 12 => '12वें (व्यय)',
    ];
    private const HOUSE_CAT = [
        1 => 'केन्द्र/त्रिकोण', 4 => 'केन्द्र', 7 => 'केन्द्र', 10 => 'केन्द्र',
        5 => 'त्रिकोण', 9 => 'त्रिकोण', 2 => 'धन', 11 => 'लाभ (उपचय)', 3 => 'उपचय',
        6 => 'दुःस्थान', 8 => 'दुःस्थान', 12 => 'दुःस्थान',
    ];
    /** Step-B house points. */
    private const HOUSE_PTS = [1 => 2, 4 => 2, 5 => 2, 7 => 2, 9 => 2, 10 => 2, 11 => 2, 2 => 1, 3 => 1, 6 => -2, 8 => -2, 12 => -2];

    /**
     * @param array<string,mixed> $chart computed chart (planets + houses + ascendant)
     * @param array<string,mixed> $rules DashaEngineRepository::load()
     * @return array<string,mixed>
     */
    public static function compute(array $chart, string $mahaLord, string $antarLord, array $rules, string $lang = 'hi'): array
    {
        $planets = $chart['planets'] ?? [];
        $houses = $chart['houses'] ?? [];
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $alwaysRemedy = (string) ($rules['config']['dasha_engine_show_remedy_always'] ?? '0') === '1';

        $mahaFacts = self::facts($mahaLord, $planets, $houses, $ascSign);
        $antarFacts = self::facts($antarLord, $planets, $houses, $ascSign);

        // Cards 2 & 3 — lord from Lagna (maha & antar).
        $mahaCard = self::lagnaCard('maha', $mahaLord, $mahaFacts, $rules, $alwaysRemedy);
        $antarLagnaCard = self::lagnaCard('antar', $antarLord, $antarFacts, $rules, $alwaysRemedy);

        // Card 4 — antar lord from the maha lord (position-based verdict).
        $houseFromMaha = (($antarFacts['house'] - $mahaFacts['house'] + 12) % 12) + 1;
        $antarMahaCard = self::antarMahaCard($antarLord, $mahaLord, $houseFromMaha, $rules, $alwaysRemedy);

        // Cards 5 — bhavesh impact (owned houses) for maha & antar.
        $bhaveshCards = array_merge(
            self::bhaveshCards('maha', $mahaLord, $mahaFacts, $rules, $lang),
            self::bhaveshCards('antar', $antarLord, $antarFacts, $rules, $lang)
        );

        // Card 6 — deduplicated remedies from every अशुभ/मिश्रित card.
        $remedies = [];
        foreach (array_merge([$mahaCard, $antarLagnaCard, $antarMahaCard], $bhaveshCards) as $c) {
            if (!empty($c['remedy'])) {
                $remedies[trim($c['remedy'])] = true;
            }
        }
        $remedies = array_keys($remedies);

        // Card 1 — overall conclusion (Step D).
        $weighted = 0.5 * $mahaFacts['score'] + 0.25 * $antarFacts['score'] + 0.25 * (self::HOUSE_PTS[$houseFromMaha] ?? 0);
        $overallVerdict = self::verdict($weighted, 1.0);
        $overall = [
            'verdict' => $overallVerdict['word'], 'tier' => $overallVerdict['tier'],
            'sentence' => sprintf(
                '%s महादशा – %s अंतर्दशा: %s लग्न से %s (%s, %s) तथा महादशेश %s से %sवें — समग्र फल %s।',
                self::hi($mahaLord), self::hi($antarLord), self::hi($antarLord),
                self::ORD_HI[$antarFacts['house']] ?? $antarFacts['house'],
                self::HOUSE_CAT[$antarFacts['house']] ?? '', $antarFacts['dignity_word'],
                self::hi($mahaLord), $houseFromMaha, $overallVerdict['word']
            ),
        ];

        // Card 7 — classical 81-combo reference (kept, collapsed in UI).
        $classical = DashaPhalaRepository::find($mahaLord, $antarLord, $lang);

        return [
            'maha_lord' => $mahaLord, 'antar_lord' => $antarLord,
            'overall' => $overall,
            'maha_card' => $mahaCard,
            'antar_lagna_card' => $antarLagnaCard,
            'antar_maha_card' => $antarMahaCard,
            'bhavesh_cards' => $bhaveshCards,
            'remedies' => $remedies,
            'classical' => $classical,
        ];
    }

    /**
     * Step A — collect a planet's facts + Step B verdict score.
     * @return array<string,mixed>
     */
    private static function facts(string $planet, array $planets, array $houses, int $ascSign): array
    {
        $p = $planets[$planet] ?? [];
        $house = (int) ($p['house'] ?? 1);
        $sign = (int) ($p['sign_index'] ?? 0);
        $deg = (float) ($p['deg_in_sign'] ?? 0.0);
        $dig = PlanetCondition::dignity($planet, $sign, $deg, $planets, $ascSign);
        $combust = PlanetCondition::combustion($planet, $planets);
        $retro = !empty($p['retro']);

        // conjunctions (same house)
        $benCon = [];
        $malCon = [];
        foreach ($planets as $name => $q) {
            if ($name === $planet || (int) ($q['house'] ?? -1) !== $house) {
                continue;
            }
            if (PlanetCondition::isBenefic((string) $name, $planets)) {
                $benCon[] = (string) $name;
            } else {
                $malCon[] = (string) $name;
            }
        }
        // aspects received on the house
        $benAsp = 0;
        $malAsp = 0;
        foreach (($houses[$house]['drishti'] ?? []) as $ab) {
            $full = Drishti::FULL[$ab] ?? $ab;
            if (PlanetCondition::isBenefic((string) $full, $planets)) {
                $benAsp++;
            } else {
                $malAsp++;
            }
        }
        // owned houses (from Lagna)
        $owned = [];
        foreach ($houses as $hn => $H) {
            if (($H['lord'] ?? null) === $planet) {
                $owned[] = (int) $hn;
            }
        }
        sort($owned);

        // Step B score
        $score = (float) (self::HOUSE_PTS[$house] ?? 0);
        $score += self::dignityPts($dig['tier'], !empty($dig['neecha_bhanga']));
        $score += max(-1.0, min(1.0, 0.5 * count($benCon) - 0.5 * count($malCon)));
        $score += $benAsp > $malAsp ? 0.5 : ($malAsp > $benAsp ? -0.5 : 0.0);

        return [
            'planet' => $planet, 'house' => $house, 'dignity_word' => (string) $dig['word'],
            'dignity_tier' => (string) $dig['tier'], 'neecha_bhanga' => !empty($dig['neecha_bhanga']),
            'retro' => $retro, 'combust' => $combust, 'ben_con' => $benCon, 'mal_con' => $malCon,
            'ben_asp' => $benAsp, 'mal_asp' => $malAsp, 'owned' => $owned,
            'score' => $score, 'verdict' => self::verdict($score, 2.0),
        ];
    }

    /** Cards 2/3 — lord from Lagna text selection. */
    private static function lagnaCard(string $context, string $planet, array $facts, array $rules, bool $alwaysRemedy): array
    {
        $row = $rules['lagna'][$context][$planet][$facts['house']] ?? null;
        $v = $facts['verdict'];
        [$text, $remedy] = self::selectText($row, $v, $alwaysRemedy);
        return [
            'kind' => $context === 'maha' ? 'maha' : 'antar_lagna',
            'planet' => $planet, 'house' => $facts['house'],
            'header' => sprintf('%s — लग्न से %s भाव में', self::hi($planet), self::ORD_HI[$facts['house']] ?? $facts['house']),
            'facts' => self::factsLine($facts),
            'verdict' => $v['word'], 'tier' => $v['tier'],
            'text' => $text, 'remedy' => $remedy,
        ];
    }

    /** Card 4 — antar from maha lord (verdict from the table). */
    private static function antarMahaCard(string $antar, string $maha, int $houseFromMaha, array $rules, bool $alwaysRemedy): array
    {
        $row = $rules['antarMaha'][$houseFromMaha] ?? null;
        $verdictWord = (string) ($row['verdict'] ?? 'मिश्रित');
        $tier = self::verdictTier($verdictWord);
        // Position-based: show positive unless the table verdict is clearly negative.
        $neg = str_contains($verdictWord, 'अशुभ');
        $text = [];
        if ($row !== null) {
            if ($neg && ($row['neg'] ?? '') !== '') {
                $text[] = ['kind' => 'neg', 'body' => $row['neg']];
            } elseif (($row['pos'] ?? '') !== '') {
                $text[] = ['kind' => 'pos', 'body' => $row['pos']];
            } elseif (($row['neg'] ?? '') !== '') {
                $text[] = ['kind' => 'neg', 'body' => $row['neg']];
            }
        }
        $remedy = ($neg || str_contains($verdictWord, 'मिश्रित') || $alwaysRemedy) ? (string) ($row['rem'] ?? '') : '';
        return [
            'kind' => 'antar_maha', 'antar' => $antar, 'maha' => $maha, 'house_from_maha' => $houseFromMaha,
            'header' => sprintf('%s — %s से %sवें', self::hi($antar), self::hi($maha), $houseFromMaha),
            'facts' => sprintf('महादशेश से %sवाँ भाव (%s)', $houseFromMaha, self::HOUSE_CAT[$houseFromMaha] ?? ''),
            'verdict' => $verdictWord, 'tier' => $tier, 'text' => $text, 'remedy' => $remedy,
        ];
    }

    /** Card 5 — bhavesh impact per owned house (fallback to existing 144 / 108). */
    private static function bhaveshCards(string $who, string $planet, array $facts, array $rules, string $lang): array
    {
        $cards = [];
        // Rahu/Ketu own no house → show Graha-in-Bhava (108) instead.
        if ($facts['owned'] === []) {
            $g = BhavPhalaRepository::grahaBhava($planet, $facts['house'], $lang);
            if ($g !== null && ((($g['positive_text'] ?? '') !== '') || (($g['negative_text'] ?? '') !== ''))) {
                $text = [];
                if (($g['positive_text'] ?? '') !== '') { $text[] = ['kind' => 'pos', 'body' => (string) $g['positive_text']]; }
                if (($g['negative_text'] ?? '') !== '') { $text[] = ['kind' => 'neg', 'body' => (string) $g['negative_text']]; }
                $cards[] = [
                    'kind' => 'bhavesh', 'who' => $who, 'planet' => $planet,
                    'header' => sprintf('%s — %s भाव में (ग्रह-भाव फल)', self::hi($planet), self::ORD_HI[$facts['house']] ?? $facts['house']),
                    'facts' => 'राहु/केतु किसी भाव के स्वामी नहीं — ग्रह-भाव फल दर्शाया गया।',
                    'text' => $text, 'remedy' => '',
                ];
            }
            return $cards;
        }

        foreach ($facts['owned'] as $H) {
            $override = $rules['bhavesh'][$H][$facts['house']] ?? null;
            $text = [];
            $remedy = '';
            if ($override !== null && (($override['pos'] ?? '') !== '' || ($override['neg'] ?? '') !== '')) {
                if (($override['pos'] ?? '') !== '') { $text[] = ['kind' => 'pos', 'body' => (string) $override['pos']]; }
                if (($override['neg'] ?? '') !== '') { $text[] = ['kind' => 'neg', 'body' => (string) $override['neg']]; }
                $remedy = (string) ($override['rem'] ?? '');
            } else {
                $b = BhavPhalaRepository::bhavesh($H, $facts['house'], $lang);
                if ($b !== null && $b !== '') { $text[] = ['kind' => 'pos', 'body' => $b]; }
            }
            if ($text === []) {
                continue;
            }
            $ownedHi = implode(' व ', array_map(static fn($x) => (self::ORD_HI[$x] ?? $x), $facts['owned']));
            $cards[] = [
                'kind' => 'bhavesh', 'who' => $who, 'planet' => $planet, 'owned_house' => $H, 'placed_house' => $facts['house'],
                'header' => sprintf('%s — %s भाव का स्वामी, %s भाव में', self::hi($planet), $ownedHi, self::ORD_HI[$facts['house']] ?? $facts['house']),
                'facts' => sprintf('%s भाव का स्वामी %s भाव में स्थित', $ownedHi, self::ORD_HI[$facts['house']] ?? $facts['house']),
                'text' => $text, 'remedy' => $remedy,
            ];
        }
        return $cards;
    }

    /**
     * Text selection by verdict: शुभ → positive; अशुभ → negative; मिश्रित →
     * both (positive first). Remedy on मिश्रित/अशुभ (or always via config).
     * @return array{0:list<array{kind:string,body:string}>,1:string}
     */
    private static function selectText(?array $row, array $verdict, bool $alwaysRemedy): array
    {
        if ($row === null) {
            return [[], ''];
        }
        $text = [];
        $w = $verdict['word'];
        if ($w === 'शुभ') {
            if (($row['pos'] ?? '') !== '') { $text[] = ['kind' => 'pos', 'body' => $row['pos']]; }
        } elseif ($w === 'अशुभ') {
            if (($row['neg'] ?? '') !== '') { $text[] = ['kind' => 'neg', 'body' => $row['neg']]; }
        } else { // मिश्रित
            if (($row['pos'] ?? '') !== '') { $text[] = ['kind' => 'pos', 'body' => $row['pos']]; }
            if (($row['neg'] ?? '') !== '') { $text[] = ['kind' => 'neg', 'body' => $row['neg']]; }
        }
        $remedy = ($w !== 'शुभ' || $alwaysRemedy) ? (string) ($row['rem'] ?? '') : '';
        return [$text, $remedy];
    }

    private static function factsLine(array $f): string
    {
        $parts = [self::HOUSE_CAT[$f['house']] ?? '', $f['dignity_word']];
        if ($f['neecha_bhanga']) { $parts[] = 'नीच भंग'; }
        if ($f['retro']) { $parts[] = 'वक्री'; }
        if ($f['combust'] !== null) { $parts[] = 'अस्त ' . $f['combust']['pct'] . '%'; }
        if ($f['ben_con'] !== []) { $parts[] = 'शुभ युति: ' . implode(', ', array_map([self::class, 'hi'], $f['ben_con'])); }
        if ($f['mal_con'] !== []) { $parts[] = 'पाप युति: ' . implode(', ', array_map([self::class, 'hi'], $f['mal_con'])); }
        if ($f['ben_asp'] > 0 || $f['mal_asp'] > 0) { $parts[] = sprintf('दृष्टि शुभ%d/पाप%d', $f['ben_asp'], $f['mal_asp']); }
        return implode(' · ', array_filter($parts, static fn($x) => $x !== ''));
    }

    private static function dignityPts(string $tier, bool $bhanga): float
    {
        return match ($tier) {
            'param_uchcha', 'exalt' => 2.0,
            'moolatrikona', 'own' => 1.5,
            'great_friend', 'friend' => 1.0,
            'neutral' => 0.0,
            'enemy', 'great_enemy' => -1.0,
            'debil' => $bhanga ? 0.0 : -2.0,
            'debil_bhanga' => 0.0,
            default => 0.0,
        };
    }

    /** @return array{word:string,tier:string} */
    private static function verdict(float $score, float $thr): array
    {
        if ($score >= $thr) {
            return ['word' => 'शुभ', 'tier' => 'shubh'];
        }
        if ($score <= -$thr) {
            return ['word' => 'अशुभ', 'tier' => 'ashubh'];
        }
        return ['word' => 'मिश्रित', 'tier' => 'mishrit'];
    }

    private static function verdictTier(string $word): string
    {
        if (str_contains($word, 'अशुभ')) {
            return 'ashubh';
        }
        if (str_contains($word, 'शुभ')) {
            return 'shubh';
        }
        return 'mishrit';
    }

    public static function hi(string $planet): string
    {
        return self::PLANET_HI[$planet] ?? $planet;
    }
}
