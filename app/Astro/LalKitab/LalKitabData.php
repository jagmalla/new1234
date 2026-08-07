<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\LalKitab;

/**
 * Baked Lal Kitab (लाल किताब) knowledge bank.
 *
 * The bulky text banks (planet-in-house remedies, prediction sutras, parental
 * debts, Sade-Sati / Dhaiya remedies, manglik remedies, conjunction remedies,
 * house subjects and planet classifications) live in the sibling
 * {@see lalkitab_data.json} file — extracted from the owner's Lal Kitab source
 * workbook (39 sheets). This class loads + caches that JSON and exposes the
 * fixed Lal Kitab constants (the chart is a fixed-Aries teva: house 1 is always
 * Aries and the twelve house-lords never change).
 *
 * Edit the JSON to change any predictive text; this file only holds structure.
 */
final class LalKitabData
{
    /** Fixed Lal Kitab house lords — house 1 is always Aries, so lords are fixed. */
    public const HOUSE_LORD = [
        1 => 'Mars', 2 => 'Venus', 3 => 'Mercury', 4 => 'Moon',
        5 => 'Sun', 6 => 'Mercury', 7 => 'Venus', 8 => 'Mars',
        9 => 'Jupiter', 10 => 'Saturn', 11 => 'Saturn', 12 => 'Jupiter',
    ];

    /** Fixed Lal Kitab sign of each house (Aries..Pisces), 0-based sign index. */
    public const HOUSE_SIGN = [
        1 => 0, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5,
        7 => 6, 8 => 7, 9 => 8, 10 => 9, 11 => 10, 12 => 11,
    ];

    /** English planet key => Hindi display name. */
    public const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चन्द्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि',
        'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];

    /** English sign key (Charts::SIGNS) => Hindi rashi name. */
    public const SIGN_HI = [
        'Aries' => 'मेष', 'Taurus' => 'वृष', 'Gemini' => 'मिथुन', 'Cancer' => 'कर्क',
        'Leo' => 'सिंह', 'Virgo' => 'कन्या', 'Libra' => 'तुला', 'Scorpio' => 'वृश्चिक',
        'Sagittarius' => 'धनु', 'Capricorn' => 'मकर', 'Aquarius' => 'कुम्भ', 'Pisces' => 'मीन',
    ];

    /** Exaltation sign index per planet (Lal Kitab / classical). */
    public const EXALT = [
        'Sun' => 0, 'Moon' => 1, 'Mars' => 9, 'Mercury' => 5,
        'Jupiter' => 3, 'Venus' => 11, 'Saturn' => 6, 'Rahu' => 1, 'Ketu' => 7,
    ];

    /** Debilitation sign index per planet. */
    public const DEBIL = [
        'Sun' => 6, 'Moon' => 7, 'Mars' => 3, 'Mercury' => 11,
        'Jupiter' => 9, 'Venus' => 5, 'Saturn' => 0, 'Rahu' => 7, 'Ketu' => 1,
    ];

    /**
     * पक्का घर — each planet's own "pukka" house(s) in the Lal Kitab teva. A
     * planet sitting in its pukka ghar gives its results with full force and
     * added stability (सूर्य-1, चन्द्र-4, मंगल-3/8, बुध-7, गुरु-9, शुक्र-7,
     * शनि-10, राहु-12, केतु-6).
     */
    public const PUKKA_GHAR = [
        'Sun' => [1], 'Moon' => [4], 'Mars' => [3, 8], 'Mercury' => [7],
        'Jupiter' => [9], 'Venus' => [7], 'Saturn' => [10], 'Rahu' => [12], 'Ketu' => [6],
    ];

