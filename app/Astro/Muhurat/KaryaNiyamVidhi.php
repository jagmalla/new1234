<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

/**
 * 📜 मुहूर्त निर्णय — कार्य-वार नियमों की व्याख्या (कोड-रूप)।
 *
 * {@see KaryaNiyamData} में मालिक की कार्यपुस्तिका का पाठ ज्यों-का-त्यों है; यहाँ
 * उसी पाठ के वे नियम हैं जो अंक-सूचियों (T/V/N/L) से बाहर, टिप्पणी-कॉलमों में
 * लिखे हैं — पक्ष, मास, वर्जित-काल, समय, लग्न-शुद्धि, अबूझ पर्व, विकल्प। हर
 * प्रविष्टि के आगे उसी पंक्ति का आधार-वाक्य टिप्पणी में है।
 *
 * संकेत:
 *   paksha    'shukla' (कृष्ण में दोष) · 'shukla_pref' (कृष्ण मध्यम) · 'krishna' (शुक्ल मध्यम) · 'none'
 *             न लिखा हो तो स्वभाव से: मांगलिक कार्य में कृष्ण 1–10 मध्यम, 11–15 दोष
 *             (मार्गदर्शिका चरण 3)।
 *   tithi     पक्ष-भेद वाली टिप्पणी — 1–30 क्रमांक में: bad / ok / madhyam
 *   maas      sun (सूर्य-राशि 0 मेष…11 मीन, केवल ये), sun_bad, months (पूर्णिमान्त मास
 *             0 चैत्र…11 फाल्गुन), months_madhyam, months_bad, hard (मास-नियम कठोर),
 *             uttarayan ('hard'|'pref'), ritu (वर्ष-ऋतु पसंद)
 *   varjit    वर्जित-काल कूट (नीचे VARJIT_LABEL) — कठोर; soft = वही पर केवल चेतावनी
 *   sado      'full' (सामान्य दोष पूरे) · 'relaxed' (केवल भद्रा व सूतक) · [सूची] · 'none'
 *   time      any · day · night · purvahna · pradosh · pradosh_morning · kutup ·
 *             evening · morning · brahma · rahukaal · ritu:1..6 (षट्कर्म दिन-भाग)
 *   hora      पसंद की होरा (ग्रह) — hora_strict = ग्रह की होरा ही (रत्न)
 *   lagna     false = लग्न-शुद्धि नहीं; या [empty (रिक्त भाव — कठोर), bad (ग्रह => भाव),
 *             moon_hard, eighth_hard, bhav (कार्य-भाव), benefic_lagna, svabhav, …]
 *   abujh     अबूझ/उत्तम पर्व कूट (FESTIVALS)
 *   options   उपयोगकर्ता-विकल्प (दिशा, क्रय/विक्रय, नया/पुराना घर …)
 */
final class KaryaNiyamVidhi
{
    /** वर्जित-काल कूट → प्रदर्शन-नाम। */
    public const VARJIT_LABEL = [
        'kharmas' => 'खरमास (सूर्य धनु/मीन)',
        'adhik' => 'अधिक (मल) मास',
        'chaturmas' => 'चातुर्मास (देवशयनी → देवउठनी)',
        'asta' => 'गुरु/शुक्र अस्त',
        'guru_asta' => 'गुरु अस्त',
        'shukra_asta' => 'शुक्र अस्त',
        'budh_asta' => 'बुध अस्त',
        'balvriddha' => 'गुरु/शुक्र बाल-वृद्ध दोष',
        'holashtak' => 'होलाष्टक',
        'pitru' => 'पितृपक्ष (श्राद्ध पक्ष)',
        'simhastha' => 'सिंहस्थ गुरु',
        'grahan' => 'ग्रहण (±3 दिन)',
        'grahan_viddha' => 'ग्रहण-विद्ध नक्षत्र (6 मास)',
        'dagdha_tithi' => 'दग्धा तिथि (सूर्य-राशि से)',
        'latta' => 'लत्ता दोष',
        'yuti' => 'युति दोष (चंद्र पाप ग्रह के साथ)',
        'janma_maas' => 'जन्म मास',
        'janma_nak' => 'जन्म नक्षत्र',
        'sun_dvisvabhava' => 'सूर्य द्विस्वभाव राशि में',
        'panchak' => 'पंचक',
        'sankranti_day' => 'संक्रान्ति का दिन',
        'ravi_hasta' => 'रविवार + हस्त',
        'vriddhi_yoga' => 'वृद्धि योग',
        'pushkar' => 'द्विपुष्कर/त्रिपुष्कर',
        'budh_vakri' => 'बुध वक्री (आधुनिक मत)',
        'graha_asta' => 'रत्न का ग्रह अस्त/नीच',
        'chandrashtam' => 'चंद्राष्टम (जन्म राशि से 8वाँ चंद्र)',
        'pratishukra' => 'प्रतिशुक्र (शुक्र सम्मुख)',
    ];

    /** पूरे "मांगलिक" समूह के वर्जित काल (कार्यपुस्तिका का बार-बार आने वाला वाक्य)। */
    public const M6 = ['kharmas', 'chaturmas', 'adhik', 'asta', 'balvriddha', 'holashtak', 'pitru'];

    /** श्रेणी-स्तर के "मुख्य वर्जित" (श्रेणी_सिद्धांत) — पंक्ति में न लिखे हों तो चेतावनी भर। */
    public const CATEGORY_SOFT = [
        1 => ['kharmas', 'chaturmas', 'adhik', 'asta', 'holashtak', 'pitru'],
        2 => ['sun_dvisvabhava', 'panchak', 'asta', 'kharmas'],
        3 => ['adhik'],
        9 => ['chandrashtam'],
    ];

    /** दिशाएँ — कोण (पूर्व 0°, घड़ी की दिशा में)। */
    public const DIRS = [
        'पूर्व' => 0, 'आग्नेय' => 45, 'दक्षिण' => 90, 'नैऋत्य' => 135,
        'पश्चिम' => 180, 'वायव्य' => 225, 'उत्तर' => 270, 'ईशान' => 315,
    ];

