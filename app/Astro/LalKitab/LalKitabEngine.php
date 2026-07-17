<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\LalKitab;

use AutoBusiness\Astro\Calc\Charts;

/**
 * Lal Kitab (लाल किताब) reading engine.
 *
 * Takes an already-computed D1 chart ({@see CalculationEngine::computeChart})
 * and produces a complete Lal Kitab teva reading:
 *
 *   - the fixed-Aries Lal Kitab chart (planets placed by their bhava, house 1
 *     always Aries with the twelve fixed house-lords);
 *   - per-planet classification (उच्च / नीच / स्वगृही), शुभ/अशुभ भाव verdict and
 *     the matching planet-in-house टोटके (remedies);
 *   - per-house reading (भाव विचार subjects + occupants + lord placement);
 *   - कारक (natural significators) per house and whether they sit well;
 *   - योग — the भविष्यवाणी सूत्र that apply to the actual placements;
 *   - श्राप / पैतृक ऋण (parental-debt) detection;
 *   - साढ़े साती / ढैय्या remedies for the janma-rashi;
 *   - मंगलीक (Mangal-dosha) status + remedy;
 *   - a consolidated remedy list (हर समस्या के नीचे उपाय).
 *
 * All predictive text is baked / owner-editable via {@see LalKitabData}. This
 * class only decides which text applies to this chart. Pure + deterministic.
 */
