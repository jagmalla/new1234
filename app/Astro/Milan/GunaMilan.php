<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Milan;

use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\PlanetCondition;

/**
 * Guna Milan / Ashtakoot — the classical 36-guna compatibility match.
 *
 * From two births' Moon rashi/nakshatra/pada (and Mars houses for the Mangal
 * check) it scores the eight kootas, runs the parihara (dosha-cancellation)
 * engine, performs the Mangal-dosha test, and produces a final summary band
 * with any override warnings. No new astronomy — it reuses the chart engine's
 * Moon longitude → rashi/nakshatra/pada, the sign lordships, and the shared
 * {@see PlanetCondition} natural-friendship table.
 *
 * The classical lookup matrices are baked in as the source of truth for POINTS
 * (so the page scores even before the SQL import and is unit-testable offline);
 * the editable phal / parihara / summary SENTENCES come from migration 014 via
 * {@see MilanRepository} and, when present, override the baked fallbacks.
 */
final class GunaMilan
{
    /** rashi (1..12) => [varna, varna_rank, vashya_group]. */
    private const RASHI_ATTR = [
        1 => ['Kshatriya', 3, 'C'], 2 => ['Vaishya', 2, 'C'], 3 => ['Shudra', 1, 'M'],
        4 => ['Brahmin', 4, 'J'], 5 => ['Kshatriya', 3, 'V'], 6 => ['Vaishya', 2, 'M'],
        7 => ['Shudra', 1, 'M'], 8 => ['Brahmin', 4, 'K'], 9 => ['Kshatriya', 3, 'M'],
        10 => ['Vaishya', 2, 'C'], 11 => ['Shudra', 1, 'M'], 12 => ['Brahmin', 4, 'J'],
    ];
    /** nakshatra (1..27) => [yoni, gana, nadi]. */
    private const NAK_ATTR = [
        1 => ['Ashwa', 'Dev', 'Aadi'], 2 => ['Gaja', 'Manushya', 'Madhya'], 3 => ['Mesha', 'Rakshasa', 'Antya'],
        4 => ['Sarpa', 'Manushya', 'Antya'], 5 => ['Sarpa', 'Dev', 'Madhya'], 6 => ['Shwan', 'Manushya', 'Aadi'],
        7 => ['Marjara', 'Dev', 'Aadi'], 8 => ['Mesha', 'Dev', 'Madhya'], 9 => ['Marjara', 'Rakshasa', 'Antya'],
        10 => ['Mushaka', 'Rakshasa', 'Antya'], 11 => ['Mushaka', 'Manushya', 'Madhya'], 12 => ['Gau', 'Manushya', 'Aadi'],
        13 => ['Mahisha', 'Dev', 'Aadi'], 14 => ['Vyaghra', 'Rakshasa', 'Madhya'], 15 => ['Mahisha', 'Dev', 'Antya'],
        16 => ['Vyaghra', 'Rakshasa', 'Antya'], 17 => ['Mriga', 'Dev', 'Madhya'], 18 => ['Mriga', 'Rakshasa', 'Aadi'],
        19 => ['Shwan', 'Rakshasa', 'Aadi'], 20 => ['Vanara', 'Manushya', 'Madhya'], 21 => ['Nakula', 'Manushya', 'Antya'],
        22 => ['Vanara', 'Dev', 'Antya'], 23 => ['Simha', 'Rakshasa', 'Madhya'], 24 => ['Ashwa', 'Rakshasa', 'Aadi'],
        25 => ['Simha', 'Manushya', 'Aadi'], 26 => ['Gau', 'Manushya', 'Madhya'], 27 => ['Gaja', 'Dev', 'Antya'],
    ];
    /** 14×14 symmetric yoni matrix (points 0..4). */
    private const YONI = [
        'Ashwa' => ['Ashwa' => 4, 'Gaja' => 2, 'Mesha' => 2, 'Sarpa' => 3, 'Shwan' => 2, 'Marjara' => 2, 'Mushaka' => 2, 'Gau' => 1, 'Mahisha' => 0, 'Vyaghra' => 1, 'Mriga' => 3, 'Vanara' => 3, 'Nakula' => 2, 'Simha' => 1],
        'Gaja' => ['Ashwa' => 2, 'Gaja' => 4, 'Mesha' => 3, 'Sarpa' => 3, 'Shwan' => 2, 'Marjara' => 2, 'Mushaka' => 2, 'Gau' => 2, 'Mahisha' => 3, 'Vyaghra' => 1, 'Mriga' => 2, 'Vanara' => 3, 'Nakula' => 2, 'Simha' => 0],
        'Mesha' => ['Ashwa' => 2, 'Gaja' => 3, 'Mesha' => 4, 'Sarpa' => 2, 'Shwan' => 1, 'Marjara' => 2, 'Mushaka' => 1, 'Gau' => 3, 'Mahisha' => 3, 'Vyaghra' => 1, 'Mriga' => 2, 'Vanara' => 0, 'Nakula' => 3, 'Simha' => 1],
        'Sarpa' => ['Ashwa' => 3, 'Gaja' => 3, 'Mesha' => 2, 'Sarpa' => 4, 'Shwan' => 2, 'Marjara' => 1, 'Mushaka' => 1, 'Gau' => 1, 'Mahisha' => 1, 'Vyaghra' => 2, 'Mriga' => 2, 'Vanara' => 2, 'Nakula' => 0, 'Simha' => 2],
        'Shwan' => ['Ashwa' => 2, 'Gaja' => 2, 'Mesha' => 1, 'Sarpa' => 2, 'Shwan' => 4, 'Marjara' => 2, 'Mushaka' => 1, 'Gau' => 2, 'Mahisha' => 2, 'Vyaghra' => 1, 'Mriga' => 0, 'Vanara' => 2, 'Nakula' => 1, 'Simha' => 1],
        'Marjara' => ['Ashwa' => 2, 'Gaja' => 2, 'Mesha' => 2, 'Sarpa' => 1, 'Shwan' => 2, 'Marjara' => 4, 'Mushaka' => 0, 'Gau' => 2, 'Mahisha' => 2, 'Vyaghra' => 1, 'Mriga' => 3, 'Vanara' => 3, 'Nakula' => 2, 'Simha' => 1],
        'Mushaka' => ['Ashwa' => 2, 'Gaja' => 2, 'Mesha' => 1, 'Sarpa' => 1, 'Shwan' => 1, 'Marjara' => 0, 'Mushaka' => 4, 'Gau' => 2, 'Mahisha' => 2, 'Vyaghra' => 2, 'Mriga' => 2, 'Vanara' => 2, 'Nakula' => 2, 'Simha' => 2],
        'Gau' => ['Ashwa' => 1, 'Gaja' => 2, 'Mesha' => 3, 'Sarpa' => 1, 'Shwan' => 2, 'Marjara' => 2, 'Mushaka' => 2, 'Gau' => 4, 'Mahisha' => 3, 'Vyaghra' => 0, 'Mriga' => 3, 'Vanara' => 2, 'Nakula' => 2, 'Simha' => 1],
        'Mahisha' => ['Ashwa' => 0, 'Gaja' => 3, 'Mesha' => 3, 'Sarpa' => 1, 'Shwan' => 2, 'Marjara' => 2, 'Mushaka' => 2, 'Gau' => 3, 'Mahisha' => 4, 'Vyaghra' => 1, 'Mriga' => 2, 'Vanara' => 2, 'Nakula' => 2, 'Simha' => 1],
        'Vyaghra' => ['Ashwa' => 1, 'Gaja' => 1, 'Mesha' => 1, 'Sarpa' => 2, 'Shwan' => 1, 'Marjara' => 1, 'Mushaka' => 2, 'Gau' => 0, 'Mahisha' => 1, 'Vyaghra' => 4, 'Mriga' => 1, 'Vanara' => 1, 'Nakula' => 2, 'Simha' => 1],
        'Mriga' => ['Ashwa' => 3, 'Gaja' => 2, 'Mesha' => 2, 'Sarpa' => 2, 'Shwan' => 0, 'Marjara' => 3, 'Mushaka' => 2, 'Gau' => 3, 'Mahisha' => 2, 'Vyaghra' => 1, 'Mriga' => 4, 'Vanara' => 2, 'Nakula' => 2, 'Simha' => 1],
        'Vanara' => ['Ashwa' => 3, 'Gaja' => 3, 'Mesha' => 0, 'Sarpa' => 2, 'Shwan' => 2, 'Marjara' => 3, 'Mushaka' => 2, 'Gau' => 2, 'Mahisha' => 2, 'Vyaghra' => 1, 'Mriga' => 2, 'Vanara' => 4, 'Nakula' => 3, 'Simha' => 2],
        'Nakula' => ['Ashwa' => 2, 'Gaja' => 2, 'Mesha' => 3, 'Sarpa' => 0, 'Shwan' => 1, 'Marjara' => 2, 'Mushaka' => 2, 'Gau' => 2, 'Mahisha' => 2, 'Vyaghra' => 2, 'Mriga' => 2, 'Vanara' => 3, 'Nakula' => 4, 'Simha' => 2],
        'Simha' => ['Ashwa' => 1, 'Gaja' => 0, 'Mesha' => 1, 'Sarpa' => 2, 'Shwan' => 1, 'Marjara' => 1, 'Mushaka' => 2, 'Gau' => 1, 'Mahisha' => 1, 'Vyaghra' => 1, 'Mriga' => 1, 'Vanara' => 2, 'Nakula' => 2, 'Simha' => 4],
    ];
    /** 5×5 vashya matrix (boy group × girl group). */
    private const VASHYA = [
        'C' => ['C' => 2, 'M' => 1, 'J' => 1, 'V' => 0, 'K' => 1],
        'M' => ['C' => 1, 'M' => 2, 'J' => 0.5, 'V' => 0, 'K' => 1],
        'J' => ['C' => 1, 'M' => 0.5, 'J' => 2, 'V' => 0, 'K' => 1],
        'V' => ['C' => 0, 'M' => 0, 'J' => 0, 'V' => 2, 'K' => 0],
        'K' => ['C' => 1, 'M' => 1, 'J' => 1, 'V' => 0, 'K' => 2],
    ];
    /** 3×3 gana matrix (boy row × girl col — NOT symmetric). */
    private const GANA = [
        'Dev' => ['Dev' => 6, 'Manushya' => 6, 'Rakshasa' => 1],
        'Manushya' => ['Dev' => 5, 'Manushya' => 6, 'Rakshasa' => 0],
        'Rakshasa' => ['Dev' => 1, 'Manushya' => 0, 'Rakshasa' => 6],
    ];