    /**
     * अबूझ / पर्व — पूर्णिमान्त मास (0 चैत्र…), पक्ष, तिथि (पक्ष की 1–15)।
     * at: तिथि कब देखें — rise (उदया), pradosh, night (निशीथ)।
     */
    public const FESTIVALS = [
        'gudi' => ['गुड़ी पड़वा / नव संवत्सर', 0, 's', [1], 'rise', true],
        'navratri_chaitra' => ['चैत्र नवरात्र', 0, 's', [1, 2, 3, 4, 5, 6, 7, 8, 9], 'rise', false],
        'ramnavami' => ['राम नवमी', 0, 's', [9], 'rise', false],
        'akshaya3' => ['अक्षय तृतीया', 1, 's', [3], 'rise', true],
        'ganga10' => ['गंगा दशहरा', 2, 's', [10], 'rise', false],
        'bhadli9' => ['भड़ली नवमी', 3, 's', [9], 'rise', true],
        'devshayani' => ['देवशयनी एकादशी', 3, 's', [11], 'rise', false],
        'gurupurnima' => ['गुरु पूर्णिमा', 3, 's', [15], 'rise', false],
        'nagpanchami' => ['नाग पंचमी', 4, 's', [5], 'rise', false],
        'rakhi' => ['रक्षा बंधन', 4, 's', [15], 'rise', false],
        'janmashtami' => ['जन्माष्टमी', 5, 'k', [8], 'night', false],
        'ganesh4' => ['गणेश चतुर्थी', 5, 's', [4], 'rise', false],
        'navratri' => ['शारदीय नवरात्र', 6, 's', [1, 2, 3, 4, 5, 6, 7, 8, 9], 'rise', false],
        'vijaya10' => ['विजयादशमी (दशहरा)', 6, 's', [10], 'rise', true],
        'sharad15' => ['शरद पूर्णिमा', 6, 's', [15], 'rise', false],
        'karva4' => ['करवा चौथ', 7, 'k', [4], 'rise', false],
        'dhanteras' => ['धनतेरस', 7, 'k', [13], 'pradosh', false],
        'diwali' => ['दीपावली', 7, 'k', [15], 'pradosh', false],
        'govardhan' => ['गोवर्धन / बलि प्रतिपदा', 7, 's', [1], 'rise', true],
        'bhaidooj' => ['भाई दूज', 7, 's', [2], 'rise', false],
        'labh5' => ['लाभ पंचमी', 7, 's', [5], 'rise', false],
        'devuthani' => ['देवउठनी एकादशी', 7, 's', [11], 'rise', true],
        'kartik15' => ['कार्तिक पूर्णिमा', 7, 's', [15], 'rise', false],
        'vasant5' => ['वसंत पंचमी', 10, 's', [5], 'rise', true],
        'shivratri' => ['महाशिवरात्रि', 11, 'k', [14], 'night', false],
        'phulera2' => ['फुलेरा दूज', 11, 's', [2], 'rise', true],
        'holi' => ['होलिका दहन', 11, 's', [15], 'pradosh', false],
    ];

    /** वार/तिथि/नक्षत्र से बनने वाले आवर्ती पर्व (FESTIVALS के अलावा)। */
    public const RECURRING = [
        'pradosh' => 'प्रदोष (त्रयोदशी)',
        'masik_shivratri' => 'मासिक शिवरात्रि (कृष्ण 14)',
        'amavasya' => 'अमावस्या',
        'purnima' => 'पूर्णिमा',
        'shani_amavasya' => 'शनि अमावस्या',
        'shani_pradosh' => 'शनि प्रदोष',
        'shravan' => 'श्रावण मास',
        'shravan_somvar' => 'श्रावण सोमवार',
        'pushya_guru' => 'गुरु-पुष्य',
        'pushya_ravi' => 'रवि-पुष्य',
        'grahan' => 'ग्रहण',
        'makar' => 'मकर संक्रान्ति',
        'baisakhi' => 'बैसाखी (मेष संक्रान्ति)',
        'pitru_paksha' => 'पितृपक्ष',
        'sarvapitru' => 'सर्वपितृ अमावस्या',
    ];

    /** सामान्य लग्न-शुद्धि (लग्न_चंद्र_तारा, नियम 3–8) — जिनकी पंक्ति में ये लिखे हैं। */
    private const LS_STD = ['eighth_hard' => true, 'moon_hard' => true];