    /**
     * House-based dignity — the Lal Kitab teva is fixed (house 1 is always मेष),
     * so a planet's उच्च / नीच / स्वगृही standing is read from the *house* it sits
     * in, never from its real transiting rashi. Confirmed by the owner, and
     * corroborated by the bank's own भंग rules, which are all stated in houses
     * ("सूर्य मेष में हो और सामने सातवें भाव (तुला) में …").
     *
     * नीच is the seventh house from उच्च, which holds for all nine planets.
     * Confirmed against Farman 4 of the source book (house 1: Sun उच्च, Saturn
     * नीच, Mars घर-ग्रह; house 6: Mercury & Rahu उच्च; house 7: Saturn उच्च,
     * Sun नीच, Venus घर-ग्रह — all match).
     */
    public const UCH_BHAV = [
        'Sun' => [1], 'Moon' => [2], 'Mars' => [10], 'Mercury' => [6], 'Jupiter' => [4],
        'Venus' => [12], 'Saturn' => [7], 'Rahu' => [3, 6], 'Ketu' => [9, 12],
    ];

    /** Debilitation house(s) — primary tier (the seventh from exaltation). */
    public const NEECH_BHAV = [
        'Sun' => [7], 'Moon' => [8], 'Mars' => [4], 'Mercury' => [12], 'Jupiter' => [10],
        'Venus' => [6], 'Saturn' => [1], 'Rahu' => [9, 12], 'Ketu' => [3, 6],
    ];

    /**
     * Secondary (गौण) debilitation houses — the owner's "मुख्यतः" note marks a
     * milder second tier for the shadow planets; these carry half weight.
     */
    public const NEECH_BHAV_SECONDARY = [
        'Rahu' => [8, 11], 'Ketu' => [8],
    ];

    /**
     * उच्च-भंग — a planet counted उच्च here still gives no auspicious result.
     * Rahu in the 6th and Ketu in the 12th mirror each other, so this reads as
     * deliberate, not an error (their भंग rules both say "शुभ फल नहीं")।
     * @var array<string,list<int>>
     */
    public const UCH_BHANG = [
        'Rahu' => [6], 'Ketu' => [12],
    ];

    /** Own-sign house(s) — the house whose fixed rashi this planet rules. */
    public const SWA_BHAV = [
        'Sun' => [5], 'Moon' => [4], 'Mars' => [1, 8], 'Mercury' => [3, 6], 'Jupiter' => [9, 12],
        'Venus' => [2, 7], 'Saturn' => [10, 11], 'Rahu' => [12], 'Ketu' => [6],
    ];

    /**
     * पक्का घर — a house's कारक planet, which is that planet's pukka ghar; the two
     * are one table rather than two. Sitting here means the result arrives at full
     * force and stability — for a benefic *and* for a malefic alike, so this is a
     * measure of intensity, not of auspiciousness.
     */
    public const KARAK_BHAV = [
        'Sun' => [1], 'Moon' => [4], 'Mars' => [3, 8], 'Mercury' => [6, 7],
        'Jupiter' => [2, 5, 9, 11, 12], 'Venus' => [7], 'Saturn' => [8, 10],
        'Rahu' => [12], 'Ketu' => [6],
    ];

    /** कच्चा घर — supplied by the owner as an independent list, not derivable. */
    public const KACHCHA_BHAV = [
        'Sun' => [4, 7], 'Moon' => [7, 12], 'Mars' => [4], 'Mercury' => [9, 12],
        'Jupiter' => [8, 12], 'Venus' => [1, 9], 'Saturn' => [4, 12],
        'Rahu' => [1, 4, 8], 'Ketu' => [4, 10],
    ];

    /** केन्द्र भाव (खाली मुट्ठी) — फरमान 14: चारों खाली हों तो टेवा नाबालिग। */
    public const KENDRA = [1, 4, 7, 10];

    /**
     * फरमान 14 — टेवे की किस्में. शत्रु-युगल विशेष रूप से "अंधा टेवा" नियम के लिए
     * (आपस में शत्रु दो ग्रह एक ही भाव — खासकर भाव 10 — में). यह सूची पुस्तक में
     * इसी नियम हेतु दी गई है और सामान्य स्थायी-मैत्री (bank → maitri) से जान-बूझकर
     * अलग है, इसलिए इसे अलग रखा गया है। key => उसके शत्रु ग्रह; कोड में दोनों दिशा
     * से जाँच होती है (बाहम = परस्पर)।
     */
    public const TEVA_SHATRU_YUGAL = [
        'Mercury' => ['Jupiter', 'Moon', 'Mars'],
        'Venus'   => ['Jupiter', 'Moon', 'Rahu'],
        'Saturn'  => ['Sun', 'Moon', 'Mars'],
        'Rahu'    => ['Sun', 'Moon', 'Mars', 'Jupiter'],
        'Ketu'    => ['Sun', 'Moon', 'Mars'],
    ];

