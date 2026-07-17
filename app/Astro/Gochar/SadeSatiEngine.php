<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Gochar;

use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * Shani Sade Sati / Dhaiyya / Pancham-Shani detection + severity + Paya
 * (Update 04, Gochar Ch.4). A Layer-4 overlay on the existing Gochar engine:
 * it reads the natal chart + the transit Saturn and adds a separate reading —
 * it does NOT change any Layer-1 Saturn house text (C16), and the Paya is a
 * standalone indicator whose score is never merged into Layer 1 (C15).
 *
 *   detection  Saturn's house from the natal Moon → 12/1/2 = Sade Sati
 *              (phase 1/2/3), 4/8 = Dhaiyya, 5 = Pancham Shani.
 *   severity   0..3 from natal Saturn strength + yogakaraka (lagna & Moon,
 *              via {@see DashaSupport}), the transit-nakshatra lord's relation
 *              to Saturn, the natal Sun/Mars-rashi rule (S10), the running
 *              dasha-lord's functional benefity (S13) and the occurrence number.
 *   paya       metal of Saturn's house from the Moon (Silver/Copper shubh,
 *              Gold/Iron ashubh) with its own effect text.
 */
final class SadeSatiEngine
{
    private const NAK_LORDS = ['Ketu', 'Venus', 'Sun', 'Moon', 'Mars', 'Rahu', 'Jupiter', 'Saturn', 'Mercury'];
    private const SEV_HI = [0 => 'नगण्य / शुभ', 1 => 'मंद', 2 => 'मध्यम', 3 => 'तीव्र'];

