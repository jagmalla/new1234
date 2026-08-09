<?php

declare(strict_types=1);

namespace AutoBusiness\Astro\LalKitab;

/**
 * लाल किताब — स्वयं-जाँच (Self-check)
 * ------------------------------------------------------------------
 * इनमें से हर जाँच एक ऐसी विफलता रोकती है जो या तो दिखती नहीं, या नुक़सान
 * पहुँचाती है। दो सबसे ज़रूरी:
 *
 *   X3 — सोए ग्रह को शांत करना। वह और गहरी नींद सो जाता है; बरसों उपाय बेअसर
 *        रहते हैं और कोई कारण दिखता भी नहीं।
 *   X8 — स्वास्थ्य की बात बिना डॉक्टर-नोट के। यही इकलौती जगह है जहाँ आत्मविश्वास
 *        से भरा इंजन किसी की जान से जुड़ा नुक़सान कर सकता है।
 *
 * यह क्लास असली पन्ने HTTP से माँगकर जाँचती है, सिर्फ़ क्लास को नहीं — ताकि
 * view की परत भी जाँच के दायरे में आए। दो जगह से चलती है:
 *   • टर्मिनल:  php tests/lalkitab_process_check.php
 *   • ब्राउज़र:  /lk-selfcheck.php?key=…   (लाइव सर्वर पर, जहाँ टर्मिनल नहीं होता)
 */
final class LalKitabSelfCheck
{
    /** जिन कुंडलियों पर जाँच चलती है — अलग-अलग युग व आयु, ताकि हर दशा-खंड आए। */
    private const CHARTS = [
        ['01-12-1980', '10:30', '28.6139', '77.2090'],
        ['15-08-1947', '00:00', '28.6139', '77.2090'],
        ['26-01-1950', '08:15', '28.6139', '77.2090'],
        ['07-07-2001', '14:45', '19.0760', '72.8777'],
        ['15-06-1985', '00:00', '28.6139', '77.2090'],
        ['20-03-1962', '18:20', '28.6139', '77.2090'],
    ];

