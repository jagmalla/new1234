<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\LalKitab;

/**
 * लाल किताब कुंडली-मिलान — Lal Kitab compatibility between two charts.
 *
 * लाल किताब में 36 गुण का अष्टकूट नहीं है। उसका विवाह-विचार गिनती का नहीं,
 * जगह का है: कौन-सा घर किस हाल में है, और उस घर का कारक ग्रह क्या कर रहा है।
 * इसलिए यह मिलान पाँच बिंदुओं पर होता है — पाँचों वही हैं जो पुस्तक स्वयं
 * विवाह के नाम पर गिनाती है, और पाँचों की गणना पहले से हर कुंडली में मौजूद है
 * ({@see LalKitabEngine::compute()}):
 *
 *   1. सप्तम भाव    — "भार्या — विवाह, पति-पत्नी…"। जीवनसाथी का घर स्वयं।
 *   2. विवाह-कारक   — शुक्र (स्त्री/पत्नी) · राहु (ससुराल) · केतु (औलाद)।
 *                     ये तीनों नाम पुस्तक के अपने कारक-चक्र से हैं, बाहर से नहीं।
 *   3. मंगल व पाप ग्रह — 1,4,7,8,12 में पाप ग्रह, तीव्रता-क्रम सहित, और पुस्तक
 *                     की अपनी परिहार-सूची — जिसमें कई शर्तें *दूसरी* कुंडली की हैं।
 *   4. गृहस्थी के भाव — 2 (कुटुम्ब/ससुराल) · 4 (घर-सुख) · 12 (शयन-कक्ष)।
 *   5. पितृ-ऋण      — साझा व एकपक्षीय; और जो ऋण विवाह को छूता ही नहीं, वह
 *                     मिलान का दर्जा नहीं गिराता।
 *
 * ── जो जान-बूझकर नहीं किया गया ──
 *  • **प्रतिशत नहीं।** तीन-चार धुरियों का औसत निकालकर "67%" छाप देना नाप जैसा
 *    दिखता है, है नहीं — मान गिनती के होते हैं, और वही गिनती दिखाई जाती है
 *    ("5 में से 3 बिंदु निर्दोष")। साथ ही 36-गुण के अंक से बग़ल में रखा प्रतिशत
 *    पढ़ने वाला अपने-आप जोड़ लेता है, जबकि दोनों अलग प्रणालियाँ हैं।
 *  • **"गुरु = पति-कारक" नहीं।** वह वैदिक परिपाटी है; इस पुस्तक का कारक-चक्र
 *    गुरु को *पिता/बाबा* कहता है। जीवनसाथी का विचार यहाँ सप्तम भाव से होता है।
 *  • **"दोनों मांगलिक तो दोष कट गया" अपनी तरफ़ से नहीं।** वह बात पुस्तक की
 *    परिहार-सूची में है और वहीं से, नाम लेकर, लागू होती है।
 *
 * उपाय अपनी अलग सूची से नहीं बनते — वे {@see LalKitabProcess} की उसी कतार से
 * आते हैं जो दिशा (शांति / बल-वृद्धि / जगाना) पहले तय करती है, वर्जित काम रोकती
 * है और एक-दो से ज़्यादा उपाय जारी नहीं करती। सोए ग्रह को शांत करने वाला उपाय
 * यहाँ से निकल ही नहीं सकता।
 */
final class LalKitabMilan
{
    /** तालिका का क्रम — विवाह से जुड़े ग्रह पहले, बाक़ी बाद में। */
    private const PLANETS = ['Venus', 'Mars', 'Rahu', 'Ketu', 'Moon', 'Jupiter', 'Saturn', 'Sun', 'Mercury'];

    /** मंगली-विचार: दोष के भाव। */
    private const DOSHA_HOUSES = [1, 4, 7, 8, 12];

    /**
     * पाप ग्रह व उनका प्रभाव-क्रम — पुस्तक का अपना वाक्य:
     * "क्रम — मंगल > शनि/सूर्य/राहु > केतु"। यही कारण है कि केवल मंगल देखना
     * अधूरा है: सप्तम में बैठा शनि अब तक "मंगल की दृष्टि से निर्दोष" पढ़ा जाता था।
     */
    private const PAPA_RANK = ['Mars' => 3, 'Saturn' => 2, 'Sun' => 2, 'Rahu' => 2, 'Ketu' => 1];

    /** तीव्रता — "सप्तम भाव में प्रभाव सबसे अधिक, बारहवें में सबसे कम"। */
    private const TIVRATA = [7 => 'सबसे अधिक', 1 => 'अधिक', 4 => 'मध्यम', 8 => 'मध्यम', 12 => 'सबसे कम'];

    /** गृहस्थी के भाव (सप्तम अलग धुरी है)। */
    private const GHAR_HOUSES = [
        2  => 'कुटुम्ब, ससुराल व आर्थिक दशा',
        4  => 'घर-सुख, माता व मकान',
        12 => 'शयन-कक्ष, व्यय व एकांत',
    ];

    /** पुस्तक का कारक-चक्र — विवाह से सीधे जुड़े तीन नाम। */
    private const VIVAH_KARAK = [
        'Venus' => 'स्त्री (पत्नी) व दाम्पत्य-सुख',
        'Rahu'  => 'ससुराल',
        'Ketu'  => 'औलाद',
    ];

    /**
     * राशि+भाव की वे जोड़ियाँ जिन पर पुस्तक मंगल-दोष प्रायः समाप्त मानती है।
     * (परिहार-सूची की दो पंक्तियों का जोड़ — इंजन अब तक इनमें से चार ही जाँचता था।)
     * राशि-अंक: मेष 0 … मीन 11.
     */
    private const PARIHAR_SIGN_HOUSE = [
        1  => [0],            // मेष का मंगल लग्न में
        4  => [7, 0],         // वृश्चिक / मेष का मंगल चौथे
        7  => [9, 3],         // मकर / कर्क का मंगल सातवें
        8  => [3, 11],        // कर्क / मीन का मंगल आठवें
        12 => [8, 0, 3],      // धनु / मेष / कर्क का मंगल बारहवें
    ];

    /**
     * @param array<string,mixed> $lkA  वर की  LalKitabEngine::compute() output
     * @param array<string,mixed> $lkB  कन्या की LalKitabEngine::compute() output
     * @return array<string,mixed>
     */
    public static function match(array $lkA, array $lkB, string $nameA = 'वर', string $nameB = 'कन्या'): array
    {
        if (empty($lkA['ok']) || empty($lkB['ok'])) {
            return ['ok' => false, 'error' => 'दोनों कुंडलियाँ आवश्यक हैं'];
        }

        $A = self::side($lkA, $nameA);
        $B = self::side($lkB, $nameB);

        $axes = [
            'saat'   => self::saatAxis($A, $B),
            'karak'  => self::karakAxis($A, $B),
            'mangal' => self::mangalAxis($A, $B),
            'ghar'   => self::gharAxis($A, $B),
            'rin'    => self::rinAxis($A, $B),
        ];

        // ── दर्जा गेट से, औसत से नहीं ──
        // औसत निकालने पर हर जोड़ा बीच में आ जाता है और कोई मिलान कभी "शुभ" नहीं
        // कहलाता। गेट में हर धुरी अपना फ़ैसला ख़ुद देती है और सिर्फ़ गिनती जुड़ती है।
        $neg = $mix = 0;
        foreach ($axes as $ax) {
            if ($ax['tone'] === 'neg') { $neg++; } elseif ($ax['tone'] === 'mix') { $mix++; }
        }
        $total = count($axes);
        $clear = $total - $neg - $mix;

        if ($neg >= 2)      { $tier = 'kathin'; }
        elseif ($neg === 1) { $tier = 'savdhan'; }
        elseif ($mix >= 2)  { $tier = 'shubh_upay'; }
        else                { $tier = 'shubh'; }

        $tierHi = [
            'shubh'      => 'शुभ मिलान',
            'shubh_upay' => 'शुभ — कुछ बिंदुओं पर उपाय के बाद',
            'savdhan'    => 'सावधानी योग्य — एक बिंदु पर दोष',
            'kathin'     => 'कठिन — एक से अधिक बिंदुओं पर दोष',
        ][$tier];

        $remedies = self::remedies($axes, $A, $B);

        return [
            'ok'        => true,
            'nameA'     => $nameA,
            'nameB'     => $nameB,
            'axes'      => $axes,
            'pairs'     => self::pairs($A, $B),
            'clear'     => $clear,
            'mix_count' => $mix,
            'neg_count' => $neg,
            'total'     => $total,
            'tier'      => $tier,
            'tier_hi'   => $tierHi,
            'verdict'   => self::verdict($tier, $axes, $clear, $total),
            'remedies'  => $remedies,
            'caution'   => self::caution(),
        ];
    }

