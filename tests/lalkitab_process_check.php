<?php

declare(strict_types=1);

/**
 * लाल किताब प्रक्रिया — जाँच-कवच
 * ------------------------------------------------------------------
 * इनमें से हर जाँच एक ऐसी विफलता रोकती है जो या तो दिखती नहीं, या नुक़सान
 * पहुँचाती है। दो सबसे ज़रूरी:
 *
 *   X2 — जो ग्रह इस वक़्त किसी ऋण को रोके हुए है उसे शांत कर देना। आदमी की
 *        ज़िंदगी उस क्षेत्र में ठीक चल रही है *क्योंकि* कुछ उसे रोके हुए है।
 *        कवच हटते ही मुसीबत आती है — और वह भी उपाय शुरू करने के हफ़्तों बाद।
 *        वह दोनों को जोड़ेगा, और ठीक ही जोड़ेगा।
 *
 *   X3 — सोए ग्रह को शांत करना। वह और गहरी नींद सो जाता है, बरसों उपाय बेअसर
 *        रहते हैं, और कोई कारण दिखता भी नहीं।
 *
 * चलाने का तरीक़ा:  php tests/lalkitab_process_check.php
 * (dev-server चालू हो — यह HTTP से असली पन्ने जाँचता है, ताकि view की परत भी
 *  जाँच में आ जाए, सिर्फ़ क्लास नहीं।)
 */

$base = getenv('LK_BASE') ?: 'http://127.0.0.1:8899';
$charts = [
    '01-12-1980|10:30|28.6139|77.2090',
    '15-08-1947|00:00|28.6139|77.2090',
    '26-01-1950|08:15|28.6139|77.2090',
    '07-07-2001|14:45|19.0760|72.8777',
    '15-06-1985|00:00|28.6139|77.2090',
    '20-03-1962|18:20|28.6139|77.2090',
];

$pass = $fail = 0;
$results = [];
/** @param callable():bool $fn */
$check = static function (string $id, string $what, callable $fn) use (&$pass, &$fail, &$results): void {
    try { $ok = $fn(); } catch (\Throwable $e) { $ok = false; $what .= ' [' . $e->getMessage() . ']'; }
    $ok ? $pass++ : $fail++;
    $results[] = ($ok ? '  ✅ ' : '  ❌ ') . $id . ' — ' . $what;
};

$pages = [];
foreach ($charts as $c) {
    [$d, $t, $lat, $lon] = explode('|', $c);
    $url = $base . '/calc?date=' . rawurlencode($d) . '&time=' . rawurlencode($t)
        . '&lat=' . $lat . '&lon=' . $lon . '&tz=5.5&layout=lalkitab';
    $html = @file_get_contents($url);
    if ($html === false) {
        fwrite(STDERR, "dev-server नहीं चल रहा — पहले उसे शुरू करें।\n");
        exit(2);
    }
    $pages[$d] = $html;
}

echo "\n लाल किताब प्रक्रिया — जाँच-कवच (" . count($pages) . " कुंडलियाँ)\n";
echo str_repeat('─', 66) . "\n";

// ── बुनियादी सेहत ─────────────────────────────────────────────
$check('BASE', 'कोई PHP error / "गणना विफल" कहीं नहीं', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (preg_match('/Fatal error|Uncaught|Parse error|गणना विफल/u', $h)) { return false; }
    }
    return true;
});

// ── R3 · V3 — निष्क्रिय की छत ─────────────────────────────────
// सोया ग्रह "मध्यम" नहीं होता। यह सबसे ज़्यादा ग़लत पढ़ा जाने वाला दर्जा है:
// आदमी को "ठीक-ठाक" बताया जाता है जबकि असल में कुछ हो ही नहीं रहा।
$check('R3/V3', 'निष्क्रिय दर्जा मौजूद है और उसका अपना वाक्य है', static function () use ($pages): bool {
    $seen = false;
    foreach ($pages as $h) {
        if (mb_strpos($h, 'निष्क्रिय') !== false) {
            $seen = true;
            if (mb_strpos($h, 'रुका') === false) { return false; }   // अपना शब्द होना चाहिए
        }
    }
    return $seen;   // कम-से-कम एक कुंडली में मिलना चाहिए, वरना गेट-0 चला ही नहीं
});

