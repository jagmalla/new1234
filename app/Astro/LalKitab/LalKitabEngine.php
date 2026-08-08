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
     * Authentic Lal Kitab करें (do) / न करें (don't) per planet — the standard
     * conduct-based guidance that strengthens the planet's शुभ फल and averts its
     * अशुभ फल. Shown with every placement (§ do's-don'ts request).
     * @var array<string,array{do:list<string>,dont:list<string>}>
     */
    private const DO_DONT = [
        'Sun' => [
            'do' => ['पिता, बड़ों व सरकारी अधिकारियों का सम्मान करें', 'प्रत्येक कार्य कुछ मीठा खाकर व जल पीकर आरम्भ करें', 'सूर्य को जल दें, घर का मुख्य द्वार साफ़ व खुला रखें', 'आँख/हड्डी की देखभाल करें, नित्य दिनचर्या रखें'],
            'dont' => ['मुफ़्त/दान की वस्तुएँ न लें, तांबा दान में न लें', 'पिता/सरकार का अपमान न करें', 'मांस-मदिरा से दूर रहें', 'चारित्रिक शिथिलता न रखें'],
        ],
        'Moon' => [
            'do' => ['माता व वृद्ध स्त्रियों की सेवा करें', 'चाँदी/जल/चावल पास रखें, जल-स्रोत साफ़ रखें', 'सत्य बोलें, मन शान्त रखें', 'रात्रि में सिरहाने जल भरकर रखें व प्रातः पौधे में डालें'],
            'dont' => ['दूध/दही/चावल दान में न लें', 'जल-पात्र फोड़ें नहीं, जल व्यर्थ न बहाएँ', 'माता का अपमान न करें', 'घर अँधेरा/गन्दा न रखें'],
        ],
        'Mars' => [
            'do' => ['भाइयों से मेल रखें, साहस-धैर्य से काम लें', 'मीठा बाँटें, रक्तदान/भूमि-सेवा करें', 'हनुमान-उपासना करें'],
            'dont' => ['क्रोध व झगड़े से बचें, अस्त्र-शस्त्र से सावधानी', 'रक्त/अग्नि से जोखिम न लें', 'भाइयों से भूमि-विवाद न करें'],
        ],
        'Mercury' => [
            'do' => ['बुआ/बहन/बेटी का सम्मान व सहायता करें', 'हरी वस्तु/पालक रखें, वाणी मधुर रखें', 'गाय को हरा चारा दें'],
            'dont' => ['किसी की निंदा/चुगली न करें', 'हरी वस्तु का दान न लें', 'झूठ व धोखा न करें'],
        ],
        'Jupiter' => [
            'do' => ['गुरु/बड़ों/ब्राह्मण का सम्मान करें, धर्म-कर्म करें', 'केसर/हल्दी का तिलक, पीली वस्तु रखें', 'मन्दिर व ज्ञान में दान करें'],
            'dont' => ['धर्म/गुरु का अपमान न करें', 'सोना/पीली वस्तु का दान न लें', 'अहंकार व कुसंग से बचें'],
        ],
        'Venus' => [
            'do' => ['पत्नी/स्त्री-वर्ग का सम्मान करें, स्वच्छता रखें', 'गाय की सेवा करें, सुगन्ध-सौन्दर्य बनाए रखें'],
            'dont' => ['चारित्रिक शिथिलता व व्यसन से बचें', 'स्त्री का अपमान न करें', 'दिखावे में अति न करें'],
        ],
        'Saturn' => [
            'do' => ['मज़दूर/गरीब/वृद्ध की सेवा करें, अनुशासन रखें', 'शनिवार तेल/उड़द/लोहा दान करें, न्यायपूर्ण रहें'],
            'dont' => ['किसी का हक़ न मारें, आलस्य न करें', 'मदिरा-मांस व असत्य से बचें', 'बुज़ुर्गों/सेवकों का अपमान न करें'],
        ],
        'Rahu' => [
            'do' => ['सिर ढककर रखें, स्वच्छता व सादगी रखें', 'ससुराल-पक्ष से मधुर सम्बन्ध, चींटी/कुत्ते को भोजन'],
            'dont' => ['छल-कपट व अनैतिक लाभ से बचें', 'नीली/काली अशुद्ध वस्तु से सावधानी', 'बिजली/जुए/नशे से दूर रहें'],
        ],
        'Ketu' => [
            'do' => ['कुत्ते/संतान की सेवा करें, आध्यात्मिक रहें', 'दो-रंगा कम्बल/कान छिदवाना लाभप्रद'],
            'dont' => ['संतान/श्वान का अनादर न करें', 'अकारण संदेह व भटकाव से बचें', 'पैतृक-वस्तु न बेचें'],
        ],
    ];

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
        $supt      = self::suptReadings($house, $occupants);
        $yutiDosha = self::yutiDoshaReadings($occupants);
        // सोए ग्रहों का सेट — एक बार, सबके फल बनने से पहले।
        // वजह: सोने का फ़ैसला दृष्टि पर टिका है, और दृष्टि का असर इस पर कि देखने
        // वाला सोया है या नहीं। दोनों एक-दूसरे का जवाब पहले माँगते हैं। इसलिए
        // पहले सिर्फ़ बैठक से नींद तय होती है (यह गणना किसी फल पर निर्भर नहीं),
        // फिर वह **जमा** दी जाती है — उसके बाद कोई पास उसे नहीं बदलता। वरना वही
        // कुंडली हर बार अलग पढ़ी जाएगी, बिना किसी कारण के।
        $asleepSet = self::asleepSet($house, $occupants);
        $planets   = self::planetReadings($chart, $house, $occupants, $supt, $yutiDosha, $age, $asleepSet);
        // मसनूई sits on top of the per-planet analysis: it needs each source
        // planet's own verdict to decide which of the two to send away.
        $masnui    = self::masnuiReadings($house, $occupants, $planets, $age);
        self::markMasnuiSources($planets, $masnui);
        self::scorePlanets($planets, $supt, $yutiDosha, $active, $age, $masnui);
        $priority  = self::priorityReadings($planets);
        $activeNow = self::activeReadings($house, $planets, $active, $age);
        $yoga      = self::yogaReadings($house);
        $inter     = self::interEffects($house, $yoga, $yutiDosha);
        // situation-based findings (computed once, reused by the remedy plan)
        $collisions = self::collisionReadings($occupants);
        $buniyad    = self::buniyadReadings($occupants, array_column($planets, 'status', 'planet'));
        $mukabla    = self::mukablaReading($occupants);
        $special    = self::specialStates($chart, $house, $planets);
        $situations = self::gatherSituations($collisions, $buniyad, $mukabla, $supt, $special);

        return [
            'ok'          => true,
            'error'       => null,
            'lagna_sign'  => $lagnaSign,
            'lagna_hi'    => LalKitabData::signHi(Charts::SIGNS[$lagnaSign]),
            'moon_sign'   => $moonSign,
            'moon_hi'     => LalKitabData::signHi(Charts::SIGNS[$moonSign]),
            'grid'        => $grid,
            'planets'     => $planets,
            'teva_kisam'  => self::tevaKisamReadings($house, $occupants),
            'karak_rishtedar' => LalKitabData::section('karak_rishtedar'),
            'general'     => self::generalOverview($planets),
            'age_cycle'   => self::ageCycle($occupants, $planets, $age),
            'active'      => $activeNow,
            'priority'    => $priority,
            'yuti_dosha'  => $yutiDosha,
            'masnui'      => $masnui,
            'collisions'  => $collisions,
            'buniyad'     => $buniyad,
            'mukabla'     => $mukabla,
            'houses'      => self::houseReadings($house, $occupants, $planets),
            'karak'       => self::karakReadings($house, $planets),
            'yoga'        => $yoga,
            'inter'       => $inter,
            'shrap'       => self::shrapReadings($house),
            'sadesati'    => self::sadeSatiReadings($moonSign, $active),
            'manglik'     => self::manglikReadings($chart, $lagnaSign, $house),
            'remedy'      => self::remedyReadings($house, $occupants),
            'remedy_plan' => self::remedyPlan($planets, $masnui, $situations),
            'ayu'         => self::ayuReadings($house, $occupants, $chart),
            'health'      => self::healthReadings($planets),
            'bhavan'      => LalKitabData::section('bhavan'),
            'varsh_gyan'  => self::varshGyanReadings($age),
            'rules'       => self::ruleReadings(),
            'supt'        => $supt,
            'special'     => $special,
            'varjit'      => self::varjitReadings($house),
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
    private static function planetReadings(array $chart, array $house, array $occupants = [], array $supt = [], array $yutiDosha = [], ?int $age = null, array $asleepSet = []): array
    {
        $sb  = LalKitabData::section('supt_bhav');     // भाव => चाबी (जगाने वाला ग्रह)
        $gp  = LalKitabData::section('graha_parichay');
        $sab = LalKitabData::section('shubh_ashubh_bhav');
        $un  = LalKitabData::section('uch_neech_niyam');
        $al  = LalKitabData::section('ashubh_lakshan');
        $bg  = LalKitabData::section('bhavgat_upay');
        $su  = LalKitabData::section('samanya_upay');
        $mt  = LalKitabData::section('maitri');
        $sh  = LalKitabData::section('sheeghra');
        $pd  = LalKitabData::section('puja_daan');
        $sg  = LalKitabData::section('supt_grah');    // awakening age + trigger
        $gc  = LalKitabData::section('grah_chakra');  // effect years / caution years
        $gbp = LalKitabData::section('grah_bhav_phal'); // per-planet × house nek/mandi phal

        // lookups shared by the per-planet analysis
        $doshaOf = [];   // planet-en => list of dosha names it participates in
        foreach ($yutiDosha as $dEntry) {
            foreach ($dEntry['planets'] as $dp) { $doshaOf[$dp][] = $dEntry['name']; }
        }
        // "संबंध" (Lal Kitab) = same house OR a भाव-दृष्टि link either way.
        $sambandh = static function (string $a, string $b) use ($house): ?string {
            $ha = $house[$a] ?? null;
            $hb = $house[$b] ?? null;
            if ($ha === null || $hb === null) { return null; }
            if ($ha === $hb) { return 'एक ही भाव में युति'; }
            if (isset(LalKitabData::DRISHTI[$ha][$hb])) {
                return LalKitabData::houseOrdinalHi($ha) . ' भाव की दृष्टि ' . LalKitabData::houseOrdinalHi($hb) . ' पर';
            }
            if (isset(LalKitabData::DRISHTI[$hb][$ha])) {
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

            // 1) house-based dignity — the teva rashi is fixed, so उच्च/नीच/स्वगृही
            //    come from the HOUSE, not the real gochar rashi. (Farman 4.)
            $dig      = LalKitabTeva::dignity($p, $h, $occupants);
            $status   = $dig['status'];
            $digTier  = $dig['tier'];       // 'गौण' for a secondary नीच
            $digBhang = $dig['bhang'];       // उच्च but no benefit
            $pukka    = $dig['pakka'];
            $kachcha  = $dig['kachcha'];

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

            // 3) दृष्टि व टक्कर — एकतरफ़ा लाल किताब मैट्रिक्स से।
            $inHits = [];   // कौन इस ग्रह पर असर डालता है
            foreach (LalKitabTeva::seenBy($h, $occupants) as $s) {
                $inHits[] = ['kind' => 'दृष्टि', 'house' => $s['house'], 'pct' => $s['pct'],
                    'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $s['planets'])];
            }
            $struck = LalKitabTeva::struckBy($h, $occupants);   // कौन (8वें से) इसे टक्कर मारता है
            if ($struck !== null) {
                $inHits[] = ['kind' => 'टकराव', 'house' => $struck['house'], 'pct' => 100,
                    'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $struck['planets'])];
            }
            // सोए ग्रह की दृष्टि/टक्कर आधी — जमे हुए सेट से (सेटिंग: reduced)
            $inHits = self::attenuate($inHits, $occupants, $asleepSet);
            $outHits = [];   // यह ग्रह किन पर असर डालता है
            foreach (LalKitabTeva::aspectsFrom($h) as $ht => $pct) {
                if (!empty($occupants[$ht])) {
                    $outHits[] = ['kind' => 'दृष्टि', 'house' => $ht, 'pct' => $pct,
                        'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $occupants[$ht])];
                }
            }
            $strikesHouse = LalKitabData::TAKKAR[$h] ?? null;   // यह किसे टक्कर मारता है
            if ($strikesHouse !== null && !empty($occupants[$strikesHouse])) {
                $outHits[] = ['kind' => 'टकराव', 'house' => $strikesHouse, 'pct' => 100,
                    'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $occupants[$strikesHouse])];
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
            if ($status === 'उच्च' && !$digBhang) { $v++; $vWhy[] = 'उच्च भाव (+)'; }
            elseif ($status === 'उच्च' && $digBhang) { $vWhy[] = 'उच्च भाव में — पर भंग, उच्च का शुभ फल नहीं'; }
            elseif ($status === 'नीच') { $v -= ($digTier === 'गौण' ? 1 : 1); $vWhy[] = 'नीच भाव' . ($digTier === 'गौण' ? ' (गौण)' : '') . ' (−)'; }
            elseif ($status === 'स्वगृही') { $v++; $vWhy[] = 'स्वगृही (+)'; }
            foreach ($mates as $mEntry) {
                if ($mEntry['rel'] === 'मित्र') { $v++; $vWhy[] = $mEntry['hi'] . ' (मित्र) साथ (+)'; }
                elseif ($mEntry['rel'] === 'शत्रु') { $v--; $vWhy[] = $mEntry['hi'] . ' (शत्रु) साथ (−)'; }
            }
            foreach (($doshaOf[$p] ?? []) as $dn) { $v--; $vWhy[] = $dn . ' (−)'; }
            foreach ($inHits as $ih) {
                if ($ih['kind'] !== 'टकराव') { continue; }
                // मारने वाला खुद सोया हो तो चोट पूरी नहीं पड़ती
                if (!empty($ih['weak'])) {
                    $vWhy[] = $ih['house'] . 'वें भाव (' . implode(', ', $ih['planets_hi'])
                        . ') से टकराव — पर मारने वाला खुद सोया है, चोट आधी';
                } else {
                    $v--;
                    $vWhy[] = $ih['house'] . 'वें भाव (' . implode(', ', $ih['planets_hi']) . ') से टकराव — यह ग्रह खराब होता है (−)';
                }
            }
            // पक्का घर = तीव्रता (वॉल्यूम बटन): जिधर फल झुका है उसे और गहरा करता है,
            // शुभता नहीं जोड़ता। कच्चा घर उल्टा — फल मंद करता है।
            if ($pukka && $v !== 0) { $v += $v > 0 ? 1 : -1; $vWhy[] = 'पक्का घर — असर पूरी ताकत से (' . ($v > 0 ? 'दुगुना शुभ' : 'दुगुना अशुभ') . ')'; }
            if ($kachcha && $v !== 0) { $v += $v > 0 ? -1 : 1; $vWhy[] = 'कच्चा घर — फल मंद/अधूरा'; }

            // सोया / गूंगा / बहरा / लंगड़ा / अंधा / मृत — एकतरफ़ा दृष्टि पर टिकी सीढ़ी
            $impair = LalKitabTeva::impairedState($p, $h, $occupants, $dig);
            $isAsleep = in_array($impair['code'], ['SOYA', 'GUNGA', 'ANDHA', 'MRIT'], true);
            if ($impair['state'] !== '') { $vWhy[] = $impair['state'] . ' ग्रह — ' . (LalKitabData::SITUATION[$impair['code']]['phal'] ?? 'फल दबा रहेगा'); }

            // नींद का रिकॉर्ड — कौन सुला रहा है, कौन जगाएगा (उपाय इसी पर निशाना लगाता है)
            $wake = self::wakeRecord($p, $h, $impair, $mates, $inHits, $age, $sg, $sb);

            // ---- फ़ैसले के गेट — जोड़-घटा नहीं, क्रम से पूछे गए सवाल ----
            // गेट 0 — क्या ग्रह काम कर भी सकता है? सोया + कोई जगाने वाला नहीं = निष्क्रिय।
            // निष्क्रिय ≠ मध्यम: यह "कुछ होता ही नहीं" है, "ठीक-ठाक" नहीं। आगे कोई गेट
            // नहीं पूछा जाता, और उपाय की दिशा उलट जाती है — शांति नहीं, जगाना।
            $decidedAtGate = 5;
            if ($isAsleep && !$wake['awake']) {
                $verdict = 'निष्क्रिय';
                $decidedAtGate = 0;
                $vWhy[] = 'गेट-0: ' . ($impair['state'] ?: 'सोया') . ' ग्रह और जगाने की शर्त अभी पूरी नहीं — '
                    . 'इसके मामले आगे नहीं बढ़ेंगे (यह बुरा फल नहीं, रुका हुआ फल है)';
            } else {
                $verdict = $v > 0 ? 'शुभ' : ($v < 0 ? 'अशुभ' : 'मध्यम');
                if ($isAsleep && $wake['awake']) {
                    $decidedAtGate = 1;
                    $vWhy[] = 'सोया था, पर जागृति की शर्त पूरी हो चुकी ('
                        . ($wake['condition'] !== '' ? $wake['condition'] : 'आयु ' . $wake['wake_age'] . ' वर्ष') . ') — फल खुलता है';
                }
                // फरमान 13 — अकेला बृहस्पति दृष्टि/टक्कर से कितना ही मंदा क्यों न हो,
                // कभी अशुभ फल नहीं देता। (साथ शत्रु हों तो मंदा हो सकता है — तब बुध जैसा
                // असर; पर अकेले की सूरत में फल कभी अशुभ नहीं।)
                if ($p === 'Jupiter' && $alone && $verdict === 'अशुभ') {
                    $verdict = 'मध्यम';
                    $vWhy[] = 'अकेला बृहस्पति — गुरु का रक्षक स्वभाव, दृष्टि/टक्कर से भी अशुभ नहीं';
                }
                // छाया-ग्रहों पर संगत भारी पड़ती है (सेटिंग: chhaya_company_dominance)।
                // राहु-केतु अपनी संगत का रंग बाक़ी सात से कहीं ज़्यादा पकड़ते हैं —
                // इसलिए इनके लिए संगत बैठक को भी काट देती है, अपने पक्के घर में भी।
                if (($p === 'Rahu' || $p === 'Ketu') && $mates !== []) {
                    $rel = array_column($mates, 'rel');
                    $names = implode(', ', array_column($mates, 'hi'));
                    if (!in_array('मित्र', $rel, true) && !in_array('सम', $rel, true) && $verdict !== 'अशुभ') {
                        $verdict = 'अशुभ';
                        $vWhy[] = 'छाया-ग्रह — पूरी संगत शत्रु (' . $names . '); संगत बैठक पर भारी पड़ती है';
                    } elseif (!in_array('शत्रु', $rel, true) && !in_array('सम', $rel, true) && $verdict !== 'शुभ') {
                        $verdict = 'शुभ';
                        $vWhy[] = 'छाया-ग्रह — पूरी संगत मित्र (' . $names . '); संगत बैठक पर भारी पड़ती है';
                    }
                }
            }
            $isAshubh = $verdict === 'अशुभ';
            $isShubh = $verdict === 'शुभ';

            // ---- क्षमता — दिशा से बिल्कुल अलग धुरी ----
            // दिशा (नेक/बद) बताती है "भला करेगा या बुरा"; क्षमता बताती है "कितना कर
            // सकता है"। उपाय का क्रम क्षमता से बनता है, दिशा से नहीं — प्रबल बद ग्रह
            // पहले सँभाला जाता है, कमज़ोर बद ग्रह सिर्फ़ खीझ देता है।
            $capScore = 0;
            if ($pukka) { $capScore++; }
            if ($status === 'उच्च' && !$digBhang) { $capScore++; }
            if ($status === 'स्वगृही') { $capScore++; }
            if ($status === 'नीच') { $capScore--; }
            if ($kachcha) { $capScore--; }
            if ($digBhang) { $capScore--; }
            if ($impair['code'] !== '' && !$isAsleep) { $capScore--; }
            $kshamata = $verdict === 'निष्क्रिय' ? 'शून्य'
                : ($capScore >= 2 ? 'प्रबल' : ($capScore <= -1 ? 'कमज़ोर' : 'मध्यम'));

            // ---- बैठक का विरोध — औसत नहीं किया जाता, दोनों तथ्य दर्ज रहते हैं ----
            // "उच्च पर कच्चे घर में" जैसी हालत +1−1=0 करके मिटा देना सबसे क़ीमती
            // जानकारी फेंक देना है। इसे चिह्नित रखते हैं ताकि फल में qualifier बने।
            $seatConflict = '';
            if ($status === 'उच्च' && $digBhang) { $seatConflict = 'उच्च — पर भंग की शर्त लगी है'; }
            elseif ($status === 'उच्च' && $kachcha) { $seatConflict = 'उच्च — पर कच्चे घर में, फल अधूरा'; }
            elseif ($status === 'नीच' && $pukka) { $seatConflict = 'नीच — पर अपने पक्के घर में, अधिकार बना रहता है'; }
            elseif ($pukka && $isAshubh) { $seatConflict = 'पक्का घर — पर संगत/दृष्टि से फल बिगड़ा'; }

            // 5) टिप्पणी के "अगर-तो" नियम — इस कुंडली पर जाँचे हुए: केवल लागू
            //    वाले मुख्य फल बनते हैं, शेष संदर्भ में जाते हैं।
            [$notesApplied, $notesRef] = self::noteClauses(
                (string) ($sab[$p]['note'] ?? ''), $p, $h, $alone, $isAshubh, $isShubh, $sambandh
            );

            // हर नकारात्मक बात पर उपाय — टक्कर, सोया/अपंग अवस्था, कच्चे में शुभ, भंग भी
            $hasTakkar = false;
            foreach ($inHits as $ih) { if ($ih['kind'] === 'टकराव') { $hasTakkar = true; break; } }
            $needRemedy = $isAshubh || $status === 'नीच' || ($doshaOf[$p] ?? []) !== []
                || $hasTakkar || $impair['code'] !== '' || ($kachcha && $isShubh) || $digBhang;
            $remedies = $bg[$p][(string) $h] ?? [];

            // 6) फल — प्लेन-भाषा निष्कर्ष: कारक-भाव + स्थित-भाव के जीवन-क्षेत्र
            //    (क्या प्रभावित होगा) + verdict-अनुसार क्या होगा + ठोस applied-note
            //    प्रभाव। यही "आखिर होगा क्या" वाला उत्तर है।
            $areaHouses = self::parseHouses((string) ($gp[$p]['karak_bhav'] ?? ''));
            if (!in_array($h, $areaHouses, true)) { $areaHouses[] = $h; }
            sort($areaHouses);
            $areas = [];
            foreach ($areaHouses as $ah) {
                if (isset(LalKitabData::HOUSE_TOPIC[$ah])) { $areas[] = LalKitabData::HOUSE_TOPIC[$ah]; }
            }
            $effectVerb = $verdict === 'शुभ'
                ? 'इन क्षेत्रों में उन्नति, लाभ व अनुकूल फल मिलेगा।'
                : ($verdict === 'अशुभ'
                    ? 'इन क्षेत्रों में बाधा, कष्ट व हानि की सम्भावना है — उपाय आवश्यक।'
                    : ($verdict === 'निष्क्रिय'
                        ? 'इन क्षेत्रों में अभी कुछ आगे नहीं बढ़ रहा — काम रुका-सा रहेगा। '
                          . 'यह बुरा फल नहीं है; इसका उपाय शांति नहीं, इस ग्रह को जगाना है।'
                        : 'इन क्षेत्रों में मिश्रित/सामान्य फल रहेगा।'));
            if ($isAsleep && $verdict !== 'निष्क्रिय') {
                $effectVerb .= ' (ग्रह सुप्त था, अब जागृत — फल देर से पर मिलेगा।)';
            }
            if ($seatConflict !== '') {
                $effectVerb .= ' (' . $seatConflict . ' — इसलिए फल पूरा नहीं, अधूरा रहेगा।)';
            }
            $predHead = LalKitabData::planetHi($p) . ' के कारक क्षेत्र — ' . implode('; ', array_unique($areas))
                . ' — पर इस स्थिति का असर पड़ता है। ' . $effectVerb;
            $predEffects = array_map(static fn ($na) => (string) $na['text'], $notesApplied);

            // 📅 आयु/समय-प्रभाव — when this planet wakes & its strong/caution years.
            $ageBits = [];
            if (trim((string) ($sg[$p]['aayu'] ?? '')) !== '') {
                $ageBits[] = '⏳ जागृति: ' . $sg[$p]['aayu'] . (trim((string) ($sg[$p]['jagega'] ?? '')) !== '' ? ' (' . $sg[$p]['jagega'] . ')' : '');
            }
            foreach (LalKitabDasha::template() as $dt) {
                if ($dt['planet'] === $p) { $ageBits[] = '🕰️ दशा-खंड: हर 35-वर्षीय चक्र में आयु ' . $dt['from'] . '–' . $dt['to'] . ' (' . $dt['years'] . ' वर्ष)'; break; }
            }
            if (trim((string) ($gc[$p]['ashubh'] ?? '')) !== '') { $ageBits[] = '⚠️ सावधानी वर्ष: ' . $gc[$p]['ashubh']; }
            if (trim((string) ($gc[$p]['vishesh'] ?? '')) !== '') { $ageBits[] = '✨ विशेष: ' . $gc[$p]['vishesh']; }
            $ageTiming = $ageBits;

            $out[] = [
                'planet'    => $p,
                'hi'        => LalKitabData::planetHi($p),
                'house'     => $h,
                'house_ord' => LalKitabData::houseOrdinalHi($h),
                'dos'       => self::DO_DONT[$p]['do'] ?? [],
                'donts'     => self::DO_DONT[$p]['dont'] ?? [],
                'age_timing'=> $ageTiming,
                'sign'      => LalKitabData::HOUSE_SIGN[$h],
                'sign_hi'   => LalKitabData::signHi(Charts::SIGNS[LalKitabData::HOUSE_SIGN[$h]]),
                'gochar_sign_hi' => LalKitabData::signHi(Charts::SIGNS[$sIdx]),
                'retro'     => $retro,
                'status'    => $status,
                'status_tier' => $digTier,
                'uch_bhang' => $digBhang,
                'verdict'   => $verdict,
                'verdict_why' => $vWhy,
                'kshamata'  => $kshamata,          // दिशा से अलग धुरी — उपाय-क्रम इसी से
                'decided_at_gate' => $decidedAtGate,
                'seat_conflict' => $seatConflict,  // औसत नहीं — दोनों तथ्य क़ायम
                'wake'      => $wake,              // कारण-ग्रह / जगाने वाला / शर्त
                'is_nishkriya' => $verdict === 'निष्क्रिय',
                'is_ashubh' => $isAshubh,
                'pukka'     => $pukka,
                'kachcha'   => $kachcha,
                'impair'    => $impair['state'],
                'impair_code' => $impair['code'],
                'alone'     => $alone,
                'mates'     => $mates,
                'dosha'     => array_values($doshaOf[$p] ?? []),
                'asleep'    => $isAsleep,
                'in_hits'   => $inHits,
                'out_hits'  => $outHits,
                'notes_applied' => $notesApplied,
                'notes_ref' => $notesRef,
                'pred_head' => $predHead,      // plain-language "क्या होगा" headline
                'pred_effects' => $predEffects, // concrete applied-note effects
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
                // फरमान 13 — भाव-अनुसार नेक/मंदी फल (जिस भाव में ग्रह है); कौन-सा
                // पक्ष खुले, यह verdict तय करता है — शुभ→नेक, अशुभ→मंदी, मध्यम→दोनों।
                'bhav_phal' => (static function () use ($gbp, $p, $h, $isShubh, $isAshubh) {
                    $bp = $gbp[$p][(string) $h] ?? null;
                    if (!is_array($bp)) { return null; }
                    $bp['show'] = $isShubh ? 'nek' : ($isAshubh ? 'mandi' : 'both');
                    return $bp;
                })(),
                // यह ग्रह किन ग्रहों को बल देता है, व उसके स्थायी नियम।
                'helps' => array_map([LalKitabData::class, 'planetHi'],
                    LalKitabData::HELPS_BY_HOUSE[$p][$h] ?? []),
                'special_niyam' => LalKitabData::PLANET_NIYAM[$p] ?? [],
            ];
        }
        return $out;
    }

    /** सोए ग्रहों का जमा हुआ सेट — planet-en => true. सिर्फ़ बैठक से, किसी फल से नहीं। */
    private static function asleepSet(array $house, array $occupants): array
    {
        $out = [];
        foreach (self::PLANETS as $p) {
            if (!isset($house[$p])) { continue; }
            $h = $house[$p];
            $imp = LalKitabTeva::impairedState($p, $h, $occupants, LalKitabTeva::dignity($p, $h, $occupants));
            if (in_array($imp['code'], ['SOYA', 'GUNGA', 'ANDHA', 'MRIT'], true)) { $out[$p] = true; }
        }
        return $out;
    }

    /**
     * सोए ग्रह की दृष्टि/टक्कर आधी रह जाती है (सेटिंग: soya_drishti = reduced)।
     *
     * सोया ग्रह क्या देख भी सकता है — इस पर टीकाकारों में मतभेद है, इसलिए यह एक
     * सेटिंग है, नियम नहीं। जो भी चुना जाए, रिपोर्ट उसे अपने नीचे दर्ज करती है,
     * ताकि दो अलग सेटिंग वाले इंजन अलग नतीजे दें तो पढ़ने वाला जान सके कि किस
     * नियम पर चला गया।
     *
     * @param list<array<string,mixed>> $hits
     * @param array<string,bool> $asleepSet
     * @return list<array<string,mixed>>
     */
    private static function attenuate(array $hits, array $occupants, array $asleepSet): array
    {
        foreach ($hits as $i => $hit) {
            $src = $occupants[$hit['house']] ?? [];
            $awake = array_values(array_filter($src, static fn ($p) => empty($asleepSet[$p])));
            $sleep = array_values(array_filter($src, static fn ($p) => !empty($asleepSet[$p])));
            $hits[$i]['eff_pct'] = ($awake === [] && $sleep !== [])
                ? (int) round(((int) $hit['pct']) / 2)     // सब देखने वाले सोए हुए
                : (int) $hit['pct'];
            $hits[$i]['soya_src'] = array_map([LalKitabData::class, 'planetHi'], $sleep);
            $hits[$i]['weak'] = $awake === [] && $sleep !== [];
        }
        return $hits;
    }

    /**
     * नींद का रिकॉर्ड — सिर्फ़ "सोया है" जान लेना काफ़ी नहीं। यह भी चाहिए कि उसे
     * किसने सुलाया और क्या उसे जगाएगा, क्योंकि उपाय प्रायः दूसरे ग्रह पर जाता है:
     * गुरु शनि के दबाव से सोया हो तो गुरु को बल देने से कुछ नहीं होता — शनि शांत
     * करो, गुरु अपने-आप उठ जाता है।
     *
     * @param array{state:string,code:string} $impair
     * @param list<array{planet:string,hi:string,rel:string}> $mates
     * @param list<array<string,mixed>> $inHits
     * @return array{asleep:bool,awake:bool,cause_hi:string,agent_hi:string,condition:string,wake_age:int|null}
     */
    private static function wakeRecord(string $p, int $h, array $impair, array $mates, array $inHits, ?int $age, array $sg, array $sb): array
    {
        if ($impair['code'] === '') {
            return ['asleep' => false, 'awake' => true, 'cause_hi' => '',
                'agent_hi' => '', 'condition' => '', 'wake_age' => null];
        }
        // सुलाने वाला — पहले साथ बैठा शत्रु, वरना टक्कर मारने वाला
        $cause = '';
        foreach ($mates as $m) {
            if ($m['rel'] === 'शत्रु') { $cause = $m['hi']; break; }
        }
        if ($cause === '') {
            foreach ($inHits as $ih) {
                if ($ih['kind'] === 'टकराव' && ($ih['planets_hi'] ?? []) !== []) {
                    $cause = (string) $ih['planets_hi'][0];
                    break;
                }
            }
        }
        // जगाने वाला — इस भाव की "चाबी", वरना ग्रह की अपनी जागृति-शर्त
        $chaabi = trim((string) ($sb[(string) $h] ?? ''));
        $cond   = trim((string) ($sg[$p]['jagega'] ?? ''));
        $aayu   = trim((string) ($sg[$p]['aayu'] ?? ''));
        $wakeAge = null;
        if ($aayu !== '' && preg_match('/\d+/', $aayu, $mm)) { $wakeAge = (int) $mm[0]; }
        // जागृति की शर्त तभी पूरी मानी जाती है जब आयु ज्ञात हो और वह उम्र निकल चुकी हो
        $awake = $wakeAge !== null && $age !== null && $age >= $wakeAge;
        return [
            'asleep'    => true,
            'awake'     => $awake,
            'cause_hi'  => $cause,
            'agent_hi'  => $chaabi !== '' ? $chaabi : $cond,
            'condition' => $cond !== '' ? $cond : ($aayu !== '' ? 'आयु ' . $aayu : ''),
            'wake_age'  => $wakeAge,
        ];
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
                if (isset(LalKitabData::DRISHTI[$wh][$h])) {
                    $awakeBy = $wakerHi . ' (' . LalKitabData::houseOrdinalHi($wh) . ') की दृष्टि इस भाव पर';
                }
            }
            $isAwake = $awakeBy !== null;

            // 3) दृष्टि व टक्कर — one-way matrix, incoming from occupied houses.
            $inHits = [];
            foreach (LalKitabTeva::seenBy($h, $occupants) as $s) {
                $inHits[] = ['kind' => 'दृष्टि', 'house' => $s['house'], 'pct' => $s['pct'],
                    'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $s['planets'])];
            }
            $struck = LalKitabTeva::struckBy($h, $occupants);
            if ($struck !== null) {
                $inHits[] = ['kind' => 'टकराव', 'house' => $struck['house'], 'pct' => 100,
                    'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $struck['planets'])];
            }
            // विश्वासघात / अचानक-चोट warnings from the confirmed maps (occupied only)
            $warn = [];
            $col = LalKitabTeva::collisions($h, $occupants);
            foreach (['vishwasghat' => 'विश्वासघात की आशंका', 'achanak' => 'अचानक चोट/हानि की आशंका'] as $wk => $wl) {
                if ($col[$wk] !== []) {
                    $warn[] = $wl . ' — ' . implode(', ', array_map(
                        static fn ($x) => LalKitabData::houseOrdinalHi((int) $x) . ' (' . implode(', ', array_map([LalKitabData::class, 'planetHi'], $occupants[$x] ?? [])) . ')', $col[$wk]
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

            // ---- मालिक और मेहमान ----
            // भरा हुआ भाव तीन अलग फल नहीं होता, एक ही भीड़ भरा कमरा होता है।
            // जो ग्रह अपने पक्के घर में बैठा है वह उस कमरे का गृहस्वामी है; बाक़ी
            // मेहमान। कमरे का मिज़ाज मालिक तय करता है, मेहमान उसे रंग देते हैं।
            // और जब भीड़ हो पर कोई मालिक न हो — तो किसी का अधिकार नहीं चलता और
            // उस भाव के मामले कभी तय नहीं होते।
            $malik = null; $mehmaan = [];
            foreach ($occ as $oe) {
                $pe = $pMap[$oe['planet']] ?? null;
                if ($pe !== null && !empty($pe['pukka'])) { $malik = $oe; } else { $mehmaan[] = $oe; }
            }
            $vivadit = $malik === null && count($occ) >= 2;
            if ($malik !== null) {
                $why[] = 'इस भाव का मालिक ' . $malik['hi'] . ' (अपने पक्के घर में) — कमरे का मिज़ाज इसी से';
            } elseif ($vivadit) {
                $why[] = 'भाव में भीड़ पर कोई मालिक नहीं — विवादित भाव, मामले टिकते नहीं';
            }

            // ---- मालिक घर से बाहर ----
            // पक्का घर उस ग्रह का घर है, चाहे वह उसमें खड़ा हो या न हो। मालिक
            // बाहर हो तो इन मामलों का स्वाभाविक रखवाला मौजूद नहीं। यह अपने-आप
            // बुरा नहीं — सब इस पर है कि वह गया कहाँ और वहाँ कैसा है। पर एक हालत
            // साफ़ चेतावनी है: **मालिक बाहर और उसके घर पर शत्रु का क़ब्ज़ा** —
            // रखवाला ग़ैर-हाज़िर और कमरा दुश्मन के पास।
            $ownerAway = null;
            foreach (self::PLANETS as $op) {
                if (($house[$op] ?? null) === null) { continue; }
                $od = LalKitabTeva::dignity($op, $h, $occupants);
                if (empty($od['pakka']) || $house[$op] === $h) { continue; }   // यह भाव इसका पक्का घर है, पर यह यहाँ नहीं
                $opHi = LalKitabData::planetHi($op);
                $foes = [];
                $shatruTxt = (string) (LalKitabData::section('maitri')[$op]['shatru'] ?? '');
                foreach ($occ as $oe) {
                    if ($shatruTxt !== '' && mb_strpos($shatruTxt, (string) $oe['hi']) !== false) { $foes[] = (string) $oe['hi']; }
                }
                $ownerAway = ['hi' => $opHi, 'sits' => LalKitabData::houseOrdinalHi($house[$op]), 'foes' => $foes];
                if ($foes !== []) {
                    $why[] = '⚠ इस घर का मालिक ' . $opHi . ' बाहर (' . $ownerAway['sits'] . ' भाव में) है और यहाँ '
                        . implode(', ', $foes) . ' (शत्रु) बैठा है — रखवाला ग़ैर-हाज़िर, कमरा दुश्मन के पास';
                    $v--;
                } else {
                    $why[] = 'इस घर का मालिक ' . $opHi . ' बाहर (' . $ownerAway['sits'] . ' भाव में) है — देखभाल कम';
                }
                break;
            }

            // ---- पाँच दर्जे ----
            // शुभ · मंदा · मिश्रित (स्थिर बँटवारा) · अस्थिर (वही बात कभी ठीक कभी
            // बिगड़ी — कोई टिकाव नहीं) · सुप्त (कुछ हिलता ही नहीं)। ये पाँच अलग
            // ज़िंदगियाँ हैं; किन्हीं दो को एक शब्द से कहना आठ चरणों की मेहनत
            // फेंक देना है।
            $verdict = $v > 0 ? 'शुभ' : ($v < 0 ? 'अशुभ' : 'मध्यम');
            $allNishkriya = $occ !== [];
            foreach ($occ as $oe) {
                if (($pMap[$oe['planet']]['verdict'] ?? '') !== 'निष्क्रिय') { $allNishkriya = false; }
            }
            if ($vivadit) {
                $verdict = 'अस्थिर';
                $why[] = 'मालिक-विहीन भीड़ — फल एक-सा नहीं रहता';
            } elseif ($allNishkriya || (!$isAwake && $occ === [])) {
                $verdict = 'सुप्त';
                $why[] = $allNishkriya ? 'भाव के सभी ग्रह निष्क्रिय — विषय आगे नहीं बढ़ते' : 'भाव सुप्त व ख़ाली';
            } elseif ($malik !== null && $verdict === 'अशुभ') {
                // अपने घर का मालिक बुरे मेहमानों से नष्ट नहीं होता — परेशान होता है
                $verdict = 'मध्यम';
                $why[] = 'मालिक अपने घर में है — मेहमान परेशान करते हैं, अधिकार छीनते नहीं';
            } elseif (!$isAwake && $verdict === 'शुभ') {
                $verdict = 'मध्यम';
            }

            // फल — क्या होगा: इस भाव के जीवन-क्षेत्र + verdict-अनुसार परिणाम।
            $hTopic = LalKitabData::HOUSE_TOPIC[$h] ?? '';
            $hEffect = $verdict === 'शुभ'
                ? 'इन विषयों में उन्नति, सुख व अनुकूल फल मिलेगा।'
                : ($verdict === 'अशुभ'
                    ? 'इन विषयों में बाधा, कष्ट या हानि की सम्भावना है — उपाय आवश्यक।'
                    : ($verdict === 'अस्थिर'
                        ? 'यह मामला कभी ठीक, कभी बिगड़ा रहेगा — टिकाव नहीं आता। '
                          . '(यह "कुछ अच्छा कुछ बुरा" नहीं है; यही एक बात बार-बार पलटती रहती है।)'
                        : ($verdict === 'सुप्त'
                            ? 'यह हिस्सा अभी रुका हुआ है — इन विषयों में कुछ आगे नहीं बढ़ रहा। '
                              . 'यह बुरा फल नहीं, ठहरा हुआ फल है।'
                            : 'इन विषयों में मिश्रित/सामान्य फल रहेगा।')));
            if (!$isAwake && !in_array($verdict, ['सुप्त', 'अस्थिर'], true)) {
                $hEffect .= ' (भाव सुप्त होने से ये विषय दबे रहेंगे — समय पर पूरा फल नहीं मिलेगा।)';
            }
            if ($malik !== null) {
                $hEffect .= ' इस भाव का मालिक ' . $malik['hi'] . ' है — इस कमरे में इसी की चलती है।';
            }
            $hPredHead = 'इस भाव से ' . $hTopic . ' का विचार होता है। ' . $hEffect;
            $hPredEffects = [];
            foreach ($occ as $oe) {
                if ($oe['verdict'] === 'अशुभ') { $hPredEffects[] = $oe['hi'] . ' के अशुभ होने से इस भाव पर दबाव।'; }
                elseif ($oe['verdict'] === 'शुभ') { $hPredEffects[] = $oe['hi'] . ' शुभ होकर इस भाव को बल देता है।'; }
            }
            if ($lordE !== null && ($lordE['verdict'] ?? '') === 'अशुभ') {
                $hPredEffects[] = 'भाव-स्वामी ' . LalKitabData::planetHi($lord) . ' दुर्बल — भाव-फल में कमी।';
            }
            foreach ($warn as $wl) { $hPredEffects[] = $wl; }

            // 5) उपाय — only when the house needs strengthening.
            $needRemedy = in_array($verdict, ['अशुभ', 'अस्थिर', 'सुप्त'], true) || !$isAwake;
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

            // ✅/⛔ भाव हेतु आचरण — इसमें बैठे ग्रह + भाव-स्वामी के अनुसार।
            $hDos = []; $hDonts = [];
            foreach (array_values(array_unique(array_merge($occupants[$h], [$lord]))) as $rp) {
                $dd = self::DO_DONT[$rp] ?? null;
                if ($dd === null) { continue; }
                if (!empty($dd['do'])) { $hDos[] = LalKitabData::planetHi($rp) . ': ' . $dd['do'][0]; }
                if (!empty($dd['dont'])) { $hDonts[] = LalKitabData::planetHi($rp) . ': ' . $dd['dont'][0]; }
            }

            $out[$h] = [
                'house'      => $h,
                'house_ord'  => LalKitabData::houseOrdinalHi($h),
                'dos'        => $hDos,
                'donts'      => $hDonts,
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
                'malik_hi'   => $malik['hi'] ?? '',       // कमरे का गृहस्वामी
                'mehmaan_hi' => array_column($mehmaan, 'hi'),
                'vivadit'    => $vivadit,                 // भीड़ पर मालिक कोई नहीं
                'owner_away' => $ownerAway,               // मालिक बाहर (+ शत्रु का क़ब्ज़ा?)
                'pred_head'  => $hPredHead,
                'pred_effects' => $hPredEffects,
                'maas'       => $bm[(string) $h] ?? '',
                'need_remedy' => $needRemedy,
                'remedies'   => $remedies,
                // बुनियाद-असूल (फरमान 15): नींव=मालिक · इमारत=पक्का-घर · राज=आगंतुक
                'buniyad'    => LalKitabTeva::foundation($h, $occupants),
            ];
        }
        return $out;
    }

    /**
     * फरमान 14 — टेवे की किस्में. Overall classification of the whole teva with
     * phal/upay pulled from the (owner-editable) LalKitabData::TEVA_KISM bank.
     *
     * @param array<string,int> $house
     * @param array<int,list<string>> $occupants
     * @return array{kinds:list<array<string,mixed>>,ling_niyam:string}
     */
    private static function tevaKisamReadings(array $house, array $occupants): array
    {
        $kinds = [];
        foreach (LalKitabTeva::classifyTeva($house, $occupants) as $k) {
            $meta = LalKitabData::TEVA_KISM[$k['code']] ?? null;
            if ($meta === null) { continue; }
            $kinds[] = [
                'code'  => $k['code'],
                'naam'  => $meta['naam'],
                'by'    => $k['by'],
                'phal'  => $meta['phal'],
                'upay'  => $meta['upay'],
                'darja' => $meta['darja'],
            ];
        }
        return ['kinds' => $kinds, 'ling_niyam' => LalKitabData::TEVA_LING_NIYAM];
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
            $kVerdict = $anyWeak ? 'अशुभ' : ($allStrong ? 'शुभ' : 'मध्यम');

            // फल — क्या होगा: कारक-बल से इस भाव के जीवन-क्षेत्र का परिणाम।
            $kTopic = LalKitabData::HOUSE_TOPIC[$h] ?? '';
            $kEffect = $kVerdict === 'शुभ'
                ? 'इनका कारक बलवान है — ये विषय अच्छे व अनुकूल रहेंगे।'
                : ($kVerdict === 'अशुभ'
                    ? 'कारक दुर्बल होने से ये विषय कमजोर — इन क्षेत्रों में विशेष सावधानी व उपाय आवश्यक।'
                    : 'कारक मध्यम — इन विषयों में सामान्य फल रहेगा।');
            $kPredHead = 'इस भाव से ' . $kTopic . ' का विचार होता है। ' . $kEffect;
            $kWeakList = [];
            foreach ($karaks as $k) { if ($k['weak']) { $kWeakList[] = $k['hi']; } }

            $out[$h] = [
                'house'     => $h,
                'house_ord' => LalKitabData::houseOrdinalHi($h),
                'vishay'    => LalKitabData::section('bhav_vichar')[(string) $h]['vishay'] ?? '',
                'karaks'    => $karaks,
                'verdict'   => $kVerdict,
                'pred_head' => $kPredHead,
                'weak_list' => $kWeakList,
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
     * ग्रह अंतर्संबंध — "कौन ग्रह किसका फल खा/दे रहा है". Built from the applicable
     * भविष्यवाणी सूत्र whose "प्रभावित ग्रह" column names a real planet: the
     * planet(s) named in the सूत्र's स्थिति are the SUBJECT, and प्रभावित is the
     * TARGET they act on. Special युति-ग्रहण doshas are folded in too. For each
     * planet the result lists whom it प्रभावित करता है (→) and किनसे प्रभावित है (←),
     * each with the concrete फल and its tone. Data-backed, not interpretive.
     *
     * @param array<string,int> $house
     * @param list<array<string,mixed>> $yoga  yogaReadings() output
     * @param list<array<string,mixed>> $yutiDosha
     * @return list<array<string,mixed>>
     */
    private static function interEffects(array $house, array $yoga, array $yutiDosha): array
    {
        $PLHI   = LalKitabData::PLANET_HI;              // en => hi
        $hiToEn = array_flip($PLHI);
        $out = [];
        foreach (array_keys($house) as $p) {
            $out[$p] = ['planet' => $p, 'hi' => LalKitabData::planetHi($p), 'affects' => [], 'affected_by' => []];
        }
        $addEdge = static function (string $from, string $to, string $phal, string $tone) use (&$out): void {
            if (!isset($out[$from], $out[$to]) || $from === $to) { return; }
            $out[$from]['affects'][] = ['other' => LalKitabData::planetHi($to), 'phal' => $phal, 'tone' => $tone];
            $out[$to]['affected_by'][] = ['other' => LalKitabData::planetHi($from), 'phal' => $phal, 'tone' => $tone];
        };

        foreach ($yoga as $Y) {
            if (empty($Y['applicable'])) { continue; }
            $sthiti = (string) ($Y['sthiti'] ?? '');
            $prab   = trim((string) ($Y['prabhavit'] ?? ''));
            $phal   = (string) ($Y['phal'] ?? '');
            $tone   = (string) ($Y['tone'] ?? 'mix');

            // subjects = planets named in the स्थिति that are actually placed
            $subjects = [];
            foreach ($PLHI as $en => $hi) {
                if ($hi !== '' && mb_strpos($sthiti, $hi) !== false && isset($house[$en])) { $subjects[] = $en; }
            }
            if ($subjects === []) { continue; }

            if ($prab === 'दोनों' || $prab === 'दोनो') {
                // each subject affects the other(s)
                foreach ($subjects as $a) {
                    foreach ($subjects as $b) { $addEdge($a, $b, $phal, $tone); }
                }
                continue;
            }
            // resolve प्रभावित to a planet key
            $target = $hiToEn[$prab] ?? null;
            if ($target === null) {
                foreach ($PLHI as $en => $hi) {
                    if ($hi !== '' && mb_strpos($prab, $hi) !== false) { $target = $en; break; }
                }
            }
            if ($target === null || !isset($house[$target])) { continue; }   // जातक/पापी/- skipped
            foreach ($subjects as $a) { $addEdge($a, $target, $phal, $tone); }
        }

        // fold the special ग्रहण/युति doshas (both planets affect each other)
        foreach ($yutiDosha as $d) {
            $ps = $d['planets'] ?? [];
            if (count($ps) < 2) { continue; }
            [$a, $b] = $ps;
            $addEdge($a, $b, $d['desc'] ?? $d['name'], 'neg');
            $addEdge($b, $a, $d['desc'] ?? $d['name'], 'neg');
        }

        // keep only planets that have at least one relation; canonical order
        $order = self::PLANETS;
        $res = [];
        foreach ($order as $p) {
            if (isset($out[$p]) && ($out[$p]['affects'] !== [] || $out[$p]['affected_by'] !== [])) {
                $res[] = $out[$p];
            }
        }
        return $res;
    }

    /**
     * विशेष युति / ग्रहण दोष — well-known malefic conjunctions detected from the
     * teva (two planets sharing a house). Each carries its उपाय, looked up from
     * the ग्रह-युति उपाय bank (plus each planet's शीघ्र उपाय as fallback).
     *
     * @param array<int,list<string>> $occupants
     * @return list<array<string,mixed>>
     */
    /**
     * 🕰️ लाल किताब आयु-चक्र (Lal Kitab's own timing system — not Vimshottari).
     * Combines: (a) अवस्था चक्र — the 4 life-stages of 25 yrs each, each ruled by a
     * house-group whose planets then unfold; (b) ग्रह चक्र प्रभाव-वर्ष — each planet's
     * strong / caution years; (c) सुप्त-ग्रह जागृति — when a dormant planet awakens.
     * Produces the current stage + a forward age-timeline of "कब क्या होगा".
     *
     * @param array<int,list<string>> $occupants  house => planet-en list
     * @param list<array<string,mixed>> $planets  planetReadings output
     */
    private static function ageCycle(array $occupants, array $planets, ?int $age): array
    {
        $gc = LalKitabData::section('grah_chakra');
        $sg = LalKitabData::section('supt_grah');
        $pByEn = [];
        foreach ($planets as $pr) { $pByEn[$pr['planet']] = $pr; }
        $effShort = static function (string $en) use ($pByEn): string {
            $pr = $pByEn[$en] ?? null;
            if ($pr === null) { return ''; }
            return $pr['house_ord'] . ' भाव में ' . ($pr['verdict'] ?? 'मध्यम') . ' — '
                . ($pr['verdict'] === 'अशुभ' ? 'इस ग्रह के कारक विषयों में बाधा/सतर्कता' : ($pr['verdict'] === 'शुभ' ? 'इस ग्रह के कारक विषयों में लाभ/उन्नति' : 'सामान्य फल'));
        };

        // ---- (a) 4 अवस्था stages = "महादशा" level; each stage's active planets
        //     are the chart's four "time-zones" (see below) — NOT the dasha.
        $toneOf = static fn (string $v): string => $v === 'शुभ' ? 'pos' : ($v === 'अशुभ' ? 'neg' : 'mix');

        // ══ मुख्य दशा — the 35-year Lal Kitab cycle (शनि→राहु→केतु→गुरु→सूर्य→
        //    चन्द्र→शुक्र→मंगल→बुध). Fixed and universal, starts at birth, repeats
        //    every 35 years. See LalKitabDasha for why grah_chakra's प्रभाव-वर्ष
        //    are NOT used for timing (the sheet's copy of this cycle is corrupt).
        $dashaRow = static function (array $d) use ($pByEn, $toneOf, $effShort): array {
            $en = $d['planet'];
            $pr = $pByEn[$en] ?? [];
            $verdict = (string) ($pr['verdict'] ?? 'मध्यम');
            $rem = !empty($pr['need_remedy']) ? array_slice($pr['remedies'] ?? [], 0, 2) : [];
            return [
                'hi'        => $d['hi'],
                'planet'    => $en,
                'from'      => $d['from'],
                'to'        => $d['to'],
                'years'     => $d['years'],
                'cycle'     => $d['cycle'] ?? null,
                'active'    => !empty($d['active']),
                'placed'    => isset($pr['house']) ? (int) $pr['house'] : null,
                'house_ord' => $pr['house_ord'] ?? '',
                'verdict'   => $verdict,
                'tone'      => $toneOf($verdict),
                'asleep'    => !empty($pr['asleep']),
                'pred'      => (string) ($pr['pred_head'] ?? $effShort($en)),
                'do'        => ($pr['dos'] ?? [])[0] ?? '',
                'dont'      => ($pr['donts'] ?? [])[0] ?? '',
                'remedies'  => array_values($rem),
            ];
        };
        $dashaNow = null;
        $dashaCycle = [];
        $dashaAhead = [];
        if ($age !== null) {
            $cur = LalKitabDasha::at($age);
            $dashaNow = $dashaRow($cur + ['active' => true]);
            $dashaNow['elapsed'] = $cur['elapsed'];
            $dashaNow['remaining'] = $cur['remaining'];
            foreach (LalKitabDasha::currentCycle($age) as $c) {
                $c['cycle'] = $cur['cycle'];
                $dashaCycle[] = $dashaRow($c);
            }
            foreach (LalKitabDasha::timeline($age, $age + 30, $age) as $t) {
                $dashaAhead[] = $dashaRow($t);
            }
        }

        // ══ अवस्था — the chart's four TIME-ZONES (कुण्डली की घड़ियाँ). Each quarter
        //    of life switches on one house-group; the quarter holding the most
        //    planets is the native's "peak time" (centre of gravity), and a
        //    quarter whose three houses are all empty runs on autopilot.
        $stageDefs = [
            ['name' => 'प्रथम अवस्था', 'from' => 1, 'to' => 25, 'houses' => [1, 2, 3], 'label' => 'बुनियाद (Foundation)', 'theme' => 'शरीर (1) · परिवार-संस्कार (2) · शुरुआती प्रयास (3) — बचपन व पढ़ाई का काल'],
            ['name' => 'द्वितीय अवस्था', 'from' => 26, 'to' => 50, 'houses' => [4, 5, 6], 'label' => 'विस्तार व संघर्ष (Expansion & Struggle)', 'theme' => 'घर-गृहस्थी (4) · विद्या-संतान (5) · नौकरी-प्रतिस्पर्धा (6) — सर्वाधिक कर्म का काल'],
            ['name' => 'तृतीय अवस्था', 'from' => 51, 'to' => 75, 'houses' => [7, 8, 9], 'label' => 'ठहराव व परिपक्वता (Maturity)', 'theme' => 'साझेदारी (7) · आयु-रहस्य (8) · भाग्य-धर्म (9) — भागदौड़ घटती है, संचित भाग्य काम आता है'],
            ['name' => 'चतुर्थ अवस्था', 'from' => 76, 'to' => 100, 'houses' => [10, 11, 12], 'label' => 'परिणाम व मोक्ष (Conclusion)', 'theme' => 'अंतिम कर्म (10) · आखिरी इच्छाएँ (11) · मोक्ष-त्याग (12)'],
        ];
        $stages = [];
        $maxCount = 0;
        foreach ($stageDefs as $st) {
            $pls = [];
            foreach ($st['houses'] as $hn) {
                foreach (($occupants[$hn] ?? []) as $en) {
                    $pr = $pByEn[$en] ?? null;
                    $pls[] = ['hi' => LalKitabData::planetHi($en), 'house' => $hn, 'verdict' => $pr['verdict'] ?? 'मध्यम'];
                }
            }
            $maxCount = max($maxCount, count($pls));
            $stages[] = [
                'name' => $st['name'], 'from' => $st['from'], 'to' => $st['to'],
                'houses' => $st['houses'], 'label' => $st['label'], 'theme' => $st['theme'],
                'planets' => $pls, 'count' => count($pls),
                'empty' => $pls === [],
                'active' => $age !== null && $age >= $st['from'] && $age <= $st['to'],
            ];
        }
        // centre of gravity — the quarter carrying the most planets is the peak.
        $peak = null;
        foreach ($stages as $i => $st) {
            $isPeak = $maxCount > 0 && $st['count'] === $maxCount;
            $stages[$i]['peak'] = $isPeak;
            $stages[$i]['note'] = $st['empty']
                ? 'तीनों भाव खाली — ये 25 वर्ष बिना बड़े झटके/बदलाव के, रूटीन (autopilot) में शांति से बीतेंगे।'
                : ($isPeak ? 'कुण्डली का सर्वाधिक भार यहीं — जीवन की सबसे बड़ी घटनाएँ इसी 25-वर्षीय खंड में घटेंगी (peak time)।' : '');
            if ($isPeak && $peak === null) { $peak = $stages[$i]['name'] . ' (' . $st['from'] . '–' . $st['to'] . ')'; }
        }

        // ---- forward timeline of events (current age → +25) ----
        //  NOTE: grah_chakra's "प्रभाव" column is deliberately NOT used here. It is
        //  a corrupted copy of the 35-year dasha cycle (शनि rotated to the end,
        //  राहु cut 6→4 years); the दशा above is the authoritative timing. Only the
        //  अशुभ (caution) years and the सुप्त-ग्रह awakening ages are read here.
        $from = $age ?? 0; $to = $from + 25;
        $topRemedy = static function (string $en) use ($pByEn): array {
            $pr = $pByEn[$en] ?? null;
            if ($pr === null || empty($pr['remedies'])) { return []; }
            return array_slice($pr['remedies'], 0, 2);
        };
        $events = [];
        foreach (self::PLANETS as $en) {
            $hi = LalKitabData::planetHi($en);
            $eff = $effShort($en);
            foreach (self::parseYears((string) ($gc[$en]['ashubh'] ?? '')) as $yr) {
                if ($yr >= $from && $yr <= $to) { $events[] = ['age' => $yr, 'planet' => $hi, 'kind' => 'सावधानी', 'tone' => 'neg', 'text' => $hi . ' का सावधानी-वर्ष — सतर्कता व उपाय रखें (' . $eff . ')', 'remedy' => $topRemedy($en)]; }
            }
            $wakeYrs = self::parseYears((string) ($sg[$en]['aayu'] ?? ''));
            if ($wakeYrs !== []) {
                $wy = $wakeYrs[0];
                if ($wy >= $from && $wy <= $to) { $events[] = ['age' => $wy, 'planet' => $hi, 'kind' => 'जागृति', 'tone' => 'info', 'text' => $hi . ' जागृत होगा — ' . ($sg[$en]['jagega'] ?? '') . ' पर फल सक्रिय', 'remedy' => []]; }
            }
        }
        usort($events, static fn ($a, $b) => $a['age'] <=> $b['age']);
        $events = array_slice($events, 0, 24);

        // per-planet reference chart. "prabhav" is intentionally omitted — that
        // column is the corrupted dasha copy; the दशा table above replaces it.
        $planetYears = [];
        foreach (self::PLANETS as $en) {
            $d = null;
            foreach (LalKitabDasha::template() as $t) {
                if ($t['planet'] === $en) { $d = $t; break; }
            }
            $planetYears[] = [
                'hi' => LalKitabData::planetHi($en),
                'dasha' => $d !== null ? ($d['from'] . '–' . $d['to'] . ' (' . $d['years'] . ' वर्ष)') : '',
                'ashubh' => (string) ($gc[$en]['ashubh'] ?? ''),
                'vishesh' => (string) ($gc[$en]['vishesh'] ?? ''),
                'jagega' => trim((string) ($sg[$en]['aayu'] ?? '') . ' — ' . (string) ($sg[$en]['jagega'] ?? ''), ' —'),
                'effect' => $effShort($en),
            ];
        }

        return [
            'age' => $age,
            'dasha_now' => $dashaNow,       // currently running 35-yr-cycle period
            'dasha_cycle' => $dashaCycle,   // the whole 35-year wheel the native is in
            'dasha_ahead' => $dashaAhead,   // upcoming periods (this age → +30 yrs)
            'cycle_len' => LalKitabDasha::CYCLE,
            'stages' => $stages,            // 4 avastha time-zones (+ peak / empty)
            'peak' => $peak,
            'events' => $events,
            'planet_years' => $planetYears,
        ];
    }

    /**
     * "आयु X–Y (N वर्ष)" — a planet's slot inside the 35-year Lal Kitab cycle.
     * Used wherever the corrupt grah_chakra "प्रभाव" column used to be read.
     */
    private static function dashaSpanHi(string $planetEn): string
    {
        foreach (LalKitabDasha::template() as $t) {
            if ($t['planet'] === $planetEn) {
                return 'आयु ' . $t['from'] . '–' . $t['to'] . ' (' . $t['years'] . ' वर्ष, हर चक्र में)';
            }
        }
        return '';
    }

    /** Extract all integers (life-years) from a mixed Hindi string. */
    private static function parseYears(string $s): array
    {
        if (trim($s) === '') { return []; }
        preg_match_all('/\d+/', $s, $m);
        return array_values(array_unique(array_map('intval', $m[0])));
    }

    /**
     * 🔎 सामान्य परिचय — whole-chart overview: overall शुभ/अशुभ balance, a
     * plain-language summary, and the aggregated करें/न करें (weighted toward the
     * अशुभ planets that actually need attention).
     * @param list<array<string,mixed>> $planets planetReadings output
     */
    private static function generalOverview(array $planets): array
    {
        $shubh = []; $ashubh = [];
        foreach ($planets as $p) {
            if (($p['verdict'] ?? '') === 'अशुभ') { $ashubh[] = (string) $p['hi']; }
            elseif (($p['verdict'] ?? '') === 'शुभ') { $shubh[] = (string) $p['hi']; }
        }
        $src = $ashubh !== [] ? array_filter($planets, static fn ($p) => ($p['verdict'] ?? '') === 'अशुभ') : $planets;
        $dos = []; $donts = [];
        foreach ($src as $p) {
            foreach (($p['dos'] ?? []) as $d) { $dos[] = $p['hi'] . ': ' . $d; }
            foreach (($p['donts'] ?? []) as $d) { $donts[] = $p['hi'] . ': ' . $d; }
        }
        $dos = array_slice(array_values(array_unique($dos)), 0, 8);
        $donts = array_slice(array_values(array_unique($donts)), 0, 8);
        $tone = count($ashubh) > count($shubh) ? 'neg' : (count($shubh) > count($ashubh) ? 'pos' : 'mix');
        $summary = 'इस कुंडली में ' . count($shubh) . ' ग्रह शुभ व ' . count($ashubh) . ' ग्रह अशुभ स्थिति में हैं। '
            . ($ashubh !== []
                ? 'विशेष ध्यान योग्य ग्रह: ' . implode(', ', $ashubh) . '। इनका आचरण-सुधार व उपाय ही सर्वाधिक लाभ देगा; नीचे करें/न करें व उपाय दिए हैं।'
                : 'कुंडली प्रायः बलवान है — सामान्य सदाचार, दान व नित्य-कर्म पर्याप्त; किसी उग्र उपाय की आवश्यकता नहीं।');
        return ['summary' => $summary, 'tone' => $tone, 'shubh' => $shubh, 'ashubh' => $ashubh, 'dos' => $dos, 'donts' => $donts];
    }

    /**
     * मसनूई (कृत्रिम) ग्रह — two planets sharing a house behave as a third, and
     * the teva is read for that third planet in that house.
     *
     * The manufactured planet is a dominant layer over the two sources, not a
     * replacement, so both originals keep their own cards; this reading is what
     * gets read *first* for that house. Four things are worked out per formation:
     * the phal of the manufactured planet there, its house-based dignity, whether
     * the real planet of the same kind is elsewhere in the chart (a clash of
     * equals decided by दृष्टि strength), and when in the 35-year cycle it bites.
     *
     * Remedies never touch the manufactured planet — that is a hard Lal Kitab
     * restriction. A benefic formation is reinforced by combining the two
     * sources' articles; a malefic one is broken by separating them, or by
     * seating a third planet friendly to both between them.
     *
     * @param array<string,int> $house
     * @param array<int,list<string>> $occupants
     * @param list<array<string,mixed>> $planets
     * @return list<array<string,mixed>>
     */
    private static function masnuiReadings(array $house, array $occupants, array $planets, ?int $age): array
    {
        $forms = LalKitabMasnui::detect($occupants);
        if ($forms === []) {
            return [];
        }
        $gp  = LalKitabData::section('graha_parichay');
        $sab = LalKitabData::section('shubh_ashubh_bhav');
        $gv  = LalKitabData::section('grah_vastu');
        $stv = LalKitabData::section('sthapana_vastu');
        $sh  = LalKitabData::section('sheeghra');

        $verdictOf = [];
        foreach ($planets as $p) {
            $verdictOf[$p['planet']] = (string) ($p['verdict'] ?? '');
        }
        $firstItem = static function (string $p) use ($gv): string {
            $bits = preg_split('/[,;]/u', (string) ($gv[$p] ?? '')) ?: [];
            $take = [];
            foreach ($bits as $b) {
                $b = trim($b);
                if ($b !== '') { $take[] = $b; }
                if (count($take) === 3) { break; }
            }
            return implode(', ', $take);
        };

        $out = [];
        foreach ($forms as $f) {
            $h    = (int) $f['house'];
            $a    = $f['pair'][0];
            $b    = $f['pair'][1];
            $mk   = (string) $f['makes'];      // what is manufactured
            $rd   = (string) $f['read'];       // whose phal is actually read
            $aHi  = LalKitabData::planetHi($a);
            $bHi  = LalKitabData::planetHi($b);
            $mkHi = LalKitabData::planetHi($mk);

            // ---- 1) dignity of the manufactured planet in this house ----
            $dig = LalKitabMasnui::dignity($rd, $h);
            $score = (int) $dig['weight'];
            $why = [];
            foreach ($dig['why'] as $w) { $why[] = $w; }

            // the pair's own inherent standing, before the house is considered
            $inh = $f['inherent'];
            if ($inh === 'उच्च') { $score += 2; $why[] = 'यह जोड़ी स्वयं उच्च का मसनूई ग्रह बनाती है'; }
            elseif ($inh === 'नीच') { $score -= 2; $why[] = 'यह जोड़ी स्वयं नीच का मसनूई ग्रह बनाती है'; }
            elseif ($inh === 'बद') { $score -= 3; $why[] = 'यह जोड़ी "बद" मसनूई ग्रह बनाती है — फलादेश नीच राहु जैसा'; }

            // the manufactured planet's own shubh / ashubh house lists
            $shubhL  = self::parseHouses((string) ($sab[$rd]['shubh'] ?? ''));
            $ashubhL = self::parseHouses((string) ($sab[$rd]['ashubh'] ?? ''));
            if (in_array($h, $shubhL, true)) { $score++; $why[] = $h . 'वाँ भाव ' . LalKitabData::planetHi($rd) . ' हेतु शुभ'; }
            elseif (in_array($h, $ashubhL, true)) { $score--; $why[] = $h . 'वाँ भाव ' . LalKitabData::planetHi($rd) . ' हेतु अशुभ'; }

            // पक्का घर is intensity — it doubles whichever way the result already leans
            if (!empty($dig['pakka']) && $score !== 0) { $score += $score > 0 ? 1 : -1; }
            if (!empty($dig['kachcha']) && $score !== 0) { $score += $score > 0 ? -1 : 1; }

            $verdict = $score > 0 ? 'शुभ' : ($score < 0 ? 'अशुभ' : 'मध्यम');

            // ---- 2) plain-language phal ----
            $areas = [];
            $areaHouses = self::parseHouses((string) ($gp[$rd]['karak_bhav'] ?? ''));
            if (!in_array($h, $areaHouses, true)) { $areaHouses[] = $h; }
            sort($areaHouses);
            foreach ($areaHouses as $ah) {
                if (isset(LalKitabData::HOUSE_TOPIC[$ah])) { $areas[] = LalKitabData::HOUSE_TOPIC[$ah]; }
            }
            $label = $mkHi . ($inh !== null ? ' (' . $inh . ')' : '');
            if (!empty($f['flavour'])) { $label .= ' — ' . LalKitabData::planetHi((string) $f['flavour']) . '-स्वभाव'; }
            $head = $aHi . ' व ' . $bHi . ' एक साथ ' . LalKitabData::houseOrdinalHi($h) . ' भाव में हैं ⇒ यहाँ '
                . 'मसनूई ' . $label . ' बनता है। इसलिए इस भाव का मुख्य फल ' . $aHi . ' या ' . $bHi
                . ' का नहीं, बल्कि ' . LalKitabData::houseOrdinalHi($h) . ' भाव में बैठे '
                . LalKitabData::planetHi($rd) . ' का पढ़ा जाएगा'
                . ($dig['status'] !== 'सामान्य' ? ' — और यहाँ वह ' . $dig['status'] . ' का है।' : '।');
            $effect = $verdict === 'शुभ'
                ? 'इन क्षेत्रों में शानदार व मज़बूत फल मिलेगा — ' . implode('; ', array_unique($areas)) . '।'
                : ($verdict === 'अशुभ'
                    ? 'इन क्षेत्रों में मन्दा फल मिलेगा — ' . implode('; ', array_unique($areas)) . '। उपाय आवश्यक।'
                    : 'इन क्षेत्रों में मिश्रित फल रहेगा — ' . implode('; ', array_unique($areas)) . '।');

            // ---- 3) real vs manufactured clash ----
            $clash = LalKitabMasnui::clash($rd, $h, $house);
            $clashTxt = '';
            if ($clash !== null) {
                $clashTxt = 'असली ' . LalKitabData::planetHi($rd) . ' भी कुंडली में है — '
                    . LalKitabData::houseOrdinalHi((int) $clash['real_house']) . ' भाव में। '
                    . 'दो समान ताकतें आमने-सामने आती हैं ("दो ' . LalKitabData::planetHi($rd) . ' की लड़ाई"), '
                    . 'जिससे फलादेश में मिलावट आती है। ';
                if (!$clash['linked']) {
                    $clashTxt .= 'दोनों के बीच कोई दृष्टि-संबंध नहीं, इसलिए दोनों अपने-अपने भाव में अलग-अलग फल देंगे।';
                } elseif ($clash['stronger'] === 'masnui') {
                    $clashTxt .= 'मसनूई वाला भाव असली वाले को ' . $clash['from_masnui'] . '% दृष्टि से देखता है ⇒ '
                        . 'मसनूई का पक्ष भारी रहेगा।';
                } elseif ($clash['stronger'] === 'real') {
                    $clashTxt .= 'असली ' . LalKitabData::planetHi($rd) . ' मसनूई वाले भाव को ' . $clash['from_real']
                        . '% दृष्टि से देखता है ⇒ असली का पक्ष भारी रहेगा।';
                } else {
                    $clashTxt .= 'दोनों की दृष्टि बराबर है ⇒ फल आधा-आधा बँटेगा।';
                }
            }

            // ---- 4) when it bites — the two sources' stretches of the 35-year cycle ----
            $windows = [];
            foreach ([$a, $b] as $src) {
                foreach (LalKitabDasha::template() as $dt) {
                    if ($dt['planet'] === $src) {
                        $windows[] = ['hi' => LalKitabData::planetHi($src), 'from' => $dt['from'], 'to' => $dt['to']];
                        break;
                    }
                }
            }
            $activeNow = false;
            if ($age !== null) {
                $running = (string) (LalKitabDasha::at($age)['planet'] ?? '');
                $activeNow = $running === $a || $running === $b;
            }

            // ---- 5) remedies — never on the manufactured planet ----
            $mode = $verdict === 'अशुभ' ? 'तोड़ें' : ($verdict === 'शुभ' ? 'मज़बूत करें' : 'सँभालें');
            $rem = [];
            $split = null;
            $cats = [];
            if ($verdict === 'शुभ') {
                $rem[] = '✅ यह मसनूई ' . $mkHi . ' शुभ है — इसे कायम रखें। ' . $aHi . ' व ' . $bHi
                    . ' दोनों की वस्तुएँ एक साथ मिलाकर धारण करें या घर में रखें।';
                if (!empty($stv[$a])) { $rem[] = $aHi . ' — ' . $stv[$a]; }
                if (!empty($stv[$b])) { $rem[] = $bHi . ' — ' . $stv[$b]; }
            } else {
                $split = LalKitabMasnui::splitPlan($a, $b, $verdictOf);
                $rHi = LalKitabData::planetHi($split['remove']);
                $kHi = LalKitabData::planetHi($split['keep']);
                $rem[] = '① ज़हर तोड़ें — दोनों ग्रहों का संपर्क अलग करें, ताकि मसनूई ' . $mkHi
                    . ' का वजूद ही खत्म हो जाए। (' . $split['why'] . '।)';
                $rem[] = '⬇ ' . $rHi . ' की वस्तु घर से बाहर करें या बहते पानी में बहाएँ'
                    . (!empty($sh[$split['remove']]) ? ' — ' . $sh[$split['remove']] : '')
                    . ($firstItem($split['remove']) !== '' ? ' (वस्तुएँ: ' . $firstItem($split['remove']) . ')' : '');
                $rem[] = '⬆ ' . $kHi . ' की वस्तु शरीर पर धारण करें या घर में स्थापित करें'
                    . (!empty($stv[$split['keep']]) ? ' — ' . $stv[$split['keep']] : '');
                $cats = LalKitabMasnui::catalysts($a, $b);
                if ($cats !== []) {
                    $c0 = $cats[0]['planet'];
                    $cHi = LalKitabData::planetHi($c0);
                    $rem[] = '② यदि अलग करना संभव न हो — बिचौलिया लाएँ। ' . $cHi
                        . ' दोनों से मेल रखता है' . ($cats[0]['both'] ? ' (दोनों का मित्र)' : '')
                        . '; उसकी वस्तुएँ इस भाव में स्थापित करें'
                        . (!empty($stv[$c0]) ? ' — ' . $stv[$c0] : '')
                        . ($firstItem($c0) !== '' ? ' (वस्तुएँ: ' . $firstItem($c0) . ')' : '') . '।';
                }
            }
            $rem[] = '⛔ ' . $mkHi . ' की वस्तुओं का दान या उपाय कभी न करें — यह ग्रह मसनूई है, असली नहीं। '
                . 'उपाय केवल ' . $aHi . ' व ' . $bHi . ' की वस्तुओं को एडजस्ट करके ही होगा।';

            $out[] = [
                'house'      => $h,
                'house_ord'  => LalKitabData::houseOrdinalHi($h),
                'planets'    => [$a, $b],
                'pair_hi'    => $aHi . ' + ' . $bHi,
                'makes'      => $mk,
                'makes_hi'   => $mkHi,
                'label'      => $label,
                'read_as'    => $rd,
                'read_as_hi' => LalKitabData::planetHi($rd),
                'inherent'   => $inh,
                'flavour_hi' => !empty($f['flavour']) ? LalKitabData::planetHi((string) $f['flavour']) : '',
                'note'       => (string) $f['note'],
                'status'     => $dig['status'],
                'pakka'      => (bool) $dig['pakka'],
                'kachcha'    => (bool) $dig['kachcha'],
                'verdict'    => $verdict,
                'score'      => $score,
                'why'        => $why,
                'head'       => $head,
                'effect'     => $effect,
                'clash'      => $clashTxt,
                'windows'    => $windows,
                'active_now' => $activeNow,
                'mode'       => $mode,
                'remedies'   => $rem,
            ];
        }
        return $out;
    }

    /**
     * Tell each source planet that it is busy forming a मसनूई planet, so its own
     * card can point the reader at the dominant layer instead of being read alone.
     *
     * @param list<array<string,mixed>> $planets   modified in place
     * @param list<array<string,mixed>> $masnui
     */
    private static function markMasnuiSources(array &$planets, array $masnui): void
    {
        if ($masnui === []) {
            return;
        }
        $by = [];
        foreach ($masnui as $m) {
            foreach ($m['planets'] as $p) {
                $by[$p][] = [
                    'label'   => (string) $m['label'],
                    'house'   => (int) $m['house'],
                    'verdict' => (string) $m['verdict'],
                    'with'    => (string) LalKitabData::planetHi(
                        $m['planets'][0] === $p ? $m['planets'][1] : $m['planets'][0]
                    ),
                ];
            }
        }
        foreach ($planets as &$pl) {
            $pl['masnui'] = $by[$pl['planet']] ?? [];
        }
        unset($pl);
    }

    /**
     * तीन टक्करें — विश्वासघात · साझी चोट · अचानक चोट. Map of houses, but a strike
     * only lands when BOTH the target house and the attacking house are occupied
     * (an empty house never strikes and is never struck).
     *
     * @param array<int,list<string>> $occupants
     * @return list<array<string,mixed>>
     */
    private static function collisionReadings(array $occupants): array
    {
        $namesHi = static fn (array $hs) => array_map(
            static fn ($h) => LalKitabData::houseOrdinalHi((int) $h) . ' भाव ('
                . implode(', ', array_map([LalKitabData::class, 'planetHi'], $occupants[$h] ?? [])) . ')',
            $hs
        );
        $kinds = [
            'vishwasghat' => ['नाम' => 'विश्वासघात (धोखे की टक्कर)', 'code' => 'VISHWASGHAT'],
            'sajhi'       => ['नाम' => 'साझी चोट (साझी दीवार)', 'code' => 'SAJHI_CHOT'],
            'achanak'     => ['नाम' => 'अचानक चोट (अंधी टक्कर)', 'code' => 'ACHANAK_CHOT'],
        ];
        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            if (empty($occupants[$h])) { continue; }
            $c = LalKitabTeva::collisions($h, $occupants);
            foreach ($kinds as $k => $meta) {
                if ($c[$k] === []) { continue; }
                $sit = LalKitabData::SITUATION[$meta['code']] ?? [];
                $out[] = [
                    'kind'       => $meta['नाम'],
                    'code'       => $meta['code'],
                    'house'      => $h,
                    'house_ord'  => LalKitabData::houseOrdinalHi($h),
                    'target_hi'  => array_map([LalKitabData::class, 'planetHi'], $occupants[$h]),
                    'from'       => $c[$k],
                    'from_hi'    => $namesHi($c[$k]),
                    'phal'       => (string) ($sit['phal'] ?? ''),
                    'upay'       => $sit['upay'] ?? [],
                ];
            }
        }
        return $out;
    }

    /**
     * बुनियाद (जड़) — a house whose root house holds a पापी/नीच planet, so the
     * branch's good fruit rots. Only the confirmed pairs (LalKitabData::BUNIYAD).
     *
     * @param array<int,list<string>> $occupants
     * @param array<string,string> $statusOf   planet => status
     * @return list<array<string,mixed>>
     */
    private static function buniyadReadings(array $occupants, array $statusOf): array
    {
        $out = [];
        foreach (array_keys(LalKitabData::BUNIYAD) as $h) {
            if (empty($occupants[$h])) { continue; }         // no branch planet to spoil
            $sp = LalKitabTeva::buniyadSpoiled($h, $occupants, $statusOf);
            if ($sp === null) { continue; }
            $sit = LalKitabData::SITUATION['BUNIYAD'];
            $out[] = [
                'house'     => $h,
                'house_ord' => LalKitabData::houseOrdinalHi($h),
                'root'      => $sp['root'],
                'root_ord'  => LalKitabData::houseOrdinalHi((int) $sp['root']),
                'branch_hi' => array_map([LalKitabData::class, 'planetHi'], $occupants[$h]),
                'root_hi'   => array_map([LalKitabData::class, 'planetHi'], $sp['by']),
                'phal'      => $sit['phal'],
                'upay'      => $sit['upay'],
            ];
        }
        return $out;
    }

    /**
     * सांझी गद्दी की पक्की दुश्मनी — the permanent battlefield of house 8, active
     * only when Mars and Saturn actually sit there.
     *
     * @param array<int,list<string>> $occupants
     * @return array<string,mixed>|null
     */
    private static function mukablaReading(array $occupants): ?array
    {
        if (!LalKitabTeva::jointSeatEnmity($occupants)) { return null; }
        $sit = LalKitabData::SITUATION['MUKABLA'];
        return [
            'house'     => 8,
            'house_ord' => 'आठवें',
            'planets_hi' => ['मंगल', 'शनि'],
            'phal'      => $sit['phal'],
            'upay'      => $sit['upay'],
        ];
    }

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
                    $yDos = []; $yDonts = [];
                    foreach ($pair as $p) {
                        $dd = self::DO_DONT[$p] ?? null;
                        if ($dd === null) { continue; }
                        if (!empty($dd['do'])) { $yDos[] = LalKitabData::planetHi($p) . ': ' . $dd['do'][0]; }
                        if (!empty($dd['dont'])) { $yDonts[] = LalKitabData::planetHi($p) . ': ' . $dd['dont'][0]; }
                    }
                    $out[] = [
                        'name'      => $name,
                        'desc'      => $desc,
                        'planets'   => $pair,
                        'pair_hi'   => $a . '-' . $b,
                        'house'     => $h,
                        'house_ord' => LalKitabData::houseOrdinalHi($h),
                        'remedies'  => array_values(array_filter($rem)),
                        'dos'       => $yDos,
                        'donts'     => $yDonts,
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
    private static function scorePlanets(array &$planets, array $supt, array $yutiDosha, array $active, ?int $age, array $masnui = []): void
    {
        // A harmful मसनूई formation is remedied through its two source planets —
        // never through the manufactured one — so the urgency lands on the pair.
        $masnuiBad = [];
        foreach ($masnui as $m) {
            if (($m['verdict'] ?? '') !== 'अशुभ') { continue; }
            foreach ($m['planets'] as $mp) {
                $masnuiBad[$mp] = 'मसनूई ' . $m['label'] . ' (' . $m['house_ord'] . ' भाव) अशुभ';
            }
        }
        $inDosha = [];
        foreach ($yutiDosha as $d) {
            foreach ($d['planets'] as $p) { $inDosha[$p] = $d['name']; }
        }
        $gc = LalKitabData::section('grah_chakra');
        // लाल किताब की अपनी दशा — जो ग्रह अभी 35-साला चक्र में चल रहा है, उसके उपाय
        // पहले। विंशोत्तरी यहाँ नहीं आती (वह केवल वैदिक पक्ष की है)।
        $running = $age !== null ? (string) (LalKitabDasha::at($age)['planet'] ?? '') : '';

        foreach ($planets as &$pl) {
            $p = $pl['planet'];
            $score = 0;
            $reasons = [];

            if ($running !== '' && $running === $p) {
                $score += 3; $reasons[] = 'अभी इसी की 35-साला दशा चल रही है (+3)';
            }
            if ($pl['status'] === 'नीच') { $score += 2; $reasons[] = 'नीच भाव (+2)'; }
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
            if (isset($masnuiBad[$p])) { $score += 2; $reasons[] = $masnuiBad[$p] . ' (+2)'; }
            // टक्कर खाए ग्रह को भी उपाय-प्राथमिकता में लाएँ
            foreach (($pl['in_hits'] ?? []) as $ih) {
                if (($ih['kind'] ?? '') === 'टकराव') { $score += 2; $reasons[] = 'टक्कर खा रहा है (+2)'; break; }
            }
            // अपंग अवस्थाएँ
            if (in_array($pl['impair_code'] ?? '', ['SOYA', 'GUNGA', 'BEHRA', 'ANDHA', 'MRIT', 'LANGDA'], true)) {
                $w = ($pl['impair_code'] === 'MRIT') ? 3 : 1;
                $score += $w; $reasons[] = ($pl['impair'] ?? 'अपंग') . ' ग्रह (+' . $w . ')';
            }
            // पक्का घर = तीव्रता: अशुभ ग्रह अपने पक्के घर में ⇒ उपाय और ज़्यादा ज़रूरी
            if (!empty($pl['pukka']) && !empty($pl['is_ashubh'])) {
                $score += 2; $reasons[] = 'अशुभ ग्रह अपने पक्के घर में — डैमेज तीव्र (+2)';
            }
            if ($pl['status'] === 'उच्च' && empty($pl['uch_bhang'])) { $score -= 1; $reasons[] = 'उच्च भाव — कम ज़रूरी (−1)'; }

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
            $ruling = LalKitabDasha::at($age)['planet'];
            foreach ($gc as $p => $row) {
                if ($p === $ruling) {
                    $yearEff[] = LalKitabData::planetHi($p);
                }
                if (!empty($row['ashubh']) && preg_match_all('/\d+/', (string) $row['ashubh'], $m)
                    && in_array((string) $age, $m[0], true)) {
                    $yearBad[] = LalKitabData::planetHi($p);
                }
            }
        }

        // लाल किताब की चल रही दशा — 35-साला चक्र (विंशोत्तरी नहीं)
        // दशा का अपना कोई स्वभाव नहीं होता: फल उस ग्रह की हालत का है जो चल रहा है।
        // शासक ग्रह निष्क्रिय हो तो अवधि "मंदी" नहीं, "ठहराव" की होती है — ये साल
        // बिना ख़ास हलचल के निकलते हैं। दोनों को एक शब्द से कहना अलग-अलग ज़िंदगियों
        // को एक कह देना है।
        $dasha = null;
        if ($age !== null) {
            $d = LalKitabDasha::at($age);
            $lord = $d['planet'] ?? null;
            $dasha = $mk($lord);
            if ($dasha !== null) {
                $ruler = $byKey[$lord] ?? null;
                $rv = (string) ($ruler['verdict'] ?? 'मध्यम');
                $dasha['from'] = $d['from'] ?? null;
                $dasha['to'] = $d['to'] ?? null;
                $dasha['cycle'] = $d['cycle'] ?? null;
                $dasha['kshamata'] = (string) ($ruler['kshamata'] ?? '');
                $dasha['period_type'] = $rv === 'निष्क्रिय' ? 'ठहराव'
                    : ($rv === 'शुभ' ? 'शुभ' : ($rv === 'अशुभ' ? 'मंदा' : 'मिश्रित'));
                // समाप्ति — आयु और (जन्म-वर्ष ज्ञात हो तो) अनुमानित सन् दोनों।
                // अंत-तिथि के साथ कठिन दौर सँभाला जा सकता है; बिना अंत के वही दौर डर बन जाता है।
                $endAge = $d['to'] ?? null;
                $curYear = (int) date('Y');
                $dasha['ends_at_age'] = $endAge;
                $dasha['ends_year'] = $endAge !== null ? $curYear + max(0, (int) $endAge - $age) : null;
                $dasha['ends_hi'] = $endAge !== null
                    ? 'आयु ' . $endAge . ' वर्ष तक' . ($dasha['ends_year'] !== null ? ' (लगभग सन् ' . $dasha['ends_year'] . ')' : '')
                    : '';
                $dasha['phal_hi'] = $dasha['period_type'] === 'ठहराव'
                    ? 'ये साल बिना ख़ास हलचल के निकलेंगे — न बड़ी तरक़्क़ी, न बड़ी मुसीबत। यह बुरा समय नहीं, रुका हुआ समय है।'
                    : ($dasha['period_type'] === 'शुभ'
                        ? 'इस दौर में इस ग्रह के मामले आगे बढ़ेंगे — मेहनत का फल मिलेगा।'
                        : ($dasha['period_type'] === 'मंदा'
                            ? 'इस दौर में दबाव रहेगा — सँभलकर चलें और उपाय साथ रखें।'
                            : 'इस दौर में कुछ बातें ठीक, कुछ में अड़चन।'));
                // कोई भी दौर पूरा बुरा नहीं होता — जो सहारा दे रहा है वह भी बताना ज़रूरी है
                $support = [];
                foreach ($planets as $sp) {
                    if (($sp['verdict'] ?? '') === 'शुभ') {
                        $support[] = $sp['hi'] . ' (' . $sp['house_ord'] . ' भाव)';
                    }
                }
                $dasha['supported_hi'] = $support === []
                    ? [] : array_slice($support, 0, 3);
            }
        }

        $ss = $active['sadesati'] ?? null;
        return [
            'dasha'     => $dasha,
            // साढ़े साती लाल किताब की अपनी विधि नहीं है — इसकी पहचान वैदिक गोचर से
            // होती है। इसलिए हर पंक्ति पर स्रोत का लेबल, ताकि पढ़ने वाला जाने कि यह
            // बात कहाँ से आई। उपाय फिर भी लाल किताब का ही रहता है।
            'sadesati'  => is_array($ss) ? [
                'label' => (($ss['kind'] ?? '') === 'dhaiya') ? 'ढैय्या' : 'साढ़े साती',
                'phase' => $ss['phase'] ?? null,
                'source_label' => 'वैदिक आधार पर',
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
     * उपाय की श्रेणी / लागत — an effort-graded action plan for the afflicted
     * planets so the native knows which remedies to start with. Tiers, easiest
     * first:
     *   ⚡ तुरंत/सरल  — शीघ्र उपाय (immediate, no cost)
     *   🎯 मुख्य      — भावगत उपाय (regular टोटके, 40–43 दिन)
     *   🛕 पूजा-दान   — उपासना/पाठ व दान
     *   🏺 बड़े उपाय   — स्थापना/धारण (one-time, may cost)
     *
     * @param list<array<string,mixed>> $planets
     * @return array<string,list<array{hi:string,text:string}>>
     */
    private static function remedyPlan(array $planets, array $masnui = [], array $situations = []): array
    {
        $sh  = LalKitabData::section('sheeghra');
        $bg  = LalKitabData::section('bhavgat_upay');
        $pd  = LalKitabData::section('puja_daan');
        $stv = LalKitabData::section('sthapana_vastu');

        $tiers = ['sthiti' => [], 'quick' => [], 'main' => [], 'worship' => [], 'big' => [], 'masnui' => []];
        // स्थिति-आधारित उपाय — टक्करें, बुनियाद, रतौंध, सोया आदि (हर एक अपने दर्जे सहित)
        foreach ($situations as $s) {
            foreach (($s['upay'] ?? []) as $t) {
                $tiers['sthiti'][] = ['hi' => (string) ($s['label'] ?? ''),
                    'text' => (string) $t, 'darja' => (string) ($s['darja'] ?? 'final')];
            }
        }
        // मसनूई formations are remedied as a unit, through the two source planets.
        foreach ($masnui as $m) {
            if (($m['verdict'] ?? '') === 'शुभ') { continue; }
            foreach ($m['remedies'] as $t) {
                $tiers['masnui'][] = [
                    'hi'   => 'मसनूई ' . $m['label'] . ' — ' . $m['house_ord'] . ' भाव (' . $m['pair_hi'] . ')',
                    'text' => (string) $t,
                ];
            }
        }
        // हर वह ग्रह जिसे उपाय चाहिए (अशुभ · नीच · टक्कर · सोया/अपंग · भंग · दोष) —
        // 'मध्यम' पर भी, यदि नकारात्मक चिह्न हो।
        foreach ($planets as $pe) {
            if (empty($pe['need_remedy'])) { continue; }
            $p = $pe['planet'];
            $hi = $pe['hi'];
            $h = (int) $pe['house'];
            if (!empty($sh[$p])) {
                $tiers['quick'][] = ['hi' => $hi, 'text' => (string) $sh[$p]];
            }
            foreach (array_slice($bg[$p][(string) $h] ?? [], 0, 3) as $t) {
                $tiers['main'][] = ['hi' => $hi, 'text' => (string) $t];
            }
            if (!empty($pd[$p]['upasana'])) { $tiers['worship'][] = ['hi' => $hi, 'text' => 'उपासना/पाठ: ' . $pd[$p]['upasana']]; }
            if (!empty($pd[$p]['daan'])) { $tiers['worship'][] = ['hi' => $hi, 'text' => 'दान: ' . $pd[$p]['daan']]; }
            if (!empty($stv[$p])) { $tiers['big'][] = ['hi' => $hi, 'text' => (string) $stv[$p]]; }
        }
        return $tiers;
    }

    /**
     * Flatten every situation-based finding into one list for the remedy plan,
     * each keeping its label, remedies and darja (final / anumanit / lambit).
     *
     * @return list<array{label:string,upay:list<string>,darja:string}>
     */
    private static function gatherSituations(array $collisions, array $buniyad, ?array $mukabla, array $supt, array $special): array
    {
        $out = [];
        foreach ($collisions as $c) {
            $out[] = ['label' => $c['kind'] . ' — ' . $c['house_ord'] . ' भाव',
                'upay' => $c['upay'] ?? [], 'darja' => 'final'];
        }
        foreach ($buniyad as $b) {
            $out[] = ['label' => 'बुनियाद बिगड़ी — ' . $b['house_ord'] . ' भाव (जड़ ' . $b['root_ord'] . ')',
                'upay' => $b['upay'] ?? [], 'darja' => 'final'];
        }
        if ($mukabla !== null) {
            $out[] = ['label' => 'सांझी गद्दी की दुश्मनी — आठवें भाव',
                'upay' => $mukabla['upay'] ?? [], 'darja' => 'final'];
        }
        foreach ($supt as $s) {
            $out[] = ['label' => $s['state'] . ' ग्रह — ' . $s['hi'],
                'upay' => $s['upay'] ?? [], 'darja' => (string) ($s['darja'] ?? 'anumanit')];
        }
        if (!empty($special['ratandh'])) {
            $out[] = ['label' => 'रतौंध कुंडली', 'upay' => $special['ratandh_upay'] ?? [], 'darja' => 'final'];
        }
        return $out;
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
            // आयु को कभी अंक/मृत्यु के रूप में न दिखाएँ — 5 वर्गों में बाँटें।
            $ayuRaw = (string) ($r['ayu'] ?? '');
            $band = ''; $bandNote = '';
            if (preg_match('/\d+/', $ayuRaw, $mn)) {
                $yrs = (int) $mn[0];
                foreach (LalKitabData::AYU_BAND as $bnd) {
                    if ($yrs >= $bnd['from'] && $yrs < $bnd['to']) { $band = $bnd['band']; $bandNote = $bnd['note']; break; }
                }
                if ($band === '') { $band = LalKitabData::AYU_BAND[0]['band']; $bandNote = LalKitabData::AYU_BAND[0]['note']; }
            }
            $yogaOut[] = ['yog' => $yog, 'band' => $band, 'band_note' => $bandNote,
                'applies' => $applies, 'why' => $why];
        }

        $gc = LalKitabData::section('grah_chakra');
        $chakra = [];
        foreach (self::PLANETS as $p) {
            if (isset($gc[$p])) {
                $chakra[] = [
                    'hi'      => LalKitabData::planetHi($p),
                    'prabhav' => self::dashaSpanHi($p),
                    'vishesh' => $gc[$p]['vishesh'] ?? '',
                    'ashubh'  => $gc[$p]['ashubh'] ?? '',
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
     * लाल किताब वर्ष कुंडली (annual chart) for a chosen age-year.
     *
     * The वर्ष कुंडली ज्ञान चक्र (varsh_gyan) gives, per age-year, a 12-house
     * permutation: which जन्म-भाव (natal house) content activates in each house
     * of THAT year's chart. We rotate the natal Lal Kitab placements through the
     * permutation and re-run the SAME Lal Kitab reading pipeline (planet / house
     * / yoga / remedy / करें-न करें) on the rotated placements — so the annual
     * chart is read with Lal Kitab rules, predictions and remedies, NOT the
     * birth chart. Also returns इस वर्ष का सार + a कब-क्या-होगा timeline for the
     * selected year.
     *
     * @param array<string,mixed> $chart D1 chart from CalculationEngine
     * @param int $age target age-year (1..96)
     * @return array<string,mixed>
     */
    public static function varshReading(array $chart, int $age): array
    {
        if (empty($chart['planets']) || empty($chart['ascendant'])) {
            return ['ok' => false, 'error' => 'चार्ट उपलब्ध नहीं'];
        }
        if ($age < 1)  { $age = 1; }
        if ($age > 96) { $age = 96; }

        // ---- natal Lal Kitab placements (planet => natal house) ----
        $janamHouse = [];
        foreach (self::PLANETS as $p) {
            if (!isset($chart['planets'][$p])) { continue; }
            $h = (int) ($chart['planets'][$p]['house'] ?? 0);
            if ($h >= 1 && $h <= 12) { $janamHouse[$p] = $h; }
        }

        // ---- वर्ष चक्र permutation for this age ----
        $tbl = LalKitabData::section('varsh_gyan');
        $row = $tbl[(string) $age] ?? null;
        if (!is_array($row) || count($row) < 12) {
            $row = [];
            for ($i = 1; $i <= 12; $i++) { $row[] = (string) $i; }
        }
        // varsh house (i+1) carries natal house row[i]; invert to place planets.
        $varshHouseOf = [];   // natal-house-number => varsh house
        for ($i = 0; $i < 12; $i++) {
            $jhn = (int) $row[$i];
            if ($jhn >= 1 && $jhn <= 12) { $varshHouseOf[$jhn] = $i + 1; }
        }

        // ---- rotate placements into the varsh chart ----
        $vHouse = [];
        $vOccupants = array_fill(1, 12, []);
        foreach ($janamHouse as $p => $jh) {
            $vh = $varshHouseOf[$jh] ?? $jh;
            $vHouse[$p] = $vh;
            $vOccupants[$vh][] = $p;
        }

        // ---- fixed-Aries varsh grid + north-chart payload ----
        $abbr = ['Sun' => 'Su', 'Moon' => 'Mo', 'Mars' => 'Ma', 'Mercury' => 'Me',
            'Jupiter' => 'Ju', 'Venus' => 'Ve', 'Saturn' => 'Sa', 'Rahu' => 'Ra', 'Ketu' => 'Ke'];
        $grid = [];
        $north = ['asc_sign' => 0, 'planets' => []];
        for ($h = 1; $h <= 12; $h++) {
            $sIdx = LalKitabData::HOUSE_SIGN[$h];
            $lord = LalKitabData::HOUSE_LORD[$h];
            $grid[$h] = [
                'house'      => $h,
                'sign'       => $sIdx,
                'sign_hi'    => LalKitabData::signHi(Charts::SIGNS[$sIdx]),
                'lord'       => $lord,
                'lord_hi'    => LalKitabData::planetHi($lord),
                'planets'    => $vOccupants[$h],
                'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $vOccupants[$h]),
                'from_janam' => (int) $row[$h - 1],   // natal house this varsh-house carries
            ];
        }
        foreach ($vHouse as $p => $vh) {
            $north['planets'][] = ['abbr' => $abbr[$p] ?? $p, 'sign' => $vh - 1, 'deg' => 0,
                'retro' => !empty($chart['planets'][$p]['retro'])];
        }

        // ---- run the Lal Kitab reading pipeline on the ROTATED placements ----
        $supt      = self::suptReadings($vHouse);
        $yutiDosha = self::yutiDoshaReadings($vOccupants);
        $planets   = self::planetReadings($chart, $vHouse, $vOccupants, $supt, $yutiDosha);
        self::scorePlanets($planets, $supt, $yutiDosha, [], $age);
        $houses    = self::houseReadings($vHouse, $vOccupants, $planets);
        $general   = self::generalOverview($planets);
        $yoga      = self::yogaReadings($vHouse);
        $remedy    = self::remedyReadings($vHouse, $vOccupants);
        $saar      = self::varshSaar($planets, $houses, $age);

        // ---- 0–100 शुभता-अंक for the year (drives the red→green signal bar) ----
        $sh = count($saar['shubh']); $as = count($saar['ashubh']);
        $asleep = 0; foreach ($planets as $p) { if (!empty($p['asleep'])) { $asleep++; } }
        $score = 50 + ($sh - $as) * 9 - $asleep * 2;
        $score = max(6, min(97, $score));

        $lagnaSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $moonSign  = (int) ($chart['planets']['Moon']['sign_index'] ?? 0);

        return [
            'ok'         => true,
            'age'        => $age,
            'row'        => $row,
            'grid'       => $grid,
            'north'      => $north,
            'planets'    => $planets,
            'houses'     => $houses,
            'general'    => $general,
            'yoga'       => $yoga,
            'yuti_dosha' => $yutiDosha,
            'remedy'     => $remedy,
            'saar'       => $saar,
            'score'      => $score,
            'lagna_hi'   => LalKitabData::signHi(Charts::SIGNS[$lagnaSign]),
            'moon_hi'    => LalKitabData::signHi(Charts::SIGNS[$moonSign]),
            'shubh_cnt'  => $sh,
            'ashubh_cnt' => $as,
        ];
    }

    /**
     * इस वर्ष का सार — the plain-language annual summary + कब-क्या-होगा events for
     * the selected age, built from the rotated planet/house verdicts plus the
     * ग्रह-चक्र (prabhav/ashubh/vishesh) & सुप्त-ग्रह (awakening) year tables.
     *
     * @param list<array<string,mixed>> $planets rotated planetReadings output
     * @param array<int,array<string,mixed>> $houses rotated houseReadings output
     * @return array<string,mixed>
     */
    private static function varshSaar(array $planets, array $houses, int $age): array
    {
        $gc = LalKitabData::section('grah_chakra');
        $sg = LalKitabData::section('supt_grah');

        // planets शुभ / अशुभ this year
        $shubhP = []; $ashubhP = [];
        foreach ($planets as $p) {
            if (($p['verdict'] ?? '') === 'शुभ')  { $shubhP[]  = $p['hi']; }
            elseif (($p['verdict'] ?? '') === 'अशुभ') { $ashubhP[] = $p['hi']; }
        }

        // house life-areas that shine / need care this year
        $goodAreas = []; $cautionAreas = [];
        foreach ($houses as $h => $hr) {
            $topic = LalKitabData::HOUSE_TOPIC[$h] ?? '';
            if ($topic === '') { continue; }
            if (($hr['verdict'] ?? '') === 'शुभ' && $hr['planets'] !== []) {
                $goodAreas[] = ['ord' => $hr['house_ord'], 'topic' => $topic];
            } elseif (($hr['verdict'] ?? '') === 'अशुभ') {
                $cautionAreas[] = ['ord' => $hr['house_ord'], 'topic' => $topic];
            }
        }
        $goodAreas    = array_slice($goodAreas, 0, 5);
        $cautionAreas = array_slice($cautionAreas, 0, 5);

        // कब क्या होगा — events that fall exactly on this age-year
        $events = [];
        foreach (self::PLANETS as $en) {
            $hi = LalKitabData::planetHi($en);
            if (LalKitabDasha::at($age)['planet'] === $en) {
                $events[] = ['tone' => 'pos', 'text' => '🕰️ इस वर्ष ' . $hi . ' की दशा चल रही है — इसके कारक विषय ही वर्ष का मुख्य स्वर तय करेंगे।'];
            }
            if (in_array($age, self::parseYears((string) ($gc[$en]['ashubh'] ?? '')), true)) {
                $events[] = ['tone' => 'neg', 'text' => '⚠️ ' . $hi . ' का सावधानी वर्ष — इसके कारक विषयों में सतर्कता व उपाय रखें।'];
            }
            if (trim((string) ($gc[$en]['vishesh'] ?? '')) !== ''
                && in_array($age, self::parseYears((string) ($gc[$en]['vishesh'] ?? '')), true)) {
                $events[] = ['tone' => 'info', 'text' => '✨ ' . $hi . ' — ' . trim((string) $gc[$en]['vishesh'])];
            }
            if (in_array($age, self::parseYears((string) ($sg[$en]['aayu'] ?? '')), true)) {
                $events[] = ['tone' => 'info', 'text' => '⏳ ' . $hi . ' जागृत होगा — ' . trim((string) ($sg[$en]['jagega'] ?? '')) . ' पर इसका फल सक्रिय।'];
            }
        }

        // aggregate करें / न करें / उपाय — priority to the अशुभ planets
        $srcP = array_values(array_filter($planets, static fn ($p) => ($p['verdict'] ?? '') === 'अशुभ'));
        if ($srcP === []) { $srcP = $planets; }
        $dos = []; $donts = []; $rem = [];
        foreach ($srcP as $p) {
            foreach (($p['dos'] ?? []) as $d)   { $dos[]   = $p['hi'] . ': ' . $d; }
            foreach (($p['donts'] ?? []) as $d) { $donts[] = $p['hi'] . ': ' . $d; }
            if (!empty($p['need_remedy'])) {
                foreach (array_slice($p['remedies'] ?? [], 0, 2) as $r) {
                    $rem[] = $p['hi'] . ' (' . $p['house_ord'] . ' भाव): ' . $r;
                }
            }
        }
        $dos   = array_slice(array_values(array_unique($dos)), 0, 8);
        $donts = array_slice(array_values(array_unique($donts)), 0, 8);
        $rem   = array_slice(array_values(array_unique($rem)), 0, 8);

        $tone = count($ashubhP) > count($shubhP) ? 'neg' : (count($shubhP) > count($ashubhP) ? 'pos' : 'mix');
        $headline = 'इस वर्ष (आयु ' . $age . ') की लाल किताब वर्ष-कुंडली में ' . count($shubhP)
            . ' ग्रह शुभ व ' . count($ashubhP) . ' ग्रह अशुभ स्थिति में हैं। '
            . ($goodAreas !== [] ? 'अनुकूल: ' . implode('; ', array_map(static fn ($a) => $a['topic'], $goodAreas)) . '। ' : '')
            . ($cautionAreas !== [] ? 'सावधानी: ' . implode('; ', array_map(static fn ($a) => $a['topic'], $cautionAreas)) . '।' : '');

        // upcoming mini-timeline (this age → +5)
        $timeline = [];
        for ($a = $age; $a <= $age + 5 && $a <= 100; $a++) {
            $bits = [];
            foreach (self::PLANETS as $en) {
                $hi = LalKitabData::planetHi($en);
                if (LalKitabDasha::at($a)['planet'] === $en) { $bits[] = ['tone' => 'pos', 't' => $hi . ' दशा']; }
                if (in_array($a, self::parseYears((string) ($gc[$en]['ashubh'] ?? '')), true))  { $bits[] = ['tone' => 'neg', 't' => $hi . ' सावधानी']; }
                if (in_array($a, self::parseYears((string) ($sg[$en]['aayu'] ?? '')), true))     { $bits[] = ['tone' => 'info', 't' => $hi . ' जागृति']; }
            }
            if ($bits !== []) { $timeline[] = ['age' => $a, 'bits' => $bits]; }
        }

        return [
            'headline' => $headline,
            'tone'     => $tone,
            'shubh'    => $shubhP,
            'ashubh'   => $ashubhP,
            'good'     => $goodAreas,
            'caution'  => $cautionAreas,
            'events'   => $events,
            'dos'      => $dos,
            'donts'    => $donts,
            'remedies' => $rem,
            'timeline' => $timeline,
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
    /**
     * सोया / अपंग ग्रह — a planet is सोया when the house it aspects (one-way) is
     * empty; the deeper गूंगा/बहरा/अंधा/मृत/लंगड़ा states nest above it. Each
     * carries its चाबी (the planet that wakes the empty aspected house) and its
     * self-awakening age. Only planets in a non-normal state are returned.
     *
     * @param array<string,int> $house
     * @param array<int,list<string>> $occupants
     * @return list<array<string,mixed>>
     */
    private static function suptReadings(array $house, array $occupants = []): array
    {
        $sb = LalKitabData::section('supt_bhav');   // house => चाबी (waking planet, Hindi)
        $sg = LalKitabData::section('supt_grah');   // planet => when/age/malefic
        $out = [];
        foreach (self::PLANETS as $p) {
            if (!isset($house[$p])) { continue; }
            $h = $house[$p];
            $dig = LalKitabTeva::dignity($p, $h, $occupants);
            $imp = LalKitabTeva::impairedState($p, $h, $occupants, $dig);
            if ($imp['state'] === '') { continue; }

            // the empty aspected house(s) this planet is "asleep over", and their चाबी
            $emptyAspected = [];
            foreach (array_keys(LalKitabTeva::aspectsFrom($h)) as $t) {
                if (empty($occupants[$t])) {
                    $emptyAspected[] = ['house' => $t, 'ord' => LalKitabData::houseOrdinalHi($t),
                        'chaabi' => (string) ($sb[(string) $t] ?? '')];
                }
            }
            $sit = LalKitabData::SITUATION[$imp['code']] ?? [];
            $out[] = [
                'hi'          => LalKitabData::planetHi($p),
                'house'       => $h,
                'house_ord'   => LalKitabData::houseOrdinalHi($h),
                'state'       => $imp['state'],
                'code'        => $imp['code'],
                'phal'        => (string) ($sit['phal'] ?? ''),
                'upay'        => $sit['upay'] ?? [],
                'darja'       => (string) ($sit['darja'] ?? 'anumanit'),
                'empty_aspected' => $emptyAspected,
                'jagega'      => $sg[$p]['jagega'] ?? '',
                'aayu'        => $sg[$p]['aayu'] ?? '',
                'ashubh'      => $sg[$p]['ashubh'] ?? '',
            ];
        }
        return $out;
    }

    /**
     * वर्जित-उपाय चेतावनी — remedies FORBIDDEN for this chart. Each वर्जित /
     * दान-निषेध rule's baked condition (planet(s)-in-house(s), mode and/or/single)
     * is checked against the placements; only the rules that actually apply are
     * flagged, so the native never does a remedy that would harm them.
     *
     * @param array<string,int> $house
     * @return array{forbidden:list<array<string,mixed>>, general:list<string>}
     */
    private static function varjitReadings(array $house): array
    {
        $match = static function (?array $cond) use ($house): bool {
            if (!is_array($cond) || empty($cond['clauses'])) { return false; }
            $hit = static function (array $cl) use ($house): bool {
                $p = $cl['planet'] ?? '';
                $hs = $cl['houses'] ?? [];
                return isset($house[$p]) && ($hs === [] || in_array($house[$p], $hs, true));
            };
            $mode = $cond['mode'] ?? 'single';
            if ($mode === 'and') {
                foreach ($cond['clauses'] as $cl) { if (!$hit($cl)) { return false; } }
                return true;
            }
            // single / or → any clause matches
            foreach ($cond['clauses'] as $cl) { if ($hit($cl)) { return true; } }
            return false;
        };

        $forbidden = [];
        $general = [];
        // वर्जित उपाय
        foreach (LalKitabData::section('varjit') as $r) {
            if ($match($r['cond'] ?? null)) {
                $forbidden[] = [
                    'sthiti'  => (string) ($r['sthiti'] ?? ''),
                    'varjit'  => (string) ($r['varjit'] ?? ''),
                    'parinam' => (string) ($r['parinam'] ?? ''),
                    'src'     => 'वर्जित उपाय',
                ];
            }
        }
        // दान-निषेध (skip the "सामान्य नियम" general row into $general)
        foreach (LalKitabData::section('daan_nishedh') as $r) {
            $cond = $r['cond'] ?? null;
            if ($cond === null) {
                if (trim((string) ($r['varjit'] ?? '')) !== '') { $general[] = (string) $r['varjit']; }
                continue;
            }
            if ($match($cond)) {
                $forbidden[] = [
                    'sthiti'  => (string) ($r['sthiti'] ?? ''),
                    'varjit'  => (string) ($r['varjit'] ?? ''),
                    'parinam' => '',
                    'src'     => 'दान-निषेध',
                ];
            }
        }
        return ['forbidden' => $forbidden, 'general' => $general];
    }

    /**
     * विशेष निष्फल/दुर्बल अवस्थाएँ — conditions in which a planet cannot give its
     * result properly (Lal Kitab "अंधा/रतांध" family), computed exactly:
     *   - अस्त (combust): planet within the classical combustion orb of the Sun
     *     (astronomical — Moon 12°, Mars 17°, Mercury 13°, Jupiter 11°, Venus 9°,
     *     Saturn 15°); such a planet is "जला हुआ" and gives feeble result.
     *   - रतांध ग्रह योग: सूर्य 4थे व शनि 7वें भाव में (भविष्यवाणी सूत्र 12).
     *   - नीच ग्रह: debilitated planets (weak/blind-like result).
     *
     * @param array<string,mixed> $chart
     * @param array<string,int> $house
     * @param list<array<string,mixed>> $planets   planetReadings output (for नीच)
     * @return array<string,mixed>
     */
    private static function specialStates(array $chart, array $house, array $planets): array
    {
        // ग्रह अस्त (combust) is a Vedic concept — "लाल किताब के नियम के अनुसार
        // ग्रह अस्त नहीं होता", so it is not computed here.
        // रतौंध कुंडली — भाव 4 में सूर्य व भाव 7 में शनि (Farman-confirmed).
        $ratandh = (($house['Sun'] ?? 0) === 4) && (($house['Saturn'] ?? 0) === 7);
        $ratandhSit = LalKitabData::SITUATION['RATANDH'];
        // नीच ग्रह from the computed (house-based) planet analysis.
        $neech = [];
        foreach ($planets as $pe) {
            if (($pe['status'] ?? '') === 'नीच') {
                $neech[] = ['hi' => $pe['hi'], 'house_ord' => $pe['house_ord'],
                    'tier' => $pe['status_tier'] ?? ''];
            }
        }
        return [
            'ratandh'      => $ratandh,
            'ratandh_phal' => $ratandh ? $ratandhSit['phal'] : '',
            'ratandh_upay' => $ratandh ? $ratandhSit['upay'] : [],
            'neech'        => $neech,
        ];
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
            $aspects = LalKitabTeva::aspectsFrom($h);           // [house => pct], one-way forward
            $takkarHouse = LalKitabData::TAKKAR[$h] ?? null;    // whom this house strikes
            $out[] = [
                'house'      => $h,
                'house_ord'  => LalKitabData::houseOrdinalHi($h),
                'planets_hi' => array_map([LalKitabData::class, 'planetHi'], $occupants[$h]),
                'drishti'    => array_keys($aspects),
                'drishti_pct' => $aspects,
                'drishti_p'  => $planetsInHi(array_keys($aspects)),
                'takkar'     => $takkarHouse !== null ? [$takkarHouse] : [],
                'takkar_p'   => $takkarHouse !== null ? $planetsInHi([$takkarHouse]) : [],
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
