<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Gochar;

use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * Muhurat engine — Gochar Vichar Ch.8.
 *
 * Pure computation over the natal chart + a transit snapshot for the muhurat
 * moment. Produces the "मुहूर्त" category of the Gochar panel:
 *   राहु काल · दिशा शूल · तिथि (नन्दा/भद्रा/जया/रिक्ता/पूर्णा + पक्ष-ग्रेड) ·
 *   जन्म-नक्षत्र वारफल · अस्त-ग्रह चेतावनी · शनि-अष्टकवर्ग कष्ट-राशि.
 *
 * Entirely ADDITIVE — it introduces no changes to any existing Gochar/AV/
 * Sade-Sati result; it only reads the same natal + transit data.
 */
final class MuhuratEngine
{
    private const NAK_ARC = 40.0 / 3.0;   // 13°20' = 13.3333…°
    private const PADA_ARC = 10.0 / 3.0;  // 3°20'  =  3.3333…°

    /**
     * @param array<string,mixed> $natal
     * @param array<string,array<string,mixed>> $transits
     * @param array<string,mixed> $rules   MuhuratRepository::load output
     * @param int $weekday  0 = Sunday … 6 = Saturday (civil day of the muhurat)
     * @return array<string,mixed>
     */
    public static function compute(array $natal, array $transits, array $rules, int $weekday): array
    {
        $cfg = $rules['config'] ?? [];
        $wd = (($weekday % 7) + 7) % 7;
        $wdHi = MuhuratData::WEEKDAY_HI[$wd] ?? '';

        $sunLon = (float) ($transits['Sun']['sidereal_lon'] ?? 0.0);
        $moonLon = (float) ($transits['Moon']['sidereal_lon'] ?? 0.0);
        $natalMoonLon = (float) ($natal['planets']['Moon']['sidereal_lon'] ?? 0.0);

        $out = ['weekday' => $wd, 'weekday_hi' => $wdHi];

        // ---------------- भाग 2 — राहु काल ----------------
        if (($cfg['muhurat_rahu_kaal'] ?? '1') === '1') {
            $sunrise = (float) ($cfg['muhurat_sunrise'] ?? 6.0);
            $sunset = (float) ($cfg['muhurat_sunset'] ?? 18.0);
            $span = max(0.1, $sunset - $sunrise);
            $part = MuhuratData::RAHU_KAAL_PART[$wd] ?? 8;
            $start = $sunrise + ($part - 1) * ($span / 8.0);
            $end = $start + ($span / 8.0);
            $out['rahu_kaal'] = [
                'part' => $part,
                'start' => self::hm($start), 'end' => self::hm($end),
                'note' => 'राहु काल = दिनमान (सूर्योदय→सूर्यास्त) का ' . self::ordHi($part) . ' अष्टम भाग। इस काल में कोई महत्त्वपूर्ण/मांगलिक कार्य आरम्भ न करें (दक्षिण भारत में विशेष अशुभ)।',
                'approx' => abs($sunrise - 6.0) < 0.01 && abs($sunset - 18.0) < 0.01,
            ];
        }

        // ---------------- भाग 3 — दिशा शूल ----------------
        if (($cfg['muhurat_disha_shul'] ?? '1') === '1') {
            $d = $rules['disha'][$wd] ?? MuhuratData::DISHA_SHUL[$wd];
            $out['disha_shul'] = [
                'dir' => $d['dir'], 'lord' => $d['lord'],
                'note' => $wdHi . ' को ' . $d['dir'] . ' दिशा (स्वामी ' . $d['lord'] . ') में यात्रा वर्जित — दिशा शूल। इस दिशा की यात्रा कष्टकारी मानी जाती है।',
                'principle' => MuhuratData::DISHA_PRINCIPLE,
            ];
        }

        // ---------------- भाग 5 — तिथि ----------------
        if (($cfg['muhurat_tithi'] ?? '1') === '1') {
            $diff = fmod($moonLon - $sunLon + 360.0, 360.0);
            $tithiNo = (int) floor($diff / 12.0) + 1;          // 1..30
            $tithiNo = max(1, min(30, $tithiNo));
            $paksha = $tithiNo <= 15 ? 'shukla' : 'krishna';
            $posInPaksha = ($tithiNo - 1) % 15;                // 0..14
            $nameIdx = $posInPaksha % 5 + 1;                    // 1..5 (नन्दा..पूर्णा)
            $subgroup = intdiv($posInPaksha, 5);                // 0,1,2
            $t = $rules['tithi'][$nameIdx] ?? MuhuratData::TITHI[$nameIdx];
            $grade = MuhuratData::PAKSHA_GRADE[$subgroup];
            $g = $paksha === 'shukla' ? $grade['shukla'] : $grade['krishna'];
            $out['tithi'] = [
                'num' => $tithiNo,
                'paksha_hi' => $paksha === 'shukla' ? 'शुक्ल पक्ष' : 'कृष्ण पक्ष',
                'name' => $t['name'], 'lord' => $t['lord'], 'meaning' => $t['meaning'],
                'group_label' => $grade['label'], 'grade' => $g,
                'tone' => $g === 'शुभ' ? 'pos' : ($g === 'अशुभ' ? 'neg' : 'info'),
                'note' => MuhuratData::PAKSHA_NOTE,
            ];
        }

        // ---------------- नक्षत्र (गोचर चन्द्र) + जन्म-नक्षत्र ----------------
        [$tNakIdx, $tPada] = self::nakshatra($moonLon);
        [$jNakIdx] = self::nakshatra($natalMoonLon);
        $out['nakshatra'] = [
            'transit' => ['idx' => $tNakIdx, 'name' => MuhuratData::NAKSHATRA_HI[$tNakIdx], 'pada' => $tPada],
            'janma' => ['idx' => $jNakIdx, 'name' => MuhuratData::NAKSHATRA_HI[$jNakIdx]],
        ];

        // ---------------- भाग 7 — जन्म-नक्षत्र का वारानुसार मासिक फल ----------------
        if (($cfg['muhurat_janma_nak'] ?? '1') === '1') {
            $nv = $rules['nak_vaar'][$wd] ?? MuhuratData::NAK_VAAR_PHAL[$wd];
            $active = $tNakIdx === $jNakIdx;   // गोचर चन्द्र इस समय जन्म-नक्षत्र में?
            $out['janma_nak_phal'] = [
                'janma_nak' => MuhuratData::NAKSHATRA_HI[$jNakIdx],
                'weekday_hi' => $wdHi,
                'phal' => $nv['phal'], 'cond' => $nv['cond'],
                'bonus' => $nv['bonus'], 'bonus_note' => $nv['bonus_note'],
                'active' => $active,
            ];
        }

        // ---------------- भाग 4 — अस्त-ग्रह चेतावनी (गुरु/शुक्र) ----------------
        if (($cfg['muhurat_combust'] ?? '1') === '1') {
            $warns = [];
            foreach (['Jupiter', 'Venus'] as $p) {
                if (!isset($rules['combust'][$p])) { continue; }
                $c = PlanetCondition::combustion($p, $transits);
                if ($c !== null) {
                    $warns[] = [
                        'planet' => $p, 'planet_hi' => self::hi($p),
                        'sep' => (float) ($c['sep'] ?? 0.0),
                        'warn' => (string) $rules['combust'][$p],
                    ];
                }
            }
            $out['combust'] = ['warns' => $warns, 'note' => MuhuratData::COMBUST_NOTE];
        }

        // ---------------- भाग 1 — शनि-अष्टकवर्ग कष्ट-राशि ----------------
        if (($cfg['muhurat_kashta'] ?? '1') === '1') {
            $satBav = $natal['ashtakavarga']['bav']['Saturn'] ?? [];
            if ($satBav !== []) {
                $min = min($satBav);
                $signs = [];
                foreach ($satBav as $sign => $b) { if ((int) $b === (int) $min) { $signs[] = (int) $sign; } }
                $sunSign = (int) ($transits['Sun']['sign_index'] ?? -1);
                $sunHere = in_array($sunSign, $signs, true);
                $out['kashta_rashi'] = [
                    'bindu' => (int) $min,
                    'signs' => array_map(static fn(int $s) => self::rashiHi($s), $signs),
                    'sun_here' => $sunHere,
                    'note' => MuhuratData::KASHTA_NOTE,
                    'sun_note' => $sunHere
                        ? 'गोचर सूर्य इस समय कष्ट-राशि में है — यह पूरा मास कष्ट-प्रद रह सकता है।'
                        : 'गोचर सूर्य अभी कष्ट-राशि में नहीं है।',
                ];
            }
        }

        return $out;
    }

