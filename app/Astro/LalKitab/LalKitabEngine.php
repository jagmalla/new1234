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
     * @param int|null $age native's current age in years (for the वर्ष कुंडली
     *                      ज्ञान चक्र row); null hides the age-specific block
     * @param array<string,mixed> $active current activation context:
     *        ['maha' => lord|null, 'antar' => lord|null,
     *         'sadesati' => ['kind' => 'sadesati'|'dhaiya', 'phase' => 1..3|null]|null]
     *        — used to highlight the planets that are "live" right now and to
     *        weight the priority score.
     * @return array<string,mixed>
     */
    public static function compute(array $chart, ?int $age = null, array $active = []): array
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

        // Build the interdependent readings in order: planets → sleeping-state →
        // special conjunction doshas → priority score (uses all three + the
        // running dasha / age context) → the "active now" summary strip.
        $planets   = self::planetReadings($chart, $house);
        $supt      = self::suptReadings($house);
        $yutiDosha = self::yutiDoshaReadings($occupants);
        self::scorePlanets($planets, $supt, $yutiDosha, $active, $age);
        $priority  = self::priorityReadings($planets);
        $activeNow = self::activeReadings($house, $planets, $active, $age);

        return [
            'ok'          => true,
            'error'       => null,
            'lagna_sign'  => $lagnaSign,
            'lagna_hi'    => LalKitabData::signHi(Charts::SIGNS[$lagnaSign]),
            'moon_sign'   => $moonSign,
            'moon_hi'     => LalKitabData::signHi(Charts::SIGNS[$moonSign]),
            'grid'        => $grid,
            'planets'     => $planets,
            'active'      => $activeNow,
            'priority'    => $priority,
            'yuti_dosha'  => $yutiDosha,
            'houses'      => self::houseReadings($house, $occupants),
            'karak'       => self::karakReadings($house),
            'yoga'        => self::yogaReadings($house),
            'shrap'       => self::shrapReadings($house),
            'sadesati'    => self::sadeSatiReadings($moonSign),
            'manglik'     => self::manglikReadings($chart, $lagnaSign, $house),
            'remedy'      => self::remedyReadings($house, $occupants),
            'ayu'         => self::ayuReadings(),
            'health'      => self::healthReadings(),
            'bhavan'      => LalKitabData::section('bhavan'),
            'varsh_gyan'  => self::varshGyanReadings($age),
            'rules'       => self::ruleReadings(),
            'supt'        => $supt,
            'drishti'     => self::drishtiReadings($house, $occupants),
            'reference'   => self::referenceReadings($age),
            'age'         => $age,
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
        $mt  = LalKitabData::section('maitri');
        $sh  = LalKitabData::section('sheeghra');
        $pd  = LalKitabData::section('puja_daan');

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

            // पक्का घर — the planet's own fixed house; sitting there = stable/strong.
            $pukka = in_array($h, LalKitabData::PUKKA_GHAR[$p] ?? [], true);

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
                'pukka'     => $pukka,
                'var'       => $gp[$p]['var'] ?? '',
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
                'mitra'     => $mt[$p]['mitra'] ?? '',
                'shatru'    => $mt[$p]['shatru'] ?? '',
                'sam'       => $mt[$p]['sam'] ?? '',
                'sheeghra'  => $sh[$p] ?? '',
                'upasana'   => $pd[$p]['upasana'] ?? '',
                'daan'      => $pd[$p]['daan'] ?? '',
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
        $bd    = LalKitabData::section('bhav_drishti');
        $out = [];
        foreach ($rules as $r) {
            $sthiti = (string) ($r['sthiti'] ?? '');
            $cond   = $r['cond'] ?? null;

            if (is_array($cond) && $cond !== []) {
                // Exact evaluation from the parsed clauses (baked at data-build
                // time): every clause must hold. Clause forms:
                //   {p:[..], h:[..]}  every planet's house ∈ h
                //   {p:[A,B], h:[]}   yuti — all planets share one house
                //   {asp:[A,B]}       A's house aspects B's house (भाव दृष्टि चक्र)
                $applicable = true;
                foreach ($cond as $c) {
                    if (isset($c['asp'])) {
                        [$a, $b] = $c['asp'];
                        $ha = $house[$a] ?? null;
                        $hb = $house[$b] ?? null;
                        $sees = ($ha !== null) ? ($bd[(string) $ha]['drishti'] ?? []) : [];
                        if ($ha === null || $hb === null || !in_array($hb, $sees, true)) {
                            $applicable = false;
                            break;
                        }
                        continue;
                    }
                    $ps = $c['p'] ?? [];
                    $hs = $c['h'] ?? [];
                    if ($hs !== []) {
                        foreach ($ps as $p) {
                            if (!isset($house[$p]) || !in_array($house[$p], $hs, true)) {
                                $applicable = false;
                                break 2;
                            }
                        }
                    } else {
                        // yuti: all named planets in one and the same house
                        $seen = [];
                        foreach ($ps as $p) { $seen[] = $house[$p] ?? -1; }
                        if (count(array_unique($seen)) !== 1 || $seen[0] === -1) {
                            $applicable = false;
                            break;
                        }
                    }
                }
                $mode = 'exact';
            } else {
                // Fallback: the original text heuristic (planet name + its
                // ordinal-house phrase both appear in the स्थिति text).
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
                $mode = 'text';
            }

            $out[] = [
                'sthiti'     => $sthiti,
                'prabhavit'  => (string) ($r['prabhavit'] ?? ''),
                'phal'       => (string) ($r['phal'] ?? ''),
                'applicable' => $applicable,
                'mode'       => $mode,   // 'exact' = parsed rule, 'text' = heuristic
            ];
        }
        return $out;
    }

    /**
     * विशेष युति / ग्रहण दोष — well-known malefic conjunctions detected from the
     * teva (two planets sharing a house). Each carries its उपाय, looked up from
     * the ग्रह-युति उपाय bank (plus each planet's शीघ्र उपाय as fallback).
     *
     * @param array<int,list<string>> $occupants
     * @return list<array<string,mixed>>
     */
    private static function yutiDoshaReadings(array $occupants): array
    {
        /** alphabetically-sorted pair key => [name, description] */
        $catalog = [
            'Rahu|Sun'      => ['सूर्य ग्रहण दोष', 'सूर्य-राहु की युति — पिता, मान-सम्मान व सरकारी पक्ष के फल ग्रहण-ग्रस्त होते हैं; आत्मविश्वास व पद में बाधा।'],
            'Ketu|Sun'      => ['सूर्य-केतु दोष', 'सूर्य-केतु की युति — पुत्र-पक्ष व पिता के सुख में कमी, कार्यों में अचानक रुकावट।'],
            'Moon|Rahu'     => ['चन्द्र ग्रहण दोष', 'चन्द्र-राहु की युति — मन अशांत, वहम/चिंता, माता के सुख व जल-स्रोतों से हानि।'],
            'Ketu|Moon'     => ['चन्द्र-केतु दोष', 'चन्द्र-केतु की युति — मन में उतार-चढ़ाव, माता या मामा-पक्ष को कष्ट।'],
            'Jupiter|Rahu'  => ['गुरु चांडाल योग', 'गुरु-राहु की युति — गुरु/धर्म/शिक्षा के फल दूषित; बड़ों से मतभेद, निर्णय-दोष।'],
            'Mars|Saturn'   => ['शनि-मंगल कष्ट योग', 'शनि-मंगल की युति — संघर्ष, दुर्घटना/चोट व तकनीकी-भूमि विवादों की प्रवृत्ति।'],
            'Mars|Rahu'     => ['अंगारक योग', 'मंगल-राहु की युति — क्रोध व जोखिम की अधिकता, रक्त/अग्नि सम्बन्धी कष्ट।'],
            'Rahu|Saturn'   => ['श्रापित योग', 'शनि-राहु की युति — रुके हुए काम, दीर्घ बाधाएँ व पैतृक अशुभता का संकेत।'],
        ];
        $gy = LalKitabData::section('grah_yuti_upay');
        $sh = LalKitabData::section('sheeghra');

        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            $ps = $occupants[$h] ?? [];
            $n = count($ps);
            if ($n < 2) { continue; }
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $pair = [$ps[$i], $ps[$j]];
                    sort($pair);
                    $key = implode('|', $pair);
                    if (!isset($catalog[$key])) { continue; }
                    [$name, $desc] = $catalog[$key];
                    // उपाय: matching entries from the ग्रह-युति bank …
                    $rem = [];
                    // display order follows the traditional planet sequence
                    $disp = array_values(array_intersect(self::PLANETS, $pair));
                    $a = LalKitabData::planetHi($disp[0]);
                    $b = LalKitabData::planetHi($disp[1]);
                    foreach ($gy as $row) {
                        $y = (string) ($row['yuti'] ?? '');
                        if (mb_strpos($y, $a) !== false && mb_strpos($y, $b) !== false) {
                            $rem[] = (string) ($row['upay'] ?? '');
                        }
                    }
                    // … plus each planet's quick remedy as a safe fallback.
                    foreach ($pair as $p) {
                        if (!empty($sh[$p])) { $rem[] = LalKitabData::planetHi($p) . ' शीघ्र उपाय: ' . $sh[$p]; }
                    }
                    $out[] = [
                        'name'      => $name,
                        'desc'      => $desc,
                        'planets'   => $pair,
                        'pair_hi'   => $a . '-' . $b,
                        'house'     => $h,
                        'house_ord' => LalKitabData::houseOrdinalHi($h),
                        'remedies'  => array_values(array_filter($rem)),
                    ];
                }
            }
        }
        return $out;
    }

    /**
     * Priority score — a transparent, additive severity score per planet so the
     * astrologer knows which उपाय to start with. The weights (shown to the user
     * as reasons) are:
     *   +3 महादशा स्वामी      +2 अंतर्दशा स्वामी
     *   +2 नीच राशि           +2 अशुभ भाव (लाल किताब)
     *   +2 ग्रहण/विशेष युति    +2 इस आयु-वर्ष ग्रह का अशुभ वर्ष
     *   +2 शनि पर साढ़े साती/ढैय्या    +1 सुप्त ग्रह
     *   −1 उच्च राशि          −1 पक्का घर
     *
     * @param list<array<string,mixed>> $planets   modified in place
     * @param list<array<string,mixed>> $supt
     * @param list<array<string,mixed>> $yutiDosha
     * @param array<string,mixed> $active
     */
    private static function scorePlanets(array &$planets, array $supt, array $yutiDosha, array $active, ?int $age): void
    {
        // lookups
        $asleep = [];
        foreach ($supt as $s) {
            if (empty($s['awake'])) { $asleep[$s['hi']] = true; }
        }
        $inDosha = [];
        foreach ($yutiDosha as $d) {
            foreach ($d['planets'] as $p) { $inDosha[$p] = $d['name']; }
        }
        $gc = LalKitabData::section('grah_chakra');

        foreach ($planets as &$pl) {
            $p = $pl['planet'];
            $score = 0;
            $reasons = [];

            if ($active['maha'] ?? null) {
                if ($active['maha'] === $p) { $score += 3; $reasons[] = 'महादशा स्वामी (+3)'; }
            }
            if (($active['antar'] ?? null) === $p) { $score += 2; $reasons[] = 'अंतर्दशा स्वामी (+2)'; }
            if ($pl['status'] === 'नीच') { $score += 2; $reasons[] = 'नीच राशि (+2)'; }
            if (!empty($pl['is_ashubh'])) { $score += 2; $reasons[] = 'अशुभ भाव (+2)'; }
            if (isset($inDosha[$p])) { $score += 2; $reasons[] = $inDosha[$p] . ' (+2)'; }
            if ($p === 'Saturn' && !empty($active['sadesati'])) {
                $score += 2;
                $reasons[] = (($active['sadesati']['kind'] ?? '') === 'dhaiya' ? 'ढैय्या' : 'साढ़े साती') . ' चल रही है (+2)';
            }
            if ($age !== null && !empty($gc[$p]['ashubh'])
                && preg_match_all('/\d+/', (string) $gc[$p]['ashubh'], $m)
                && in_array((string) $age, $m[0], true)) {
                $score += 2;
                $reasons[] = 'इस आयु-वर्ष में अशुभ वर्ष (+2)';
            }
            if (isset($asleep[$pl['hi']])) { $score += 1; $reasons[] = 'सुप्त ग्रह (+1)'; }
            if ($pl['status'] === 'उच्च') { $score -= 1; $reasons[] = 'उच्च राशि (−1)'; }
            if (!empty($pl['pukka'])) { $score -= 1; $reasons[] = 'पक्का घर (−1)'; }

            $pl['score']   = max(0, $score);
            $pl['reasons'] = $reasons;
        }
        unset($pl);
    }

    /**
     * "सबसे पहले इन उपायों से शुरू करें" — the top-3 planets by priority score
     * (score ≥ 2), each with its first few भावगत remedies and quick remedy.
     *
     * @param list<array<string,mixed>> $planets
     * @return list<array<string,mixed>>
     */
    private static function priorityReadings(array $planets): array
    {
        $sh = LalKitabData::section('sheeghra');
        $cand = array_values(array_filter($planets, static fn ($p) => ($p['score'] ?? 0) >= 2));
        usort($cand, static fn ($a, $b) => ($b['score'] <=> $a['score']));
        $out = [];
        foreach (array_slice($cand, 0, 3) as $p) {
            $out[] = [
                'hi'        => $p['hi'],
                'house_ord' => $p['house_ord'],
                'score'     => $p['score'],
                'reasons'   => $p['reasons'],
                'remedies'  => array_slice($p['remedies'], 0, 3),
                'sheeghra'  => $sh[$p['planet']] ?? '',
                'var'       => $p['var'],
            ];
        }
        return $out;
    }

    /**
     * 🔥 "अभी सक्रिय" strip — what is live right now: the running Maha/Antar
     * dasha lords with their Lal Kitab standing, the Sade-Sati/Dhaiya state and
     * the planets whose ग्रह-चक्र influence / malefic years include the current age.
     *
     * @param array<string,int> $house
     * @param list<array<string,mixed>> $planets
     * @return array<string,mixed>
     */
    private static function activeReadings(array $house, array $planets, array $active, ?int $age): array
    {
        $byKey = [];
        foreach ($planets as $p) { $byKey[$p['planet']] = $p; }
        $mk = static function (?string $lord) use ($byKey): ?array {
            if ($lord === null || !isset($byKey[$lord])) { return null; }
            $p = $byKey[$lord];
            return [
                'hi' => $p['hi'], 'house_ord' => $p['house_ord'],
                'verdict' => $p['verdict'], 'score' => $p['score'] ?? 0,
            ];
        };

        // planets whose influence / malefic years include the current age
        $gc = LalKitabData::section('grah_chakra');
        $yearEff = $yearBad = [];
        if ($age !== null) {
            foreach ($gc as $p => $row) {
                if (!empty($row['prabhav']) && preg_match_all('/\d+/', (string) $row['prabhav'], $m)
                    && in_array((string) $age, $m[0], true)) {
                    $yearEff[] = LalKitabData::planetHi($p);
                }
                if (!empty($row['ashubh']) && preg_match_all('/\d+/', (string) $row['ashubh'], $m)
                    && in_array((string) $age, $m[0], true)) {
                    $yearBad[] = LalKitabData::planetHi($p);
                }
            }
        }

        $ss = $active['sadesati'] ?? null;
        return [
            'maha'      => $mk($active['maha'] ?? null),
            'antar'     => $mk($active['antar'] ?? null),
            'sadesati'  => is_array($ss) ? [
                'label' => (($ss['kind'] ?? '') === 'dhaiya') ? 'ढैय्या' : 'साढ़े साती',
                'phase' => $ss['phase'] ?? null,
            ] : null,
            'year_eff'  => $yearEff,
            'year_bad'  => $yearBad,
            'age'       => $age,
        ];
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

    /**
     * आयु योग (longevity) — the yoga→years list plus each planet's Lal Kitab
     * influence-years chart.
     *
     * @return array<string,mixed>
     */
    private static function ayuReadings(): array
    {
        $gc = LalKitabData::section('grah_chakra');
        $chakra = [];
        foreach (self::PLANETS as $p) {
            if (isset($gc[$p])) {
                $chakra[] = [
                    'hi'      => LalKitabData::planetHi($p),
                    'prabhav' => $gc[$p]['prabhav'] ?? '',
                    'vishesh' => $gc[$p]['vishesh'] ?? '',
                    'ashubh'  => $gc[$p]['ashubh'] ?? '',
                    'kram'    => $gc[$p]['kram'] ?? '',
                ];
            }
        }
        return [
            'yoga'   => LalKitabData::section('ayu_yog'),
            'chakra' => $chakra,
        ];
    }

    /**
     * रोग / संतान — mixed disease & progeny remedies, grouped by category (वर्ग).
     *
     * @return array<string,list<string>>
     */
    private static function healthReadings(): array
    {
        $out = [];
        foreach (LalKitabData::section('rog_santan') as $r) {
            $varg = (string) ($r['varg'] ?? 'अन्य');
            $out[$varg][] = (string) ($r['upay'] ?? '');
        }
        return $out;
    }

    /**
     * वर्ष कुण्डली ज्ञान चक्र — for the native's current age, the bhava-number
     * activated in each of the twelve houses (plus the reference note).
     *
     * @return array<string,mixed>
     */
    private static function varshGyanReadings(?int $age): array
    {
        $tbl = LalKitabData::section('varsh_gyan');
        $row = null;
        if ($age !== null && $age >= 1) {
            $row = $tbl[(string) $age] ?? null;
        }
        return [
            'age'    => $age,
            'row'    => $row,   // list of 12 activation numbers (house1..12) or null
            'has'    => $row !== null,
        ];
    }

    /**
     * उपाय के नियम, वर्जित उपाय व दान-निषेध — the do/don't reference for remedies.
     *
     * @return array<string,mixed>
     */
    private static function ruleReadings(): array
    {
        return [
            'upay_niyam'    => LalKitabData::section('upay_niyam'),
            'paitrik_niyam' => LalKitabData::section('paitrik_niyam'),
            'varjit'        => LalKitabData::section('varjit'),
            'daan_nishedh'  => LalKitabData::section('daan_nishedh'),
        ];
    }

    /**
     * सुप्त ग्रह (sleeping planets). In Lal Kitab a planet stays dormant in its
     * house until the house's "waking" planet (सुप्त भाव चक्र) is itself present
     * in the chart. Logic here: planet P in house H wakes when supt_bhav[H] is
     * placed anywhere in this chart; otherwise it sleeps.
     *
     * @param array<string,int> $house
     * @return list<array<string,mixed>>
     */
    private static function suptReadings(array $house): array
    {
        $sb = LalKitabData::section('supt_bhav');   // house => waker (Hindi)
        $sg = LalKitabData::section('supt_grah');   // planet => when/age/malefic
        // Hindi waker name -> is that planet placed in the chart?
        $placedHi = [];
        foreach (array_keys($house) as $p) { $placedHi[LalKitabData::planetHi($p)] = true; }

        $out = [];
        foreach (self::PLANETS as $p) {
            if (!isset($house[$p])) { continue; }
            $h = $house[$p];
            $waker = (string) ($sb[(string) $h] ?? '');
            $awake = $waker !== '' && isset($placedHi[$waker]);
            $out[] = [
                'hi'        => LalKitabData::planetHi($p),
                'house'     => $h,
                'house_ord' => LalKitabData::houseOrdinalHi($h),
                'waker'     => $waker,
                'awake'     => $awake,
                'jagega'    => $sg[$p]['jagega'] ?? '',
                'aayu'      => $sg[$p]['aayu'] ?? '',
                'ashubh'    => $sg[$p]['ashubh'] ?? '',
            ];
        }
        return $out;
    }

    /**
     * भाव दृष्टि (Lal Kitab house aspects). For every occupied house, resolve its
     * दृष्टि (aspect), परस्पर सहायता (mutual help) and टकराव (conflict) target
     * houses and name the planets sitting there — turning the raw chakra into a
     * concrete planet-to-planet relationship reading.
     *
     * @param array<string,int> $house
     * @param array<int,list<string>> $occupants
     * @return list<array<string,mixed>>
     */
    private static function drishtiReadings(array $house, array $occupants): array
    {
        $bd = LalKitabData::section('bhav_drishti');
        $planetsInHi = static function (array $hs) use ($occupants): array {
            $r = [];
            foreach ($hs as $hh) {
                foreach ($occupants[$hh] ?? [] as $pl) {
                    $r[] = LalKitabData::planetHi($pl) . ' (' . LalKitabData::houseOrdinalHi((int) $hh) . ')';
                }
            }
            return $r;
        };
        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            if (empty($occupants[$h])) { continue; }
            $e = $bd[(string) $h] ?? [];
            $out[] = [
                'house'      => $h,
                'house_ord'  => LalKitabData::houseOrdinalHi($h),
                'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $occupants[$h]),
                'drishti'    => $e['drishti'] ?? [],
                'drishti_p'  => $planetsInHi($e['drishti'] ?? []),
                'sahayak'    => $e['sahayak'] ?? [],
                'sahayak_p'  => $planetsInHi($e['sahayak'] ?? []),
                'takrav'     => $e['takrav'] ?? [],
                'takrav_p'   => $planetsInHi($e['takrav'] ?? []),
            ];
        }
        return $out;
    }

    /**
     * Reference chakras — life-stage (अवस्था), minor-chart helper (अवयस्क), house
     * month, planet significators, rashi relations and establishment remedies.
     * The native's current अवस्था + अवयस्क row are flagged from their age.
     *
     * @return array<string,mixed>
     */
    private static function referenceReadings(?int $age): array
    {
        // Which अवस्था covers this age? (stages are 25-year bands.)
        $avastha = LalKitabData::section('avastha');
        $curStage = null;
        if ($age !== null && $age >= 1) {
            $idx = intdiv(max(0, $age - 1), 25);   // 1-25→0, 26-50→1 …
            if ($idx > 3) { $idx = 3; }
            $curStage = $avastha[$idx]['avastha'] ?? null;
        }
        $avyask = LalKitabData::section('avyask');
        $curAvyask = ($age !== null) ? ($avyask[(string) $age] ?? null) : null;

        // planet significators / establishment objects, in canonical order
        $vastu = LalKitabData::section('grah_vastu');
        $sthapana = LalKitabData::section('sthapana_vastu');
        $planets = [];
        foreach (self::PLANETS as $p) {
            if (isset($vastu[$p]) || isset($sthapana[$p])) {
                $planets[] = [
                    'hi'       => LalKitabData::planetHi($p),
                    'vastu'    => $vastu[$p] ?? '',
                    'sthapana' => $sthapana[$p] ?? '',
                ];
            }
        }

        return [
            'avastha'       => $avastha,
            'cur_stage'     => $curStage,
            'avyask'        => $avyask,
            'cur_avyask'    => $curAvyask,
            'age'           => $age,
            'bhav_maas'     => LalKitabData::section('bhav_maas'),
            'grah_rashi'    => LalKitabData::section('grah_rashi'),
            'bhav_sthapana' => LalKitabData::section('bhav_sthapana'),
            'planets'       => $planets,
        ];
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