    /**
     * फरमान 14 — पूरे टेवे की किस्म का फल व उपाय. दर्जा: final = पुस्तक की स्पष्ट
     * पंक्ति; anumanit = पंक्ति का सर्वोत्तम पाठ (अर्थ थोड़ा व्याख्या-सापेक्ष)।
     */
    public const TEVA_KISM = [
        'ANDHA' => [
            'naam' => 'अंधा टेवा',
            'phal' => 'भाव 10 में परस्पर शत्रु ग्रहों की युति से पूरा टेवा "अंधा" हो जाता है — फल दबे, भ्रमित व धोखा देने वाले रहते हैं; केवल एक ग्रह/रेखा देखकर निष्कर्ष नहीं निकलता।',
            'upay' => [
                'अंधे ग्रहों के मंदे समय में केतु का उपाय करें — केतु की वस्तु/पुत्र के माध्यम से, या सुबह सादिक (भोर) के समय किए कार्य फलदायी रहते हैं।',
                'एक ही समय पर 10 अंधों/असहायों को भोजन बाँटने से भाव 10 का "ज़हर" दूर होता है।',
            ],
            'darja' => 'final',
        ],
        'ADHA_ANDHA' => [
            'naam' => 'आधा-अंधा (नुहराता) टेवा',
            'phal' => 'शनि सप्तम व सूर्य चतुर्थ होने पर टेवा "आधा-अंधा / नुहराता" रहता है — फल आधे-अधूरे व धुँधले मिलते हैं।',
            'upay' => [
                'नुहराते टेवे में नेक ग्रहों से जुड़े काम रात के समय और मंदे ग्रहों से जुड़े काम दिन के समय करना मददगार रहता है।',
            ],
            'darja' => 'anumanit',
        ],
        'BALIG' => [
            'naam' => 'बालिग टेवा',
            'phal' => 'बुध षष्ठ व सूर्य 1/5/11 में होने पर टेवा "बालिग" (वयस्क) होता है — फल स्वतः पूरी ताकत से मिलता रहता है, किसी विशेष प्रयास की ज़रूरत नहीं।',
            'upay' => [],
            'darja' => 'final',
        ],
        'NABALIG' => [
            'naam' => 'नाबालिग टेवा',
            'phal' => 'केन्द्र (1·4·7·10 = खाली मुट्ठी) खाली हों, या बुध किसी पापी (राहु/केतु) के साथ हो, तो टेवा "नाबालिग" रहता है — ग्रह-चाली हालत में यह सारी उम्र नाबालिग ही गिना जाता है; अकेले पूरा फल नहीं देता।',
            'upay' => [
                'नाबालिग टेवे वाले को दूसरों की मदद व सहारा लेकर चलना मददगार रहता है।',
            ],
            'darja' => 'final',
        ],
        'DHARMI' => [
            'naam' => 'धर्मी टेवा',
            'phal' => 'शनि-गुरु की युति, या चन्द्र का 10/4 में होना — पिछले जन्म का साधु/धर्मी योग बनाता है। धर्मी टेवा वाला हर एक के लिए मददगार व सुख देने वाला होता है।',
            'upay' => [],
            'darja' => 'anumanit',
        ],
        'GURU_SHUKRA' => [
            'naam' => 'गुरु-शुक्र मुश्तरका टेवा',
            'phal' => 'गुरु व शुक्र की युति होने पर जातक अपना वर्तमान जन्म स्वयं जीता है — 12 वर्ष की उम्र के बाद पिछले जन्मों के धोखे या धक्के का कोई डर नहीं रहता।',
            'upay' => [],
            'darja' => 'final',
        ],
    ];