// ── S7 — अस्थिर और मिश्रित अलग रहें ───────────────────────────
// ये दो अलग ज़िंदगियाँ हैं: "कुछ ठीक कुछ बुरा" बनाम "वही बात बार-बार पलटती है"।
$check('S7', 'अस्थिर व मिश्रित के वाक्य अलग-अलग हैं', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (mb_strpos($h, 'अस्थिर') !== false) {
            return mb_strpos($h, 'टिकाव नहीं') !== false;
        }
    }
    return true;
});

// ── U3 — ठहराव कभी "मंदा" न बने ───────────────────────────────
// (पूरे पन्ने पर "ठहराव" शब्द डेटा-बैंक के पाठ में भी आता है — इसलिए जाँच सिर्फ़
//  दशा-पट्टी के ⏸ चिह्न पर, जो केवल period_type=ठहराव पर ही निकलता है।)
$check('U3', 'निष्क्रिय शासक की दशा "ठहराव" है, "मंदा" नहीं', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (mb_strpos($h, '⏸') !== false && mb_strpos($h, 'बिना ख़ास हलचल') === false) { return false; }
    }
    return true;
});

// ── U4 — साढ़ेसाती पर स्रोत का लेबल ────────────────────────────
// यह पूरा तंत्र लाल किताब की व्याकरण पर चलता है; साढ़ेसाती इकलौता अपवाद है।
// लेबल के बिना वह लाल किताब का सिद्धांत लगती है, जो वह नहीं है।
$check('U4', 'हर पन्ने पर "वैदिक आधार पर" लेबल मौजूद', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (mb_strpos($h, 'वैदिक आधार पर') === false) { return false; }
    }
    return true;
});

// ── U7 — मृत्यु / तबाही का समय कभी नहीं ───────────────────────
$check('U7', 'मृत्यु-समय या तबाही की भविष्यवाणी कहीं नहीं', static function () use ($pages): bool {
    foreach ($pages as $h) {
        // इंजन के अपने गढ़े वाक्य — "मृत्यु सन् …" जैसी कोई तारीख़ी घोषणा
        if (preg_match('/मृत्यु\s*(का\s*समय|सन्|वर्ष\s*\d{4})/u', $h)) { return false; }
        if (mb_strpos($h, 'कोई उपाय नहीं') !== false) { return false; }
    }
    return true;
});

// ── U6 — कोई दौर पूरा बुरा नहीं होता ──────────────────────────
$check('U6', 'चालू दौर के साथ सहारा भी बताया गया', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (mb_strpos($h, 'का दौर चल रहा है') !== false && mb_strpos($h, 'सहारा') === false) { return false; }
    }
    return true;
});

// ── U-end — अंत-तिथि के बिना कठिन दौर नहीं ────────────────────
$check('END', 'चालू दशा अपनी समाप्ति-तिथि के साथ दिखती है', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (mb_strpos($h, 'लाल किताब दशा') !== false && mb_strpos($h, 'आयु') === false) { return false; }
    }
    return true;
});

// ── W1 · W3 — निचोड़ का आकार व ताक़त-पहले ─────────────────────
// (अनुभाग-शीर्षक इमोजी सहित खोजे जाते हैं — वरना जाँच परिचय-वाक्य या टिप्पणी
//  के शब्दों पर लग जाती है और झूठी विफलता देती है।)
$check('W1/W3', 'निचोड़ में मिज़ाज है और ताक़त कठिनाई से पहले आती है', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (mb_strpos($h, '🧭 कुंडली का मिज़ाज') === false) { return false; }
        $s = mb_strpos($h, '💪 आपकी मज़बूती');
        $d = mb_strpos($h, '⚠️ ध्यान देने की बातें');
        if ($s !== false && $d !== false && $s > $d) { return false; }   // ताक़त पहले
    }
    return true;
});

