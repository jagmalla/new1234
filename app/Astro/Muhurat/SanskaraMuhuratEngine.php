<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

/**
 * 🧒 संस्कार मुहूर्त (Sanskara Muhurat) — Phase 3 of the Muhurta-Chintamani engine.
 *
 * Judges the chosen instant for each of the child-rites (नामकरण · अन्नप्राशन ·
 * कर्णवेध · मुण्डन · विद्यारम्भ · उपनयन) against that rite's prescribed नक्षत्र /
 * तिथि / वार (SN-003…SN-008). Sits ON TOP of the General panchang-shuddhi and the
 * Personal (child's chart) layers. Computes every rite at once so the UI can
 * switch rites without a re-fetch. Pure & reusable.
 */
final class SanskaraMuhuratEngine
{
    private const NAK_ARC = 40.0 / 3.0;
    private const D = MuhuratChintamaniData::class;

    /**
     * @param float $sunLon  transit Sun sidereal longitude
     * @param float $moonLon transit Moon sidereal longitude
     * @param int   $weekday 0 रवि .. 6 शनि
     * @return array<string,mixed>
     */
    public static function computeAll(float $sunLon, float $moonLon, int $weekday): array
    {
        $wd = (($weekday % 7) + 7) % 7;
        $moonNak = (int) floor(fmod($moonLon + 360.0, 360.0) / self::NAK_ARC) % 27;
        $moonSanjna = self::D::SANJNA[$moonNak];
        $elong = fmod($moonLon - $sunLon + 360.0, 360.0);
        $tithiNo = max(1, min(30, (int) floor($elong / 12.0) + 1));
        $tithiInPaksha = (($tithiNo - 1) % 15) + 1;

        $rites = [];
        foreach (self::D::SANSKARA as $key => $s) {
            // नक्षत्र — by name-list OR by sanjna group
            $nakOk = in_array($moonNak, $s['nak'], true) || in_array($moonSanjna, $s['sanjna'], true);
            // तिथि — must not be in bad_tithi; if good_tithi given, must be in it
            $tithiOk = !in_array($tithiInPaksha, $s['bad_tithi'], true)
                && ($s['good_tithi'] === [] || in_array($tithiInPaksha, $s['good_tithi'], true));
            // वार — not in bad_vaar; if good_vaar given, must be in it
            $vaarOk = !in_array($wd, $s['bad_vaar'], true)
                && ($s['good_vaar'] === [] || in_array($wd, $s['good_vaar'], true));

            $okCount = (int) $nakOk + (int) $tithiOk + (int) $vaarOk;
            if (!$nakOk) {
                $grade = 'अशुभ'; $tone = 'neg';
                $verdict = 'नक्षत्र इस संस्कार हेतु अनुपयुक्त — दूसरा दिन चुनें।';
            } elseif ($okCount === 3) {
                $grade = 'शुभ'; $tone = 'pos';
                $verdict = 'नक्षत्र, तिथि व वार तीनों अनुकूल — इस संस्कार हेतु शुभ मुहूर्त।';
            } else {
                $grade = 'मध्यम'; $tone = 'info';
                $verdict = 'नक्षत्र अनुकूल पर तिथि/वार में एक बाधा — सुधार कर या उपाय-सहित।';
            }

            $checks = [
                ['label' => 'नक्षत्र', 'value' => self::D::NAK_HI[$moonNak] . ' (' . $moonSanjna . ')', 'ok' => $nakOk],
                ['label' => 'तिथि', 'value' => 'तिथि ' . $tithiInPaksha, 'ok' => $tithiOk],
                ['label' => 'वार', 'value' => self::D::WEEKDAY_HI[$wd], 'ok' => $vaarOk],
            ];

            $rites[] = [
                'key' => $key, 'emoji' => $s['emoji'], 'label' => $s['label'], 'rule' => $s['rule'],
                'grade' => $grade, 'tone' => $tone, 'verdict' => $verdict,
                'checks' => $checks, 'prescription' => $s['prescription'], 'note' => $s['note'],
            ];
        }

        return [
            'ok' => true,
            'moon_nak' => self::D::NAK_HI[$moonNak], 'sanjna' => $moonSanjna,
            'tithi' => $tithiInPaksha, 'vaar' => self::D::WEEKDAY_HI[$wd],
            'rites' => $rites,
            'note' => 'संस्कार हेतु ऊपर 🗓️ सामान्य व 🙋 व्यक्तिगत मुहूर्त (बालक की जन्म-राशि से तारा/चन्द्र-बल) भी अवश्य देखें — तीनों अनुकूल हों तभी श्रेष्ठ।',
        ];
    }
}
