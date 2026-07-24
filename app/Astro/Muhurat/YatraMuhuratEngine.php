<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

/**
 * 🧳 यात्रा मुहूर्त (Travel Muhurat) — Phase 5 of the Muhurta-Chintamani engine.
 *
 * Judges the chosen instant for travel in each of the 8 directions:
 *   • वार-शूल + नक्षत्र-शूल (YA-010) — direction blocked by weekday / nakshatra
 *   • योगिनी-वास (YA-028) — yogini सम्मुख in the chosen direction is अशुभ
 *   • सर्वदिग् नक्षत्र (YA-033) — cancels शूल/परिघ
 *   • यात्रा-विहित/वर्ज्य नक्षत्र व तिथि (YA-009) + भद्रा
 * Computes all directions at once so the UI switches direction without a
 * re-fetch. दिशा-स्वामी & विहित वाहन shown per direction. Pure & reusable.
 */
final class YatraMuhuratEngine
{
    private const NAK_ARC = 40.0 / 3.0;
    private const D = MuhuratChintamaniData::class;

    public static function computeAll(float $sunLon, float $moonLon, int $weekday): array
    {
        $wd = (($weekday % 7) + 7) % 7;
        $moonNak = (int) floor(fmod($moonLon + 360.0, 360.0) / self::NAK_ARC) % 27;
        $elong = fmod($moonLon - $sunLon + 360.0, 360.0);
        $tithiNo = max(1, min(30, (int) floor($elong / 12.0) + 1));
        $tithiInPaksha = (($tithiNo - 1) % 15) + 1;
        $paksha = $tithiNo <= 15 ? 'शुक्ल' : 'कृष्ण';
        // भद्रा
        $kIdx = (int) floor($elong / 6.0);
        $isBhadra = $kIdx >= 1 && $kIdx < 57 && (($kIdx - 1) % 7) === 6;

        // yogini direction (तिथि 15: शुक्ल→वायव्य, कृष्ण/अमावस्या→ईशान)
        $yoginiDir = self::D::YOGINI[$tithiInPaksha] ?? ($paksha === 'शुक्ल' ? 'वायव्य' : 'ईशान');

        $sarvadig = in_array($moonNak, self::D::YATRA_SARVADIG_NAK, true);
        $vihitNak = in_array($moonNak, self::D::YATRA_VIHIT_NAK, true);
        $tithiOk = !in_array($tithiInPaksha, self::D::YATRA_BAD_TITHI, true);
        $vaarShoolDir = self::D::VAAR_SHOOL[$wd] ?? '';
        $nakShoolDir = self::D::NAK_SHOOL[$moonNak] ?? '';

        // common (direction-independent) issues
        $common = [];
        if (!$tithiOk) { $common[] = ['name' => '📉 तिथि वर्ज्य', 'why' => 'यात्रा-वर्ज्य तिथि', 'sev' => 1]; }
        if ($isBhadra) { $common[] = ['name' => '🐢 भद्रा', 'why' => 'विष्टि करण — यात्रा-आरम्भ वर्ज्य', 'sev' => 1]; }

        $dirs = [];
        foreach (self::D::DISHA_LIST as $dir) {
            $issues = $common;
            $vaarShool = $vaarShoolDir === $dir;
            $nakShool = $nakShoolDir === $dir;
            $yogini = $yoginiDir === $dir;
            $shool = ($vaarShool || $nakShool) && !$sarvadig;
            if ($vaarShool) { $issues[] = ['name' => '🧭 वार-शूल', 'why' => self::D::WEEKDAY_HI[$wd] . ' को ' . $dir . ' दिशा-शूल' . ($sarvadig ? ' (सर्वदिग् नक्षत्र से निरस्त)' : ''), 'sev' => $sarvadig ? 0 : 2]; }
            if ($nakShool) { $issues[] = ['name' => '⭐ नक्षत्र-शूल', 'why' => self::D::NAK_HI[$moonNak] . ' में ' . $dir . ' शूल' . ($sarvadig ? ' (निरस्त)' : ''), 'sev' => $sarvadig ? 0 : 2]; }
            if ($yogini) { $issues[] = ['name' => '🌀 योगिनी सम्मुख', 'why' => 'तिथि ' . $tithiInPaksha . ' — योगिनी ' . $dir . ' में; सम्मुख यात्रा अशुभ (योगिनी को पीठ/दाहिने रखें)', 'sev' => 1]; }

            $sev = 0; $strong = 0;
            foreach ($issues as $x) { $sev += (int) $x['sev']; if ((int) $x['sev'] >= 2) { $strong++; } }
            if ($strong >= 1) { $grade = 'अशुभ'; $tone = 'neg'; }
            elseif ($sev === 0 && ($vihitNak || $sarvadig)) { $grade = 'शुभ'; $tone = 'pos'; }
            elseif ($sev === 0) { $grade = 'मध्यम'; $tone = 'info'; }
            else { $grade = 'मध्यम'; $tone = 'info'; }

            $dirs[] = [
                'dir' => $dir, 'grade' => $grade, 'tone' => $tone,
                'swami' => self::D::DISHA_SWAMI[$dir] ?? '',
                'vahana' => self::D::YATRA_VAHANA[$dir] ?? '',
                'shool' => $shool, 'yogini' => $yogini,
                'issues' => array_values(array_filter($issues, static fn ($x) => (int) $x['sev'] > 0)),
                'ok_note' => $issues === $common || array_filter($issues, static fn ($x) => (int) $x['sev'] > 0) === [] ? 'इस दिशा हेतु कोई शूल/योगिनी बाधा नहीं।' : '',
            ];
        }

        return [
            'ok' => true,
            'nakshatra' => self::D::NAK_HI[$moonNak], 'vihit_nak' => $vihitNak, 'sarvadig' => $sarvadig,
            'tithi' => $tithiInPaksha, 'paksha' => $paksha, 'vaar' => self::D::WEEKDAY_HI[$wd],
            'yogini_dir' => $yoginiDir, 'vaar_shool_dir' => $vaarShoolDir, 'nak_shool_dir' => $nakShoolDir,
            'dirs' => $dirs,
            'note' => 'यदि एक ही दिन में गन्तव्य पहुँचना हो (YA-075) तो वार-शूल/नक्षत्र-शूल/योगिनी विचार नहीं। यात्रा हेतु जन्म-राशि से घात-चक्र (🙋 व्यक्तिगत) भी देखें। मन प्रसन्न न हो तो शुभ मुहूर्त में भी न जाएँ (चित्तशुद्धि)।',
        ];
    }
}