    /** कार्य-ID => व्याख्या। */
    public const TASKS = [
        // ── 1. जीवन संस्कार व परिवार ─────────────────────────────────────
        // ऋतुकाल की 4–16वीं रात्रि, केवल रात्रि; संक्रान्ति/ग्रहण/श्राद्ध/जन्म नक्षत्र/गण्डमूल वर्जित
        '1.01' => [
            'time' => 'night', 'varjit' => ['sankranti_day', 'pitru', 'janma_nak'], 'nak' => ['bad' => [1]],
            'lagna' => self::LS_STD + ['bhav' => [5]],
            'note' => 'ऋतुकाल की 4थी से 16वीं रात्रि (11वीं व 13वीं वर्जित) — ③ में मासिक धर्म आरम्भ की तिथि भरें तो यह भी जँचती है। चंद्र-तारा बल पत्नी की जन्म राशि से (पति का भी, यदि भरा)।',
        ],
        // सीमन्त हेतु पुरुष-वार; लोक में सोम-बुध-शुक्र भी; शुक्ल श्रेष्ठ; पूर्वाह्न
        '1.02' => [
            'paksha' => 'shukla_pref', 'time' => 'purvahna', 'vaar_madhyam' => [2, 4, 6],
            'lagna' => self::LS_STD, 'options' => ['garbh'],
            'note' => 'पुंसवन 2रे-3रे मास, सीमन्त 6ठे/8वें मास, लोक-रीति गोद भराई 7वें मास — ③ में LMP भरें तो गर्भ-मास जँचता है। चंद्र-ताराबल गर्भिणी की राशि से।',
        ],
        // रविवार मध्यम; 11/12वाँ दिन; पूर्वार्ध; अमावस्या/संक्रान्ति/ग्रहण
        '1.03' => [
            'time' => 'purvahna', 'vaar_madhyam' => [1], 'varjit' => ['sankranti_day'],
            'lagna' => self::LS_STD + ['svabhav' => ['sthir', 'dvi']],
            'age' => 'namkaran',
            'note' => 'नियत 11वें/12वें दिन (सूतक-समाप्ति बाद) तिथि-वार विचार शिथिल। नाम का प्रथम अक्षर जन्म-नक्षत्र के चरण-अक्षर से (③ में शिशु का विवरण भरने पर दिखता है)।',
        ],
        // 4था मास; पूर्वाह्न; दिशाशूल की दिशा में प्रथम निकास नहीं
        '1.04' => [
            'time' => 'purvahna', 'lagna' => self::LS_STD, 'options' => ['dir'], 'age' => 'nishkraman',
            'note' => 'जन्म से 4था मास (सूर्य-दर्शन 12वें दिन/3रे मास)। प्रथम निकास प्रायः देव-मंदिर दर्शन हेतु।',
        ],
        // सम मास (बालक) / विषम (बालिका); 10वाँ व 8वाँ रिक्त; M6
        '1.05' => [
            'time' => 'purvahna', 'varjit' => self::M6,
            'lagna' => self::LS_STD + ['empty' => [10]],
            'age' => 'annaprashan',
        ],
        // 1/3/5 वर्ष; उत्तरायण; माघ फाल्गुन वैशाख ज्येष्ठ (चैत्र मत); शुक्ल; M6; जन्म मास/नक्षत्र
        '1.06' => [
            'paksha' => 'shukla_pref',
            'maas' => ['months' => [10, 11, 1, 2], 'months_madhyam' => [0], 'uttarayan' => 'pref'],
            'varjit' => self::M6, 'soft' => ['janma_maas', 'janma_nak'],
            'lagna' => self::LS_STD,
            'age' => 'mundan', 'jyeshtha' => 'single',
            'note' => 'ज्येष्ठ संतान का ज्येष्ठ मास में मुंडन वर्जित। माता 5 मास से अधिक गर्भवती हो तो (5 वर्ष से छोटे बालक हेतु) वर्जित। कुलदेवी/तीर्थ पर कुल-परंपरा अनुसार।',
        ],
        // कार्तिक पौष चैत्र फाल्गुन; शुक्ल; चातुर्मास, जन्म मास, अस्त; लग्न में गुरु/शुक्र
        '1.07' => [
            'paksha' => 'shukla_pref',
            'maas' => ['months' => [7, 0, 11], 'months_madhyam' => [9]],
            'varjit' => ['chaturmas', 'asta'], 'soft' => ['janma_maas'],
            'lagna' => self::LS_STD + ['benefic_lagna' => ['Jupiter', 'Venus']], 'age' => 'karnavedh',
            'note' => 'बालक का पहले दायाँ, बालिका का पहले बायाँ कान। पौष मास मु.चि. में शुभ, लोक में खरमास से वर्जित (मतभेद)।',
        ],
        // बुध गुरु शुक्र श्रेष्ठ, रवि-सोम मध्यम; उत्तरायण; गुरु-बुध अस्त, खरमास, अधिक
        '1.08' => [
            'time' => 'purvahna', 'vaar_madhyam' => [1, 2], 'maas' => ['uttarayan' => 'pref'],
            'varjit' => ['guru_asta', 'budh_asta', 'kharmas', 'adhik'],
            'lagna' => self::LS_STD + ['bhav' => [5, 4]], 'age' => 'vidyarambh',
            'abujh' => ['vasant5', 'vijaya10', 'akshaya3', 'gurupurnima'],
        ],
        // गलग्रह; षष्ठी मध्यम; सोम मध्यम; उत्तरायण (माघ–आषाढ़); शुक्ल; M6; गुरु बल
        '1.09' => [
            'paksha' => 'shukla_pref', 'time' => 'purvahna', 'tithi' => ['madhyam' => [6, 21]], 'vaar_madhyam' => [2],
            'maas' => ['months' => [10, 11, 0, 1, 2, 3], 'hard' => true, 'uttarayan' => 'hard'],
            'varjit' => self::M6,
            'lagna' => self::LS_STD + ['benefic_kendra' => 'Jupiter'],
            'personal' => ['guru_bal' => true], 'age' => 'upanayan', 'options' => ['varna'],
            'abujh' => ['vasant5', 'akshaya3'],
            'note' => 'ब्राह्मण 8वाँ, क्षत्रिय 11वाँ, वैश्य 12वाँ वर्ष (गर्भ से) — काम्य 5/6/8वाँ; सीमा 16/22/24। बटुक का गुरु बल (न हो तो गुरु-शांति कर — परंपरा) व सूर्य बल।',
        ],
        // रविवार मध्यम; शुक्ल; चंद्रबल; खरमास/पितृपक्ष (कई परिवार)
        '1.10' => [
            'paksha' => 'shukla_pref', 'vaar_madhyam' => [1], 'varjit' => ['asta'], 'soft' => ['kharmas', 'pitru', 'holashtak'],
            'lagna' => ['bhav' => [7]], 'personal' => ['tribal_soft' => true],
            'abujh' => ['akshaya3', 'devuthani', 'vasant5'],
        ],
        // कृष्ण 1 ग्राह्य; षष्ठी मध्यम; कृष्ण 10 बाद त्याज्य; रवि मध्यम; सूर्य 0,1,2,7,9,10; दशदोष
        '1.11' => [
            'tithi' => ['bad' => [1, 26, 27, 28, 29], 'ok' => [16], 'madhyam' => [6, 21]],
            'vaar_madhyam' => [1],
            'maas' => ['sun' => [0, 1, 2, 7, 9, 10]],
            'varjit' => ['kharmas', 'chaturmas', 'adhik', 'asta', 'holashtak', 'pitru', 'grahan', 'grahan_viddha',
                'dagdha_tithi', 'latta', 'yuti', 'janma_maas', 'janma_nak'],
            'soft' => ['balvriddha', 'simhastha'],
            'bhadra_strict' => true, 'yoga_good' => [2, 4, 5, 16, 23],
            'lagna' => self::LS_STD + ['empty' => [7], 'bad' => ['Venus' => [6], 'Mars' => [8]], 'bhav' => [7]],
            'personal' => ['tribal' => true], 'jyeshtha' => 'pair',
            'abujh' => ['akshaya3', 'devuthani', 'vasant5', 'phulera2', 'bhadli9', 'vijaya10'],
            'note' => 'गोधूलि लग्न (सूर्यास्त ±24 मि.) — शुद्ध लग्न न मिले तब। अन्य दशदोष (पात, वेध, एकार्गल, उपग्रह, क्रान्तिसाम्य) पंचांग से। त्रिबल व "विवाह-लग्न जन्म राशि/लग्न से 8वाँ न हो" ③ में भरे वर-कन्या दोनों से जाँचे जाते हैं; दोनों ज्येष्ठ संतान हों तो ज्येष्ठ मास वर्जित।',
        ],
        // विवाह के बाद 1/3/5 वर्ष; सूर्य कुम्भ, वृश्चिक, मेष; शुक्ल; प्रतिशुक्र, दिशाशूल
        '1.12' => [
            'paksha' => 'shukla_pref', 'maas' => ['sun' => [10, 7, 0]],
            'varjit' => ['kharmas', 'chaturmas', 'asta', 'pratishukra'],
            'lagna' => ['moon_hard' => true], 'options' => ['dir'],
            'note' => 'प्रतिशुक्र दोष नहीं: एक ही नगर/ग्राम में, विवाह के तुरंत बाद की प्रथम विदाई, पिता के घर विपत्ति, तीर्थ यात्रा।',
        ],
        // दाह सूर्योदय-सूर्यास्त; पंचक/त्रिपाद → शांति
        '1.13' => [
            'nature' => 'pitru', 'sado' => 'none', 'time' => 'day', 'lagna' => false, 'shanti_naks' => true,
            'note' => 'ग्रहण, भद्रा, पंचक में भी दाह होता है — शांति-कर्म द्वारा दोष-निवारण। अस्थि-संचय 3रे/4थे/7वें दिन; विसर्जन 10 दिन के भीतर।',
        ],

        // ── 2. घर, जमीन और मकान ─────────────────────────────────────────
        '2.01' => [
            'paksha' => 'shukla_pref', 'chogh' => true, 'soft' => ['pitru'], 'pushkar' => 'good',
            'lagna' => ['empty' => [8, 12], 'bhav' => [4]],
            'options' => ['kray'], 'sell_naks' => [11, 20, 25, 3, 9, 2, 16],
            'abujh' => ['akshaya3', 'dhanteras', 'vijaya10', 'gudi'],
        ],
        // वैशाख श्रावण कार्तिक मार्गशीर्ष फाल्गुन (पौष मु.चि.); सूर्य द्विस्वभाव वर्जित; पंचक; अस्त; खरमास
        '2.02' => [
            'maas' => ['months' => [1, 4, 7, 8, 11], 'months_madhyam' => [9], 'months_bad' => [0, 2, 3, 5, 6, 10]],
            'varjit' => ['sun_dvisvabhava', 'asta', 'panchak', 'kharmas'],
            'lagna' => ['empty' => [8, 12], 'benefic_lagna' => ['Jupiter', 'Venus'], 'bhav' => [4], 'moon_hard' => true],
            'vastu_naag' => true,
        ],
        '2.03' => [
            'maas' => ['months' => [1, 4, 7, 8, 11], 'months_madhyam' => [9], 'months_bad' => [0, 2, 3, 5, 6, 10]],
            'varjit' => ['sun_dvisvabhava', 'asta'], 'soft' => ['panchak'],
            'lagna' => ['empty' => [8, 12], 'bhav' => [4], 'moon_hard' => true],
            'vastu_naag' => true,
            'note' => 'खुदाई ईशान कोण से, दीवार/निर्माण नैऋत्य कोण से आरम्भ (वास्तु मत)। पंचक में छत/लकड़ी का काम न करें।',
        ],
        '2.04' => [
            'soft' => ['panchak'], 'lagna' => self::LS_STD, 'urdhva_bonus' => true,
            'note' => 'प्रथम स्तम्भ प्रायः नैऋत्य कोण से (वास्तु मत)। पंचक लकड़ी के खंभे हेतु विशेष वर्जित।',
        ],
        '2.05' => [
            'soft' => ['panchak'], 'lagna' => self::LS_STD,
            'note' => 'द्वार-चक्र (सूर्य नक्षत्र से गणना) से शुभ/अशुभ फल देखें; चौखट की पूजा कर स्थापना। पंचक में लकड़ी का काम वर्जित।',
        ],
        // नवीन गृह: माघ फाल्गुन वैशाख ज्येष्ठ; कार्तिक मार्गशीर्ष मध्यम; चैत्र आषाढ़ श्रावण भाद्र आश्विन पौष वर्जित
        '2.06' => [
            'vaar_madhyam' => [7], 'bhadra_strict' => true,
            'maas' => ['months' => [10, 11, 1, 2], 'months_madhyam' => [7, 8], 'months_bad' => [0, 3, 4, 5, 6, 9],
                'hard' => true, 'uttarayan' => 'pref'],
            'varjit' => ['kharmas', 'chaturmas', 'adhik', 'asta', 'balvriddha', 'holashtak', 'pitru', 'grahan'],
            'lagna' => self::LS_STD + ['empty' => [4], 'bhav' => [4]],
            'options' => ['griha', 'dwar'], 'old_naks' => [8, 22, 23, 24],
            'abujh' => ['akshaya3', 'vasant5', 'vijaya10', 'gudi'],
            'note' => 'वाम रवि — मुख्य द्वार की दिशा चुनें तो जँचता है (प्रवेश के समय सूर्य बाईं ओर)। कलश-चक्र अलग से देखें। सपूर्व/द्वंद्व प्रवेश में मास-नियम शिथिल; किराए के घर हेतु शुभ दिन पर्याप्त।',
        ],
        '2.07' => [
            'time' => 'morning', 'agnivas' => true, 'varjit' => [...self::M6, 'panchak'],
            'lagna' => self::LS_STD + ['empty' => [4]],
            'note' => 'गृह प्रवेश से एक दिन पहले या उसी दिन प्रातः।',
        ],
        '2.08' => [
            'lagna' => ['bhav' => [4], 'moon_hard' => true], 'nak_bonus' => [20, 24], 'adho_bonus' => true,
            'note' => 'दिशा: ईशान, उत्तर या पूर्व। लग्न/4थ में चंद्र-शुक्र बली, गुरु दृष्टि।',
        ],
        '2.09' => [
            'soft' => ['panchak'], 'lagna' => self::LS_STD,
            'note' => 'पुराना ढाँचा तोड़ने (demolition) हेतु तीक्ष्ण/उग्र नक्षत्र व रिक्ता तिथि ग्राह्य। पंचक में छत डालना वर्जित।',
        ],

        // ── 3. पूजा-पाठ और धार्मिक कार्य ────────────────────────────────
        '3.01' => [
            'paksha' => 'shukla_pref', 'maas' => ['months' => [6, 7, 8, 10, 11, 1, 0, 4]],
            'varjit' => ['adhik', 'guru_asta'], 'lagna' => self::LS_STD + ['bhav' => [9]],
            'abujh' => ['gurupurnima', 'grahan', 'shivratri', 'navratri', 'navratri_chaitra'],
            'note' => 'गुरु की आज्ञा सर्वोपरि। ग्रहण, गुरु पूर्णिमा, महाशिवरात्रि, नवरात्र, सिद्ध-तीर्थ में मुहूर्त-विचार आवश्यक नहीं।',
        ],
        '3.02' => [
            'paksha' => 'shukla_pref', 'maas' => ['months' => [10, 11, 0, 1, 2], 'uttarayan' => 'pref'],
            'varjit' => ['chaturmas', 'kharmas', 'adhik', 'asta', 'grahan'],
            'lagna' => ['empty' => [8], 'moon_hard' => true],
            'options' => ['devata'],
            'note' => 'स्थिर लग्न के साथ स्थिर नवांश भी देखें। शिव-प्रतिष्ठा श्रावण में व देवी-प्रतिष्ठा नवरात्र में ग्राह्य। खंडित मूर्ति का पुनःस्थापन शीघ्र।',
        ],
        '3.03' => [
            'maas' => ['months' => [1, 4, 7, 8, 11], 'months_madhyam' => [9], 'uttarayan' => 'pref'],
            'varjit' => ['sun_dvisvabhava', 'asta'],
            'lagna' => ['empty' => [8, 12], 'bhav' => [9, 4], 'moon_hard' => true],
        ],
        '3.04' => [
            'agnivas' => true, 'lagna' => self::LS_STD, 'options' => ['havan'],
            'note' => 'आहुति-मुख (सूर्य नक्षत्र से चंद्र नक्षत्र गणना) भी देखें। सामान्य पाठ (सुंदरकांड, गीता) हेतु शुभ दिन पर्याप्त।',
        ],
        '3.05' => [
            'paksha' => 'shukla_pref', 'time' => 'brahma', 'varjit' => ['guru_asta'],
            'lagna' => ['moon_hard' => true, 'bhav' => [5, 9]],
            'abujh' => ['navratri', 'navratri_chaitra', 'grahan', 'shivratri', 'holi', 'diwali', 'pushya_guru', 'pushya_ravi'],
            'note' => 'देवता-विशेष की तिथि प्रधान (शक्ति 8/9, शिव 14, गणेश 4)। ग्रहण काल सिद्धि-काल है — मुहूर्त विचार नहीं।',
        ],
        '3.06' => [
            'time' => 'pradosh_morning', 'shivvas' => true, 'lagna' => false,
            'abujh' => ['shravan_somvar', 'shivratri', 'pradosh', 'masik_shivratri'],
            'note' => 'श्रावण मास, महाशिवरात्रि, प्रदोष, ज्योतिर्लिंग/तीर्थ व निष्काम पूजा में शिववास नहीं देखा जाता।',
        ],
        '3.07' => [
            'options' => ['vrat'], 'varjit' => ['asta', 'adhik', 'kharmas'], 'soft' => ['chaturmas'], 'lagna' => false,
            'note' => 'एकादशी व्रत उत्पन्ना एकादशी (मार्गशीर्ष कृष्ण 11) से; सोलह सोमवार श्रावण/कार्तिक/चैत्र/मार्गशीर्ष के प्रथम सोमवार से; संतोषी माता शुक्ल पक्ष के प्रथम शुक्रवार से।',
        ],
        '3.08' => [
            'strict' => ['v'], 'nature' => 'upay', 'sado' => 'relaxed', 'time' => 'evening', 'hora' => ['Saturn'], 'paksha' => 'none',
            'lagna' => false, 'abujh' => ['shani_amavasya', 'shani_pradosh'],
            'note' => 'साढ़ेसाती/ढैया में। शनि-राहु-केतु हेतु कृष्ण पक्ष/अमावस्या भी ग्राह्य।',
        ],
        '3.09' => [
            'strict' => ['v'], 'nature' => 'upay', 'sado' => 'relaxed', 'time' => 'rahukaal', 'paksha' => 'none', 'lagna' => false,
            'abujh' => ['grahan'],
            'note' => 'राहुकाल में राहु-पूजा (अपवाद — अन्य कार्यों में राहुकाल वर्जित)।',
        ],
        '3.10' => [
            'strict' => ['v'], 'nature' => 'upay', 'sado' => 'relaxed', 'time' => 'morning', 'paksha' => 'none', 'lagna' => false,
            'abujh' => ['nagpanchami', 'shivratri', 'shravan', 'amavasya'],
            'note' => 'स्थान: त्र्यंबकेश्वर, उज्जैन आदि (परंपरा)। कालसर्प योग शास्त्रीय ग्रंथों में वर्णित नहीं — परवर्ती मान्यता।',
        ],
        '3.11' => [
            'strict' => ['v'], 'nature' => 'pitru', 'sado' => 'relaxed', 'time' => 'kutup', 'paksha' => 'krishna', 'lagna' => false,
            'amavasya_good' => true, 'abujh' => ['pitru_paksha', 'sarvapitru'],
            'note' => 'पितृ कार्यों में शुभ-मुहूर्त नियम उलटे — अमावस्या, कृष्ण पक्ष, अपराह्न शुभ।',
        ],
        '3.12' => [
            'options' => ['graha'], 'lagna' => ['moon_hard' => true],
            'abujh' => ['pushya_ravi', 'pushya_guru', 'grahan', 'diwali', 'navratri', 'holi'],
            'note' => 'प्राण-प्रतिष्ठा के बाद धारण। रक्षा-कवच (अभिचार-निवारण) हेतु मंगल/शनि वार ग्राह्य।',
        ],

        // ── 4. षट्कर्म (केवल शास्त्रीय मुहूर्त-संदर्भ) ──────────────────────
        '4.01' => ['strict' => ['v'], 'nature' => 'shatkarma', 'sado' => 'none', 'paksha' => 'shukla_pref', 'time' => 'ritu:5', 'lagna' => false, 'ritu' => 5],
        '4.02' => ['strict' => ['v'], 'nature' => 'shatkarma', 'sado' => 'none', 'paksha' => 'shukla_pref', 'time' => 'purvahna', 'lagna' => false],
        '4.03' => ['strict' => ['v'], 'nature' => 'shatkarma', 'sado' => 'none', 'paksha' => 'shukla_pref', 'time' => 'ritu:1', 'lagna' => false, 'ritu' => 1],
        '4.04' => ['strict' => ['v'], 'nature' => 'shatkarma', 'sado' => 'none', 'paksha' => 'none', 'time' => 'ritu:6', 'lagna' => false, 'ritu' => 6],
        '4.05' => ['strict' => ['v', 't'], 'nature' => 'shatkarma', 'sado' => 'none', 'paksha' => 'krishna', 'time' => 'ritu:2', 'lagna' => false, 'ritu' => 2, 'bhadra_ok' => true],
        '4.06' => ['strict' => ['v', 't'], 'nature' => 'shatkarma', 'sado' => 'none', 'paksha' => 'krishna', 'time' => 'ritu:3', 'lagna' => false, 'ritu' => 3, 'bhadra_ok' => true],
        '4.07' => ['strict' => ['v', 't'], 'nature' => 'shatkarma', 'sado' => 'none', 'paksha' => 'krishna', 'time' => 'ritu:4', 'lagna' => false, 'ritu' => 4, 'bhadra_ok' => true],

        // ── 5. कारोबार, नौकरी और पैसा ───────────────────────────────────
        '5.01' => [
            'chogh' => true, 'hora' => ['Mercury', 'Jupiter'],
            'lagna' => ['empty' => [8, 12], 'bhav' => [2, 10, 11, 7], 'moon_hard' => true],
            'options' => ['vyapar'],
            'abujh' => ['akshaya3', 'dhanteras', 'diwali', 'labh5', 'vijaya10', 'gudi', 'pushya_guru'],
        ],
        '5.02' => [
            'chogh' => true, 'lagna' => self::LS_STD + ['bhav' => [10, 6]], 'options' => ['naukri'],
            'note' => 'नियोक्ता ने तिथि तय की हो तो उस दिन केवल अच्छा लग्न/चौघड़िया/होरा चुनें।',
        ],
        '5.03' => [
            'time' => 'pradosh', 'chogh' => true, 'tithi' => ['bad' => [30]],
            'lagna' => ['bhav' => [2, 11]],
            'abujh' => ['diwali', 'dhanteras', 'labh5', 'akshaya3', 'gudi', 'vijaya10', 'govardhan'],
            'note' => 'अमावस्या केवल दीपावली पर अपवादस्वरूप शुभ — प्रदोष काल + स्थिर लग्न (वृषभ/सिंह)।',
        ],
        '5.04' => [
            'varjit' => ['ravi_hasta', 'vriddhi_yoga', 'pushkar', 'sankranti_day'],
            'lagna' => ['bhav_malefic_bad' => [6]],
            'note' => 'चर लग्न में लिया ऋण शीघ्र चुकता हो; ध्रुव नक्षत्र में लिया ऋण लंबा चलता है। बैंक-लोन जैसे आवश्यक कार्यों में कम से कम मंगलवार व स्थिर लग्न टालें। बुधवार को उधार देना वर्जित (लोक)।',
        ],
        '5.05' => ['sado' => 'none', 'hora' => ['Mars'], 'pushkar' => 'good', 'lagna' => false,
            'note' => 'मंगलवार/मंगल होरा में ऋण चुकाना श्रेष्ठ।'],
        '5.06' => [
            'pushkar' => 'good', 'yoga_good' => [11], 'lagna' => ['bhav' => [2, 11]],
            'abujh' => ['dhanteras', 'akshaya3', 'pushya_guru'],
            'note' => 'अल्पकालिक ट्रेडिंग हेतु चर लग्न/क्षिप्र नक्षत्र (मत)।',
        ],
        '5.07' => [
            'chogh' => true, 'hora' => ['Mercury'], 'lagna' => ['bhav' => [7, 11]],
            'options' => ['kray'], 'sell_naks' => [11, 20, 25, 3, 9, 2, 16],
        ],
        '5.08' => ['soft' => ['budh_asta', 'budh_vakri'], 'lagna' => ['bhav' => [3, 7], 'moon_hard' => true]],
        '5.09' => ['lagna' => self::LS_STD + ['bhav' => [6]]],

        // ── 6. गहने और कपड़े ─────────────────────────────────────────────
        '6.01' => [
            'pushkar' => 'good', 'tithi' => ['bad' => [30]], 'lagna' => ['bhav' => [2, 11], 'svabhav_pref' => 'sthir'],
            'options' => ['dhatu'],
            'abujh' => ['dhanteras', 'akshaya3', 'vijaya10'],
            'note' => 'दीपावली अमावस्या अपवाद। पुष्य नक्षत्र (विशेषकर दीपावली से पूर्व) उत्तम।',
        ],
        '6.02' => ['lagna' => self::LS_STD, 'note' => 'विवाह, उत्सव, गुरु/पति द्वारा दिए आभूषण हेतु मुहूर्त आवश्यक नहीं।'],
        '6.03' => ['sado' => ['bhadra'], 'lagna' => false,
            'note' => 'उत्सव, विवाह, राजा/गुरु/ब्राह्मण द्वारा दिया वस्त्र — नक्षत्र-वार दोष नहीं।'],

        // ── 7. रत्न धारण — ग्रह का वार + होरा + नक्षत्र; ग्रह अस्त/नीच न हो ──
        '7.01' => ['strict' => ['v'], 'time' => 'day_morning', 'nakPenalty' => -8, 'gem' => 'Sun', 'hora' => ['Sun'], 'hora_strict' => true, 'paksha' => 'shukla_pref', 'varjit' => ['graha_asta'], 'lagna' => false],
        '7.02' => ['strict' => ['v'], 'time' => 'day_morning', 'nakPenalty' => -8, 'gem' => 'Moon', 'hora' => ['Moon'], 'hora_strict' => true, 'paksha' => 'shukla_pref', 'varjit' => ['graha_asta'], 'lagna' => false, 'purnima_good' => true],
        '7.03' => ['strict' => ['v'], 'time' => 'day_morning', 'nakPenalty' => -8, 'gem' => 'Mars', 'hora' => ['Mars'], 'hora_strict' => true, 'paksha' => 'shukla_pref', 'varjit' => ['graha_asta'], 'lagna' => false],
        '7.04' => ['strict' => ['v'], 'time' => 'day_morning', 'nakPenalty' => -8, 'gem' => 'Mercury', 'hora' => ['Mercury'], 'hora_strict' => true, 'paksha' => 'shukla_pref', 'varjit' => ['graha_asta'], 'lagna' => false],
        '7.05' => ['strict' => ['v'], 'time' => 'day_morning', 'nakPenalty' => -8, 'gem' => 'Jupiter', 'hora' => ['Jupiter'], 'hora_strict' => true, 'paksha' => 'shukla_pref', 'varjit' => ['graha_asta'], 'lagna' => false],
        '7.06' => ['strict' => ['v'], 'time' => 'day_morning', 'nakPenalty' => -8, 'gem' => 'Venus', 'hora' => ['Venus'], 'hora_strict' => true, 'paksha' => 'shukla_pref', 'varjit' => ['graha_asta'], 'lagna' => false],
        '7.07' => ['strict' => ['v'], 'time' => 'evening', 'nakPenalty' => -8, 'gem' => 'Saturn', 'hora' => ['Saturn'], 'hora_strict' => true, 'paksha' => 'none', 'varjit' => ['graha_asta'], 'lagna' => false],
        '7.08' => ['strict' => ['v'], 'nakPenalty' => -8, 'gem' => 'Rahu', 'hora' => ['Saturn'], 'hora_strict' => true, 'paksha' => 'none', 'lagna' => false],
        '7.09' => ['strict' => ['v'], 'nakPenalty' => -8, 'gem' => 'Ketu', 'hora' => ['Mars'], 'hora_strict' => true, 'paksha' => 'none', 'lagna' => false],

        // ── 8. सफर और गाड़ी ──────────────────────────────────────────────
        // शुक्ल प्रतिपदा … वर्जित (कृष्ण 1 ग्राह्य); मध्यम नक्षत्र; दिशाशूल, नक्षत्रशूल, योगिनी, जन्म नक्षत्र
        '8.01' => [
            'travel' => true, 'tithi' => ['ok' => [16]], 'nak_madhyam' => [4, 12, 21, 26, 11, 20, 25, 18, 19, 24],
            'chogh' => 'char', 'varjit' => ['janma_nak'], 'ghat' => true,
            'lagna' => self::LS_STD + ['bhav' => [7, 9]], 'options' => ['dir'],
            'abujh' => ['vijaya10'],
            'note' => 'अनिवार्य यात्रा में दिशाशूल-परिहार (वार अनुसार वस्तु खाकर निकलें) या एक दिन पहले प्रस्थान रख दें।',
        ],
        '8.02' => [
            'travel' => true, 'chogh' => 'char', 'varjit' => ['janma_nak'], 'ghat' => true,
            'lagna' => self::LS_STD + ['bhav' => [9, 12]], 'options' => ['dir'], 'moon_char_bonus' => true,
            'note' => 'जलयात्रा (समुद्र) हेतु जल-राशि लग्न व चंद्र बली।',
        ],
        '8.03' => [
            'travel' => true, 'sado' => 'relaxed', 'lagna' => ['bhav' => [9]], 'options' => ['dir'],
            'note' => 'नैमित्तिक तीर्थ (ग्रहण स्नान, कुम्भ, पर्व-स्नान) में मुहूर्त विचार नहीं।',
        ],
        '8.04' => [
            'lagna' => self::LS_STD + ['bhav' => [4]], 'options' => ['kray'], 'sell_naks' => [11, 20, 25, 3, 2, 9, 16],
            'abujh' => ['dhanteras', 'vijaya10', 'akshaya3', 'gudi'],
        ],
        '8.05' => ['travel' => true, 'soft' => ['panchak'], 'lagna' => ['bhav' => [4], 'moon_hard' => true], 'options' => ['dir']],

        // ── 9. सेहत और इलाज ─────────────────────────────────────────────
        '9.01' => ['lagna' => ['bhav_malefic_good' => [6], 'moon_hard' => true],
            'note' => 'जड़ी-बूटी संग्रह हेतु पुष्य नक्षत्र विशेष (आयुर्वेद परंपरा)।'],
        '9.02' => ['lagna' => ['moon_hard' => true],
            'note' => 'आपातकाल में मुहूर्त-विचार नहीं (आपत्काले न विचारः) — चिकित्सक की सलाह सर्वोपरि।'],
        '9.03' => [
            'nature' => 'ugra', 'sado' => ['bhadra', 'grahan'], 'varjit' => ['chandrashtam'], 'ghat' => true, 'options' => ['ang'],
            'lagna' => ['eighth_hard' => true, 'moon_bad' => [8]],
            'note' => 'आपातकालीन ऑपरेशन में मुहूर्त न देखें — चिकित्सक की सलाह सर्वोपरि। मंगल बली हो। "चंद्र उस अंग की राशि में न हो" नियम पाश्चात्य मूल का।',
        ],
        '9.04' => [
            'nature' => 'janma', 'tithi' => ['bad' => [29, 30], 'neutral' => [14]],
            'sado' => ['bhadra', 'vyatipat', 'vaidhriti', 'sankranti', 'gandant', 'grahan'],
            'lagna' => ['moon_hard' => true, 'lagnesh_hard' => true, 'jup_aspect' => [1, 5], 'benefic_kendra_bonus' => true],
            'note' => 'माँ व शिशु की चिकित्सकीय सुरक्षा सर्वोपरि — ज्योतिषीय समय केवल चिकित्सक की स्वीकृत खिड़की में (वही तिथि-सीमा यहाँ भरें)।',
        ],
        '9.05' => ['sado' => ['bhadra'], 'nature' => 'ugra', 'lagna' => false],

        // ── 10. खेती-बाड़ी और पशु ────────────────────────────────────────
        '10.01' => ['tiryak_bonus' => true, 'lagna' => ['bhav' => [4], 'moon_hard' => true],
            'note' => 'हल-चक्र (सूर्य नक्षत्र से गणना) भी देखें।'],
        '10.02' => ['paksha' => 'shukla_pref', 'lagna' => ['moon_hard' => true], 'options' => ['fasal']],
        '10.03' => ['maas' => ['months_pref' => [4], 'sun_pref' => [3, 4]], 'lagna' => ['moon_hard' => true]],
        '10.04' => ['sado' => ['bhadra'], 'paksha' => 'none', 'lagna' => false],
        '10.05' => ['lagna' => ['bhav' => [2, 4]]],
        '10.06' => ['lagna' => ['bhav' => [4]], 'note' => 'गौ-क्रय में अष्टमी विशेष वर्जित (मत)।'],

        // ── 11. कोर्ट-कचहरी और राजनीति ──────────────────────────────────
        '11.01' => [
            'maas' => ['uttarayan' => 'pref'], 'varjit' => ['asta'],
            'lagna' => ['eighth_hard' => true, 'bhav' => [10], 'leo_bonus' => true, 'moon_hard' => true],
        ],
        '11.02' => [
            'nature' => 'ugra', 'sado' => ['rahukaal', 'bhadra_soft'], 'paksha' => 'none', 'ghat' => true,
            'lagna' => ['bhav_malefic_good' => [6]],
        ],
        '11.03' => ['lagna' => ['bhav' => [7], 'jup_aspect' => [1, 7]], 'personal' => ['tara_mitra' => true]],
        '11.04' => ['lagna' => ['bhav' => [3]], 'abujh' => ['vijaya10']],

        // ── 12. रोजमर्रा व हुनर ──────────────────────────────────────────
        '12.01' => ['lagna' => ['bhav' => [5]], 'abujh' => ['vasant5', 'vijaya10', 'gurupurnima']],
        '12.02' => ['varjit' => ['budh_asta', 'guru_asta'], 'lagna' => ['bhav' => [3, 9]],
            'abujh' => ['vasant5', 'akshaya3', 'vijaya10']],
        '12.03' => [
            'nature' => 'daily', 'sado' => 'none', 'time' => 'day_no_sandhya', 'vaar_madhyam' => [5],
            'varjit' => ['janma_nak', 'pitru'], 'paksha' => 'none', 'lagna' => false, 'no_birthday' => true,
            'note' => 'भोजन के बाद, जन्मदिन व यात्रा के दौरान भी वर्जित। राजाज्ञा, यज्ञ-दीक्षा, विवाह, तीर्थ, मृतक-कर्म, संस्कार-मुंडन में मुहूर्त-विचार नहीं।',
        ],
        '12.04' => [
            'nature' => 'daily', 'sado' => 'none', 'varjit' => ['sankranti_day', 'pitru'], 'paksha' => 'none', 'lagna' => false,
            'note' => 'परिहार: रवि को तेल में पुष्प, गुरु को दूर्वा, मंगल को मिट्टी, शुक्र को गोमय मिलाकर — दोष नहीं; सरसों/सुगंधित तेल व रोग-उपचार हेतु मालिश में नियम नहीं।',
        ],
        '12.05' => ['lagna' => ['bhav' => [4]], 'options' => ['dastavej']],
    ];

