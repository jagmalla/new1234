<?php

declare(strict_types=1);

namespace AutoBusiness\Astro\Geo;

/**
 * स्थान-कोश — जन्म-स्थान की खोज, बिना इंटरनेट के।
 * ------------------------------------------------------------------
 * पहले स्थान की खोज Open-Meteo की ऑनलाइन सेवा से होती थी। उसका मतलब यह था कि
 * बिना इंटरनेट (localhost पर, या जहाँ नेट न पहुँचे) कुंडली बनाई ही नहीं जा
 * सकती थी — क्योंकि अक्षांश-देशांतर के बिना कोई गणना शुरू नहीं होती। अब पूरा
 * कोश तंत्र के भीतर है।
 *
 * आँकड़े — `cities.tsv.gz`, 1,48,038 नगर, हर पंक्ति नौ खानों में (TAB से अलग):
 *
 *     खोज-कुंजी(नगर) ⇥ खोज-कुंजी(राज्य+देश) ⇥ नगर ⇥ राज्य/प्रान्त ⇥ देश
 *       ⇥ अक्षांश ⇥ देशांतर ⇥ IANA समय-मंडल ⇥ आबादी
 *
 * पहले दो खाने **पहले से** छोटे अक्षरों व मात्रा-रहित बनाकर रखे जाते हैं (वही
 * norm() जो खोजते समय चलती है, इसलिए दोनों कभी अलग नहीं हो सकतीं)। इनके बिना हर
 * पंक्ति को पढ़ते समय बदलना पड़ता था और एक खोज में 276 मि.से. लगती थीं; अब 80।
 *
 * • नाम, राज्य, देश व निर्देशांक — `country-state-city` (npm) से।
 * • **समय-मंडल** — जिन देशों में एक ही मंडल है वहाँ सीधे उसी देश का; जिनमें एक
 *   से अधिक हैं (अमेरिका, कनाडा, रूस, ऑस्ट्रेलिया, ब्राज़ील, मेक्सिको…) वहाँ
 *   `city-timezones` (npm) के 7,329 ज्ञात नगरों में से **निकटतम** का मंडल।
 *   सीमा-रेखा के पास बसे नगरों में यह चूक सकता है — इसीलिए फ़ॉर्म में समय-मंडल
 *   का खाना हाथ से बदला जा सकता है (Advanced), और वह इसी से भरता है।
 * • आबादी सिर्फ़ **क्रम** लगाने को है (बड़ा नगर पहले) — `all-the-cities` (npm)
 *   से नाम+देश पर जोड़ी गई, इसलिए एक ही देश में एक ही नाम के दो नगरों को एक-सा
 *   अंक मिल सकता है। उन्हें राज्य के नाम से पहचाना जाता है, अंक से नहीं।
 *
 * फ़ाइल webroot के बाहर रहती है, इसलिए कोई उसे थोक में उतार नहीं सकता; खोज
 * सर्वर पर होती है और सिर्फ़ मिले हुए आठ नगर ब्राउज़र तक जाते हैं।
 *
 * रफ़्तार: एक खोज में लगभग 80 मि.से. (1.48 लाख पंक्तियाँ, gzip सहित) — टाइप
 * करते समय की देरी (debounce) के भीतर।
 *
 * ── आँकड़ों का श्रेय (licence) ──
 *  • नगर/राज्य/देश व निर्देशांक — dr5hn/countries-states-cities-database (ODbL 1.0)
 *  • समय-मंडल के आधार-नगर — SimpleMaps World Cities basic (CC BY 4.0)
 *  • आबादी — GeoNames (CC BY 4.0)
 * तीनों का उल्लेख रखना शर्त है; इसीलिए यह टिप्पणी फ़ाइल के साथ चलती है।
 */
final class CityGazetteer
{
    /** कितने नगर लौटाए जाएँ। */
    private const LIMIT = 8;

    /**
     * पुराने/प्रचलित नाम → कोश वाला नाम।
     *
     * लोग जन्म-स्थान वही लिखते हैं जो जन्म-प्रमाणपत्र पर है, और भारत में कई
     * नगरों के नाम बदल चुके हैं। इनके बिना "Bombay" या "Calcutta" लिखने पर कुछ
     * नहीं मिलता, जबकि नगर कोश में मौजूद है।
     */
    private const ALIASES = [
        'bombay' => 'mumbai', 'calcutta' => 'kolkata', 'madras' => 'chennai',
        'bangalore' => 'bengaluru', 'poona' => 'pune', 'baroda' => 'vadodara',
        'trivandrum' => 'thiruvananthapuram', 'mysore' => 'mysuru',
        'gurgaon' => 'gurugram', 'allahabad' => 'prayagraj', 'benares' => 'varanasi',
        'banaras' => 'varanasi', 'cawnpore' => 'kanpur', 'pondicherry' => 'puducherry',
        'simla' => 'shimla', 'cochin' => 'kochi', 'calicut' => 'kozhikode',
        'ootacamund' => 'udagamandalam', 'panjim' => 'panaji', 'orissa' => 'odisha',
        'rangoon' => 'yangon', 'peking' => 'beijing', 'saigon' => 'ho chi minh city',
    ];

    /** आँकड़ा-फ़ाइल का पता। */
    public static function file(): string
    {
        return __DIR__ . '/cities.tsv.gz';
    }

    public static function available(): bool
    {
        return is_readable(self::file());
    }