    /**
     * फरमान 14 — मर्द/औरत का टेवा किसका प्रबल (सूचनात्मक; गणना नहीं, क्योंकि लिंग व
     * वैवाहिक स्थिति इनपुट में नहीं होती)।
     */
    public const TEVA_LING_NIYAM = 'मर्द का टेवा औरत के टेवे को ढाँप लेता है। औरत विवाह से पहले अपने टेवे पर चलती है; विवाह के समय से मर्द का टेवा दोनों की किस्मत पर हावी हो जाता है। मर्द का साथ छूटने (मृत्यु/अलगाव) पर औरत का अपना टेवा पुनः बहाल हो जाता है। बच्चा जब तक छोटा है, माता-पिता की किस्मत व पिछले कर्म मददगार रहते हैं।';

    /**
     * किस ग्रह भाव-अनुसार किन ग्रहों को बल देता है। (per planet => house => list)
     * फरमान 13 — बृहस्पति: भाव 1–5 व 12 → शनि व सूर्य; भाव 6–11 → केवल शनि।
     */
    public const HELPS_BY_HOUSE = [
        'Jupiter' => [
            1 => ['Saturn', 'Sun'], 2 => ['Saturn', 'Sun'], 3 => ['Saturn', 'Sun'],
            4 => ['Saturn', 'Sun'], 5 => ['Saturn', 'Sun'],
            6 => ['Saturn'], 7 => ['Saturn'], 8 => ['Saturn'], 9 => ['Saturn'],
            10 => ['Saturn'], 11 => ['Saturn'],
            12 => ['Saturn', 'Sun'],
        ],
    ];

    /** प्रति-ग्रह स्थायी नियम (ग्रह-कार्ड में सूचना हेतु)। */
    public const PLANET_NIYAM = [
        'Jupiter' => [
            'अकेला बृहस्पति दृष्टि/टक्कर से कितना ही मंदा क्यों न हो, टेवे वाले पर कभी अशुभ फल नहीं देता।',
            'बृहस्पति का मंदा असर हमेशा शनि के मंदे असर से शुरू होता है; नेक असर बृहस्पति की अपनी वस्तु/रिश्तेदार/कारोबार से प्रकट होता है।',
            'बरबाद बृहस्पति सामान्य असर हेतु "खाली बुध" गिना जाता है — फैसला बुध के स्वभाव पर होता है।',
            'बृहस्पति व राहु दोनों भाव 12 (आसमान) में इकट्ठे माने गए हैं; राहु का दरवाज़ा जैसा होगा, बृहस्पति की हवा वैसी।',
        ],
        'Sun' => [
            'सूरज के शत्रु — शुक्र, राहु, केतु, शनि। इनके घरों (2·6·7·8·10·11·12) में अकेला सूरज बैठे तो उस घर-मालिक की वस्तुओं पर अपना नेक असर बंद कर देता है।',
            'शत्रु ग्रह सूरज से पहले (नीचे के) घरों में हों → सूरज का मंदा असर टेवे वाले पर; बाद के घरों में हों → असर उन शत्रु ग्रहों व उस घर पर।',
            'कोई ग्रह सूरज से नष्ट हो रहा हो → सूरज का उपाय करें; सूरज खुद नष्ट हो रहा हो → शत्रु ग्रह को नेक कर लें (लाल किताब में सूरज अस्त नहीं होता)।',
            'केतु भाव-1 या मंगल भाव-6 में हो तो सूरज नेक बल्कि उच्च हालत का होता है; उत्तम सूरज के समय चंद्र, शुक्र व बुध का फल भी प्रायः भला रहता है।',
            'सूरज↔बुध दृष्टि: 100%→ससुराल अमीर · 50%→स्त्री की ताकत उत्तम · 25%→ज्योतिष-गणित में निपुण। शनि↔सूरज: 25%→गणित · 50%→इल्म-मकानात · 100%→जादूगरी।',
        ],
    ];

