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
     * तयशुदा सेटिंग्स — दो इंजन अलग सेटिंग पर अलग नतीजे देंगे, इसलिए हर रिपोर्ट
     * के सिरहाने यह दर्ज रहती हैं कि किस नियम पर चला गया।
     */
    public const SETTINGS = [
        'soya_drishti'      => 'reduced',   // सोए ग्रह की दृष्टि आधी मानी गई
        'seat_precedence'   => 'पक्का घर > नीच > उच्च > शत्रु घर > मित्र घर > सम',
        'chhaya_company'    => true,        // राहु-केतु पर संगत भारी पड़ती है
        'rin_severity'      => 'graded',
        'masnui_drishti'    => false,       // मसनूई ग्रह दृष्टि नहीं डालता
    ];

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

        $conflicts   = self::conflicts($planets, $lk);
        $temperament = self::temperament($planets, $houses, $lk);
        $rin         = self::rinFindings($lk, $planets);
        $core        = self::corePoints($planets, $temperament, $rin, $lk);
        // उपाय निचोड़ की कठिनाइयों से चलता है, अपनी अलग सूची से नहीं — वरना रिपोर्ट
        // एक बात को मुख्य कहती है और उपाय किसी और का देती है।
        $upaay       = self::remedySequence($planets, $rin, $native, $lk, $core);
        $client      = self::clientDoc($temperament, $core, $upaay, $lk, $native);

        return [
            'ok'          => true,
            'settings'    => self::SETTINGS,
            'temperament' => $temperament,
            'core_points' => $core,
            'conflicts'   => $conflicts,
            'rin'         => $rin,
            'upaay'       => $upaay,
            'client'      => $client,
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
     * विरोध इकट्ठे करना। ज़्यादातर "विरोध" असल में विरोध होते ही नहीं — एक ग्रह
     * धन में नेक और सेहत में बद हो सकता है, यह दो अलग क्षेत्रों की दो सच्ची
     * बातें हैं। जो बचते हैं वे दर्ज होते हैं, औसत नहीं किए जाते; हारने वाला
     * दावा मिटता नहीं, qualifier बनकर रहता है।
     *
     * @param list<array<string,mixed>> $planets
     * @return list<array<string,string>>
     */
    private static function conflicts(array $planets, array $lk): array
    {
        $out = [];
        foreach ($planets as $p) {
            if (trim((string) ($p['seat_conflict'] ?? '')) !== '') {
                $out[] = [
                    'subject' => (string) $p['hi'],
                    'kind'    => 'बैठक का विरोध',
                    'detail'  => (string) $p['seat_conflict'],
                    'resolve' => 'भार-क्रम — बैठक का तथ्य क़ायम, दूसरा तथ्य शर्त बनकर रहता है',
                ];
            }
            // जागा ग्रह पर सोया भाव — दोनों बातें सच हैं, औसत मना
            if (($p['verdict'] ?? '') !== 'निष्क्रिय' && !empty($p['asleep'])) {
                $out[] = [
                    'subject' => (string) $p['hi'],
                    'kind'    => 'ग्रह जागा, भाव सोया',
                    'detail'  => 'ग्रह काम करने को तैयार है पर भाव अभी ग्रहण नहीं कर रहा',
                    'resolve' => 'अनिर्णीत — फल देर से खुलेगा, ज्योतिषी की राय उपयोगी',
                ];
            }
        }
        // मसनूई बनाम असली — परछाईं कभी शरीर से भारी नहीं
        foreach ((array) ($lk['masnui'] ?? []) as $m) {
            if (($m['verdict'] ?? '') === 'शुभ') { continue; }
            $out[] = [
                'subject' => 'मसनूई ' . (string) ($m['label'] ?? ''),
                'kind'    => 'परछाईं बनाम असली ग्रह',
                'detail'  => (string) ($m['house_ord'] ?? '') . ' भाव में मसनूई फल',
                'resolve' => 'गेट — असली ग्रह का दावा भारी; मसनूई फल महसूस तो होगा पर हल्का',
            ];
        }
        return $out;
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
    private static function rinFindings(array $lk, array $planets): array
    {
        $vMap = [];
        foreach ($planets as $p) { $vMap[(string) $p['hi']] = $p; }

        $entries = [];
        foreach ((array) ($lk['shrap'] ?? []) as $r) {
            if (empty($r['present'])) { continue; }
            $matched = (array) ($r['matched'] ?? []);
            // पुष्टि — कितनी दिशाओं से एक ही बात निकल रही है
            $confidence = count($matched) >= 2 ? 'पक्का' : (count($matched) === 1 ? 'संभावित' : 'कमज़ोर संकेत');

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
            $status = $allDormant ? 'सुप्त' : ($anyBad ? 'चालू' : 'दबा हुआ');

            // दबा हुआ = कोई ग्रह इसे अभी रोक रहा है। उसे शांत करना सबसे ख़तरनाक
            // ग़लती होगी — कवच हटते ही मुसीबत सामने आती है, और वह भी उपाय शुरू
            // करने के हफ़्तों बाद। इसलिए रक्षक का नाम दर्ज रखना ज़रूरी है।
            $protector = '';
            if ($status === 'दबा हुआ') {
                foreach ($carriers as $hi => $pp) {
                    if (($pp['verdict'] ?? '') === 'शुभ') { $protector = $hi; break; }
                }
            }
            $entries[] = [
                'rin'        => (string) ($r['rin'] ?? ''),
                'kind'       => mb_strpos((string) ($r['rin'] ?? ''), 'श्राप') !== false ? 'श्राप' : 'ऋण',
                'confidence' => $confidence,
                'status'     => $status,
                'protector'  => $protector,
                'matched'    => $matched,
                'upay'       => (string) ($r['upay'] ?? ''),
                'phal'       => (string) ($r['ashubh_phal'] ?? ''),
                // पीढ़ी वाली बात हमेशा शर्त के साथ — "होगा" नहीं, "अगर उपाय न किया जाए तो"
                'line_hi'    => 'कुंडली में ' . (string) ($r['rin'] ?? '') . ' का संकेत है। '
                    . 'लाल किताब कहती है कि इसे चुकाया जा सकता है — समय पर उपाय करने से यह आगे नहीं बढ़ता।',
            ];
        }
        // क्रम — पक्का पहले, चालू पहले
        usort($entries, static function (array $a, array $b): int {
            $cw = ['पक्का' => 0, 'संभावित' => 1, 'कमज़ोर संकेत' => 2];
            $sw = ['चालू' => 0, 'दबा हुआ' => 1, 'सुप्त' => 2];
            return [$cw[$a['confidence']], $sw[$a['status']]] <=> [$cw[$b['confidence']], $sw[$b['status']]];
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
                'type' => 'ऋण', 'rank' => 5,
                'title' => $r['rin'] . ' (' . $r['confidence'] . ' · ' . $r['status'] . ')',
                'text'  => $r['line_hi'],
                'source' => 'ऋण-जाँच: ' . implode(', ', $r['matched']),
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
            if ($upay === []) { continue; }

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
        return [
            'issued'    => $issued,
            'held'      => $held,
            'excluded'  => array_values(array_filter($cands, static fn ($c) => $c['excluded'])),
            'protectors'=> $protectors,
            'native_ok' => self::nativeComplete($native),
            'note'      => self::nativeComplete($native)
                ? ''
                : 'उपाय पूरी तरह तय करने के लिए कुछ पारिवारिक जानकारी चाहिए (पिता/माता जीवित हैं या नहीं, '
                  . 'वैवाहिक स्थिति आदि) — क्योंकि कई उपाय इन हालात पर उलट जाते हैं। तब तक नीचे दिए '
                  . 'उपाय सामान्य व सुरक्षित हैं।',
        ];
    }

    /** @param array<string,mixed> $native */
    private static function nativeComplete(array $native): bool
    {
        foreach (['father_living', 'mother_living', 'marital_status'] as $k) {
            if (!isset($native[$k]) || $native[$k] === '' || $native[$k] === 'अज्ञात') { return false; }
        }
        return true;
    }

    // ─────────────────────────────────────────────────────────────
    // चरण 10 — ग्राहक का पन्ना
    // ─────────────────────────────────────────────────────────────

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
        $issued = (array) ($upaay['issued'] ?? []);
        foreach ($issues as $i => $row) {
            $line = '';
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
            $issues[$i]['upay_inline'] = $line;
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

        return [
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
