<?php

declare(strict_types=1);

namespace AutoBusiness\Astro\LalKitab;

/**
 * लाल किताब प्रक्रिया-परत — चरण 7 से 10
 * ------------------------------------------------------------------
 * LalKitabEngine::compute() सब कुछ *निकालता* है — नौ ग्रह, बारह भाव, ऋण,
 * मसनूई, टक्करें, दशा, और हर चीज़ के उपाय। पर वह कुछ भी *चुनता* नहीं। नतीजा
 * एक विश्वकोश है, भविष्यवाणी नहीं: पढ़ने वाले को खुद तय करना पड़ता है कि इन
 * तीस अनुभागों में उसके लिए ज़रूरी क्या है।
 *
 * यह क्लास वही चुनाव करती है, उसी क्रम में जिसमें एक अनुभवी ज्योतिषी करता है:
 *
 *   चरण 7  विरोधाभास का हल — दो उलटी बातों का औसत नहीं निकाला जाता। पहले देखो
 *          कि विरोध सचमुच है भी या नहीं (अलग-अलग क्षेत्र की बातें विरोध नहीं
 *          होतीं), फिर भार-क्रम से तय करो, वरना ईमानदारी से "अनिर्णीत" कहो।
 *   चरण 8  निचोड़ — सब कुछ बता देना निचोड़ नहीं है। तीन से पाँच बातें, ताक़त
 *          पहले, हर कठिनाई अपने उपाय के साथ।
 *   चरण 9  उपाय — एक, ज़्यादा से ज़्यादा दो। दिशा पहले तय होती है (शांति /
 *          बल-वृद्धि / जगाना), निशाना प्रायः दूसरे ग्रह पर होता है, और जो ग्रह
 *          इस वक़्त बचा रहा है उसे छेड़ा नहीं जाता।
 *   चरण 10 प्रस्तुति — हर आंतरिक दर्जे का एक तयशुदा सरल-हिंदी वाक्य, ताकि
 *          "अस्थिर" और "मिश्रित", "निष्क्रिय" और "मंदा" एक जैसे न पढ़े जाएँ।
 *
 * यह परत इंजन के ऊपर बैठती है और उसका कोई फल नहीं बदलती — सिर्फ़ छाँटती,
 * क्रम लगाती और सरल भाषा में कहती है।
 */
final class LalKitabProcess
{
    /**
     * जातक से पूछे जाने वाले छह सवाल। लाल किताब के बहुत से उपाय इन्हीं पर पलटते
     * हैं — किराये के मकान की नींव नहीं खोदी जा सकती, और जो ख़ुद सबसे बड़ा है उसे
     * "बड़े भाई की सेवा" नहीं कही जा सकती। हालत मालूम न हो तो अंदाज़ा लगाने के
     * बजाय वैसा उपाय रोक दिया जाता है।
     *
     * क्रम वही है जिसमें फ़ॉर्म में दिखते हैं। values की पहली प्रविष्टि हमेशा
     * "अज्ञात" (खाली) मानी जाती है, इसलिए यहाँ सिर्फ़ असली जवाब लिखे हैं।
     *
     * @var array<string,array{q:string,values:list<string>}>
     */
    public const NATIVE_FIELDS = [
        'father_living'  => ['q' => 'पिता जीवित?',        'values' => ['हाँ', 'नहीं']],
        'mother_living'  => ['q' => 'माता जीवित?',        'values' => ['हाँ', 'नहीं']],
        'marital_status' => ['q' => 'वैवाहिक स्थिति',      'values' => ['विवाहित', 'अविवाहित']],
        'children'       => ['q' => 'संतान है?',           'values' => ['हाँ', 'नहीं']],
        'eldest'         => ['q' => 'भाई-बहनों में सबसे बड़े?', 'values' => ['हाँ', 'नहीं']],
        'own_home'       => ['q' => 'घर अपना या किराये का?', 'values' => ['अपना', 'किराये का']],
    ];

    /**
     * चल रही सेटिंग्स — दो इंजन अलग सेटिंग पर अलग नतीजे देंगे, इसलिए हर रिपोर्ट
     * के सिरहाने दर्ज रहता है कि किस नियम पर चला गया।
     *
     * यह पहले जड़ा हुआ स्थिरांक था। दिक़्क़त यह थी कि सेटिंग बदलने पर भी यहाँ वही
     * पुराना मान छपता रहता — यानी रिपोर्ट का सिरहाना झूठ बोल सकता था। अब यह
     * LalKitabSettings से बनता है, इसलिए दोनों कभी अलग नहीं हो सकते।
     *
     * @return array<string,string|bool>
     */
    private static function settings(): array
    {
        return [
            'soya_drishti'    => LalKitabSettings::get('soya_drishti'),
            'seat_precedence' => (string) (LalKitabSettings::FIELDS['seat_precedence']['options']
                [LalKitabSettings::get('seat_precedence')] ?? ''),
            'chhaya_company'  => LalKitabSettings::get('chhaya_company'),
            'rin_severity'    => LalKitabSettings::get('rin_severity'),
            'mrit_avastha'    => LalKitabSettings::get('mrit_avastha'),
            'masnui_drishti'  => false,     // मसनूई ग्रह दृष्टि नहीं डालता — यह विवादित नहीं
        ];
    }

    /**
     * चरण 10 §10.2 — शब्दों का नक्शा। यह जड़ा हुआ है, हर बार नया नहीं गढ़ा जाता;
     * वरना कुछ सौ रिपोर्ट के बाद ये भेद अपने-आप घुल जाते हैं और किसी को पता भी
     * नहीं चलता। **मोटे वाले चार सबसे ज़्यादा ग़लत पढ़े जाते हैं।**
     */
    public const SHABD = [
        'शुभ'      => 'इस मामले में अच्छा चलेगा।',
        'मंदा'     => 'यहाँ दिक़्क़त आती रहेगी।',
        'अशुभ'     => 'यहाँ दिक़्क़त आती रहेगी।',
        'मिश्रित'   => 'कुछ बातें ठीक रहेंगी, कुछ में परेशानी।',
        'मध्यम'    => 'कुछ बातें ठीक रहेंगी, कुछ में परेशानी।',
        'अस्थिर'    => 'यह मामला कभी ठीक, कभी बिगड़ा — टिकाव नहीं रहता।',
        'सुप्त'     => 'यह हिस्सा अभी रुका हुआ है। काम आगे नहीं बढ़ रहा।',
        'निष्क्रिय'  => 'यह हिस्सा अभी रुका हुआ है। काम आगे नहीं बढ़ रहा।',
        'ठहराव'    => 'ये साल बिना ख़ास हलचल के निकलेंगे।',
    ];

    /**
     * चरण 10 §10.3 — मना शब्द। पुरानी शैली इन्हें खुलकर बरतती है; यह इंजन नहीं।
     * आख़िरी वजह सिर्फ़ नरमी नहीं, सिद्धांत है: लाल किताब का पूरा उपाय-तंत्र इसी
     * आधार पर खड़ा है कि फल बदला जा सकता है। "यह तो होना ही है" कहना उसी किताब
     * का खंडन है जिससे यह सब आया है।
     */
    public const MANA_SHABD = [
        'मृत्यु', 'मौत', 'नाश', 'बर्बादी', 'तबाही', 'कुछ नहीं बचेगा',
        'कोई उपाय नहीं', 'भाग्य में नहीं', 'कट नहीं सकता', 'होना ही है',
    ];