final class LalKitabEngine
{
    /** Planets carried into the Lal Kitab chart (incl. shadow planets). */
    private const PLANETS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];

    /**
     * @param array<string,mixed> $chart D1 chart from CalculationEngine
     * @return array<string,mixed>
     */
    public static function compute(array $chart): array
    {
        if (empty($chart['planets']) || empty($chart['ascendant'])) {
            return ['ok' => false, 'error' => 'चार्ट उपलब्ध नहीं'];
        }

        $lagnaSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $moonSign  = (int) ($chart['planets']['Moon']['sign_index'] ?? 0);

        // ---- place planets into Lal Kitab houses (= bhava from lagna) ----
        $house = [];            // planet => LK house 1..12
        $occupants = array_fill(1, 12, []);
        foreach (self::PLANETS as $p) {
            if (!isset($chart['planets'][$p])) {
                continue;
            }
            $h = (int) ($chart['planets'][$p]['house'] ?? 0);
            if ($h < 1 || $h > 12) {
                continue;
            }
            $house[$p] = $h;
            $occupants[$h][] = $p;
        }

        // ---- the fixed-Aries Lal Kitab chart grid ----
        $grid = [];
        for ($h = 1; $h <= 12; $h++) {
            $sIdx = LalKitabData::HOUSE_SIGN[$h];
            $lord = LalKitabData::HOUSE_LORD[$h];
            $grid[$h] = [
                'house'    => $h,
                'sign'     => $sIdx,
                'sign_hi'  => LalKitabData::signHi(Charts::SIGNS[$sIdx]),
                'lord'     => $lord,
                'lord_hi'  => LalKitabData::planetHi($lord),
                'planets'  => $occupants[$h],
                'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $occupants[$h]),
            ];
        }

        return [
            'ok'          => true,
            'error'       => null,
            'lagna_sign'  => $lagnaSign,
            'lagna_hi'    => LalKitabData::signHi(Charts::SIGNS[$lagnaSign]),
            'moon_sign'   => $moonSign,
            'moon_hi'     => LalKitabData::signHi(Charts::SIGNS[$moonSign]),
            'grid'        => $grid,
            'planets'     => self::planetReadings($chart, $house),
            'houses'      => self::houseReadings($house, $occupants),
            'karak'       => self::karakReadings($house),
            'yoga'        => self::yogaReadings($house),
            'shrap'       => self::shrapReadings($house),
            'sadesati'    => self::sadeSatiReadings($moonSign),
            'manglik'     => self::manglikReadings($chart, $lagnaSign, $house),
            'remedy'      => self::remedyReadings($house, $occupants),
        ];
    }

    /**
     * @param array<string,mixed> $chart
     * @param array<string,int> $house
     * @return array<int,array<string,mixed>>
     */
    private static function planetReadings(array $chart, array $house): array
    {
        $gp  = LalKitabData::section('graha_parichay');
        $sab = LalKitabData::section('shubh_ashubh_bhav');
        $un  = LalKitabData::section('uch_neech_niyam');
        $al  = LalKitabData::section('ashubh_lakshan');
        $bg  = LalKitabData::section('bhavgat_upay');
        $su  = LalKitabData::section('samanya_upay');

        $out = [];
        foreach (self::PLANETS as $p) {
            if (!isset($house[$p])) {
                continue;
            }
            $h    = $house[$p];
            $sIdx = (int) ($chart['planets'][$p]['sign_index'] ?? 0);
            $retro = !empty($chart['planets'][$p]['retro']);

            // classification against the real rashi
            $status = 'सम';
            $exalt = LalKitabData::EXALT[$p] ?? null;
            $debil = LalKitabData::DEBIL[$p] ?? null;
            if ($exalt !== null && $sIdx === $exalt) {
                $status = 'उच्च';
            } elseif ($debil !== null && $sIdx === $debil) {
                $status = 'नीच';
            } elseif (Charts::SIGN_LORDS[$sIdx] === $p) {
                $status = 'स्वगृही';
            }

            // shubh / ashubh house verdict
            $shubhList  = self::parseHouses($sab[$p]['shubh'] ?? '');
            $ashubhList = self::parseHouses($sab[$p]['ashubh'] ?? '');
            $isAshubh = in_array($h, $ashubhList, true);
            $isShubh  = in_array($h, $shubhList, true);
            if ($status === 'नीच') {
                $isAshubh = true;
            }

            $verdict = $isAshubh ? 'अशुभ' : ($isShubh ? 'शुभ' : 'मध्यम');

            $remedies = $bg[$p][(string) $h] ?? [];

            $out[] = [
                'planet'    => $p,
                'hi'        => LalKitabData::planetHi($p),
                'house'     => $h,
                'house_ord' => LalKitabData::houseOrdinalHi($h),
                'sign'      => $sIdx,
                'sign_hi'   => LalKitabData::signHi(Charts::SIGNS[$sIdx]),
                'retro'     => $retro,
                'status'    => $status,
                'verdict'   => $verdict,
                'is_ashubh' => $isAshubh,
                'karak_bhav'=> $gp[$p]['karak_bhav'] ?? '',
                'prakriti'  => $gp[$p]['prakriti'] ?? '',
                'rang'      => $gp[$p]['rang'] ?? '',
                'shubh'     => $sab[$p]['shubh'] ?? '',
                'ashubh'    => $sab[$p]['ashubh'] ?? '',
                'note'      => $sab[$p]['note'] ?? '',
                'uch_rashi' => $un[$p]['uch'] ?? '',
                'neech_rashi'=> $un[$p]['neech'] ?? '',
                'bhang'     => $un[$p]['bhang'] ?? '',
                'ashubh_lakshan' => $isAshubh ? ($al[$p] ?? '') : '',
                'remedies'  => array_values($remedies),
                'samanya'   => array_values($su[$p] ?? []),
            ];
        }
        return $out;
    }

    /**
     * @param array<string,int> $house
     * @param array<int,list<string>> $occupants
     * @return array<int,array<string,mixed>>
     */
    private static function houseReadings(array $house, array $occupants): array
    {
        $bv = LalKitabData::section('bhav_vichar');
        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            $lord = LalKitabData::HOUSE_LORD[$h];
            $out[$h] = [
                'house'      => $h,
                'house_ord'  => LalKitabData::houseOrdinalHi($h),
                'rashi'      => $bv[(string) $h]['rashi'] ?? '',
                'swami'      => $bv[(string) $h]['swami'] ?? '',
                'vishay'     => $bv[(string) $h]['vishay'] ?? '',
                'lord'       => $lord,
                'lord_hi'    => LalKitabData::planetHi($lord),
                'lord_house' => $house[$lord] ?? null,   // where this house-lord sits (LK)
                'planets'    => $occupants[$h],
                'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $occupants[$h]),
            ];
        }
        return $out;
    }

    /**
     * कारक — natural significator per house (from ग्रह परिचय कारक भाव), plus where
     * that karak actually sits and whether that is favourable.
     *
     * @param array<string,int> $house
     * @return array<int,array<string,mixed>>
     */
    private static function karakReadings(array $house): array
    {
        $gp = LalKitabData::section('graha_parichay');
        // build house => list of karak planets from the karak_bhav column
        $byHouse = array_fill(1, 12, []);
        foreach ($gp as $p => $row) {
            foreach (self::parseHouses((string) ($row['karak_bhav'] ?? '')) as $kh) {
                if ($kh >= 1 && $kh <= 12) {
                    $byHouse[$kh][] = $p;
                }
            }
        }
        $sab = LalKitabData::section('shubh_ashubh_bhav');
        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            $karaks = [];
            foreach ($byHouse[$h] as $p) {
                $ph = $house[$p] ?? null;
                $ashubhList = self::parseHouses($sab[$p]['ashubh'] ?? '');
                $karaks[] = [
                    'planet'   => $p,
                    'hi'       => LalKitabData::planetHi($p),
                    'placed'   => $ph,
                    'placed_ord' => $ph ? LalKitabData::houseOrdinalHi($ph) : '',
                    'ok'       => $ph !== null && !in_array($ph, $ashubhList, true),
                ];
            }
            $out[$h] = [
                'house'     => $h,
                'house_ord' => LalKitabData::houseOrdinalHi($h),
                'karaks'    => $karaks,
            ];
        }
        return $out;
    }

    /**
     * योग — भविष्यवाणी सूत्र that apply. A sutra is flagged applicable when its
     * स्थिति text names the ordinal house together with the planet that actually
     * occupies that house in this chart.
     *
     * @param array<string,int> $house
     * @return list<array<string,mixed>>
     */
    private static function yogaReadings(array $house): array
    {
        $rules = LalKitabData::section('bhavishyavani');
        // reverse: house => "<ordinal> भाव" phrases for the occupant planets
        $out = [];
        foreach ($rules as $r) {
            $sthiti = (string) ($r['sthiti'] ?? '');
            $applicable = false;
            foreach ($house as $p => $h) {
                $ord = LalKitabData::houseOrdinalHi($h);
                $ph  = LalKitabData::planetHi($p);
                if ($ph !== '' && mb_strpos($sthiti, $ph) !== false
                    && mb_strpos($sthiti, $ord . ' भाव') !== false) {
                    $applicable = true;
                    break;
                }
            }
            $out[] = [
                'sthiti'     => $sthiti,
                'prabhavit'  => (string) ($r['prabhavit'] ?? ''),
                'phal'       => (string) ($r['phal'] ?? ''),
                'applicable' => $applicable,
            ];
        }
        return $out;
    }

    /**
     * श्राप / पैतृक ऋण — the nine ancestral debts. Compound OR-conditions make
     * exact detection unreliable, so each debt's planet-in-house cues are matched
     * best-effort and flagged "संभावित" (possible) when any cue matches.
     *
     * @param array<string,int> $house
     * @return list<array<string,mixed>>
     */
    private static function shrapReadings(array $house): array
    {
        $rin = LalKitabData::section('paitrik_rin');
        $out = [];
        foreach ($rin as $r) {
            $pehchan = (string) ($r['pehchan'] ?? '');
            $possible = false;
            foreach ($house as $p => $h) {
                $ord = LalKitabData::houseOrdinalHi($h);
                $ph  = LalKitabData::planetHi($p);
                if ($ph !== '' && mb_strpos($pehchan, $ph) !== false
                    && mb_strpos($pehchan, $ord . ' भाव') !== false) {
                    $possible = true;
                    break;
                }
            }
            $out[] = [
                'rin'         => (string) ($r['rin'] ?? ''),
                'pehchan'     => $pehchan,
                'ashubh_grah' => (string) ($r['ashubh_grah'] ?? ''),
                'sanket'      => (string) ($r['sanket'] ?? ''),
                'ashubh_phal' => (string) ($r['ashubh_phal'] ?? ''),
                'upay'        => (string) ($r['upay'] ?? ''),
                'possible'    => $possible,
            ];
        }
        return $out;
    }

    /**
     * साढ़े साती + ढैय्या for the janma-rashi (Moon sign).
     *
     * @return array<string,mixed>
     */
    private static function sadeSatiReadings(int $moonSign): array
    {
        $ssp = LalKitabData::section('sadesati_pehchan');
        $ssu = LalKitabData::section('sadesati_upay');
        $dhp = LalKitabData::section('dhaiya_pehchan');
        $dhu = LalKitabData::section('dhaiya_upay');
        $k = (string) $moonSign;
        return [
            'rashi_hi'        => LalKitabData::signHi(Charts::SIGNS[$moonSign]),
            'pehchan'         => $ssp[$k]['shani_on'] ?? '',
            'note'            => $ssp[$k]['note'] ?? '',
            'upay'            => array_values($ssu[$k] ?? []),
            'dhaiya_pehchan'  => $dhp[$k]['shani_on'] ?? '',
            'dhaiya_upay'     => array_values($dhu[$k] ?? []),
        ];
    }

    /**
     * मंगलीक — Mars in 1/4/7/8/12 from lagna (Lal Kitab), with the lagna-specific
     * remedy for the offending house.
     *
     * @param array<string,mixed> $chart
     * @param array<string,int> $house
     * @return array<string,mixed>
     */
    private static function manglikReadings(array $chart, int $lagnaSign, array $house): array
    {
        $mh = $house['Mars'] ?? 0;
        $doshaHouses = [1, 4, 7, 8, 12];
        $isManglik = in_array($mh, $doshaHouses, true);
        $mu = LalKitabData::section('manglik_upay');
        $upay = '';
        if ($isManglik) {
            $upay = $mu[(string) $lagnaSign][(string) $mh] ?? '';
        }
        return [
            'is'         => $isManglik,
            'mars_house' => $mh,
            'mars_ord'   => $mh ? LalKitabData::houseOrdinalHi($mh) : '',
            'lagna_hi'   => LalKitabData::signHi(Charts::SIGNS[$lagnaSign]),
            'upay'       => $upay,
            'parihar'    => LalKitabData::section('manglik_parihar'),
            'vichar'     => LalKitabData::section('manglik_vichar'),
        ];
    }

    /**
     * Consolidated remedies: per-planet सामान्य उपाय + conjunction (युति) remedies
     * for planets sharing a house, plus the "problem → remedy" pairs for every
     * afflicted (अशुभ) planet.
     *
     * @param array<string,int> $house
     * @param array<int,list<string>> $occupants
     * @return array<string,mixed>
     */
    private static function remedyReadings(array $house, array $occupants): array
    {
        $su  = LalKitabData::section('samanya_upay');
        $gy  = LalKitabData::section('grah_yuti_upay');

        $samanya = [];
        foreach (self::PLANETS as $p) {
            if (isset($house[$p]) && !empty($su[$p])) {
                $samanya[] = ['hi' => LalKitabData::planetHi($p), 'upay' => array_values($su[$p])];
            }
        }

        // conjunctions: any house with 2+ planets → match yuti remedies
        $yuti = [];
        for ($h = 1; $h <= 12; $h++) {
            $ps = $occupants[$h];
            $n = count($ps);
            if ($n < 2) {
                continue;
            }
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = LalKitabData::planetHi($ps[$i]);
                    $b = LalKitabData::planetHi($ps[$j]);
                    foreach ($gy as $row) {
                        $y = (string) ($row['yuti'] ?? '');
                        if (mb_strpos($y, $a) !== false && mb_strpos($y, $b) !== false) {
                            $yuti[] = [
                                'yuti'  => $y,
                                'house' => $h,
                                'house_ord' => LalKitabData::houseOrdinalHi($h),
                                'shart' => (string) ($row['shart'] ?? ''),
                                'upay'  => (string) ($row['upay'] ?? ''),
                            ];
                        }
                    }
                }
            }
        }

        return ['samanya' => $samanya, 'yuti' => $yuti];
    }

    /** Parse a "1, 5, 8" / "1 से 5, 8" house string into a flat int list. */
    private static function parseHouses(string $s): array
    {
        $out = [];
        // handle "1 से 5" ranges
        $s = preg_replace_callback('/(\d+)\s*से\s*(\d+)/u', static function ($m) {
            $r = [];
            for ($i = (int) $m[1]; $i <= (int) $m[2]; $i++) {
                $r[] = $i;
            }
            return implode(',', $r);
        }, $s) ?? $s;
        foreach (preg_split('/[^0-9]+/', $s) as $tok) {
            if ($tok !== '' && ctype_digit($tok)) {
                $n = (int) $tok;
                if ($n >= 1 && $n <= 12) {
                    $out[$n] = $n;
                }
            }
        }
        return array_values($out);
    }
}