    /** उपयोगकर्ता-विकल्प — कूट => [लेबल, [मान => नाम]]। पहला मान पहले से चुना। */
    public const OPTIONS = [
        'dir' => ['यात्रा / निकास की दिशा', [
            '' => '— दिशा चुनें (वैकल्पिक) —', 'पूर्व' => 'पूर्व', 'आग्नेय' => 'आग्नेय (दक्षिण-पूर्व)', 'दक्षिण' => 'दक्षिण',
            'नैऋत्य' => 'नैऋत्य (दक्षिण-पश्चिम)', 'पश्चिम' => 'पश्चिम', 'वायव्य' => 'वायव्य (उत्तर-पश्चिम)',
            'उत्तर' => 'उत्तर', 'ईशान' => 'ईशान (उत्तर-पूर्व)']],
        'kray' => ['क्रय या विक्रय', ['buy' => 'खरीदना (क्रय)', 'sell' => 'बेचना (विक्रय)']],
        'griha' => ['घर किस प्रकार का', ['new' => 'नया घर (अपूर्व प्रवेश)', 'old' => 'पुराना / जीर्णोद्धार (द्वंद्व)', 'return' => 'यात्रा से लौटकर (सपूर्व)', 'rent' => 'किराए का घर']],
        'dhatu' => ['धातु', ['gold' => 'सोना', 'silver' => 'चाँदी']],
        'fasal' => ['फसल', ['upar' => 'धरती के ऊपर उगने वाली', 'kand' => 'कंद-मूल (आलू, प्याज़ …)']],
        'naukri' => ['नौकरी का प्रकार', ['niji' => 'निजी / सामान्य', 'sarkari' => 'सरकारी पद']],
        'vyapar' => ['व्यापार का प्रकार', ['samanya' => 'सामान्य', 'loha' => 'लोहा, तेल, मशीनरी, श्रम-आधारित']],
        'havan' => ['हवन का प्रकार', ['kamya' => 'काम्य (मनोकामना हेतु)', 'nitya' => 'नित्य / संस्कार / पर्व हवन']],
        'vrat' => ['व्रत', ['arambh' => 'नया व्रत शुरू करना', 'udyapan' => 'उद्यापन']],
        'devata' => ['देवता', ['' => 'सामान्य', 'ganesh' => 'गणेश', 'surya' => 'सूर्य', 'durga' => 'दुर्गा / देवी', 'vishnu' => 'विष्णु', 'shiva' => 'शिव', 'hanuman' => 'हनुमान']],
        'graha' => ['यंत्र / ताबीज किस ग्रह का', ['' => 'सामान्य', 'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध', 'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि']],
        'dastavej' => ['दस्तावेज़', ['registry' => 'रजिस्ट्री (क्रय)', 'vasiyat' => 'वसीयत']],
        'garbh' => ['कौन-सा संस्कार', ['godbharai' => 'गोद भराई (लोक-रीति — 7वाँ मास)', 'pumsavan' => 'पुंसवन (2रा-3रा मास)', 'simanta' => 'सीमन्तोन्नयन (6ठा / 8वाँ मास)']],
        'dwar' => ['मुख्य द्वार किस दिशा में (वाम रवि)', ['' => '— चुनें (वैकल्पिक) —', 'पूर्व' => 'पूर्वमुखी', 'उत्तर' => 'उत्तरमुखी', 'पश्चिम' => 'पश्चिममुखी', 'दक्षिण' => 'दक्षिणमुखी']],
        'ang' => ['ऑपरेशन का अंग (चंद्र उसकी राशि में न हो)', ['' => '— चुनें (वैकल्पिक) —', 'sir' => 'सिर, मस्तिष्क, आँख (मेष)',
            'gala' => 'चेहरा, गला, दाँत, थायरॉइड (वृषभ)', 'bhuja' => 'कंधे, बाँह, हाथ, फेफड़े (मिथुन)', 'chhati' => 'छाती, स्तन, आमाशय (कर्क)',
            'hriday' => 'हृदय, पीठ, रीढ़ (सिंह)', 'pet' => 'पेट, आँतें, पाचन (कन्या)', 'gurde' => 'गुर्दे, कमर, त्वचा (तुला)',
            'guhya' => 'जननांग, मूत्राशय (वृश्चिक)', 'jangh' => 'जाँघ, कूल्हा, यकृत (धनु)', 'ghutne' => 'घुटने, हड्डियाँ, जोड़ (मकर)',
            'pindli' => 'पिंडली, टखने (कुम्भ)', 'pair' => 'पैर, तलवे (मीन)']],
        'varna' => ['उपनयन की आयु-परंपरा', ['brahmin' => 'ब्राह्मण — गर्भ से 8वाँ वर्ष', 'kshatriya' => 'क्षत्रिय — 11वाँ वर्ष', 'vaishya' => 'वैश्य — 12वाँ वर्ष', 'samanya' => 'आयु-नियम न देखें']],
    ];

    /** देवता की निज तिथि (1–30) — इसमें रिक्ता/वर्जित तिथि का दोष नहीं (3.02)। */
    public const DEVATA_TITHI = [
        'ganesh' => [4, 19], 'surya' => [7, 22], 'durga' => [8, 9, 23, 24], 'vishnu' => [11, 12, 26, 27],
        'shiva' => [29], 'hanuman' => [15],
    ];

    /** एक कार्य की व्याख्या (न हो तो खाली = सब सामान्य)। */
    public static function rule(string $id): array
    {
        return self::TASKS[$id] ?? [];
    }
}