    /**
     * भाव-दृष्टि — house => [seen house => strength %]. Lal Kitab aspects run one
     * way and forwards only, which is why houses 7–12 cast none: nothing is left
     * ahead of them. The sole exception is the 8th, whose "टक्कर की दृष्टि" looks
     * back at the 2nd.
     *
     * Supplied by the owner, confirmed against Farman 4 (1→7, 2→6, 5→9, 8→2
     * reverse), and treated as authoritative over the bank's `bhav_drishti`
     * sheet, which is a plain "every house sees the 6th ahead" rule that
     * contradicts these at houses 2, 3 and 5.
     */
    public const DRISHTI = [
        1 => [7 => 100], 2 => [6 => 25], 3 => [9 => 50, 11 => 50], 4 => [10 => 100],
        5 => [9 => 50], 6 => [12 => 25], 7 => [], 8 => [2 => 100],
        9 => [], 10 => [], 11 => [], 12 => [],
    ];

    /**
     * टक्कर — a planet strikes whatever sits in the eighth house from it, and
     * spoils only that planet, never the sixth. One-way, except the 8th's
     * "उल्टी टक्कर" which is the DRISHTI 8→2 above. house => struck house.
     */
    public const TAKKAR = [
        1 => 8, 2 => 9, 3 => 10, 4 => 11, 5 => 12, 6 => 1,
        7 => 2, 8 => 3, 9 => 4, 10 => 5, 11 => 6, 12 => 7,
    ];

    /**
     * बुनियाद (जड़) — house => root house whose affliction rots this house's
     * fruit. From Farman 4: the 2nd's root is the 5th, the 9th's root is the
     * 2nd, the 8th's root is the 5th by way of the 2nd. Remedy always treats the
     * root planet, never the branch. Only the confirmed pairs; not a trine loop.
     * @var array<int,int>
     */
    public const BUNIYAD = [
        2 => 5, 9 => 2, 8 => 2, 5 => 9,
    ];

    /**
     * तीन टक्करें — incoming maps: house => list of houses that strike it.
     * Fire only when both the target house and the attacking house are occupied.
     *
     * विश्वासघात (धोखा): the 4th and 10th betray each house.
     * साझी चोट: neighbours (2nd & 12th); the four kendras also share with the
     *   other kendras.
     * अचानक चोट (अंधी): each kendra is struck by a trine group; the eight non-
     *   kendra houses are struck only by kendras.
     */
    public const VISHWASGHAT = [
        1 => [4, 10], 2 => [5, 11], 3 => [6, 12], 4 => [7, 1], 5 => [8, 2], 6 => [9, 3],
        7 => [10, 4], 8 => [11, 5], 9 => [12, 6], 10 => [1, 7], 11 => [2, 8], 12 => [3, 9],
    ];
    public const SAJHI_CHOT = [
        1 => [2, 12, 4, 10], 2 => [3, 1], 3 => [4, 2], 4 => [5, 3, 7, 1], 5 => [6, 4], 6 => [7, 5],
        7 => [8, 6, 10, 4], 8 => [9, 7], 9 => [10, 8], 10 => [11, 9, 1, 7], 11 => [12, 10], 12 => [1, 11],
    ];
    public const ACHANAK_CHOT = [
        1 => [3, 7, 11], 2 => [4], 3 => [1], 4 => [2, 6, 10], 5 => [7], 6 => [4],
        7 => [1, 5, 9], 8 => [10], 9 => [7], 10 => [4, 8, 12], 11 => [1], 12 => [10],
    ];

    /**
     * उच्च-भंग की शर्तें — an exalted planet gives no उच्च benefit when one of
     * these "other planet(s) in house N" conditions holds. Taken from the bank's
     * uch_neech_niyam bhang column, which is entirely house-stated, and structured
     * here so it is computable. planet => list of {planets, house}.
     * @var array<string,list<array{planets:list<string>,house:int}>>
     */
    public const BHANG_COND = [
        'Sun'     => [['planets' => ['Venus', 'Rahu', 'Ketu', 'Saturn'], 'house' => 7]],
        'Moon'    => [['planets' => ['Mercury', 'Venus'], 'house' => 6],
                     ['planets' => ['Mercury', 'Venus'], 'house' => 12]],
        'Mars'    => [['planets' => ['Mercury', 'Ketu'], 'house' => 2]],
        'Mercury' => [['planets' => ['Moon'], 'house' => 12]],
        'Jupiter' => [['planets' => ['Venus', 'Mercury'], 'house' => 10]],
        'Venus'   => [['planets' => ['Sun', 'Rahu', 'Moon'], 'house' => 2]],
        'Saturn'  => [['planets' => ['Sun', 'Mars'], 'house' => 1]],
        'Rahu'    => [['planets' => ['Venus', 'Sun', 'Mars'], 'house' => 12]],
        'Ketu'    => [['planets' => ['Moon', 'Mars'], 'house' => 2]],
    ];

