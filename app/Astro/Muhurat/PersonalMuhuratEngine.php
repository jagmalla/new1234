<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

/**
 * 🙋 व्यक्तिगत मुहूर्त (Personal Muhurat) — Phase 2 of the Muhurta-Chintamani engine.
 *
 * Judges the chosen instant AGAINST the person's D1 chart (janma नक्षत्र / राशि):
 *   • तारा-बल (नवतारा) — janma-nak → day-nak (विपत्/प्रत्यरि/वध flagged)
 *   • चन्द्र-बल — transit Moon house from janma-rashi
 *   • घात-चक्र — चन्द्र/तिथि/वार/नक्षत्र (+लग्न if known) from janma-rashi
 *   • अष्टकवर्ग गोचर-बिन्दु — transiting Sun/Moon/Jupiter/Saturn BAV bindu (≥4 शुभ)
 *   • गोचर-वेध — Jupiter/Saturn shubh house from janma-rashi
 * Returns a personal शुभ/मध्यम/अशुभ grade with reasons + remedies. Pure.
 */
final class PersonalMuhuratEngine
{
    private const NAK_ARC = 40.0 / 3.0;
    private const D = MuhuratChintamaniData::class;

    /** नन्दादि-संज्ञा of a tithi-in-paksha (1..15). */
    private static function nandaAdi(int $tithiInPaksha): string
    {
        return self::D::NANDA_ADI[(($tithiInPaksha - 1) % 5)];
    }

