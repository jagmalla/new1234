<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

/**
 * 🗓️ सामान्य मुहूर्त (General Muhurat) — Phase 1 of the Muhurta-Chintamani engine.
 *
 * Panchang-shuddhi of a chosen instant (no birth chart needed): tithi · vaar ·
 * nakshatra (+ sanjna/gana) · nitya-yoga · karana, then the granth's शुभाशुभ
 * checks — सिद्धयोग, सर्वार्थसिद्धि, रवियोग (दोष-नाशक), दग्ध-नक्षत्र, दग्ध/अधम-तिथि,
 * भद्रा (विष्टि), अशुभ नित्य-योग, आनन्दादि योग — collected as शुभ-योग vs दोष, with a
 * parihara-aware overall grade and remedies. Pure & reusable.
 *
 * Input is the same transit snapshot the Gochar/Muhurat engines already build.
 */
final class GeneralMuhuratEngine
{
    private const NAK_ARC = 40.0 / 3.0;    // 13°20'
    private const PADA_ARC = 10.0 / 3.0;   // 3°20'
    private const D = MuhuratChintamaniData::class;

    /**
     * @param float $sunLon  transit Sun sidereal longitude
     * @param float $moonLon transit Moon sidereal longitude
     * @param int   $weekday 0 रवि .. 6 शनि (civil day of the muhurat)
     * @param int   $moonSign transit Moon rashi 0..11 (for भद्रा निवास)
     * @return array<string,mixed>
     */
    public static function compute(float $sunLon, float $moonLon, int $weekday, int $moonSign): array
    {
        $wd = (($weekday % 7) + 7) % 7;
        $wdHi = self::D::WEEKDAY_HI[$wd];

        // ---- panchang core ----
        $elong = fmod($moonLon - $sunLon + 360.0, 360.0);
        $tithiNo = max(1, min(30, (int) floor($elong / 12.0) + 1));
        $paksha = $tithiNo <= 15 ? 'शुक्ल' : 'कृष्ण';
        $pos = ($tithiNo - 1) % 15;                       // 0..14
        $tithiInPaksha = $pos + 1;                        // 1..15
        $nandaAdi = self::D::NANDA_ADI[$pos % 5];
        $tGrade = self::D::TITHI_PHALA[$tithiInPaksha][$paksha === 'शुक्ल' ? 0 : 1];

        $mNak = self::nakIdx($moonLon);
        $mPada = self::pada($moonLon);
        $sNak = self::nakIdx($sunLon);
        $sanjna = self::D::SANJNA[$mNak];
        $gana = self::D::GANA[$mNak];

        $yogaIdx = (int) floor(fmod($sunLon + $moonLon, 360.0) / self::NAK_ARC) % 27;
        $yogaName = self::D::NITYA_YOGA[$yogaIdx];
        $yogaBad = in_array($yogaIdx, self::D::NITYA_ASHUBHA, true);

        [$karanaName, $isBhadra] = self::karana($elong);

        // ---- shubh yogas (some are dosha-nashak / parihara) ----
        $shubh = []; $nashak = false;
        if ((self::D::SIDDHA[$nandaAdi] ?? -1) === $wd) {
            $shubh[] = ['name' => '✨ सिद्धयोग', 'why' => $nandaAdi . ' तिथि + ' . $wdHi . ' — कार्य-सिद्धि का शुभ योग'];
        }
        if (in_array($mNak, self::D::SARVARTHA[$wd] ?? [], true)) {
            $shubh[] = ['name' => '🌟 सर्वार्थसिद्धि योग', 'why' => $wdHi . ' + ' . self::D::NAK_HI[$mNak] . ' — सब कार्यों में सिद्धि; अनेक दोषों का नाशक'];
            $nashak = true;
        }
        // रवियोग (SA-013): सूर्य-नक्षत्र से चन्द्र-नक्षत्र तक गणना ∈ {4,6,9,10,13,20}
        $count = (($mNak - $sNak + 27) % 27) + 1;
        if (in_array($count, [4, 6, 9, 10, 13, 20], true)) {
            $shubh[] = ['name' => '☀️ रवियोग', 'why' => 'सूर्य-नक्षत्र से चन्द्र-नक्षत्र गणना = ' . $count . ' — यह दोष-समूह का नाश करता है'];
            $nashak = true;
        }
        // आनन्दादि योग (27-गणना; indicative)
        $anandaNo = (($mNak - self::D::ANANDA_START[$wd] + 27) % 27) + 1;
        $ananda = self::D::ANANDA[$anandaNo] ?? null;

        // ---- doshas ----
        $dosha = [];
        if ((self::D::DAGDHA_NAK[$wd] ?? -1) === $mNak) {
            $dosha[] = ['name' => '🔥 दग्ध नक्षत्र', 'why' => $wdHi . ' + ' . self::D::NAK_HI[$mNak] . ' — दग्ध योग', 'sev' => 2, 'rem' => self::D::REMEDY['dagdha_nak']];
        }
        if ((self::D::DAGDHA_TITHI[$wd] ?? -1) === $tithiInPaksha) {
            $dosha[] = ['name' => '🔥 दग्ध/अधम तिथि', 'why' => $wdHi . ' + तिथि ' . $tithiInPaksha . ' — मंगल कार्य में त्याज्य', 'sev' => 2, 'rem' => self::D::REMEDY['dagdha_tithi']];
        }
        if ($isBhadra) {
            $nivasa = self::bhadraNivasa($moonSign);
            $dosha[] = ['name' => '🐢 भद्रा (विष्टि करण)', 'why' => 'भद्रा ' . $nivasa['loka'] . ' में' . ($nivasa['bad'] ? ' — यहीं फल देती, त्याज्य' : ' — मंगल कार्य में सावधानी'), 'sev' => $nivasa['bad'] ? 2 : 1, 'rem' => self::D::REMEDY['bhadra']];
        }
        if ($yogaBad) {
            $dosha[] = ['name' => '⚠️ अशुभ नित्य-योग', 'why' => $yogaName . ' योग — आरम्भ-भाग त्याज्य', 'sev' => 1, 'rem' => self::D::REMEDY['nitya']];
        }
        if ($ananda && $ananda[2]) {
            $dosha[] = ['name' => '⚠️ दुष्ट आनन्दादि योग', 'why' => $ananda[0] . ' (' . $ananda[1] . ') — आरम्भ-घटी त्याज्य', 'sev' => 1, 'rem' => self::D::REMEDY['ananda']];
        }
        if (in_array($tGrade, ['अशुभ', 'अघम'], true)) {
            $dosha[] = ['name' => '📉 अशुभ तिथि', 'why' => $paksha . ' पक्ष तिथि ' . $tithiInPaksha . ' — ' . $tGrade, 'sev' => $tGrade === 'अघम' ? 2 : 1, 'rem' => self::D::REMEDY['tithi']];
        }

        // ---- overall grade (parihara-aware) ----
        $sev = 0; foreach ($dosha as $d) { $sev += (int) $d['sev']; }
        $strong = 0; foreach ($dosha as $d) { if ((int) $d['sev'] >= 2) { $strong++; } }
        if ($nashak && $strong <= 1) {
            $grade = 'शुभ'; $tone = 'pos';
            $verdict = 'दोष-नाशक योग (सर्वार्थसिद्धि/रवियोग) उपस्थित — अधिकांश दोष निरस्त। कार्य हेतु शुभ।';
        } elseif ($sev === 0 && count($shubh) > 0) {
            $grade = 'शुभ'; $tone = 'pos'; $verdict = 'कोई दोष नहीं तथा शुभ योग उपस्थित — मुहूर्त शुभ।';
        } elseif ($sev === 0) {
            $grade = 'मध्यम'; $tone = 'info'; $verdict = 'कोई प्रबल दोष नहीं — सामान्य कार्य हेतु ग्राह्य।';
        } elseif ($strong >= 1 && !$nashak) {
            $grade = 'अशुभ'; $tone = 'neg'; $verdict = 'प्रबल दोष उपस्थित तथा कोई दोष-नाशक योग नहीं — मंगल कार्य में सावधानी/त्याग।';
        } else {
            $grade = 'मध्यम'; $tone = 'info'; $verdict = 'मिश्र — कुछ दोष हैं पर सामान्य/उपाय-सहित कार्य सम्भव।';
        }

        return [
            'ok' => true,
            'grade' => $grade, 'tone' => $tone, 'verdict' => $verdict,
            'panchang' => [
                'vaar' => $wdHi,
                'tithi' => $paksha . ' पक्ष · ' . self::tithiName($tithiInPaksha) . ' (' . $tithiInPaksha . ') · ' . $nandaAdi,
                'tithi_grade' => $tGrade,
                'nakshatra' => self::D::NAK_HI[$mNak] . ' पाद ' . $mPada,
                'sanjna' => $sanjna, 'sanjna_karya' => self::D::SANJNA_KARYA[$sanjna] ?? '',
                'gana' => $gana,
                'yoga' => $yogaName . ($yogaBad ? ' (अशुभ)' : ''),
                'karana' => $karanaName . ($isBhadra ? ' — भद्रा!' : ''),
                'sun_nak' => self::D::NAK_HI[$sNak],
                'ananda' => $ananda ? $ananda[0] . ' — ' . $ananda[1] : '',
            ],
            'shubh' => $shubh,
            'dosha' => $dosha,
            'nashak' => $nashak,
            'chitta' => self::D::CHITTA_NOTE,
        ];
    }

