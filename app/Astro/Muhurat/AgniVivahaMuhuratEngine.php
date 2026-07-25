<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * 🔥 अग्न्याधान · 💑 वधूप्रवेश · 🔄 द्विरागमन मुहूर्त — Phase 7.
 *
 *   • अग्न्याधान (AG): कृत्तिकादि विहित नक्षत्र + ऋतु-शुद्धि (वर्ण-भेद) + उत्तरायण +
 *     शुभ तिथि/वार। श्रौताग्नि-स्थापन का काल।
 *   • वधूप्रवेश (VP): वधू का प्रथम गृह-प्रवेश — गृहप्रवेश-सम मास-शुद्धि + सौम्य नक्षत्र
 *     + शुभ तिथि/वार + गुरु/शुक्र उदय।
 *   • द्विरागमन (DR): गौना/द्वितीय-प्रयाण — यात्रा-सम चर-नक्षत्र + सम-मास + शुभ तिथि/वार।
 * Each judged for the chosen instant; relational rules (वधू-जन्ममास, सम-मास) noted.
 */
final class AgniVivahaMuhuratEngine
{
    private const NAK_ARC = 40.0 / 3.0;
    private const D = MuhuratChintamaniData::class;

    public static function computeAll(float $sunLon, float $moonLon, int $weekday, array $transits): array
    {
        $wd = (($weekday % 7) + 7) % 7;
        $sunSign = (int) floor(fmod($sunLon + 360.0, 360.0) / 30.0) % 12;
        $moonNak = (int) floor(fmod($moonLon + 360.0, 360.0) / self::NAK_ARC) % 27;
        $elong = fmod($moonLon - $sunLon + 360.0, 360.0);
        $tithiNo = max(1, min(30, (int) floor($elong / 12.0) + 1));
        $tip = (($tithiNo - 1) % 15) + 1;               // तिथि-in-paksha 1..15
        $maasa = self::D::CHANDRA_MAASA[$sunSign];
        $ritu = self::D::RITU_BY_SUNSIGN[$sunSign];
        $uttarayan = in_array($sunSign, [9, 10, 11, 0, 1, 2], true);
        $nakHi = self::D::NAK_HI[$moonNak];
        $vaarHi = self::D::WEEKDAY_HI[$wd];

        // गुरु/शुक्र अस्त — विवाह-सम कर्मों में वर्ज्य
        $asta = [];
        foreach (['Jupiter' => 'गुरु', 'Venus' => 'शुक्र'] as $p => $pHi) {
            if (isset($transits[$p]) && PlanetCondition::combustion($p, $transits) !== null) { $asta[] = $pHi; }
        }
        $astaStr = implode('/', $asta);

        // ---- 🔥 अग्न्याधान ----
        $agBad = [];
        $agNakOk = in_array($moonNak, self::D::AGNI_NAK, true);
        if (!$agNakOk) { $agBad[] = ['name' => '⭐ नक्षत्र अविहित', 'why' => $nakHi . ' — अग्न्याधान हेतु कृत्तिकादि विहित नक्षत्र श्रेष्ठ', 'sev' => 2]; }
        $agRituOk = in_array($ritu, self::D::AGNI_GOOD_RITU, true);
        if (!$agRituOk) { $agBad[] = ['name' => '🍂 ऋतु प्रतिकूल', 'why' => $ritu . ' — वसन्त/ग्रीष्म/हेमन्त ऋतु श्रेष्ठ (वर्ण-भेद से)', 'sev' => 1]; }
        if (!$uttarayan) { $agBad[] = ['name' => '☀️ दक्षिणायन', 'why' => 'अग्न्याधान हेतु उत्तरायण श्रेष्ठ', 'sev' => 1]; }
        if (in_array($tip, self::D::AGNI_BAD_TITHI, true)) { $agBad[] = ['name' => '📉 तिथि वर्ज्य', 'why' => 'रिक्ता/अमा तिथि — त्याज्य', 'sev' => 1]; }
        if (!in_array($wd, self::D::AGNI_VAAR, true)) { $agBad[] = ['name' => '🗓️ वार अशुभ', 'why' => $vaarHi . ' — रवि/सोम/बुध/गुरु/शुक्र श्रेष्ठ (मंगल·शनि त्याज्य)', 'sev' => 1]; }
        $agnyadhana = self::grade($agBad, [
            'नक्षत्र' => $nakHi . ($agNakOk ? ' ✓' : ''),
            'ऋतु' => $ritu . ($agRituOk ? ' ✓' : ''),
            'अयन' => $uttarayan ? 'उत्तरायण' : 'दक्षिणायन',
            'तिथि·वार' => 'तिथि ' . $tip . ' · ' . $vaarHi,
        ], 'वर्ण-भेद से ऋतु: ब्राह्मण—वसन्त, क्षत्रिय—ग्रीष्म, वैश्य—शरद/वर्षा। कृत्तिका (अग्नि-देवता) श्रेष्ठतम; उत्तरायण व शुभ लग्न में स्थापन करें।');

        // ---- 💑 वधूप्रवेश ----
        $vpBad = [];
        $vpNakOk = in_array($moonNak, self::D::VADHU_NAK, true);
        if (!$vpNakOk) { $vpBad[] = ['name' => '⭐ नक्षत्र अविहित', 'why' => $nakHi . ' — वधूप्रवेश हेतु सौम्य/स्थिर नक्षत्र श्रेष्ठ', 'sev' => 2]; }
        $vpMaasBad = in_array($maasa, self::D::VADHU_BAD_MAAS, true);
        if ($vpMaasBad) { $vpBad[] = ['name' => '📅 मास त्याज्य', 'why' => $maasa . ' — चैत्र/भाद्र/आश्विन/पौष वर्ज्य', 'sev' => 2]; }
        if (in_array($tip, self::D::VADHU_BAD_TITHI, true)) { $vpBad[] = ['name' => '📉 तिथि वर्ज्य', 'why' => 'रिक्ता/अमा तिथि — त्याज्य', 'sev' => 1]; }
        if (!in_array($wd, self::D::VADHU_VAAR, true)) { $vpBad[] = ['name' => '🗓️ वार अशुभ', 'why' => $vaarHi . ' — सोम/बुध/गुरु/शुक्र श्रेष्ठ', 'sev' => 1]; }
        if ($asta !== []) { $vpBad[] = ['name' => '☀️ ' . $astaStr . ' अस्त', 'why' => $astaStr . ' अस्त — वधूप्रवेश हेतु उदित आवश्यक', 'sev' => 2]; }
        $vpGoodMaas = in_array($maasa, self::D::VADHU_GOOD_MAAS, true);
        $vadhupravesh = self::grade($vpBad, [
            'मास' => $maasa . ($vpGoodMaas ? ' (विहित)' : ($vpMaasBad ? ' (त्याज्य)' : '')),
            'नक्षत्र' => $nakHi . ($vpNakOk ? ' ✓' : ''),
            'तिथि·वार' => 'तिथि ' . $tip . ' · ' . $vaarHi,
            'गुरु-शुक्र' => $asta !== [] ? $astaStr . ' अस्त' : 'उदित',
        ], 'वधू का जन्म-मास व जन्म-नक्षत्र त्याज्य। गृहप्रवेश-सम मास-शुद्धि लें; द्वार-पूजन व मंगल-कलश सहित गृह-प्रवेश कराएँ।');

        // ---- 🔄 द्विरागमन (गौना) ----
        $drBad = [];
        $drNakOk = in_array($moonNak, self::D::DVIRA_NAK, true);
        if (!$drNakOk) { $drBad[] = ['name' => '⭐ नक्षत्र अविहित', 'why' => $nakHi . ' — द्विरागमन हेतु चर/सौम्य (यात्रा-सम) नक्षत्र श्रेष्ठ', 'sev' => 2]; }
        $drMaasBad = in_array($maasa, self::D::DVIRA_BAD_MAAS, true);
        if ($drMaasBad) { $drBad[] = ['name' => '📅 मास त्याज्य', 'why' => $maasa . ' — ज्येष्ठ/आषाढ़/पौष व मलमास वर्ज्य', 'sev' => 1]; }
        if (in_array($tip, self::D::DVIRA_BAD_TITHI, true)) { $drBad[] = ['name' => '📉 तिथि वर्ज्य', 'why' => 'रिक्ता/अमा तिथि — त्याज्य', 'sev' => 1]; }
        if (!in_array($wd, self::D::DVIRA_VAAR, true)) { $drBad[] = ['name' => '🗓️ वार अशुभ', 'why' => $vaarHi . ' — सोम/बुध/गुरु/शुक्र श्रेष्ठ', 'sev' => 1]; }
        if ($asta !== []) { $drBad[] = ['name' => '☀️ ' . $astaStr . ' अस्त', 'why' => $astaStr . ' अस्त — शुभ हेतु उदित श्रेष्ठ', 'sev' => 1]; }
        $dviragamana = self::grade($drBad, [
            'मास' => $maasa . ($drMaasBad ? ' (त्याज्य)' : ''),
            'नक्षत्र' => $nakHi . ($drNakOk ? ' ✓' : ''),
            'तिथि·वार' => 'तिथि ' . $tip . ' · ' . $vaarHi,
            'गुरु-शुक्र' => $asta !== [] ? $astaStr . ' अस्त' : 'उदित',
        ], 'विवाह से सम (युग्म) मास/वर्ष में द्विरागमन श्रेष्ठ; वधू का जन्म-मास व जन्म-नक्षत्र त्याज्य। प्रयाण-सम चर-नक्षत्र अनुकूल — यात्रा-शूल भी देखें।');

        return [
            'ok' => true, 'maasa' => $maasa, 'ritu' => $ritu, 'uttarayan' => $uttarayan,
            'nak' => $nakHi, 'tithi' => $tip, 'vaar' => $vaarHi,
            'agnyadhana' => $agnyadhana, 'vadhupravesh' => $vadhupravesh, 'dviragamana' => $dviragamana,
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