    // ───────────────────────────────────────────────────────── एक पक्ष की तैयारी

    /**
     * एक कुंडली से वह सब निकालना जो मिलान को चाहिए — ताकि हर धुरी दोबारा
     * खोज-बीन न करे।
     *
     * @param array<string,mixed> $lk
     * @return array<string,mixed>
     */
    private static function side(array $lk, string $name): array
    {
        $byPlanet = [];
        foreach (($lk['planets'] ?? []) as $p) {
            if (trim((string) ($p['planet'] ?? '')) !== '') { $byPlanet[(string) $p['planet']] = $p; }
        }
        $houses = [];
        foreach (($lk['houses'] ?? []) as $h) {
            $houses[(int) ($h['house'] ?? 0)] = $h;
        }
        // उपाय की कतार वहीं से जहाँ से पूरी रिपोर्ट की आती है — दिशा, वर्जित-पहरा
        // और "एक, ज़्यादा से ज़्यादा दो" सब वहीं तय हो चुके होते हैं।
        $proc = LalKitabProcess::run($lk);

        return [
            'name'    => $name,
            'lk'      => $lk,
            'p'       => $byPlanet,
            'h'       => $houses,
            'proc'    => $proc,
            'moon_sign' => (int) ($lk['moon_sign'] ?? 0),
        ];
    }

    /** ग्रह की चार अवस्थाओं में से एक — शुभ · मध्यम · अशुभ · निष्क्रिय। */
    private static function state(array $p): string
    {
        $v = (string) ($p['verdict'] ?? '');
        if (in_array($v, ['शुभ', 'मध्यम', 'अशुभ', 'निष्क्रिय'], true)) { return $v; }
        return !empty($p['is_ashubh']) ? 'अशुभ' : 'मध्यम';
    }

    /**
     * तालिका में दिखने वाला लेबल।
     *
     * पहले यहाँ सिर्फ़ शुभ/अशुभ दो ही ख़ाने थे, इसलिए **निष्क्रिय ग्रह "शुभ" छपता
     * था** — यानी जो ग्रह कुछ कर ही नहीं रहा, उसे हरे रंग में "परस्पर सहयोग व
     * अनुकूलता" कहकर दिखाया जाता था। मापने पर यह हर तीसरे ख़ाने में हो रहा था।
     */
    private static function stateLabel(array $p): string
    {
        $st = self::state($p);
        if ($st === 'निष्क्रिय') { return 'निष्क्रिय (सोया)'; }
        $dig = (string) ($p['status'] ?? '');
        $pre = in_array($dig, ['उच्च', 'नीच', 'स्वगृही'], true) ? $dig . ' · ' : '';
        if ($st === 'शुभ') {
            $k = (string) ($p['kshamata'] ?? '');
            return $pre . 'शुभ' . ($k === 'कमज़ोर' ? ' (क्षमता कमज़ोर)' : '');
        }
        return $pre . $st;
    }

    /** ग्रह जिस पर मिलान भरोसा नहीं कर सकता — अशुभ, या जो जाग ही नहीं रहा। */
    private static function unfit(array $p): bool
    {
        return in_array(self::state($p), ['अशुभ', 'निष्क्रिय'], true);
    }

    // ───────────────────────────────────────────────────── 1. सप्तम भाव

    /**
     * जीवनसाथी का घर। पुस्तक का विषय-वाक्य ख़ुद कहता है: "भार्या — विवाह,
     * पति-पत्नी, व्यवहार, व्यापार में भागीदारी…"। इसलिए मिलान की पहली बात
     * नौ ग्रहों का औसत नहीं, यह एक घर है — दोनों तरफ़।
     *
     * @param array<string,mixed> $A
     * @param array<string,mixed> $B
     * @return array<string,mixed>
     */
    private static function saatAxis(array $A, array $B): array
    {
        $read = static function (array $S): array {
            $h7 = $S['h'][7] ?? [];
            $verdict = (string) ($h7['verdict'] ?? 'मध्यम');
            $occ = [];
            $phal = LalKitabData::section('grah_bhav_phal');
            foreach ((array) ($h7['planets'] ?? []) as $en) {
                $p = $S['p'][$en] ?? [];
                $st = self::state($p);
                $line = (string) ($phal[$en]['7'][$st === 'अशुभ' || $st === 'निष्क्रिय' ? 'mandi' : 'nek'] ?? '');
                $occ[] = [
                    'hi'    => (string) ($p['hi'] ?? LalKitabData::planetHi($en)),
                    'en'    => $en,
                    'state' => $st,
                    'papa'  => isset(self::PAPA_RANK[$en]),
                    'phal'  => $line,
                ];
            }
            // भाव का बल — तीन दर्जे, ताकि "किसका सप्तम किसे सँभालेगा" कहा जा सके।
            //
            // "मालिक बाहर है" यहाँ दर्जा नहीं गिराता। स्थिर मेष-टेवे में सप्तम का
            // मालिक शुक्र है और वह सप्तम में शायद ही कभी बैठता है — नापने पर यह
            // बात 100 में से 99 कुंडलियों पर सच निकली। जो बात हर किसी पर लागू हो
            // वह मिलान में कुछ नहीं बताती; वह तभी छपती है जब उसी घर में शत्रु भी
            // बैठा हो (12%) — और वह हालत भाव के अपने दर्जे में पहले से गिनी जा
            // चुकी होती है।
            $bad = $verdict === 'अशुभ' || $verdict === 'अस्थिर';
            foreach ($occ as $o) { if ($o['papa'] && ($o['state'] === 'अशुभ')) { $bad = true; } }
            $dull = !$bad && $verdict === 'सुप्त';
            return [
                'verdict'    => $verdict,
                'occ'        => $occ,
                'owner_away' => $h7['owner_away'] ?? null,
                'pred'       => (string) ($h7['pred_head'] ?? ''),
                'strong'     => !$bad && !$dull,
                'dull'       => $dull,
                'bad'        => $bad,
            ];
        };

        $a = $read($A); $b = $read($B);
        $detail = [];
        foreach ([[$A, $a], [$B, $b]] as [$S, $r]) {
            $who = $S['name'];
            $line = $who . ': सप्तम भाव — ' . $r['verdict'];
            $line .= $r['occ'] === []
                ? ' · कोई ग्रह नहीं'
                : ' · ' . implode(', ', array_map(static fn ($o) => $o['hi'] . ' (' . $o['state'] . ')', $r['occ']));
            if (!empty($r['owner_away']['hi'])) {
                $line .= ' · मालिक ' . $r['owner_away']['hi'] . ' ' . $r['owner_away']['sits'] . ' भाव में';
            }
            $detail[] = $line;
            foreach ($r['occ'] as $o) {
                if (trim($o['phal']) !== '') {
                    $detail[] = '📖 ' . $who . ' — सप्तम का ' . $o['hi'] . ': ' . $o['phal'];
                }
            }
            if (!empty($r['owner_away']['foes'])) {
                $detail[] = '⚠ ' . $who . ': सप्तम का मालिक ' . $r['owner_away']['hi'] . ' बाहर है और यहाँ '
                    . implode(', ', (array) $r['owner_away']['foes']) . ' (शत्रु) बैठा है — रखवाला ग़ैर-हाज़िर।';
            }
        }

        if ($a['bad'] && $b['bad']) {
            $tone = 'neg';
            $reason = 'दोनों की कुंडली में सप्तम भाव दबाव में है — दाम्पत्य का घर किसी एक तरफ़ से भी नहीं सँभला। '
                . 'दोनों को सप्तम भाव का उपाय करना आवश्यक है।';
        } elseif ($a['bad'] || $b['bad']) {
            // मिलान का सवाल यह नहीं कि किसी एक का सप्तम कमज़ोर है — सवाल यह है कि
            // दोनों मिलकर यह घर उठा पाते हैं या नहीं। दूसरी तरफ़ का सप्तम मज़बूत
            // हो तो वही उठा लेता है; तब यह बिंदु रोक नहीं, सहारा है।
            $weak = $a['bad'] ? $A['name'] : $B['name'];
            $othr = $a['bad'] ? $B : $A;
            $othrR = $a['bad'] ? $b : $a;
            $tone = $othrR['strong'] ? 'pos' : 'mix';
            $reason = $weak . ' का सप्तम भाव दबाव में है';
            $reason .= $othrR['strong']
                ? ', पर ' . $othr['name'] . ' का सप्तम मज़बूत है — वही पक्ष गृहस्थी को सँभालेगा। '
                  . 'यह जोड़ी इस बिंदु पर पूरी है; ' . $weak . ' सप्तम का उपाय कर ले तो और अच्छा।'
                : ' और दूसरी तरफ़ भी यह घर पूरा जागा हुआ नहीं — ' . $weak . ' का उपाय आवश्यक।';
        } elseif ($a['dull'] && $b['dull']) {
            // एक तरफ़ का सुप्त सप्तम मिलान नहीं रोकता — दूसरी तरफ़ का जागा हुआ घर
            // उसे उठा लेता है। दर्जा तभी गिरता है जब दोनों तरफ़ यह घर सोया हो।
            $tone = 'mix';
            $reason = 'दोनों की कुंडली में सप्तम भाव सुप्त है — यह बुरा फल नहीं, ठहरा हुआ फल है: '
                . 'दाम्पत्य के विषय देर से खुलते हैं। इसका उपाय शांति नहीं, भाव को जगाना है।';
        } elseif ($a['dull'] || $b['dull']) {
            $tone = 'pos';
            $who  = $a['dull'] ? $A['name'] : $B['name'];
            $othr = $a['dull'] ? $B['name'] : $A['name'];
            $reason = $who . ' का सप्तम भाव सुप्त है, पर ' . $othr . ' का जागा हुआ — यह घर उसी तरफ़ से चलेगा। '
                . 'मिलान इस बिंदु पर रुकता नहीं; ' . $who . ' चाहे तो भाव जगाने का उपाय कर ले।';
        } else {
            $tone = 'pos';
            $reason = 'दोनों की कुंडली में दाम्पत्य का घर साफ़ है — न पाप ग्रह का दबाव, न भाव सुप्त।';
        }

        return [
            'key'      => 'saat',
            'label'    => 'सप्तम भाव — दाम्पत्य का घर',
            'tone'     => $tone,
            'reason'   => $reason,
            'detail'   => $detail,
            'a'        => $a,
            'b'        => $b,
            'remedies' => self::houseRemedies($A, $B, 7, $a['bad'] || $a['dull'], $b['bad'] || $b['dull']),
        ];
    }