    /**
     * @return array{0:int,1:int}  [nakshatra index 0..26, pada 1..4]
     */
    private static function nakshatra(float $lon): array
    {
        $lon = fmod($lon + 360.0, 360.0);
        $idx = (int) floor($lon / self::NAK_ARC) % 27;
        $pada = (int) floor(fmod($lon, self::NAK_ARC) / self::PADA_ARC) + 1;
        return [$idx, max(1, min(4, $pada))];
    }

    /** Decimal hours -> "HH:MM" (24-hour). */
    private static function hm(float $hours): string
    {
        $hours = fmod($hours + 24.0, 24.0);
        $h = (int) floor($hours);
        $m = (int) round(($hours - $h) * 60.0);
        if ($m === 60) { $h = ($h + 1) % 24; $m = 0; }
        return sprintf('%02d:%02d', $h, $m);
    }

    private static function ordHi(int $n): string
    {
        return ['', 'प्रथम', 'द्वितीय', 'तृतीय', 'चतुर्थ', 'पंचम', 'षष्ठ', 'सप्तम', 'अष्टम'][$n] ?? ($n . 'वाँ');
    }

    private static function hi(string $planet): string
    {
        return GocharRepository::PLANET_HI[$planet] ?? $planet;
    }

    private static function rashiHi(int $s): string
    {
        return ['मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या', 'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुंभ', 'मीन'][$s] ?? '';
    }
}