    /**
     * पूरी प्रक्रिया चलाकर निचोड़ + उपाय + ग्राहक-पन्ना लौटाती है।
     *
     * @param array<string,mixed> $lk LalKitabEngine::compute() का पूरा आउटपुट
     * @param array<string,mixed> $native जातक की परिस्थिति (पिता/माता जीवित आदि)
     * @return array<string,mixed>
     */
    public static function run(array $lk, array $native = []): array
    {
        if (empty($lk['ok'])) {
            return ['ok' => false, 'error' => (string) ($lk['error'] ?? 'गणना उपलब्ध नहीं')];
        }
        $planets = is_array($lk['planets'] ?? null) ? $lk['planets'] : [];
        $houses  = is_array($lk['houses'] ?? null) ? $lk['houses'] : [];

        $temperament = self::temperament($planets, $houses, $lk);
        $rin         = self::rinFindings($lk, $planets, $houses);
        $conflicts   = self::conflicts($planets, $houses, $rin, $lk);
        $core        = self::corePoints($planets, $temperament, $rin, $lk);
        // उपाय निचोड़ की कठिनाइयों से चलता है, अपनी अलग सूची से नहीं — वरना रिपोर्ट
        // एक बात को मुख्य कहती है और उपाय किसी और का देती है।
        $upaay       = self::remedySequence($planets, $rin, $native, $lk, $core);
        $client      = self::clientDoc($temperament, $core, $upaay, $lk, $native);
        // हर ग्रह का अपना निचोड़ — विस्तृत पन्नों के लिए। वहाँ अब तक सब तथ्य तो थे
        // पर कोई उन्हें मिलाकर एक वाक्य नहीं कहता था, इसलिए एक ही कार्ड "मृत —
        // कारकत्व अनुपस्थित" और "लाभ मिलेगा" दोनों कह देता था।
        $briefs      = self::planetBriefs($planets, $upaay, $lk);
        $hBriefs     = self::houseBriefs($lk, $briefs);
        $kBriefs     = self::karakBriefs($lk, $planets);

        return [
            'ok'          => true,
            'settings'    => self::settings(),
            'niyam'       => LalKitabSettings::all(),
            'niyam_hi'    => LalKitabSettings::describe(),
            'niyam_default' => LalKitabSettings::allDefault(),
            'temperament' => $temperament,
            'core_points' => $core,
            'conflicts'   => $conflicts['resolved'],
            'unresolved'  => $conflicts['unresolved'],
            'density'     => $conflicts['density'],
            'data_warn'   => $conflicts['data_warn'],
            'rin'         => $rin,
            'upaay'       => $upaay,
            'client'      => $client,
            'briefs'      => $briefs,
            'house_briefs'=> $hBriefs,
            'karak_briefs'=> $kBriefs,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // चरण 8 §8.1 — कुंडली का मिज़ाज
    // ─────────────────────────────────────────────────────────────

    /**
     * किसी एक फल से पहले यह बताना कि यह *किस क़िस्म की* कुंडली है। लोग इसे
     * किसी भी अलग भविष्यवाणी से ज़्यादा पहचानते हैं — जिस आदमी की आधी कुंडली
     * सोई हो, उसने बरसों यही सोचा है कि मेहनत के बावजूद कुछ बनता क्यों नहीं,
     * और हर किसी ने उसे यही कहा है कि तुम पूरी कोशिश नहीं कर रहे।
     *
     * @param list<array<string,mixed>> $planets
     * @param list<array<string,mixed>> $houses
     * @return array<string,mixed>
     */
    private static function temperament(array $planets, array $houses, array $lk): array
    {
        $nishkriya = $ashubh = $shubh = 0;
        foreach ($planets as $p) {
            $v = (string) ($p['verdict'] ?? '');
            if ($v === 'निष्क्रिय') { $nishkriya++; }
            elseif ($v === 'अशुभ') { $ashubh++; }
            elseif ($v === 'शुभ') { $shubh++; }
        }
        // भीड़ वाले भाव व ख़ाली हिस्से
        $occ = [];
        foreach ($planets as $p) { $occ[(int) $p['house']][] = $p['hi']; }
        $heavy = 0;
        foreach ($occ as $list) { if (count($list) >= 3) { $heavy++; } }
        $usedHouses = count($occ);

        $primary = 'संतुलित';
        $line = 'ग्रह कुंडली में फैले हुए हैं — साधारण उतार-चढ़ाव, कोई ढाँचागत संकट नहीं।';
        $direction = 'mixed';
        if ($nishkriya >= 3) {
            $primary = 'बहुत कुछ सोया हुआ';
            $line = 'आपकी कुंडली में क्षमता बहुत है, पर उसका बड़ा हिस्सा अभी सोया हुआ है। '
                . 'इसीलिए मेहनत के बावजूद कई काम आगे नहीं बढ़ते — कमी कोशिश की नहीं, जागृति की है।';
            $direction = 'awaken';
        } elseif ($heavy >= 2) {
            $primary = 'भीड़ भरी कुंडली';
            $line = 'कुछ ही भाव सारा बोझ उठा रहे हैं — ज़िंदगी के दो-तीन क्षेत्रों में सब कुछ इकट्ठा है, '
                . 'बाक़ी हिस्से ख़ाली पड़े हैं।';
            $direction = 'settle';
        } elseif ($usedHouses <= 6) {
            $primary = 'एक तरफ़ झुकी कुंडली';
            $line = 'सारे ग्रह कुंडली के एक हिस्से में सिमटे हैं — कुछ क्षेत्रों में बहुत तीव्रता, '
                . 'कुछ क्षेत्र जीवन-भर अनछुए रह जाते हैं।';
            $direction = 'balance';
        } elseif ($ashubh > $shubh + 2) {
            $primary = 'दबाव वाली कुंडली';
            $line = 'इस कुंडली में अड़चनें ज़्यादा हैं — पर हर अड़चन का उपाय भी इसी किताब में है।';
            $direction = 'pacify';
        } elseif ($shubh >= 4) {
            $primary = 'मज़बूत कुंडली';
            $line = 'यह बुनियादी तौर पर मज़बूत कुंडली है — सहारे अड़चनों से ज़्यादा हैं।';
            $direction = 'strengthen';
        }
        return [
            'primary'    => $primary,
            'line'       => $line,
            'direction'  => $direction,   // उपाय की समग्र दिशा यहीं से तय होती है
            'nishkriya'  => $nishkriya,
            'ashubh'     => $ashubh,
            'shubh'      => $shubh,
            'heavy'      => $heavy,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // चरण 7 — विरोधाभास
    // ─────────────────────────────────────────────────────────────

    /**
     * भार-क्रम — कौन-सा दावा भारी है।
     *
     * लाल किताब ऐसी कोई तालिका नहीं छापती; अलग-अलग सिद्धांत ज़रूर देती है
     * (संगत दृष्टि से भारी · असली शरीर परछाईं से भारी · समय कुछ पैदा नहीं करता)।
     * यह पूरा क्रम उन्हीं सिद्धांतों से जोड़कर बनाया गया है — प्रमाणित तालिका
     * नहीं, काम चलाने का ढाँचा। दो जगह सबसे ज़्यादा बहस की गुंजाइश है:
     * विश्वासघात को टक्कर से ऊपर रखना, और उच्च/नीच को शत्रु/मित्र-घर से ऊपर।
     */
    private const BHAR_KRAM = [
        'स्थिति'      => 1,   // ग्रह जहाँ सचमुच बैठा है — तथ्य, व्याख्या नहीं
        'पक्का घर'    => 2,   // अपने घर का अधिकार हालात से नहीं छिनता
        'उच्च/नीच'    => 3,
        'शत्रु/मित्र घर' => 4,
        'युति'        => 5,   // एक ही कमरे में — मामले पर हाथ
        'विश्वासघात'  => 6,   // सटीक, दिशात्मक, और जहाँ पड़े वहाँ गहरा
        'टक्कर'       => 7,   // खुला झगड़ा — दिखता है, सँभाला जा सकता है
        'दृष्टि'       => 8,   // दूर से असर
        'मसनूई'       => 9,   // परछाईं — महसूस होती है, ठोस नहीं
        'दशा/वर्षफल'  => 10,  // सिर्फ़ समय — पैदा या ख़त्म नहीं करता
        'साढ़ेसाती'    => 11,  // घोषित अपवाद, नियम से सबसे नीचे
    ];

    /**
     * भार-क्रम, सेटिंग लागू करके। दो रैंक spec में ख़ुद [INFERRED] हैं — "विश्वासघात
     * बनाम टक्कर" और "उच्च/नीच बनाम शत्रु/मित्र घर"। जिस घराने का मत अलग है, उसके
     * लिए यह अदला-बदली सेटिंग से होती है; बाक़ी क्रम वैसा ही रहता है।
     *
     * @return array<string,int>
     */
    private static function bharKram(): array
    {
        $k = self::BHAR_KRAM;
        if (LalKitabSettings::get('rank_vishwasghat') === 'below') {
            [$k['विश्वासघात'], $k['टक्कर']] = [$k['टक्कर'], $k['विश्वासघात']];
        }
        if (LalKitabSettings::get('rank_dignity') === 'below') {
            [$k['उच्च/नीच'], $k['शत्रु/मित्र घर']] = [$k['शत्रु/मित्र घर'], $k['उच्च/नीच']];
        }
        return $k;
    }

    /**
     * चरण 7 — विरोधाभास का हल।
     *
     * औसत निकालना ही वह चूक है जिसे रोकने के लिए यह चरण मौजूद है। दो उलटी बातें
     * मिलाकर "मध्यम" बना देना कुछ नहीं कहता, किसी की मदद नहीं करता, और कुंडली की
     * सबसे मज़बूत जानकारी चुपचाप फेंक देता है।
     *
     * पर पहले यह पूछना ज़रूरी है कि विरोध सचमुच है भी या नहीं — ज़्यादातर मामलों
     * में नहीं होता, और हर ऐसा मामला जो यहाँ सुलझ जाता है वह उस जानकारी को बचा
     * लेता है जिसे भार-क्रम फेंक देता।
     *
     * @param list<array<string,mixed>> $planets
     * @param list<array<string,mixed>> $houses
     * @param array<string,mixed> $rin
     * @return array<string,mixed>
     */
    private static function conflicts(array $planets, array $houses, array $rin, array $lk): array
    {
        $raw = [];

        foreach ($planets as $p) {
            $hi = (string) $p['hi'];
            // बैठक का विरोध — उच्च पर कच्चे घर में, नीच पर अपने घर में
            if (trim((string) ($p['seat_conflict'] ?? '')) !== '') {
                $raw[] = ['subject' => $hi, 'kind' => 'बैठक का विरोध',
                    'detail' => (string) $p['seat_conflict'],
                    'a_type' => !empty($p['pukka']) ? 'पक्का घर' : 'उच्च/नीच',
                    'b_type' => 'शत्रु/मित्र घर',
                    'a_claim' => 'ग्रह की अपनी ताक़त', 'b_claim' => 'बैठक की हालत',
                    'domain_a' => '', 'domain_b' => ''];
            }
            // जागा ग्रह पर सोया भाव — दोनों बातें सच हैं
            if (($p['verdict'] ?? '') !== 'निष्क्रिय' && !empty($p['asleep'])) {
                $raw[] = ['subject' => $hi, 'kind' => 'ग्रह जागा, भाव सोया',
                    'detail' => 'ग्रह काम करने को तैयार है पर भाव अभी ग्रहण नहीं कर रहा',
                    'a_type' => 'स्थिति', 'b_type' => 'स्थिति',
                    'a_claim' => 'ग्रह फल देने को तैयार', 'b_claim' => 'भाव अभी बंद',
                    'domain_a' => '', 'domain_b' => ''];
            }
        }
        // मसनूई बनाम असली — परछाईं कभी शरीर से भारी नहीं
        foreach ((array) ($lk['masnui'] ?? []) as $m) {
            if (($m['verdict'] ?? '') === 'शुभ') { continue; }
            $raw[] = ['subject' => 'मसनूई ' . (string) ($m['label'] ?? ''),
                'kind' => 'परछाईं बनाम असली ग्रह',
                'detail' => (string) ($m['house_ord'] ?? '') . ' भाव में मसनूई फल',
                'a_type' => 'मसनूई', 'b_type' => 'स्थिति',
                'a_claim' => 'मसनूई का फल', 'b_claim' => 'उसी भाव के असली ग्रह का फल',
                'domain_a' => '', 'domain_b' => ''];
        }
        // ऋण का संकेत बनाम भाव का फल — दो अलग रास्तों से आई बातें
        foreach ((array) ($rin['reportable'] ?? []) as $r) {
            foreach ((array) ($r['clash_houses'] ?? []) as $ch) {
                $raw[] = ['subject' => (string) $r['rin'], 'kind' => 'ऋण बनाम भाव-फल',
                    'detail' => $ch['ord'] . ' भाव का फल शुभ है, पर ऋण वहीं चोट बताता है',
                    'a_type' => 'स्थिति', 'b_type' => 'दृष्टि',
                    'a_claim' => 'भाव-फल शुभ', 'b_claim' => 'ऋण की चोट',
                    'domain_a' => '', 'domain_b' => ''];
            }
        }

        $resolved = $unresolved = [];
        foreach ($raw as $c) {
            // 7.2 — क्षेत्र-विभाजन। एक ग्रह धन में नेक और सेहत में बद हो सकता है;
            // यह विरोध नहीं, दो अलग क्षेत्रों की दो सच्ची बातें हैं।
            if ($c['domain_a'] !== '' && $c['domain_b'] !== '' && $c['domain_a'] !== $c['domain_b']) {
                $c['resolution'] = 'अलग-अलग क्षेत्र';
                $c['resolve'] = 'यह विरोध नहीं — दोनों बातें अपने-अपने क्षेत्र में सच हैं।';
                $resolved[] = $c;
                continue;
            }
            // 7.4 — गेट। ये भार-क्रम में शामिल नहीं होते; पहले छानते हैं और
            // इनके ऊपर कुछ नहीं जाता।
            if ($c['kind'] === 'परछाईं बनाम असली ग्रह') {
                $c['resolution'] = 'गेट — असली ग्रह भारी';
                $c['resolve'] = 'मसनूई फल महसूस होगा पर हल्का; उसी भाव के असली ग्रह का दावा ऊपर रहेगा।';
                $resolved[] = $c;
                continue;
            }
            // 7.5 — भार-क्रम
            $kram = self::bharKram();
            $ra = $kram[$c['a_type']] ?? 99;
            $rb = $kram[$c['b_type']] ?? 99;
            if ($ra !== $rb) {
                $win = $ra < $rb ? 'a' : 'b';
                $c['resolution'] = 'भार-क्रम';
                // हारने वाला दावा मिटता नहीं — शर्त बनकर रहता है। मिटा देने से
                // रिपोर्ट ज़रूरत से ज़्यादा साफ़ हो जाती है और ज़िंदगी से मेल नहीं खाती।
                $c['resolve'] = ($win === 'a' ? $c['a_claim'] : $c['b_claim']) . ' भारी है; '
                    . ($win === 'a' ? $c['b_claim'] : $c['a_claim']) . ' शर्त बनकर रहता है — '
                    . 'यानी फल मिलेगा, पर पूरा नहीं।';
                $resolved[] = $c;
                continue;
            }
            // 7.7 — जो तय न हो सके। यह विफलता नहीं, ईमानदार नतीजा है: असली
            // कुंडलियों में सचमुच ऐसे खिंचाव होते हैं, और यहाँ फ़ैसला गढ़ लेना
            // ज़्यादा उपयोगी नहीं — कम उपयोगी है, क्योंकि वह भरोसा नक़ली होता है।
            $c['resolution'] = 'अनिर्णीत';
            $c['resolve'] = 'यह दोनों तरफ़ खिंचता है — कौन-सा पक्ष खुलेगा, यह कुंडली अकेले तय नहीं करती। '
                . 'यहाँ ज्योतिषी की राय काम आएगी।';
            $unresolved[] = $c;
        }

        // 7.8 — विरोध की गिनती एक सस्ता गुणवत्ता-संकेत है। हर तरफ़ से टकराव
        // फेंकती कुंडली प्रायः असामान्य ज़िंदगी नहीं, बिगड़ी हुई तालिका का लक्षण
        // होती है — इसीलिए यह आँकड़ा रखा जाता है।
        $total = max(1, count($planets) + count($houses));
        return [
            'resolved'   => $resolved,
            'unresolved' => $unresolved,
            'density'    => round(count($unresolved) / $total, 3),
            'data_warn'  => (count($unresolved) / $total) > 0.25,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // चरण 5 — ऋण / श्राप (पुष्टि-दर्जा व हालत सहित)
    // ─────────────────────────────────────────────────────────────

    /**
     * हर अड़चन ऋण नहीं होती। ऋण का ज़्यादा निदान इस विद्या की सबसे आम चूक है —
     * संकेत चौड़े हैं और हर दुर्भाग्य को किसी क़र्ज़ से समझा देने का लालच बड़ा।
     * नतीजा एक भारी-भरकम रिपोर्ट, डरा हुआ आदमी, और माँग भरे उपायों का ढेर
     * जिनमें से ज़्यादातर किसी चीज़ का इलाज नहीं करते।
     *
     * इसलिए तीन दर्जे: पक्का · संभावित · कमज़ोर संकेत — और **कमज़ोर संकेत को
     * ऋण कहकर बताया ही नहीं जाता**, उसे साधारण ग्रह-दोष की तरह पढ़ा जाता है।
     *
     * @param list<array<string,mixed>> $planets
     * @return array<string,mixed>
     */
    private static function rinFindings(array $lk, array $planets, array $houses = []): array
    {
        $vMap = [];
        foreach ($planets as $p) { $vMap[(string) $p['hi']] = $p; }
        $hMap = [];
        foreach ($houses as $hh) { $hMap[(int) $hh['house']] = $hh; }

        $entries = [];
        foreach ((array) ($lk['shrap'] ?? []) as $r) {
            if (empty($r['present'])) { continue; }
            $matched = (array) ($r['matched'] ?? []);

            // ---- ऋण और श्राप एक चीज़ नहीं हैं ----
            // ऋण वंश पर चढ़ा हुआ क़र्ज़ है — उसे **चुकाया** जाता है, देकर।
            // श्राप मिली हुई चोट है — उसे **शांत** किया जाता है। चुकाने का उपाय
            // श्राप पर लगाने से कुछ नहीं होता, और आदमी नतीजा निकालता है कि पूरी
            // विद्या बेकार है। चरण 9 सबसे ऊपर इसी शाखा पर बँटता है।
            $rinName = (string) ($r['rin'] ?? '');
            $kind = mb_strpos($rinName, 'श्राप') !== false ? 'श्राप' : 'ऋण';

            // ---- चोट कहाँ पड़ती है, और क्या भाव-फल भी वही कह रहा है ----
            // दो अलग रास्तों से एक ही नतीजा असली प्रमाण है; दो रास्तों से उलटे
            // नतीजे वह चीज़ है जिस पर परदा नहीं डालना।
            $hitHouses = [];
            foreach ($matched as $mtxt) {
                if (preg_match('/(\d+)/', (string) $mtxt, $mh)) { $hitHouses[] = (int) $mh[1]; }
            }
            $corrob = []; $clash = [];
            foreach (array_unique($hitHouses) as $hn) {
                $hv = (string) ($hMap[$hn]['verdict'] ?? '');
                if (in_array($hv, ['अशुभ', 'अस्थिर', 'सुप्त'], true)) {
                    $corrob[] = ['house' => $hn, 'ord' => (string) ($hMap[$hn]['house_ord'] ?? $hn), 'verdict' => $hv];
                } elseif ($hv === 'शुभ') {
                    $clash[] = ['house' => $hn, 'ord' => (string) ($hMap[$hn]['house_ord'] ?? $hn), 'verdict' => $hv];
                }
            }

            // पुष्टि — कितनी दिशाओं से एक ही बात निकल रही है। ऋण का ज़्यादा निदान
            // इस विद्या की सबसे आम चूक है, इसलिए दो-तरफ़ा पुष्टि पर ही "पक्का"।
            $signals = count($matched) + count($corrob);
            $confidence = $signals >= 3 ? 'पक्का' : ($signals === 2 ? 'संभावित' : 'कमज़ोर संकेत');

            // हालत — जिन ग्रहों से यह चलता है वे क्या कर रहे हैं
            $carriers = [];
            foreach ($matched as $mtxt) {
                foreach ($vMap as $hi => $pp) {
                    if (mb_strpos((string) $mtxt, $hi) !== false) { $carriers[$hi] = $pp; }
                }
            }
            $allDormant = $carriers !== [];
            $anyBad = false;
            foreach ($carriers as $pp) {
                if (($pp['verdict'] ?? '') !== 'निष्क्रिय') { $allDormant = false; }
                if (($pp['verdict'] ?? '') === 'अशुभ') { $anyBad = true; }
            }
            // दबा हुआ = कोई ग्रह इसे अभी रोक रहा है। उसे शांत करना सबसे ख़तरनाक
            // ग़लती होगी — कवच हटते ही मुसीबत सामने आती है, और वह भी उपाय शुरू
            // करने के हफ़्तों बाद। इसलिए रक्षक का नाम दर्ज रखना ज़रूरी है।
            //
            // और इसीलिए "दबा हुआ" तभी कहा जाता है जब रोकने वाले का नाम सचमुच
            // निकल आए। बिना नाम के यह कहना कि "कोई इसे रोके हुए है" एक ऐसा दावा
            // है जिसकी जाँच नहीं हो सकती — और जिस पर आगे चलकर उपाय-बहिष्कार टिकता
            // है। नाम न मिले तो हालत चालू मानी जाती है: दबे को चालू कह देना
            // ज़्यादा-से-ज़्यादा एक अतिरिक्त उपाय कराता है, जबकि चालू को दबा कह
            // देना असली मुसीबत को अनदेखा करा देता है।
            $protector = '';
            if ($carriers !== [] && !$allDormant && !$anyBad) {
                foreach ($carriers as $hi => $pp) {
                    if (($pp['verdict'] ?? '') === 'शुभ') { $protector = $hi; break; }
                }
                if ($protector === '') {
                    // कोई साफ़ शुभ नहीं — तब सबसे ज़्यादा क्षमता वाला जागा ग्रह ही
                    // कवच है। सोए ग्रह किसी को नहीं रोकते।
                    $rank = ['प्रबल' => 3, 'मध्यम' => 2, 'कमज़ोर' => 1, 'शून्य' => 0];
                    $best = 0;
                    foreach ($carriers as $hi => $pp) {
                        if (($pp['verdict'] ?? '') === 'निष्क्रिय') { continue; }
                        $k = $rank[(string) ($pp['kshamata'] ?? 'मध्यम')] ?? 2;
                        if ($k > $best) { $best = $k; $protector = $hi; }
                    }
                }
            }
            $status = $allDormant ? 'सुप्त' : (($anyBad || $protector === '') ? 'चालू' : 'दबा हुआ');
            // तीव्रता — चरण 9 को कई ऋणों में क्रम लगाना है, उसी के लिए
            // दर्जों में नापें या सिर्फ़ है/नहीं — विवादित, इसलिए सेटिंग से।
            $sev = LalKitabSettings::get('rin_severity') === 'binary'
                ? ($corrob !== [] ? 'तीव्र' : 'हल्का')
                : (count($corrob) >= 2 ? 'तीव्र' : (count($corrob) === 1 ? 'मध्यम' : 'हल्का'));
            if ($status === 'सुप्त') { $sev = 'हल्का'; }

            $entries[] = [
                'rin'        => $rinName,
                'kind'       => $kind,
                'confidence' => $confidence,
                'status'     => $status,
                'severity'   => $sev,
                'protector'  => $protector,
                'matched'    => $matched,
                'corroborated' => $corrob,          // भाव-फल भी यही कह रहा है
                'clash_houses' => $clash,           // भाव-फल उलटा कह रहा है → चरण 7
                'direction'  => $kind === 'ऋण' ? 'चुकाना' : 'शांत करना',
                'upay'       => (string) ($r['upay'] ?? ''),
                'phal'       => (string) ($r['ashubh_phal'] ?? ''),
                // पीढ़ी वाली बात हमेशा शर्त के साथ। "आपके बच्चों को कष्ट होगा" उस
                // बात को तय बता देना है जिसे किताब खुद बदलने योग्य कहती है — और
                // यही वह चीज़ है जो उपाय बदलता है। किसी पुरखे का नाम भी नहीं।
                'line_hi'    => 'कुंडली में ' . $rinName . ' का संकेत है। लाल किताब कहती है कि इसे '
                    . ($kind === 'ऋण' ? 'चुकाया' : 'शांत किया') . ' जा सकता है — '
                    . 'समय पर उपाय करने से यह आगे नहीं बढ़ता।'
                    . ($status === 'दबा हुआ' && $protector !== ''
                        ? ' अभी यह दबा हुआ है — ' . $protector . ' इसे रोके हुए है, इसलिए आपको इसका असर महसूस नहीं होता।'
                        : ''),
            ];
        }
        // क्रम — पक्का पहले, चालू पहले, तीव्र पहले। आदमी सचमुच एक या दो ही उठा
        // सकता है; बाक़ी रुके रहते हैं और पहला पूरा होने पर उनकी बारी आती है।
        usort($entries, static function (array $a, array $b): int {
            $cw = ['पक्का' => 0, 'संभावित' => 1, 'कमज़ोर संकेत' => 2];
            $sw = ['चालू' => 0, 'दबा हुआ' => 1, 'सुप्त' => 2];
            $vw = ['तीव्र' => 0, 'मध्यम' => 1, 'हल्का' => 2];
            return [$cw[$a['confidence']], $sw[$a['status']], $vw[$a['severity']]]
                <=> [$cw[$b['confidence']], $sw[$b['status']], $vw[$b['severity']]];
        });
        // कमज़ोर संकेत ग्राहक को ऋण कहकर नहीं बताए जाते
        $reportable = array_values(array_filter($entries, static fn ($e) => $e['confidence'] !== 'कमज़ोर संकेत'));
        $protectors = [];
        foreach ($entries as $e) {
            if ($e['protector'] !== '') { $protectors[] = $e['protector']; }
        }
        return [
            'entries'    => $entries,
            'reportable' => $reportable,
            'primary'    => $reportable[0] ?? null,
            'excluded'   => array_values(array_filter($entries, static fn ($e) => $e['confidence'] === 'कमज़ोर संकेत')),
            'protectors' => array_values(array_unique($protectors)),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // चरण 8 §8.2–8.4 — निचोड़ के मुख्य बिंदु
    // ─────────────────────────────────────────────────────────────

    /**
     * तीन से पाँच बातें, इससे ज़्यादा नहीं — और ताक़त हमेशा पहले।
     *
     * यह सजावट नहीं है। जो आदमी पहले अपनी मुसीबत सुनता है वह बाक़ी रिपोर्ट अपने
     * ही डर के अंदर बैठकर पढ़ता है और उपाय — यानी वह हिस्सा जो उसकी मदद करता —
     * उस तक पहुँचता ही नहीं। और नौ ग्रह बारह भावों में हों तो कुछ न कुछ हमेशा
     * सहारा दे ही रहा होता है; पहले मुसीबत रखना कुंडली का सच्चा संतुलन भी नहीं है।
     *
     * @param list<array<string,mixed>> $planets
     * @param array<string,mixed> $temperament
     * @param array<string,mixed> $rin
     * @return list<array<string,mixed>>
     */
    private static function corePoints(array $planets, array $temperament, array $rin, array $lk): array
    {
        $rank = ['प्रबल' => 3, 'मध्यम' => 2, 'कमज़ोर' => 1, 'शून्य' => 0];
        $good = $bad = $stalled = [];
        foreach ($planets as $p) {
            $v = (string) ($p['verdict'] ?? '');
            $k = (string) ($p['kshamata'] ?? 'मध्यम');
            $row = [
                'planet' => (string) $p['hi'],
                'house'  => (string) $p['house_ord'],
                'weight' => $rank[$k] ?? 1,
                'kshamata' => $k,
                'source' => 'ग्रह-फल: ' . $p['hi'] . ' / ' . $p['house_ord'] . ' भाव',
            ];
            if ($v === 'शुभ') { $good[] = $row; }
            elseif ($v === 'अशुभ') { $bad[] = $row; }
            elseif ($v === 'निष्क्रिय') { $stalled[] = $row; }
        }
        // क्रम क्षमता से लगता है, दिशा से नहीं — जो ग्रह ज़ोर से बोल रहा है
        // उसे पहले सुना जाता है, चाहे वह भला बोले या बुरा।
        $byWeight = static fn (array $a, array $b): int => $b['weight'] <=> $a['weight'];
        usort($good, $byWeight);
        usort($bad, $byWeight);
        usort($stalled, $byWeight);

        $points = [];
        // 1. मिज़ाज
        $points[] = [
            'type' => 'मिज़ाज', 'rank' => 1,
            'title' => 'कुंडली का मिज़ाज — ' . $temperament['primary'],
            'text'  => $temperament['line'],
            'source' => 'चरण-8: ग्रहों की कुल हालत',
            'remedy_needed' => false,
        ];
        // 2. सबसे मज़बूत सहारा — हमेशा पहले
        if ($good !== []) {
            $g = $good[0];
            $points[] = [
                'type' => 'ताक़त', 'rank' => 2,
                'title' => 'आपकी सबसे बड़ी मज़बूती — ' . $g['planet'],
                'text'  => $g['planet'] . ' आपके ' . $g['house'] . ' भाव में अच्छी हालत में है'
                    . ($g['kshamata'] === 'प्रबल' ? ' और पूरी ताक़त से काम कर रहा है' : '')
                    . '। जहाँ भी अड़चन आए, सहारा इसी तरफ़ से मिलेगा — इसी पर ज़ोर लगाएँ।',
                'source' => $g['source'],
                'remedy_needed' => false,
            ];
        }
        // 3. मुख्य कठिनाई — क्षमता के क्रम से
        if ($bad !== []) {
            $b = $bad[0];
            $points[] = [
                'type' => 'कठिनाई', 'rank' => 3, 'planet' => $b['planet'],
                'title' => 'सबसे पहले ध्यान देने की बात — ' . $b['planet'],
                'text'  => $b['planet'] . ' आपके ' . $b['house'] . ' भाव में दबाव दे रहा है'
                    . ($b['kshamata'] === 'प्रबल' ? ' और इसका असर तेज़ है, इसलिए सबसे पहले यही सँभालना है' : '')
                    . '। उपाय नीचे दिया है।',
                'source' => $b['source'],
                'remedy_needed' => true,
            ];
        }
        // 4. रुका हुआ हिस्सा — यह "बुरा" नहीं है और इसे बुरा कहना ग़लत होगा
        if ($stalled !== []) {
            $s = $stalled[0];
            $names = array_slice(array_column($stalled, 'planet'), 0, 3);
            $points[] = [
                'type' => 'ठहराव', 'rank' => 4, 'planet' => $s['planet'],
                'title' => 'जो हिस्सा अभी रुका हुआ है — ' . implode(', ', $names),
                'text'  => implode(', ', $names) . ' कुंडली में मौजूद तो हैं पर अभी काम नहीं कर रहे। '
                    . 'इनके मामलों में सालों से कुछ आगे नहीं बढ़ रहा होगा। यह बुरा फल नहीं है — '
                    . 'इसका उपाय शांति नहीं, इन्हें जगाना है।',
                'source' => $s['source'],
                'remedy_needed' => true,
            ];
        }
        // 5. मुख्य ऋण — कभी उपाय के बिना नहीं
        if (($rin['primary'] ?? null) !== null) {
            $r = $rin['primary'];
            $points[] = [
                'type' => 'ऋण', 'rank' => 5, 'planet' => (string) $r['rin'],
                'title' => $r['rin'] . ' (' . $r['confidence'] . ' · ' . $r['status']
                    . ' · तीव्रता ' . $r['severity'] . ')',
                'text'  => $r['line_hi'],
                'source' => 'ऋण-जाँच: ' . implode(', ', $r['matched'])
                    . ($r['corroborated'] !== []
                        ? ' — भाव-फल से भी पुष्ट (' . implode(', ', array_column($r['corroborated'], 'ord')) . ')' : ''),
                'remedy_needed' => true,
            ];
        }
        return array_slice($points, 0, 5);
    }

    // ─────────────────────────────────────────────────────────────
    // चरण 9 — उपाय का चयन और क्रम
    // ─────────────────────────────────────────────────────────────

    /**
     * यही वह चरण है जिसका असर असल ज़िंदगी में पड़ता है — बाक़ी सब विश्लेषण था,
     * यह निर्देश है जिस पर कोई अमल करेगा। इसीलिए यहाँ सबसे ज़्यादा बंदिशें हैं।
     *
     * सबसे बड़ी बात दिशा है, और वही सबसे ज़्यादा ग़लत होती है: सोए ग्रह को शांत
     * करने से वह और गहरी नींद सो जाता है। बरसों ईमानदारी से किए गए उपाय बेअसर
     * रहते हैं और आदमी नतीजा निकालता है कि लाल किताब काम नहीं करती — जबकि उसे
     * उल्टी दिशा का उपाय दिया गया था।
     *
     * @param list<array<string,mixed>> $planets
     * @param array<string,mixed> $rin
     * @param array<string,mixed> $native
     * @return array<string,mixed>
     */
    private static function remedySequence(array $planets, array $rin, array $native, array $lk, array $core = []): array
    {
        $protectors = (array) ($rin['protectors'] ?? []);
        $rank = ['प्रबल' => 3, 'मध्यम' => 2, 'कमज़ोर' => 1, 'शून्य' => 0];
        // निचोड़ ने जिन ग्रहों को मुख्य कहा, उपाय उन्हीं का पहले — इसी क्रम में।
        // वरना रिपोर्ट राहु को मुख्य कठिनाई बताएगी और उपाय गुरु का दे देगी।
        $corePlanets = [];
        foreach ($core as $c) {
            if (($c['remedy_needed'] ?? false) && trim((string) ($c['planet'] ?? '')) !== '') {
                $corePlanets[] = (string) $c['planet'];
            }
        }

        $cands = [];
        foreach ($planets as $p) {
            if (empty($p['need_remedy']) && ($p['verdict'] ?? '') !== 'निष्क्रिय') { continue; }
            $v = (string) ($p['verdict'] ?? '');
            $k = (string) ($p['kshamata'] ?? 'मध्यम');
            $hi = (string) $p['hi'];

            // ---- दिशा पहले, उपाय बाद में। कभी उल्टा नहीं। ----
            if ($v === 'निष्क्रिय') { $dir = 'जगाना'; }
            elseif ($v === 'अशुभ') { $dir = 'शांति'; }
            elseif ($v === 'शुभ' && $k === 'कमज़ोर') { $dir = 'बल-वृद्धि'; }
            elseif ($v === 'मध्यम') { $dir = 'शांति'; }
            else { continue; }

            // ---- निशाना — प्रायः वह ग्रह नहीं जो दुखी दिख रहा है ----
            // गुरु शनि के दबाव से सोया हो तो गुरु को बल देने का कोई फ़ायदा नहीं;
            // शनि शांत करो, गुरु अपने-आप उठ जाता है।
            $wake = (array) ($p['wake'] ?? []);
            $target = $hi;
            $targetNote = '';
            if ($dir === 'जगाना' && trim((string) ($wake['cause_hi'] ?? '')) !== '') {
                $target = (string) $wake['cause_hi'];
                $targetNote = $hi . ' को ' . $target . ' ने दबा रखा है — इसलिए उपाय ' . $target
                    . ' का है, ' . $hi . ' का नहीं।';
            } elseif ($dir === 'जगाना' && trim((string) ($wake['agent_hi'] ?? '')) !== '') {
                $target = (string) $wake['agent_hi'];
                $targetNote = $hi . ' को जगाने की चाबी ' . $target . ' के पास है।';
            }

            // ---- रक्षक को मत छेड़ो ----
            // अगर यह ग्रह इस वक़्त किसी ऋण को रोके हुए है तो इसे शांत करना उस
            // कवच को हटा देगा। मुसीबत उपाय शुरू करने के कुछ हफ़्तों बाद आएगी और
            // आदमी दोनों को जोड़ेगा — ठीक ही जोड़ेगा।
            if (in_array($target, $protectors, true) && $dir === 'शांति') {
                $cands[] = ['excluded' => true, 'planet' => $hi, 'target' => $target,
                    'reason' => 'यह ग्रह इस समय एक ऋण को रोके हुए है — इसे शांत करना कवच हटा देगा'];
                continue;
            }

            $upay = array_values(array_filter([
                (string) ($p['sheeghra'] ?? ''),
                (string) (($p['remedies'][0] ?? '')),
                (string) (($p['samanya'][0] ?? '')),
            ], static fn ($t) => trim($t) !== ''));

            // ---- हालात के गेट ----
            // ये बारीक अक्षर नहीं हैं। कई उपाय माता-पिता के जीवित होने या न होने
            // पर अपना असर उलट देते हैं, और ग़लत हालत में दिया गया उपाय वही कर
            // सकता है जिससे बचाना था। जहाँ हालत अज्ञात हो, वह उपाय रोक दिया
            // जाता है — अंदाज़ा नहीं लगाया जाता।
            [$upay, $gateNotes] = self::nativeGate($upay, $native);
            if ($upay === []) {
                $cands[] = ['excluded' => true, 'planet' => $hi, 'target' => $target,
                    'reason' => 'इस ग्रह के उपाय पारिवारिक हालत पर निर्भर हैं — वह जानकारी अभी नहीं है'];
                continue;
            }

            $cands[] = [
                'excluded'   => false,
                'planet'     => $hi,
                'house'      => (string) $p['house_ord'],
                'target'     => $target,
                'target_note'=> $targetNote,
                'direction'  => $dir,
                'kshamata'   => $k,
                'weight'     => $rank[$k] ?? 1,
                'branch'     => 'साधारण',
                'upay'       => $upay,
                'gate_notes' => $gateNotes,
                // हर उपाय के साथ उसका अंत भी बताया जाता है। जिस उपाय का अंत न
                // बताया जाए, आदमी उसे डरते हुए और अनिश्चित काल तक करता रहता है।
                'kind'       => $dir === 'जगाना' ? 'निश्चित अवधि' : 'लगातार',
                'duration'   => $dir === 'जगाना' ? '43 दिन' : 'जब तक हालत सुधरे',
                'stop_when'  => $dir === 'जगाना'
                    ? '43 दिन पूरे होने पर रुक जाएँ — दोबारा तभी, जब सलाह मिले'
                    : 'इस क्षेत्र में सुधार दिखने के बाद हल्का कर दें',
                'repeat'     => $dir === 'जगाना' ? 'बिना सलाह दोबारा नहीं' : 'हाँ',
            ];
        }
        // ---- ऋण / श्राप की अपनी शाखा ----
        // चुकाने का उपाय श्राप पर बेअसर है, और शांत करने का उपाय ऋण को खड़ा
        // छोड़ देता है। इसीलिए चरण 5 ने दोनों को अलग-अलग पहचाना था।
        // कमज़ोर संकेत यहाँ ऋण की तरह बरता ही नहीं जाता — उसे साधारण ग्रह-दोष
        // मानकर ऊपर वाला उपाय काफ़ी है। कमज़ोर संकेत पर भारी चुकाने का कार्यक्रम
        // थोप देना उस आदमी पर बोझ है जिसका शायद कोई ऋण है ही नहीं।
        $primaryRin = $rin['primary'] ?? null;
        if (is_array($primaryRin) && trim((string) $primaryRin['upay']) !== '') {
            $cands[] = [
                'excluded'   => false,
                'planet'     => (string) $primaryRin['rin'],
                'house'      => '',
                'target'     => (string) $primaryRin['rin'],
                'target_note'=> $primaryRin['kind'] === 'ऋण'
                    ? 'यह ऋण है — इसका उपाय चुकाना है, यानी देकर लौटाना।'
                    : 'यह श्राप है — इसका उपाय शांत करना है, चुकाना नहीं।',
                'direction'  => (string) $primaryRin['direction'],
                'kshamata'   => 'प्रबल',
                'weight'     => 4,   // ऋण पुष्ट हो तो सबसे पहले
                'branch'     => (string) $primaryRin['kind'],
                'upay'       => [(string) $primaryRin['upay']],
                'kind'       => 'निश्चित अवधि',
                'duration'   => 'जब तक उपाय की विधि कहे',
                'stop_when'  => 'विधि पूरी होने पर — बिना सलाह बढ़ाएँ नहीं',
                'repeat'     => 'बिना सलाह दोबारा नहीं',
            ];
        }

        $issuedPool = array_values(array_filter($cands, static fn ($c) => !$c['excluded']));
        // क्रम — पहले वे ग्रह जिन्हें निचोड़ ने मुख्य कहा (उसी क्रम में), फिर बाक़ी
        // क्षमता से: जो ज़ोर से बोल रहा है वह पहले, चाहे शब्दों में कोई और बात
        // ज़्यादा डरावनी लगे।
        usort($issuedPool, static function (array $a, array $b) use ($corePlanets): int {
            $ia = array_search($a['planet'], $corePlanets, true);
            $ib = array_search($b['planet'], $corePlanets, true);
            $ra = $ia === false ? 99 : (int) $ia;
            $rb = $ib === false ? 99 : (int) $ib;
            if ($ra !== $rb) { return $ra <=> $rb; }
            return $b['weight'] <=> $a['weight'];
        });

        // ---- एक, ज़्यादा से ज़्यादा दो ----
        // आठ उपायों की सूची दूसरे हफ़्ते छूट जाती है, और छूटा हुआ उपाय शुरू न
        // किए गए उपाय से बुरा है — आदमी के पास तब मूल समस्या भी होती है और
        // नाकाम रहने का बोझ भी। एक उपाय पूरा होना छह अधूरे से ज़्यादा क़ीमती है।
        $issued = array_slice($issuedPool, 0, 2);
        // दो जारी तभी, जब दोनों एक-दूसरे को काटते न हों
        if (count($issued) === 2) {
            $d1 = $issued[0]['direction']; $d2 = $issued[1]['direction'];
            $sameTarget = $issued[0]['target'] === $issued[1]['target'];
            if ($sameTarget || ($d1 === 'शांति' && $d2 === 'बल-वृद्धि') || ($d1 === 'बल-वृद्धि' && $d2 === 'शांति')) {
                $issued = [$issued[0]];
            }
        }
        $issuedKeys = array_map(static fn ($c) => $c['planet'] . '|' . $c['direction'], $issued);
        $held = [];
        foreach ($issuedPool as $c) {
            if (!in_array($c['planet'] . '|' . $c['direction'], $issuedKeys, true)) {
                $held[] = ['planet' => $c['planet'], 'direction' => $c['direction'],
                    'reason' => 'नोट कर लिया गया — पहला उपाय पूरा होने के बाद इसकी बारी'];
            }
        }
        // कौन-सा सवाल बाक़ी है, यह नाम लेकर बताया जाता है — "कुछ जानकारी चाहिए"
        // पढ़कर कोई वापस जाकर नहीं भरता, "संतान है?" पढ़कर भर देता है।
        $missing = [];
        foreach (self::NATIVE_FIELDS as $nk => $nf) {
            $nv = (string) ($native[$nk] ?? '');
            if ($nv === '' || $nv === 'अज्ञात') { $missing[] = $nf['q']; }
        }
        // किस-किस उपाय को हालत के कारण रोका गया — यह भी दिखता है, वरना पढ़ने वाले
        // को लगता है कि उसके ग्रह के लिए कोई उपाय है ही नहीं।
        $gateAll = [];
        foreach ($cands as $c) {
            foreach ((array) ($c['gate_notes'] ?? []) as $gn) { $gateAll[] = (string) $gn; }
        }

        return [
            'issued'    => $issued,
            'held'      => $held,
            'excluded'  => array_values(array_filter($cands, static fn ($c) => $c['excluded'])),
            'protectors'=> $protectors,
            'gate_notes'=> array_values(array_unique($gateAll)),
            'missing'   => $missing,
            'native_ok' => $missing === [],
            'note'      => $missing === []
                ? ''
                : 'उपाय पूरी तरह तय करने के लिए इतना और बताना होगा — ' . implode(' · ', $missing)
                  . '। कई उपाय इन्हीं हालात पर उलट जाते हैं, इसलिए हालत मालूम न होने पर वैसा उपाय '
                  . 'रोक दिया जाता है। तब तक नीचे दिए उपाय सामान्य व सुरक्षित हैं।',
        ];
    }

    /**
     * हालात के गेट — हर उपाय जारी होने से पहले जातक की परिस्थिति पर जाँचा जाता है।
     *
     * जिन उपायों में पिता/माता या पत्नी से जुड़ा काम है, वे उस रिश्ते के होने या
     * न होने पर अलग-अलग असर देते हैं। जहाँ हालत अज्ञात है वहाँ उपाय **रोका**
     * जाता है — "लगभग वैसा ही" कोई दूसरा उपाय रख देना ग़लत है; सही रास्ता यह
     * पूछना है, अंदाज़ा लगाना नहीं।
     *
     * @param list<string> $upay
     * @param array<string,mixed> $native
     * @return array{0:list<string>,1:list<string>}
     */
    private static function nativeGate(array $upay, array $native): array
    {
        $v = static fn (string $k): string => (string) ($native[$k] ?? '');
        $father = $v('father_living');
        $mother = $v('mother_living');
        $marry  = $v('marital_status');
        $child  = $v('children');
        $eldest = $v('eldest');
        $home   = $v('own_home');

        $kept = $notes = [];
        foreach ($upay as $t) {
            $needsFather = mb_strpos($t, 'पिता') !== false || mb_strpos($t, 'बुज़ुर्ग') !== false;
            $needsMother = mb_strpos($t, 'माता') !== false || mb_strpos($t, 'माँ') !== false;
            $needsWife   = mb_strpos($t, 'पत्नी') !== false || mb_strpos($t, 'स्त्री') !== false;
            // घर से जुड़ा उपाय = मकान का ढाँचा छूने वाला। किराये के मकान की नींव
            // नहीं खोदी जा सकती, छत नहीं बदली जा सकती, फ़र्श कच्चा नहीं किया जा
            // सकता — और मकान-मालिक की इजाज़त माँगना उपाय का हिस्सा नहीं है।
            $needsHome = preg_match('/मकान|भवन|घर की|घर के|घर में|चारदीवारी|दहलीज|रोशनदान/u', $t) === 1
                && preg_match('/नींव|फ़र्श|फर्श|दीवार|छत|दबा|तह-ज़मीन|बनाकर|गिरा/u', $t) === 1;
            // अपनी औलाद से जुड़ा उपाय — दूसरों के बच्चों को कुछ बाँटने वाला उपाय
            // इसमें नहीं आता, वह सबके लिए एक-सा है।
            $needsChild  = preg_match('/औलाद|संतान|निःसंतान/u', $t) === 1;
            $needsElder  = preg_match('/बड़े भाई|बड़ा भाई|बड़े भाइयों/u', $t) === 1;

            if ($needsFather && $father === '') {
                $notes[] = 'पिता से जुड़ा एक उपाय रोका गया — पहले बताएँ कि पिता जीवित हैं या नहीं।';
                continue;
            }
            if ($needsMother && $mother === '') {
                $notes[] = 'माता से जुड़ा एक उपाय रोका गया — पहले बताएँ कि माता जीवित हैं या नहीं।';
                continue;
            }
            if ($needsWife && $marry === '') {
                $notes[] = 'पत्नी/स्त्री से जुड़ा एक उपाय रोका गया — वैवाहिक स्थिति बताएँ।';
                continue;
            }
            if ($needsWife && $marry === 'अविवाहित') {
                $notes[] = 'पत्नी से जुड़ा उपाय आपकी स्थिति में लागू नहीं — हटा दिया गया।';
                continue;
            }
            if ($needsHome && $home === '') {
                $notes[] = 'मकान का ढाँचा छूने वाला एक उपाय रोका गया — बताएँ कि घर अपना है या किराये का।';
                continue;
            }
            if ($needsHome && $home === 'किराये का') {
                $notes[] = 'मकान की नींव/छत/फ़र्श वाला उपाय किराये के घर में नहीं हो सकता — हटा दिया गया। '
                    . 'अपना घर होने पर यह दोबारा आएगा।';
                continue;
            }
            if ($needsChild && $child === '') {
                $notes[] = 'औलाद से जुड़ा एक उपाय रोका गया — बताएँ कि संतान है या नहीं।';
                continue;
            }
            if ($needsElder && $eldest === '') {
                $notes[] = 'बड़े भाई से जुड़ा एक उपाय रोका गया — बताएँ कि आप भाई-बहनों में सबसे बड़े हैं या नहीं।';
                continue;
            }
            if ($needsElder && $eldest === 'हाँ') {
                $notes[] = 'बड़े भाई की सेवा वाला उपाय आप पर लागू नहीं (आप ही सबसे बड़े हैं) — हटा दिया गया।';
                continue;
            }
            $kept[] = $t;
        }
        return [$kept, array_values(array_unique($notes))];
    }

    /** @param array<string,mixed> $native */
    private static function nativeComplete(array $native): bool
    {
        foreach (array_keys(self::NATIVE_FIELDS) as $k) {
            if (!isset($native[$k]) || $native[$k] === '' || $native[$k] === 'अज्ञात') { return false; }
        }
        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // चरण 10 — ग्राहक का पन्ना
    // ─────────────────────────────────────────────────────────────

    /**
     * हर ग्रह का अपना निचोड़ — "अभी क्या मानें"।
     *
     * विस्तृत पन्नों की असली दिक़्क़त जानकारी की कमी नहीं थी, उसका न मिलाया जाना
     * था। एक ही कार्ड पर एक साथ छपता था: *मृत ग्रह — कारकत्व अनुपस्थित मानें* और
     * *इन क्षेत्रों में उन्नति व लाभ मिलेगा*। दोनों वाक्य अपनी-अपनी जगह ठीक थे —
     * एक अवस्था का, दूसरा दिशा का — पर पढ़ने वाले के लिए यह कुछ नहीं कहता। वह
     * यही समझता है कि गणना भरोसे लायक़ नहीं।
     *
     * यहाँ वही मेल किया जाता है, उसी क्रम में जिसमें ज्योतिषी करता है:
     *   1. ग्रह जाग रहा है या नहीं — क्योंकि सोए ग्रह का नेक/बद बेमानी है।
     *   2. जाग रहा है तो कितने ज़ोर से (क्षमता) — दिशा से अलग धुरी।
     *   3. किन मामलों में — कारक क्षेत्र नाम से, "अनुकूल फल" जैसे भराव से नहीं।
     *   4. अब क्या करना है — दिशा, निशाना, और यह कि उपाय अभी जारी है, कतार में
     *      है, या रोका गया — वही जवाब जो निचोड़ देता है, ताकि दोनों पन्ने एक ही
     *      बात कहें।
     *
     * @param list<array<string,mixed>> $planets
     * @param array<string,mixed> $upaay
     * @return array<string,array<string,mixed>>
     */
    private static function planetBriefs(array $planets, array $upaay, array $lk): array
    {
        $age    = $lk['age'] ?? null;
        $issued = (array) ($upaay['issued'] ?? []);
        $held   = (array) ($upaay['held'] ?? []);
        $excl   = (array) ($upaay['excluded'] ?? []);
        $dashaHi = (string) (($lk['active']['dasha']['hi']) ?? '');

        $kshamataHi = [
            'प्रबल'  => 'यह ग्रह अभी ज़ोर से बोल रहा है — इसका जो भी फल है, तेज़ मिलेगा।',
            'मध्यम'  => 'इसकी ताक़त सामान्य है — फल मिलेगा पर धीरे।',
            'कमज़ोर' => 'यह कमज़ोर है — इसका फल हल्का रहेगा, चाहे अच्छा हो या बुरा।',
            'शून्य'  => 'अभी इसकी कोई ताक़त काम नहीं कर रही।',
        ];

        $out = [];
        foreach ($planets as $p) {
            $hi     = (string) ($p['hi'] ?? '');
            if ($hi === '') { continue; }
            $ord    = (string) ($p['house_ord'] ?? '');
            $verd   = (string) ($p['verdict'] ?? 'मध्यम');
            $ksh    = (string) ($p['kshamata'] ?? 'मध्यम');
            $impair = trim((string) ($p['impair'] ?? ''));
            $wake   = (array) ($p['wake'] ?? []);
            $areaList = array_values(array_filter(array_map('strval', (array) ($p['areas_hi'] ?? []))));
            $areas  = implode(' · ', array_slice($areaList, 0, 3));
            $wakeAge = $wake['wake_age'] ?? null;
            $awake   = !empty($wake['awake']);
            $asleep  = !empty($wake['asleep']);

            // ---- 1. अभी की हालत, एक वाक्य में ----
            $areaTxt = $areas !== '' ? $areas : 'इसके अपने मामले';
            $mein    = $areas !== '' ? $areas . ' — इनमें' : 'इसके मामलों में';
            $shart   = trim((string) ($wake['condition'] ?? ''));
            if ($verd === 'निष्क्रिय') {
                $state = 'रुका हुआ';
                $line  = $hi . ' अभी काम नहीं कर रहा। ' . $mein . ' सालों से कुछ आगे नहीं बढ़ रहा होगा। '
                    . 'यह बुरा फल नहीं है, रुका हुआ फल है — इसका इलाज शांति नहीं, जगाना है।';
            } elseif ($asleep && $awake) {
                // यहीं दो उलटे वाक्य साथ छपते थे: "कारकत्व अनुपस्थित मानें" और
                // "लाभ मिलेगा"। पर उलटी तरफ़ झुककर "जाग चुका है" कह देना भी उतना ही
                // ग़लत होगा — इंजन के पास सिर्फ़ यह ख़बर है कि जागने की **उम्र** निकल
                // चुकी है। किताब जागने को एक घटना से बाँधती है (सरकारी काम, सन्तान
                // का जन्म…), और वह घटना हुई या नहीं, यह कुंडली नहीं बता सकती —
                // जातक बता सकता है। इसलिए वाक्य शर्त के साथ रखा जाता है।
                $state = 'जाग चुका';
                $line  = $hi . ' जन्म से ' . ($impair !== '' ? $impair : 'सोया') . ' है, पर जागने की उम्र'
                    . ($wakeAge !== null ? ' (' . (int) $wakeAge . ' वर्ष)' : '') . ' निकल चुकी है। '
                    . ($shart !== ''
                        ? 'किताब इसे एक घटना से बाँधती है — ' . $shart . '। वह हो चुकी हो तो ' . $mein
                          . ' फल अब खुलता है; न हुई हो तो ग्रह अब भी सोया ही मानें।'
                        : $mein . ' फल अब खुलने लगता है — देर से, पर मिलता है।');
            } elseif ($asleep) {
                $state = 'सोया';
                $baaki = ($wakeAge !== null && is_int($age)) ? max(0, (int) $wakeAge - $age) : null;
                $line  = $hi . ' अभी ' . ($impair !== '' ? $impair : 'सोया') . ' है — ' . $mein
                    . ' अभी कुछ आगे नहीं बढ़ रहा।'
                    . ($baaki !== null && $baaki > 0
                        ? ' अपने-आप आयु ' . (int) $wakeAge . ' पर जागने का समय आता है — अभी ' . $baaki . ' वर्ष बाक़ी।'
                        : ($wakeAge !== null ? ' अपने-आप जागने की उम्र ' . (int) $wakeAge . ' वर्ष के आसपास है।' : ''));
            } else {
                $state = 'चालू';
                $kya = $verd === 'अशुभ' ? 'यहाँ दिक़्क़त आती रहेगी'
                    : ($verd === 'शुभ' ? 'यहाँ काम बनता चलेगा' : 'कुछ बातें ठीक, कुछ में अड़चन');
                $line = $hi . ' ' . $ord . ' भाव में ' . $verd . ' है। ' . $mein . ' ' . $kya . '।';
            }

            // ---- 2. किसने सुलाया, चाबी किसके पास ----
            $kaun = [];
            if ($asleep && !$awake) {
                if (trim((string) ($wake['cause_hi'] ?? '')) !== '') {
                    $kaun['sulane_wala'] = (string) $wake['cause_hi'];
                }
                if (trim((string) ($wake['agent_hi'] ?? '')) !== '') {
                    $kaun['chaabi'] = (string) $wake['agent_hi'];
                }
                if (trim((string) ($wake['condition'] ?? '')) !== '') {
                    $kaun['shart'] = (string) $wake['condition'];
                }
            }

            // ---- 3. अब क्या करना है — वही जवाब जो निचोड़ देता है ----
            $act = ['status' => 'ज़रूरत नहीं', 'text' => '', 'dir' => '', 'target' => '', 'why' => ''];
            foreach ($issued as $u) {
                if ((string) ($u['planet'] ?? '') === $hi) {
                    $act = ['status' => 'जारी', 'text' => (string) ($u['upay'][0] ?? ''),
                        'dir' => (string) ($u['direction'] ?? ''), 'target' => (string) ($u['target'] ?? ''),
                        'why' => (string) ($u['target_note'] ?? '')];
                    break;
                }
            }
            if ($act['status'] === 'ज़रूरत नहीं') {
                foreach ($held as $hd) {
                    if ((string) ($hd['planet'] ?? '') === $hi) {
                        $act = ['status' => 'कतार में', 'text' => '',
                            'dir' => (string) ($hd['direction'] ?? ''), 'target' => '',
                            'why' => 'पहला उपाय पूरा होने के बाद इसकी बारी — एक साथ कई उपाय शुरू करने से कोई पूरा नहीं होता'];
                        break;
                    }
                }
            }
            if ($act['status'] === 'ज़रूरत नहीं') {
                foreach ($excl as $ex) {
                    if ((string) ($ex['planet'] ?? '') === $hi || (string) ($ex['target'] ?? '') === $hi) {
                        $act = ['status' => 'रोका गया', 'text' => '', 'dir' => '',
                            'target' => (string) ($ex['target'] ?? ''), 'why' => (string) ($ex['reason'] ?? '')];
                        break;
                    }
                }
            }
            if ($act['status'] === 'ज़रूरत नहीं' && $verd !== 'अशुभ' && $verd !== 'निष्क्रिय') {
                $act['why'] = 'यह ग्रह इस समय दिक़्क़त नहीं दे रहा — इसका अलग उपाय ज़रूरी नहीं।';
            } elseif ($act['status'] === 'ज़रूरत नहीं') {
                $act['why'] = 'इस बार के एक-दो उपायों में यह नहीं आया — ऊपर वाला पूरा होने पर दोबारा देखें।';
            }

            $out[$hi] = [
                'hi'        => $hi,
                'house_ord' => $ord,
                'state'     => $state,
                'line'      => $line,
                'verdict'   => $verd,
                'kshamata'  => $ksh,
                'kshamata_hi' => $kshamataHi[$ksh] ?? '',
                'areas'     => $areas,
                'kaun'      => $kaun,
                'action'    => $act,
                // बैठक का विरोध मिटाया नहीं जाता — शर्त बनकर साथ चलता है
                'qualifier' => trim((string) ($p['seat_conflict'] ?? '')),
                'in_dasha'  => $dashaHi !== '' && $dashaHi === $hi,
            ];
        }
        return $out;
    }

    /**
     * हर भाव का निचोड़ — "अभी क्या मानें"।
     *
     * भाव-पन्ने की दिक़्क़त वही थी जो ग्रह-पन्ने की: तथ्य सब थे, मेल कोई नहीं। एक ही
     * कार्ड पर ऊपर दो चेतावनियाँ छपतीं (विश्वासघात की आशंका · अचानक चोट की आशंका)
     * और उसी कार्ड के अंत में **"✅ भाव सबल — कोई उपाय आवश्यक नहीं।"** यह सिर्फ़
     * बेतुका नहीं, ख़तरनाक है: पढ़ने वाला उसी पंक्ति को फ़ैसला मानकर चेतावनी छोड़
     * देता है।
     *
     * यहाँ भाव का दर्जा उसकी चेतावनियों के **साथ** तय होता है, और "उपाय ज़रूरी
     * नहीं" तभी कहा जाता है जब सचमुच कोई चेतावनी न हो।
     *
     * दो अलग शब्द भी साफ़ किए जाते हैं। **स्वामी** = स्थिर मेष-टेवे का मालिक (भाव 1
     * का मंगल), जो कभी नहीं बदलता। **मालिक** = वह ग्रह जिसका यह पक्का घर है और जो
     * अभी यहाँ बैठा है। दोनों को बिना बताए साथ छापने से कार्ड पर दो "मालिक" दिखते
     * थे और पढ़ने वाला उलझ जाता था।
     *
     * @param array<string,array<string,mixed>> $planetBriefs
     * @return array<int,array<string,mixed>>
     */
    private static function houseBriefs(array $lk, array $planetBriefs): array
    {
        $out = [];
        foreach ((array) ($lk['houses'] ?? []) as $hh) {
            $n    = (int) ($hh['house'] ?? 0);
            if ($n < 1) { continue; }
            $ord  = (string) ($hh['house_ord'] ?? '');
            $verd = (string) ($hh['verdict'] ?? 'मध्यम');
            $topicShort = trim((string) (LalKitabData::HOUSE_TOPIC[$n] ?? ''));
            if ($topicShort === '') { $topicShort = 'इस भाव के विषय'; }
            $warn = array_values(array_filter(array_map(
                static fn ($w) => trim((string) (is_array($w) ? ($w['text'] ?? $w['rule'] ?? '') : $w)),
                (array) ($hh['warn'] ?? [])
            )));
            $occHi = (array) ($hh['planets_hi'] ?? []);

            // ---- अभी क्या मानें ----
            if ($verd === 'सुप्त') {
                $line = $topicShort . ' — यह हिस्सा अभी रुका हुआ है, इसमें कुछ आगे नहीं बढ़ रहा।'
                    . (trim((string) ($hh['waker_hi'] ?? '')) !== ''
                        ? ' जगाने वाला ग्रह ' . $hh['waker_hi'] . ' है।' : '');
            } elseif ($verd === 'अस्थिर') {
                $line = $topicShort . ' — यहाँ टिकाव नहीं रहता: बात बनते-बनते पलट जाती है।';
            } else {
                $kya = $verd === 'शुभ' ? 'यहाँ काम बनता चलेगा'
                    : ($verd === 'मंदा' || $verd === 'अशुभ' ? 'यहाँ दिक़्क़त आती रहेगी'
                    : 'कुछ बातें ठीक, कुछ में अड़चन');
                $line = $topicShort . ' — ' . $kya . '।';
            }
            // चेतावनी को दर्जे में घुलने नहीं दिया जाता; वह अलग से साथ चलती है।
            if ($warn !== []) {
                $line .= ' पर इस भाव पर ' . count($warn) . ' चेतावनी भी है — नीचे देखें; '
                    . 'दर्जा अच्छा हो तब भी वह अपने-आप नहीं टलती।';
            }

            // ---- स्वामी बनाम मालिक ----
            $lordHi = (string) ($hh['lord_hi'] ?? '');
            $malik  = trim((string) ($hh['malik_hi'] ?? ''));
            $roles  = [];
            if ($lordHi !== '') {
                $roles[] = 'स्वामी ' . $lordHi . ' (स्थिर मेष-टेवे का, कभी नहीं बदलता)'
                    . (($hh['lord_house'] ?? null) !== null
                        ? ' — अभी ' . LalKitabData::houseOrdinalHi((int) $hh['lord_house']) . ' भाव में' : '');
            }
            if ($malik !== '' && $malik !== $lordHi) {
                $roles[] = 'मालिक ' . $malik . ' (यह उसका पक्का घर है, और वह यहीं बैठा है)';
            }
            if (!empty($hh['vivadit'])) {
                $roles[] = 'इस भाव पर मालिक कोई नहीं — कमरा विवादित है';
            }

            // ---- अब क्या करें — यहाँ बैठे ग्रहों के निचोड़ से ----
            $act = ['status' => '', 'text' => '', 'planet' => ''];
            foreach ($occHi as $ph) {
                $b = $planetBriefs[(string) $ph] ?? null;
                $st = (string) (($b['action']['status']) ?? '');
                if ($st === 'जारी') {
                    $act = ['status' => 'जारी', 'text' => (string) $b['action']['text'], 'planet' => (string) $ph];
                    break;
                }
                if ($st === 'कतार में' && $act['status'] === '') {
                    $act = ['status' => 'कतार में', 'text' => '', 'planet' => (string) $ph];
                }
            }

            $out[$n] = [
                'house' => $n, 'house_ord' => $ord, 'verdict' => $verd,
                'line' => $line, 'warn' => $warn, 'roles' => $roles, 'action' => $act,
                // यही वह पंक्ति है जो पहले चेतावनी के बावजूद "उपाय ज़रूरी नहीं" कह देती थी
                'safe' => $warn === [] && !in_array($verd, ['मंदा', 'अशुभ', 'सुप्त', 'अस्थिर'], true),
                // पढ़ने का क्रम — जिन भावों पर चेतावनी या दिक़्क़त है, वही पहले
                'weight' => count($warn) * 2
                    + (in_array($verd, ['मंदा', 'अशुभ'], true) ? 3 : 0)
                    + ($verd === 'अस्थिर' ? 2 : 0) + ($verd === 'सुप्त' ? 1 : 0),
            ];
        }
        return $out;
    }

    /**
     * हर भाव के कारक का निचोड़।
     *
     * इस पन्ने पर दो असली ग़लतियाँ थीं।
     *
     * **पहली — धुरी की।** कारक-पंक्ति पर *अशुभ* लिखा जाता था, जबकि नीचे उसी ग्रह
     * पर *शुभ* का ठप्पा होता था। दोनों सही थे और दोनों साथ बेतुके: ग्रह की दिशा
     * नेक थी, पर सोया होने से उसकी **क्षमता** शून्य थी। नेक/बद और कितना-कर-सकता-है
     * दो अलग धुरियाँ हैं; कारक की बात हमेशा दूसरी धुरी की है। इसलिए यहाँ अब
     * बलवान/मध्यम/दुर्बल कहा जाता है, शुभ/अशुभ नहीं।
     *
     * **दूसरी — दिशा की।** सोए कारक के लिए *"शराब न पीएँ, वायदा न तोड़ें"* जैसा
     * शांति-उपाय दिया जा रहा था। सोए ग्रह को शांत करना उसे और गहरी नींद सुला देता
     * है — यही वह चूक है जिसे निचोड़-पन्ने पर जाँच X3 रोकती है, पर यह पन्ना उसकी
     * नज़र से बाहर था। अब दिशा पहले तय होती है और सोए कारक पर **जगाना** लिखा जाता
     * है, उसकी चाबी के साथ।
     *
     * @param list<array<string,mixed>> $planets
     * @return array<int,array<string,mixed>>
     */
    private static function karakBriefs(array $lk, array $planets): array
    {
        $pMap = [];
        foreach ($planets as $pe) { $pMap[(string) ($pe['hi'] ?? '')] = $pe; }

        $out = [];
        foreach ((array) ($lk['karak'] ?? []) as $kh) {
            $n = (int) ($kh['house'] ?? 0);
            if ($n < 1) { continue; }
            $rows = [];
            $anyWeak = false; $allStrong = true;
            foreach ((array) ($kh['karaks'] ?? []) as $k) {
                $hi   = (string) ($k['hi'] ?? '');
                $pe   = $pMap[$hi] ?? [];
                $ksh  = (string) ($pe['kshamata'] ?? '');
                $imp  = trim((string) ($pe['impair'] ?? ''));
                $weak = !empty($k['weak']) || $ksh === 'शून्य' || $ksh === 'कमज़ोर';
                $asleep = !empty($k['asleep']) || $imp !== '' || (string) ($pe['verdict'] ?? '') === 'निष्क्रिय';
                if ($weak) { $anyWeak = true; }
                if ($weak || $ksh !== 'प्रबल') { $allStrong = false; }
                // दिशा पहले, उपाय बाद में — उलटा करने पर सोए ग्रह को शांत कर बैठते हैं
                $dir = $asleep ? 'जगाना' : ($weak ? 'बल-वृद्धि' : '');
                $rows[] = [
                    'hi' => $hi,
                    'placed_ord' => (string) ($k['placed_ord'] ?? ''),
                    'bal' => $weak ? 'दुर्बल' : ($ksh === 'प्रबल' ? 'बलवान' : 'मध्यम'),
                    'asleep' => $asleep,
                    'impair' => $imp,
                    'dir' => $dir,
                    'chaabi' => (string) (($pe['wake']['agent_hi']) ?? ''),
                    'remedies' => (array) ($k['remedies'] ?? []),
                ];
            }
            $bal = $anyWeak ? 'दुर्बल' : ($allStrong ? 'बलवान' : 'मध्यम');
            $topic = trim((string) (LalKitabData::HOUSE_TOPIC[$n] ?? ''));
            if ($topic === '') { $topic = 'इस भाव के विषय'; }
            $line = $bal === 'बलवान'
                ? $topic . ' — इनका कारक मज़बूत है, ये विषय अपने बल पर चलते हैं।'
                : ($bal === 'दुर्बल'
                    ? $topic . ' — इनका कारक कमज़ोर है। इसका मतलब बुरा फल नहीं; मतलब यह कि ये विषय '
                      . 'अपने-आप नहीं चलेंगे, इन्हें सहारा चाहिए।'
                    : $topic . ' — कारक सामान्य बल का है, ये विषय ठीक-ठाक चलेंगे।');
            $out[$n] = ['house' => $n, 'house_ord' => (string) ($kh['house_ord'] ?? ''),
                'bal' => $bal, 'line' => $line, 'rows' => $rows];
        }
        return $out;
    }

    /**
     * नौ चरणों का सही विश्लेषण बेकार है अगर दसवाँ उसे ऐसी भाषा में दे जो पढ़ने
     * वाला बरत न सके। और चूक उल्टी दिशा में भी होती है: कठिन शब्द कमज़ोर बात को
     * भी वज़नदार सुना देते हैं — यही वजह है कि यह क्षेत्र उनसे भरा पड़ा है।
     *
     * @param array<string,mixed> $temperament
     * @param list<array<string,mixed>> $core
     * @param array<string,mixed> $upaay
     * @return array<string,mixed>
     */
    private static function clientDoc(array $temperament, array $core, array $upaay, array $lk, array $native): array
    {
        $strengths = array_values(array_filter($core, static fn ($c) => ($c['type'] ?? '') === 'ताक़त'));
        $issues    = array_values(array_filter($core, static fn ($c) => in_array($c['type'] ?? '', ['कठिनाई', 'ठहराव', 'ऋण'], true)));

        // ध्यान देने की हर बात अपने उपाय के साथ — बाद के किसी अनुभाग में नहीं,
        // उसी अनुच्छेद में। जो आदमी तीन पन्ने मुसीबत पढ़कर उपाय तक पहुँचता है,
        // वे तीन पन्ने डर में बिता चुका होता है, और डरा हुआ आदमी निर्देश नहीं पढ़ता।
        // उपाय अपनी कठिनाई से **नाम** के मिलान पर जुड़ता है, क्रम-संख्या से नहीं।
        // क्रम-संख्या से जोड़ने पर राहु की कठिनाई के नीचे गुरु का उपाय छप जाता है —
        // और पढ़ने वाला यह ग़लती तुरंत पकड़ लेता है।
        //
        // और जिस कठिनाई का उपाय इस बार जारी नहीं हुआ, उसके नीचे **वजह** लिखी जाती
        // है। एक-दो उपाय की सीमा जान-बूझकर है (आठ उपायों की सूची दूसरे हफ़्ते छूट
        // जाती है), इसलिए तीसरा उपाय ठूँस देना हल नहीं। पर बिना कुछ कहे छोड़ देना
        // उससे भी बुरा है: पढ़ने वाले को "आपके ऊपर पितृ-ऋण चालू है" पढ़ाकर आगे
        // ख़ाली जगह दिखाना उसे बेबस छोड़ना है। इसलिए तीन में से एक बात हमेशा
        // रहती है — उपाय, या कतार में होने की सूचना, या रुकने का कारण।
        $issued   = (array) ($upaay['issued'] ?? []);
        $heldList = (array) ($upaay['held'] ?? []);
        $excluded = (array) ($upaay['excluded'] ?? []);
        foreach ($issues as $i => $row) {
            $line = $hold = '';
            $want = trim((string) ($row['planet'] ?? ''));
            foreach ($issued as $u) {
                if ($want !== '' && (string) $u['planet'] === $want) {
                    $line = (string) ($u['upay'][0] ?? '');
                    if (trim((string) ($u['target_note'] ?? '')) !== '') {
                        $line = $u['target_note'] . ' ' . $line;
                    }
                    break;
                }
            }
            if ($line === '' && $want !== '') {
                foreach ($heldList as $hd) {
                    if ((string) ($hd['planet'] ?? '') === $want) {
                        $hold = 'इसका उपाय कतार में है — ' . (string) ($hd['reason'] ?? '')
                            . '। एक साथ कई उपाय शुरू करने से कोई पूरा नहीं होता।';
                        break;
                    }
                }
                if ($hold === '') {
                    foreach ($excluded as $ex) {
                        if ((string) ($ex['planet'] ?? '') === $want || (string) ($ex['target'] ?? '') === $want) {
                            $hold = 'इसका उपाय अभी रोका गया है — ' . (string) ($ex['reason'] ?? '') . '।';
                            break;
                        }
                    }
                }
                if ($hold === '') {
                    $hold = 'इस बात का उपाय इस बार जारी नहीं हुआ — पहले ऊपर दिया उपाय पूरा करें, '
                        . 'उसके बाद इसकी बारी आती है।';
                }
            }
            $issues[$i]['upay_inline'] = $line;
            $issues[$i]['hold_reason'] = $hold;
        }

        $d = (array) (($lk['active']['dasha']) ?? []);
        $samay = '';
        if ($d !== []) {
            $samay = ($d['hi'] ?? '') . ' का दौर चल रहा है — ' . (string) ($d['phal_hi'] ?? '')
                . ' ' . (trim((string) ($d['ends_hi'] ?? '')) !== '' ? 'यह दौर ' . $d['ends_hi'] . ' है।' : '');
            if (!empty($d['supported_hi'])) {
                $samay .= ' इस दौरान सहारा इनसे रहेगा: ' . implode(', ', (array) $d['supported_hi']) . '.';
            }
        }

        // मना-शब्दों की जाँच अपने गढ़े वाक्यों पर। ये शब्द इस वक़्त कहीं नहीं हैं —
        // पर एक भी वाक्य बदलते ही चुपचाप घुस सकते हैं, और तब कोई पकड़ने वाला नहीं
        // होता। इसलिए जो अपना लिखा है उसी पर लगातार पहरा। पुस्तक का मूल पाठ (उपाय,
        // वर्जित नियम) इसमें नहीं आता — वह जैसा है वैसा ही रहता है, बदलना उसे
        // झुठलाना होगा।
        $apneVakya = [$temperament['line'], $samay];
        foreach (array_merge($strengths, $issues) as $row) {
            $apneVakya[] = (string) ($row['line'] ?? '');
        }
        $wordWarn = [];
        foreach ($apneVakya as $vk) {
            if ($vk !== '' && self::hasForbidden($vk)) { $wordWarn[] = $vk; }
        }

        return [
            'word_warn'  => $wordWarn,
            'intro'      => 'यह लाल किताब के अनुसार आपकी कुंडली का पढ़ाव है। इसमें आपकी मज़बूती, '
                . 'ध्यान देने की बातें, अभी का समय और करने योग्य उपाय दिए गए हैं।',
            'mizaj'      => $temperament['line'],
            'strengths'  => $strengths,
            'issues'     => $issues,
            'samay'      => trim($samay),
            'upaay'      => $issued,
            'held'       => (array) ($upaay['held'] ?? []),
            'karne'      => self::doList($lk, $upaay),
            'na_karne'   => self::dontList($lk),
            'boundary'   => 'कुंडली रास्ता दिखाती है, बाँधती नहीं। जो लिखा है वह बदला जा सकता है — '
                . 'इसीलिए उपाय हैं। आपकी मेहनत और आपके फ़ैसले भी उतने ही ज़रूरी हैं।',
            'medical'    => 'सेहत से जुड़ी कोई भी बात यहाँ सिर्फ़ ध्यान दिलाने के लिए है। '
                . 'यह चिकित्सा-सलाह नहीं है — जाँच और इलाज डॉक्टर से ही कराएँ, और चल रहा इलाज बंद न करें।',
        ];
    }

    /** @return list<string> */
    private static function doList(array $lk, array $upaay): array
    {
        $out = [];
        foreach ((array) ($upaay['issued'] ?? []) as $u) {
            $out[] = (string) ($u['upay'][0] ?? '');
        }
        foreach ((array) ($lk['planets'] ?? []) as $p) {
            if (($p['verdict'] ?? '') === 'शुभ' && ($p['dos'][0] ?? '') !== '') {
                $out[] = (string) $p['dos'][0];
            }
        }
        return array_slice(array_values(array_unique(array_filter($out))), 0, 5);
    }

    /** @return list<string> */
    private static function dontList(array $lk): array
    {
        $out = [];
        foreach ((array) (($lk['varjit']['forbidden']) ?? []) as $f) {
            $t = trim((string) ($f['rule'] ?? $f['text'] ?? ''));
            if ($t !== '') { $out[] = $t; }
        }
        foreach ((array) ($lk['planets'] ?? []) as $p) {
            if (($p['verdict'] ?? '') === 'अशुभ' && ($p['donts'][0] ?? '') !== '') {
                $out[] = (string) $p['donts'][0];
            }
        }
        return array_slice(array_values(array_unique(array_filter($out))), 0, 5);
    }

    /**
     * मना शब्दों की जाँच — इंजन के अपने गढ़े वाक्यों पर। (पुस्तक के मूल पाठ पर
     * नहीं; वह जैसा है वैसा ही रहता है।)
     */
    public static function hasForbidden(string $text): bool
    {
        foreach (self::MANA_SHABD as $w) {
            if (mb_strpos($text, $w) !== false) { return true; }
        }
        return false;
    }
}