    // ───────────────────────────────────────────────────── 2. विवाह-कारक

    /**
     * विवाह के कारक ग्रह — शुक्र (स्त्री/दाम्पत्य-सुख) · राहु (ससुराल) ·
     * केतु (औलाद)। तीनों नाम पुस्तक के अपने कारक-चक्र से हैं।
     *
     * पहले ये तीन नौ ग्रहों की क़तार में बराबरी से खड़े थे — यानी ससुराल का कारक
     * और तीसरे भाव का बुध एक ही वज़न रखते थे। मिलान में यह ठीक उलटा है।
     *
     * @return array<string,mixed>
     */
    private static function karakAxis(array $A, array $B): array
    {
        $rows = [];
        $venAsh = 0;      // शुक्र कितनी तरफ़ अशुभ
        $venSleep = 0;    // शुक्र कितनी तरफ़ सोया
        $secFlag = [];    // राहु/केतु — जहाँ वे सचमुच विवाह पर पड़ रहे हैं

        foreach (self::VIVAH_KARAK as $en => $vishay) {
            $pa = $A['p'][$en] ?? null; $pb = $B['p'][$en] ?? null;
            if ($pa === null || $pb === null) { continue; }
            $sa = self::state($pa); $sb = self::state($pb);
            $ua = self::unfit($pa); $ub = self::unfit($pb);
            $hi = (string) ($pa['hi'] ?? LalKitabData::planetHi($en));

            if ($en === 'Venus') {
                $venAsh   = ($sa === 'अशुभ' ? 1 : 0) + ($sb === 'अशुभ' ? 1 : 0);
                $venSleep = ($sa === 'निष्क्रिय' ? 1 : 0) + ($sb === 'निष्क्रिय' ? 1 : 0);
            } else {
                // राहु का अशुभ होना 100 में से 60 कुंडलियों की आम बात है — अकेले
                // उसी से मिलान का दर्जा गिराना हर दूसरे जोड़े को डराना है। वह तभी
                // गिनती में आता है जब वह विवाह के घरों (2·4·7·12) में भी बैठा हो —
                // यानी जब उसका अशुभ होना इसी विषय पर पड़ रहा हो।
                $mHouse = [2, 4, 7, 12];
                $ha = in_array((int) ($pa['house'] ?? 0), $mHouse, true) && $sa === 'अशुभ';
                $hb = in_array((int) ($pb['house'] ?? 0), $mHouse, true) && $sb === 'अशुभ';
                if ($ha && $hb) { $secFlag[] = $hi; }
            }

            if ($sa === 'अशुभ' && $sb === 'अशुभ') {
                $note = 'दोनों तरफ़ ' . $hi . ' अशुभ — ' . $vishay . ' का पक्ष किसी की कुंडली से नहीं सँभलता।';
            } elseif ($sa === 'निष्क्रिय' && $sb === 'निष्क्रिय') {
                $note = 'दोनों तरफ़ ' . $hi . ' सोया हुआ — ' . $vishay . ' का विषय रुका रहेगा, बिगड़ा नहीं। '
                    . 'इसका उपाय शांति नहीं, जगाना है।';
            } elseif ($ua || $ub) {
                $strong = $ua ? $B['name'] : $A['name'];
                $weak   = $ua ? $A['name'] : $B['name'];
                $note = $strong . ' का ' . $hi . ' ठीक है — ' . $vishay . ' का पक्ष उसी तरफ़ से सँभलेगा; '
                    . $weak . ' उपाय कर ले।';
            } else {
                $note = 'दोनों तरफ़ ' . $hi . ' ठीक — ' . $vishay . ' में अनुकूलता।';
            }
            $rows[] = [
                'en' => $en, 'hi' => $hi, 'vishay' => $vishay, 'mukhya' => $en === 'Venus',
                'a_house' => (int) ($pa['house'] ?? 0), 'a_state' => self::stateLabel($pa), 'a_bad' => $ua,
                'b_house' => (int) ($pb['house'] ?? 0), 'b_state' => self::stateLabel($pb), 'b_bad' => $ub,
                'tone' => ($sa === 'अशुभ' && $sb === 'अशुभ') ? 'neg' : (($ua || $ub) ? 'mix' : 'pos'),
                'note' => $note,
            ];
        }

        if ($venAsh === 2 || ($venAsh === 1 && $secFlag !== [])) {
            $tone = 'neg';
            $reason = $venAsh === 2
                ? 'शुक्र — पुस्तक का स्त्री/दाम्पत्य-कारक — दोनों कुंडलियों में अशुभ है। यह मिलान का सबसे '
                  . 'सीधा बिंदु है; दोनों को शुक्र का उपाय करना आवश्यक।'
                : 'एक तरफ़ शुक्र अशुभ है और साथ ही ' . implode(', ', $secFlag)
                  . ' भी विवाह के घर में दोनों तरफ़ अशुभ — दो कारक एक साथ दबे हैं।';
        } elseif ($venAsh === 1) {
            $tone = 'mix';
            $reason = 'एक पक्ष में शुक्र अशुभ है — दाम्पत्य-सुख का भार दूसरे पक्ष पर आएगा; उसी व्यक्ति का '
                . 'शुक्र-उपाय आवश्यक।';
        } elseif ($venSleep === 2) {
            $tone = 'mix';
            $reason = 'शुक्र दोनों तरफ़ सोया हुआ है — दाम्पत्य का सुख बिगड़ा नहीं, रुका हुआ है। दोनों को '
                . 'शुक्र जगाने का उपाय करना चाहिए; शांत करने का नहीं।';
        } elseif ($venSleep === 1) {
            // एक तरफ़ का सोया शुक्र तभी छूट सकता है जब दूसरी तरफ़ का शुक्र सचमुच
            // शुभ हो। दूसरा भी मध्यम हो तो "दोनों तरफ़ ठीक है" कहना उसी तालिका
            // को झुठलाना है जो नीचे "निष्क्रिय (सोया)" छाप रही है।
            $vs = $A['p']['Venus'] ?? []; $vo = $B['p']['Venus'] ?? [];
            $sleepSide  = self::state($vs) === 'निष्क्रिय' ? $A['name'] : $B['name'];
            $otherState = self::state(self::state($vs) === 'निष्क्रिय' ? $vo : $vs);
            if ($otherState === 'शुभ') {
                $tone = 'pos';
                $reason = $sleepSide . ' का शुक्र सोया हुआ है, पर दूसरी तरफ़ का शुक्र शुभ है — दाम्पत्य-सुख '
                    . 'उसी पक्ष से चलेगा। ' . $sleepSide . ' चाहे तो शुक्र जगाने का उपाय कर ले।';
            } else {
                $tone = 'mix';
                $reason = $sleepSide . ' का शुक्र सोया हुआ है और दूसरी तरफ़ भी वह पूरे बल में नहीं — '
                    . 'दाम्पत्य-सुख देर से खुलेगा। उपाय शांति नहीं, शुक्र को जगाना है।';
            }
        } elseif ($secFlag !== []) {
            $tone = 'mix';
            $reason = implode(', ', $secFlag) . ' विवाह के घर में दोनों तरफ़ अशुभ — '
                . 'शुक्र ठीक है, इसलिए दाम्पत्य का मूल पक्ष सँभला रहेगा; इसी एक विषय पर ध्यान चाहिए।';
        } else {
            $tone = 'pos';
            $reason = 'शुक्र — दाम्पत्य का मुख्य कारक — दोनों तरफ़ ठीक है, और ससुराल/औलाद के कारक भी '
                . 'विवाह के घरों पर दबाव नहीं डाल रहे।';
        }

        return [
            'key'   => 'karak',
            'label' => 'विवाह-कारक — शुक्र · राहु · केतु',
            'tone'  => $tone,
            'reason'=> $reason,
            'rows'  => $rows,
            'detail'=> array_map(static fn ($r) => $r['hi'] . ' (' . $r['vishay'] . ') — '
                . $r['a_state'] . ' / ' . $r['b_state'], $rows),
            'remedies' => [],
        ];
    }

