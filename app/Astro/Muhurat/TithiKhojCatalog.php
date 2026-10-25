<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

/**
 * 🔍 तिथि खोज — कार्यों की सूची (12 मुख्य श्रेणियाँ → उप-श्रेणियाँ)।
 *
 * मुहूर्त पन्ने का "तिथि खोज" इसी सूची से अपनी दोनों ड्रॉपडाउन भरता है। हर
 * उप-श्रेणी के साथ यह भी दर्ज है कि उसकी तिथि-खोज किस नियम से होती है:
 *
 *   • दूसरा खाना = {@see MuhuratDateScanner::TYPES} का कोई मौजूदा प्रकार — उस कार्य
 *     के शास्त्रीय (मुहूर्त-चिन्तामणि) नियम सिस्टम में पहले से हैं, खोज उन्हीं से
 *     होती है। (जैसे शादी → विवाह, गौना → द्विरागमन, शपथ → राज्याभिषेक।)
 *   • दूसरा खाना null — इस कार्य के अपने नियम अभी जोड़े जाने हैं। तब तक:
 *       – SAUMYA (शुभ/सौम्य कार्य): खोज सामान्य पंचांग-शुद्धि से होती है, और पन्ना
 *         यह साफ़ लिखता है कि यह विशेष नियम नहीं है।
 *       – VISHESH: जिन कार्यों की कसौटी ही सामान्य "शुभ दिन" से अलग है (षट्कर्म,
 *         शल्य, अन्त्येष्टि, ग्रह-शान्ति, शस्त्र) — वहाँ सामान्य शुभ-दिन दिखाना
 *         ग़लत मार्गदर्शन होता, इसलिए नियम जुड़ने तक खोज बंद रहती है।
 *
 * नियम जोड़ने का तरीक़ा: स्कैनर में नया प्रकार बनाइए और यहाँ उस उप-श्रेणी के
 * दूसरे खाने में उसका नाम लिख दीजिए — पन्ने में और कुछ नहीं बदलना पड़ता।
 * उप-श्रेणी की कुंजियाँ (बाईं ओर) स्थायी हैं; उन्हें बदलें नहीं, वे ब्राउज़र में
 * याद रखे गए चुनाव से जुड़ी हैं।
 */
final class TithiKhojCatalog
{
    public const SAUMYA = 'saumya';
    public const VISHESH = 'vishesh';