    /**
     * @param array<string,mixed> $natal    CalculationEngine::computeChart output
     * @param array<string,array<string,mixed>> $transits transit snapshot
     * @param int  $weekday 0 रवि .. 6 शनि
     * @param int|null $lagnaSign muhurat ascendant sign 0..11 (enables घात-लग्न)
     * @return array<string,mixed>
     */
    public static function compute(array $natal, array $transits, int $weekday, ?int $lagnaSign = null): array
    {
        $natalMoonLon = (float) ($natal['planets']['Moon']['sidereal_lon'] ?? 0.0);
        $janmaRashi = (int) ($natal['planets']['Moon']['sign_index'] ?? (int) floor($natalMoonLon / 30.0) % 12);
        $janmaNak = self::nakIdx($natalMoonLon);

        $sunLon = (float) ($transits['Sun']['sidereal_lon'] ?? 0.0);
        $moonLon = (float) ($transits['Moon']['sidereal_lon'] ?? 0.0);
        $moonNak = self::nakIdx($moonLon);
        $moonRashi = (int) ($transits['Moon']['sign_index'] ?? (int) floor(fmod($moonLon + 360.0, 360.0) / 30.0) % 12);
        $wd = (($weekday % 7) + 7) % 7;

        $elong = fmod($moonLon - $sunLon + 360.0, 360.0);
        $tithiNo = max(1, min(30, (int) floor($elong / 12.0) + 1));
        $tithiInPaksha = (($tithiNo - 1) % 15) + 1;
        $nandaAdi = self::nandaAdi($tithiInPaksha);

        $good = []; $bad = [];

        // ---- तारा-बल (नवतारा) ----
        $steps = (($moonNak - $janmaNak) % 27 + 27) % 27;
        $taraNum = ($steps % 9) + 1;
        $tara = self::D::NAVATARA[$taraNum];
        $taraLine = ['label' => 'तारा-बल', 'value' => $taraNum . '. ' . $tara[0] . ' — ' . $tara[1], 'ok' => $tara[2]];
        if (!$tara[2]) {
            $bad[] = ['name' => '⭐ दुष्ट तारा (' . $tara[0] . ')', 'why' => 'जन्म-नक्षत्र से आज का चन्द्र-नक्षत्र = ' . $tara[0] . ' तारा', 'sev' => $taraNum === 7 ? 2 : 1, 'rem' => self::D::PREMEDY['tara']];
        } else {
            $good[] = ['name' => '⭐ शुभ तारा (' . $tara[0] . ')', 'why' => 'जन्म-नक्षत्र से आज का चन्द्र-नक्षत्र शुभ तारा में'];
        }

        // ---- चन्द्र-बल ----
        $chHouse = (($moonRashi - $janmaRashi + 12) % 12) + 1;
        $chGood = in_array($chHouse, [1, 3, 6, 7, 10, 11], true);
        $chBad = in_array($chHouse, [4, 8, 12], true);
        $chLine = ['label' => 'चन्द्र-बल', 'value' => 'चन्द्र जन्म-राशि से ' . self::ord($chHouse) . ' भाव — ' . ($chGood ? 'शुभ' : ($chBad ? 'दुर्बल' : 'मध्यम')), 'ok' => $chGood];
        if ($chBad) {
            $bad[] = ['name' => '🌙 चन्द्र-बल दुर्बल', 'why' => 'चन्द्र जन्म-राशि से ' . self::ord($chHouse) . ' भाव में', 'sev' => 1, 'rem' => self::D::PREMEDY['chandra']];
        } elseif ($chGood) {
            $good[] = ['name' => '🌙 शुभ चन्द्र-बल', 'why' => 'चन्द्र जन्म-राशि से ' . self::ord($chHouse) . ' भाव में'];
        }

        // ---- घात-चक्र (from janma-rashi) ----
        $ghata = [];
        if (self::D::GHATA_CHANDRA[$janmaRashi] === $moonRashi) { $ghata[] = 'घात-चन्द्र (' . self::D::RASHI_HI[$moonRashi] . ')'; }
        if (self::D::GHATA_TITHI[$janmaRashi] === $nandaAdi) { $ghata[] = 'घात-तिथि (' . $nandaAdi . ')'; }
        if (self::D::GHATA_VAARA[$janmaRashi] === $wd) { $ghata[] = 'घात-वार (' . self::D::WEEKDAY_HI[$wd] . ')'; }
        if (self::D::GHATA_NAK[$janmaRashi] === $moonNak) { $ghata[] = 'घात-नक्षत्र (' . self::D::NAK_HI[$moonNak] . ')'; }
        if ($lagnaSign !== null && self::D::GHATA_LAGNA[$janmaRashi] === $lagnaSign) { $ghata[] = 'घात-लग्न (' . self::D::RASHI_HI[$lagnaSign] . ')'; }
        foreach ($ghata as $g) {
            $bad[] = ['name' => '⚡ ' . $g, 'why' => 'जन्म-राशि ' . self::D::RASHI_HI[$janmaRashi] . ' हेतु घातक', 'sev' => 2, 'rem' => self::D::PREMEDY['ghata']];
        }

        // ---- अष्टकवर्ग गोचर-बिन्दु ----
        $bav = $natal['ashtakavarga']['bav'] ?? [];
        $avRows = [];
        foreach (['Sun' => 'सूर्य', 'Moon' => 'चन्द्र', 'Jupiter' => 'गुरु', 'Saturn' => 'शनि'] as $p => $pHi) {
            if (!isset($bav[$p], $transits[$p]['sign_index'])) { continue; }
            $sign = (int) $transits[$p]['sign_index'];
            $b = (int) ($bav[$p][$sign] ?? 0);
            $ok = $b >= 4;
            $avRows[] = ['planet' => $pHi, 'sign' => self::D::RASHI_HI[$sign], 'bindu' => $b, 'ok' => $ok];
            if (!$ok && in_array($p, ['Jupiter', 'Saturn'], true)) {
                $bad[] = ['name' => '📊 ' . $pHi . ' अष्टकवर्ग कम', 'why' => 'गोचर ' . $pHi . ' ' . self::D::RASHI_HI[$sign] . ' में — बिन्दु ' . $b . ' (शुभ हेतु 4+ चाहिए)', 'sev' => 1, 'rem' => self::D::PREMEDY['av']];
            } elseif ($ok && in_array($p, ['Jupiter', 'Saturn'], true)) {
                $good[] = ['name' => '📊 ' . $pHi . ' अष्टकवर्ग बली', 'why' => 'गोचर ' . $pHi . ' ' . self::D::RASHI_HI[$sign] . ' में — बिन्दु ' . $b];
            }
        }

        // ---- गोचर-वेध (Jupiter/Saturn from janma-rashi) ----
        $slow = [];
        $jH = self::houseFrom($transits, 'Jupiter', $janmaRashi);
        if ($jH !== null) {
            $jok = in_array($jH, [2, 5, 7, 9, 11], true);
            $slow[] = 'गुरु जन्म-राशि से ' . self::ord($jH) . ' भाव — ' . ($jok ? 'शुभ' : 'सामान्य');
            if ($jok) { $good[] = ['name' => '🟡 गुरु गोचर शुभ', 'why' => 'गुरु जन्म-राशि से ' . self::ord($jH) . ' भाव में']; }
        }
        $sH = self::houseFrom($transits, 'Saturn', $janmaRashi);
        if ($sH !== null) {
            $sok = in_array($sH, [3, 6, 11], true);
            $sSade = in_array($sH, [12, 1, 2], true);
            $slow[] = 'शनि जन्म-राशि से ' . self::ord($sH) . ' भाव — ' . ($sok ? 'शुभ' : ($sSade ? 'साढ़ेसाती' : 'सामान्य'));
            if ($sSade) { $bad[] = ['name' => '🪐 शनि साढ़ेसाती', 'why' => 'शनि जन्म-राशि से ' . self::ord($sH) . ' भाव में', 'sev' => 1, 'rem' => 'साढ़ेसाती में बड़े मंगल कार्य सोच-समझकर; शनि-उपाय सहायक।']; }
        }

        // ---- overall personal grade ----
        $sev = 0; $strong = 0;
        foreach ($bad as $b) { $sev += (int) $b['sev']; if ((int) $b['sev'] >= 2) { $strong++; } }
        if ($strong >= 1) {
            $grade = 'अशुभ'; $tone = 'neg'; $verdict = 'आपकी कुण्डली से प्रबल व्यक्तिगत दोष (घात/वध-तारा) — यह दिन आपके लिए त्याज्य।';
        } elseif ($sev === 0) {
            $grade = 'शुभ'; $tone = 'pos'; $verdict = 'आपकी कुण्डली से तारा-बल, चन्द्र-बल व घात-शुद्धि अनुकूल — व्यक्तिगत रूप से शुभ दिन।';
        } elseif ($sev <= 2) {
            $grade = 'मध्यम'; $tone = 'info'; $verdict = 'व्यक्तिगत रूप से मिश्र — कुछ दुर्बलता है पर सामान्य/उपाय-सहित कार्य सम्भव।';
        } else {
            $grade = 'अशुभ'; $tone = 'neg'; $verdict = 'अनेक व्यक्तिगत दोष — शुभ तारा/चन्द्र-बल वाला दूसरा दिन चुनना श्रेष्ठ।';
        }

        return [
            'ok' => true,
            'grade' => $grade, 'tone' => $tone, 'verdict' => $verdict,
            'janma_rashi' => self::D::RASHI_HI[$janmaRashi], 'janma_nak' => self::D::NAK_HI[$janmaNak],
            'core' => [$taraLine, $chLine],
            'ghata' => $ghata,
            'av' => $avRows,
            'slow' => $slow,
            'good' => $good, 'bad' => $bad,
            'chitta' => self::D::CHITTA_NOTE,
        ];
    }

    private static function nakIdx(float $lon): int
    {
        return (int) floor(fmod($lon + 360.0, 360.0) / self::NAK_ARC) % 27;
    }
    private static function houseFrom(array $transits, string $p, int $fromSign): ?int
    {
        if (!isset($transits[$p]['sign_index'])) { return null; }
        return ((((int) $transits[$p]['sign_index'] - $fromSign) % 12) + 12) % 12 + 1;
    }
    private static function ord(int $h): string
    {
        static $o = [1 => 'प्रथम', 2 => 'द्वितीय', 3 => 'तृतीय', 4 => 'चतुर्थ', 5 => 'पंचम', 6 => 'षष्ठ',
            7 => 'सप्तम', 8 => 'अष्टम', 9 => 'नवम', 10 => 'दशम', 11 => 'एकादश', 12 => 'द्वादश'];
        return $o[$h] ?? (string) $h;
    }
}
