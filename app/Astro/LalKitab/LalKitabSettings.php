<?php

declare(strict_types=1);

namespace AutoBusiness\Astro\LalKitab;

/**
 * लाल किताब — विवादित नियमों की सेटिंग
 * ------------------------------------------------------------------
 * लाल किताब की कुछ बातों पर घराने आपस में सहमत नहीं हैं। जैसे: सोया ग्रह दृष्टि
 * डालता भी है या नहीं? राहु-केतु पर संगत बैठक से भारी पड़ती है — पक्के घर में भी?
 * मृत अवस्था मानी जाए या वह अंधे का ही गहरा रूप है?
 *
 * इनका कोई एक "सही" जवाब यह कोड तय नहीं कर सकता। पर चुपचाप एक पक्ष चुन लेना और
 * उसे नियम की तरह छाप देना — यही सबसे बड़ी बेईमानी होगी, क्योंकि तब पढ़ने वाले को
 * पता ही नहीं चलेगा कि उसका फल किस मत पर बना है। इसलिए तीन बातें साथ चलती हैं:
 *
 *   1. हर विवादित बिंदु यहाँ नाम से दर्ज है, अपने विकल्पों और डिफ़ॉल्ट के साथ।
 *   2. ज्योतिषी पन्ने पर उसे बदल सकता है (चुनाव URL में जाता है, इसलिए वही लिंक
 *      दोबारा खोलने पर वही फल आता है — और ग्राहक को भेजा गया लिंक भी वही कहेगा)।
 *   3. हर रिपोर्ट के नीचे चुनी हुई सेटिंग छपती है।
 *
 * डिफ़ॉल्ट वही हैं जो spec सुझाती है। बदलने से इंजन का व्यवहार सचमुच बदलता है —
 * ये सजावट नहीं हैं; हर एक के आगे लिखा है कि वह किस जगह लागू होता है।
 */
final class LalKitabSettings
{
    /**
     * विवादित बिंदु — सवाल, विकल्प (मान => दिखने वाला नाम), और डिफ़ॉल्ट।
     *
     * @var array<string,array{q:string,help:string,default:string,options:array<string,string>}>
     */
    public const FIELDS = [
        'soya_drishti' => [
            'q'       => 'सोए ग्रह की दृष्टि',
            'help'    => 'जो ग्रह ख़ुद सो रहा है, उसकी दृष्टि दूसरे भाव पर कितनी लगती है।',
            'default' => 'reduced',
            'options' => [
                'reduced' => 'आधी रह जाती है (spec का सुझाव)',
                'full'    => 'पूरी लगती है',
                'none'    => 'बिल्कुल नहीं लगती',
            ],
        ],
        'seat_precedence' => [
            'q'       => 'बैठक का क्रम',
            'help'    => 'बैठक के तथ्य आपस में उलटे पड़ें तो कौन-सा पहले गिना जाए।',
            'default' => 'pakka_first',
            'options' => [
                'pakka_first'  => 'पक्का घर > नीच > उच्च > शत्रु/मित्र (spec का सुझाव)',
                'dignity_first' => 'उच्च/नीच > पक्का घर > शत्रु/मित्र',
            ],
        ],
        'chhaya_company' => [
            'q'       => 'राहु-केतु पर संगत की प्रधानता',
            'help'    => 'छाया-ग्रह का फल संगत से तय होता है — पर क्या पक्के घर में भी?',
            'default' => 'yes',
            'options' => [
                'yes'       => 'हाँ, हर हाल में (spec का सुझाव)',
                'not_pakka' => 'हाँ, पर पक्के घर में नहीं',
                'no'        => 'नहीं — बैठक ही तय करे',
            ],
        ],
        'rin_severity' => [
            'q'       => 'ऋण की तीव्रता',
            'help'    => 'ऋण कितना भारी है — यह दर्जों में नापा जाए या सिर्फ़ है/नहीं।',
            'default' => 'graded',
            'options' => [
                'graded' => 'दर्जों में — हल्का/मध्यम/तीव्र (spec का सुझाव)',
                'binary' => 'सिर्फ़ है या नहीं',
            ],
        ],
        'rank_vishwasghat' => [
            'q'       => 'विश्वासघात बनाम टक्कर',
            'help'    => 'दोनों उलटा कहें तो भार-क्रम में कौन ऊपर।',
            'default' => 'above',
            'options' => [
                'above' => 'विश्वासघात ऊपर (spec का सुझाव)',
                'below' => 'टक्कर ऊपर',
            ],
        ],
        'rank_dignity' => [
            'q'       => 'उच्च/नीच बनाम शत्रु/मित्र घर',
            'help'    => 'भार-क्रम में इन दोनों में कौन ऊपर।',
            'default' => 'above',
            'options' => [
                'above' => 'उच्च/नीच ऊपर (spec का सुझाव)',
                'below' => 'शत्रु/मित्र घर ऊपर',
            ],
        ],
        'apang_kasauti' => [
            'q'       => 'अपंग अवस्था की कसौटी',
            'help'    => 'एकतरफ़ा दृष्टि में "कोई नहीं देखता" आम बात है — इसे अपने-आप दोष माना जाए या तभी, जब ग्रह सचमुच अकेला भी हो।',
            'default' => 'dheeli',
            'options' => [
                'dheeli' => 'ढीली — न देखा जाना ही काफ़ी (मौजूदा)',
                'sakht'  => 'सख़्त — साथी भी न हो, तभी',
            ],
        ],
        'mrit_avastha' => [
            'q'       => 'मृत अवस्था',
            'help'    => 'सीढ़ी में "मृत" अलग अवस्था मानी जाए, या वह अंधे का ही गहरा रूप है।',
            'default' => 'keep',
            'options' => [
                'keep' => 'अलग अवस्था मानें (मौजूदा 5-सीढ़ी)',
                'drop' => 'न मानें — अंधा ही कहें',
            ],
        ],
    ];