    /**
     * नगर खोजो।
     *
     * "moga" · "moga, punjab" · "ludhiana india" — तीनों चलते हैं: पहला शब्द-समूह
     * नगर पर और बाक़ी राज्य/देश पर मिलाया जाता है।
     *
     * @return list<array{name:string,admin:string,country:string,lat:float,lon:float,tz:string,label:string}>
     */
    public static function search(string $query, int $limit = self::LIMIT): array
    {
        $q = self::norm($query);
        if ($q === '' || !self::available()) { return []; }

        // "नगर, राज्य" — अल्पविराम हो तो पहला हिस्सा नगर, बाक़ी इलाक़ा
        $where = '';
        if (str_contains($q, ',')) {
            [$q, $where] = array_map('trim', explode(',', $q, 2));
            if ($q === '') { return []; }
        }
        $q = self::ALIASES[$q] ?? $q;
        if (mb_strlen($q) < 2) { return []; }

        $hits = [];
        $fh = @gzopen(self::file(), 'rb');
        if ($fh === false) { return []; }
        while (($line = gzgets($fh)) !== false) {
            // सस्ती छँटाई पहले — पूरी पंक्ति में शब्द है ही नहीं तो आगे बढ़ो।
            // कुंजियाँ पहले से बनी हैं, इसलिए यहाँ कुछ बदलना नहीं पड़ता।
            if (strpos($line, $q) === false) { continue; }
            $col = explode("\t", rtrim($line, "\r\n"));
            if (count($col) < 9) { continue; }
            $name = $col[0];
            $rank = null;
            if ($name === $q)                    { $rank = 0; }
            elseif (str_starts_with($name, $q))  { $rank = 1; }
            elseif (str_contains($name, $q))     { $rank = 2; }
            if ($rank === null) { continue; }    // राज्य/देश में मिलना अकेले काफ़ी नहीं
            if ($where !== '' && !str_contains($col[1], $where)) { continue; }
            $hits[] = [
                'rank' => $rank,
                'pop'  => (int) ($col[8] ?? 0),
                'row'  => array_slice($col, 2),   // कुंजियाँ आगे नहीं जातीं
            ];
        }
        gzclose($fh);

        // पहले जो ठीक वही नाम है, फिर जिससे शुरू होता है, फिर जिसमें आता है;
        // बराबरी पर बड़ा नगर पहले — वरना "Delhi" पर पहले कोई गाँव आ जाता है।
        usort($hits, static function (array $a, array $b): int {
            return [$a['rank'], -$a['pop']] <=> [$b['rank'], -$b['pop']];
        });

        $out = [];
        foreach (array_slice($hits, 0, max(1, $limit)) as $hit) {
            $c = $hit['row'];
            $label = implode(', ', array_values(array_filter([$c[0], $c[1], $c[2]], static fn ($x) => trim((string) $x) !== '')));
            $out[] = [
                'name'    => (string) $c[0],
                'admin'   => (string) $c[1],
                'country' => (string) $c[2],
                'lat'     => (float) $c[3],
                'lon'     => (float) $c[4],
                'tz'      => (string) $c[5],
                'label'   => $label,
            ];
        }
        return $out;
    }

    /**
     * निर्देशांक से निकटतम नगर — "यह जगह कौन-सी है" का उल्टा जवाब।
     *
     * जिस कुंडली में स्थान का नाम नहीं भरा, वहाँ पन्ना अब तक अक्षांश-देशांतर
     * दिखाता था और नाम के लिए एक ऑनलाइन सेवा (bigdatacloud) से पूछता था। वही
     * काम अब इसी कोश से होता है — बिना इंटरनेट भी नाम आ जाता है।
     *
     * @return array{name:string,admin:string,country:string,lat:float,lon:float,tz:string,label:string}|null
     */
    public static function nearest(float $lat, float $lon): ?array
    {
        if (!self::available() || $lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
            return null;
        }
        $fh = @gzopen(self::file(), 'rb');
        if ($fh === false) { return null; }
        $cosLat = cos($lat * M_PI / 180.0);
        $best = null; $bestD = INF;
        while (($line = gzgets($fh)) !== false) {
            $col = explode("\t", rtrim($line, "\r\n"));
            if (count($col) < 9) { continue; }
            $dLat = ((float) $col[5]) - $lat;
            if ($dLat > 3.0 || $dLat < -3.0) { continue; }   // सस्ती छँटाई — 3° से दूर देखना ही नहीं
            $dLon = (((float) $col[6]) - $lon) * $cosLat;
            $d = $dLat * $dLat + $dLon * $dLon;              // वर्ग में तुलना, जड़ की ज़रूरत नहीं
            if ($d < $bestD) { $bestD = $d; $best = $col; }
        }
        gzclose($fh);
        if ($best === null) { return null; }
        $c = array_slice($best, 2);
        return [
            'name' => (string) $c[0], 'admin' => (string) $c[1], 'country' => (string) $c[2],
            'lat' => (float) $c[3], 'lon' => (float) $c[4], 'tz' => (string) $c[5],
            'label' => implode(', ', array_values(array_filter([$c[0], $c[1], $c[2]],
                static fn ($x) => trim((string) $x) !== ''))),
        ];
    }

    /**
     * मिलान के लिए एक-सा रूप — छोटे अक्षर + मात्रा-रहित लातीनी।
     * "Cancún" लिखने वाला "Cancun" भी लिख सकता है, और दोनों एक ही नगर हैं।
     */
    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        if (preg_match('/[\x80-\xFF]/', $s)) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t) && $t !== '') { $s = mb_strtolower($t, 'UTF-8'); }
            $s = str_replace(['`', "'", '"', '^', '~'], '', $s);
        }
        return preg_replace('/\s+/', ' ', $s) ?? $s;
    }
}
