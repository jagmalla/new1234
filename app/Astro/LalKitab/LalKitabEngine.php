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

    /** @var array<string,int> planet => LK house, for the note-clause parser's
     *  relative-house ("X के Nवें में") checks. Set by planetReadings(). */
    private static array $noteHouses = [];

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

        // Build the interdependent readings in order: sleeping-state + special
        // conjunction doshas first (the per-planet analysis consumes both) →
        // planets → priority score (uses the running dasha / age context) →
        // the "active now" summary strip.
        $supt      = self::suptReadings($house);
        $yutiDosha = self::yutiDoshaReadings($occupants);
        $planets   = self::planetReadings($chart, $house, $occupants, $supt, $yutiDosha);
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
            'houses'      => self::houseReadings($house, $occupants, $planets),
            'karak'       => self::karakReadings($house, $planets),
            'yoga'        => self::yogaReadings($house),
            'shrap'       => self::shrapReadings($house),
            'sadesati'    => self::sadeSatiReadings($moonSign, $active),
            'manglik'     => self::manglikReadings($chart, $lagnaSign, $house),
            'remedy'      => self::remedyReadings($house, $occupants),
            'ayu'         => self::ayuReadings($house, $occupants, $chart),
            'health'      => self::healthReadings($planets),
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
    private static function planetReadings(array $chart, array $house, array $occupants = [], array $supt = [], array $yutiDosha = []): array
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
        $bd  = LalKitabData::section('bhav_drishti');

        // lookups shared by the per-planet analysis
        $awake = [];   // planet-en => bool
        foreach ($supt as $s) {
            foreach (LalKitabData::PLANET_HI as $en => $hiName) {
                if ($hiName === $s['hi']) { $awake[$en] = !empty($s['awake']); }
            }
        }
        $doshaOf = [];   // planet-en => list of dosha names it participates in
        foreach ($yutiDosha as $dEntry) {
            foreach ($dEntry['planets'] as $dp) { $doshaOf[$dp][] = $dEntry['name']; }
        }
        // "संबंध" (Lal Kitab) = same house OR a भाव-दृष्टि link either way.
        $sambandh = static function (string $a, string $b) use ($house, $bd): ?string {
            $ha = $house[$a] ?? null;
            $hb = $house[$b] ?? null;
            if ($ha === null || $hb === null) { return null; }
            if ($ha === $hb) { return 'एक ही भाव में युति'; }
            if (in_array($hb, $bd[(string) $ha]['drishti'] ?? [], true)) {
                return LalKitabData::houseOrdinalHi($ha) . ' भाव की दृष्टि ' . LalKitabData::houseOrdinalHi($hb) . ' पर';
            }
            if (in_array($ha, $bd[(string) $hb]['drishti'] ?? [], true)) {
                return LalKitabData::planetHi($b) . ' (' . LalKitabData::houseOrdinalHi($hb) . ') की दृष्टि इस भाव पर';
            }
            return null;
        };

        self::$noteHouses = $house;   // for the note parser's relative-house checks

        $out = [];
        foreach (self::PLANETS as $p) {
            if (!isset($house[$p])) {
                continue;
            }
            $h    = $house[$p];
            $sIdx = (int) ($chart['planets'][$p]['sign_index'] ?? 0);
            $retro = !empty($chart['planets'][$p]['retro']);

            // 1) classification against the real rashi
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
            $pukka = in_array($h, LalKitabData::PUKKA_GHAR[$p] ?? [], true);

            // 2) युति — house-mates with their मैत्री relation to this planet.
            $mates = [];
            $mitraTxt = (string) ($mt[$p]['mitra'] ?? '');
            $shatruTxt = (string) ($mt[$p]['shatru'] ?? '');
            foreach (($occupants[$h] ?? []) as $om) {
                if ($om === $p) { continue; }
                $omHi = LalKitabData::planetHi($om);
                $rel = 'सम';
                if ($omHi !== '' && mb_strpos($mitraTxt, $omHi) !== false) { $rel = 'मित्र'; }
                elseif ($omHi !== '' && mb_strpos($shatruTxt, $omHi) !== false) { $rel = 'शत्रु'; }
                $mates[] = ['planet' => $om, 'hi' => $omHi, 'rel' => $rel];
            }
            $alone = $mates === [];

            // 3) दृष्टि (भाव-दृष्टि चक्र) — who affects this planet, whom it affects.
            $e = $bd[(string) $h] ?? [];
            $inHits = [];   // planets whose house sees / clashes with this house
            foreach ($occupants as $h2 => $ps2) {
                if ($h2 === $h || $ps2 === []) { continue; }
                $e2 = $bd[(string) $h2] ?? [];
                $kind = null;
                if (in_array($h, $e2['takrav'] ?? [], true)) { $kind = 'टकराव'; }
                elseif (in_array($h, $e2['drishti'] ?? [], true)) { $kind = 'दृष्टि'; }
                elseif (in_array($h, $e2['sahayak'] ?? [], true)) { $kind = 'सहायता'; }
                if ($kind !== null) {
                    $inHits[] = ['kind' => $kind, 'house' => $h2,
                        'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $ps2)];
                }
            }
            $outHits = [];   // occupied houses this planet's house sees / clashes with
            foreach (['drishti' => 'दृष्टि', 'takrav' => 'टकराव', 'sahayak' => 'सहायता'] as $keyK => $kindHi) {
                foreach (($e[$keyK] ?? []) as $ht) {
                    if (!empty($occupants[$ht])) {
                        $outHits[] = ['kind' => $kindHi, 'house' => $ht,
                            'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $occupants[$ht])];
                    }
                }
            }

            // 4) निष्कर्ष — additive verdict with every reason recorded. The
            //    house lists switch to the "अदृष्ट अकेला" columns when alone.
            $shubhSrc = ($alone && trim((string) ($sab[$p]['shubh_alone'] ?? '')) !== '')
                ? (string) $sab[$p]['shubh_alone'] : (string) ($sab[$p]['shubh'] ?? '');
            $ashubhSrc = ($alone && trim((string) ($sab[$p]['ashubh_alone'] ?? '')) !== '')
                ? (string) $sab[$p]['ashubh_alone'] : (string) ($sab[$p]['ashubh'] ?? '');
            $shubhList  = self::parseHouses($shubhSrc);
            $ashubhList = self::parseHouses($ashubhSrc);
            $v = 0;
            $vWhy = [];
            if (in_array($h, $shubhList, true)) { $v++; $vWhy[] = ($alone ? 'अकेला — ' : '') . $h . 'वाँ भाव इस ग्रह हेतु शुभ (+)'; }
            elseif (in_array($h, $ashubhList, true)) { $v--; $vWhy[] = ($alone ? 'अकेला — ' : '') . $h . 'वाँ भाव इस ग्रह हेतु अशुभ (−)'; }
            if ($status === 'उच्च') { $v++; $vWhy[] = 'उच्च राशि (+)'; }
            elseif ($status === 'नीच') { $v--; $vWhy[] = 'नीच राशि (−)'; }
            elseif ($status === 'स्वगृही') { $v++; $vWhy[] = 'स्वगृही (+)'; }
            if ($pukka) { $v++; $vWhy[] = 'पक्का घर (+)'; }
            foreach ($mates as $mEntry) {
                if ($mEntry['rel'] === 'मित्र') { $v++; $vWhy[] = $mEntry['hi'] . ' (मित्र) साथ (+)'; }
                elseif ($mEntry['rel'] === 'शत्रु') { $v--; $vWhy[] = $mEntry['hi'] . ' (शत्रु) साथ (−)'; }
            }
            foreach (($doshaOf[$p] ?? []) as $dn) { $v--; $vWhy[] = $dn . ' (−)'; }
            foreach ($inHits as $ih) {
                if ($ih['kind'] === 'टकराव') { $v--; $vWhy[] = $ih['house'] . 'वें भाव (' . implode(', ', $ih['planets_hi']) . ') से टकराव (−)'; }
            }
            $isAsleep = isset($awake[$p]) && $awake[$p] === false;
            if ($isAsleep) { $vWhy[] = 'ग्रह सुप्त — फल दबा रहेगा'; }

            $verdict = $v > 0 ? 'शुभ' : ($v < 0 ? 'अशुभ' : 'मध्यम');
            if ($isAsleep && $verdict === 'शुभ') { $verdict = 'मध्यम'; }
            $isAshubh = $verdict === 'अशुभ';
            $isShubh = $verdict === 'शुभ';

            // 5) टिप्पणी के "अगर-तो" नियम — इस कुंडली पर जाँचे हुए: केवल लागू
            //    वाले मुख्य फल बनते हैं, शेष संदर्भ में जाते हैं।
            [$notesApplied, $notesRef] = self::noteClauses(
                (string) ($sab[$p]['note'] ?? ''), $p, $h, $alone, $isAshubh, $isShubh, $sambandh
            );

            $needRemedy = $isAshubh || $status === 'नीच' || ($doshaOf[$p] ?? []) !== [];
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
                'verdict_why' => $vWhy,
                'is_ashubh' => $isAshubh,
                'pukka'     => $pukka,
                'alone'     => $alone,
                'mates'     => $mates,
                'dosha'     => array_values($doshaOf[$p] ?? []),
                'asleep'    => $isAsleep,
                'in_hits'   => $inHits,
                'out_hits'  => $outHits,
                'notes_applied' => $notesApplied,
                'notes_ref' => $notesRef,
                'need_remedy' => $needRemedy,
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
    private static function houseReadings(array $house, array $occupants, array $planets = []): array
    {
        $bv = LalKitabData::section('bhav_vichar');
        $bd = LalKitabData::section('bhav_drishti');
        $bm = LalKitabData::section('bhav_maas');
        $bs = LalKitabData::section('bhav_sthapana');
        $sv = LalKitabData::section('sthapana_vastu');
        $sh = LalKitabData::section('sheeghra');

        // planet-key => computed planet analysis (verdict etc.) from planetReadings
        $pMap = [];
        foreach ($planets as $pe) { $pMap[$pe['planet']] = $pe; }
        // Hindi waker name (सुप्त भाव चक्र / भाव विचार) => planet key
        $hiToEn = array_flip(LalKitabData::PLANET_HI);

        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            $lord = LalKitabData::HOUSE_LORD[$h];
            $lordE = $pMap[$lord] ?? null;

            // 1) स्थित ग्रह — each with its computed verdict.
            $occ = [];
            foreach ($occupants[$h] as $op) {
                $occ[] = [
                    'planet' => $op,
                    'hi' => LalKitabData::planetHi($op),
                    'verdict' => $pMap[$op]['verdict'] ?? 'मध्यम',
                    'asleep' => !empty($pMap[$op]['asleep']),
                ];
            }

            // 2) जागृत / सुप्त भाव — occupied house is awake; an empty house wakes
            //    only if its जगाने-वाला ग्रह sits in / aspects it (भाव-दृष्टि).
            $wakerHi = trim((string) ($bv[(string) $h]['jagane'] ?? ''));
            $wakerEn = $hiToEn[$wakerHi] ?? null;
            $awakeBy = null;
            if ($occ !== []) {
                $awakeBy = 'भाव में ग्रह स्थित';
            } elseif ($wakerEn !== null && isset($house[$wakerEn])) {
                $wh = $house[$wakerEn];
                if (in_array($h, $bd[(string) $wh]['drishti'] ?? [], true)) {
                    $awakeBy = $wakerHi . ' (' . LalKitabData::houseOrdinalHi($wh) . ') की दृष्टि इस भाव पर';
                }
            }
            $isAwake = $awakeBy !== null;

            // 3) दृष्टि-प्रभाव — incoming from occupied houses (टकराव / सहायता /
            //    दृष्टि + the chakra's विश्वासघात / अचानक-चोट warning columns).
            $inHits = [];
            foreach ($occupants as $h2 => $ps2) {
                if ($h2 === $h || $ps2 === []) { continue; }
                $e2 = $bd[(string) $h2] ?? [];
                $kind = null;
                if (in_array($h, $e2['takrav'] ?? [], true)) { $kind = 'टकराव'; }
                elseif (in_array($h, $e2['sahayak'] ?? [], true)) { $kind = 'सहायता'; }
                elseif (in_array($h, $e2['drishti'] ?? [], true)) { $kind = 'दृष्टि'; }
                if ($kind !== null) {
                    $inHits[] = ['kind' => $kind, 'house' => $h2,
                        'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $ps2)];
                }
            }
            $warn = [];
            $eH = $bd[(string) $h] ?? [];
            foreach (['vishwasghat' => 'विश्वासघात की आशंका', 'achanak_chot' => 'अचानक चोट/हानि की आशंका'] as $wk => $wl) {
                $whs = array_values(array_filter($eH[$wk] ?? [], static fn ($x) => !empty($occupants[$x])));
                if ($whs !== []) {
                    $warn[] = $wl . ' — ' . implode(', ', array_map(
                        static fn ($x) => $x . 'वें (' . implode(', ', array_map([LalKitabData::class, 'planetHi'], $occupants[$x])) . ')', $whs
                    )) . ' से';
                }
            }

            // 4) निष्कर्ष — additive verdict with reasons.
            $v = 0;
            $why = [];
            foreach ($occ as $oe) {
                if ($oe['verdict'] === 'शुभ') { $v++; $why[] = $oe['hi'] . ' शुभ स्थिति में (+)'; }
                elseif ($oe['verdict'] === 'अशुभ') { $v--; $why[] = $oe['hi'] . ' अशुभ स्थिति में (−)'; }
            }
            if ($lordE !== null) {
                if ($lordE['verdict'] === 'शुभ') { $v++; $why[] = 'भाव-स्वामी ' . LalKitabData::planetHi($lord) . ' शुभ (+)'; }
                elseif ($lordE['verdict'] === 'अशुभ') { $v--; $why[] = 'भाव-स्वामी ' . LalKitabData::planetHi($lord) . ' अशुभ (−)'; }
                if (!empty($lordE['asleep'])) { $why[] = 'भाव-स्वामी सुप्त'; }
            }
            foreach ($inHits as $ih) {
                if ($ih['kind'] === 'टकराव') { $v--; $why[] = $ih['house'] . 'वें (' . implode(', ', $ih['planets_hi']) . ') से टकराव (−)'; }
                elseif ($ih['kind'] === 'सहायता') { $v++; $why[] = $ih['house'] . 'वें (' . implode(', ', $ih['planets_hi']) . ') से सहायता (+)'; }
            }
            if (!$isAwake) { $why[] = 'भाव सुप्त — विषय दबे रहेंगे'; }
            $verdict = $v > 0 ? 'शुभ' : ($v < 0 ? 'अशुभ' : 'मध्यम');
            if (!$isAwake && $verdict === 'शुभ') { $verdict = 'मध्यम'; }

            // 5) उपाय — only when the house needs strengthening.
            $needRemedy = $verdict === 'अशुभ' || !$isAwake;
            $remedies = [];
            if ($needRemedy) {
                $est = trim((string) ($bs[(string) $h] ?? ''));
                if ($est !== '') {
                    $vastu = trim((string) ($sv[$lord] ?? ''));
                    $remedies[] = 'भाव-स्थापना: ' . $est
                        . ($vastu !== '' ? ' (स्वामी ' . LalKitabData::planetHi($lord) . ' की वस्तु: ' . $vastu . ')' : '');
                }
                if (!$isAwake && $wakerEn !== null && !empty($sh[$wakerEn])) {
                    $remedies[] = 'भाव जगाने हेतु ' . $wakerHi . ' का शीघ्र उपाय: ' . $sh[$wakerEn];
                }
            }

            $out[$h] = [
                'house'      => $h,
                'house_ord'  => LalKitabData::houseOrdinalHi($h),
                'rashi'      => $bv[(string) $h]['rashi'] ?? '',
                'swami'      => $bv[(string) $h]['swami'] ?? '',
                'vishay'     => $bv[(string) $h]['vishay'] ?? '',
                'lord'       => $lord,
                'lord_hi'    => LalKitabData::planetHi($lord),
                'lord_house' => $house[$lord] ?? null,   // where this house-lord sits (LK)
                'lord_verdict' => $lordE['verdict'] ?? null,
                'planets'    => $occupants[$h],
                'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $occupants[$h]),
                'occ'        => $occ,
                'awake'      => $isAwake,
                'awake_by'   => $awakeBy,
                'waker_hi'   => $wakerHi,
                'in_hits'    => $inHits,
                'warn'       => $warn,
                'verdict'    => $verdict,
                'verdict_why' => $why,
                'maas'       => $bm[(string) $h] ?? '',
                'need_remedy' => $needRemedy,
                'remedies'   => $remedies,
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
    private static function karakReadings(array $house, array $planets = []): array
    {
        $gp = LalKitabData::section('graha_parichay');
        $bg = LalKitabData::section('bhavgat_upay');
        $sh = LalKitabData::section('sheeghra');
        $pMap = [];
        foreach ($planets as $pe) { $pMap[$pe['planet']] = $pe; }

        // build house => list of karak planets from the karak_bhav column
        $byHouse = array_fill(1, 12, []);
        foreach ($gp as $p => $row) {
            foreach (self::parseHouses((string) ($row['karak_bhav'] ?? '')) as $kh) {
                if ($kh >= 1 && $kh <= 12) {
                    $byHouse[$kh][] = $p;
                }
            }
        }
        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            $karaks = [];
            foreach ($byHouse[$h] as $p) {
                $ph = $house[$p] ?? null;
                $pe = $pMap[$p] ?? null;
                $verdict = $pe['verdict'] ?? 'मध्यम';
                // कारक ग्रह अशुभ/नीच/सुप्त हो तो वह जिस भाव का कारक है उसका फल दुर्बल।
                $weak = $verdict === 'अशुभ' || ($pe['status'] ?? '') === 'नीच' || !empty($pe['asleep']);
                $remedies = [];
                if ($weak && $ph !== null) {
                    $remedies = array_slice($bg[$p][(string) $ph] ?? [], 0, 3);
                    if (!empty($sh[$p])) { $remedies[] = '⚡ शीघ्र: ' . $sh[$p]; }
                }
                $karaks[] = [
                    'planet'   => $p,
                    'hi'       => LalKitabData::planetHi($p),
                    'placed'   => $ph,
                    'placed_ord' => $ph ? LalKitabData::houseOrdinalHi($ph) : '',
                    'verdict'  => $verdict,
                    'asleep'   => !empty($pe['asleep']),
                    'weak'     => $weak,
                    'remedies' => array_values($remedies),
                ];
            }
            // house-level verdict = worst of its karaks
            $anyWeak = false; $allStrong = $karaks !== [];
            foreach ($karaks as $k) { if ($k['weak']) { $anyWeak = true; } if ($k['verdict'] !== 'शुभ') { $allStrong = false; } }
            $out[$h] = [
                'house'     => $h,
                'house_ord' => LalKitabData::houseOrdinalHi($h),
                'vishay'    => LalKitabData::section('bhav_vichar')[(string) $h]['vishay'] ?? '',
                'karaks'    => $karaks,
                'verdict'   => $anyWeak ? 'अशुभ' : ($allStrong ? 'शुभ' : 'मध्यम'),
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
                'tone'       => self::phalTone((string) ($r['phal'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * फल का शुभ/अशुभ स्वभाव — the yoga card's colour must follow the RESULT, not
     * merely "is it applicable". Scans the phal text for benefic vs malefic
     * cues; the last-mentioned polarity wins for "अशुभ … परन्तु … शुभ" style
     * sentences (Lal Kitab phrases the exception at the end). Returns
     * 'pos' | 'neg' | 'mix'.
     */
    private static function phalTone(string $phal): string
    {
        if (trim($phal) === '') { return 'mix'; }
        // Negated malefic clauses ("अशुभ … नहीं होता", "हानि नहीं करेगा") actually
        // read benefic — drop them so they neither count as neg nor let their
        // शुभ/उच्च leak in. Mask before scanning.
        $work = preg_replace('/(हानि|अशुभ|नीच|कष्ट|रोग|दोष|कुप्रभाव|बुरा)[^।;]{0,20}?नहीं\s*(होता|होती|करता|करती|करेगा|करेगी|देता|देती|देगा|देगी|पाता|पाती|पड़ता|पड़ती)/u', ' शुभ ', $phal) ?? $phal;
        // Mask "अशुभ" so the pos cue "शुभ" cannot match inside it.
        $posText = str_replace('अशुभ', 'अ✕', $work);

        // strong malefic cues
        $neg = ['हानि', 'अशुभ', 'नीच', 'कष्ट', 'रोग', 'दुःख', 'दुख', 'मरते', 'मरवा', 'मृत्यु', 'मौत',
            'विष', 'नाश', 'भारी होता', 'कुप्रभाव', 'दरिद्र', 'निर्धन', 'शत्रु', 'बाधा', 'दोष', 'भय',
            'दुर्घटना', 'ग्रहण की स्थिति', 'अन्धे', 'मौन', 'रतान्ध', 'हानिकारक', 'बुरा', 'पीड़ा', 'विकार'];
        // strong benefic cues
        $pos = ['शुभ', 'उच्च', 'लाभ', 'धनी', 'राजा', 'सुख', 'उन्नति', 'वृद्धि', 'सफल', 'यश', 'कीर्ति',
            'सम्मान', 'रक्षा', 'कल्याण', 'समृद्ध', 'भाग्य', 'उत्तम', 'श्रेष्ठ', 'सुखी'];

        // find the last occurrence of any cue on each side → later wins.
        $lastNeg = -1; $lastPos = -1; $nHits = 0; $pHits = 0;
        foreach ($neg as $w) { $i = mb_strrpos($work, $w); if ($i !== false) { $nHits++; if ($i > $lastNeg) { $lastNeg = $i; } } }
        foreach ($pos as $w) { $i = mb_strrpos($posText, $w); if ($i !== false) { $pHits++; if ($i > $lastPos) { $lastPos = $i; } } }

        if ($nHits === 0 && $pHits === 0) { return 'mix'; }
        if ($nHits > 0 && $pHits === 0) { return 'neg'; }
        if ($pHits > 0 && $nHits === 0) { return 'pos'; }
        // both present ("अशुभ … परन्तु … शुभ" / "शुभ … किन्तु … हानि"): later wins.
        return $lastPos > $lastNeg ? 'pos' : 'neg';
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
            if (!empty($pl['is_ashubh'])) { $score += 2; $reasons[] = 'लाल-किताब निष्कर्ष अशुभ (+2)'; }
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
     * श्राप / पैतृक ऋण — the nine ancestral debts, detected EXACTLY from the
     * baked {houses,planets} condition of each debt: the debt is present when
     * any listed planet actually sits in any listed house of THIS chart. The
     * matched planet+house are reported so the astrologer sees why.
     *
     * @param array<string,int> $house
     * @return list<array<string,mixed>>
     */
    private static function shrapReadings(array $house): array
    {
        $rin = LalKitabData::section('paitrik_rin');
        $out = [];
        foreach ($rin as $r) {
            $cond = $r['cond'] ?? ['houses' => [], 'planets' => []];
            $matched = [];
            foreach (($cond['planets'] ?? []) as $cp) {
                $ph = $house[$cp] ?? null;
                if ($ph !== null && in_array($ph, $cond['houses'] ?? [], true)) {
                    $matched[] = LalKitabData::planetHi($cp) . ' ' . LalKitabData::houseOrdinalHi($ph) . ' भाव में';
                }
            }
            $present = $matched !== [];
            $out[] = [
                'rin'         => (string) ($r['rin'] ?? ''),
                'pehchan'     => (string) ($r['pehchan'] ?? ''),
                'ashubh_grah' => (string) ($r['ashubh_grah'] ?? ''),
                'sanket'      => (string) ($r['sanket'] ?? ''),
                'ashubh_phal' => (string) ($r['ashubh_phal'] ?? ''),
                'upay'        => (string) ($r['upay'] ?? ''),
                'present'     => $present,           // exact chart match
                'matched'     => $matched,           // "राहु पाँचवें भाव में" …
            ];
        }
        return $out;
    }

    /**
     * साढ़े साती + ढैय्या for the janma-rashi (Moon sign).
     *
     * @return array<string,mixed>
     */
    private static function sadeSatiReadings(int $moonSign, array $active = []): array
    {
        $ssp = LalKitabData::section('sadesati_pehchan');
        $ssu = LalKitabData::section('sadesati_upay');
        $dhp = LalKitabData::section('dhaiya_pehchan');
        $dhu = LalKitabData::section('dhaiya_upay');
        $k = (string) $moonSign;

        // running-now status from the controller's SadeSatiTimeline (via $active)
        $ss = $active['sadesati'] ?? null;
        $runningKind = is_array($ss) ? (string) ($ss['kind'] ?? '') : '';   // 'sadesati'|'dhaiya'|''
        $runningPhase = is_array($ss) ? ($ss['phase'] ?? null) : null;       // 1|2|3|null

        return [
            'rashi_hi'        => LalKitabData::signHi(Charts::SIGNS[$moonSign]),
            'pehchan'         => $ssp[$k]['shani_on'] ?? '',
            'note'            => $ssp[$k]['note'] ?? '',
            'upay'            => array_values($ssu[$k] ?? []),
            'dhaiya_pehchan'  => $dhp[$k]['shani_on'] ?? '',
            'dhaiya_upay'     => array_values($dhu[$k] ?? []),
            'running'         => $runningKind !== '',      // is Saturn transiting now?
            'running_kind'    => $runningKind === 'dhaiya' ? 'ढैय्या' : ($runningKind === 'sadesati' ? 'साढ़े साती' : ''),
            'running_phase'   => $runningPhase,            // charan index for साढ़े साती
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
        // house-wise meaning of the offending Mars placement (1 शरीर · 4 सुख ·
        // 7 दाम्पत्य · 8 आयु · 12 व्यय) — so the card is specific, not generic.
        static $mhMean = [
            1 => 'शरीर, स्वभाव व स्वास्थ्य पर', 4 => 'सुख, माता, भूमि-वाहन व घरेलू शांति पर',
            7 => 'दाम्पत्य, जीवनसाथी व साझेदारी पर', 8 => 'आयु, दुर्घटना व अकस्मात बाधाओं पर',
            12 => 'व्यय, शयन-सुख व विदेश पर',
        ];
        return [
            'is'         => $isManglik,
            'mars_house' => $mh,
            'mars_ord'   => $mh ? LalKitabData::houseOrdinalHi($mh) : '',
            'mars_effect'=> $isManglik ? ($mhMean[$mh] ?? '') : '',
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
     * आयु योग (longevity) — which आयु-योग actually apply in THIS chart. Each
     * ायु rule is "<ग्रह> <किसी/named ग्रह> के साथ" — a conjunction. A rule fires
     * when its planet shares a house with the required companion (or any planet
     * for "किसी भी ग्रह के साथ"). Applicable ones are flagged; the reference list
     * + per-planet influence-year chart follow.
     *
     * @param array<string,int> $house
     * @param array<int,list<string>> $occupants
     * @param array<string,mixed> $chart
     * @return array<string,mixed>
     */
    private static function ayuReadings(array $house = [], array $occupants = [], array $chart = []): array
    {
        // house-mates for each planet
        $mates = [];
        foreach ($house as $p => $h) {
            $mates[$p] = array_values(array_filter($occupants[$h] ?? [], static fn ($x) => $x !== $p));
        }
        // मंगल शुभ = own sign or exalted; else अशुभ (Lal Kitab longevity variant).
        $isMarsShubh = static function () use ($chart): bool {
            $s = (int) ($chart['planets']['Mars']['sign_index'] ?? -1);
            if ($s < 0) { return true; }
            return $s === (LalKitabData::EXALT['Mars'] ?? -1) || Charts::SIGN_LORDS[$s] === 'Mars';
        };

        $yogaOut = [];
        foreach (LalKitabData::section('ayu_yog') as $r) {
            $yog = (string) ($r['yog'] ?? '');
            // lead planet = first planet name in the rule text
            $lead = null; $others = [];
            foreach (LalKitabData::PLANET_HI as $en => $hiName) {
                if (mb_strpos($yog, $hiName) !== false) {
                    if ($lead === null) { $lead = $en; } else { $others[] = $en; }
                }
            }
            $applies = false; $why = '';
            if ($lead !== null && isset($house[$lead])) {
                $companions = $mates[$lead] ?? [];
                if (mb_strpos($yog, 'किसी भी ग्रह') !== false) {
                    // "with any planet" — but a Mars शुभ/अशुभ variant must match sign
                    $variantOk = true;
                    if (mb_strpos($yog, 'शुभ') !== false) { $variantOk = $isMarsShubh(); }
                    elseif (mb_strpos($yog, 'अशुभ') !== false) { $variantOk = !$isMarsShubh(); }
                    if ($companions !== [] && $variantOk) { $applies = true; $why = LalKitabData::planetHi($lead) . ' के साथ ' . LalKitabData::planetHi($companions[0]); }
                } elseif ($others !== []) {
                    foreach ($others as $o) {
                        if (in_array($o, $companions, true)) { $applies = true; $why = LalKitabData::planetHi($lead) . '-' . LalKitabData::planetHi($o) . ' युति'; break; }
                    }
                }
            }
            $yogaOut[] = ['yog' => $yog, 'ayu' => (string) ($r['ayu'] ?? ''), 'applies' => $applies, 'why' => $why];
        }

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
        return ['yoga' => $yogaOut, 'chakra' => $chakra];
    }

    /**
     * रोग / संतान — the mixed disease & progeny remedies, plus a chart-context
     * note: which planets are अशुभ (whose रोग may need attention) so the section
     * is not purely generic.
     *
     * @param list<array<string,mixed>> $planets
     * @return array<string,mixed>
     */
    private static function healthReadings(array $planets = []): array
    {
        $groups = [];
        foreach (LalKitabData::section('rog_santan') as $r) {
            $varg = (string) ($r['varg'] ?? 'अन्य');
            $groups[$varg][] = (string) ($r['upay'] ?? '');
        }
        // planets that are अशुभ → their रोग-areas need care (from ग्रह परिचय रंग/कारक)
        $afflicted = [];
        foreach ($planets as $pe) {
            if (!empty($pe['is_ashubh'])) {
                $afflicted[] = ['hi' => $pe['hi'], 'house_ord' => $pe['house_ord']];
            }
        }
        return ['groups' => $groups, 'afflicted' => $afflicted];
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

    /**
     * टिप्पणी (शुभ-अशुभ भाव चक्र) के "अगर-तो" वाक्यों को अलग कर इस कुंडली पर
     * जाँचता है। Conditions understood per clause (ANDed):
     *   "अकेला"                → planet has no house-mate;
     *   "<ग्रह> से संबंध/साथ"   → yuti or a भाव-दृष्टि link with that planet;
     *   "किसी ग्रह के साथ संबंध" → not alone;
     *   "<भाव> में (हो/स्थित)"  → the planet sits in that house;
     *   "अशुभ हो(गा)"          → computed verdict is अशुभ.
     * A clause whose conditions hold goes to applied (with the reason); a
     * failing one to reference; a condition-free clause is general → applied.
     *
     * @return array{0:list<array{text:string,why:string}>,1:list<string>}
     */
    private static function noteClauses(string $note, string $p, int $h, bool $alone, bool $isAshubh, bool $isShubh, callable $sambandh): array
    {
        if (trim($note) === '') {
            return [[], []];
        }
        static $ordMap = [
            'पहले' => 1, 'दूसरे' => 2, 'तीसरे' => 3, 'चौथे' => 4, 'पांचवें' => 5, 'पाँचवें' => 5,
            'छठे' => 6, 'छठें' => 6, 'सातवें' => 7, 'आठवें' => 8, 'नौवें' => 9, 'दसवें' => 10,
            'ग्यारहवें' => 11, 'बारहवें' => 12,
        ];
        $applied = [];
        $ref = [];
        foreach (preg_split('/\s*[;।]\s*/u', $note) ?: [] as $cl) {
            $cl = trim($cl);
            if ($cl === '') { continue; }
            $conds = 0;
            $ok = true;
            $why = [];

            if (mb_strpos($cl, 'अकेला') !== false) {
                $conds++;
                if ($alone) { $why[] = 'ग्रह अकेला बैठा है'; } else { $ok = false; }
            }
            if (preg_match('/अशुभ हो(गा)?/u', $cl)) {
                $conds++;
                if ($isAshubh) { $why[] = 'ग्रह अशुभ स्थिति में है'; } else { $ok = false; }
            }
            // relative-house condition — "<ग्रह> के Nवें (…) में" = the Nth house
            // counted FROM that planet's own house.
            $clH = $cl;   // copy with the possessive part removed for the plain scan
            if (preg_match('/(सूर्य|चन्द्र|मंगल|बुध|गुरु|शुक्र|शनि|राहु|केतु)\s*के\s*(\d{1,2}|पहले|दूसरे|तीसरे|चौथे|पाँचवें|पांचवें|छठें|छठे|सातवें|आठवें|नौवें|दसवें|ग्यारहवें|बारहवें)\s*(?:वें|वे|ठे|थे)?\s*(?:\([^)]*\)\s*)?(?:भाव\s*)?में/u', $cl, $mR)) {
                $conds++;
                $refP = null;
                foreach (LalKitabData::PLANET_HI as $en => $hiName) {
                    if ($hiName === $mR[1]) { $refP = $en; break; }
                }
                $n = ctype_digit($mR[2]) ? (int) $mR[2] : ($ordMap[$mR[2]] ?? 0);
                if ($refP !== null && $n >= 1 && $n <= 12 && isset(self::$noteHouses[$refP])) {
                    $target = ((self::$noteHouses[$refP] - 1 + ($n - 1)) % 12) + 1;
                    if ($h === $target) {
                        $why[] = $mR[1] . ' के ' . $n . 'वें = ' . LalKitabData::houseOrdinalHi($target) . ' भाव — यही स्थिति';
                    } else { $ok = false; }
                } else { $ok = false; }
                $clH = str_replace($mR[0], ' ', $cl);
            }
            // plain house condition — "… भाव में / …वें में / 1st में" (not "…भाव का फल")
            $hSet = [];
            if (preg_match_all('/(पहले|दूसरे|तीसरे|चौथे|पाँचवें|पांचवें|छठें|छठे|सातवें|आठवें|नौवें|दसवें|ग्यारहवें|बारहवें)\s*(?:भाव\s*)?में/u', $clH, $mW)) {
                foreach ($mW[1] as $w) { if (isset($ordMap[$w])) { $hSet[$ordMap[$w]] = true; } }
            }
            if (preg_match_all('/(\d{1,2})\s*(?:st|nd|rd|th|वें|वे|ठे|थे)?\s*(?:भाव\s*)?में/u', $clH, $mD)) {
                foreach ($mD[1] as $n) { $n = (int) $n; if ($n >= 1 && $n <= 12) { $hSet[$n] = true; } }
            }
            if ($hSet !== []) {
                $conds++;
                if (isset($hSet[$h])) { $why[] = 'यह ग्रह ' . LalKitabData::houseOrdinalHi($h) . ' भाव में है'; } else { $ok = false; }
            }
            // other-planet संबंध
            if (preg_match('/किसी ग्रह के साथ/u', $cl)) {
                $conds++;
                if (!$alone) { $why[] = 'साथ में अन्य ग्रह उपस्थित'; } else { $ok = false; }
            } elseif (preg_match('/संबंध|के साथ/u', $cl)) {
                foreach (LalKitabData::PLANET_HI as $en => $hiName) {
                    if ($en === $p || mb_strpos($cl, $hiName) === false) { continue; }
                    $conds++;
                    $s = $sambandh($p, $en);
                    if ($s !== null) { $why[] = $hiName . ' से संबंध (' . $s . ')'; } else { $ok = false; }
                    break;
                }
            }

            if ($conds === 0) {
                $applied[] = ['text' => $cl, 'why' => 'सामान्य नियम'];
            } elseif ($ok) {
                $applied[] = ['text' => $cl, 'why' => implode(' · ', $why)];
            } else {
                $ref[] = $cl;
            }
        }
        return [$applied, $ref];
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