    /**
     * कुंजी => [emoji, नाम, [उप-कुंजी => [नाम, स्कैनर-प्रकार|null, स्वभाव?]]]
     * स्वभाव न लिखा हो तो SAUMYA।
     */
    public const CATEGORIES = [
        'sanskar' => ['👪', 'जीवन के मुख्य संस्कार और परिवार', [
            'garbhadhan'   => ['गर्भधारण', null],
            'godbharai'    => ['गोद भराई', 'seemantonnayan'],
            'namkaran'     => ['नामकरण (बच्चे का नाम रखना)', 'namkaran'],
            'nishkraman'   => ['बच्चे को पहली बार घर से बाहर निकालना', null],
            'annaprashan'  => ['पहली बार ठोस खाना खिलाना', 'annaprashan'],
            'mundan'       => ['मुंडन (सिर के बाल उतरवाना)', 'mundan'],
            'karnavedh'    => ['कान छिदवाना', 'karnavedh'],
            'vidyarambh'   => ['पढ़ाई / अक्षर ज्ञान शुरू कराना', 'vidyarambh'],
            'upanayan'     => ['जनेऊ संस्कार', 'upanayan'],
            'sagai'        => ['सगाई / रोका', null],
            'vivaha'       => ['शादी / विवाह', 'vivaha'],
            'gauna'        => ['गौना (दुल्हन की विदाई)', 'dviragamana'],
            'antyeshti'    => ['अंतिम संस्कार और अस्थि विसर्जन', null, self::VISHESH],
        ]],
        'ghar' => ['🏠', 'घर, जमीन और मकान', [
            'bhoomi_kray'  => ['जमीन या प्लॉट खरीदना-बेचना', null],
            'bhoomi_pujan' => ['भूमि पूजन और नींव रखना', 'vastu'],
            'griharambh'   => ['घर का निर्माण शुरू करना', 'vastu'],
            'stambh'       => ['खंभा या पिलर खड़ा करना', null],
            'dwar'         => ['मुख्य दरवाजा लगाना', null],
            'grihapravesh' => ['गृह प्रवेश (नए या पुराने घर में जाना)', 'grihapravesh'],
            'vastu_shanti' => ['घर की शांति पूजा (वास्तु शांति)', null],
            'koop'         => ['बोरवेल / कुआं खुदवाना', null],
            'marammat'     => ['घर की मरम्मत या रिनोवेशन शुरू करना', null],
        ]],
        'dharm' => ['🙏', 'पूजा-पाठ और धार्मिक कार्य', [
            'guru_diksha'  => ['गुरु दीक्षा लेना', null],
            'murti'        => ['मंदिर में मूर्ति की स्थापना करना', 'pratishtha'],
            'mandir'       => ['मंदिर बनवाना', null],
            'havan'        => ['हवन, यज्ञ या पाठ शुरू करना', null],
            'mantra_jap'   => ['मंत्र जाप साधना शुरू करना', null],
            'rudrabhishek' => ['रुद्राभिषेक और विशेष पूजा', null],
            'vrat'         => ['व्रत शुरू करना और उद्यापन करना', null],
            'grah_shanti'  => ['ग्रह शांति पूजा (शनि, राहु, कालसर्प, पितृ दोष)', null, self::VISHESH],
            'yantra'       => ['ताबीज या यंत्र पहनना', null],
        ]],
        'shatkarma' => ['🔱', 'तांत्रिक और विशेष प्रयोग (षट्कर्म)', [
            'shanti_karma' => ['शांति कर्म (संकट दूर करना)', null, self::VISHESH],
            'paushtik'     => ['बरकत और समृद्धि के उपाय', null, self::VISHESH],
            'vashikaran'   => ['वशीकरण (प्रभाव में लेना)', null, self::VISHESH],
            'stambhan'     => ['स्तम्भन (किसी को काम करने से रोकना)', null, self::VISHESH],
            'vidveshan'    => ['विद्वेषण (दो लोगों में अलगाव कराना)', null, self::VISHESH],
            'uchchatan'    => ['उच्चाटन (मन भटकाना या किसी को भगाना)', null, self::VISHESH],
            'maran'        => ['मारण (शत्रु विनाश)', null, self::VISHESH],
        ]],
        'karobar' => ['💼', 'कारोबार, नौकरी और पैसा', [
            'vyapar'       => ['नई दुकान, ऑफिस या बिजनेस शुरू करना', 'vanijya'],
            'naukri'       => ['नई नौकरी ज्वाइन करना / पद संभालना', null],
            'bahi_khata'   => ['बही-खाता पूजन और नया रजिस्टर शुरू करना', 'vanijya'],
            'rin'          => ['कर्ज लेना और कर्ज चुकाना', 'vanijya'],
            'nivesh'       => ['बैंक में पैसा जमा करना या निवेश करना', null],
            'kray_vikray'  => ['माल खरीदना और बेचना', 'vanijya'],
            'anubandh'     => ['एग्रीमेंट या कॉन्ट्रैक्ट साइन करना', null],
            'sevak'        => ['नया नौकर या कर्मचारी रखना', null],
        ]],
        'abhushan' => ['👗', 'गहने और कपड़े', [
            'swarna'       => ['सोना-चांदी खरीदना और बनवाना', null],
            'gahne'        => ['नए गहने पहली बार पहनना', null],
            'vastra'       => ['नए कपड़े पहली बार पहनना', null],
        ]],
        'ratna' => ['💎', 'रत्न एवं अंगूठी धारण (9 ग्रहों के रत्न)', [
            'ratna_manikya'  => ['माणिक्य / रूबी पहनना (सूर्य)', null],
            'ratna_moti'     => ['मोती / पर्ल पहनना (चंद्रमा)', null],
            'ratna_moonga'   => ['मूंगा / कोरल पहनना (मंगल)', null],
            'ratna_panna'    => ['पन्ना / एमराल्ड पहनना (बुध)', null],
            'ratna_pukhraj'  => ['पुखराज / यलो सफायर पहनना (बृहस्पति)', null],
            'ratna_heera'    => ['हीरा / डायमंड पहनना (शुक्र)', null],
            'ratna_neelam'   => ['नीलम / ब्लू सफायर पहनना (शनि)', null],
            'ratna_gomed'    => ['गोमेद / हेसोनाइट पहनना (राहु)', null],
            'ratna_lahsuniya' => ['लहसुनिया / कैट्स आई पहनना (केतु)', null],
        ]],
        'safar' => ['🚗', 'सफर और गाड़ी', [
            'yatra'        => ['छोटी-बड़ी यात्रा पर निकलना', null],
            'videsh'       => ['विदेश यात्रा शुरू करना', null],
            'teerth'       => ['तीर्थ यात्रा पर जाना', null],
            'vahan_kray'   => ['नई गाड़ी खरीदना या बेचना', null],
            'vahan_pujan'  => ['नई गाड़ी की पहली बार सवारी और पूजा', null],
        ]],
        'sehat' => ['🩺', 'सेहत और इलाज', [
            'aushadh_nirman' => ['दवाइयां बनाना', null],
            'aushadh_sevan'  => ['नई दवा खाना शुरू करना', 'chikitsa'],
            'shalya'         => ['ऑपरेशन / सर्जरी कराना', null, self::VISHESH],
            'shalya_prasav'  => ['ऑपरेशन से बच्चे का जन्म कराना', null, self::VISHESH],
            'rogmukti_snan'  => ['बीमारी ठीक होने के बाद पहला शुद्ध स्नान', null],
        ]],
        'krishi' => ['🌾', 'खेती-बाड़ी और पशु', [
            'hal'          => ['खेत में हल चलाना / जुताई', null],
            'beej'         => ['बीज बोना', 'krishi'],
            'ropan'        => ['पेड़-पौधे और बाग लगाना', 'krishi'],
            'fasal'        => ['फसल काटना', null],
            'anaj'         => ['अनाज गोदाम में भरना', null],
            'pashu'        => ['गाय, भैंस या अन्य पशु खरीदना-बेचना', null],
        ]],
        'kanoon' => ['⚖️', 'कोर्ट-कचहरी और राजनीति', [
            'shapath'      => ['मंत्री या नेता का शपथ ग्रहण', 'rajyabhishek'],
            'mukadma'      => ['कोर्ट केस या मुकदमा दर्ज करना', 'yuddha'],
            'samjhauta'    => ['दो पक्षों में राजीनामा या समझौता करना', null],
            'shastra'      => ['हथियार खरीदना या संभालना', null, self::VISHESH],
        ]],
        'dincharya' => ['🎨', 'रोजमर्रा के काम और हुनर सीखना', [
            'kala'         => ['संगीत, डांस या पेंटिंग सीखना शुरू करना', null],
            'granth'       => ['किताब लिखना या छपवाना', null],
            'kshaur'       => ['बाल कटवाना, शेविंग और नाखून काटना', null],
            'abhyang'      => ['तेल मालिश करके नहाना', null],
            'vasiyat'      => ['वसीयत या रजिस्ट्री लिखना', null],
        ]],
    ];

