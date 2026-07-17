<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Saham;

use AutoBusiness\Core\Database;
use PDO;
use Throwable;

/**
 * Loads the Saham rule tables (migration 015): the 50 formula definitions
 * (ordered by seq so dependencies resolve), the editable phal sentences, and
 * the saham_* config. Resilient — on a DB error load() returns null and the
 * caller degrades to an empty Saham panel; lastError() surfaces the reason.
 */
final class SahamRepository
{
    private static ?string $lastError = null;

    /** Fallback saham_definitions (migration 015 seed) — DB overrides. */
    private const DEFAULT_DEFS = [
        ['saham_key'=>'punya','seq'=>1,'name_hi'=>'पुण्य','a_day'=>'MOON','b_day'=>'SUN','c_day'=>'LAGNA','a_night'=>'SUN','b_night'=>'MOON','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>''],
        ['saham_key'=>'guru','seq'=>2,'name_hi'=>'गुरु','a_day'=>'SUN','b_day'=>'MOON','c_day'=>'LAGNA','a_night'=>'MOON','b_night'=>'SUN','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>''],
        ['saham_key'=>'gyan','seq'=>3,'name_hi'=>'ज्ञान (विद्या)','a_day'=>'SUN','b_day'=>'MOON','c_day'=>'LAGNA','a_night'=>'MOON','b_night'=>'SUN','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'ग्रन्थ में गुरु-सहम के समान'],
        ['saham_key'=>'yash','seq'=>4,'name_hi'=>'यश','a_day'=>'JUPITER','b_day'=>'PUNYA','c_day'=>'LAGNA','a_night'=>'PUNYA','b_night'=>'JUPITER','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>''],
        ['saham_key'=>'mitra','seq'=>5,'name_hi'=>'मित्र','a_day'=>'GURU_SAHAM','b_day'=>'PUNYA','c_day'=>'VENUS','a_night'=>'PUNYA','b_night'=>'GURU_SAHAM','c_night'=>'VENUS','sekata'=>1,'nature'=>'shubh','special'=>'जोड़/सैकता-जाँच शुक्र से'],
        ['saham_key'=>'mahatmya','seq'=>6,'name_hi'=>'माहात्म्य','a_day'=>'PUNYA','b_day'=>'MARS','c_day'=>'LAGNA','a_night'=>'MARS','b_night'=>'PUNYA','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>''],
        ['saham_key'=>'asha','seq'=>7,'name_hi'=>'आशा','a_day'=>'SATURN','b_day'=>'VENUS','c_day'=>'LAGNA','a_night'=>'VENUS','b_night'=>'SATURN','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>''],
        ['saham_key'=>'samarthya','seq'=>8,'name_hi'=>'सामर्थ्य','a_day'=>'MARS','b_day'=>'LAGNA_LORD','c_day'=>'LAGNA','a_night'=>'LAGNA_LORD','b_night'=>'MARS','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'मंगल स्वयं लग्नेश → सदा: गुरु−मंगल+लग्न'],
        ['saham_key'=>'bhratru','seq'=>9,'name_hi'=>'भ्रातृ','a_day'=>'JUPITER','b_day'=>'SATURN','c_day'=>'LAGNA','a_night'=>'JUPITER','b_night'=>'SATURN','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'दिन-रात एक'],
        ['saham_key'=>'gaurav','seq'=>10,'name_hi'=>'गौरव','a_day'=>'JUPITER','b_day'=>'MOON','c_day'=>'SUN','a_night'=>'JUPITER','b_night'=>'SUN','c_night'=>'MOON','sekata'=>1,'nature'=>'shubh','special'=>'जोड़: D सूर्य / N चन्द्र'],
        ['saham_key'=>'rajya','seq'=>11,'name_hi'=>'राज्य','a_day'=>'SATURN','b_day'=>'SUN','c_day'=>'LAGNA','a_night'=>'SUN','b_night'=>'SATURN','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>''],
        ['saham_key'=>'tata','seq'=>12,'name_hi'=>'तात (पितृ)','a_day'=>'SATURN','b_day'=>'SUN','c_day'=>'LAGNA','a_night'=>'SUN','b_night'=>'SATURN','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'ग्रन्थ में राज्य-सहम के समान'],
        ['saham_key'=>'matru','seq'=>13,'name_hi'=>'मातृ','a_day'=>'MOON','b_day'=>'VENUS','c_day'=>'LAGNA','a_night'=>'VENUS','b_night'=>'MOON','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>''],
        ['saham_key'=>'suta','seq'=>14,'name_hi'=>'सुत (पुत्र)','a_day'=>'JUPITER','b_day'=>'MOON','c_day'=>'LAGNA','a_night'=>'JUPITER','b_night'=>'MOON','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'दिन-रात एक'],
        ['saham_key'=>'jeevit','seq'=>15,'name_hi'=>'जीवित','a_day'=>'SATURN','b_day'=>'JUPITER','c_day'=>'LAGNA','a_night'=>'JUPITER','b_night'=>'SATURN','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'⚠ पाठ-भेद संभव — PL से मिलान करें'],
        ['saham_key'=>'ambu','seq'=>16,'name_hi'=>'अम्बु','a_day'=>'MOON','b_day'=>'VENUS','c_day'=>'LAGNA','a_night'=>'VENUS','b_night'=>'MOON','c_night'=>'LAGNA','sekata'=>1,'nature'=>'neutral','special'=>'मातृ-सहम के समान'],
        ['saham_key'=>'karma','seq'=>17,'name_hi'=>'कर्म','a_day'=>'MARS','b_day'=>'MERCURY','c_day'=>'LAGNA','a_night'=>'MERCURY','b_night'=>'MARS','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>''],
        ['saham_key'=>'roga','seq'=>18,'name_hi'=>'रोग (मान्द्य)','a_day'=>'LAGNA','b_day'=>'MOON','c_day'=>'LAGNA','a_night'=>'LAGNA','b_night'=>'MOON','c_night'=>'LAGNA','sekata'=>0,'nature'=>'ashubh','special'=>'सैकता नहीं लगती'],
        ['saham_key'=>'manmatha','seq'=>19,'name_hi'=>'मन्मथ (मदन)','a_day'=>'MOON','b_day'=>'LAGNA_LORD','c_day'=>'LAGNA','a_night'=>'LAGNA_LORD','b_night'=>'MOON','c_night'=>'LAGNA','sekata'=>1,'nature'=>'neutral','special'=>'चन्द्र लग्नेश → सदा: सूर्य−लग्नेश+लग्न, सैकता नहीं'],
        ['saham_key'=>'kali','seq'=>20,'name_hi'=>'कलि','a_day'=>'JUPITER','b_day'=>'MARS','c_day'=>'LAGNA','a_night'=>'MARS','b_night'=>'JUPITER','c_night'=>'LAGNA','sekata'=>1,'nature'=>'ashubh','special'=>''],
        ['saham_key'=>'kshama','seq'=>21,'name_hi'=>'क्षमा','a_day'=>'JUPITER','b_day'=>'MARS','c_day'=>'LAGNA','a_night'=>'MARS','b_night'=>'JUPITER','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'ग्रन्थ स्वयं कलि=क्षमा को वैचित्र्य कहता है'],
        ['saham_key'=>'shastra','seq'=>22,'name_hi'=>'शास्त्र','a_day'=>'JUPITER','b_day'=>'SATURN','c_day'=>'LAGNA','a_night'=>'SATURN','b_night'=>'JUPITER','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>''],
        ['saham_key'=>'bandhu','seq'=>23,'name_hi'=>'बन्धु','a_day'=>'MERCURY','b_day'=>'MOON','c_day'=>'LAGNA','a_night'=>'MERCURY','b_night'=>'MOON','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'दिन-रात एक'],
        ['saham_key'=>'bandaka','seq'=>24,'name_hi'=>'बन्दक','a_day'=>'MOON','b_day'=>'MERCURY','c_day'=>'LAGNA','a_night'=>'MERCURY','b_night'=>'MOON','c_night'=>'LAGNA','sekata'=>1,'nature'=>'ashubh','special'=>'रात्रि-सूत्र = बन्धु'],
        ['saham_key'=>'mrityu','seq'=>25,'name_hi'=>'मृत्यु','a_day'=>'H8','b_day'=>'MOON','c_day'=>'SATURN','a_night'=>'MOON','b_night'=>'H8','c_night'=>'SATURN','sekata'=>1,'nature'=>'ashubh','special'=>'जोड़/जाँच शनि से'],
        ['saham_key'=>'pardesh','seq'=>26,'name_hi'=>'परदेश (देशान्तर)','a_day'=>'H9','b_day'=>'H9_LORD','c_day'=>'LAGNA','a_night'=>'H9','b_night'=>'H9_LORD','c_night'=>'LAGNA','sekata'=>1,'nature'=>'neutral','special'=>'दिन-रात एक'],
        ['saham_key'=>'dhana','seq'=>27,'name_hi'=>'धन (अर्थ)','a_day'=>'H2','b_day'=>'H2_LORD','c_day'=>'LAGNA','a_night'=>'H2','b_night'=>'H2_LORD','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'दिन-रात एक'],
        ['saham_key'=>'anyadara','seq'=>28,'name_hi'=>'अन्यदारा','a_day'=>'VENUS','b_day'=>'SUN','c_day'=>'LAGNA','a_night'=>'VENUS','b_night'=>'SUN','c_night'=>'LAGNA','sekata'=>1,'nature'=>'ashubh','special'=>'दिन-रात एक'],
        ['saham_key'=>'anyakarma','seq'=>29,'name_hi'=>'अन्यकर्म','a_day'=>'MOON','b_day'=>'SATURN','c_day'=>'LAGNA','a_night'=>'SATURN','b_night'=>'MOON','c_night'=>'LAGNA','sekata'=>1,'nature'=>'neutral','special'=>''],
        ['saham_key'=>'vanik','seq'=>30,'name_hi'=>'वणिक्','a_day'=>'MOON','b_day'=>'MERCURY','c_day'=>'LAGNA','a_night'=>'MOON','b_night'=>'MERCURY','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'सदा (दिवा-बन्दक रीति)'],
        ['saham_key'=>'karyasiddhi','seq'=>31,'name_hi'=>'कार्यसिद्धि','a_day'=>'SATURN','b_day'=>'SUN','c_day'=>'SUN_SIGN_LORD','a_night'=>'SATURN','b_night'=>'MOON','c_night'=>'MOON_SIGN_LORD','sekata'=>1,'nature'=>'shubh','special'=>'जोड़: D सूर्य-राशि-स्वामी / N चन्द्र-राशि-स्वामी'],
        ['saham_key'=>'vivaha','seq'=>32,'name_hi'=>'विवाह (उद्वाह)','a_day'=>'VENUS','b_day'=>'SATURN','c_day'=>'LAGNA','a_night'=>'VENUS','b_night'=>'SATURN','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'दिन-रात एक'],
        ['saham_key'=>'suti','seq'=>33,'name_hi'=>'सूति (प्रसूति)','a_day'=>'JUPITER','b_day'=>'MERCURY','c_day'=>'LAGNA','a_night'=>'MERCURY','b_night'=>'JUPITER','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>''],
        ['saham_key'=>'santapa','seq'=>34,'name_hi'=>'सन्ताप','a_day'=>'SATURN','b_day'=>'MOON','c_day'=>'H6','a_night'=>'SATURN','b_night'=>'MOON','c_night'=>'H6','sekata'=>1,'nature'=>'ashubh','special'=>'जोड़/जाँच षष्ठ-भाव से; दिन-रात एक'],
        ['saham_key'=>'shraddha','seq'=>35,'name_hi'=>'श्रद्धा','a_day'=>'VENUS','b_day'=>'MARS','c_day'=>'LAGNA','a_night'=>'VENUS','b_night'=>'MARS','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'दिन-रात एक'],
        ['saham_key'=>'preeti','seq'=>36,'name_hi'=>'प्रीति','a_day'=>'VIDYA_SAHAM','b_day'=>'PUNYA','c_day'=>'LAGNA','a_night'=>'VIDYA_SAHAM','b_night'=>'PUNYA','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'दिन-रात एक'],
        ['saham_key'=>'bala','seq'=>37,'name_hi'=>'बल','a_day'=>'JUPITER','b_day'=>'PUNYA','c_day'=>'LAGNA','a_night'=>'PUNYA','b_night'=>'JUPITER','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'यश-सहम के समान'],
        ['saham_key'=>'tanu','seq'=>38,'name_hi'=>'तनु (देह)','a_day'=>'JUPITER','b_day'=>'PUNYA','c_day'=>'LAGNA','a_night'=>'PUNYA','b_night'=>'JUPITER','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'यश-सहम के समान'],
        ['saham_key'=>'jadya','seq'=>39,'name_hi'=>'जाड्य','a_day'=>'MARS','b_day'=>'SATURN','c_day'=>'MERCURY','a_night'=>'SATURN','b_night'=>'MARS','c_night'=>'MERCURY','sekata'=>1,'nature'=>'ashubh','special'=>'जोड़/जाँच बुध से'],
        ['saham_key'=>'vyapara','seq'=>40,'name_hi'=>'व्यापार','a_day'=>'MARS','b_day'=>'MERCURY','c_day'=>'LAGNA','a_night'=>'MARS','b_night'=>'MERCURY','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'सदा (कर्म का नित्य रूप)'],
        ['saham_key'=>'paniyapatana','seq'=>41,'name_hi'=>'पानीयपतन','a_day'=>'SATURN','b_day'=>'MOON','c_day'=>'LAGNA','a_night'=>'MOON','b_night'=>'SATURN','c_night'=>'LAGNA','sekata'=>1,'nature'=>'ashubh','special'=>''],
        ['saham_key'=>'ripu','seq'=>42,'name_hi'=>'रिपु','a_day'=>'MARS','b_day'=>'SATURN','c_day'=>'LAGNA','a_night'=>'SATURN','b_night'=>'MARS','c_night'=>'LAGNA','sekata'=>1,'nature'=>'ashubh','special'=>''],
        ['saham_key'=>'shaurya','seq'=>43,'name_hi'=>'शौर्य','a_day'=>'PUNYA','b_day'=>'MARS','c_day'=>'LAGNA','a_night'=>'MARS','b_night'=>'PUNYA','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'सूत्र माहात्म्य जैसा (ग्रन्थ-वैचित्र्य)'],
        ['saham_key'=>'upaya','seq'=>44,'name_hi'=>'उपाय','a_day'=>'SATURN','b_day'=>'JUPITER','c_day'=>'LAGNA','a_night'=>'JUPITER','b_night'=>'SATURN','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>''],
        ['saham_key'=>'daridra','seq'=>45,'name_hi'=>'दरिद्र','a_day'=>'PUNYA','b_day'=>'MERCURY','c_day'=>'MERCURY','a_night'=>'MERCURY','b_night'=>'PUNYA','c_night'=>'MERCURY','sekata'=>1,'nature'=>'ashubh','special'=>'जोड़/जाँच बुध से; ⚠ PL से मिलान करें'],
        ['saham_key'=>'guruta','seq'=>46,'name_hi'=>'गुरुता','a_day'=>'SUN_EXALT','b_day'=>'SUN','c_day'=>'LAGNA','a_night'=>'MOON_EXALT','b_night'=>'MOON','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'उच्च-बिंदु: सूर्य 10°, चन्द्र 33°'],
        ['saham_key'=>'jalapatha','seq'=>47,'name_hi'=>'जलपथ (अम्बुपथ)','a_day'=>'KARKARDHA','b_day'=>'SATURN','c_day'=>'LAGNA','a_night'=>'SATURN','b_night'=>'KARKARDHA','c_night'=>'LAGNA','sekata'=>1,'nature'=>'neutral','special'=>'कर्कार्ध = 105°'],
        ['saham_key'=>'bandhana','seq'=>48,'name_hi'=>'बन्धन','a_day'=>'PUNYA','b_day'=>'SATURN','c_day'=>'LAGNA','a_night'=>'SATURN','b_night'=>'PUNYA','c_night'=>'LAGNA','sekata'=>1,'nature'=>'ashubh','special'=>''],
        ['saham_key'=>'duhita','seq'=>49,'name_hi'=>'दुहिता (कन्या)','a_day'=>'VENUS','b_day'=>'MOON','c_day'=>'LAGNA','a_night'=>'VENUS','b_night'=>'MOON','c_night'=>'LAGNA','sekata'=>1,'nature'=>'shubh','special'=>'दिन-रात एक'],
        ['saham_key'=>'ashva','seq'=>50,'name_hi'=>'अश्व','a_day'=>'PUNYA','b_day'=>'SUN','c_day'=>'H11','a_night'=>'SUN','b_night'=>'PUNYA','c_night'=>'H11','sekata'=>1,'nature'=>'shubh','special'=>'जोड़/जाँच एकादश-भाव से'],
    ];
    /** Fallback saham_phal (Hindi) — DB overrides. */
    private const DEFAULT_PHAL = [
        'punya'=>['signifies'=>'वर्ष का समग्र पुण्य-सौभाग्य','phal_anukul'=>'वर्ष का समग्र पुण्य-सौभाग्य — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर वर्ष शुभ-फलदायी; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर वर्ष में सौभाग्य-कमी; उपाय रखें।'],
        'guru'=>['signifies'=>'गुरुजन व बड़ों का आशीर्वाद','phal_anukul'=>'गुरुजन व बड़ों का आशीर्वाद — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर गुरु-कृपा और मार्गदर्शन; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर गुरुजनों से दूरी; उपाय रखें।'],
        'gyan'=>['signifies'=>'विद्या-ज्ञान','phal_anukul'=>'विद्या-ज्ञान — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर अध्ययन में सफलता; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर विद्या में व्यवधान; उपाय रखें।'],
        'yash'=>['signifies'=>'कीर्ति-यश','phal_anukul'=>'कीर्ति-यश — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर मान-प्रतिष्ठा की वृद्धि; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर अपयश का भय; उपाय रखें।'],
        'mitra'=>['signifies'=>'मित्र-सहयोग','phal_anukul'=>'मित्र-सहयोग — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर मित्रों से लाभ; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर मित्र-वियोग/धोखा; उपाय रखें।'],
        'mahatmya'=>['signifies'=>'महत्ता-प्रभाव','phal_anukul'=>'महत्ता-प्रभाव — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर समाज में प्रभाव बढ़ेगा; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर प्रभाव-क्षय; उपाय रखें।'],
        'asha'=>['signifies'=>'मनोकामना','phal_anukul'=>'मनोकामना — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर आशाएँ फलेंगी; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर मनोरथ में विलंब; उपाय रखें।'],
        'samarthya'=>['signifies'=>'कार्य-शक्ति','phal_anukul'=>'कार्य-शक्ति — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर कार्यक्षमता प्रबल; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर शक्ति-संकोच; उपाय रखें।'],
        'bhratru'=>['signifies'=>'भाई-बहन','phal_anukul'=>'भाई-बहन — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर भाई-पक्ष से सुख; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर भाई-पक्ष की चिंता; उपाय रखें।'],
        'gaurav'=>['signifies'=>'सम्मान','phal_anukul'=>'सम्मान — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर सम्मान-प्राप्ति; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर मान-हानि का भय; उपाय रखें।'],
        'rajya'=>['signifies'=>'राज्य/सरकार-पक्ष, पद','phal_anukul'=>'राज्य/सरकार-पक्ष, पद — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर राज्य-पक्ष से लाभ/पद; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर सरकारी बाधा; उपाय रखें।'],
        'tata'=>['signifies'=>'पिता','phal_anukul'=>'पिता — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर पिता का सुख-सहयोग; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर पिता के स्वास्थ्य/संबंध की चिंता; उपाय रखें।'],
        'matru'=>['signifies'=>'माता','phal_anukul'=>'माता — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर माता का सुख; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर माता-पक्ष की चिंता; उपाय रखें।'],
        'suta'=>['signifies'=>'संतान','phal_anukul'=>'संतान — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर संतान-सुख/शुभ समाचार; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर संतान-विषय की चिंता; उपाय रखें।'],
        'jeevit'=>['signifies'=>'जीवनी-शक्ति/आयु','phal_anukul'=>'जीवनी-शक्ति/आयु — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर आरोग्य-आयुबल; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर जीवनी-शक्ति में कमी; उपाय रखें।'],
        'ambu'=>['signifies'=>'जल-संबंधी लाभ-भय','phal_anukul'=>'जल-संबंधी लाभ-भय — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर जल/तरल क्षेत्र से लाभ; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर जल से भय-हानि; उपाय रखें।'],
        'karma'=>['signifies'=>'कार्य-व्यवसाय','phal_anukul'=>'कार्य-व्यवसाय — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर कार्यक्षेत्र में प्रगति; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर कार्य में रुकावट; उपाय रखें।'],
        'roga'=>['signifies'=>'रोग','phal_anukul'=>'रोग — सहम पाप-प्रभाव/बली होने पर रोग-भय सक्रिय — स्वास्थ्य-अनुशासन रखें।','phal_pratikul'=>'सहम शुभ-दृष्ट/नियंत्रित होने पर रोग से राहत/नियंत्रण।'],
        'manmatha'=>['signifies'=>'काम/प्रेम-आकर्षण','phal_anukul'=>'काम/प्रेम-आकर्षण — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर प्रेम-संबंध अनुकूल; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर आकर्षण से भटकाव; उपाय रखें।'],
        'kali'=>['signifies'=>'कलह','phal_anukul'=>'कलह — सहम पाप-प्रभाव/बली होने पर कलह-विवाद का भय — वाणी-संयम।','phal_pratikul'=>'सहम शुभ-दृष्ट/नियंत्रित होने पर विवादों से राहत।'],
        'kshama'=>['signifies'=>'सहनशीलता','phal_anukul'=>'सहनशीलता — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर धैर्य-क्षमा का बल; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर सहनशीलता की परीक्षा; उपाय रखें।'],
        'shastra'=>['signifies'=>'शास्त्र-अध्ययन','phal_anukul'=>'शास्त्र-अध्ययन — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर गूढ़/शास्त्र-अध्ययन में सफलता; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर अध्ययन में विघ्न; उपाय रखें।'],
        'bandhu'=>['signifies'=>'बन्धु-वर्ग','phal_anukul'=>'बन्धु-वर्ग — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर बन्धुओं से सहयोग; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर बन्धु-विरोध; उपाय रखें।'],
        'bandaka'=>['signifies'=>'अवरोध/बंधक','phal_anukul'=>'अवरोध/बंधक — सहम पाप-प्रभाव/बली होने पर रुकावट-गिरवी का योग — लेन-देन सावधानी।','phal_pratikul'=>'सहम शुभ-दृष्ट/नियंत्रित होने पर अवरोध हटेंगे।'],
        'mrityu'=>['signifies'=>'मृत्यु-तुल्य संकट','phal_anukul'=>'मृत्यु-तुल्य संकट — सहम पाप-प्रभाव/बली होने पर संकट-भय सक्रिय — जोखिम टालें।','phal_pratikul'=>'सहम शुभ-दृष्ट/नियंत्रित होने पर संकट से रक्षा।'],
        'pardesh'=>['signifies'=>'विदेश/दूर-यात्रा','phal_anukul'=>'विदेश/दूर-यात्रा — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर यात्रा-प्रवास से लाभ; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर यात्रा में कष्ट; उपाय रखें।'],
        'dhana'=>['signifies'=>'धन-लाभ','phal_anukul'=>'धन-लाभ — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर धन-आगम अनुकूल; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर धन-संकोच; उपाय रखें।'],
        'anyadara'=>['signifies'=>'अनुचित आकर्षण','phal_anukul'=>'अनुचित आकर्षण — सहम पाप-प्रभाव/बली होने पर मर्यादा-भंग का भय — संयम रखें।','phal_pratikul'=>'सहम शुभ-दृष्ट/नियंत्रित होने पर संयम बना रहेगा।'],
        'anyakarma'=>['signifies'=>'गौण/अतिरिक्त कार्य','phal_anukul'=>'गौण/अतिरिक्त कार्य — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर दूसरे कार्यों से लाभ; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर कार्य-बिखराव; उपाय रखें।'],
        'vanik'=>['signifies'=>'वाणिज्य','phal_anukul'=>'वाणिज्य — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर व्यापार-लाभ; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर व्यापार में मंदी; उपाय रखें।'],
        'karyasiddhi'=>['signifies'=>'कार्य-सफलता','phal_anukul'=>'कार्य-सफलता — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर रुके कार्य सिद्ध होंगे; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर कार्यसिद्धि में विलंब; उपाय रखें।'],
        'vivaha'=>['signifies'=>'विवाह','phal_anukul'=>'विवाह — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर विवाह/संबंध-योग; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर विवाह में विलंब; उपाय रखें।'],
        'suti'=>['signifies'=>'संतान-जन्म','phal_anukul'=>'संतान-जन्म — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर संतान-जन्म/शुभ वृद्धि; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर प्रसव-विषय में सावधानी; उपाय रखें।'],
        'santapa'=>['signifies'=>'मानसिक क्लेश','phal_anukul'=>'मानसिक क्लेश — सहम पाप-प्रभाव/बली होने पर क्लेश-चिंता सक्रिय — मन का ध्यान रखें।','phal_pratikul'=>'सहम शुभ-दृष्ट/नियंत्रित होने पर मानसिक शांति।'],
        'shraddha'=>['signifies'=>'श्रद्धा-भक्ति','phal_anukul'=>'श्रद्धा-भक्ति — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर धर्म-कार्य में मन; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर श्रद्धा में कमी; उपाय रखें।'],
        'preeti'=>['signifies'=>'प्रेम-सौहार्द','phal_anukul'=>'प्रेम-सौहार्द — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर संबंधों में मिठास; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर प्रीति-भंग का भय; उपाय रखें।'],
        'bala'=>['signifies'=>'शारीरिक बल','phal_anukul'=>'शारीरिक बल — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर बल-ऊर्जा अनुकूल; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर बल-क्षय; उपाय रखें।'],
        'tanu'=>['signifies'=>'देह-स्वास्थ्य','phal_anukul'=>'देह-स्वास्थ्य — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर स्वास्थ्य अनुकूल; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर देह-कष्ट; उपाय रखें।'],
        'jadya'=>['signifies'=>'जड़ता-आलस्य','phal_anukul'=>'जड़ता-आलस्य — सहम पाप-प्रभाव/बली होने पर जड़ता-भ्रम का योग — निर्णय टालें नहीं।','phal_pratikul'=>'सहम शुभ-दृष्ट/नियंत्रित होने पर सक्रियता लौटेगी।'],
        'vyapara'=>['signifies'=>'व्यवसाय-लेनदेन','phal_anukul'=>'व्यवसाय-लेनदेन — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर सौदों में लाभ; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर लेनदेन में हानि; उपाय रखें।'],
        'paniyapatana'=>['signifies'=>'जल-दुर्घटना भय','phal_anukul'=>'जल-दुर्घटना भय — सहम पाप-प्रभाव/बली होने पर जल-यात्रा/जल से जोखिम — सावधानी।','phal_pratikul'=>'सहम शुभ-दृष्ट/नियंत्रित होने पर जल-भय से रक्षा।'],
        'ripu'=>['signifies'=>'शत्रु','phal_anukul'=>'शत्रु — सहम पाप-प्रभाव/बली होने पर शत्रु-पक्ष सक्रिय — सतर्क रहें।','phal_pratikul'=>'सहम शुभ-दृष्ट/नियंत्रित होने पर शत्रुओं पर नियंत्रण।'],
        'shaurya'=>['signifies'=>'वीरता-साहस','phal_anukul'=>'वीरता-साहस — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर साहसिक सफलता; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर साहस की परीक्षा; उपाय रखें।'],
        'upaya'=>['signifies'=>'उपाय-युक्ति','phal_anukul'=>'उपाय-युक्ति — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर युक्ति से समाधान; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर उपाय देर से फलेंगे; उपाय रखें।'],
        'daridra'=>['signifies'=>'अभाव-दरिद्रता','phal_anukul'=>'अभाव-दरिद्रता — सहम पाप-प्रभाव/बली होने पर अभाव का भय — व्यय-नियंत्रण।','phal_pratikul'=>'सहम शुभ-दृष्ट/नियंत्रित होने पर अभाव से मुक्ति।'],
        'guruta'=>['signifies'=>'बड़प्पन-गौरव','phal_anukul'=>'बड़प्पन-गौरव — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर गुरुता-पद की प्राप्ति; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर गौरव में कमी; उपाय रखें।'],
        'jalapatha'=>['signifies'=>'समुद्र/जल-यात्रा','phal_anukul'=>'समुद्र/जल-यात्रा — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर जल-मार्ग से लाभ; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर जल-यात्रा में विघ्न; उपाय रखें।'],
        'bandhana'=>['signifies'=>'बंधन-अवरोध','phal_anukul'=>'बंधन-अवरोध — सहम पाप-प्रभाव/बली होने पर बंधन-भय — विधिक/नियम सावधानी।','phal_pratikul'=>'सहम शुभ-दृष्ट/नियंत्रित होने पर बंधनों से मुक्ति।'],
        'duhita'=>['signifies'=>'कन्या-संतान','phal_anukul'=>'कन्या-संतान — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर कन्या-संतान/कन्या-पक्ष शुभ; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर कन्या-पक्ष की चिंता; उपाय रखें।'],
        'ashva'=>['signifies'=>'वाहन','phal_anukul'=>'वाहन — सहमेश बली, शुभ-स्थित व अ-अस्त होने पर वाहन-सुख/नया वाहन; फल सहमेश की मुद्दा-दशा या गणित-दिवस पर।','phal_pratikul'=>'सहमेश निर्बल, अस्त या पाप-पीड़ित होने पर वाहन से सावधानी; उपाय रखें।'],
    ];
    /** Plain, easy-Hindi one-line "what is this saham?" — DB overrides via saham_phal.explain_hi. */
    private const DEFAULT_EXPLAIN = [
        'punya'=>'यह सहम इस वर्ष के आपके कुल भाग्य और पुण्य को दिखाता है — साल कितना शुभ व सहज रहेगा।',
        'guru'=>'यह सहम गुरुजनों, बड़ों और मार्गदर्शकों के आशीर्वाद व सहयोग को बताता है।',
        'gyan'=>'यह सहम पढ़ाई, विद्या और नई बातें सीखने की स्थिति दिखाता है।',
        'yash'=>'यह सहम आपकी कीर्ति, नाम और समाज में प्रतिष्ठा को दर्शाता है।',
        'mitra'=>'यह सहम मित्रों और शुभचिंतकों से मिलने वाले सहयोग को बताता है।',
        'mahatmya'=>'यह सहम आपके प्रभाव, महत्ता और लोगों पर पड़ने वाले असर को दिखाता है।',
        'asha'=>'यह सहम आपकी इच्छाओं और मनोकामनाओं के पूरा होने की संभावना बताता है।',
        'samarthya'=>'यह सहम काम करने की आपकी शक्ति और क्षमता को दर्शाता है।',
        'bhratru'=>'यह सहम भाई-बहनों से संबंध और उनसे मिलने वाले सुख को बताता है।',
        'gaurav'=>'यह सहम आपको मिलने वाले मान-सम्मान और गौरव को दिखाता है।',
        'rajya'=>'यह सहम सरकार, पद और अधिकार-पक्ष से लाभ या बाधा को बताता है।',
        'tata'=>'यह सहम पिता से संबंध, उनके सुख और स्वास्थ्य को दर्शाता है।',
        'matru'=>'यह सहम माता से संबंध और उनके सुख को बताता है।',
        'suta'=>'यह सहम संतान से जुड़े सुख और शुभ समाचार को दर्शाता है।',
        'jeevit'=>'यह सहम आपकी जीवनी-शक्ति, रोग-प्रतिरोध और आयुबल को बताता है।',
        'ambu'=>'यह सहम जल और तरल-पदार्थ से जुड़े लाभ या भय को दिखाता है।',
        'karma'=>'यह सहम आपके कार्य, नौकरी और व्यवसाय की स्थिति को दर्शाता है।',
        'roga'=>'यह सहम रोग और स्वास्थ्य-कष्ट की संभावना को बताता है।',
        'manmatha'=>'यह सहम प्रेम, आकर्षण और वैवाहिक-सुख को दर्शाता है।',
        'kali'=>'यह सहम कलह, झगड़े और वाद-विवाद की संभावना को बताता है।',
        'kshama'=>'यह सहम आपके धैर्य, सहनशीलता और क्षमा-भाव को दर्शाता है।',
        'shastra'=>'यह सहम गहन अध्ययन, शास्त्र और विशेष विद्या में रुचि को बताता है।',
        'bandhu'=>'यह सहम रिश्तेदारों और परिवार-वर्ग से सहयोग को दर्शाता है।',
        'bandaka'=>'यह सहम रुकावट, अवरोध और बंधन जैसी बाधाओं को बताता है।',
        'mrityu'=>'यह सहम बड़े संकट, जोखिम और मृत्यु-तुल्य भय को दर्शाता है।',
        'pardesh'=>'यह सहम विदेश, दूर-यात्रा और प्रवास से जुड़ी बातों को बताता है।',
        'dhana'=>'यह सहम धन-लाभ, आमदनी और आर्थिक स्थिति को दर्शाता है।',
        'anyadara'=>'यह सहम पराए आकर्षण और मर्यादा से जुड़े जोखिम को बताता है।',
        'anyakarma'=>'यह सहम मुख्य काम के अलावा दूसरे व अतिरिक्त कार्यों को दर्शाता है।',
        'vanik'=>'यह सहम व्यापार और वाणिज्य से जुड़े लाभ को बताता है।',
        'karyasiddhi'=>'यह सहम रुके हुए कामों के पूरा होने और सफलता को दर्शाता है।',
        'vivaha'=>'यह सहम विवाह और वैवाहिक-संबंध के योग को बताता है।',
        'suti'=>'यह सहम संतान-जन्म और प्रसव से जुड़ी बातों को दर्शाता है।',
        'santapa'=>'यह सहम मानसिक चिंता, दुख और क्लेश की संभावना को बताता है।',
        'shraddha'=>'यह सहम धर्म, भक्ति और श्रद्धा-भाव को दर्शाता है।',
        'preeti'=>'यह सहम प्रेम, स्नेह और संबंधों की मिठास को बताता है।',
        'bala'=>'यह सहम आपकी शारीरिक शक्ति और ऊर्जा को दर्शाता है।',
        'tanu'=>'यह सहम शरीर और स्वास्थ्य की सामान्य स्थिति को बताता है।',
        'jadya'=>'यह सहम आलस्य, जड़ता और सुस्ती की स्थिति को दर्शाता है।',
        'vyapara'=>'यह सहम व्यापार, सौदे और लेन-देन की स्थिति को बताता है।',
        'paniyapatana'=>'यह सहम जल-दुर्घटना और जल से जुड़े जोखिम को दर्शाता है।',
        'ripu'=>'यह सहम शत्रु और विरोधियों की स्थिति को बताता है।',
        'shaurya'=>'यह सहम आपके साहस, वीरता और पराक्रम को दर्शाता है।',
        'upaya'=>'यह सहम समस्याओं के समाधान और युक्ति-उपाय को बताता है।',
        'daridra'=>'यह सहम अभाव, दरिद्रता और आर्थिक तंगी की संभावना को दर्शाता है।',
        'guruta'=>'यह सहम बड़प्पन, गौरव और ऊँचे पद की प्राप्ति को बताता है।',
        'jalapatha'=>'यह सहम समुद्र और जल-मार्ग की यात्रा से जुड़ी बातों को दर्शाता है।',
        'bandhana'=>'यह सहम बंधन, कैद और विधिक अवरोध के भय को बताता है।',
        'duhita'=>'यह सहम कन्या-संतान और कन्या-पक्ष से जुड़े सुख को दर्शाता है।',
        'ashva'=>'यह सहम वाहन और सवारी-सुख से जुड़ी बातों को बताता है।',
    ];


    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{defs: list<array<string,mixed>>, phal: array<string,array<string,string>>, config: array<string,string>}|null
     */
    public static function load(string $language = 'hi'): ?array
    {
        self::$lastError = null;
        try {
            $pdo = Database::pdo();
            $defs = [];
            foreach ($pdo->query('SELECT saham_key, seq, name_hi, a_day, b_day, c_day, a_night, b_night, c_night, sekata, nature, special FROM saham_definitions ORDER BY seq') as $r) {
                $defs[] = [
                    'saham_key' => (string) $r['saham_key'], 'seq' => (int) $r['seq'], 'name_hi' => (string) $r['name_hi'],
                    'a_day' => (string) $r['a_day'], 'b_day' => (string) $r['b_day'], 'c_day' => (string) $r['c_day'],
                    'a_night' => (string) $r['a_night'], 'b_night' => (string) $r['b_night'], 'c_night' => (string) $r['c_night'],
                    'sekata' => (int) $r['sekata'], 'nature' => (string) $r['nature'], 'special' => (string) ($r['special'] ?? ''),
                ];
            }

            $phal = [];
            $stmt = $pdo->prepare('SELECT saham_key, signifies, phal_anukul, phal_pratikul FROM saham_phal WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $phal[(string) $r['saham_key']] = [
                    'signifies' => (string) ($r['signifies'] ?? ''),
                    'phal_anukul' => (string) ($r['phal_anukul'] ?? ''),
                    'phal_pratikul' => (string) ($r['phal_pratikul'] ?? ''),
                ];
            }

            $config = [];
            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    if (str_starts_with((string) $r['cfg_key'], 'saham_')) {
                        $config[(string) $r['cfg_key']] = (string) $r['cfg_value'];
                    }
                }
            } catch (Throwable $e) { /* config optional */ }

            // Easy-Hindi "what is this saham?" line. Optional DB column
            // (saham_phal.explain_hi); missing column is tolerated. Baked text
            // fills any gap so every saham always has an explanation.
            try {
                $es = $pdo->prepare('SELECT saham_key, explain_hi FROM saham_phal WHERE language = ?');
                $es->execute([$language]);
                foreach ($es as $r) {
                    $ex = trim((string) ($r['explain_hi'] ?? ''));
                    if ($ex !== '' && isset($phal[(string) $r['saham_key']])) {
                        $phal[(string) $r['saham_key']]['explain'] = $ex;
                    }
                }
            } catch (Throwable $e) { /* explain_hi column optional */ }

            if ($defs === []) { $defs = self::DEFAULT_DEFS; }      // table empty -> baked
            if ($phal === []) { $phal = self::DEFAULT_PHAL; }
            return ['defs' => $defs, 'phal' => self::withExplain($phal), 'config' => self::config($config)];
        } catch (Throwable $e) {
            // DB unreachable: fall back to the baked classical data so the panel
            // still works (production DB, once imported, overrides this).
            self::$lastError = $e->getMessage();
            error_log('Saham rules load failed (using baked fallback): ' . $e->getMessage());
            return ['defs' => self::DEFAULT_DEFS, 'phal' => self::withExplain(self::DEFAULT_PHAL), 'config' => self::config([])];
        }
    }

    /** Ensure every phal entry carries an 'explain' line (baked fallback when unset). */
    private static function withExplain(array $phal): array
    {
        foreach ($phal as $key => &$p) {
            if (empty($p['explain'])) { $p['explain'] = self::DEFAULT_EXPLAIN[$key] ?? ''; }
        }
        unset($p);
        return $phal;
    }

    /** Merge loaded config over saham_* defaults. */
    private static function config(array $loaded): array
    {
        return $loaded + [
            'saham_house_point' => 'sign_start', 'saham_sekata_dir' => 'b_to_a',
            'saham_mode' => 'varsha', 'saham_strong_check' => 'shadbala_min',
        ];
    }
}
