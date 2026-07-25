<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

/**
 * ⚔️ युद्ध · 🌾 कृषि · 💰 वाणिज्य/ऋण · 💊 रोग-चिकित्सा मुहूर्त — Phase 9.
 *
 *   • युद्ध/संग्राम (YU): बल-आधारित — उग्र/तीक्ष्ण/चर (बल) नक्षत्र + मंगल/रवि/शनि
 *     वार + शुक्ल-पक्ष बल-वृद्धि। प्रतिस्पर्धा/विवाद/प्रतियोगिता हेतु भी।
 *   • कृषि (KR): बीज-वपन/रोपण — उर्वर नक्षत्र + शुभ वार + तिथि।
 *   • वाणिज्य/ऋण (VN): व्यापार-आरम्भ — क्षिप्र/चर नक्षत्र + बुध/गुरु/शुक्र वार;
 *     ऋण-दान शुक्ल-पक्ष श्रेष्ठ, ऋण-ग्रहण कृष्ण-पक्ष अनुकूल (ऋण क्षीण हो)।
 *   • रोग-चिकित्सा (CH): औषध/चिकित्सा-आरम्भ — वैद्य-नक्षत्र (अश्विनी/शतभिषा) + शुभ वार।
 */
final class KaryaMuhuratEngine
{
    private const NAK_ARC = 40.0 / 3.0;
    private const D = MuhuratChintamaniData::class;

