<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * 🧭 यात्रा-विस्तार · 🛕 प्रतिष्ठा मुहूर्त — Phase 8.
 *
 *   • यात्रा-विस्तार (YV): आज के वार-शूल की दिशा + उसका परिहार, समस्त वार-शूल
 *     परिहार-सारणी, प्रयाण-शकुन (शुभ/अशुभ) व अशुभ-शकुन उपाय, प्रस्थान-विधि।
 *   • प्रतिष्ठा (PR): देव-मूर्ति-स्थापन — विहित मास + उत्तरायण + स्थिर/मृदु नक्षत्र
 *     + शुभ तिथि/वार + गुरु/शुक्र उदय।
 * यात्रा-विस्तार Phase-5 की दिशा-श्रेणी का पूरक है (वहाँ प्रति-दिशा शूल-निर्णय है)।
 */
final class YatraPratishthaEngine
{
    private const NAK_ARC = 40.0 / 3.0;
    private const D = MuhuratChintamaniData::class;

    public static function compute(float $sunLon, float $moonLon, int $weekday, array $transits): array
    {
        $wd = (($weekday % 7) + 7) % 7;
        $sunSign = (int) floor(fmod($sunLon + 360.0, 360.0) / 30.0) % 12;
        $moonNak = (int) floor(fmod($moonLon + 360.0, 360.0) / self::NAK_ARC) % 27;
        $elong = fmod($moonLon - $sunLon + 360.0, 360.0);
        $tithiNo = max(1, min(30, (int) floor($elong / 12.0) + 1));
        $tip = (($tithiNo - 1) % 15) + 1;
        $maasa = self::D::CHANDRA_MAASA[$sunSign];
        $uttarayan = in_array($sunSign, [9, 10, 11, 0, 1, 2], true);
        $nakHi = self::D::NAK_HI[$moonNak];
        $vaarHi = self::D::WEEKDAY_HI[$wd];

        // ---- 🧭 यात्रा-विस्तार ----
        $shoolDir = self::D::VAAR_SHOOL[$wd] ?? '—';
        $todayParihara = self::D::VAAR_SHOOL_PARIHARA[$wd] ?? '—';
        $shoolNak = self::D::NAK_SHOOL[$moonNak] ?? null;         // आज नक्षत्र-शूल?
        $sarvadig = in_array($moonNak, self::D::YATRA_SARVADIG_NAK, true);
        $pariharaTable = [];
        foreach (self::D::VAAR_SHOOL_PARIHARA as $i => $rem) {
            $pariharaTable[] = [
                'vaar' => self::D::WEEKDAY_HI[$i],
                'dir' => self::D::VAAR_SHOOL[$i] ?? '—',
                'rem' => $rem,
                'today' => $i === $wd,
            ];
        }
        $yatravistara = [
            'vaar' => $vaarHi,
            'shool_dir' => $shoolDir,
            'today_parihara' => $todayParihara,
            'nak' => $nakHi,
            'nak_shool_dir' => $shoolNak,
            'nak_shool_parihara' => self::D::NAK_SHOOL_PARIHARA,
            'sarvadig' => $sarvadig,
            'table' => $pariharaTable,
            'shubh_shakun' => self::D::PRAYANA_SHUBH_SHAKUN,
            'ashubh_shakun' => self::D::PRAYANA_ASHUBH_SHAKUN,
            'shakun_remedy' => self::D::SHAKUN_REMEDY,
            'prasthana' => self::D::YATRA_PRASTHANA_NOTE,
        ];

        // ---- 🛕 प्रतिष्ठा ----
        $asta = [];
        foreach (['Jupiter' => 'गुरु', 'Venus' => 'शुक्र'] as $p => $pHi) {
            if (isset($transits[$p]) && PlanetCondition::combustion($p, $transits) !== null) { $asta[] = $pHi; }
        }
        $astaStr = implode('/', $asta);
        $prBad = [];
        $prNakOk = in_array($moonNak, self::D::PRATISHTHA_NAK, true);
        if (!$prNakOk) { $prBad[] = ['name' => '⭐ नक्षत्र अविहित', 'why' => $nakHi . ' — प्रतिष्ठा हेतु स्थिर/मृदु/ध्रुव नक्षत्र श्रेष्ठ', 'sev' => 2]; }
        $prMaasOk = in_array($maasa, self::D::PRATISHTHA_GOOD_MAAS, true);
        if (!$prMaasOk) { $prBad[] = ['name' => '📅 मास अविहित', 'why' => $maasa . ' — प्रतिष्ठा हेतु माघ/फाल्गुन/वैशाख/ज्येष्ठ/आषाढ़ श्रेष्ठ', 'sev' => 1]; }
        if (!$uttarayan) { $prBad[] = ['name' => '☀️ दक्षिणायन', 'why' => 'देव-प्रतिष्ठा हेतु उत्तरायण श्रेष्ठ', 'sev' => 1]; }
        if (in_array($tip, self::D::PRATISHTHA_BAD_TITHI, true)) { $prBad[] = ['name' => '📉 तिथि वर्ज्य', 'why' => 'रिक्ता/अमा तिथि — त्याज्य; शुक्ल पक्ष श्रेष्ठ', 'sev' => 1]; }
        if (!in_array($wd, self::D::PRATISHTHA_VAAR, true)) { $prBad[] = ['name' => '🗓️ वार अशुभ', 'why' => $vaarHi . ' — रवि/सोम/बुध/गुरु/शुक्र श्रेष्ठ (मंगल·शनि त्याज्य)', 'sev' => 1]; }
        if ($asta !== []) { $prBad[] = ['name' => '☀️ ' . $astaStr . ' अस्त', 'why' => $astaStr . ' अस्त — देव-प्रतिष्ठा हेतु गुरु/शुक्र बली व उदित आवश्यक', 'sev' => 2]; }
        $pratishtha = self::grade($prBad, [
            'मास' => $maasa . ($prMaasOk ? ' (विहित)' : ''),
            'नक्षत्र' => $nakHi . ($prNakOk ? ' ✓' : ''),
            'अयन' => $uttarayan ? 'उत्तरायण' : 'दक्षिणायन',
            'तिथि·वार' => 'तिथि ' . $tip . ' · ' . $vaarHi,
            'गुरु-शुक्र' => $asta !== [] ? $astaStr . ' अस्त' : 'उदित',
        ], 'स्थिर लग्न व शुक्ल पक्ष श्रेष्ठ; गुरु/शुक्र बली-उदित हों; मलमास त्याज्य। जीर्णोद्धार/पुनः-प्रतिष्ठा में भी यही शुद्धि देखें।');

        return [
            'ok' => true, 'maasa' => $maasa, 'uttarayan' => $uttarayan,
            'nak' => $nakHi, 'tithi' => $tip, 'vaar' => $vaarHi,
            'yatravistara' => $yatravistara, 'pratishtha' => $pratishtha,
        ];
    }

    /** Build a {grade,tone,verdict,checks,bad,note} block. */
    private static function grade(array $bad, array $checks, string $note): array
    {
        $sev = 0; $strong = 0;
        foreach ($bad as $b) { $sev += (int) $b['sev']; if ((int) $b['sev'] >= 2) { $strong++; } }
        if ($strong >= 1) { $g = 'अशुभ'; $t = 'neg'; $v = 'प्रबल दोष — यह मुहूर्त त्याज्य, विहित मास/नक्षत्र वाला दिन चुनें।'; }
        elseif ($sev === 0) { $g = 'शुभ'; $t = 'pos'; $v = 'सभी अंग अनुकूल — शुभ मुहूर्त।'; }
        else { $g = 'मध्यम'; $t = 'info'; $v = 'मुख्य अंग अनुकूल पर एक बाधा — सुधार या उपाय-सहित।'; }
        $rows = [];
        foreach ($checks as $lbl => $val) { $rows[] = ['label' => $lbl, 'value' => $val]; }
        return ['grade' => $g, 'tone' => $t, 'verdict' => $v, 'checks' => $rows, 'bad' => $bad, 'note' => $note];
    }
}