    /**
     * स्थिति-उपाय बैंक — every negative situation the engine can detect, keyed by
     * a stable code so the text can be updated without touching engine code.
     *   phal   — plain-language effect
     *   upay   — list of remedies
     *   darja  — 'final' (owner/source), 'anumanit' (my reading of the rules),
     *            'lambit' (interim text until the owner supplies the real one)
     * @var array<string,array{phal:string,upay:list<string>,darja:string}>
     */
    public const SITUATION = [
        'RATANDH' => [
            'phal' => 'रतौंध कुंडली — भाव 4 में सूर्य व भाव 7 में शनि। सूर्यास्त के बाद बुद्धि, समझ व भाग्य साथ नहीं देते; रात के फ़ैसले भारी नुकसान देते हैं। दिन में जातक राजा रहता है।',
            'upay' => [
                'सबसे बड़ा उपाय परहेज़ है — बड़े फ़ैसले, दस्तख़त, बड़ा लेन-देन व नया काम सूर्यास्त के बाद कभी न करें; दिन के उजाले में निपटाएँ।',
                'सूर्य को बल दें — तांबे के बर्तन में गंगाजल या शुद्ध जल घर में रखें; रात को दूध पीने से सख़्त परहेज़ (दिन में ठीक)।',
                'शनि का ज़हर काटें — काली लकड़ी की बांसुरी में चीनी भरकर सुनसान जगह ज़मीन में दबाएँ; रात को काले या गहरे नीले कपड़े न पहनें।',
            ],
            'darja' => 'final',
        ],
        'SOYA' => [
            'phal' => 'सोया ग्रह — यह जिस भाव को देखता है वह पूरी तरह खाली है, इसलिए इसका कारकत्व फल नहीं दे रहा। अपनी जागृति आयु आने पर यह अपने-आप जाग जाएगा।',
            'upay' => ['जागृति आयु से पहले — जिस भाव को यह देखता है, उसकी "चाबी" जिस ग्रह के पास है उसे सक्रिय करें (सोया-चाबी तालिका)।'],
            'darja' => 'anumanit',
        ],
        'GUNGA' => [
            'phal' => 'गूंगा ग्रह — बोल तो सकता है पर सुनने वाला कोई नहीं (देखे जाने वाला भाव व दोनों पड़ोसी भाव खाली)। कारकत्व लगभग निष्फल।',
            'upay' => ['जिस भाव को यह देखता है, उसमें इसी ग्रह की स्थापना-वस्तु रखें — आवाज़ को जगह मिलेगी।'],
            'darja' => 'anumanit',
        ],
        'BEHRA' => [
            'phal' => 'बहरा ग्रह — कोई भरा हुआ भाव इसे नहीं देखता, इसलिए सलाह व मदद नहीं पहुँचती; उपाय का असर बहुत धीमा।',
            'upay' => ['जो भाव इसे देखता है (पर खाली है) उसके कारक ग्रह की सेवा करें — कान खुलेंगे।'],
            'darja' => 'anumanit',
        ],
        'LANGDA' => [
            'phal' => 'लंगड़ा ग्रह — कच्चे घर में व कमज़ोर स्थिति में। फल मिलता तो है पर बहुत देर से — यह चाल की समस्या है, संपर्क की नहीं।',
            'upay' => ['इसी ग्रह की स्थापना-वस्तु से बल दें।'],
            'darja' => 'anumanit',
        ],
        'ANDHA' => [
            'phal' => 'अंधा ग्रह — गूंगा भी और बहरा भी; किसी से कोई संपर्क नहीं। फल अप्रत्याशित या उल्टा।',
            'upay' => ['गूंगा व बहरा दोनों के उपाय एक साथ करें।'],
            'darja' => 'anumanit',
        ],
        'MRIT' => [
            'phal' => 'मृत ग्रह — अंधा व अकेला (कोई साथी ग्रह नहीं)। पूर्ण एकांत; कारकत्व अनुपस्थित मानें। सबसे दुर्लभ व कठोर श्रेणी।',
            'upay' => ['सर्वोच्च प्राथमिकता — स्थापना-वस्तु + कारक भाव की सेवा + उस ग्रह का दान, तीनों एक साथ।'],
            'darja' => 'anumanit',
        ],
        'VISHWASGHAT' => [
            'phal' => 'विश्वासघात — इस भाव के ग्रह को 4थे व 10वें भाव का ग्रह पीछे से धोखा देता है। जिस पर सबसे ज़्यादा भरोसा (साझेदार, रिश्तेदार, नौकर) वही धोखा देगा।',
            'upay' => ['बचाव — जिस ग्रह पर चोट पड़ रही है उसकी वस्तुएँ धारण करके उसे बल दें; या दोनों के बीच किसी मित्र ग्रह की वस्तुएँ स्थापित करें (हमलावर का सीधा उपाय कभी नहीं)।'],
            'darja' => 'final',
        ],
        'SAJHI_CHOT' => [
            'phal' => 'साझी चोट — दो भाव एक ही नींव से जुड़े हैं; एक पर चोट लगे तो दूसरे में भी दरार आती है। नुकसान बिना सीधे कारण के दूसरे हिस्से को चपेट में ले लेता है।',
            'upay' => ['नींव मज़बूत करें — दोनों भावों के कारकों की एक साथ सेवा करें।'],
            'darja' => 'final',
        ],
        'ACHANAK_CHOT' => [
            'phal' => 'अचानक चोट (अंधी टक्कर) — घात लगाकर वार; बचाव का मौका नहीं। अचानक दुर्घटना, अचानक बीमारी या रातों-रात नुकसान।',
            'upay' => ['चाबी वाले भाव से टालें — जिस घर पर आशंका है, उसकी चाबी जिस ग्रह के पास है उसे सक्रिय कराएँ (हमलावर का सीधा उपाय कभी नहीं)।'],
            'darja' => 'final',
        ],
        'BUNIYAD' => [
            'phal' => 'बुनियाद (जड़) बिगड़ी — जड़ वाले भाव में पापी/नीच ग्रह है, इसलिए शाखा वाले भाव के उच्च ग्रह का फल भी सड़ रहा है।',
            'upay' => ['उपाय हमेशा जड़ वाले भाव के ग्रह का करें — शाखा वाले ग्रह को बिल्कुल न छेड़ें; जड़ ठीक होते ही शाखा का फल अपने-आप सुधरेगा।'],
            'darja' => 'final',
        ],
        'MUKABLA' => [
            'phal' => 'सांझी गद्दी की पक्की दुश्मनी — भाव 8 में मंगल व शनि (आग व लोहा) की स्थायी टक्कर। अचानक दुर्घटना, रक्त-सर्जरी, या रातों-रात सब खत्म कर देने वाला झूठा आरोप/मुकदमा।',
            'upay' => ['यहाँ "एक को हटाओ" नहीं चलेगा (दोनों घर के मालिक हैं)। बिचौलिया चन्द्रमा — चाँदी का चौकोर टुकड़ा जेब में रखें, या दूध से स्नान करें/पिएँ (तत्व, मैत्री से बड़ा — नामित अपवाद)।'],
            'darja' => 'final',
        ],
        'VARSH_ASHUBH' => [
            'phal' => 'वर्षफल का वर्ष अशुभ — इस वर्ष की कुंडली में ग्रह ऐसे भावों में गए कि वर्ष का निष्कर्ष अशुभ बना।',
            'upay' => ['अंतरिम — इस वर्ष जो ग्रह अशुभ भाव में गए, उन्हीं के ग्रह-वार उपाय जन्मदिन से पूरे वर्ष चलाएँ; प्राथमिकता सबसे बुरे भाव वाले ग्रह को। (पूरा वर्ष-विशेष उपाय-पाठ आना बाकी।)'],
            'darja' => 'lambit',
        ],
    ];