    public static function computeAll(float $sunLon, float $moonLon, int $weekday): array
    {
        $wd = (($weekday % 7) + 7) % 7;
        $moonNak = (int) floor(fmod($moonLon + 360.0, 360.0) / self::NAK_ARC) % 27;
        $elong = fmod($moonLon - $sunLon + 360.0, 360.0);
        $tithiNo = max(1, min(30, (int) floor($elong / 12.0) + 1));
        $tip = (($tithiNo - 1) % 15) + 1;
        $shukla = $tithiNo <= 15;
        $paksha = $shukla ? 'शुक्ल' : 'कृष्ण';
        $nakHi = self::D::NAK_HI[$moonNak];
        $vaarHi = self::D::WEEKDAY_HI[$wd];
        $sanjna = self::D::SANJNA[$moonNak];
        $tvStr = 'तिथि ' . $tip . ' (' . $paksha . ') · ' . $vaarHi;

        // ---- ⚔️ युद्ध / संग्राम (बल-आधारित) ----
        $yuBad = [];
        $yuNakBal = in_array($moonNak, self::D::YUDDHA_BAL_NAK, true);
        if (!$yuNakBal) { $yuBad[] = ['name' => '⭐ बल-हीन नक्षत्र', 'why' => $nakHi . ' (' . $sanjna . ') — युद्ध हेतु उग्र/तीक्ष्ण/चर बल-नक्षत्र श्रेष्ठ', 'sev' => 2]; }
        $yuVaarBal = in_array($wd, self::D::YUDDHA_BAL_VAAR, true);
        if (!$yuVaarBal) { $yuBad[] = ['name' => '🗓️ बल-हीन वार', 'why' => $vaarHi . ' — मंगल/रवि/शनि (उग्र वार) बल-वर्धक', 'sev' => 1]; }
        if (!$shukla) { $yuBad[] = ['name' => '🌙 चन्द्र-बल क्षीण', 'why' => 'कृष्ण-पक्ष — शुक्ल-पक्ष में बल-वृद्धि', 'sev' => 1]; }
        $yuddha = self::grade($yuBad, [
            'नक्षत्र' => $nakHi . ' (' . $sanjna . ')' . ($yuNakBal ? ' ✓बल' : ''),
            'वार' => $vaarHi . ($yuVaarBal ? ' ✓बल' : ''),
            'पक्ष' => $paksha . ($shukla ? ' (बल-वृद्धि)' : ''),
        ], 'युद्ध/प्रतिस्पर्धा हेतु उग्र-बल आवश्यक — सामान्य शुभ कार्य से भिन्न। दिशा-शूल में कूच न करें (यात्रा श्रेणी देखें); सेनापति/नायक की लग्न-शुद्धि व मंगल-बल देखें। प्रतियोगिता·विवाद·मुकदमे में भी लागू।', 'जय');

        // ---- 🌾 कृषि ----
        $krBad = [];
        $krNakOk = in_array($moonNak, self::D::KRISHI_NAK, true);
        if (!$krNakOk) { $krBad[] = ['name' => '⭐ नक्षत्र अनुर्वर', 'why' => $nakHi . ' — बीज-वपन हेतु उर्वर नक्षत्र (रोहिणी/पुष्य/हस्त आदि) श्रेष्ठ', 'sev' => 2]; }
        if (in_array($tip, self::D::KRISHI_BAD_TITHI, true)) { $krBad[] = ['name' => '📉 तिथि वर्ज्य', 'why' => 'रिक्ता/अमा तिथि — त्याज्य', 'sev' => 1]; }
        if (!in_array($wd, self::D::KRISHI_VAAR, true)) { $krBad[] = ['name' => '🗓️ वार अशुभ', 'why' => $vaarHi . ' — सोम/बुध/गुरु/शुक्र/शनि श्रेष्ठ (रवि·मंगल अग्नि-कारक त्याज्य)', 'sev' => 1]; }
        $krishi = self::grade($krBad, [
            'नक्षत्र' => $nakHi . ($krNakOk ? ' ✓' : ''),
            'तिथि·वार' => $tvStr,
        ], 'रोहिणी बीज-वपन हेतु श्रेष्ठतम। ऋतु-अनुसार (खरीफ/रबी) बीज चुनें; जल-स्रोत व भूमि-शुद्धि सहित आरम्भ करें।', 'शुभ');

        // ---- 💰 वाणिज्य / ऋण ----
        $vnBad = [];
        $vnNakOk = in_array($moonNak, self::D::VANIJYA_NAK, true);
        if (!$vnNakOk) { $vnBad[] = ['name' => '⭐ नक्षत्र अविहित', 'why' => $nakHi . ' — व्यापार हेतु क्षिप्र/चर/लघु नक्षत्र श्रेष्ठ', 'sev' => 2]; }
        if (in_array($tip, self::D::VANIJYA_BAD_TITHI, true)) { $vnBad[] = ['name' => '📉 तिथि वर्ज्य', 'why' => 'रिक्ता/अमा तिथि — त्याज्य', 'sev' => 1]; }
        if (!in_array($wd, self::D::VANIJYA_VAAR, true)) { $vnBad[] = ['name' => '🗓️ वार अशुभ', 'why' => $vaarHi . ' — बुध (वाणिज्य-कारक)/गुरु/शुक्र/सोम श्रेष्ठ', 'sev' => 1]; }
        $rinNote = $shukla
            ? 'आज शुक्ल-पक्ष — ऋण-दान (उधार देना) व नव-व्यापार-आरम्भ श्रेष्ठ (धन वृद्धि-सहित लौटे)।'
            : 'आज कृष्ण-पक्ष — ऋण-ग्रहण (उधार लेना) अनुकूल (ऋण क्षीण होता जाए); ऋण-दान हेतु शुक्ल-पक्ष लें।';
        $vanijya = self::grade($vnBad, [
            'नक्षत्र' => $nakHi . ' (' . $sanjna . ')' . ($vnNakOk ? ' ✓' : ''),
            'तिथि·वार' => $tvStr,
            'पक्ष' => $paksha,
        ], 'ऋण-नियम पक्ष-भेद से विपरीत: ' . $rinNote . ' बुधवार व क्षिप्र नक्षत्र दुकान/लेखा-आरम्भ हेतु उत्तम।', 'शुभ');

        // ---- 💊 रोग / चिकित्सा-आरम्भ ----
        $chBad = [];
        $chNakOk = in_array($moonNak, self::D::CHIKITSA_NAK, true);
        if (!$chNakOk) { $chBad[] = ['name' => '⭐ नक्षत्र अविहित', 'why' => $nakHi . ' — चिकित्सा हेतु वैद्य-नक्षत्र (अश्विनी/शतभिषा/पुष्य/हस्त) श्रेष्ठ', 'sev' => 2]; }
        if (in_array($tip, self::D::CHIKITSA_BAD_TITHI, true)) { $chBad[] = ['name' => '📉 तिथि वर्ज्य', 'why' => 'रिक्ता/अमा तिथि — त्याज्य', 'sev' => 1]; }
        if (!in_array($wd, self::D::CHIKITSA_VAAR, true)) { $chBad[] = ['name' => '🗓️ वार अशुभ', 'why' => $vaarHi . ' — रवि/बुध/गुरु/शुक्र श्रेष्ठ', 'sev' => 1]; }
        if (!$shukla) { $chBad[] = ['name' => '🌙 चन्द्र-बल क्षीण', 'why' => 'कृष्ण-पक्ष — औषध-आरम्भ हेतु बली (शुक्ल) चन्द्र श्रेष्ठ', 'sev' => 1]; }
        $chikitsa = self::grade($chBad, [
            'नक्षत्र' => $nakHi . ($chNakOk ? ' ✓' : ''),
            'तिथि·वार' => $tvStr,
            'पक्ष' => $paksha,
        ], 'अश्विनी (अश्विनीकुमार) व शतभिषा औषध-आरम्भ हेतु श्रेष्ठतम। क्षीण चन्द्र में औषध न आरम्भ करें; शल्यकर्म हेतु मंगलवार व क्रूर नक्षत्र का भिन्न विधान (रोग-अंग-राशि में चन्द्र त्याज्य)।', 'शुभ');

        return [
            'ok' => true, 'nak' => $nakHi, 'tithi' => $tip, 'paksha' => $paksha, 'vaar' => $vaarHi,
            'yuddha' => $yuddha, 'krishi' => $krishi, 'vanijya' => $vanijya, 'chikitsa' => $chikitsa,
        ];
    }

    /**
     * Build a {grade,tone,verdict,checks,bad,note} block.
     * $posWord — the शुभ-grade label ('शुभ' या 'जय' for war).
     */
    private static function grade(array $bad, array $checks, string $note, string $posWord): array
    {
        $sev = 0; $strong = 0;
        foreach ($bad as $b) { $sev += (int) $b['sev']; if ((int) $b['sev'] >= 2) { $strong++; } }
        if ($strong >= 1) { $g = 'अशुभ'; $t = 'neg'; $v = 'प्रबल दोष — यह मुहूर्त त्याज्य, विहित नक्षत्र/वार वाला दिन चुनें।'; }
        elseif ($sev === 0) { $g = $posWord; $t = 'pos'; $v = 'सभी अंग अनुकूल — श्रेष्ठ मुहूर्त।'; }
        else { $g = 'मध्यम'; $t = 'info'; $v = 'मुख्य अंग अनुकूल पर एक बाधा — सुधार या उपाय-सहित।'; }
        $rows = [];
        foreach ($checks as $lbl => $val) { $rows[] = ['label' => $lbl, 'value' => $val]; }
        return ['grade' => $g, 'tone' => $t, 'verdict' => $v, 'checks' => $rows, 'bad' => $bad, 'note' => $note];
    }
}
