<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * 💍 विवाह मुहूर्त + मंगल-दोष (Phase 4 of the Muhurta-Chintamani engine).
 *
 * Two computable pieces from one screen:
 *   • विवाह मुहूर्त — date suitability of the chosen instant (VV-001/002/020):
 *     मास-शुद्धि (सूर्य-राशि) · विवाह-विहित नक्षत्र · तिथि · वार · गुरु/शुक्र अस्त · भद्रा.
 *   • मंगल (कुज) दोष — of the LOADED person's own D1 chart (MG rules): Mars from
 *     लग्न/चन्द्र/शुक्र, severity + which of the 14 परिहार cancel it.
 * (36-गुण मिलान needs the partner's chart → the panel links to Kundali Milan.)
 */
final class VivahaMuhuratEngine
{
    private const NAK_ARC = 40.0 / 3.0;
    private const D = MuhuratChintamaniData::class;
    private const OWN = ['Mars' => [0, 7]];   // मेष, वृश्चिक

    /** विवाह-मुहूर्त (date suitability) from the transit snapshot. */
    public static function muhurat(float $sunLon, float $moonLon, int $weekday, array $transits): array
    {
        $wd = (($weekday % 7) + 7) % 7;
        $sunRashi = (int) floor(fmod($sunLon + 360.0, 360.0) / 30.0) % 12;
        $moonNak = (int) floor(fmod($moonLon + 360.0, 360.0) / self::NAK_ARC) % 27;
        $elong = fmod($moonLon - $sunLon + 360.0, 360.0);
        $tithiNo = max(1, min(30, (int) floor($elong / 12.0) + 1));
        $tithiInPaksha = (($tithiNo - 1) % 15) + 1;
        // भद्रा = विष्टि करण
        $kIdx = (int) floor($elong / 6.0);
        $isBhadra = $kIdx >= 1 && $kIdx < 57 && (($kIdx - 1) % 7) === 6;

        $checks = []; $bad = [];
        $masOk = in_array($sunRashi, self::D::VIVAHA_SUN_RASHI, true);
        $checks[] = ['label' => 'मास (सूर्य-राशि)', 'value' => self::D::RASHI_HI[$sunRashi], 'ok' => $masOk];
        if (!$masOk) { $bad[] = ['name' => '📅 मास अशुद्ध', 'why' => 'सूर्य ' . self::D::RASHI_HI[$sunRashi] . ' — विवाह-विहित मास नहीं (चैत्र/पौष आदि वर्ज्य)', 'sev' => 2]; }

        $nakOk = in_array($moonNak, self::D::VIVAHA_NAK, true);
        $checks[] = ['label' => 'नक्षत्र', 'value' => self::D::NAK_HI[$moonNak], 'ok' => $nakOk];
        if (!$nakOk) { $bad[] = ['name' => '⭐ नक्षत्र अविहित', 'why' => self::D::NAK_HI[$moonNak] . ' — विवाह-विहित नक्षत्र नहीं', 'sev' => 2]; }

        $tithiOk = !in_array($tithiInPaksha, self::D::VIVAHA_BAD_TITHI, true);
        $checks[] = ['label' => 'तिथि', 'value' => 'तिथि ' . $tithiInPaksha, 'ok' => $tithiOk];
        if (!$tithiOk) { $bad[] = ['name' => '📉 तिथि वर्ज्य', 'why' => 'रिक्ता/पर्व तिथि — विवाह में त्याज्य', 'sev' => 1]; }

        $vaarOk = in_array($wd, self::D::VIVAHA_GOOD_VAAR, true);
        $checks[] = ['label' => 'वार', 'value' => self::D::WEEKDAY_HI[$wd], 'ok' => $vaarOk];
        if (in_array($wd, self::D::VIVAHA_BAD_VAAR, true)) { $bad[] = ['name' => '🗓️ वार अशुभ', 'why' => self::D::WEEKDAY_HI[$wd] . ' — विवाह हेतु सोम/बुध/गुरु/शुक्र श्रेष्ठ', 'sev' => 1]; }

        // गुरु/शुक्र अस्त — major विवाह-वर्ज्य
        $asta = [];
        foreach (['Jupiter' => 'गुरु', 'Venus' => 'शुक्र'] as $p => $pHi) {
            if (isset($transits[$p])) {
                $c = PlanetCondition::combustion($p, $transits);
                if ($c !== null) { $asta[] = $pHi; }
            }
        }
        if ($asta !== []) { $bad[] = ['name' => '☀️ ' . implode('/', $asta) . ' अस्त', 'why' => implode('/', $asta) . ' सूर्य से अस्त — विवाह वर्ज्य (गुरु-शुक्र बल आवश्यक)', 'sev' => 2]; }
        $checks[] = ['label' => 'गुरु-शुक्र बल', 'value' => $asta === [] ? 'दोनों उदित' : implode('/', $asta) . ' अस्त', 'ok' => $asta === []];

        if ($isBhadra) { $bad[] = ['name' => '🐢 भद्रा', 'why' => 'विष्टि करण — मंगल कार्य वर्ज्य', 'sev' => 1]; }
        $checks[] = ['label' => 'करण', 'value' => $isBhadra ? 'विष्टि (भद्रा)' : 'शुभ', 'ok' => !$isBhadra];

        $sev = 0; $strong = 0; foreach ($bad as $b) { $sev += (int) $b['sev']; if ((int) $b['sev'] >= 2) { $strong++; } }
        if ($strong >= 1) { $grade = 'अशुभ'; $tone = 'neg'; $verdict = 'विवाह हेतु प्रबल दोष (मास/नक्षत्र/गुरु-शुक्र अस्त) — यह मुहूर्त त्याज्य।'; }
        elseif ($sev === 0) { $grade = 'शुभ'; $tone = 'pos'; $verdict = 'मास · नक्षत्र · तिथि · वार व गुरु-शुक्र बल — सब अनुकूल। विवाह हेतु शुभ मुहूर्त।'; }
        else { $grade = 'मध्यम'; $tone = 'info'; $verdict = 'मुख्य अंग अनुकूल पर तिथि/वार/करण में बाधा — सुधार कर या शुभ लग्न में।'; }

        return ['ok' => true, 'grade' => $grade, 'tone' => $tone, 'verdict' => $verdict,
            'checks' => $checks, 'bad' => $bad,
            'note' => 'विवाह में लग्नशुद्धि (केन्द्र/त्रिकोण शुभ · 6/8/12 शुद्ध) व वर-कन्या की जन्म-राशि से गुरु-शुद्धि (1/5/9/2/7 श्रेष्ठ) भी देखें।'];
    }

    /** मंगल (कुज) दोष of the loaded person's own natal chart. */
    public static function mangalDosha(array $natal): array
    {
        $pl = $natal['planets'] ?? [];
        if (!isset($pl['Mars']['sign_index'])) { return ['ok' => false]; }
        $asc = (int) ($natal['ascendant']['sign_index'] ?? 0);
        $marsSign = (int) $pl['Mars']['sign_index'];
        $marsHouseFromLagna = (($marsSign - $asc + 12) % 12) + 1;
        $moonSign = (int) ($pl['Moon']['sign_index'] ?? 0);
        $venusSign = (int) ($pl['Venus']['sign_index'] ?? 0);
        $hFrom = static fn (int $from): int => (($marsSign - $from + 12) % 12) + 1;

        $bases = [
            'लग्न' => $marsHouseFromLagna,
            'चन्द्र' => $hFrom($moonSign),
            'शुक्र' => $hFrom($venusSign),
        ];
        $hit = [];
        foreach ($bases as $lbl => $hh) {
            if (in_array($hh, self::D::MANGAL_HOUSES, true)) { $hit[$lbl] = $hh; }
        }
        $count = count($hit);

        // ---- parihara (single-chart) ----
        $dig = PlanetCondition::resolve('Mars', $pl, $asc);
        $marsRetro = !empty($pl['Mars']['retro']);
        $marsCombust = ($dig['combust']['pct'] ?? 0) >= 40;
        $marsTier = (string) ($dig['dignity']['tier'] ?? '');
        $parihara = [];
        // MG-020: Cancer lagna
        if ($asc === 3) { $parihara[] = 'MG-020'; }
        // MG-019: retro / debil(कर्क) / combust
        if ($marsRetro || $marsSign === 3 || $marsCombust) { $parihara[] = 'MG-019'; }
        // MG-021: own/exalt/great-friend
        if (in_array($marsSign, self::OWN['Mars'], true) || $marsSign === 9 || in_array($marsTier, ['param_uchcha', 'exalt', 'moolatrikona', 'own', 'great_friend'], true)) { $parihara[] = 'MG-021'; }
        // MG-024: own/exalt sign in 4th or 7th
        if (in_array($marsHouseFromLagna, [4, 7], true) && in_array($marsSign, [0, 3, 7, 9], true)) { $parihara[] = 'MG-024'; }
        // MG-023: Mars conjunct Jupiter/Moon, or Moon in kendra from lagna
        $conj = static fn (string $p): bool => (int) ($pl[$p]['sign_index'] ?? -1) === $marsSign;
        $moonHouse = (($moonSign - $asc + 12) % 12) + 1;
        if ($conj('Jupiter') || $conj('Moon') || in_array($moonHouse, [1, 4, 7, 10], true)) { $parihara[] = 'MG-023'; }
        // MG-022: rashi-bhava specific
        $mg22 = ($marsSign === 0 && $marsHouseFromLagna === 1) || ($marsSign === 8 && $marsHouseFromLagna === 12)
            || ($marsSign === 7 && $marsHouseFromLagna === 4) || ($marsSign === 11 && $marsHouseFromLagna === 7)
            || ($marsSign === 10 && $marsHouseFromLagna === 8);
        if ($mg22) { $parihara[] = 'MG-022'; }
        $parihara = array_values(array_unique($parihara));

        $cancelled = $count > 0 && $parihara !== [];
        if ($count === 0) {
            $status = 'अमांगलिक (मंगल-दोष नहीं)'; $tone = 'pos';
            $verdict = 'लग्न, चन्द्र व शुक्र — तीनों से मंगल दोष-भाव में नहीं। मांगलिक दोष नहीं।';
        } elseif ($cancelled) {
            $status = 'मांगलिक — किन्तु दोष परिहृत'; $tone = 'info';
            $verdict = 'मंगल दोष-भाव में है पर शास्त्रीय परिहार लागू — दोष प्रायः निष्प्रभावी।';
        } else {
            $status = $count >= 2 ? 'प्रबल मांगलिक' : 'मांगलिक (मृदु)'; $tone = 'neg';
            $verdict = 'मंगल ' . implode(', ', array_map(static fn ($l, $h) => $l . ' से ' . $h . 'वें', array_keys($hit), array_values($hit)))
                . ' भाव में — मांगलिक दोष; समान-मांगलिक वर/वधू या परिहार अनुशंसित।';
        }

        $pariharaText = [];
        foreach ($parihara as $id) { $pariharaText[] = $id . ' — ' . (self::D::MANGAL_PARIHARA[$id][0] ?? ''); }

        return [
            'ok' => true, 'status' => $status, 'tone' => $tone, 'verdict' => $verdict,
            'bases' => $bases, 'hit' => $hit, 'count' => $count, 'cancelled' => $cancelled,
            'mars_sign' => self::D::RASHI_HI[$marsSign], 'mars_retro' => $marsRetro, 'mars_combust' => $marsCombust,
            'parihara' => $pariharaText,
            'note' => 'मंगल-दोष लग्न · चन्द्र · शुक्र — तीनों से देखा जाता है (MG-028)। दो-कुण्डली परिहार (दोनों मांगलिक/समान-भाव) हेतु कुण्डली-मिलान देखें।',
        ];
    }
}