    // ───────────────────────────────────────────────────── 3. मंगल व पाप ग्रह

    /**
     * मंगली-विचार, पुस्तक के अपने तीन वाक्यों पर:
     *   • दोष के भाव 1,4,7,8,12
     *   • "मंगल, शनि, सूर्य, राहु व केतु पाप ग्रह हैं… इनके इन भावों में होने पर
     *      भी मंगल दोष सदृश दुष्प्रभाव" — क्रम: मंगल > शनि/सूर्य/राहु > केतु
     *   • "लग्न के अतिरिक्त चन्द्र राशि या शुक्र से भी"
     * और परिहार-सूची — जिसकी कई शर्तें *दूसरी* कुंडली की हैं और इसीलिए वे केवल
     * मिलान के समय जाँची जा सकती हैं। अब तक वे सूची में छपकर रह जाती थीं।
     *
     * @return array<string,mixed>
     */
    private static function mangalAxis(array $A, array $B): array
    {
        $a = self::doshaScan($A);
        $b = self::doshaScan($B);
        // परिहार — अपनी कुंडली के, और दूसरी कुंडली के
        $a['parihar'] = array_merge(self::pariharOwn($A), self::pariharCross($A, $B, (int) ($a['hits'][0]['rank'] ?? 0)));
        $b['parihar'] = array_merge(self::pariharOwn($B), self::pariharCross($B, $A, (int) ($b['hits'][0]['rank'] ?? 0)));

        // बचा हुआ बल — परिहार लागू हो तो दो दर्जे घटते हैं (पुस्तक "प्रायः समाप्त"
        // कहती है; हम उसे "घट जाता है" तक ही ले जाते हैं, मिटा हुआ नहीं मानते)।
        foreach ([&$a, &$b] as &$r) {
            $r['residual'] = max(0, $r['severity'] - ($r['parihar'] !== [] ? 2 : 0));
        }
        unset($r);

        $detail = [];
        foreach ([[$A, $a], [$B, $b]] as [$S, $r]) {
            if ($r['hits'] === []) {
                $detail[] = $S['name'] . ': 1, 4, 7, 8, 12 — किसी भाव में पाप ग्रह नहीं।';
                continue;
            }
            foreach ($r['hits'] as $hit) {
                $detail[] = $S['name'] . ': ' . $hit['hi'] . ' ' . LalKitabData::houseOrdinalHi($hit['house'])
                    . ' भाव में — तीव्रता ' . $hit['tivrata'] . ' · ' . $hit['effect'];
            }
            foreach ($r['extra'] as $ex) { $detail[] = $S['name'] . ': ' . $ex; }
            foreach ($r['parihar'] as $ph) { $detail[] = '✔ ' . $S['name'] . ' — परिहार लागू: ' . $ph; }
            if ($r['parihar'] === [] && $r['severity'] > 0) {
                $detail[] = '✖ ' . $S['name'] . ' — पुस्तक की परिहार-सूची में से कोई शर्त इस जोड़ी पर पूरी नहीं होती।';
            }
        }

        $res = max($a['residual'], $b['residual']);
        if ($res >= 2) {
            $tone = 'neg';
            $who = $a['residual'] >= 2 ? ($b['residual'] >= 2 ? 'दोनों' : $A['name']) : $B['name'];
            $reason = $who . ' की ओर से मंगल/पाप-ग्रह का दोष बिना परिहार के खड़ा है — विवाह से पूर्व '
                . 'उसी भाव व ग्रह का उपाय करना आवश्यक।';
        } elseif ($res === 1) {
            $tone = 'mix';
            $reason = 'दोष है, पर या तो उसकी तीव्रता कम है या पुस्तक की परिहार-शर्त लागू हो रही है — '
                . 'बल घट जाता है, इसलिए साधारण उपाय पर्याप्त है।';
        } elseif ($a['hits'] !== [] || $b['hits'] !== []) {
            $tone = 'pos';
            $reason = 'दोष-भावों में ग्रह तो हैं, पर पुस्तक की परिहार-शर्तें दोनों तरफ़ लागू हो रही हैं — '
                . 'मंगल की दृष्टि से यह जोड़ी रुकती नहीं।';
        } else {
            $tone = 'pos';
            $reason = 'किसी की कुंडली में 1, 4, 7, 8, 12 भाव में पाप ग्रह नहीं — मंगली-विचार से मिलान निर्दोष है।';
        }

        // उपाय केवल वहाँ जहाँ दोष सचमुच मार रहा है। जो पाप ग्रह ख़ुद सोया हुआ है
        // वह दोष-भाव में बैठकर भी चोट नहीं कर रहा — उसे "शांत" करना उल्टी दिशा
        // का उपाय है, और यही चूक इस तंत्र में सबसे महँगी पड़ती है।
        $remedies = [];
        foreach ([[$A, $a], [$B, $b]] as [$S, $r]) {
            if ($r['residual'] < 1) { continue; }
            $top = $r['hits'][0] ?? null;
            if ($top === null || $top['state'] === 'निष्क्रिय') { continue; }
            $up = trim((string) ($S['lk']['manglik']['upay'] ?? ''));
            if ($up === '' && $top['en'] !== 'Mars') {
                $up = trim((string) (($S['p'][$top['en']]['sheeghra'] ?? '')));
            }
            if ($up !== '') {
                $remedies[] = ['who' => $S['name'], 'hi' => $top['hi'], 'text' => $up,
                    'direction' => 'शांति',
                    'why' => $top['hi'] . ' ' . LalKitabData::houseOrdinalHi($top['house'])
                        . ' भाव में (तीव्रता ' . $top['tivrata'] . ') और परिहार से पूरा नहीं ढका'];
            }
        }

        return [
            'key'    => 'mangal',
            'label'  => 'मंगल व पाप ग्रह — दोष, तीव्रता व परिहार',
            'tone'   => $tone,
            'reason' => $reason,
            'detail' => $detail,
            'a'      => $a,
            'b'      => $b,
            'remedies' => $remedies,
        ];
    }

