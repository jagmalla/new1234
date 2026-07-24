<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * 🏗️ वास्तु · 🚪 गृहप्रवेश · 👑 राज्याभिषेक मुहूर्त — Phase 6.
 *
 *   • गृहारम्भ (VS): चान्द्रमास-फल (VS-014) + वृषभ-वास्तु चक्र (VS-011, सूर्य-नक्षत्र से)
 *   • गृहप्रवेश (GP): विहित मास (GP-001/002) + कुम्भ-चक्र (GP-005, सूर्य-नक्षत्र से)
 *   • राज्याभिषेक/शपथ (RA): अयन-शुद्धि + विहित नक्षत्र/लग्न + गुरु-शुक्र उदय (RA-001/002)
 * Each judged for the chosen instant. राज्याभिषेक links to the Politics module.
 */
final class VastuMuhuratEngine
{
    private const NAK_ARC = 40.0 / 3.0;
    private const D = MuhuratChintamaniData::class;

    public static function computeAll(float $sunLon, float $moonLon, int $weekday, array $transits): array
    {
        $wd = (($weekday % 7) + 7) % 7;
        $sunSign = (int) floor(fmod($sunLon + 360.0, 360.0) / 30.0) % 12;
        $sunNak = (int) floor(fmod($sunLon + 360.0, 360.0) / self::NAK_ARC) % 27;
        $moonNak = (int) floor(fmod($moonLon + 360.0, 360.0) / self::NAK_ARC) % 27;
        $maasa = self::D::CHANDRA_MAASA[$sunSign];
        // अयन — मकर-संक्रान्ति (सूर्य सिद्ध मकर) से कर्क-संक्रान्ति तक उत्तरायण
        $uttarayan = in_array($sunSign, [9, 10, 11, 0, 1, 2], true);

        // ---- गृहारम्भ / वास्तु ----
        $mp = self::D::GRIHA_MAASA_PHALA[$maasa] ?? ['—', true];
        // वृषभ-वास्तु: सूर्य-नक्षत्र से चन्द्र-नक्षत्र तक गणना (1..27); 8-18 शुभ, शेष अशुभ
        $vc = (($moonNak - $sunNak + 27) % 27) + 1;
        $vcShubh = $vc >= 8 && $vc <= 18;
        $vasBad = [];
        if (!$mp[1]) { $vasBad[] = ['name' => '📅 मास अशुभ', 'why' => $maasa . ' मास — ' . $mp[0], 'sev' => 2]; }
        if (!$vcShubh) { $vasBad[] = ['name' => '🐂 वृषभ-वास्तु प्रतिकूल', 'why' => 'सूर्य-नक्षत्र से गणना ' . $vc . ' (शुभ: 8-18)', 'sev' => 1]; }
        $vastu = self::grade($vasBad, [
            'माह' => $maasa . ' — ' . $mp[0] . ($mp[1] ? ' (शुभ)' : ' (अशुभ)'),
            'वृषभ-वास्तु' => 'गणना ' . $vc . ($vcShubh ? ' — शुभ (8-18)' : ' — अशुभ'),
        ], 'गृहारम्भ हेतु उत्तरायण, शुभ नक्षत्र/तिथि व वृषभ-वास्तु 8-18 श्रेष्ठ। शिलान्यास में लग्नशुद्धि भी देखें।');

        // ---- गृहप्रवेश ----
        $isNew = in_array($maasa, self::D::GRIHAPRAVESH_MAAS_NEW, true);
        $isJirna = in_array($maasa, self::D::GRIHAPRAVESH_MAAS_JIRNA, true);
        // कुम्भ-चक्र: सूर्य-नक्षत्र से गणना
        $kc = (($moonNak - $sunNak + 27) % 27) + 1;
        [$kName, $kShubh] = self::kumbha($kc);
        $gpBad = [];
        if (!$isNew && !$isJirna) { $gpBad[] = ['name' => '📅 मास अविहित', 'why' => $maasa . ' — गृहप्रवेश-विहित मास नहीं (ज्येष्ठ/माघ/फाल्गुन/वैशाख नवीन; मार्गशीर्ष/कार्तिक/श्रावण जीर्ण)', 'sev' => 2]; }
        if (!$kShubh) { $gpBad[] = ['name' => '🏺 कुम्भ-चक्र प्रतिकूल', 'why' => $kName . ' — ' . $kc . ' गणना', 'sev' => 1]; }
        if (!$uttarayan) { $gpBad[] = ['name' => '☀️ दक्षिणायन', 'why' => 'गृहप्रवेश हेतु उत्तरायण श्रेष्ठ', 'sev' => 1]; }
        $grihapravesh = self::grade($gpBad, [
            'माह' => $maasa . ($isNew ? ' (नवीन-विहित)' : ($isJirna ? ' (जीर्ण-विहित)' : ' (अविहित)')),
            'कुम्भ-चक्र' => $kName . ' (गणना ' . $kc . ')',
            'अयन' => $uttarayan ? 'उत्तरायण' : 'दक्षिणायन',
        ], 'अपूर्व/सपूर्व/द्वन्द्वाभय प्रकार पहले तय करें; वास्तु-पूजन व भूतबलि सहित प्रवेश करें।');

        // ---- राज्याभिषेक / शपथ ----
        $raNakOk = in_array($moonNak, self::D::RAJYA_NAK, true) || in_array(self::D::SANJNA[$moonNak], self::D::RAJYA_SANJNA, true);
        $guruAsta = isset($transits['Jupiter']) && PlanetCondition::combustion('Jupiter', $transits) !== null;
        $shukraAsta = isset($transits['Venus']) && PlanetCondition::combustion('Venus', $transits) !== null;
        $raBad = [];
        if (!$uttarayan) { $raBad[] = ['name' => '☀️ दक्षिणायन', 'why' => 'राज्याभिषेक हेतु उत्तरायण श्रेष्ठ', 'sev' => 1]; }
        if (!$raNakOk) { $raBad[] = ['name' => '⭐ नक्षत्र अविहित', 'why' => self::D::NAK_HI[$moonNak] . ' — ज्येष्ठा/श्रवण या क्षिप्र/ध्रुव-संज्ञक श्रेष्ठ', 'sev' => 1]; }
        if ($guruAsta || $shukraAsta) { $raBad[] = ['name' => '☀️ गुरु/शुक्र अस्त', 'why' => trim(($guruAsta ? 'गुरु ' : '') . ($shukraAsta ? 'शुक्र ' : '')) . 'अस्त — उदित व बली आवश्यक', 'sev' => 2]; }
        $rajya = self::grade($raBad, [
            'अयन' => $uttarayan ? 'उत्तरायण' : 'दक्षिणायन',
            'नक्षत्र' => self::D::NAK_HI[$moonNak] . ' (' . self::D::SANJNA[$moonNak] . ')' . ($raNakOk ? ' ✓' : ''),
            'गुरु-शुक्र' => ($guruAsta || $shukraAsta) ? 'अस्त' : 'उदित',
        ], 'शीर्षोदय/उपचय लग्न, केन्द्र-त्रिकोण में शुभ ग्रह, गुरु 1/5/9, शुक्र 10 — राजलक्ष्मी योग (RA-004)।');

        return [
            'ok' => true, 'maasa' => $maasa, 'uttarayan' => $uttarayan,
            'vastu' => $vastu, 'grihapravesh' => $grihapravesh, 'rajya' => $rajya,
        ];
    }

    /** @return array{0:string,1:bool} कुम्भ-चक्र फल (GP-005). */
    private static function kumbha(int $n): array
    {
        if ($n === 1) { return ['मुख — अग्निभय', false]; }
        if ($n <= 5) { return ['पूर्व — उद्वसन', false]; }
        if ($n <= 9) { return ['दक्षिण — धन-लाभ', true]; }
        if ($n <= 13) { return ['पश्चिम — लक्ष्मी', true]; }
        if ($n <= 17) { return ['उत्तर — कलह', false]; }
        if ($n <= 21) { return ['गर्भ — विनाश', false]; }
        if ($n <= 24) { return ['अधोभाग — स्थिरता', true]; }
        return ['कण्ठ — स्थिरत्व', true];
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