    /**
     * आयु-योग के 5 वर्ग — the number is never shown; the band is. From the owner.
     * @var list<array{band:string,from:int,to:int,note:string}>
     */
    public const AYU_BAND = [
        ['band' => 'बालारिष्ट / अति-अल्प आयु', 'from' => 0, 'to' => 12, 'note' => 'प्राण-कष्ट की आशंका — उपाय आवश्यक'],
        ['band' => 'अल्प आयु', 'from' => 12, 'to' => 32, 'note' => 'प्रारंभिक स्वास्थ्य संघर्ष'],
        ['band' => 'मध्यम आयु', 'from' => 32, 'to' => 64, 'note' => ''],
        ['band' => 'दीर्घ आयु', 'from' => 64, 'to' => 80, 'note' => 'लंबी उम्र'],
        ['band' => 'पूर्ण आयु', 'from' => 80, 'to' => 120, 'note' => 'परिपूर्ण आयु'],
    ];

    /** Short life-area label of each house (1..12) — for plain-language फल. */
    public const HOUSE_TOPIC = [
        1 => 'शरीर, स्वास्थ्य व मान-सम्मान',
        2 => 'धन, कुटुम्ब व वाणी',
        3 => 'भाई-बहन, पराक्रम व साहस',
        4 => 'माता, सुख, भूमि-वाहन व मन',
        5 => 'संतान, विद्या व बुद्धि',
        6 => 'रोग, शत्रु, ऋण व मुकदमा',
        7 => 'विवाह, दाम्पत्य व साझेदारी',
        8 => 'आयु, आकस्मिक बाधा व गुप्त बातें',
        9 => 'भाग्य, धर्म, पिता व यात्रा',
        10 => 'कर्म, व्यवसाय व प्रतिष्ठा',
        11 => 'आय, लाभ व इच्छापूर्ति',
        12 => 'व्यय, हानि, विदेश व शयन-सुख',
    ];