    /**
     * @param array<string,mixed> $natal   CalculationEngine::computeChart
     * @param array<string,mixed> $tSaturn transit Saturn snapshot (sign_index, sidereal_lon)
     * @param string|null $runningDashaLord natal Mahadasha lord at the transit date
     * @param float $ageYears age at the transit instant (for the occurrence number)
     * @param array<string,mixed> $rules   SadeSatiRepository::load
     * @return array<string,mixed>|null null when Saturn is not in a Sade-Sati/Dhaiyya/Pancham house
     */
    public static function compute(array $natal, array $tSaturn, ?string $runningDashaLord, float $ageYears, array $rules): ?array
    {
        $planets = $natal['planets'] ?? [];
        $moonSign = (int) ($planets['Moon']['sign_index'] ?? 0);
        $ascSign = (int) ($natal['ascendant']['sign_index'] ?? 0);
        $satSign = (int) ($tSaturn['sign_index'] ?? 0);
        $house = (($satSign - $moonSign) % 12 + 12) % 12 + 1;   // Saturn from the natal Moon

        // ---- detection ----
        $type = null; $phase = null; $typeHi = '';
        if (in_array($house, [12, 1, 2], true)) {
            $type = 'sadesati'; $phase = [12 => 1, 1 => 2, 2 => 3][$house];
            $typeHi = 'साढ़े साती — चरण ' . $phase . ' (' . [1 => 'आरम्भ', 2 => 'शिखर', 3 => 'उतरती'][$phase] . ')';
        } elseif (in_array($house, [4, 8], true)) {
            $type = 'dhaiyya'; $typeHi = 'ढैय्या (लघु साढ़े साती)';
        } elseif ($house === 5) {
            $type = 'pancham'; $typeHi = 'पंचम शनि (कर्नाटक परम्परा)';
        }

        // ---- Paya (independent indicator; house from Moon) ----
        $payaRow = $rules['paya'][$house] ?? null;
        $paya = $payaRow !== null ? [
            'house' => $house, 'metal' => $payaRow['metal'], 'shubh' => (bool) $payaRow['shubh'], 'effect' => $payaRow['effect'],
        ] : null;

        if ($type === null) {
            // Not an active Sade-Sati window — still surface the Paya indicator.
            return $paya === null ? null : ['active' => false, 'house' => $house, 'paya' => $paya];
        }

        // ---- severity (S8–S13) ----
        $sev = 2; $why = [];
        $satStrength = DashaSupport::isStrong('Saturn', $natal);
        $yk = DashaSupport::functionalRole('Saturn', $ascSign)['yogakaraka']
            || DashaSupport::functionalRole('Saturn', $moonSign)['yogakaraka'];
        if ($satStrength['strong'] || $yk) { $sev -= 2; $why[] = 'जन्म शनि बली/योगकारक — कष्ट कम (S8)'; }
        if ($satStrength['weak']) { $sev += 1; $why[] = 'जन्म शनि निर्बल/शत्रु-क्षेत्री — विशेष अनिष्ट (S8)'; }

        // Nakshatra-lord relation to Saturn (S9).
        $nakIndex = (int) floor(Charts::norm((float) ($tSaturn['sidereal_lon'] ?? 0.0)) / (40.0 / 3.0)); // 13°20'
        $nakLord = self::NAK_LORDS[$nakIndex % 9];
        $rel = $nakLord === 'Saturn' ? 'F' : PlanetCondition::naturalRelationDirected('Saturn', $nakLord);
        if (in_array($rel, ['F'], true) || $nakLord === 'Saturn') { $sev -= 1; $why[] = self::hi($nakLord) . '-नक्षत्र (शनि-मित्र/स्व) — फल नरम (S9)'; }
        elseif ($rel === 'E') { $sev += 1; $why[] = self::hi($nakLord) . '-नक्षत्र (शनि-शत्रु) — फल कठोर (S9)'; }

        // Natal Sun/Mars rashi or 7th from it (S10).
        $s10 = false;
        foreach (['Sun', 'Mars'] as $mp) {
            if (!isset($planets[$mp])) { continue; }
            $ms = (int) $planets[$mp]['sign_index'];
            if ($satSign === $ms || $satSign === ($ms + 6) % 12) { $s10 = true; }
        }
        if ($s10) { $sev += 1; $why[] = 'गोचर शनि जन्म सूर्य/मंगल की राशि (या 7वीं) में — समय खराब (S10)'; }

        // Running dasha lord benefic (S13/C22 via functional role).
        if ($runningDashaLord !== null && !in_array($runningDashaLord, ['Rahu', 'Ketu'], true)) {
            $dr = DashaSupport::functionalRole($runningDashaLord, $ascSign);
            if ($dr['benefic'] || $dr['yogakaraka']) { $sev -= 1; $why[] = self::hi($runningDashaLord) . ' महादशा शुभ/योगकारक — कष्ट टलता (S13)'; }
        }

        // Occurrence number (S11) from age and Saturn's ~29.46-yr cycle.
        $cycle = (float) ($rules['config']['sadesati_saturn_cycle'] ?? 29.46);
        $occ = (int) floor($ageYears / $cycle) + 1;
        if ($occ === 2) { $sev -= 1; $why[] = 'दूसरी साढ़े साती — कष्ट अधिक नहीं (S11)'; }
        elseif ($occ >= 3) { $sev += 1; $why[] = 'तीसरी+ साढ़े साती — प्रायः घातक (S11)'; }

        $sev = max(0, min(3, $sev));

        // ---- phase phal text ----
        $phalKey = $type === 'sadesati' ? 'phase' . $phase : $type;
        $phal = (string) ($rules['phal'][$phalKey] ?? '');
        $general = (string) ($rules['phal']['general'] ?? '');

        // ---- event mapping (S14): natal planets Saturn conjoins / aspects (3,7,10) ----
        $events = self::eventMap($satSign, $planets);

        return [
            'active' => true,
            'type' => $type, 'type_hi' => $typeHi, 'phase' => $phase, 'house' => $house,
            'severity' => $sev, 'severity_hi' => self::SEV_HI[$sev],
            'severity_why' => $why,
            'occurrence' => $occ,
            'nak_lord' => self::hi($nakLord),
            'phal' => $phal, 'general' => $general,
            'events' => $events,
            'paya' => $paya,
        ];
    }

    /**
     * S14 event mapping: natal planets that transit Saturn conjoins (same sign)
     * or aspects by its 3rd / 7th / 10th whole-sign drishti.
     *
     * @param array<string,array<string,mixed>> $planets
     * @return list<array{planet:string,planet_hi:string,how:string}>
     */
    private static function eventMap(int $satSign, array $planets): array
    {
        $out = [];
        foreach (['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'] as $p) {
            if (!isset($planets[$p])) { continue; }
            $ps = (int) $planets[$p]['sign_index'];
            $rel = (($ps - $satSign) % 12 + 12) % 12 + 1;   // house of natal planet from transit Saturn
            $how = $rel === 1 ? 'युति' : ($rel === 3 ? 'तृतीय दृष्टि' : ($rel === 7 ? 'सप्तम दृष्टि' : ($rel === 10 ? 'दशम दृष्टि' : null)));
            if ($how === null) { continue; }
            $out[] = ['planet' => $p, 'planet_hi' => self::hi($p), 'how' => $how];
        }
        return $out;
    }

    private static function hi(string $p): string
    {
        return ['Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
            'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु'][$p] ?? $p;
    }
}