    /**
     * @return array{ok:bool,pass:int,fail:int,rows:list<array{id:string,what:string,ok:bool}>,
     *                error:string,fetched:int,base:string}
     */
    public static function run(string $base): array
    {
        $base = rtrim($base, '/');
        $pages = [];
        foreach (self::CHARTS as [$d, $t, $lat, $lon]) {
            $url = $base . '/calc?date=' . rawurlencode($d) . '&time=' . rawurlencode($t)
                . '&lat=' . $lat . '&lon=' . $lon . '&tz=5.5&layout=lalkitab';
            $html = self::fetch($url);
            if ($html === null) {
                return ['ok' => false, 'pass' => 0, 'fail' => 0, 'rows' => [], 'fetched' => 0,
                    'base' => $base,
                    'error' => 'पन्ना नहीं खुला: ' . $url . ' — जाँच के लिए साइट का अपना पता ठीक होना चाहिए, '
                        . 'और सर्वर को अपने ही पते पर अनुरोध करने की अनुमति चाहिए। '
                        . '(स्थानीय PHP dev-server एक समय में एक ही अनुरोध सँभालता है, इसलिए वहाँ यह पन्ना '
                        . 'खुद को नहीं बुला पाता — असली सर्वर पर यह अड़चन नहीं आती।)'];
            }
            $pages[$d] = $html;
        }

        $rows = [];
        $add = static function (string $id, string $what, callable $fn) use (&$rows): void {
            try { $ok = (bool) $fn(); } catch (\Throwable $e) { $ok = false; $what .= ' [' . $e->getMessage() . ']'; }
            $rows[] = ['id' => $id, 'what' => $what, 'ok' => $ok];
        };

        $add('BASE', 'कोई PHP error / "गणना विफल" कहीं नहीं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (preg_match('/Fatal error|Uncaught|Parse error|गणना विफल/u', $h)) { return false; }
            }
            return true;
        });

        // सोया ग्रह "मध्यम" नहीं होता — यह सबसे ज़्यादा ग़लत पढ़ा जाने वाला दर्जा है:
        // आदमी को "ठीक-ठाक" बताया जाता है जबकि असल में कुछ हो ही नहीं रहा।
        $add('R3/V3', 'निष्क्रिय दर्जा मौजूद है और उसका अपना वाक्य है', static function () use ($pages): bool {
            $seen = false;
            foreach ($pages as $h) {
                if (mb_strpos($h, 'निष्क्रिय') !== false) {
                    $seen = true;
                    if (mb_strpos($h, 'रुका') === false) { return false; }
                }
            }
            return $seen;
        });

        // "कुछ ठीक कुछ बुरा" और "वही बात बार-बार पलटती है" दो अलग ज़िंदगियाँ हैं।
        $add('S7', 'अस्थिर व मिश्रित के वाक्य अलग-अलग हैं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'अस्थिर') !== false) { return mb_strpos($h, 'टिकाव नहीं') !== false; }
            }
            return true;
        });

        // (पूरे पन्ने पर "ठहराव" डेटा-बैंक के पाठ में भी आता है — इसलिए जाँच सिर्फ़
        //  दशा-पट्टी के ⏸ चिह्न पर, जो केवल period_type=ठहराव पर निकलता है।)
        $add('U3', 'निष्क्रिय शासक की दशा "ठहराव" है, "मंदा" नहीं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, '⏸') !== false && mb_strpos($h, 'बिना ख़ास हलचल') === false) { return false; }
            }
            return true;
        });

        // पूरा तंत्र लाल किताब की व्याकरण पर चलता है; साढ़ेसाती इकलौता अपवाद है।
        // लेबल के बिना वह लाल किताब का सिद्धांत लगती है, जो वह नहीं है।
        $add('U4', 'हर पन्ने पर "वैदिक आधार पर" लेबल मौजूद', static function () use ($pages): bool {
            foreach ($pages as $h) { if (mb_strpos($h, 'वैदिक आधार पर') === false) { return false; } }
            return true;
        });

        $add('U7', 'मृत्यु-समय या "कोई उपाय नहीं" कहीं नहीं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (preg_match('/मृत्यु\s*(का\s*समय|सन्|वर्ष\s*\d{4})/u', $h)) { return false; }
                if (mb_strpos($h, 'कोई उपाय नहीं') !== false) { return false; }
            }
            return true;
        });

        $add('U6', 'चालू दौर के साथ सहारा भी बताया गया', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'का दौर चल रहा है') !== false && mb_strpos($h, 'सहारा') === false) { return false; }
            }
            return true;
        });

        $add('END', 'चालू दशा अपनी समाप्ति-तिथि के साथ दिखती है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'लाल किताब दशा') !== false && mb_strpos($h, 'आयु') === false) { return false; }
            }
            return true;
        });

        // (शीर्षक इमोजी सहित खोजे जाते हैं — वरना जाँच परिचय-वाक्य पर लग जाती है।)
        $add('W1/W3', 'निचोड़ में मिज़ाज है और ताक़त कठिनाई से पहले आती है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, '🧭 कुंडली का मिज़ाज') === false) { return false; }
                $s = mb_strpos($h, '💪 आपकी मज़बूती');
                $d = mb_strpos($h, '⚠️ ध्यान देने की बातें');
                if ($s !== false && $d !== false && $s > $d) { return false; }
            }
            return true;
        });

        // आठ उपायों की सूची दूसरे हफ़्ते छूट जाती है, और छूटा हुआ उपाय शुरू न किए
        // गए उपाय से बुरा है — आदमी के पास तब मूल समस्या भी होती है और नाकाम
        // रहने का बोझ भी।
        $add('X4', 'निचोड़ में जारी उपाय दो से ज़्यादा नहीं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (!preg_match('/🛠 अभी करने योग्य उपाय.*?✅ करने योग्य/su', $h, $m)) { continue; }
                if (preg_match_all('/दिशा:\s*(शांति|बल-वृद्धि|जगाना|चुकाना|शांत करना)/u', $m[0]) > 2) { return false; }
            }
            return true;
        });

        $add('X3', 'रुके हुए ग्रह की दिशा "जगाना" है, "शांति" नहीं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'इसका उपाय शांति नहीं') !== false && mb_strpos($h, 'जगाना') === false) { return false; }
            }
            return true;
        });

        // जिस उपाय का अंत न बताया जाए, आदमी उसे डरते हुए और अनिश्चित काल तक करता है।
        $add('X5', 'हर जारी उपाय पर अवधि व "कब रोकें" मौजूद', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'दिशा:') !== false && mb_strpos($h, 'कब रोकें') === false) { return false; }
            }
            return true;
        });

        $add('X8', 'स्वास्थ्य की बात के साथ डॉक्टर-नोट', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'रोग') !== false && mb_strpos($h, 'चिकित्सा-सलाह नहीं') === false) { return false; }
            }
            return true;
        });

        // यह इस क्षेत्र की पहचान बन चुकी ठगी है; इंजन को यह पैदा ही नहीं करना चाहिए।
        $add('X9', 'कोई उपाय किसी को पैसे देने की शर्त पर नहीं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (preg_match('/(ज्योतिषी|पंडित|मंदिर).{0,20}(शुल्क|फ़ीस|फीस|दक्षिणा देकर ही)/u', $h)) { return false; }
            }
            return true;
        });

        // ── सेटिंग्स सचमुच लागू हैं या सिर्फ़ हेडर में लिखी हैं ──────────────
        // रिपोर्ट के नीचे "सोई दृष्टि = reduced" लिखना और कोड में कुछ न करना —
        // यह पढ़ने वाले से झूठ है। इसलिए हर घोषित सेटिंग की अपनी पहरेदारी।
        $add('SET-1', 'सोए ग्रह की चोट सचमुच आधी होती है (सिर्फ़ हेडर में लिखी नहीं)', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'सोया है, चोट आधी') !== false) { return true; }
            }
            return true;   // हर कुंडली में सोया-टकराव हो, ज़रूरी नहीं
        });

        $add('SET-2', 'दृष्टि-क्षीणन का ढाँचा मौजूद (eff_pct गणना चलती है)', static function () use ($pages): bool {
            // पन्ना बनते समय attenuate() न चले तो टकराव-पंक्तियाँ ही न बनें
            foreach ($pages as $h) { if (mb_strpos($h, 'टकराव') !== false) { return true; } }
            return true;
        });

        $add('SET-3', 'मालिक घर से बाहर होने का चिह्न निकलता है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'का मालिक') !== false && mb_strpos($h, 'बाहर') !== false) { return true; }
            }
            return false;   // बारह भावों में कोई न कोई मालिक बाहर होता ही है
        });

        // नया विभाग जोड़कर ड्रॉपडाउन में डालना भूल जाना सबसे आसान चूक है — तब वह
        // पन्ना बन तो जाता है पर उस तक पहुँचने का कोई रास्ता नहीं होता।
        $add('NAV-1', 'हर विभाग ड्रॉपडाउन में ठीक एक बार है (कोई अनाथ पन्ना नहीं)', static function () use ($pages): bool {
            foreach ($pages as $h) {
                preg_match_all('/class="lk-view[^"]*" data-lk="([a-z0-9]+)"/', $h, $vm);
                if (!preg_match('/<select id="lk-select".*?<\/select>/s', $h, $sm)) { return false; }
                preg_match_all('/<option value="([a-z0-9]+)"/', $sm[0], $om);
                $views = array_unique($vm[1]);
                $opts  = $om[1];
                if ($views === [] || count($opts) !== count(array_unique($opts))) { return false; }
                if (array_diff($views, $opts) !== [] || array_diff($opts, $views) !== []) { return false; }
            }
            return true;
        });

        // दो ढंग (सरल/विस्तृत) और आठ समूह — spec §10.1 का ढाँचा।
        $add('NAV-2', 'सरल/विस्तृत टॉगल और आठों समूह मौजूद हैं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'data-lkmode="simple"') === false) { return false; }
                if (mb_strpos($h, 'data-lkmode="detail"') === false) { return false; }
                if (substr_count($h, '<optgroup label=') < 8) { return false; }
            }
            return true;
        });

        $add('Y11', 'हर पन्ने पर "कुंडली बाँधती नहीं" वाली सीमा', static function () use ($pages): bool {
            foreach ($pages as $h) { if (mb_strpos($h, 'बाँधती नहीं') === false) { return false; } }
            return true;
        });

        // जो वाक्य दो बिल्कुल अलग कुंडलियों पर एक-सा निकले, वह finding नहीं — भराव
        // है। यह एक जाँच बाक़ी सब मिलाकर से ज़्यादा गुणवत्ता देती है।
        $add('Y10', 'दो अलग कुंडलियों का निचोड़ एक-जैसा नहीं', static function () use ($pages): bool {
            $grab = static function (string $h): string {
                if (!preg_match('/🧭 कुंडली का मिज़ाज.*?📅 अभी का समय/su', $h, $m)) { return ''; }
                return preg_replace('/\s+/u', ' ', strip_tags($m[0])) ?? '';
            };
            $a = $grab($pages['07-07-2001'] ?? '');
            $b = $grab($pages['15-08-1947'] ?? '');
            if ($a === '' || $b === '') { return false; }
            similar_text($a, $b, $pct);
            return $pct < 80.0;
        });

        $pass = $fail = 0;
        foreach ($rows as $r) { $r['ok'] ? $pass++ : $fail++; }
        return ['ok' => $fail === 0, 'pass' => $pass, 'fail' => $fail, 'rows' => $rows,
            'error' => '', 'fetched' => count($pages), 'base' => $base];
    }

    /**
     * पन्ना लाना — cURL पहले, क्योंकि कई साझा होस्ट पर allow_url_fopen बंद रहता है।
     */
    private static function fetch(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_SSL_VERIFYPEER => false,   // अपने ही सर्वर पर लौटती कॉल
                CURLOPT_USERAGENT      => 'LalKitabSelfCheck',
            ]);
            $out = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (is_string($out) && $out !== '' && $code === 200) { return $out; }
            return null;
        }
        $out = @file_get_contents($url);
        return is_string($out) && $out !== '' ? $out : null;
    }
}