    private const RASHI_HI = [
        1 => 'मेष', 2 => 'वृषभ', 3 => 'मिथुन', 4 => 'कर्क', 5 => 'सिंह', 6 => 'कन्या',
        7 => 'तुला', 8 => 'वृश्चिक', 9 => 'धनु', 10 => 'मकर', 11 => 'कुंभ', 12 => 'मीन',
    ];
    private const NAK_HI = [
        1 => 'अश्विनी', 2 => 'भरणी', 3 => 'कृत्तिका', 4 => 'रोहिणी', 5 => 'मृगशिरा', 6 => 'आर्द्रा',
        7 => 'पुनर्वसु', 8 => 'पुष्य', 9 => 'आश्लेषा', 10 => 'मघा', 11 => 'पूर्वाफाल्गुनी', 12 => 'उत्तराफाल्गुनी',
        13 => 'हस्त', 14 => 'चित्रा', 15 => 'स्वाति', 16 => 'विशाखा', 17 => 'अनुराधा', 18 => 'ज्येष्ठा',
        19 => 'मूल', 20 => 'पूर्वाषाढ़ा', 21 => 'उत्तराषाढ़ा', 22 => 'श्रवण', 23 => 'धनिष्ठा', 24 => 'शतभिषा',
        25 => 'पूर्वाभाद्रपद', 26 => 'उत्तराभाद्रपद', 27 => 'रेवती',
    ];
    private const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];
    private const VARNA_HI = ['Brahmin' => 'ब्राह्मण', 'Kshatriya' => 'क्षत्रिय', 'Vaishya' => 'वैश्य', 'Shudra' => 'शूद्र'];
    private const YONI_HI = [
        'Ashwa' => 'अश्व', 'Gaja' => 'गज', 'Mesha' => 'मेष', 'Sarpa' => 'सर्प', 'Shwan' => 'श्वान',
        'Marjara' => 'मार्जार', 'Mushaka' => 'मूषक', 'Gau' => 'गौ', 'Mahisha' => 'महिष', 'Vyaghra' => 'व्याघ्र',
        'Mriga' => 'मृग', 'Vanara' => 'वानर', 'Nakula' => 'नकुल', 'Simha' => 'सिंह',
    ];
    private const GANA_HI = ['Dev' => 'देव', 'Manushya' => 'मनुष्य', 'Rakshasa' => 'राक्षस'];
    private const NADI_HI = ['Aadi' => 'आदि', 'Madhya' => 'मध्य', 'Antya' => 'अन्त्य'];

    private const KOOTA_LABEL = [
        'varna' => 'वर्ण', 'vashya' => 'वश्य', 'tara' => 'तारा', 'yoni' => 'योनि',
        'maitri' => 'ग्रह मैत्री', 'gana' => 'गण', 'bhakoot' => 'भकूट', 'nadi' => 'नाड़ी',
    ];
    private const KOOTA_MAX = [
        'varna' => 1, 'vashya' => 2, 'tara' => 3, 'yoni' => 4,
        'maitri' => 5, 'gana' => 6, 'bhakoot' => 7, 'nadi' => 8,
    ];

    /** Fallback phal sentences (migration 014 seed) — DB overrides these. */
    private const PHAL = [
        'varna|pass' => 'वर्ण मेल — स्वभाव-स्तर मिलता है; एक-दूसरे का सम्मान सहज रहेगा।',
        'varna|fail' => 'वर्ण भेद — दृष्टिकोण-स्तर में अंतर; एक-दूसरे के विचारों का सम्मान सीखना होगा (बड़ा दोष नहीं)।',
        'vashya|v2' => 'परस्पर आकर्षण-प्रभाव उत्तम — दोनों एक-दूसरे की बात मानेंगे।',
        'vashya|v1' => 'प्रभाव संतोषजनक — पर एक साथी का पलड़ा भारी रहेगा।',
        'vashya|v05' => 'आकर्षण सीमित — प्रभाव-संतुलन पर सचेत काम करना होगा।',
        'vashya|v0' => 'स्वभाव-वर्ग विपरीत — ज़िद टकरा सकती है; खुला संवाद ज़रूरी।',
        'tara|both_good' => 'तारा शुभ — स्वास्थ्य-सौभाग्य एक-दूसरे के लिए अनुकूल।',
        'tara|one_good' => 'तारा मिश्रित — एक पक्ष का सौभाग्य-योग सामान्य; बड़ा दोष नहीं।',
        'tara|both_bad' => 'तारा प्रतिकूल — स्वास्थ्य-सौभाग्य के लिए मंत्र-दान आदि उपाय अनुशंसित।',
        'yoni|y4' => 'समान योनि — शारीरिक-स्वभावगत मेल उत्तम; गहरा सामंजस्य।',
        'yoni|y3' => 'मित्र योनि — अच्छा शारीरिक-स्वभावगत मेल।',
        'yoni|y2' => 'सम योनि — मेल सामान्य; आपसी समझ से सुधरेगा।',
        'yoni|y1' => 'शत्रु योनि — स्वभाव-भिन्नता; धैर्य और स्थान (space) देना होगा।',
        'yoni|y0' => 'महाशत्रु योनि — गहरा स्वभाव-वैर; यह कूट विशेष विचारणीय।',
        'maitri|m5' => 'ग्रह-मैत्री श्रेष्ठ — मानसिक-वैचारिक मेल गहरा; मित्रता-भाव बना रहेगा।',
        'maitri|m4' => 'ग्रह-मैत्री अच्छी — विचार अधिकतर मिलेंगे।',
        'maitri|m3' => 'ग्रह-संबंध सम — वैचारिक मेल सामान्य।',
        'maitri|m1' => 'ग्रह-संबंध मिश्रित (मित्र-शत्रु) — विचारों में खिंचाव संभव।',
        'maitri|m05' => 'ग्रह-संबंध कमजोर — वैचारिक दूरी; संवाद-सेतु बनाना होगा।',
        'maitri|m0' => 'ग्रह-शत्रुता — मानसिक तालमेल कठिन; यह कूट गंभीरता से देखें।',
        'gana|g6' => 'गण समान/अनुकूल — प्रकृति-मेल उत्तम; घर का वातावरण सहज।',
        'gana|g5' => 'गण मेल अच्छा — स्वभाव लगभग अनुकूल।',
        'gana|g1' => 'देव-राक्षस गण — प्रकृति-भेद बड़ा; सहनशीलता चाहिए।',
        'gana|g0' => 'गण दोष (मनुष्य-राक्षस) — स्वभाव-टकराव की आशंका; परिहार देखें।',
        'bhakoot|b7' => 'भकूट शुभ — जीवन-प्रवाह, धन और परिवार-वृद्धि अनुकूल।',
        'bhakoot|b_2_12' => 'द्विर्द्वादश भकूट दोष — धन-हानि व पारिवारिक कलह की आशंका; परिहार जाँचें।',
        'bhakoot|b_5_9' => 'नव-पंचम भकूट दोष — संतान-विषय व वैचारिक दूरी की आशंका; परिहार जाँचें।',
        'bhakoot|b_6_8' => 'षडाष्टक भकूट दोष — स्वास्थ्य-आयु की दृष्टि से सबसे गंभीर; परिहार अनिवार्यतः जाँचें।',
        'nadi|n8' => 'नाड़ी भिन्न — संतान व वंश-स्वास्थ्य के लिए उत्तम; सबसे भारी कूट पूर्ण अंक।',
        'nadi|n0_aadi' => 'नाड़ी दोष (आदि) — संतान-स्वास्थ्य चिंता; परिहार जाँचें।',
        'nadi|n0_madhya' => 'नाड़ी दोष (मध्य) — सबसे गंभीर माना जाता है; परिहार अनिवार्यतः जाँचें।',
        'nadi|n0_antya' => 'नाड़ी दोष (अन्त्य) — संतान-स्वास्थ्य चिंता; परिहार जाँचें।',
    ];
    private const PARIHARA_TXT = [
        'nadi_p1' => 'नाड़ी दोष भंग — समान राशि, भिन्न नक्षत्र।',
        'nadi_p2' => 'नाड़ी दोष भंग — समान नक्षत्र, भिन्न चरण।',
        'nadi_p3' => 'नाड़ी दोष शमित — राशि-स्वामियों की मैत्री।',
        'bhakoot_p1' => 'भकूट दोष भंग — एक ही स्वामी (जैसे मकर-कुम्भ, मेष-वृश्चिक)।',
        'bhakoot_p2' => 'भकूट दोष शमित — स्वामियों की मैत्री।',
        'gana_p1' => 'गण दोष शमित — स्वामियों की मैत्री।',
        'gana_p2' => 'गण दोष नरम — भकूट-तारा का सहारा।',
    ];
    private const SUMMARY_TXT = [
        's_33_36' => 'उत्तम मेल — गुण-मिलान की दृष्टि से विवाह अत्यंत अनुकूल।',
        's_25_32' => 'बहुत अच्छा मेल — विवाह अनुकूल; दोष-खंड अवश्य देखें।',
        's_18_24' => 'मध्यम मेल — दोष-परिहार और मंगल-संतुलन देखकर ही आगे बढ़ें।',
        's_0_17' => 'गुण-मेल अपर्याप्त — गुण-मिलान की दृष्टि से अनुशंसित नहीं।',
        'o_nadi' => 'विशेष चेतावनी: नाड़ी दोष (परिहार नहीं मिला) — कुल अंक अच्छे हों तो भी विवाह-निर्णय से पहले योग्य ज्योतिषी से परामर्श आवश्यक।',
        'o_bhakoot' => 'विशेष चेतावनी: भकूट दोष (परिहार नहीं मिला) — {dosha_type} की आशंका; परामर्श आवश्यक।',
        'o_both' => 'गंभीर चेतावनी: नाड़ी और भकूट दोनों दोष सक्रिय — अंक कितने भी हों, यह मिलान बिना विशेषज्ञ-परामर्श स्वीकार्य नहीं।',
        'o_mangal_mismatch' => 'मंगल-असंतुलन: एक पत्रिका मांगलिक, दूसरी नहीं — शास्त्रोक्त परिहार (मंगल स्वराशि/उच्च, गुरु-दृष्टि आदि) जाँचें; यह अष्टकूट से अलग अनिवार्य परीक्षा है।',
        'o_mangal_both' => 'दोनों पत्रिकाएँ मांगलिक — मंगल दोष परस्पर कट जाता है।',
        'o_mangal_none' => 'मंगल दोष दोनों में नहीं — यह परीक्षा उत्तीर्ण।',
    ];

    /**
     * @param array<string,mixed> $boy   milan person descriptor (see MilanController::milanPerson)
     * @param array<string,mixed> $girl  same shape
     * @param array<string,mixed> $rules editable text tables (MilanRepository::load) or []
     * @param array<string,string> $cfg  house_engine_config milan_* keys
     * @return array<string,mixed>
     */
    public static function compute(array $boy, array $girl, array $rules = [], array $cfg = []): array
    {
        $rb = (int) $boy['rashi'];
        $rg = (int) $girl['rashi'];
        $nb = (int) $boy['nak'];
        $ng = (int) $girl['nak'];
        $janma = (string) ($cfg['milan_janma_tara_shubh'] ?? '1') === '1';

        $kootas = [];
        $total = 0.0;

        // 1. VARNA (1)
        [$vpts, $vout] = self::varna($rb, $rg);
        $kootas[] = self::koota('varna', $vout, $vpts, $rules,
            'वर: ' . self::VARNA_HI[self::RASHI_ATTR[$rb][0]] . ' · कन्या: ' . self::VARNA_HI[self::RASHI_ATTR[$rg][0]]);

        // 2. VASHYA (2)
        $gb = self::vashyaGroup($rb, (float) ($boy['moon_deg'] ?? 0.0), $cfg);
        $gg = self::vashyaGroup($rg, (float) ($girl['moon_deg'] ?? 0.0), $cfg);
        $vshPts = (float) (($rules['vashya'][$gb][$gg] ?? null) ?? self::VASHYA[$gb][$gg]);
        $vshOut = $vshPts >= 2 ? 'v2' : ($vshPts >= 1 ? 'v1' : ($vshPts >= 0.5 ? 'v05' : 'v0'));
        $kootas[] = self::koota('vashya', $vshOut, $vshPts, $rules,
            'वर: ' . self::RASHI_HI[$rb] . ' → वर्ग ' . $gb . ' · कन्या: ' . self::RASHI_HI[$rg] . ' → वर्ग ' . $gg);

        // 3. TARA (3)
        [$tpts, $tout] = self::tara($nb, $ng, $janma);
        $kootas[] = self::koota('tara', $tout, $tpts, $rules,
            'वर नक्षत्र ' . self::NAK_HI[$nb] . ' ↔ कन्या नक्षत्र ' . self::NAK_HI[$ng]);

        // 4. YONI (4)
        $yb = self::NAK_ATTR[$nb][0];
        $yg = self::NAK_ATTR[$ng][0];
        $yPts = (float) (($rules['yoni'][$yb][$yg] ?? null) ?? self::YONI[$yb][$yg]);
        $yOut = 'y' . (int) $yPts;
        $kootas[] = self::koota('yoni', $yOut, $yPts, $rules,
            'वर: ' . self::NAK_HI[$nb] . ' → ' . self::YONI_HI[$yb] . ' योनि · कन्या: ' . self::NAK_HI[$ng] . ' → ' . self::YONI_HI[$yg] . ' योनि');

        // 5. GRAH MAITRI (5)
        $lordB = Charts::signLord($rb - 1);
        $lordG = Charts::signLord($rg - 1);
        [$mpts, $mout] = self::maitri($lordB, $lordG);
        $kootas[] = self::koota('maitri', $mout, $mpts, $rules,
            'वर राशि-स्वामी ' . self::PLANET_HI[$lordB] . ' · कन्या राशि-स्वामी ' . self::PLANET_HI[$lordG]);

        // 6. GANA (6)
        $ganaB = self::NAK_ATTR[$nb][1];
        $ganaG = self::NAK_ATTR[$ng][1];
        $ganaPts = (float) (($rules['gana'][$ganaB][$ganaG] ?? null) ?? self::GANA[$ganaB][$ganaG]);
        $ganaOut = 'g' . (int) $ganaPts;
        $kootas[] = self::koota('gana', $ganaOut, $ganaPts, $rules,
            'वर: ' . self::GANA_HI[$ganaB] . ' · कन्या: ' . self::GANA_HI[$ganaG]);

        // 7. BHAKOOT (7)
        [$bpts, $bout] = self::bhakoot($rb, $rg);
        $kootas[] = self::koota('bhakoot', $bout, $bpts, $rules,
            'राशि ' . self::RASHI_HI[$rb] . ' ↔ ' . self::RASHI_HI[$rg]);

        // 8. NADI (8)
        $nadiB = self::NAK_ATTR[$nb][2];
        $nadiG = self::NAK_ATTR[$ng][2];
        $nadiPts = $nadiB !== $nadiG ? 8.0 : 0.0;
        $nadiOut = $nadiB !== $nadiG ? 'n8' : ('n0_' . strtolower($nadiB));
        $kootas[] = self::koota('nadi', $nadiOut, $nadiPts, $rules,
            'वर: ' . self::NADI_HI[$nadiB] . ' नाड़ी · कन्या: ' . self::NADI_HI[$nadiG] . ' नाड़ी');

        foreach ($kootas as $k) {
            $total += $k['points'];
        }

        // ---- Parihara engine (only for fired doshas) ----
        $fF = static fn(string $a, string $b): bool =>
            PlanetCondition::naturalRelationDirected($a, $b) === 'F'
            && PlanetCondition::naturalRelationDirected($b, $a) === 'F';
        $mutualLord = $fF($lordB, $lordG);

        $doshas = [];
        $nadiUnresolved = false;
        $bhakootUnresolved = false;

        if (str_starts_with($nadiOut, 'n0')) {
            $checks = [
                ['nadi_p1', 'समान राशि, भिन्न नक्षत्र', $rb === $rg && $nb !== $ng],
                ['nadi_p2', 'समान नक्षत्र, भिन्न चरण', $nb === $ng && (int) $boy['pada'] !== (int) $girl['pada']],
                ['nadi_p3', 'राशि-स्वामी एक/मित्र', $lordB === $lordG || $mutualLord],
            ];
            $d = self::doshaBlock('नाड़ी', $checks, $rules);
            $doshas[] = $d;
            $nadiUnresolved = !$d['resolved'];
        }
        if (in_array($bout, ['b_2_12', 'b_5_9', 'b_6_8'], true)) {
            $checks = [
                ['bhakoot_p1', 'एक ही राशि-स्वामी', $lordB === $lordG],
                ['bhakoot_p2', 'राशि-स्वामी परस्पर मित्र', $mutualLord],
            ];
            $d = self::doshaBlock('भकूट', $checks, $rules);
            $doshas[] = $d;
            $bhakootUnresolved = !$d['resolved'];
        }
        if ($ganaOut === 'g0') {
            $checks = [
                ['gana_p1', 'राशि-स्वामी परस्पर मित्र', $mutualLord],
                ['gana_p2', 'भकूट शुभ व तारा शुभ', $bout === 'b7' && $tout === 'both_good'],
            ];
            $doshas[] = self::doshaBlock('गण', $checks, $rules);
        }

        // ---- Final summary band + overrides ----
        $passScore = (float) ($cfg['milan_pass_score'] ?? 18);
        $bandKey = $total < $passScore ? 's_0_17'
            : ($total >= 33 ? 's_33_36' : ($total >= 25 ? 's_25_32' : ($total >= 18 ? 's_18_24' : 's_0_17')));

        $overrides = [];
        if ($nadiUnresolved && $bhakootUnresolved) {
            $overrides[] = self::summaryText('o_both', $rules);
        } elseif ($nadiUnresolved) {
            $overrides[] = self::summaryText('o_nadi', $rules);
        } elseif ($bhakootUnresolved) {
            $type = ['b_2_12' => 'द्विर्द्वादश', 'b_5_9' => 'नव-पंचम', 'b_6_8' => 'षडाष्टक'][$bout] ?? '';
            $overrides[] = strtr(self::summaryText('o_bhakoot', $rules), ['{dosha_type}' => $type]);
        }

        // ---- Mangal dosha (never changes the 36 total) ----
        $mangal = self::mangal($boy, $girl, $rules, $cfg);

        return [
            'boy' => self::personSummary($boy),
            'girl' => self::personSummary($girl),
            'kootas' => $kootas,
            'total' => round($total, 1),
            'max' => 36,
            'band' => ['key' => $bandKey, 'tier' => self::bandTier($bandKey), 'text' => self::summaryText($bandKey, $rules)],
            'overrides' => $overrides,
            'doshas' => $doshas,
            'mangal' => $mangal,
        ];
    }

    // ---- individual koota calculators --------------------------------------

    /** @return array{0:float,1:string} */
    private static function varna(int $rb, int $rg): array
    {
        $pass = self::RASHI_ATTR[$rb][1] >= self::RASHI_ATTR[$rg][1];
        return [$pass ? 1.0 : 0.0, $pass ? 'pass' : 'fail'];
    }

    private static function vashyaGroup(int $rashi, float $moonDeg, array $cfg): string
    {
        $base = self::RASHI_ATTR[$rashi][2];
        if ((string) ($cfg['milan_vashya_by_degree'] ?? '1') !== '1') {
            return $base;
        }
        if ($rashi === 9) {           // Dhanu: first half Manushya, second half Chatushpada
            return $moonDeg < 15.0 ? 'M' : 'C';
        }
        if ($rashi === 10) {          // Makara: first half Chatushpada, second half Jalachara
            return $moonDeg < 15.0 ? 'C' : 'J';
        }
        return $base;
    }

    /** @return array{0:float,1:string} */
    private static function tara(int $nb, int $ng, bool $janma): array
    {
        $good = static function (int $from, int $to) use ($janma): bool {
            $cnt = (($to - $from + 27) % 27) + 1;
            $rem = $cnt % 9;
            if (in_array($rem, [3, 5, 7], true)) {
                return false;
            }
            return $rem !== 1 || $janma;      // rem 1 = janma tara: good only when configured
        };
        $bg = $good($nb, $ng);
        $gb = $good($ng, $nb);
        if ($bg && $gb) {
            return [3.0, 'both_good'];
        }
        if ($bg || $gb) {
            return [1.5, 'one_good'];
        }
        return [0.0, 'both_bad'];
    }

    /** @return array{0:float,1:string} */
    private static function maitri(string $a, string $b): array
    {
        if ($a === $b) {
            return [5.0, 'm5'];
        }
        $pair = [
            PlanetCondition::naturalRelationDirected($a, $b),
            PlanetCondition::naturalRelationDirected($b, $a),
        ];
        sort($pair);                          // unordered pair, sorted: E < F < N
        $key = implode('', $pair);
        return match ($key) {
            'FF' => [5.0, 'm5'],
            'FN' => [4.0, 'm4'],
            'NN' => [3.0, 'm3'],
            'EF' => [1.0, 'm1'],
            'EN' => [0.5, 'm05'],
            'EE' => [0.0, 'm0'],
            default => [3.0, 'm3'],
        };
    }

    /** @return array{0:float,1:string} */
    private static function bhakoot(int $rb, int $rg): array
    {
        $d1 = (($rg - $rb + 12) % 12) + 1;
        $d2 = (($rb - $rg + 12) % 12) + 1;
        $pair = [min($d1, $d2), max($d1, $d2)];
        if ($pair === [2, 12]) {
            return [0.0, 'b_2_12'];
        }
        if ($pair === [5, 9]) {
            return [0.0, 'b_5_9'];
        }
        if ($pair === [6, 8]) {
            return [0.0, 'b_6_8'];
        }
        return [7.0, 'b7'];               // {1,1}{3,11}{4,10}{7,7} and any other = auspicious
    }

    // ---- parihara + mangal + helpers ---------------------------------------

    /**
     * @param list<array{0:string,1:string,2:bool}> $checks
     * @return array<string,mixed>
     */
    private static function doshaBlock(string $dosha, array $checks, array $rules): array
    {
        $rows = [];
        $resolved = false;
        $matchedText = '';
        foreach ($checks as [$key, $label, $ok]) {
            $rows[] = ['key' => $key, 'label' => $label, 'ok' => $ok];
            if ($ok && !$resolved) {
                $resolved = true;
                $matchedText = self::pariharaText($key, $rules);
            }
        }
        return [
            'dosha' => $dosha,
            'checks' => $rows,
            'resolved' => $resolved,
            'text' => $resolved ? $matchedText : 'परिहार नहीं मिला — दोष सक्रिय।',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    /**
     * Single-person Mangal (Manglik) test — Mars in house 1/4/7/8/12 (+2 by
     * config) from the Lagna (and the Moon / Venus per config), with the standard
     * cancellations (Mars own/exalted, Jupiter aspecting Mars, or Jupiter/Venus in
     * the Lagna). Public so the D1 birth-chart summary reuses the SAME calculation
     * Kundali Milan performs, instead of a parallel implementation.
     *
     * @param array<string,mixed>  $p   {mars:{from_lagna,from_moon,from_venus,own_or_exalt}, jupiter_aspects_mars, lagna_has_jup_or_venus}
     * @param array<string,string> $cfg house_engine_config milan_* keys (defaults match Milan)
     * @return array{manglik:bool,raw:bool,hits:list<string>,cancel:array{own_or_exalt:bool,jupiter_or_lagna:bool}}
     */
    public static function mangalPerson(array $p, array $cfg = []): array
    {
        $houses = [1, 4, 7, 8, 12];
        if ((string) ($cfg['milan_mangal_second_house'] ?? '1') === '1') {
            $houses[] = 2;
        }
        $fromMoon = (string) ($cfg['milan_mangal_from_moon'] ?? '1') === '1';
        $fromVenus = (string) ($cfg['milan_mangal_from_venus'] ?? '0') === '1';

        $mars = $p['mars'] ?? [];
        $refs = ['लग्न' => (int) ($mars['from_lagna'] ?? 0)];
        if ($fromMoon) {
            $refs['चन्द्र'] = (int) ($mars['from_moon'] ?? 0);
        }
        if ($fromVenus && isset($mars['from_venus'])) {
            $refs['शुक्र'] = (int) $mars['from_venus'];
        }
        $hits = [];
        foreach ($refs as $label => $house) {
            if (in_array($house, $houses, true)) {
                $hits[] = $label . ' (भाव ' . $house . ')';
            }
        }
        $raw = $hits !== [];
        $c1 = (bool) ($mars['own_or_exalt'] ?? false);
        $c2 = (bool) ($p['jupiter_aspects_mars'] ?? false) || (bool) ($p['lagna_has_jup_or_venus'] ?? false);
        $cancelled = $c1 || $c2;
        return [
            'manglik' => $raw && !$cancelled,
            'raw' => $raw,
            'hits' => $hits,
            'cancel' => ['own_or_exalt' => $c1, 'jupiter_or_lagna' => $c2],
        ];
    }

    private static function mangal(array $boy, array $girl, array $rules, array $cfg): array
    {
        $b = self::mangalPerson($boy, $cfg);
        $g = self::mangalPerson($girl, $cfg);
        $key = $b['manglik'] && $g['manglik'] ? 'o_mangal_both'
            : (($b['manglik'] xor $g['manglik']) ? 'o_mangal_mismatch' : 'o_mangal_none');

        return ['boy' => $b, 'girl' => $g, 'result' => ['key' => $key, 'text' => self::summaryText($key, $rules)]];
    }

    /** @return array<string,mixed> */
    private static function koota(string $koota, string $outcome, float $points, array $rules, string $reason): array
    {
        return [
            'koota' => $koota,
            'label' => self::KOOTA_LABEL[$koota] ?? $koota,
            'max' => self::KOOTA_MAX[$koota] ?? 0,
            'points' => $points,
            'outcome' => $outcome,
            'reason' => $reason,
            'phal' => self::phalText($koota, $outcome, $rules),
            'is_dosha' => $points <= 0 && in_array($koota, ['gana', 'bhakoot', 'nadi'], true),
        ];
    }

    private static function phalText(string $koota, string $outcome, array $rules): string
    {
        $key = $koota . '|' . $outcome;
        return (string) (($rules['koota_phal'][$key] ?? null) ?? self::PHAL[$key] ?? '');
    }

    private static function pariharaText(string $key, array $rules): string
    {
        return (string) (($rules['parihara'][$key] ?? null) ?? self::PARIHARA_TXT[$key] ?? '');
    }

    private static function summaryText(string $key, array $rules): string
    {
        return (string) (($rules['summary'][$key] ?? null) ?? self::SUMMARY_TXT[$key] ?? '');
    }

    private static function bandTier(string $bandKey): string
    {
        return match ($bandKey) {
            's_33_36' => 'shubh',
            's_25_32' => 'shubh',
            's_18_24' => 'mishrit',
            default => 'ashubh',
        };
    }

    /** @return array<string,mixed> */
    private static function personSummary(array $p): array
    {
        $r = (int) $p['rashi'];
        $n = (int) $p['nak'];
        return [
            'name' => (string) ($p['name'] ?? ''),
            'rashi' => $r,
            'rashi_hi' => self::RASHI_HI[$r] ?? (string) $r,
            'nak' => $n,
            'nak_hi' => self::NAK_HI[$n] ?? (string) $n,
            'pada' => (int) $p['pada'],
        ];
    }
}
