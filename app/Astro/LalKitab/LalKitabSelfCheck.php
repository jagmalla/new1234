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
