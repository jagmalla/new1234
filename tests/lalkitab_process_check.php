<?php

declare(strict_types=1);

/**
 * लाल किताब प्रक्रिया — जाँच-कवच (टर्मिनल से)
 * ------------------------------------------------------------------
 * असली जाँचें LalKitabSelfCheck में हैं, ताकि टर्मिनल और ब्राउज़र दोनों एक ही
 * कोड चलाएँ और दोनों जगह नतीजा एक ही रहे। यह फ़ाइल सिर्फ़ उसे चलाकर छापती है।
 *
 *   चलाएँ:  php tests/lalkitab_process_check.php
 *   पता बदलें:  LK_BASE=https://आपकी-साइट php tests/lalkitab_process_check.php
 *
 * लाइव सर्वर पर टर्मिनल न हो तो ब्राउज़र वाला रास्ता:
 *   /lk-selfcheck.php?key=…   (कुंजी .env के LK_SELFCHECK_KEY से)
 */

require dirname(__DIR__) . '/bootstrap.php';

use AutoBusiness\Astro\LalKitab\LalKitabSelfCheck;

$base = getenv('LK_BASE') ?: 'http://127.0.0.1:8899';
$r = LalKitabSelfCheck::run($base);

echo "\n लाल किताब प्रक्रिया — जाँच-कवच (" . $r['fetched'] . " कुंडलियाँ · " . $r['base'] . ")\n";
echo str_repeat('─', 66) . "\n";

if ($r['error'] !== '') {
    fwrite(STDERR, ' ❌ ' . $r['error'] . "\n\n");
    exit(2);
}

foreach ($r['rows'] as $row) {
    echo ($row['ok'] ? '  ✅ ' : '  ❌ ') . $row['id'] . ' — ' . $row['what'] . "\n";
}
echo str_repeat('─', 66) . "\n";
printf(" पास: %d · फेल: %d\n\n", $r['pass'], $r['fail']);
exit($r['fail'] === 0 ? 0 : 1);