    /** @var array<string,string> इस अनुरोध की चुनी हुई सेटिंग */
    private static array $current = [];

    /**
     * अनुरोध के इनपुट से सेटिंग तय करना। अनजाना या ग़लत मान चुपचाप डिफ़ॉल्ट पर
     * लौटता है — कोई भी लिंक इंजन को अपरिभाषित हालत में नहीं डाल सकता।
     *
     * @param array<string,mixed> $in
     */
    public static function apply(array $in): void
    {
        $out = [];
        foreach (self::FIELDS as $k => $f) {
            $v = is_string($in[$k] ?? null) ? (string) $in[$k] : '';
            $out[$k] = isset($f['options'][$v]) ? $v : $f['default'];
        }
        self::$current = $out;
    }

    /** किसी एक सेटिंग का मौजूदा मान (कभी ख़ाली नहीं लौटता)। */
    public static function get(string $key): string
    {
        if (self::$current === []) { self::apply([]); }
        return self::$current[$key] ?? (string) (self::FIELDS[$key]['default'] ?? '');
    }

    /** सब सेटिंग — रिपोर्ट के नीचे छापने के लिए। @return array<string,string> */
    public static function all(): array
    {
        if (self::$current === []) { self::apply([]); }
        return self::$current;
    }

    /** पढ़ने लायक़ रूप — "सोए ग्रह की दृष्टि: आधी रह जाती है"। @return list<string> */
    public static function describe(): array
    {
        $out = [];
        foreach (self::all() as $k => $v) {
            $f = self::FIELDS[$k] ?? null;
            if ($f === null) { continue; }
            $out[] = $f['q'] . ': ' . ($f['options'][$v] ?? $v);
        }
        return $out;
    }

    /** क्या सब सेटिंग डिफ़ॉल्ट पर हैं (यानी spec का सुझाया रूप)। */
    public static function allDefault(): bool
    {
        foreach (self::all() as $k => $v) {
            if ($v !== (string) self::FIELDS[$k]['default']) { return false; }
        }
        return true;
    }
}
