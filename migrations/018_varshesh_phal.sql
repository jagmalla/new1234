-- =============================================================================
-- Auto Business — Varshesh nirnaya + Varshesh phala (migration 018)
-- Source: Tajik Neelkanthi, Varsha-tantra shlokas 1-44 (docs/Varshesh_Rules md).
-- REQUIRES migration 016 (tajik drishti service, hadda, yoga detector).
-- Reuses the existing Panchavargeeya (Varshesha) + Tajik (016) services.
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS varshesh_phal (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet VARCHAR(16) NOT NULL, bala_band VARCHAR(8) NOT NULL,  -- full/madhya/heen
    language VARCHAR(8) NOT NULL DEFAULT 'hi', phal_text TEXT NOT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_vp (planet, bala_band, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO varshesh_phal (planet,bala_band,language,phal_text) VALUES
('Sun','full','hi','राज्य-सुख और पुत्र-धन का लाभ; कुलोचित प्रभुत्व, परिवार-सौख्य, पुष्ट यश, गृह-सुख, विविध प्रतिष्ठा; शत्रु अनायास नष्ट होते हैं। (श्लोक 15)'),
('Sun','madhya','hi','सब फल स्वल्प; स्वजनों से ही विषाद, स्थान-च्युति, सुख में कमी, शरीर में कृशता, राज-भय — किन्तु ये उपद्रव तभी, जब वर्षेश किसी शुभ ग्रह से मुथशिल न करे; शुभ-इत्थशाल हो तो फल शुभ। (श्लोक 16)'),
('Sun','heen','hi','विदेश-गमन, धन-क्षय, शोक, शत्रु-भय, आलस्य/तन्द्रा, अग्नि-भय, लोकापवाद, कठिन रोग, अति-दुःख; पिता से निराशा तथा पुत्र-मित्र से भय। (श्लोक 17)'),
('Moon','full','hi','धन, स्त्री-पुत्र-मित्र-गृह के अनेक सुख; माला, सुगन्धित चन्दन, इत्र, मोती, वस्त्र-विभूति का लाभ; कुलोचित पद और राजाओं से मित्रता। (श्लोक 19)'),
('Moon','madhya','hi','सामान्य फल; पुत्र-मित्र-वर्ग से शत्रुता, स्थान-भ्रंश, शरीर-कृशता; पाप ग्रह से ईसराफ हो तो कफ-श्लेष्म विकार, खाँसी-श्वास व ज्वर का कष्ट। (श्लोक 20)'),
('Moon','heen','hi','शीत-कफ आदि रोग, चोर-भय, स्वजन-विग्रह; अति-हीन/नीच हो तो दूर-देश गमन, स्त्री-पुत्र सुख-क्षय और मृत्यु-तुल्य कष्ट। (श्लोक 21)'),
('Mars','full','hi','कीर्ति-विजय, शत्रु-नाश, सेनापतित्व/रण-नायकता, प्रतिष्ठा; कुलोचित धन-लाभ, लोक-सम्मान; मित्र-पुत्र-धन-स्त्री का सौख्य। (श्लोक 22)'),
('Mars','madhya','hi','रक्त-विकार (फोड़ा-फुंसी, रुधिर-पीव), अधिक क्रोध, कलह, शस्त्राघात-क्षत; स्वामित्व, अपने गणों पर अधिकार, बल-गौरव — सुख थोड़ा, फल मिश्रित। (श्लोक 23)'),
('Mars','heen','hi','शत्रु-चोर-अग्नि-लोकापवाद (मिथ्या-कलंक) का भय; अपनी ही बुद्धि से हानि, कार्यों में विघ्न, अति-रोग-भय, विदेश-यात्रा; गुरु-दृष्टि न हो तो अपनी कुचाल से नाश। (श्लोक 24)'),
('Mercury','full','hi','वाद-विवाद में विजय (शीघ्र-उत्तर चातुर्य), लेखन व शास्त्रों में प्रवीणता, सद्व्यवहार से विजय व अर्थ-लाभ; ज्ञान, कला, गणित, वैद्य-विद्या में गुरुता; राजाश्रय से नृप-तुल्य पद/राजमन्त्रित्व। (श्लोक 25)'),
('Mercury','madhya','hi','सब फल मध्यम; यात्रा-द्वारा व्यापार-प्रसार; पुत्र-मित्र को सुख — बुध शुभ ग्रहों से इत्थशाल करे तो शुभ; पाप से ईसराफ हो तो अनिष्ट। (श्लोक 26)'),
('Mercury','heen','hi','बल-बुद्धि की हानि, धर्म-क्षय, अपने वाक्य-दोष से अनादर; अनेक विपत्तियाँ; झूठी गवाही का प्रसंग; पराये व्यवहार में पुत्र-धन-मित्र की हानि। (श्लोक 27)'),
('Jupiter','full','hi','परिवार-सुख, धर्म-लाभ, गुण-ग्राहकता, धन-कीर्ति-पुत्र सुख; लोक-विश्वास, उत्तम बुद्धि, पराक्रम-प्राप्ति, निधि-लाभ; राजा से सम्मान और शत्रु-नाश। (श्लोक 28)'),
('Jupiter','madhya','hi','उत्तम फल साधारण रूप में; ज्ञान-शास्त्र-परता, नृप-संगम; पाप ग्रह से ईसराफ हो तो दारिद्र्य, धन-विलय और स्त्री-पीड़ा। (श्लोक 29)'),
('Jupiter','heen','hi','धन-धर्म-सुख की हानि; पुत्र-मित्र-कुटुम्बीजन व स्त्री का त्याग/वियोग; लोकापवाद से व्याकुलता, अति-कष्ट, शरीर में कफ-रोग; शत्रु-भय व कलह। (श्लोक 30)'),
('Venus','full','hi','आरोग्य (नीरुजता), विलास, स्वच्छ-मधुर स्वर, रत्न व मिष्ठान्न-भोग; क्षेम, प्रताप, विजय; वनिता-विलास, हास्य-आनन्द; राजाश्रय से धन व सुख। (श्लोक 31)'),
('Venus','madhya','hi','सब कार्यों में अल्प-प्रवृत्ति; गुप्त दुःख बना रहे; जीविका बँधी हुई; शुक्र पाप-युक्त/पाप-दृष्ट हो तो विपत्ति और धन-नाश। (श्लोक 32)'),
('Venus','heen','hi','मन में संताप, लोक-उपहास, विपद, अपनी जीविका/वृत्ति का नाश; स्त्री-पुत्र-मित्र से द्वेष; कष्ट से अन्न; आरम्भ किए कार्य निष्फल; सुख-अभाव। (श्लोक 33)'),
('Saturn','full','hi','नई भूमि-भवन-क्षेत्र की प्राप्ति; हीन-वर्ण/म्लेच्छ-पक्ष से धन-संचय; बाग-जलाशय (बगीचा, तालाब, कुआँ-बावली) निर्माण-सुख; अंग-पुष्टि; कुलोचित पद व गुणियों में अग्रगण्यता। (श्लोक 34)'),
('Saturn','madhya','hi','सब फल मध्यम; बड़े कष्ट से भोजन; दास, ऊँट, महिष-समूह का लाभ-उपयोग; नीच-जन संसर्ग; सामान्य लाभ — शनि को पाप ग्रह देखे/मिले तो अशुभ फल। (श्लोक 35)'),
('Saturn','heen','hi','सम्पूर्ण क्रियाओं में बाधा/व्यर्थता, धन-नाश, विपत्ति, शत्रु-भय; स्त्री-पुत्र-मित्र-स्वजन से वैर; कदन्न (रूखा-सूखा) भोजन — शुभ ग्रह से इत्थशाल हो तो कुछ सुख (प्राचीन-मत)। (श्लोक 36)')
ON DUPLICATE KEY UPDATE phal_text=VALUES(phal_text);

CREATE TABLE IF NOT EXISTS varshesh_special_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(16) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    situation VARCHAR(160) NOT NULL, phal_text TEXT NOT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_vsr (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO varshesh_special_rules (rule_key,language,situation,phal_text) VALUES
('sp_37','hi','वर्षेश का बल जन्म-काल और वर्ष-काल दोनों में देखें','दोनों में बली → सम्पूर्ण वर्ष शुभ; दोनों में हीन → अनिष्ट; एक में बली-एक में हीन → मध्यम (मिश्रित) फल। (श्लोक 37)'),
('sp_38','hi','वर्षेश जिस ग्रह से इत्थशाल करे','वह ग्रह अपने स्वभाव (संज्ञातन्त्र-स्वरूप) अनुसार फल देता है; शुभ से ईसराफ = थोड़ा शुभ; पाप से ईसराफ = अनिष्ट। (श्लोक 38)'),
('sp_39','hi','वर्षेश मित्र/शत्रु की हद्दा में स्थित होकर इत्थशाल करे','हद्दा-स्वामी के अनुसार फल — शुभ की हद्दा = शुभ, पाप की = अशुभ; जन्म व वर्ष दोनों में समान स्थिति हो तो वैसा ही निश्चित। (श्लोक 39, 43)'),
('sp_40','hi','जन्म में भाव-फल-समर्थ ग्रह का वर्ष-लग्नेश/वर्षेश से ईसराफ','उस वर्ष वह अपना जन्म-कालीन भाव-फल नहीं देता। (श्लोक 40)'),
('sp_41','hi','उक्त ग्रह का ईसराफ न हो','जन्म-भाव-फल मिलता ही है; इत्थशाल हो तो विशेष रूप से मिलता है। (श्लोक 41)'),
('sp_42','hi','उदाहरण-नियम: जन्म का पुत्र-दाता पंचमेश, वर्ष में वर्षेश↔पंचमाधिप मूसरीफ','उस वर्ष पुत्र-विषय में नाश/बाधा; यही नियम हर भावेश पर लागू। (श्लोक 42)'),
('sp_43','hi','वर्षेश मित्र-हद्दा में + चन्द्रमा से इत्थशाल (मित्र-दृष्टि से)','शुभ फलदायक — चन्द्र वर्षेश को तेज देता है। (श्लोक 43)'),
('sp_44','hi','निर्णय-सूत्र','बलाबल (पंचवर्गीय) + तीन योग (इत्थशाल, कम्बूल, ईसराफ) — इन्हीं से वर्ष का शुभाशुभ कहें। (श्लोक 44)'),
('sp_moon18','hi','चन्द्र-मत (श्लोक 18)','रात्रि-जन्म में चन्द्र-राशि का स्वामी जिस ग्रह से इत्थशाल कर रहा हो वही वर्षेश हो और उसका चन्द्रमा से कम्बूल भी हो → वर्ष अत्यन्त उत्तम।'),
('sp_13','hi','वर्षेश की स्थिति (श्लोक 13)','वर्षेश 12, 6, 8 भाव छोड़कर अन्यत्र स्थित/उदित (अस्त नहीं) और जन्म-समय में बली हो → पूर्ण उत्तम फल: आरोग्य, बल-पुष्टि, राज्य-लाभ, अतीव सौख्य।')
ON DUPLICATE KEY UPDATE situation=VALUES(situation),phal_text=VALUES(phal_text);

CREATE TABLE IF NOT EXISTS tajik_trirashi_pati (
    sun_sign TINYINT NOT NULL PRIMARY KEY,
    day_lord VARCHAR(16) NULL, night_lord VARCHAR(16) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO tajik_trirashi_pati (sun_sign,day_lord,night_lord) VALUES
(1,NULL,NULL),(2,NULL,NULL),(3,NULL,NULL),(4,NULL,NULL),(5,NULL,NULL),(6,NULL,NULL),
(7,NULL,NULL),(8,NULL,NULL),(9,NULL,NULL),(10,NULL,NULL),(11,NULL,NULL),(12,NULL,NULL)
ON DUPLICATE KEY UPDATE sun_sign=sun_sign;
-- NULL = अभी अपूरित; admin से संज्ञातन्त्र/PL की त्रि-राशिप सारणी भरें.
-- Engine: NULL रहने पर त्रि-राशिप उम्मीदवार सूची से छूटेगा (शेष 4 से चयन).

CREATE TABLE IF NOT EXISTS panchavargiya_weights (
    component VARCHAR(16) NOT NULL PRIMARY KEY, max_vishwa DECIMAL(5,2) NOT NULL,
    notes VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO panchavargiya_weights (component,max_vishwa,notes) VALUES
('griha',30,'स्वगृह 30 | अति-मित्र 22.5 | मित्र 15 | सम 7.5 | शत्रु 3.75 | अति-शत्रु 1.875 (पंचधा मैत्री)'),
('uccha',20,'(180 - नीच-बिंदु से कोणीय दूरी... ) /180 x 20 — परम-उच्च पर 20'),
('hadda',15,'अपनी हद्दा (tajik_hadda) में 15'),
('drekkana',10,'अपने द्रेष्काण में 10'),
('navamsha',5,'अपने नवांश में 5')
ON DUPLICATE KEY UPDATE max_vishwa=VALUES(max_vishwa),notes=VALUES(notes);

INSERT INTO house_engine_config (cfg_key,cfg_value,notes) VALUES
('varshesh_full_min','45','पंचवर्गीय योग >= यह = पूर्ण-बली (PL से मिलाएँ)'),
('varshesh_madhya_min','30','>= यह = मध्यम; अन्यथा हीन'),
('varshesh_tie_rule','varsha_lagnesh','बल-साम्य वरीयता: varsha_lagnesh / dina_ratri_rashish / moon_ithasala'),
('varshesh_fallback','munthesh','लग्न-द्रष्टा न मिले/सब निर्बल -> munthesh; विकल्प strongest'),
('varshesh_lagna_drishti_min','1','लग्न-दृष्टि मान्यता की न्यूनतम स्फुट-कला'),
('varshesh_check_natal','1','जन्म-कालीन बल भी जाँचें (श्लोक 37)'),
('varshesh_display','both','वर्षेश दिखाएँ: new / old / both (पद्धति-भेद पर)')
ON DUPLICATE KEY UPDATE cfg_value=cfg_value;
-- End of migration 018.