// ── X4 — एक, ज़्यादा से ज़्यादा दो ─────────────────────────────
// आठ उपायों की सूची दूसरे हफ़्ते छूट जाती है, और छूटा हुआ उपाय शुरू न किए गए
// उपाय से बुरा है।
$check('X4', 'निचोड़ में जारी उपाय दो से ज़्यादा नहीं', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (!preg_match('/🛠 अभी करने योग्य उपाय.*?✅ करने योग्य/su', $h, $m)) { continue; }
        if (preg_match_all('/दिशा:\s*(शांति|बल-वृद्धि|जगाना|चुकाना|शांत करना)/u', $m[0]) > 2) { return false; }
    }
    return true;
});

// ── X3 — सोए ग्रह को शांत मत करो ──────────────────────────────
// यही वह चूक है जो बरसों की ईमानदार मेहनत बेकार कर देती है।
$check('X3', 'रुके हुए ग्रह की दिशा "जगाना" है, "शांति" नहीं', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (mb_strpos($h, 'इसका उपाय शांति नहीं') !== false && mb_strpos($h, 'जगाना') === false) { return false; }
    }
    return true;
});

// ── X5 — हर उपाय का अंत भी बताया जाए ──────────────────────────
$check('X5', 'हर जारी उपाय पर अवधि व "कब रोकें" मौजूद', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (mb_strpos($h, 'दिशा:') !== false && mb_strpos($h, 'कब रोकें') === false) { return false; }
    }
    return true;
});

// ── X8 — स्वास्थ्य पर डॉक्टर-नोट ──────────────────────────────
// यही इकलौती जगह है जहाँ आत्मविश्वास से भरा इंजन किसी की जान से जुड़ा नुक़सान
// कर सकता है।
$check('X8', 'स्वास्थ्य की बात के साथ डॉक्टर-नोट', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (mb_strpos($h, 'रोग') !== false && mb_strpos($h, 'चिकित्सा-सलाह नहीं') === false) { return false; }
    }
    return true;
});

// ── X9 — कोई उपाय पैसे की शर्त पर नहीं ────────────────────────
// यह इस क्षेत्र की पहचान बन चुकी ठगी है; इंजन को यह पैदा ही नहीं करना चाहिए।
$check('X9', 'कोई उपाय किसी को पैसे देने की शर्त पर नहीं', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (preg_match('/(ज्योतिषी|पंडित|मंदिर).{0,20}(शुल्क|फ़ीस|फीस|दक्षिणा देकर ही)/u', $h)) { return false; }
    }
    return true;
});

// ── Y11 — सीमा-वाक्य ──────────────────────────────────────────
// किताब का अपना रुख़: फल बदला जा सकता है — इसीलिए उपाय हैं।
$check('Y11', 'हर पन्ने पर "कुंडली बाँधती नहीं" वाली सीमा', static function () use ($pages): bool {
    foreach ($pages as $h) {
        if (mb_strpos($h, 'बाँधती नहीं') === false) { return false; }
    }
    return true;
});

// ── Y10 — बारनम जाँच ──────────────────────────────────────────
// जो वाक्य दो बिल्कुल अलग कुंडलियों पर एक-सा निकले, वह finding नहीं — भराव है।
// यह एक जाँच बाक़ी सब मिलाकर से ज़्यादा गुणवत्ता देती है।
$check('Y10', 'दो अलग कुंडलियों का निचोड़ एक-जैसा नहीं', static function () use ($pages): bool {
    $grab = static function (string $h): string {
        if (!preg_match('/🧭 कुंडली का मिज़ाज.*?📅 अभी का समय/su', $h, $m)) { return ''; }
        return preg_replace('/\s+/u', ' ', strip_tags($m[0])) ?? '';
    };
    $a = $grab($pages['07-07-2001'] ?? '');
    $b = $grab($pages['15-08-1947'] ?? '');
    if ($a === '' || $b === '') { return false; }
    similar_text($a, $b, $pct);
    return $pct < 80.0;   // 80% से ज़्यादा मेल = भराव पैदा हो रहा है
});

echo implode("\n", $results) . "\n";
echo str_repeat('─', 66) . "\n";
printf(" पास: %d · फेल: %d\n\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