    /**
     * एक कुंडली में दोष-भावों के पाप ग्रह + उनका बल।
     *
     * @return array<string,mixed>
     */
    private static function doshaScan(array $S): array
    {
        static $effect = [
            1 => 'शरीर, स्वभाव व स्वास्थ्य पर', 4 => 'सुख, माता, भूमि-वाहन व घरेलू शांति पर',
            7 => 'दाम्पत्य, जीवनसाथी व साझेदारी पर', 8 => 'आयु, दुर्घटना व अकस्मात बाधाओं पर',
            12 => 'व्यय, शयन-सुख व विदेश पर',
        ];
        $hits = []; $sev = 0;
        foreach (self::PAPA_RANK as $en => $rank) {
            $p = $S['p'][$en] ?? null;
            if ($p === null) { continue; }
            $h = (int) ($p['house'] ?? 0);
            if (!in_array($h, self::DOSHA_HOUSES, true)) { continue; }
            $tiv = self::TIVRATA[$h] ?? 'मध्यम';
            $hits[] = [
                'en' => $en, 'hi' => (string) ($p['hi'] ?? LalKitabData::planetHi($en)),
                'house' => $h, 'rank' => $rank, 'tivrata' => $tiv, 'effect' => $effect[$h] ?? '',
                'state' => self::state($p),
            ];
            // बल — ऊँचा दर्जा वही जहाँ पुस्तक ख़ुद ऊँचा कहती है: मंगल, और सप्तम/लग्न।
            $s = $rank >= 3 ? 3 : ($rank >= 2 ? 2 : 1);
            if ($h === 7) { $s++; } elseif ($h === 12) { $s--; }
            if (self::state($p) === 'निष्क्रिय') { $s--; }   // जो ग्रह सोया है, वह मार भी नहीं रहा
            $sev = max($sev, max(1, $s));
        }
        usort($hits, static fn ($x, $y) => [$y['rank'], $y['house'] === 7 ? 1 : 0] <=> [$x['rank'], $x['house'] === 7 ? 1 : 0]);

        // "लग्न के अतिरिक्त चन्द्र राशि या शुक्र से भी" — वही मंगल, दूसरे संदर्भ-बिंदु से
        $extra = [];
        $mars = $S['p']['Mars'] ?? null;
        if ($mars !== null) {
            $ms = (int) ($mars['sign'] ?? 0);
            $refs = ['चन्द्र' => $S['moon_sign'], 'शुक्र' => (int) (($S['p']['Venus']['sign'] ?? 0))];
            foreach ($refs as $refHi => $refSign) {
                $hh = ((($ms - $refSign) % 12) + 12) % 12 + 1;
                if (in_array($hh, self::DOSHA_HOUSES, true)) {
                    $extra[] = $refHi . ' से भी मंगल ' . LalKitabData::houseOrdinalHi($hh) . ' भाव में पड़ता है '
                        . '(पुस्तक: "लग्न के अतिरिक्त चन्द्र राशि या शुक्र से भी")';
                    $sev = max($sev, 2);
                }
            }
        }

        return ['hits' => $hits, 'extra' => $extra, 'severity' => $sev, 'parihar' => [], 'residual' => $sev];
    }

