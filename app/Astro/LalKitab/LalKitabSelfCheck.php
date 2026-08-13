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
        // इसकी चालू दशा का शासक इस समय निष्क्रिय है — U3 का "ठहराव" वाला आधा
        // हिस्सा इसी कुंडली से परखा जाता है। आयु बढ़ने पर शासक बदल जाएगा; तब
        // U3-COV लाल होकर बता देगा कि नई तारीख़ चुननी है (देखें वहीं की टिप्पणी)।
        ['03-04-2003', '06:40', '28.6139', '77.2090'],
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

        // ── मिलान के पन्ने ──
        // तीन अलग जोड़े, ताकि यह भी परखा जा सके कि मिलान भेद करता है या सब पर
        // एक ही दर्जा छापता है। (पुराना मिलान 80 में से एक भी जोड़े को "शुभ"
        // नहीं कह पाता था — और कोई जाँच उसे पकड़ नहीं रही थी, क्योंकि हर जाँच
        // एक ही पन्ना देखती थी।)
        $couples = [
            'A' => ['01-12-1980', '12:31', '15-08-1985', '09:20'],
            'B' => ['07-07-2001', '14:45', '26-01-1998', '05:10'],
            'C' => ['20-03-1962', '18:20', '11-11-1966', '21:40'],
        ];
        $milan = [];
        $milanRaw = '';
        foreach ($couples as $ck => [$bd, $bt, $gd, $gt]) {
            $url = $base . '/milan?boy_date=' . rawurlencode($bd) . '&boy_time=' . rawurlencode($bt)
                . '&girl_date=' . rawurlencode($gd) . '&girl_time=' . rawurlencode($gt);
            $html = self::fetch($url);
            if ($html !== null) {
                $milan[$ck] = self::lkmBlock($html);
                if ($milanRaw === '') { $milanRaw = $html; }   // तिथि-खोजक का फ़ॉर्म पूरे पन्ने में है
            }
        }
        // विवाह-मुहूर्त तिथि-खोजक — पूरे एक साल की माँग पर पूरा साल जाँचा जाए
        $mdfYear = self::fetch($base . '/milan?mdf_from=' . rawurlencode(date('d-m-Y'))
            . '&mdf_to=' . rawurlencode(date('d-m-Y', strtotime('+1 year'))));

        // कन्या के लिंग वाली एक कुंडली — मिलान की कड़ी सही तरफ़ भरती है या नहीं
        $femalePage = self::fetch($base . '/calc?date=01-12-1980&time=10:30&lat=28.6139&lon=77.2090'
            . '&tz=5.5&gender=Female');

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
            // पहले यह जाँच पन्ने पर ⏸ ढूँढ़ती थी। दिक़्क़त यह थी कि जिन छह कुंडलियों
            // पर जाँच चलती है उनमें से किसी की चालू दशा का शासक अभी निष्क्रिय नहीं
            // है — यानी जाँच हरी तो रहती थी, पर जिस नियम की रखवाली करती है उसे कभी
            // छूती ही नहीं थी। अब वह नियम सीधे परखा जाता है: दशा-पट्टी शासक का दर्जा
            // ख़ुद छापती है, इसलिए दोनों तरफ़ से मिलान होता है — निष्क्रिय हो तो ठहराव
            // वाक्य होना ही चाहिए, और न हो तो होना ही नहीं चाहिए।
            $seen = 0;
            foreach ($pages as $h) {
                if (!preg_match('/<div class="lk-active">(.*?)<div class="lk-moderow">/su', $h, $m)) { continue; }
                $strip = $m[1];
                if (mb_strpos($strip, 'लाल किताब दशा') === false) { continue; }
                if (!preg_match('/<span class="lk-pill"[^>]*>([^<]+)<\/span>/u', $strip, $pm)) { return false; }
                $seen++;
                $verdict = trim($pm[1]);
                $thahrav = mb_strpos($strip, 'बिना ख़ास हलचल') !== false;
                if ($verdict === 'निष्क्रिय' && !$thahrav) { return false; }
                if ($verdict !== 'निष्क्रिय' && $thahrav) { return false; }
            }
            return $seen > 0;   // एक भी दशा-पट्टी न मिले तो जाँच बेकार है
        });

        // U3 दोनों तरफ़ से मिलान करता है, पर "निष्क्रिय → ठहराव" वाला आधा हिस्सा तभी
        // चलता है जब किसी कुंडली का चालू शासक सचमुच निष्क्रिय हो। आयु हर साल बढ़ती
        // है, इसलिए यह अपने-आप छूट सकता है — और तब U3 हरा रहकर भी कुछ नहीं परख
        // रहा होगा। यह जाँच ठीक वही चुप्पी पकड़ती है।
        $add('U3-COV', 'कोई एक कुंडली निष्क्रिय-शासक वाली दशा दिखाती है (U3 खाली न चले)', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (!preg_match('/<div class="lk-active">(.*?)<div class="lk-moderow">/su', $h, $m)) { continue; }
                if (mb_strpos($m[1], 'लाल किताब दशा') === false) { continue; }
                if (preg_match('/<span class="lk-pill"[^>]*>निष्क्रिय<\/span>/u', $m[1])) { return true; }
            }
            return false;   // CHARTS में एक नई जन्म-तारीख़ चुनें जिसका चालू शासक निष्क्रिय हो
        });

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
        // ये दोनों जाँचें पहले `return true` पर ख़त्म होती थीं — यानी कभी लाल हो
        // ही नहीं सकती थीं। हरा रंग दिखाकर कुछ न परखना, न परखने से बुरा है: भरोसा
        // वहाँ बनता है जहाँ बचाव है ही नहीं। अब दोनों सचमुच परखती हैं।

        // SET-1 — सोए ग्रह की मार। सेटिंग बदलकर वही कुंडली दोबारा माँगी जाती है:
        // "पूरी" पर मार पूरी पड़नी चाहिए, "बिल्कुल नहीं" पर पहुँचनी ही नहीं चाहिए।
        // दोनों का नतीजा एक-सा निकले तो सेटिंग सजावट है, नियम नहीं।
        $add('SET-1', 'सोई दृष्टि की सेटिंग सचमुच फल बदलती है (सिर्फ़ हेडर में लिखी नहीं)',
            static function () use ($base): bool {
                $strip = static function (?string $h): string {
                    if ($h === null) { return ''; }
                    $i = strpos($h, 'id="sec-lalkitab"');
                    if ($i === false) { return ''; }
                    $j = strpos($h, 'id="sec-today"');
                    $seg = substr($h, $i, $j !== false && $j > $i ? $j - $i : null);
                    // सेटिंग-पट्टी व नियम-फुटर ख़ुद सेटिंग छापते हैं, और vid हर बार
                    // बदलता है — इन्हें हटाए बिना हर बदलाव "फल बदला" दिखेगा।
                    $seg = (string) preg_replace('/<details id="lk-niyam".*?<\/script>/su', '', $seg);
                    $seg = (string) preg_replace('/<div data-lk-protectors=.*?<\/div>/su', '', $seg);
                    $seg = (string) preg_replace('/vid = "[0-9a-f]+"/', '', $seg);
                    return (string) preg_replace('/\s+/u', ' ', strip_tags($seg));
                };
                [$d, $t, $la, $lo] = self::CHARTS[0];
                $u = $base . '/calc?date=' . rawurlencode($d) . '&time=' . rawurlencode($t)
                    . '&lat=' . $la . '&lon=' . $lo . '&tz=5.5&layout=lalkitab';
                $full = $strip(self::fetch($u . '&soya_drishti=full'));
                $none = $strip(self::fetch($u . '&soya_drishti=none'));
                if ($full === '' || $none === '') { return false; }
                return $full !== $none;
            });

        // SET-2 — जो सेटिंग चली, वही छपे। दोनों अलग हो जाएँ तो रिपोर्ट का सिरहाना
        // झूठ बोलता है, और कोई कभी बता ही नहीं सकेगा कि फल किस मत पर बना।
        $add('SET-2', 'रिपोर्ट में वही नियम-सेटिंग छपती है जो सचमुच चली',
            static function () use ($base): bool {
                [$d, $t, $la, $lo] = self::CHARTS[0];
                $u = $base . '/calc?date=' . rawurlencode($d) . '&time=' . rawurlencode($t)
                    . '&lat=' . $la . '&lon=' . $lo . '&tz=5.5&layout=lalkitab'
                    . '&soya_drishti=none&mrit_avastha=drop&rin_severity=binary';
                $h = self::fetch($u);
                if ($h === null || !preg_match('/data-lk-niyam="([^"]*)"/u', $h, $m)) { return false; }
                $got = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                foreach (['soya_drishti=none', 'mrit_avastha=drop', 'rin_severity=binary'] as $need) {
                    if (mb_strpos($got, $need) === false) { return false; }
                }
                // और बिना माँगे कोई सेटिंग बदली हुई न हो
                return mb_strpos($got, 'seat_precedence=pakka_first') !== false;
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

        // X2 — रक्षक को छेड़ने वाला उपाय। यह इस पूरी परत की सबसे महँगी चूक है:
        // कवच हटते ही दबा हुआ ऋण सामने आता है, और वह भी उपाय शुरू करने के कुछ
        // हफ़्तों बाद — यानी आदमी सुधार की उम्मीद में उपाय करता है और बदले में
        // मुसीबत पाता है। इसलिए यह सिर्फ़ इंजन के भरोसे नहीं छोड़ा जाता।
        $add('X2', 'कोई जारी उपाय किसी रक्षक ग्रह को शांत नहीं कर रहा', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (!preg_match('/data-lk-protectors="([^"]*)"/u', $h, $pm)) { continue; }
                $prot = array_values(array_filter(explode('|', html_entity_decode($pm[1], ENT_QUOTES, 'UTF-8'))));
                if ($prot === []) { continue; }
                preg_match_all('/data-lk-upay="([^"]*)" data-dir="([^"]*)"/u', $h, $um, PREG_SET_ORDER);
                foreach ($um as $u) {
                    $target = html_entity_decode($u[1], ENT_QUOTES, 'UTF-8');
                    $dir    = html_entity_decode($u[2], ENT_QUOTES, 'UTF-8');
                    if ($dir === 'शांति' && in_array($target, $prot, true)) { return false; }
                }
            }
            return true;
        });

        // Y1 — बिना स्रोत का वाक्य ज्योतिष नहीं, भराव है। हर ग्राहक-बात किसी
        // तकनीकी नतीजे से निकली होनी चाहिए, और वह नतीजा नाम से दर्ज हो।
        $add('Y1', 'निचोड़ की हर बात अपने तकनीकी स्रोत से जुड़ी है', static function () use ($pages): bool {
            $seen = 0;
            foreach ($pages as $h) {
                preg_match_all('/data-lk-point="[^"]*" data-src="([^"]*)"/u', $h, $m);
                foreach ($m[1] as $src) {
                    $seen++;
                    if (trim(html_entity_decode($src, ENT_QUOTES, 'UTF-8')) === '') { return false; }
                }
            }
            return $seen > 0;
        });

        // Y2/Y3 — मना शब्द। इंजन के अपने गढ़े वाक्यों पर पहरा; यह पट्टी दिखते ही
        // जाँच लाल। (पुस्तक का मूल पाठ इसमें नहीं आता।)
        $add('Y2', 'इंजन के अपने वाक्यों में कोई मना-शब्द नहीं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'मना-शब्द मिला') !== false) { return false; }
            }
            return true;
        });

        // Y4 — हर कठिनाई का उपाय उसी अनुच्छेद में। जो आदमी तीन पन्ने मुसीबत पढ़कर
        // उपाय तक पहुँचता है, वे तीन पन्ने डर में बिता चुका होता है।
        $add('Y4', 'हर कठिनाई के साथ उसी जगह उसका उपाय या रुकने की वजह', static function () use ($pages): bool {
            $seen = 0;
            foreach ($pages as $h) {
                preg_match_all('/<div data-lk-point="कठिनाई".*?<\/div>\s*<\/div>/su', $h, $m);
                foreach ($m[0] as $blk) {
                    $seen++;
                    if (mb_strpos($blk, '🛠 उपाय') === false && mb_strpos($blk, '🔒') === false) { return false; }
                }
            }
            return $seen > 0;
        });

        // हर ग्रह-कार्ड पर "अभी क्या मानें" — यही वह पट्टी है जो अवस्था, ताक़त और
        // उपाय को मिलाकर एक वाक्य कहती है। इसके बिना वही पुरानी हालत लौट आती है
        // जिसमें एक ही कार्ड "मृत — कारकत्व अनुपस्थित" और "लाभ मिलेगा" दोनों कहता था।
        $add('BR-1', 'हर ग्रह-कार्ड पर उसका अपना निचोड़ मौजूद है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view" data-lk="planet">');
                $j = mb_strpos($h, '<div class="lk-view" data-lk="house">');
                if ($i === false || $j === false || $j <= $i) { return false; }
                $seg = mb_substr($h, $i, $j - $i);
                // नौ ग्रह, नौ पट्टियाँ
                if (substr_count($seg, 'अभी क्या मानें') < 9) { return false; }
            }
            return true;
        });

        // सोया-अवस्था का ब्योरा भूतकाल में तभी कहा जाए जब जागने की उम्र निकल चुकी
        // हो — और तब "कब जागेगा" भविष्य में नहीं पूछा जाना चाहिए। 45 साल के आदमी
        // को "22 वर्ष के उपरान्त जागेगा" दिखाना सादा ग़लती है।
        $add('BR-2', 'जागने की उम्र निकल चुकी हो तो वह भूतकाल में कही जाती है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'जागने की उम्र निकल चुकी') !== false) {
                    if (mb_strpos($h, 'जातक से पूछकर ही तय होगा') === false) { return false; }
                }
            }
            return true;
        });

        // ग्रह-अंतर्संबंध पन्ना कभी ख़ाली न रहे — युति/दृष्टि/टक्कर हर कुंडली में होती हैं।
        $add('BR-3', 'ग्रह-अंतर्संबंध पन्ने पर इस कुंडली के असली रिश्ते दिखते हैं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'इस कुंडली के असली रिश्ते') === false) { return false; }
            }
            return true;
        });

        // भाव पर चेतावनी हो तो "कोई उपाय आवश्यक नहीं" कभी न छपे। यह पंक्ति कार्ड के
        // अंत में आती है और पढ़ने वाला अंत की पंक्ति को फ़ैसला मानता है — इसलिए वह
        // ऊपर लिखी विश्वासघात/चोट की चेतावनी को छोड़ देता।
        $add('HB-1', 'चेतावनी वाले भाव पर "उपाय आवश्यक नहीं" नहीं छपता', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view" data-lk="house">');
                $j = mb_strpos($h, '<div class="lk-view" data-lk="karak">');
                if ($i === false || $j === false || $j <= $i) { return false; }
                $seg = mb_substr($h, $i, $j - $i);
                foreach (preg_split('/(?=<div class="lk-card[^"]*" data-bad=)/', $seg) as $card) {
                    if (mb_strpos($card, '⚠️') !== false && mb_strpos($card, 'कोई उपाय आवश्यक नहीं') !== false) {
                        return false;
                    }
                }
            }
            return true;
        });

        // हर भाव-कार्ड पर उसका अपना निचोड़।
        $add('HB-2', 'हर भाव-कार्ड पर उसका अपना निचोड़ मौजूद है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (substr_count($h, '🏠 अभी क्या मानें') < 12) { return false; }
            }
            return true;
        });

        // कारक की बात क्षमता की धुरी पर होती है, दिशा की नहीं — और सोए कारक पर
        // दिशा "जगाना" होनी चाहिए, शांति-उपाय नहीं (वही नियम जो X3 कहता है)।
        $add('KB-1', 'कारक क्षमता की धुरी पर कहा जाता है, और सोए कारक की दिशा जगाना है',
            static function () use ($pages): bool {
                foreach ($pages as $h) {
                    $i = mb_strpos($h, '<div class="lk-view" data-lk="karak">');
                    $j = mb_strpos($h, '<div class="lk-view" data-lk="yoga">');
                    if ($i === false || $j === false || $j <= $i) { return false; }
                    $seg = mb_substr($h, $i, $j - $i);
                    if (mb_strpos($seg, 'बलवान / मध्यम / दुर्बल') === false) { return false; }
                    // सोया कारक दिखे तो उसके साथ "जगाना" भी दिखना चाहिए
                    if (mb_strpos($seg, '😴') !== false && mb_strpos($seg, 'दिशा: जगाना') === false) {
                        return false;
                    }
                    // और उस पर शांति-उपाय की सूची नहीं लगनी चाहिए
                    if (mb_strpos($seg, 'शांत नहीं करना') === false && mb_strpos($seg, '😴') !== false) {
                        return false;
                    }
                }
                return true;
            });

        // योग-पन्ना पूरी पुस्तक-सूची रखता है, पर डिफ़ॉल्ट रूप से सिर्फ़ लागू सूत्र
        // दिखने चाहिए — और चेक हटाने पर चेतावनी। बिना इसके पढ़ने वाला उन सैकड़ों
        // सूत्रों का फल अपने ऊपर पढ़ लेता है जो उसकी कुंडली की बात ही नहीं।
        $add('YG-1', 'योग-पन्ना डिफ़ॉल्ट रूप से सिर्फ़ लागू सूत्र दिखाता है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (!preg_match('/class="lk-onlyapp" data-scope="yoga" checked/u', $h)) { return false; }
                if (mb_strpos($h, 'सूत्रों में से इस कुंडली पर') === false) { return false; }
                if (mb_strpos($h, 'lk-yoga-allwarn') === false) { return false; }
            }
            return true;
        });

        // दृष्टि-पन्ना: जो सचमुच लग रहा है वह पहले, और टक्करें क़िस्म के हिसाब से
        // गिनती के साथ — क्योंकि वे औसतन हर कुंडली में पंद्रह बार बनती हैं।
        $add('DR-1', 'दृष्टि-पन्ना जीवित संबंध पहले दिखाता है और टक्करें समूह में', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view" data-lk="drishti">');
                $j = mb_strpos($h, '<div class="lk-view" data-lk="remedy">');
                if ($i === false || $j === false || $j <= $i) { return false; }
                $seg = mb_substr($h, $i, $j - $i);
                if (mb_strpos($seg, 'असल में क्या लग रहा है') === false) { return false; }
                if (mb_strpos($seg, 'विशेष टक्करें') !== false) {
                    // गिनती व "असामान्य नहीं" वाली सच्चाई साथ होनी चाहिए
                    if (mb_strpos($seg, 'यह असामान्य नहीं है') === false) { return false; }
                    // और उपाय क़िस्म पर एक बार, हर जगह दोहराया हुआ नहीं
                    if (substr_count($seg, 'सब जगहों पर यही') < 1) { return false; }
                }
            }
            return true;
        });

        // ऋण के "व" वाले नियम पूरे मिलने चाहिए। एक ग्रह पर ऋण घोषित कर देना सिर्फ़
        // ग़लत गिनती नहीं — उसके साथ वे वाक्य भी छपते हैं जो पढ़ने वाले पर अपराध का
        // आरोप लगाते हैं। इसलिए यह जाँच पन्ने पर ही परखती है कि जिन नियमों में
        // ग्रह छूटे हैं, वे "बनता है" में न गिने जाएँ।
        $add('RN-1', 'ऋण के "व" वाले नियम अधूरे मिलान पर नहीं बनते', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view" data-lk="shrap">');
                $j = mb_strpos($h, '<div class="lk-view" data-lk="sadesati">');
                if ($i === false || $j === false || $j <= $i) { return false; }
                $seg = mb_substr($h, $i, $j - $i);
                // बने हुए ऋणों वाला हिस्सा अधूरे-मिलान वाले खाने से पहले ख़त्म होता
                // है — वहीं तक देखो, वरना नीचे का "नहीं मिले" ऊपर के कार्ड का लगने
                // लगता है और जाँच बिना किसी असली ख़राबी के लाल हो जाती है।
                $cut = mb_strpos($seg, 'अधूरा मिलान');
                $built = $cut !== false ? mb_substr($seg, 0, $cut) : $seg;
                if (mb_strpos($built, 'बनता है') !== false && mb_strpos($built, 'नहीं मिले') !== false) {
                    return false;
                }
            }
            return true;
        });

        // "संकेत" पुस्तक के पहचान-चिह्न हैं, आरोप नहीं — और वे भूतकाल में लिखे हैं
        // ("हत्या की होगी")। बिना लेबल के छापना पढ़ने वाले पर अपराध मढ़ना है।
        $add('RN-2', 'ऋण के "संकेत" आरोप नहीं, मिलान के सवाल कहकर दिखते हैं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view" data-lk="shrap">');
                $j = mb_strpos($h, '<div class="lk-view" data-lk="sadesati">');
                if ($i === false || $j === false || $j <= $i) { return false; }
                $seg = mb_substr($h, $i, $j - $i);
                if (mb_strpos($seg, 'बनता है') === false) { continue; }   // इस कुंडली पर कोई ऋण नहीं
                if (mb_strpos($seg, 'पहचान के चिह्न') === false) { return false; }
                if (mb_strpos($seg, 'आरोप नहीं') === false) { return false; }
                // अशुभ फल शर्त के साथ ही आए
                if (mb_strpos($seg, 'अशुभ फल:') !== false) { return false; }
                if (mb_strpos($seg, 'उपाय न किया जाए') === false) { return false; }
            }
            return true;
        });

        // मंगली दोष की तीव्रता — पुस्तक का अपना क्रम पन्ने पर लिखा है, इसलिए बरता
        // भी जाना चाहिए। 12वें का मंगल 7वें जैसा नहीं पढ़ा जा सकता।
        $add('MG-1', 'मंगली दोष तीव्रता के साथ बताया जाता है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view" data-lk="manglik">');
                $j = mb_strpos($h, '<div class="lk-view" data-lk="agecycle">');
                if ($i === false || $j === false || $j <= $i) { return false; }
                $seg = mb_substr($h, $i, $j - $i);
                if (mb_strpos($seg, 'मंगलीक दोष है') !== false) {
                    if (mb_strpos($seg, 'तीव्रता —') === false) { return false; }
                    if (mb_strpos($seg, 'वैदिक आधार पर') === false) { return false; }
                }
            }
            return true;
        });

        // "समय" वाले अनुभाग में सन् होना ही चाहिए। आयु में लिखा दौर पढ़ने वाले से
        // हर बार जोड़-घटा कराता है, और जो हिसाब हर बार ख़ुद करना पड़े वह किया नहीं
        // जाता — यानी टाइमलाइन होते हुए भी किसी को पता नहीं चलता कि कब।
        $add('TM-1', 'आयु-दशा टाइमलाइन हर दौर पर सन् भी बताती है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view" data-lk="agecycle">');
                $j = mb_strpos($h, '<div class="lk-view" data-lk="ayu">');
                if ($i === false || $j === false || $j <= $i) { return false; }
                $seg = mb_substr($h, $i, $j - $i);
                // नौ दौर का पहिया — हर एक पर सन् की पट्टी
                if (substr_count($seg, 'सन् ') < 9) { return false; }
                if (mb_strpos($seg, 'अगला बदलाव') === false) { return false; }
            }
            return true;
        });

        // बीते और आगे वाले दौर के उपाय आज के काम की तरह न दिखें — वरना आदमी एक साथ
        // कई उपाय शुरू कर बैठता है, और वही अनुशासन टूटता है जिस पर निचोड़ टिका है।
        $add('TM-2', 'बीते व आगामी दौर के उपाय आज के काम की तरह नहीं दिखते', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view" data-lk="agecycle">');
                $j = mb_strpos($h, '<div class="lk-view" data-lk="ayu">');
                if ($i === false || $j === false || $j <= $i) { return false; }
                $seg = mb_substr($h, $i, $j - $i);
                if (mb_strpos($seg, 'बीत चुका') !== false
                    && mb_strpos($seg, 'अब करने की बात नहीं') === false) { return false; }
                if (mb_strpos($seg, '>आगे<') !== false
                    && mb_strpos($seg, 'अभी शुरू न करें') === false) { return false; }
            }
            return true;
        });

        // दशा-सूची जन्म से शुरू हो, और खुली सिर्फ़ चालू दशा मिले। बीता हुआ जीवन ही
        // वह हिस्सा है जिससे ज्योतिषी मिलान करता है — उसके बिना बाक़ी फल पर भरोसा
        // बनने का कोई रास्ता नहीं। पर सब खोलकर रख देना पन्ने को फिर से ढेर बना देता।
        $add('TM-3', 'दशा-सूची जन्म से है और खुली सिर्फ़ चालू दशा', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view" data-lk="agecycle">');
                $j = mb_strpos($h, '<div class="lk-view" data-lk="ayu">');
                if ($i === false || $j === false || $j <= $i) { return false; }
                $seg = mb_substr($h, $i, $j - $i);
                $rows = substr_count($seg, '<details class="lk-dasha-row"');
                $open = substr_count($seg, '<details class="lk-dasha-row" open');
                // एक पूरे चक्र में नौ दौर। जवान जातक की सूची छोटी होती है (आगे का
                // दायरा आयु+36 तक है), इसलिए कसौटी एक पूरा चक्र है, दो नहीं।
                if ($rows < 9 || $open !== 1) { return false; }
                if (mb_strpos($seg, 'चक्र 1 — आयु 1–35') === false) { return false; }
                if (mb_strpos($seg, 'मिलान के लिए') === false) { return false; }
            }
            return true;
        });

        // वर्ष-कुंडली पन्ना बाक़ी लाल किताब पन्नों जैसा दिखे: चुनाव व सार ऊपर एक
        // पट्टी में (उसी तरह जैसे "उपाय हेतु आपकी स्थिति"), कुंडली उसके नीचे।
        $add('VK-1', 'वर्ष-कुंडली में चुनाव व सार ऊपर की पट्टी में हैं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view" data-lk="varsh">');
                if ($i === false) { return false; }
                $j = mb_strpos($h, '<div class="lk-view" data-lk="compare">');
                if ($j === false || $j <= $i) { return false; }
                $seg = mb_substr($h, $i, $j - $i);
                // क्रम ही कसौटी है: पट्टी → चुनाव → सार → मापक → (समेटा परिचय) → कुंडली।
                // सिर्फ़ "कुंडली से पहले" जाँचना काफ़ी नहीं — सार पट्टी से बाहर खिसका
                // देने पर भी वह शर्त पूरी हो जाती थी, इसलिए हर पड़ाव का क्रम देखा
                // जाता है और परिचय को सीमा-चिह्न की तरह बरता जाता है।
                $pos = [];
                foreach (['strip' => 'class="lkv-top"', 'age' => 'id="lkv-age"', 'year' => 'id="lkv-year"',
                          'sum' => 'id="lkv-summary"', 'bar' => 'id="lkv-bar"',
                          'about' => 'class="lk-note lkv-about"', 'main' => 'class="lkv-main"'] as $k => $needle) {
                    $at = mb_strpos($seg, $needle);
                    if ($at === false) { return false; }
                    $pos[$k] = $at;
                }
                $order = ['strip', 'age', 'year', 'sum', 'bar', 'about', 'main'];
                for ($n2 = 1; $n2 < count($order); $n2++) {
                    if ($pos[$order[$n2]] <= $pos[$order[$n2 - 1]]) { return false; }
                }
                // क्रम भर देखना काफ़ी नहीं था — सार को पट्टी से बाहर खिसका देने पर भी
                // क्रम वही रहता है। इसलिए सचमुच का घेराव जाँचा जाता है: पट्टी खुलने
                // और सार के बीच जितने <div खुले, उतने ही बंद हुए हों (पट्टी ख़ुद अब भी
                // खुली) — तभी सार उसके भीतर है।
                foreach (['sum', 'bar'] as $inside) {
                    $between = mb_substr($seg, $pos['strip'], $pos[$inside] - $pos['strip']);
                    $opens  = substr_count($between, '<div');
                    $closes = substr_count($between, '</div>');
                    if ($opens - $closes < 1) { return false; }
                }
            }
            return true;
        });

        // इस कुंडली में जो काम मना हैं, वे ग्राहक-पन्ने की "न करने योग्य" सूची में
        // होने ही चाहिए — किताब उन्हीं के आगे "निर्धन या कंगाल हो जायेंगे" लिखती है।
        // पहले ये चुपचाप ग़ायब थे, क्योंकि सूची ग़लत कुंजी पढ़ रही थी।
        $add('RM-1', 'वर्जित काम ग्राहक-पन्ने की "न करने योग्य" सूची में आते हैं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view active" data-lk="nichod">');
                $j = mb_strpos($h, '<div class="lk-view" data-lk="overview">');
                if ($i === false || $j === false || $j <= $i) { return false; }
                $nichod = mb_substr($h, $i, $j - $i);
                // इस कुंडली की वर्जित सूची (नियम-पन्ने से) उठाकर मिलाओ
                $vi = mb_strpos($h, '<div class="lk-view" data-lk="rules">');
                if ($vi === false) { return false; }
                $rules = mb_substr($h, $vi, 9000);
                if (!preg_match('/🚫 ([^<]{12,})/u', $rules, $m)) { continue; }   // कोई वर्जित नहीं
                $first = trim($m[1]);
                if (mb_strpos($nichod, mb_substr($first, 0, 25)) === false) { return false; }
            }
            return true;
        });

        // उपाय-भंडार में एक ही पाठ जगह-दर-जगह दोहराया न जाए — दोहराव में वे उपाय
        // दब जाते हैं जो सचमुच नाम लेकर बताए गए हैं।
        $add('RM-2', 'उपाय-भंडार में एक ही उपाय-पाठ दोहराया नहीं जाता', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $i = mb_strpos($h, '<div class="lk-view" data-lk="remedy">');
                $j = mb_strpos($h, '<div class="lk-view" data-lk="calendar">');
                if ($i === false || $j === false || $j <= $i) { return false; }
                $seg = mb_substr($h, $i, $j - $i);
                // ऊपर की "पहले सिर्फ़ इतना करें" पट्टी जान-बूझकर वही उपाय दोहराती है
                // जो जारी हुए हैं — वह दोहराव सही है। भंडार वहीं से आगे शुरू होता है।
                $after = mb_strpos($seg, 'एक साथ कई उपाय शुरू करने से कोई पूरा नहीं होता');
                if ($after !== false) { $seg = mb_substr($seg, $after); }
                preg_match_all('/<li><b>[^<]*<\/b>\s*([^<]{20,})/u', $seg, $m);
                $texts = array_map(static fn ($t) => trim($t), $m[1]);
                if ($texts !== array_unique($texts)) { return false; }
            }
            return true;
        });

        // आयु-पन्ना: नियम इतने चौड़े हैं कि हर कुंडली पर कई योग एक साथ बनते हैं और
        // आपस में उलटे पड़ते हैं। उन्हें "लागू · लागू · लागू" की सूची बनाकर छापना
        // पढ़ने वाले को यह समझाता है कि उसकी आयु पर कई ओर से ख़तरा है। और बालारिष्ट
        // बचपन का योग है — बड़ी उम्र वाले को उसे आज की चेतावनी दिखाना सादा ग़लती है।
        $add('AY-1', 'आयु-योग गिनती के रूप में दिखते हैं, और बीत चुका बालारिष्ट वैसा कहा जाता है',
            static function () use ($pages): bool {
                foreach ($pages as $h) {
                    $i = mb_strpos($h, '<div class="lk-view" data-lk="ayu">');
                    $j = mb_strpos($h, '<div class="lk-view" data-lk="bhavan">');
                    if ($i === false || $j === false || $j <= $i) { return false; }
                    $seg = mb_substr($h, $i, $j - $i);
                    if (mb_strpos($seg, 'आयु-योग बनते हैं') === false) { continue; }   // कोई योग नहीं बना
                    if (mb_strpos($seg, 'कोई अंक या निष्कर्ष नहीं निकाला जाता') === false) { return false; }
                    // बालारिष्ट दिखे तो या तो वह बीता हुआ कहा जाए, या चेतावनी के साथ हो
                    if (mb_strpos($seg, 'बालारिष्ट आयु के योग') !== false) {
                        $past = mb_strpos($seg, 'यह प्रश्न बीत चुका') !== false;
                        $warn = mb_strpos($seg, 'घबराने की बात नहीं') !== false;
                        if (!$past && !$warn) { return false; }
                    }
                }
                return true;
            });

        // टेवे के नीचे वैदिक D1 और विंशोत्तरी का बटन। D1 इसलिए कि लाल किताब का
        // टेवा स्थिर मेष का है और उसमें राशि दिखती ही नहीं — मिलान के लिए असली
        // कुंडली पास चाहिए। विंशोत्तरी अलग पॉपअप में रहती है, पन्ने में घुली हुई
        // नहीं, ताकि वह लाल किताब के फल में मिलावट न करे।
        $add('D1-1', 'लाल किताब टेवे के नीचे वैदिक D1 और विंशोत्तरी का बटन मौजूद', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'id="lk-d1-side"') === false) { return false; }
                if (mb_strpos($h, 'id="lk-vim-btn"') === false) { return false; }
                // टेवे के बाद आए, पहले नहीं
                $teva = mb_strpos($h, 'id="lk-chart"');
                $d1   = mb_strpos($h, 'id="lk-d1-side"');
                if ($teva === false || $d1 < $teva) { return false; }
                // और पॉपअप का ✕ बंद-बटन भी हो
                if (mb_strpos($h, 'id="dasha-modal-close"') === false) { return false; }
            }
            return true;
        });

        // ऊपर की पट्टी और D1 के नीचे — दोनों जगह लाल किताब दशा, सन् सहित। सूर्य-राशि
        // की टाइल हटाई गई (वह जानकारी D1 पन्ने पर पहले से है)।
        $add('TOP-1', 'ऊपर की टाइल व D1 के नीचे लाल किताब दशा सन् सहित दिखती है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                // लेबल किस tag में छपा है, यह जाँच की चिंता नहीं — बात यह है कि
                // सूर्य-राशि वाला ख़ाना न हो और लाल किताब दशा वाला हो। पहले यह
                // जाँच `<div ...>` से बँधी थी, इसलिए पट्टी का रूप बदलते ही वह
                // बिना किसी असली गड़बड़ के लाल हो जाती।
                if (preg_match('/class="ov-label">\s*Sun Sign\s*</u', $h)) { return false; }
                if (!preg_match('/class="ov-label">\s*लाल किताब दशा\s*</u', $h)) { return false; }
                if (mb_strpos($h, '📕 लाल किताब दशा:') === false) { return false; }
                // दोनों जगह सन् भी हो — आयु अकेली कुछ नहीं बताती
                if (!preg_match('/लाल किताब दशा.{0,400}सन् \d{4}/su', $h)) { return false; }
            }
            return true;
        });

        // टेवे के नीचे की स्थिर भाव-स्वामी सूची हटाई गई — वह हर पन्ने पर दोहराने की
        // चीज़ नहीं थी, और उसकी जगह अब D1 चित्र को मिली है। D1 टेवे जितना चौड़ा हो,
        // वरना दोनों चित्र आमने-सामने पढ़े नहीं जाते।
        $add('D1-2', 'टेवे की पुरानी भाव-स्वामी सूची हटी, D1 पूरी चौड़ाई में', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'हर भाव का स्वामी स्थिर है') !== false) { return false; }
                if (mb_strpos($h, '<div id="lk-d1-side" class="w-full">') === false) { return false; }
                // राशि की जानकारी कहीं और से भी मिलती रहे — छपने वाली रिपोर्ट में
                if (mb_strpos($h, 'चन्द्र राशि') === false) { return false; }
            }
            return true;
        });

        // "और देखें" पट्टी का हर बटन किसी न किसी असली चीज़ पर जाता है। यह जाँच
        // वही टूटन पकड़ती है जो सबसे चुपचाप होती है: कोई विभाग या ड्रॉपडाउन-विकल्प
        // कल बदल जाए और पट्टी का बटन कहीं न पहुँचे — बटन दिखता रहेगा, दबाने पर
        // कुछ नहीं होगा, और किसी को पता तक न चलेगा।
        $add('MORE-1', '"और देखें" के सब बटन असली विभाग/विकल्प पर जाते हैं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'id="more-panel"') === false) { return false; }
                if (mb_strpos($h, 'id="more-modal"') === false) { return false; }
                if (mb_strpos($h, 'id="more-close"') === false) { return false; }   // ✕ बंद करने का बटन
                if (!preg_match_all('/data-more="(\w+)" data-val="([^"]*)"/u', $h, $m, PREG_SET_ORDER)) { return false; }
                if (count($m) < 40) { return false; }   // सूची अचानक ख़ाली न हो जाए
                foreach ($m as $one) {
                    [$all, $kind, $val] = $one;
                    if ($kind === 'chart') {
                        if (mb_strpos($h, '<option value="' . $val . '">') === false) { return false; }
                    } elseif ($kind === 'pred') {
                        if (!preg_match('/<option value="' . preg_quote($val, '/') . '"[^>]*>[^<]*<\/option>/u', $h)) { return false; }
                    } elseif ($kind === 'sec') {
                        if (mb_strpos($h, 'id="sec-' . $val . '"') === false) { return false; }
                    } elseif ($kind === 'url') {
                        if (mb_strpos($val, '/milan') === false) { return false; }
                    }
                }
                // लाल किताब वाले बटन उसी ड्रॉपडाउन के विकल्प हों जो पन्ने पर है
                if (preg_match_all('/data-more="sec" data-val="lalkitab" data-lk="(\w+)"/u', $h, $lm)) {
                    foreach ($lm[1] as $lkv) {
                        if (!preg_match('/<option value="' . preg_quote($lkv, '/') . '"[^>]*>/u', $h)) { return false; }
                    }
                }
            }
            return true;
        });

        // मिलान की कड़ी उसी तरफ़ भरे जो लिंग चुना गया है। ग़लत तरफ़ भरना चुपचाप
        // होता है — पन्ना खुल जाता है, बस जातक उल्टे खाने में बैठा होता है।
        $add('MLN-1', 'मिलान की कड़ी चुने गए लिंग वाली तरफ़ भरती है', static function () use ($pages, $femalePage): bool {
            // बिना लिंग वाली कुंडलियाँ — वर की तरफ़
            foreach ($pages as $h) {
                if (!preg_match('/data-more="url" data-val="([^"]*)"/u', $h, $m)) { return false; }
                $u = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                if (mb_strpos($u, 'boy_date=') === false || mb_strpos($u, 'mfrom=boy') === false) { return false; }
            }
            if ($femalePage === null) { return false; }
            if (!preg_match('/data-more="url" data-val="([^"]*)"/u', $femalePage, $m2)) { return false; }
            $u2 = html_entity_decode($m2[1], ENT_QUOTES, 'UTF-8');
            if (mb_strpos($u2, 'girl_date=') === false || mb_strpos($u2, 'mfrom=girl') === false) { return false; }
            if (mb_strpos($u2, 'boy_date=') !== false) { return false; }
            // साइड-मेन्यू की कड़ी भी वही हो, दो अलग नहीं
            return mb_strpos($femalePage, 'class="l2-mi-link" title="यह कुंडली कन्या की ओर') !== false;
        });

        // वर्षफल पन्ने की दूसरी पंक्ति — लाल किताब की वर्ष कुंडली बाएँ, मुद्दा दशा
        // दाएँ, और ग्रह-स्थिति की तालिका समेटी हुई (पर मौजूद, ताकि मेन्यू व
        // "और देखें" के उसके लिंक टूटें नहीं)।
        $add('VP-1', 'वर्षफल में लाल किताब वर्ष-कुंडली मुद्दा दशा के साथ है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                $lk = mb_strpos($h, 'id="vpl-card"');
                $md = mb_strpos($h, 'id="vp-mudda-cell"');
                $det = mb_strpos($h, 'id="vp-positions-wrap"');
                $tbl = mb_strpos($h, 'id="card-varshadet"');
                if ($lk === false || $md === false || $det === false || $tbl === false) { return false; }
                if ($lk > $md) { return false; }            // चित्र पहले (बाएँ)
                if ($det > $tbl) { return false; }          // तालिका समेटन के भीतर
                if (mb_strpos($h, 'id="vpl-chart"') === false) { return false; }
                // और पट्टी मेन्यू के नीचे न जाए
                if (mb_strpos($h, '#more-panel { grid-column: 2 / 4') === false) { return false; }
            }
            return true;
        });

        // लाल किताब का वर्ष-कुंडली पन्ना — बाएँ दोनों वर्ष-कुंडलियाँ (लाल किताब व
        // वैदिक, मुंथा सहित) एक के नीचे एक, दाएँ सिर्फ़ फल और वह अपने भीतर सरकता।
        // साझा शीर्ष-पट्टी एक ही गाँठ में बँधी हो और वर्ष-कुंडली के फल वाले खाने
        // में उसके लिए जगह हो — तभी वह वहाँ खिसक सकती है (JS: showLkView)।
        $add('LKV-2', 'साझा शीर्ष-पट्टी एक गाँठ में है और वर्ष-पन्ने पर उसकी जगह बनी है', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (mb_strpos($h, 'id="lk-head"') === false) { return false; }
                if (mb_strpos($h, 'id="lkv-head-slot"') === false) { return false; }
                // पट्टी के चारों हिस्से उसी गाँठ के भीतर हों
                if (!preg_match('/id="lk-head"(.*?)<!-- \/#lk-head -->/su', $h, $m)) { return false; }
                foreach (['lk-moderow', 'id="lk-niyam"', 'id="lk-select"', 'lk-search-row'] as $part) {
                    if (mb_strpos($m[1], $part) === false) { return false; }
                }
                // और वह जगह फल वाले खाने में हो, चित्रों वाले में नहीं
                if (!preg_match('/<div class="lkv-right">\s*(?:<\?php.*?\?>\s*)?.{0,400}id="lkv-head-slot"/su', $h)) { return false; }
            }
            return true;
        });

        $add('LKV-1', 'वर्ष-कुंडली पन्ने पर दोनों वर्ष-कुंडलियाँ बाएँ, फल दाएँ', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (!preg_match('/<div class="lkv-charts">(.*?)<\/div>\s*<!-- server-rendered/su', $h, $m)) {
                    // ढाँचा बदल गया — तब भी क्रम व मौजूदगी जाँची जाए
                    $m = [1 => $h];
                }
                foreach (['id="lkv-chart"', 'id="lkv-vedic"', 'id="lkv-janam"'] as $need) {
                    if (mb_strpos($m[1], $need) === false) { return false; }
                }
                // क्रम: लाल किताब की वर्ष-कुंडली पहले, वैदिक उसके नीचे
                if (mb_strpos($m[1], 'id="lkv-chart"') > mb_strpos($m[1], 'id="lkv-vedic"')) { return false; }
                // वैदिक कार्ड मुंथा का चिह्न बताए (वरना MUN अक्षर पहेली रह जाता है)
                if (mb_strpos($h, 'मुंथा <b>MUN</b> से चिह्नित') === false) { return false; }
                // फल का खाना चित्रों के बाद आए और सरकने वाला हो
                $body = mb_strpos($h, 'id="lkv-body"');
                if ($body === false || $body < mb_strpos($h, 'id="lkv-janam"')) { return false; }
                if (!preg_match('/id="lkv-body" class="[^"]*lk-scroll/u', $h)) { return false; }
            }
            return true;
        });

        // स्थान-कोश — बिना इंटरनेट भी जन्म-स्थान मिलना चाहिए। यह जाँच वही रास्ता
        // चलती है जो ब्राउज़र चलता है (अपना endpoint), इसलिए कोश की फ़ाइल न चढ़े
        // या रास्ता टूटे तो यहीं पकड़ा जाए — वरना पता तब चलता जब कोई ग्राहक
        // अपना शहर खोज न पाता।
        $add('GEO-1', 'स्थान-कोश तंत्र के भीतर से जवाब देता है (नेट के बिना)', static function () use ($base): bool {
            $ask = static function (string $qs) use ($base): ?array {
                $raw = self::fetch($base . '/calc/citySearch?' . $qs);
                if ($raw === null) { return null; }
                $j = json_decode($raw, true);
                return is_array($j) && !empty($j['ok']) ? $j : null;
            };
            // नाम से — पहला नतीजा वही नगर, निर्देशांक व समय-मंडल सहित
            $want = [
                'moga'     => ['Moga', 'India', 30.8, 75.1, 'Asia/Kolkata'],
                'ludhiana' => ['Ludhiana', 'India', 30.9, 75.8, 'Asia/Kolkata'],
                'toronto'  => ['Toronto', 'Canada', 43.7, -79.4, 'America/Toronto'],
            ];
            foreach ($want as $q => [$name, $country, $lat, $lon, $tz]) {
                $j = $ask('q=' . rawurlencode($q));
                if ($j === null || empty($j['results'][0])) { return false; }
                $r = $j['results'][0];
                if ((string) $r['name'] !== $name || (string) $r['country'] !== $country) { return false; }
                if (abs((float) $r['lat'] - $lat) > 0.5 || abs((float) $r['lon'] - $lon) > 0.5) { return false; }
                if ((string) $r['tz'] !== $tz) { return false; }
            }
            // पुराना नाम भी चले — लोग प्रमाणपत्र वाला नाम लिखते हैं
            $j = $ask('q=bombay');
            if ($j === null || (string) ($j['results'][0]['name'] ?? '') !== 'Mumbai') { return false; }
            // और निर्देशांक से नाम (उल्टी खोज) — यह भी अपने कोश से हो
            $j = $ask('lat=30.8038&lon=75.1494');
            if ($j === null || (string) ($j['results'][0]['name'] ?? '') !== 'Moga') { return false; }
            return !empty($j['offline']);
        });

        // गोचर पन्ने के दोनों चार्ट-खानों में घुमाने का चुनाव। गोचर पढ़ते समय
        // ज्योतिषी चन्द्र या किसी और भाव को पहले घर पर लाकर देखता है; यह सुविधा
        // अब तक सिर्फ़ जन्म-कुंडली वाले पैनल पर थी।
        $add('ROT-1', 'गोचर पन्ने के दोनों चार्ट घुमाए जा सकते हैं', static function () use ($pages): bool {
            foreach ($pages as $h) {
                if (!preg_match('/<div id="sec-gochar".*?<div id="gp-pred-src"/su', $h, $m)) { return false; }
                $sec = $m[0];
                foreach (['c1', 'c2'] as $slot) {
                    if (!preg_match('/<select class="gp-sel gp-rot-sel" data-slot-id="' . $slot . '"(.*?)<\/select>/su', $sec, $r)) {
                        return false;
                    }
                    if (preg_match_all('/<option value="\d+"/u', $r[1]) !== 12) { return false; }
                }
                if (mb_substr_count($sec, 'gp-rot-tag') !== 2) { return false; }
            }
            return true;
        });

        // ───────────────────── लाल किताब मिलान ─────────────────────
        // ये पाँच जाँचें उन्हीं पाँच चूकों की रखवाली करती हैं जो मिलान में मिलीं:
        // निष्क्रिय ग्रह का "शुभ" छपना, नकली प्रतिशत, दूसरी कुंडली वाले परिहार का
        // कभी न जाँचा जाना, बिना दिशा के ढेर-सारे उपाय, और हर जोड़े पर एक ही दर्जा।

        $add('MM-1', 'मिलान में निष्क्रिय ग्रह "शुभ" नहीं छपता', static function () use ($milan): bool {
            if ($milan === []) { return false; }
            foreach ($milan as $b) {
                // तालिका में सोया ग्रह अपने नाम से दिखे
                if (mb_strpos($b, 'निष्क्रिय (सोया)') === false) { return false; }
                // और उसे हरा (शुभ) रंग कभी न मिले — पुराना कोड इसी तरह हर तीसरे
                // ख़ाने में सोए ग्रह को "शुभ" छाप रहा था
                if (preg_match('/<span class="st-good">[^<]*निष्क्रिय/u', $b)) { return false; }
            }
            return true;
        });

        $add('MM-2', 'मिलान अंक/प्रतिशत नहीं, निर्दोष बिंदुओं की गिनती दिखाता है', static function () use ($milan): bool {
            if ($milan === []) { return false; }
            foreach ($milan as $b) {
                if (mb_strpos($b, '<div class="lbl">निर्दोष बिंदु</div>') === false) { return false; }
                // पुराना प्रतिशत-गेज लौट न आए। ("अनुकूलता" शब्द वाक्यों में चलता
                // है — जाँच गेज के अपने लेबल पर है, शब्द पर नहीं।)
                if (mb_strpos($b, '<div class="lbl">अनुकूलता</div>') !== false) { return false; }
                if (preg_match('/लाल किताब अनुकूलता \(सांकेतिक\)/u', $b)) { return false; }
                if (preg_match('/<div class="big">\s*\d+<span[^>]*>%/u', $b)) { return false; }
                // और यह भी कहा जाए कि गिनती को गुण-मिलान के अंकों से न जोड़ें
                if (mb_strpos($b, 'गुण-मिलान के अंकों से जोड़कर न पढ़ें') === false) { return false; }
            }
            return true;
        });

        $add('MM-3', 'दूसरी कुंडली वाले परिहार सचमुच जाँचे जाते हैं', static function () use ($milan): bool {
            if ($milan === []) { return false; }
            $seenApplied = false;
            foreach ($milan as $b) {
                // हर जोड़े पर परिहार का नतीजा कहा जाए — लागू हुआ या नहीं हुआ
                $said = mb_strpos($b, 'परिहार लागू') !== false
                    || mb_strpos($b, 'परिहार-सूची में से कोई शर्त') !== false
                    || mb_strpos($b, 'भाव में पाप ग्रह नहीं') !== false;
                if (!$said) { return false; }
                if (mb_strpos($b, 'परिहार लागू: दूसरी कुंडली') !== false) { $seenApplied = true; }
            }
            return $seenApplied;   // कम-से-कम एक जोड़े पर क्रॉस-परिहार सचमुच लगा हो
        });

        $add('MM-4', 'हर उपाय के साथ दिशा, और गिनती पाँच से ऊपर नहीं', static function () use ($milan): bool {
            if ($milan === []) { return false; }
            foreach ($milan as $b) {
                if (!preg_match('/🛠 उपाय \(मिलान के अनुसार\)(.*?)<\/ul>/su', $b, $m)) { continue; }
                $li  = preg_match_all('/<li>/u', $m[1]);
                $dir = preg_match_all('/class="lkm-dir /u', $m[1]);
                if ($li > 5) { return false; }
                if ($li !== $dir) { return false; }   // बिना दिशा का उपाय नहीं
            }
            return true;
        });

        $add('MM-5', 'मिलान दोनों कुंडलियों को एक ही कसौटी पर देखता है (पक्षपात-चेतावनी)', static function () use ($milan): bool {
            if ($milan === []) { return false; }
            foreach ($milan as $b) {
                if (mb_strpos($b, 'केवल कन्या को दोषी ठहराना पक्षपात है') === false) { return false; }
            }
            return true;
        });

        $add('MM-6', 'मिलान का दर्जा जोड़े-दर-जोड़े बदलता है (सब पर एक-सा नहीं)', static function () use ($milan): bool {
            if (count($milan) < 3) { return false; }
            $sig = [];
            foreach ($milan as $b) {
                preg_match('/निर्दोष बिंदु/u', $b);
                $n = preg_match('/<div class="big">(\d+)<span/u', $b, $m) ? $m[1] : '?';
                $t = preg_match('/(शुभ मिलान|शुभ — कुछ बिंदुओं पर उपाय के बाद|सावधानी योग्य[^<—]*|कठिन[^<—]*)/u', $b, $m2) ? trim($m2[1]) : '?';
                $sig[] = $n . '|' . $t;
            }
            return count(array_unique($sig)) > 1;
        });

        // विवाह-मुहूर्त तिथि-खोजक — तिथियाँ पहले से भरी हों (ख़ाली डिब्बा किसी काम
        // का नहीं) और अवधि-टैब मौजूद हों।
        $add('MDF-1', 'विवाह-तिथि खोजक में आज व छह-माह की तिथि पहले से भरी है', static function () use ($milanRaw): bool {
            if ($milanRaw === '') { return false; }
            if (!preg_match('/id="mdf_from"[^>]*value="(\d{2}-\d{2}-\d{4})"/u', $milanRaw, $mf)) { return false; }
            if (!preg_match('/id="mdf_to"[^>]*value="(\d{2}-\d{2}-\d{4})"/u', $milanRaw, $mt)) { return false; }
            if ($mf[1] !== date('d-m-Y')) { return false; }
            // तिथि d-m-Y है, इसलिए उसे उसी साँचे से पढ़ो। पहले यहाँ हाइफ़न को
            // स्लैश में बदलकर strtotime() को दिया जाता था — और स्लैश वाली तिथि
            // को PHP अमेरिकी m/d/Y मानता है: "13-02-2027" → "13/02/2027" = महीना
            // 13 = अमान्य (हर महीने की 13 से 31 तारीख़ को जाँच झूठा फ़ेल देती थी),
            // और "05-02-2027" चुपचाप 2 मई पढ़ी जाती थी। यानी जाँच वह परख ही नहीं
            // रही थी जिसका दावा करती है।
            $toDt = \DateTime::createFromFormat('d-m-Y H:i:s', $mt[1] . ' 12:00:00');
            if ($mt[1] === $mf[1] || $toDt === false || $toDt->getTimestamp() <= time()) { return false; }
            foreach ([1, 3, 6, 12] as $mn) {
                if (mb_strpos($milanRaw, 'data-months="' . $mn . '"') === false) { return false; }
            }
            return true;
        });

        // और माँगी हुई अवधि सचमुच जाँची जाए। पहले यह 120 दिन पर चुपचाप कट जाती
        // थी — साल भर माँगने वाले को चार महीने का जवाब मिलता था, और आधे से ज़्यादा
        // शुभ दिन उसे दिखते ही नहीं थे।
        $add('MDF-2', 'साल भर की माँग पर पूरा साल जाँचा जाता है (कटता नहीं)', static function () use ($mdfYear): bool {
            if ($mdfYear === null) { return false; }
            if (mb_strpos($mdfYear, 'सीमा बड़ी है') !== false) { return false; }
            if (!preg_match('/(\d+) दिन जाँचे/u', $mdfYear, $m)) { return false; }
            if ((int) $m[1] < 365) { return false; }
            // और खोज के बाद फ़ॉर्म वही सीमा दिखाता रहे — पहले डिब्बे ख़ाली हो जाते
            // थे, इसलिए यह भी पता नहीं चलता था कि नीचे की सूची किस अवधि की है
            $want = date('d-m-Y', strtotime('+1 year'));
            if (!preg_match('/id="mdf_to"[^>]*value="' . preg_quote($want, '/') . '"/u', $mdfYear)) { return false; }
            return preg_match('/class="mdf-tab on"\s*\n?\s*data-months="12"/u', $mdfYear) === 1;
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
    /**
     * मिलान-पन्ने में से केवल लाल किताब वाला हिस्सा — बाक़ी पन्ने पर गुण-मिलान
     * का अपना प्रतिशत व अपने शब्द हैं, और उन्हें इन जाँचों में घसीटने से जाँच
     * ग़लत जगह लाल/हरी होती।
     */
    private static function lkmBlock(string $html): string
    {
        $a = mb_strpos($html, 'लाल किताब मिलान — Lal Kitab Compatibility');
        if ($a === false) { return ''; }
        $b = mb_strpos($html, 'विवाह-मुहूर्त तिथि-खोजक', $a);
        return $b === false ? mb_substr($html, $a) : mb_substr($html, $a, $b - $a);
    }

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