    private static function nakIdx(float $lon): int
    {
        return (int) floor(fmod($lon + 360.0, 360.0) / self::NAK_ARC) % 27;
    }
    private static function pada(float $lon): int
    {
        return (int) floor(fmod(fmod($lon + 360.0, 360.0), self::NAK_ARC) / self::PADA_ARC) + 1;
    }

    /** @return array{0:string,1:bool} [karana name, isBhadra(विष्टि)] */
    private static function karana(float $elong): array
    {
        // 60 half-tithis across the lunar month; karana 0 = किंस्तुघ्न (fixed),
        // 1..57 cycle बव..विष्टि (7 movable), 58..60 fixed शकुनि/चतुष्पाद/नाग.
        $k = (int) floor($elong / 6.0);   // 0..59
        if ($k === 0) { return ['किंस्तुघ्न', false]; }
        if ($k >= 57) { return [self::D::KARANA_FIXED[$k - 57] ?? 'शकुनि', false]; }
        $idx = ($k - 1) % 7;
        return [self::D::KARANA[$idx], $idx === 6];   // 6 = विष्टि = भद्रा
    }

    private static function bhadraNivasa(int $moonSign): array
    {
        // SA-014: कुम्भ/मीन/कर्क/सिंह → मर्त्य (bad); मेष/वृष/मिथुन/वृश्चिक → पाताल; अन्य → स्वर्ग
        if (in_array($moonSign, [10, 11, 3, 4], true)) { return ['loka' => 'मर्त्यलोक', 'bad' => true]; }
        if (in_array($moonSign, [0, 1, 2, 7], true)) { return ['loka' => 'पाताल', 'bad' => false]; }
        return ['loka' => 'स्वर्ग', 'bad' => false];
    }

    private static function tithiName(int $t): string
    {
        static $n = [1 => 'प्रतिपदा', 2 => 'द्वितीया', 3 => 'तृतीया', 4 => 'चतुर्थी', 5 => 'पञ्चमी', 6 => 'षष्ठी',
            7 => 'सप्तमी', 8 => 'अष्टमी', 9 => 'नवमी', 10 => 'दशमी', 11 => 'एकादशी', 12 => 'द्वादशी',
            13 => 'त्रयोदशी', 14 => 'चतुर्दशी', 15 => 'पूर्णिमा/अमावस्या'];
        return $n[$t] ?? (string) $t;
    }
}