    /**
     * परिहार जो इसी कुंडली से जाँचे जा सकते हैं।
     *
     * @return list<string>
     */
    private static function pariharOwn(array $S): array
    {
        $out = [];
        $mars = $S['p']['Mars'] ?? null;
        if ($mars !== null) {
            $mh = (int) ($mars['house'] ?? 0);
            $ms = (int) ($mars['sign'] ?? 0);
            if (in_array($ms, self::PARIHAR_SIGN_HOUSE[$mh] ?? [], true)) {
                $out[] = LalKitabData::signHi(\AutoBusiness\Astro\Calc\Charts::SIGNS[$ms] ?? '') . ' राशि का मंगल '
                    . LalKitabData::houseOrdinalHi($mh) . ' भाव में';
            }
            // "मंगली योग कारक ग्रह स्वराशि, मूल त्रिकोण या उच्च राशि में हो"
            if (in_array($ms, [0, 7, 9], true)) {
                $out[] = 'मंगल स्वराशि/उच्च राशि (' . LalKitabData::signHi(\AutoBusiness\Astro\Calc\Charts::SIGNS[$ms] ?? '') . ') में';
            }
        }
        // "द्वितीय भाव में चन्द्र-शुक्र की युति, केन्द्र में चन्द्र-मंगल की युति,
        //  मंगल-गुरु की युति, या गुरु मंगल को पूर्ण दृष्टि से देखे"
        $hOf = static fn (string $en) => (int) (($S['p'][$en]['house'] ?? 0));
        if ($hOf('Moon') === 2 && $hOf('Venus') === 2) { $out[] = 'द्वितीय भाव में चन्द्र-शुक्र की युति'; }
        if ($hOf('Moon') === $hOf('Mars') && in_array($hOf('Mars'), [1, 4, 7, 10], true)) {
            $out[] = 'केन्द्र में चन्द्र-मंगल की युति';
        }
        if ($hOf('Mars') !== 0 && $hOf('Mars') === $hOf('Jupiter')) { $out[] = 'मंगल-गुरु की युति'; }
        $jh = $hOf('Jupiter'); $mh2 = $hOf('Mars');
        if ($jh > 0 && $mh2 > 0 && ($jh !== $mh2)) {
            $asp = LalKitabTeva::aspectsFrom($jh);
            if ((int) ($asp[$mh2] ?? 0) >= 100) { $out[] = 'गुरु की पूर्ण दृष्टि मंगल पर'; }
        }
        // "सप्तमेश या शुक्र बलवान हो तथा सप्तम भाव इनसे युत या दृष्ट हो"
        // (स्थिर मेष-टेवे में सप्तम का स्वामी शुक्र ही है।)
        $ven = $S['p']['Venus'] ?? null;
        if ($ven !== null && self::state($ven) === 'शुभ') {
            $vh = (int) ($ven['house'] ?? 0);
            $asp = $vh > 0 ? LalKitabTeva::aspectsFrom($vh) : [];
            if ($vh === 7 || (int) ($asp[7] ?? 0) >= 100) { $out[] = 'बलवान शुक्र (सप्तमेश) सप्तम भाव में या उस पर दृष्टि'; }
        }
        // "बली गुरु शुक्र की राशि या अष्टम भाव में हो तथा सप्तमेश बलवान होकर
        //  केन्द्र या त्रिकोण में हो"
        $jup = $S['p']['Jupiter'] ?? null;
        if ($jup !== null && $ven !== null && self::state($jup) === 'शुभ' && self::state($ven) === 'शुभ') {
            $jSign = (int) ($jup['sign'] ?? -1);
            $jOk = (int) ($jup['house'] ?? 0) === 8 || in_array($jSign, [1, 6], true);   // वृष / तुला = शुक्र की राशियाँ
            if ($jOk && in_array((int) ($ven['house'] ?? 0), [1, 4, 5, 7, 9, 10], true)) {
                $out[] = 'बली गुरु (अष्टम भाव या शुक्र की राशि में) व बलवान शुक्र केन्द्र/त्रिकोण में';
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * परिहार जो केवल *दूसरी* कुंडली से जाँचे जा सकते हैं — यही वह हिस्सा है
     * जिसे पुस्तक "कुंडली मिलाते समय" कहकर लिखती है और जो अब तक कहीं लागू
     * नहीं होता था।
     *
     * @return list<string>
     */
    private static function pariharCross(array $S, array $O, int $ownRank = 0): array
    {
        $out = [];
        $sat = $O['p']['Saturn'] ?? null;
        if ($sat !== null && in_array((int) ($sat['house'] ?? 0), self::DOSHA_HOUSES, true)) {
            $out[] = 'दूसरी कुंडली (' . $O['name'] . ') में शनि ' . LalKitabData::houseOrdinalHi((int) $sat['house'])
                . ' भाव में — पुस्तक: "कुंडली मिलाते समय अन्य कुंडली में 1,4,7,8,12वें भाव में शनि हो"';
        }
        // दूसरी शर्त — "एक की कुंडली में मंगली योग हो तथा दूसरे की कुंडली में
        // मंगली-योग-कारक भाव में कोई पाप ग्रह स्थित हो"। यहाँ एक व्याख्या जोड़ी
        // गई है, और वह जान-बूझकर: दूसरी तरफ़ का ग्रह कम-से-कम उतने ही दर्जे का
        // होना चाहिए। पुस्तक का अपना क्रम "मंगल > शनि/सूर्य/राहु > केतु" है, और
        // उसे न मानने पर बारहवें का केतु सातवें के मंगल को काट देता — यानी सबसे
        // भारी दोष सबसे हल्के ग्रह से ढक जाता।
        foreach (self::PAPA_RANK as $en => $rank) {
            $p = $O['p'][$en] ?? null;
            if ($p === null || $en === 'Saturn' || $rank < $ownRank) { continue; }
            if (in_array((int) ($p['house'] ?? 0), self::DOSHA_HOUSES, true)) {
                $out[] = 'दूसरी कुंडली (' . $O['name'] . ') में भी दोष-भाव में समान दर्जे का पाप ग्रह — '
                    . (string) ($p['hi'] ?? '') . ' ' . LalKitabData::houseOrdinalHi((int) $p['house']) . ' भाव में';
                break;   // एक ही बार — यह एक ही परिहार-शर्त है, सूची नहीं
            }
        }
        return $out;
    }

    // ───────────────────────────────────────────────────── 4. गृहस्थी के भाव

    /**
     * 2 (कुटुम्ब/ससुराल) · 4 (घर-सुख) · 12 (शयन-कक्ष) — विवाह के बाद का
     * रोज़ का जीवन इन्हीं तीन घरों में बीतता है।
     *
     * @return array<string,mixed>
     */
    private static function gharAxis(array $A, array $B): array
    {
        $scan = static function (array $S): array {
            $bad = []; $dull = [];
            foreach (self::GHAR_HOUSES as $hn => $vishay) {
                $v = (string) (($S['h'][$hn]['verdict'] ?? 'मध्यम'));
                if ($v === 'अशुभ' || $v === 'अस्थिर') { $bad[] = [$hn, $vishay, $v]; }
                elseif ($v === 'सुप्त') { $dull[] = [$hn, $vishay, $v]; }
            }
            return ['bad' => $bad, 'dull' => $dull];
        };
        $a = $scan($A); $b = $scan($B);

        $detail = [];
        foreach ([[$A, $a], [$B, $b]] as [$S, $r]) {
            foreach ($r['bad'] as [$hn, $vishay, $v]) {
                $detail[] = $S['name'] . ': ' . LalKitabData::houseOrdinalHi($hn) . ' भाव (' . $vishay . ') — ' . $v;
            }
            foreach ($r['dull'] as [$hn, $vishay, $v]) {
                $detail[] = $S['name'] . ': ' . LalKitabData::houseOrdinalHi($hn) . ' भाव (' . $vishay . ') — सुप्त '
                    . '(रुका हुआ फल — इसका उपाय जगाना है)';
            }
        }
        if ($detail === []) { $detail[] = 'दोनों की कुंडलियों में कुटुम्ब, घर-सुख व शयन-कक्ष के भाव ठीक हैं।'; }

        // सुप्त भाव यहाँ दर्जा नहीं गिराता। नापने पर ये तीनों भाव 100 में से
        // लगभग 60 कुंडलियों में सुप्त निकलते हैं — यानी वह हालत आम है, दोष नहीं।
        // उसे दोष गिनने पर यह धुरी हर जोड़े पर पीली हो जाती थी और कुछ कहती ही नहीं थी।
        $sumBad = count($a['bad']) + count($b['bad']);
        if ($sumBad >= 3) {
            $tone = 'neg';
            $reason = 'गृहस्थी के तीन या अधिक भाव (दोनों कुंडलियाँ मिलाकर) दबाव में हैं — रोज़ के जीवन '
                . '(कुटुम्ब, घर, एकांत) में टकराव की सम्भावना; भाव-उपाय आवश्यक।';
        } elseif ($sumBad === 2) {
            $tone = 'mix';
            $reason = 'गृहस्थी के दो भाव दबे हुए हैं — यह मिलान रोकने का कारण नहीं, '
                . 'पर उन्हीं भावों का उपाय कर लेना चाहिए।';
        } elseif ($sumBad === 1) {
            // छह ख़ानों (तीन भाव × दो कुंडलियाँ) में से एक का दबा होना आम बात है।
            // उसे नाम लेकर छापा जाता है, पर उस पर पूरा मिलान पीला नहीं किया जाता।
            $tone = 'pos';
            $reason = 'गृहस्थी का सिर्फ़ एक भाव दबा है (ऊपर नाम सहित) — छह में से एक, यानी साधारण '
                . 'उतार-चढ़ाव। उसी भाव का उपाय कर लें; मिलान इस बिंदु पर रुकता नहीं।';
        } elseif ($a['dull'] !== [] || $b['dull'] !== []) {
            $tone = 'pos';
            $reason = 'गृहस्थी का कोई भाव बिगड़ा नहीं। कुछ भाव सुप्त ज़रूर हैं — वह रुका हुआ फल है, '
                . 'दोष नहीं; चाहें तो उन्हें जगाने का उपाय कर लें।';
        } else {
            $tone = 'pos';
            $reason = 'कुटुम्ब, घर-सुख व शयन-कक्ष — तीनों भाव दोनों तरफ़ ठीक हैं।';
        }

        return [
            'key'    => 'ghar',
            'label'  => 'गृहस्थी के भाव — 2 · 4 · 12',
            'tone'   => $tone,
            'reason' => $reason,
            'detail' => $detail,
            'remedies' => [],
        ];
    }

    // ───────────────────────────────────────────────────── 5. पितृ-ऋण

    /**
     * ऋण व्यक्ति का अपना कर्म है, जोड़े का नहीं। इसलिए यहाँ दो बातें अलग रखी
     * गई हैं: कौन-सा ऋण विवाह को छूता है (स्त्री, संतान, कुटुम्ब, ससुराल), और
     * कौन-सा नहीं। न छूने वाला ऋण नीचे सूचना के तौर पर छपता है, पर मिलान का
     * दर्जा नहीं गिराता — वरना हर दूसरा जोड़ा किसी और के धन-ऋण पर "मिश्र" हो
     * जाता था।
     *
     * @return array<string,mixed>
     */
    private static function rinAxis(array $A, array $B): array
    {
        $presA = self::presentRin($A['lk']['shrap'] ?? []);
        $presB = self::presentRin($B['lk']['shrap'] ?? []);

        $shared = array_intersect_key($presA, $presB);
        $onlyA  = array_diff_key($presA, $presB);
        $onlyB  = array_diff_key($presB, $presA);

        $detail = []; $remedies = [];
        $sharedVivah = false; $oneVivah = false;

        foreach ($shared as $name => $row) {
            $v = self::rinTouchesVivah($row);
            $sharedVivah = $sharedVivah || $v;
            $detail[] = ($v ? '⚠ ' : '• ') . 'साझा ऋण — ' . $name . ' (दोनों की कुंडली में)'
                . ($v ? ' · यह ऋण विवाह-पक्ष को छूता है' : ' · इसका असर विवाह पर सीधा नहीं')
                . ' : ' . self::matchStr($row);
            if ($v && trim((string) ($row['upay'] ?? '')) !== '') {
                $remedies[] = ['who' => 'दोनों', 'hi' => $name, 'text' => (string) $row['upay'],
                    'direction' => 'चुकाना', 'why' => 'यही ऋण दोनों की कुंडली में है'];
            }
        }
        foreach ([[$A['name'], $onlyA], [$B['name'], $onlyB]] as [$who, $list]) {
            foreach ($list as $name => $row) {
                $v = self::rinTouchesVivah($row);
                $oneVivah = $oneVivah || $v;
                $detail[] = ($v ? '⚠ ' : '• ') . $who . ' में ' . $name
                    . ($v ? ' · विवाह-पक्ष को छूता है' : ' · व्यक्तिगत — मिलान पर सीधा असर नहीं')
                    . ' : ' . self::matchStr($row);
            }
        }

        if ($sharedVivah) {
            $tone = 'neg';
            $reason = 'एक ही पितृ-ऋण दोनों की कुंडली में है और वह विवाह-पक्ष को छूता है — विवाह के बाद वही '
                . 'बात दोनों तरफ़ से दोहराती है। इसका उपाय दोनों परिवारों को मिलकर करना होता है।';
        } elseif ($oneVivah) {
            $tone = 'mix';
            $reason = 'किसी एक की कुंडली में विवाह-पक्ष को छूने वाला ऋण है — वही व्यक्ति अपना उपाय कर ले तो '
                . 'मिलान अनुकूल रहता है।';
        } elseif ($shared !== [] || $onlyA !== [] || $onlyB !== []) {
            $tone = 'pos';
            $reason = 'ऋण मिले हैं, पर उनका विषय स्त्री-संतान-कुटुम्ब नहीं — वे अपनी-अपनी कुंडली की बात हैं, '
                . 'मिलान की नहीं। उपाय अपने-अपने पन्ने पर हैं।';
        } else {
            $tone = 'pos';
            $reason = 'किसी कुंडली में सक्रिय पितृ-ऋण नहीं मिला।';
            $detail[] = 'दोनों कुंडलियों में कोई सक्रिय पितृ-ऋण नहीं।';
        }

        return [
            'key'    => 'rin',
            'label'  => 'पितृ-ऋण — साझा व एकपक्षीय',
            'tone'   => $tone,
            'reason' => $reason,
            'detail' => $detail,
            'remedies' => $remedies,
        ];
    }

    /** कुंडली में सचमुच मौजूद ऋण, नाम से। */
    private static function presentRin(array $shrap): array
    {
        $out = [];
        foreach ($shrap as $row) {
            if (!empty($row['present'])) { $out[(string) ($row['rin'] ?? '')] = $row; }
        }
        unset($out['']);
        return $out;
    }

    /** यह ऋण विवाह-पक्ष को छूता है या नहीं — पुस्तक के अपने फल-वाक्य से। */
    private static function rinTouchesVivah(array $row): bool
    {
        static $keys = ['स्त्री', 'पत्नी', 'पति', 'विवाह', 'दाम्पत्य', 'संतान', 'सन्तान', 'वंश',
            'कुटुम्ब', 'ससुराल', 'गृहस्थ', 'औलाद'];
        // ऋण का अपना नाम भी पढ़ा जाता है — वरना "स्त्री-ऋण" जैसा ऋण, जिसका विषय
        // नाम में ही खड़ा है, "व्यक्तिगत" गिना जाकर मिलान से बाहर रह जाता था।
        $txt = (string) ($row['rin'] ?? '') . ' ' . (string) ($row['ashubh_phal'] ?? '')
            . ' ' . (string) ($row['pehchan'] ?? '');
        foreach ($keys as $k) { if (mb_strpos($txt, $k) !== false) { return true; } }
        return false;
    }

    private static function matchStr(array $row): string
    {
        $m = $row['matched'] ?? [];
        return is_array($m) && $m !== [] ? implode(', ', $m) : (string) ($row['pehchan'] ?? '');
    }

    // ───────────────────────────────────────────────────── ग्रह-तालिका

    /**
     * नौ ग्रहों की आमने-सामने तालिका — जानकारी के लिए, फ़ैसले के लिए नहीं।
     * इसीलिए इसका कोई अपना दर्जा नहीं है: पहले यह "धुरी" बनकर 96% जोड़ों पर
     * "मिश्र" छापती थी, यानी कुछ कहती ही नहीं थी।
     *
     * @return list<array<string,mixed>>
     */
    private static function pairs(array $A, array $B): array
    {
        $pairs = [];
        foreach (self::PLANETS as $en) {
            $a = $A['p'][$en] ?? null; $b = $B['p'][$en] ?? null;
            if ($a === null || $b === null) { continue; }
            $hi = (string) ($a['hi'] ?? LalKitabData::planetHi($en));
            $ua = self::unfit($a); $ub = self::unfit($b);
            // क्षेत्र ग्रह के अपने पाठ से — "इसके कारक क्षेत्र में" जैसा ख़ाली
            // वाक्य नहीं, बल्कि वही क्षेत्र जिनके नाम इंजन पहले ही निकाल चुका है।
            $areas = implode(', ', array_slice((array) ($a['areas_hi'] ?? []), 0, 2));
            $vivah = self::VIVAH_KARAK[$en] ?? '';
            $sa = self::state($a); $sb = self::state($b);
            if ($sa === 'निष्क्रिय' && $sb === 'निष्क्रिय') {
                // दो सोए ग्रह "दोष" नहीं हैं। पूरी रिपोर्ट यही सिखाती है कि
                // निष्क्रिय बुरा फल नहीं, रुका हुआ फल है — तालिका में उसे लाल
                // ठप्पा देना उसी बात को यहीं झुठला देता।
                $tone = 'mix';
                $note = 'दोनों तरफ़ सोया — ' . ($areas !== '' ? $areas : 'इसके क्षेत्र')
                    . ' में बात रुकी रहेगी, बिगड़ी नहीं। उपाय शांति नहीं, जगाना है।';
            } elseif ($sa === 'अशुभ' && $sb === 'अशुभ') {
                $tone = 'neg';
                $note = 'दोनों तरफ़ अशुभ — ' . ($areas !== '' ? $areas : 'इसके क्षेत्र') . ' में सहारा किसी तरफ़ से नहीं।';
            } elseif ($ua && $ub) {
                $tone = 'mix';
                $note = 'दोनों तरफ़ कमज़ोर — एक ओर अशुभ, दूसरी ओर सोया; '
                    . ($areas !== '' ? $areas : 'इसके क्षेत्र') . ' में सहारा कम रहेगा।';
            } elseif ($ua || $ub) {
                $tone = 'mix';
                $note = ($ua ? $B['name'] : $A['name']) . ' की तरफ़ से सहारा — '
                    . ($areas !== '' ? $areas : 'इसके क्षेत्र') . ' उसी पक्ष से सँभलेंगे।';
            } else {
                $tone = 'pos';
                $note = 'दोनों तरफ़ ठीक — ' . ($areas !== '' ? $areas : 'इसके क्षेत्र') . ' में अनुकूलता।';
            }
            if ($vivah !== '') { $note = '(विवाह-कारक: ' . $vivah . ') ' . $note; }
            $pairs[] = [
                'planet' => $en, 'hi' => $hi, 'vivah_karak' => $vivah !== '',
                'a_house' => (int) ($a['house'] ?? 0), 'a_status' => self::stateLabel($a), 'a_bad' => $ua,
                'b_house' => (int) ($b['house'] ?? 0), 'b_status' => self::stateLabel($b), 'b_bad' => $ub,
                'tone' => $tone, 'note' => $note,
            ];
        }
        return $pairs;
    }

    // ───────────────────────────────────────────────────── उपाय

    /**
     * सप्तम/भाव का उपाय — भाव-पाठ से, दिशा सहित। सुप्त भाव पर "शांति" कभी नहीं।
     *
     * @return list<array<string,mixed>>
     */
    private static function houseRemedies(array $A, array $B, int $hn, bool $needA, bool $needB): array
    {
        $out = [];
        foreach ([[$A, $needA], [$B, $needB]] as [$S, $need]) {
            if (!$need) { continue; }
            $h = $S['h'][$hn] ?? [];
            $rem = (array) ($h['remedies'] ?? []);
            if ($rem === []) { continue; }
            $dull = in_array((string) ($h['verdict'] ?? ''), ['सुप्त'], true);
            $out[] = [
                'who' => $S['name'], 'hi' => LalKitabData::houseOrdinalHi($hn) . ' भाव',
                'text' => (string) $rem[0],
                'direction' => $dull ? 'जगाना' : 'बल-वृद्धि',
                'why' => $dull ? 'भाव सुप्त है — इसे जगाना है, शांत नहीं करना' : 'दाम्पत्य का घर दबाव में है',
            ];
        }
        // भाव का उपाय दोनों के लिए एक ही निकले तो वह एक ही पंक्ति है, दो नहीं —
        // वरना पाँच में से दो जगहें एक ही वाक्य दो बार छापने में चली जाती हैं।
        if (count($out) === 2 && $out[0]['text'] === $out[1]['text'] && $out[0]['direction'] === $out[1]['direction']) {
            $out = [['who' => 'दोनों'] + $out[0]];
        }
        return $out;
    }

    /**
     * अंतिम उपाय-सूची।
     *
     * ग्रह-उपाय अपनी अलग खोज से नहीं बनते — वे {@see LalKitabProcess} की उसी
     * कतार से आते हैं जो हर कुंडली के अपने पन्ने पर चलती है। इससे तीन बातें
     * अपने-आप ठीक रहती हैं: दिशा (सोए ग्रह को शांत नहीं किया जाता), वर्जित काम
     * उपाय बनकर बाहर नहीं जाते, और गिनती एक-दो पर रुकती है। पहले यहाँ सीधे
     * `remedies[0]` उठाया जाता था — इसीलिए एक-एक मिलान में सात-सात उपाय निकलते
     * थे, और उनमें सोए ग्रह को शांत करने वाले उपाय भी शामिल थे।
     *
     * @return list<array<string,mixed>>
     */
    private static function remedies(array $axes, array $A, array $B): array
    {
        $out = [];
        // 1) जो मिलान की धुरियों ने ख़ुद माँगे (भाव/मंगल/ऋण)
        foreach ($axes as $ax) {
            foreach ((array) ($ax['remedies'] ?? []) as $rm) { $out[] = $rm; }
        }
        // 2) हर पक्ष का अपना पहला ग्रह-उपाय — केवल तभी, जब उस पक्ष पर कोई
        //    धुरी लाल/पीली हो। शुभ मिलान पर उपाय थोपना ग्राहक को डराना है।
        $needy = false;
        foreach ($axes as $ax) { if ($ax['tone'] !== 'pos') { $needy = true; } }
        if ($needy) {
            foreach ([$A, $B] as $S) {
                foreach (array_slice((array) ($S['proc']['upaay']['issued'] ?? []), 0, 1) as $iss) {
                    $txt = (string) (($iss['upay'][0] ?? ''));
                    if (trim($txt) === '') { continue; }
                    $out[] = [
                        // नाम उसी ग्रह का जिसका उपाय-पाठ है। कतार निशाना दूसरे ग्रह
                        // पर मोड़ देती है (गुरु को शनि ने दबाया हो तो उपाय शनि का),
                        // पर पाठ अब भी दुखी ग्रह का ही होता है — इसलिए शीर्षक में
                        // निशाने का नाम लिख देना "शनि: गुड़ बहाएँ" जैसी बेमेल पंक्ति
                        // बनाता था। निशाना नीचे "क्यों" में नाम लेकर आता है।
                        'who' => $S['name'],
                        'hi'  => (string) ($iss['planet'] ?? ''),
                        'text' => $txt,
                        'direction' => (string) ($iss['direction'] ?? ''),
                        'why' => trim((string) ($iss['target_note'] ?? '')) !== ''
                            ? (string) $iss['target_note']
                            : 'इस कुंडली का पहला उपाय — मिलान से पहले यही करना है',
                        'duration' => (string) ($iss['duration'] ?? ''),
                        'stop_when' => (string) ($iss['stop_when'] ?? ''),
                    ];
                }
            }
        }
        // दोहराव हटाओ, और गिनती पाँच पर रोको — छूटा हुआ उपाय शुरू न किए गए
        // उपाय से बुरा है।
        $seen = []; $final = [];
        foreach ($out as $rm) {
            $k = ($rm['who'] ?? '') . '|' . mb_substr((string) ($rm['text'] ?? ''), 0, 40);
            if (isset($seen[$k])) { continue; }
            $seen[$k] = true;
            $final[] = $rm;
            if (count($final) >= 5) { break; }
        }
        return $final;
    }

    // ───────────────────────────────────────────────────── निष्कर्ष व सीमा

    private static function verdict(string $tier, array $axes, int $clear, int $total): string
    {
        $bad = [];
        foreach ($axes as $ax) { if ($ax['tone'] === 'neg') { $bad[] = (string) $ax['label']; } }

        $head = $total . ' में से ' . $clear . ' बिंदु निर्दोष।';
        if ($tier === 'shubh') {
            return $head . ' पाँचों दृष्टि से यह मिलान अनुकूल है — किसी बिंदु पर रोक नहीं।';
        }
        if ($tier === 'shubh_upay') {
            return $head . ' किसी बिंदु पर दोष नहीं है, पर दो या अधिक बिंदुओं पर ध्यान चाहिए — '
                . 'नीचे दिए उपाय कर लेने पर मिलान शुभ है।';
        }
        if ($tier === 'savdhan') {
            return $head . ' दोष एक ही बिंदु पर है — ' . implode(', ', $bad)
                . '। वही बिंदु सुधारना है, पूरा मिलान नहीं।';
        }
        return $head . ' दोष एक से अधिक बिंदुओं पर है — ' . implode(', ', $bad)
            . '। विवाह से पूर्व इन्हीं का उपाय आवश्यक है; उपाय के बाद पुनर्विचार किया जा सकता है।';
    }

    /**
     * सीमा — जो इस पन्ने को नहीं कहना चाहिए। दूसरी पंक्ति पुस्तक का अपना
     * वाक्य है और वह इसी विषय पर है, इसलिए वह यहाँ से हटती नहीं।
     *
     * @return list<string>
     */
    private static function caution(): array
    {
        return [
            'लाल किताब में 36 गुण का अष्टकूट नहीं है — यह मिलान पाँच बिंदुओं का है, अंकों का नहीं। '
                . 'ऊपर की गिनती नाप नहीं, बिंदुओं की गिनती है; इसे गुण-मिलान के अंकों से जोड़कर न पढ़ें।',
            'पुस्तक की चेतावनी: "लग्ने व्यये च पाताले…" वाला श्लोक असंगत है — मंगली दोष लड़का-लड़की '
                . 'दोनों के लिए समान फलदायी है, केवल कन्या को दोषी ठहराना पक्षपात है। यह पन्ना दोनों '
                . 'कुंडलियों को एक ही कसौटी पर देखता है।',
            'जीवनसाथी का विचार यहाँ सप्तम भाव से होता है। "गुरु = पति-कारक" वैदिक परिपाटी है; इस '
                . 'पुस्तक का कारक-चक्र गुरु को पिता/बाबा कहता है, इसलिए वह नियम यहाँ नहीं लगाया गया।',
            'कुंडली विवाह बाँधती या तोड़ती नहीं — यह मिलान दिशा बताता है, निर्णय नहीं।',
        ];
    }
}