    /**
     * पन्ने के लिए तैयार रूप — हर उप-श्रेणी की स्थिति सहित:
     *   'rule'    — शास्त्रीय नियम उपलब्ध (मौजूदा स्कैनर-प्रकार से खोज)
     *   'general' — विशेष नियम शीघ्र; अभी सामान्य पंचांग-शुद्धि से खोज
     *   'pending' — विशेष नियम शीघ्र; तब तक खोज बंद
     *
     * @return list<array{key:string,emoji:string,label:string,
     *   subs:list<array{key:string,label:string,type:string,status:string,rule:string}>}>
     */
    public static function forView(): array
    {
        $types = MuhuratDateScanner::TYPES;
        $out = [];
        foreach (self::CATEGORIES as $ck => [$emoji, $label, $subs]) {
            $list = [];
            foreach ($subs as $sk => $row) {
                $type = $row[1] ?? null;
                $nature = $row[2] ?? self::SAUMYA;
                if ($type !== null && isset($types[$type])) {
                    $status = 'rule';
                } elseif ($nature === self::VISHESH) {
                    $status = 'pending';
                    $type = null;
                } else {
                    $status = 'general';
                    $type = 'general';
                }
                $list[] = [
                    'key' => $sk, 'label' => $row[0], 'type' => (string) $type,
                    'status' => $status, 'rule' => $type !== null ? (string) $types[$type] : '',
                ];
            }
            $out[] = ['key' => $ck, 'emoji' => $emoji, 'label' => $label, 'subs' => $list];
        }
        return $out;
    }
}