    /** @var array<string,mixed>|null cached decoded JSON */
    private static ?array $bank = null;

    /** @return array<string,mixed> the full decoded knowledge bank (empty on failure). */
    public static function bank(): array
    {
        if (self::$bank !== null) {
            return self::$bank;
        }
        $file = \AB_ROOT . '/app/Astro/LalKitab/lalkitab_data.json';
        $json = @file_get_contents($file);
        if ($json === false) {
            self::$bank = [];
            error_log('LalKitab data bank missing: ' . $file);
            return self::$bank;
        }
        $data = json_decode($json, true);
        self::$bank = is_array($data) ? $data : [];
        return self::$bank;
    }

    /** One top-level section of the bank (e.g. "bhavgat_upay"). */
    public static function section(string $key): array
    {
        $b = self::bank();
        return isset($b[$key]) && is_array($b[$key]) ? $b[$key] : [];
    }

    public static function planetHi(string $p): string
    {
        return self::PLANET_HI[$p] ?? $p;
    }

    public static function signHi(string $s): string
    {
        return self::SIGN_HI[$s] ?? $s;
    }

    /** Hindi ordinal house label ("पहले", "दूसरे" ...). */
    public static function houseOrdinalHi(int $h): string
    {
        static $o = [
            1 => 'पहले', 2 => 'दूसरे', 3 => 'तीसरे', 4 => 'चौथे', 5 => 'पाँचवें',
            6 => 'छठे', 7 => 'सातवें', 8 => 'आठवें', 9 => 'नौवें', 10 => 'दसवें',
            11 => 'ग्यारहवें', 12 => 'बारहवें',
        ];
        return $o[$h] ?? ((string) $h);
    }
}
