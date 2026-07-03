-- =============================================================================
-- Auto Business — Bhava Bala Bhava-Phala rules (migration 013)
-- 'भाव बल मत' section inside Bhava Phaladesh, directly BELOW 'अष्टकवर्ग मत'.
-- Uses EXISTING Bhava Bala (virupa) + Shadbala values — no new calculation.
-- Idempotent. Renumbered from the uploaded 011 (taken) to 013 (next free).
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS bb_house_band_phal (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    house TINYINT NOT NULL, band_key VARCHAR(12) NOT NULL,
    min_virupa SMALLINT NOT NULL, max_virupa SMALLINT NOT NULL,
    language VARCHAR(8) NOT NULL DEFAULT 'hi', phal_text TEXT NOT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_bbb (house, band_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bb_house_band_phal (house,band_key,min_virupa,max_virupa,language,phal_text) VALUES
(1,'balwan',480,9999,'hi','स्वास्थ्य, आत्मबल और व्यक्तित्व — भाव की नींव मजबूत; ये विषय जीवन में पूर्ण फल देते हैं।'),
(1,'shubh',450,479,'hi','स्वास्थ्य, आत्मबल और व्यक्तित्व — अच्छा बल; सामान्यतः शुभ और स्थिर फल।'),
(1,'madhyam',420,449,'hi','स्वास्थ्य, आत्मबल और व्यक्तित्व — मध्यम बल; फल दशा-गोचर के सहारे उभरते हैं।'),
(1,'durbal',390,419,'hi','स्वास्थ्य, आत्मबल और व्यक्तित्व — बल कम; इस क्षेत्र में विलंब और अधूरापन रहता है।'),
(1,'ati_durbal',0,389,'hi','स्वास्थ्य, आत्मबल और व्यक्तित्व — लग्न अति दुर्बल; अन्य भाव बली हों तो भी फल भोगने की क्षमता घटती है — देह और आत्मविश्वास पर सबसे पहले काम करें।'),
(2,'balwan',480,9999,'hi','धन-संचय, कुटुंब और वाणी — भाव की नींव मजबूत; ये विषय जीवन में पूर्ण फल देते हैं।'),
(2,'shubh',450,479,'hi','धन-संचय, कुटुंब और वाणी — अच्छा बल; सामान्यतः शुभ और स्थिर फल।'),
(2,'madhyam',420,449,'hi','धन-संचय, कुटुंब और वाणी — मध्यम बल; फल दशा-गोचर के सहारे उभरते हैं।'),
(2,'durbal',390,419,'hi','धन-संचय, कुटुंब और वाणी — बल कम; इस क्षेत्र में विलंब और अधूरापन रहता है।'),
(2,'ati_durbal',0,389,'hi','धन-संचय, कुटुंब और वाणी — भाव अति दुर्बल; यह जीवन की परीक्षा का क्षेत्र है — धैर्य व उपाय रखें।'),
(3,'balwan',480,9999,'hi','साहस, प्रयास और भाई-बहन — भाव की नींव मजबूत; ये विषय जीवन में पूर्ण फल देते हैं।'),
(3,'shubh',450,479,'hi','साहस, प्रयास और भाई-बहन — अच्छा बल; सामान्यतः शुभ और स्थिर फल।'),
(3,'madhyam',420,449,'hi','साहस, प्रयास और भाई-बहन — मध्यम बल; फल दशा-गोचर के सहारे उभरते हैं।'),
(3,'durbal',390,419,'hi','साहस, प्रयास और भाई-बहन — बल कम; इस क्षेत्र में विलंब और अधूरापन रहता है।'),
(3,'ati_durbal',0,389,'hi','साहस, प्रयास और भाई-बहन — भाव अति दुर्बल; यह जीवन की परीक्षा का क्षेत्र है — धैर्य व उपाय रखें।'),
(4,'balwan',480,9999,'hi','माता, भवन-वाहन और मन का सुख — भाव की नींव मजबूत; ये विषय जीवन में पूर्ण फल देते हैं।'),
(4,'shubh',450,479,'hi','माता, भवन-वाहन और मन का सुख — अच्छा बल; सामान्यतः शुभ और स्थिर फल।'),
(4,'madhyam',420,449,'hi','माता, भवन-वाहन और मन का सुख — मध्यम बल; फल दशा-गोचर के सहारे उभरते हैं।'),
(4,'durbal',390,419,'hi','माता, भवन-वाहन और मन का सुख — बल कम; इस क्षेत्र में विलंब और अधूरापन रहता है।'),
(4,'ati_durbal',0,389,'hi','माता, भवन-वाहन और मन का सुख — भाव अति दुर्बल; यह जीवन की परीक्षा का क्षेत्र है — धैर्य व उपाय रखें।'),
(5,'balwan',480,9999,'hi','संतान, विद्या और बुद्धि — भाव की नींव मजबूत; ये विषय जीवन में पूर्ण फल देते हैं।'),
(5,'shubh',450,479,'hi','संतान, विद्या और बुद्धि — अच्छा बल; सामान्यतः शुभ और स्थिर फल।'),
(5,'madhyam',420,449,'hi','संतान, विद्या और बुद्धि — मध्यम बल; फल दशा-गोचर के सहारे उभरते हैं।'),
(5,'durbal',390,419,'hi','संतान, विद्या और बुद्धि — बल कम; इस क्षेत्र में विलंब और अधूरापन रहता है।'),
(5,'ati_durbal',0,389,'hi','संतान, विद्या और बुद्धि — भाव अति दुर्बल; यह जीवन की परीक्षा का क्षेत्र है — धैर्य व उपाय रखें।'),
(6,'balwan',480,9999,'hi','लड़ने की शक्ति प्रबल — रोग-प्रतिरोध, मुकदमे व प्रतियोगिता में जीत; सेवा-क्षेत्र में सफलता। पर ऋण-विवाद जीवन में सक्रिय रहते हैं।'),
(6,'shubh',450,479,'hi','प्रतिरोध-क्षमता अच्छी; विवादों में पलड़ा भारी।'),
(6,'madhyam',420,449,'hi','रोग-ऋण-शत्रु साधारण स्तर पर।'),
(6,'durbal',390,419,'hi','प्रतिरोध-क्षमता कम — रोग आए तो लंबा चले; ऋण-विवाद से दूर रहें।'),
(6,'ati_durbal',0,389,'hi','रोग-ऋण से लड़ने की शक्ति बहुत कम — स्वास्थ्य-अनुशासन और लेन-देन में सतर्कता आवश्यक।'),
(7,'balwan',480,9999,'hi','विवाह और साझेदारी — भाव की नींव मजबूत; ये विषय जीवन में पूर्ण फल देते हैं।'),
(7,'shubh',450,479,'hi','विवाह और साझेदारी — अच्छा बल; सामान्यतः शुभ और स्थिर फल।'),
(7,'madhyam',420,449,'hi','विवाह और साझेदारी — मध्यम बल; फल दशा-गोचर के सहारे उभरते हैं।'),
(7,'durbal',390,419,'hi','विवाह और साझेदारी — बल कम; इस क्षेत्र में विलंब और अधूरापन रहता है।'),
(7,'ati_durbal',0,389,'hi','विवाह और साझेदारी — भाव अति दुर्बल; यह जीवन की परीक्षा का क्षेत्र है — धैर्य व उपाय रखें।'),
(8,'balwan',480,9999,'hi','आयु-बल और संकट से बच निकलने की क्षमता प्रबल; गूढ़ विद्या, शोध, बीमा-विरासत से लाभ। पर जीवन में आकस्मिक मोड़ अधिक।'),
(8,'shubh',450,479,'hi','आयु-बल अच्छा; गहराई वाले विषयों में सफलता।'),
(8,'madhyam',420,449,'hi','आयु-बल सामान्य।'),
(8,'durbal',390,419,'hi','आकस्मिकता कम (शुभ) पर आयु-बल साधारण — तब लग्न और शनि का बल निर्णायक।'),
(8,'ati_durbal',0,389,'hi','आयु/स्वास्थ्य का संवेदनशील क्षेत्र — नियमित जाँच और सावधानी; जोखिम टालें।'),
(9,'balwan',480,9999,'hi','भाग्य, धर्म और पिता का सहयोग — भाव की नींव मजबूत; ये विषय जीवन में पूर्ण फल देते हैं।'),
(9,'shubh',450,479,'hi','भाग्य, धर्म और पिता का सहयोग — अच्छा बल; सामान्यतः शुभ और स्थिर फल।'),
(9,'madhyam',420,449,'hi','भाग्य, धर्म और पिता का सहयोग — मध्यम बल; फल दशा-गोचर के सहारे उभरते हैं।'),
(9,'durbal',390,419,'hi','भाग्य, धर्म और पिता का सहयोग — बल कम; इस क्षेत्र में विलंब और अधूरापन रहता है।'),
(9,'ati_durbal',0,389,'hi','भाग्य, धर्म और पिता का सहयोग — भाव अति दुर्बल; यह जीवन की परीक्षा का क्षेत्र है — धैर्य व उपाय रखें।'),
(10,'balwan',480,9999,'hi','करियर, पद और अधिकार — नींव मजबूत; कर्मक्षेत्र जीवन की धुरी बनता है, पद-प्रतिष्ठा निश्चित।'),
(10,'shubh',450,479,'hi','करियर, पद और अधिकार — अच्छा बल; सामान्यतः शुभ और स्थिर फल।'),
(10,'madhyam',420,449,'hi','करियर, पद और अधिकार — मध्यम बल; फल दशा-गोचर के सहारे उभरते हैं।'),
(10,'durbal',390,419,'hi','करियर, पद और अधिकार — बल कम; इस क्षेत्र में विलंब और अधूरापन रहता है।'),
(10,'ati_durbal',0,389,'hi','करियर, पद और अधिकार — भाव अति दुर्बल; यह जीवन की परीक्षा का क्षेत्र है — धैर्य व उपाय रखें।'),
(11,'balwan',480,9999,'hi','आय, लाभ और मित्र-वर्ग — भाव की नींव मजबूत; ये विषय जीवन में पूर्ण फल देते हैं।'),
(11,'shubh',450,479,'hi','आय, लाभ और मित्र-वर्ग — अच्छा बल; सामान्यतः शुभ और स्थिर फल।'),
(11,'madhyam',420,449,'hi','आय, लाभ और मित्र-वर्ग — मध्यम बल; फल दशा-गोचर के सहारे उभरते हैं।'),
(11,'durbal',390,419,'hi','आय, लाभ और मित्र-वर्ग — बल कम; इस क्षेत्र में विलंब और अधूरापन रहता है।'),
(11,'ati_durbal',0,389,'hi','आय, लाभ और मित्र-वर्ग — भाव अति दुर्बल; यह जीवन की परीक्षा का क्षेत्र है — धैर्य व उपाय रखें।'),
(12,'balwan',480,9999,'hi','व्यय-धारा प्रबल — बचत के लिए प्रतिकूल, पर विदेश-वास, सेवा-संस्थान और ध्यान-मोक्ष के लिए अनुकूल; क्षेत्र देखकर फल कहें।'),
(12,'shubh',450,479,'hi','व्यय-विदेश-एकांत के योग सक्रिय; खर्च नियोजित रखें।'),
(12,'madhyam',420,449,'hi','व्यय सामान्य — आय-व्यय संतुलन बनाए रखें।'),
(12,'durbal',390,419,'hi','हानि सीमित (सांसारिक दृष्टि से शुभ); विदेश-विश्राम के विषय मंद।'),
(12,'ati_durbal',0,389,'hi','हानि-व्यय बहुत सीमित — शुभ; पर नींद, विश्राम और एकांत-साधना की कमी खल सकती है।')
ON DUPLICATE KEY UPDATE min_virupa=VALUES(min_virupa),max_virupa=VALUES(max_virupa),phal_text=VALUES(phal_text);

CREATE TABLE IF NOT EXISTS bb_rank_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(16) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    situation VARCHAR(96) NULL, phal_text TEXT NOT NULL, score DECIMAL(4,2) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_bbr (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bb_rank_rules (rule_key,language,situation,phal_text,score) VALUES
('r_top3','hi','यह भाव बारहों में शीर्ष-3 बली में है','यह जीवन के मुख्य वरदान-क्षेत्रों में है — इसके विषय सहज फलते हैं।',1.0),
('r_mid6','hi','मध्य-6 में','फल सामान्य — दशा-गोचर अनुकूल होने पर उभरते हैं।',0.0),
('r_bottom3','hi','अंतिम-3 (सबसे कम बली) में','यह जीवन का कमजोर क्षेत्र है — यहीं संघर्ष व विलंब; उपाय-योग्य।',-1.0),
('r_spread_high','hi','सबसे बली और सबसे निर्बल भाव का अंतर बड़ा (config: 150+ विरूपा)','जीवन में उतार-चढ़ाव तीखे — बली क्षेत्रों का सहारा लेकर निर्बल सँभालें।',0.0),
('r_spread_low','hi','अंतर छोटा (<80 विरूपा)','सभी क्षेत्र पास-पास — संतुलित, स्थिर जीवन।',0.0)
ON DUPLICATE KEY UPDATE situation=VALUES(situation),phal_text=VALUES(phal_text),score=VALUES(score);

CREATE TABLE IF NOT EXISTS bb_compare_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(16) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    condition_expr VARCHAR(32) NOT NULL, houses_involved VARCHAR(12) NOT NULL,
    phal_text TEXT NOT NULL, good_bad VARCHAR(12) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_bbc (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bb_compare_rules (rule_key,language,condition_expr,houses_involved,phal_text,good_bad) VALUES
('bb_1_gt_8','hi','BB1>BB8','1,8','स्वास्थ्य-बल संकटों पर भारी — संकट से उबरने की क्षमता अच्छी।','शुभ'),
('bb_8_gt_1','hi','BB8>BB1','1,8','स्वास्थ्य-संकट भारी पड़ सकते हैं — लग्न-बल बढ़ाने वाले उपाय करें।','अशुभ'),
('bb_1_gt_6','hi','BB1>BB6','1,6','रोग-शत्रु पर जातक की जीत — प्रतियोगिता में पलड़ा भारी।','शुभ'),
('bb_6_gt_1','hi','BB6>BB1','1,6','रोग-ऋण-विवाद हावी हो सकते हैं — स्वास्थ्य और लेन-देन में सावधानी।','अशुभ'),
('bb_2_gt_12','hi','BB2>BB12','2,12','कमाया हुआ टिकता है — संचय बनता है।','शुभ'),
('bb_12_gt_2','hi','BB12>BB2','2,12','धन हाथ में आकर निकल जाता है — बजट-अनुशासन रखें।','अशुभ'),
('bb_11_gt_12','hi','BB11>BB12','11,12','आय व्यय पर भारी — समृद्धि का मूल सूत्र।','शुभ'),
('bb_12_gt_11','hi','BB12>BB11','11,12','आय से अधिक व्यय — क्लासिक चेतावनी।','अशुभ'),
('bb_7_gt_6','hi','BB7>BB6','6,7','साझेदारी विवादों पर भारी — साझा व्यापार शुभ।','शुभ'),
('bb_6_gt_7','hi','BB6>BB7','6,7','साझे में विवाद की आशंका — partnership सोच-समझकर।','अशुभ'),
('bb_10_gt_9','hi','BB10>BB9','9,10','कर्म भाग्य से आगे — self-made योग; परिश्रम ही मार्ग।','शुभ'),
('bb_9_gt_10','hi','BB9>BB10','9,10','भाग्य-सहारा प्रबल — कम श्रम में भी काम बनते हैं।','शुभ'),
('bb_4_gt_10','hi','BB4>BB10','4,10','जीवन का झुकाव घर-परिवार-सुख की ओर।','सूचना'),
('bb_10_gt_4','hi','BB10>BB4','4,10','जीवन का झुकाव करियर की ओर।','सूचना')
ON DUPLICATE KEY UPDATE condition_expr=VALUES(condition_expr),houses_involved=VALUES(houses_involved),phal_text=VALUES(phal_text),good_bad=VALUES(good_bad);

CREATE TABLE IF NOT EXISTS bb_lord_match (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(8) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    situation VARCHAR(64) NULL, phal_text TEXT NOT NULL, score DECIMAL(4,2) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_bbl (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bb_lord_match (rule_key,language,situation,phal_text,score) VALUES
('lm_ss','hi','भाव बली + भावेश (षड्बल) बली','श्रेष्ठ स्थिति — नींव और स्वामी दोनों मजबूत; पूर्ण, स्थायी फल।',1.0),
('lm_sw','hi','भाव बली, भावेश निर्बल','फल दिखते हैं पर टिकते नहीं — भवन सुंदर, नींव कमजोर; स्वामी के उपाय करें।',-0.5),
('lm_ws','hi','भाव निर्बल, भावेश बली','विलंब से, संघर्ष के बाद फल — स्वामी की दशा में क्षेत्र सँभलता है।',0.0),
('lm_ww','hi','भाव और भावेश दोनों निर्बल','यह क्षेत्र जीवन-भर की परीक्षा — धैर्य, उपाय और यथार्थ अपेक्षाएँ।',-1.0)
ON DUPLICATE KEY UPDATE situation=VALUES(situation),phal_text=VALUES(phal_text),score=VALUES(score);

INSERT INTO house_pred_templates (rule_key,language,sentence_template,label) VALUES
('bb_dasha_lord','hi','{lord} (भावेश) की दशा/अंतर्दशा चल रही है — इस भाव के विषय इसी काल में सक्रिय हैं ({phal_word})।','BB timing: lord dasha running'),
('bb_dasha_occupant','hi','{planet} की दशा चल रही है — इस भाव (जहाँ वह बैठा है) के फल सक्रिय काल में हैं।','BB timing: occupant dasha running')
ON DUPLICATE KEY UPDATE sentence_template=VALUES(sentence_template),label=VALUES(label);

INSERT INTO house_engine_config (cfg_key,cfg_value,notes) VALUES
('bb_band_balwan','480','>=480 विरूपा = बलवान'),('bb_band_shubh','450','450-479 = शुभ'),
('bb_band_madhyam','420','420-449 = मध्यम'),('bb_band_durbal','390','390-419 = दुर्बल; <390 अति दुर्बल'),
('bb_spread_high','150','max-min इससे अधिक = तीखे उतार-चढ़ाव'),('bb_spread_low','80','इससे कम = संतुलित'),
('bb_section_weight','0.5','House verdict में भाव बल खंड का भार')
ON DUPLICATE KEY UPDATE cfg_value=cfg_value;
-- End of migration 013.