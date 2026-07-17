-- =============================================================================
-- Auto Business — Ashtakavarga Bhava-Phala rules (migration 012)
-- 'अष्टकवर्ग मत' section inside Bhava Phaladesh (House Prediction) + Gochar
-- grading rules. Uses EXISTING SAV/BAV computations — no new astronomy.
-- Idempotent. Renumbered from the uploaded 010 (taken) to 012 (next free).
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS av_house_band_phal (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    house TINYINT NOT NULL, band_key VARCHAR(12) NOT NULL,
    min_points TINYINT NOT NULL, max_points SMALLINT NOT NULL,
    language VARCHAR(8) NOT NULL DEFAULT 'hi', phal_text TEXT NOT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_avb (house, band_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO av_house_band_phal (house,band_key,min_points,max_points,language,phal_text) VALUES
(1,'uttam',30,999,'hi','स्वास्थ्य, आत्मविश्वास और व्यक्तित्व — भाव बलवान है; ये विषय जीवन में सहज और अच्छे फलते हैं।'),
(1,'shubh',28,29,'hi','स्वास्थ्य, आत्मविश्वास और व्यक्तित्व — औसत से अच्छा; सामान्यतः शुभ फल मिलते हैं।'),
(1,'samanya',25,27,'hi','स्वास्थ्य, आत्मविश्वास और व्यक्तित्व — फल मिले-जुले; भावेश, कारक और दशा पर निर्भर।'),
(1,'durbal',23,24,'hi','स्वास्थ्य, आत्मविश्वास और व्यक्तित्व — इस क्षेत्र में मेहनत अधिक, फल देर से मिलते हैं।'),
(1,'pidit',0,22,'hi','स्वास्थ्य, आत्मविश्वास और व्यक्तित्व — बहुत कमजोर क्षेत्र; विशेष सावधानी और उपाय करें।'),
(2,'uttam',30,999,'hi','धन-संचय, कुटुंब और वाणी — भाव बलवान है; ये विषय जीवन में सहज और अच्छे फलते हैं।'),
(2,'shubh',28,29,'hi','धन-संचय, कुटुंब और वाणी — औसत से अच्छा; सामान्यतः शुभ फल मिलते हैं।'),
(2,'samanya',25,27,'hi','धन-संचय, कुटुंब और वाणी — फल मिले-जुले; भावेश, कारक और दशा पर निर्भर।'),
(2,'durbal',23,24,'hi','धन-संचय, कुटुंब और वाणी — इस क्षेत्र में मेहनत अधिक, फल देर से मिलते हैं।'),
(2,'pidit',0,22,'hi','धन-संचय, कुटुंब और वाणी — बहुत कमजोर क्षेत्र; विशेष सावधानी और उपाय करें।'),
(3,'uttam',30,999,'hi','साहस, प्रयास और भाई-बहन का साथ — भाव बलवान है; ये विषय जीवन में सहज और अच्छे फलते हैं।'),
(3,'shubh',28,29,'hi','साहस, प्रयास और भाई-बहन का साथ — औसत से अच्छा; सामान्यतः शुभ फल मिलते हैं।'),
(3,'samanya',25,27,'hi','साहस, प्रयास और भाई-बहन का साथ — फल मिले-जुले; भावेश, कारक और दशा पर निर्भर।'),
(3,'durbal',23,24,'hi','साहस, प्रयास और भाई-बहन का साथ — इस क्षेत्र में मेहनत अधिक, फल देर से मिलते हैं।'),
(3,'pidit',0,22,'hi','साहस, प्रयास और भाई-बहन का साथ — बहुत कमजोर क्षेत्र; विशेष सावधानी और उपाय करें।'),
(4,'uttam',30,999,'hi','माता, भवन-वाहन और मन का सुख — भाव बलवान है; ये विषय जीवन में सहज और अच्छे फलते हैं।'),
(4,'shubh',28,29,'hi','माता, भवन-वाहन और मन का सुख — औसत से अच्छा; सामान्यतः शुभ फल मिलते हैं।'),
(4,'samanya',25,27,'hi','माता, भवन-वाहन और मन का सुख — फल मिले-जुले; भावेश, कारक और दशा पर निर्भर।'),
(4,'durbal',23,24,'hi','माता, भवन-वाहन और मन का सुख — इस क्षेत्र में मेहनत अधिक, फल देर से मिलते हैं।'),
(4,'pidit',0,22,'hi','माता, भवन-वाहन और मन का सुख — बहुत कमजोर क्षेत्र; विशेष सावधानी और उपाय करें।'),
(5,'uttam',30,999,'hi','संतान, विद्या और बुद्धि — भाव बलवान है; ये विषय जीवन में सहज और अच्छे फलते हैं।'),
(5,'shubh',28,29,'hi','संतान, विद्या और बुद्धि — औसत से अच्छा; सामान्यतः शुभ फल मिलते हैं।'),
(5,'samanya',25,27,'hi','संतान, विद्या और बुद्धि — फल मिले-जुले; भावेश, कारक और दशा पर निर्भर।'),
(5,'durbal',23,24,'hi','संतान, विद्या और बुद्धि — इस क्षेत्र में मेहनत अधिक, फल देर से मिलते हैं।'),
(5,'pidit',0,22,'hi','संतान, विद्या और बुद्धि — बहुत कमजोर क्षेत्र; विशेष सावधानी और उपाय करें।'),
(6,'uttam',30,999,'hi','रोगों से लड़ने की शक्ति और शत्रुओं पर विजय की क्षमता अच्छी — पर ऋण/विवाद का क्षेत्र सक्रिय भी रहता है।'),
(6,'shubh',28,29,'hi','रोग-प्रतिरोध ठीक; प्रतियोगिता और विवादों में जीत संभव।'),
(6,'samanya',25,27,'hi','रोग, ऋण और शत्रु-पक्ष साधारण स्तर पर — बड़ा भय नहीं।'),
(6,'durbal',23,24,'hi','रोग-ऋण से बचाव कमजोर; लग्न बलवान हो तो स्थिति संभल जाती है।'),
(6,'pidit',0,22,'hi','रोग, ऋण और शत्रु-पक्ष हावी हो सकते हैं — स्वास्थ्य और लेन-देन में सतर्क रहें।'),
(7,'uttam',30,999,'hi','विवाह, जीवनसाथी और साझेदारी — भाव बलवान है; ये विषय जीवन में सहज और अच्छे फलते हैं।'),
(7,'shubh',28,29,'hi','विवाह, जीवनसाथी और साझेदारी — औसत से अच्छा; सामान्यतः शुभ फल मिलते हैं।'),
(7,'samanya',25,27,'hi','विवाह, जीवनसाथी और साझेदारी — फल मिले-जुले; भावेश, कारक और दशा पर निर्भर।'),
(7,'durbal',23,24,'hi','विवाह, जीवनसाथी और साझेदारी — इस क्षेत्र में मेहनत अधिक, फल देर से मिलते हैं।'),
(7,'pidit',0,22,'hi','विवाह, जीवनसाथी और साझेदारी — बहुत कमजोर क्षेत्र; विशेष सावधानी और उपाय करें।'),
(8,'uttam',30,999,'hi','दीर्घायु का अच्छा संकेत — अचानक घटनाओं और संकटों से बचाव मिलता है।'),
(8,'shubh',28,29,'hi','आयु-बल अच्छा; गूढ़ विषयों में रुचि फलदायी।'),
(8,'samanya',25,27,'hi','आयु-बल सामान्य।'),
(8,'durbal',23,24,'hi','स्वास्थ्य के प्रति सतर्क रहें; जोखिम भरे काम सोच-समझकर करें।'),
(8,'pidit',0,22,'hi','आयु/स्वास्थ्य का संवेदनशील क्षेत्र — नियमित जाँच, बीमा और सावधानी आवश्यक।'),
(9,'uttam',30,999,'hi','भाग्य, धर्म और पिता का सहयोग — भाव बलवान है; ये विषय जीवन में सहज और अच्छे फलते हैं।'),
(9,'shubh',28,29,'hi','भाग्य, धर्म और पिता का सहयोग — औसत से अच्छा; सामान्यतः शुभ फल मिलते हैं।'),
(9,'samanya',25,27,'hi','भाग्य, धर्म और पिता का सहयोग — फल मिले-जुले; भावेश, कारक और दशा पर निर्भर।'),
(9,'durbal',23,24,'hi','भाग्य, धर्म और पिता का सहयोग — इस क्षेत्र में मेहनत अधिक, फल देर से मिलते हैं।'),
(9,'pidit',0,22,'hi','भाग्य, धर्म और पिता का सहयोग — बहुत कमजोर क्षेत्र; विशेष सावधानी और उपाय करें।'),
(10,'uttam',30,999,'hi','करियर, पद और प्रतिष्ठा — भाव बलवान; 36+ बिंदु हों तो उच्च पद-अधिकार का विशेष योग।'),
(10,'shubh',28,29,'hi','करियर, पद और प्रतिष्ठा — औसत से अच्छा; सामान्यतः शुभ फल मिलते हैं।'),
(10,'samanya',25,27,'hi','करियर, पद और प्रतिष्ठा — फल मिले-जुले; भावेश, कारक और दशा पर निर्भर।'),
(10,'durbal',23,24,'hi','करियर, पद और प्रतिष्ठा — इस क्षेत्र में मेहनत अधिक, फल देर से मिलते हैं।'),
(10,'pidit',0,22,'hi','करियर, पद और प्रतिष्ठा — बहुत कमजोर क्षेत्र; विशेष सावधानी और उपाय करें।'),
(11,'uttam',30,999,'hi','आय, लाभ और इच्छापूर्ति — इच्छाएँ पूरी होती हैं, आय अच्छी; बिंदु जितने अधिक, उतना श्रेष्ठ।'),
(11,'shubh',28,29,'hi','आय, लाभ और इच्छापूर्ति — औसत से अच्छा; सामान्यतः शुभ फल मिलते हैं।'),
(11,'samanya',25,27,'hi','आय, लाभ और इच्छापूर्ति — फल मिले-जुले; भावेश, कारक और दशा पर निर्भर।'),
(11,'durbal',23,24,'hi','आय, लाभ और इच्छापूर्ति — इस क्षेत्र में मेहनत अधिक, फल देर से मिलते हैं।'),
(11,'pidit',0,22,'hi','आय, लाभ और इच्छापूर्ति — बहुत कमजोर क्षेत्र; विशेष सावधानी और उपाय करें।'),
(12,'uttam',30,999,'hi','व्यय बहुत अधिक — आय से ज़्यादा खर्च का योग; विदेश-संबंध प्रबल (विदेश के लिए शुभ, बचत के लिए अशुभ)।'),
(12,'shubh',28,29,'hi','खर्च अधिक रहता है; विदेश/यात्रा और एकांत-साधना के योग।'),
(12,'samanya',25,27,'hi','व्यय सामान्य — आय-व्यय में संतुलन रखें।'),
(12,'durbal',23,24,'hi','व्यय नियंत्रित — धन टिकता है; शुभ।'),
(12,'pidit',0,22,'hi','हानि-व्यय बहुत सीमित — शुभ; परन्तु शय्यासुख, विदेश और विश्राम के विषय मंद रहते हैं।')
ON DUPLICATE KEY UPDATE min_points=VALUES(min_points),max_points=VALUES(max_points),phal_text=VALUES(phal_text);

CREATE TABLE IF NOT EXISTS av_compare_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(16) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    condition_expr VARCHAR(64) NOT NULL, houses_involved VARCHAR(16) NOT NULL,
    phal_text TEXT NOT NULL, good_bad VARCHAR(12) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_avc (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO av_compare_rules (rule_key,language,condition_expr,houses_involved,phal_text,good_bad) VALUES
('c_11_gt_10','hi','SAV11>SAV10','10,11','परिश्रम से अधिक लाभ मिलता है — करियर सुखद रहता है।','शुभ'),
('c_10_gt_11','hi','SAV10>SAV11','10,11','मेहनत अधिक, प्रतिफल कम — करियर में संघर्ष रहता है।','अशुभ'),
('c_11_gt_12','hi','SAV11>SAV12','11,12','आय व्यय से अधिक — धन टिकता है।','शुभ'),
('c_12_gt_11','hi','SAV12>SAV11','11,12','व्यय आय से अधिक — धन नहीं टिकता; बजट पर ध्यान दें।','अशुभ'),
('c_1_gt_12','hi','SAV1>SAV12','1,12','उपलब्धि हानि से अधिक — जीवन में संतोष रहता है।','शुभ'),
('c_1_gt_6','hi','SAV1>SAV6','1,6','रोग और शत्रुओं पर जीत की क्षमता — लग्न भारी पड़ता है।','शुभ'),
('c_6_gt_1','hi','SAV6>SAV1','1,6','रोग-ऋण-विवाद हावी हो सकते हैं — स्वास्थ्य/लेन-देन में सावधानी।','अशुभ'),
('c_dhan','hi','SAV2>=30&&SAV11>=30','2,11','स्थायी धन-संचय का श्रेष्ठ योग।','शुभ'),
('c_all4','hi','SAV11>SAV10&&SAV11>SAV12&&SAV1>SAV12','1,10,11,12','धनी और सुखी जीवन का उत्तम अष्टकवर्ग संकेत।','शुभ'),
('c_tribhag','hi','TRIBHAG','1,5,9','जिस खंड का योग सबसे अधिक — जीवन का वही चरण (पूर्वार्ध/मध्य/उत्तरार्ध) सबसे अनुकूल।','सूचना')
ON DUPLICATE KEY UPDATE condition_expr=VALUES(condition_expr),houses_involved=VALUES(houses_involved),phal_text=VALUES(phal_text),good_bad=VALUES(good_bad);

CREATE TABLE IF NOT EXISTS av_bav_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(16) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    situation VARCHAR(96) NULL, sentence_template TEXT NOT NULL, score DECIMAL(4,2) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_avr (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO av_bav_rules (rule_key,language,situation,sentence_template,score) VALUES
('b_occ_5plus','hi','भाव में बैठे ग्रह के अपने BAV बिंदु 5-8','{planet} के यहाँ {bav} बिंदु हैं — यह इस भाव में शुभ फल देने में समर्थ है।',1.0),
('b_occ_4','hi','बिंदु 4','{planet} के यहाँ 4 बिंदु हैं — फल मध्यम रहेंगे।',0.0),
('b_occ_0_3','hi','बिंदु 0-3','{planet} के यहाँ केवल {bav} बिंदु हैं — उच्च/शुभ होते हुए भी पूरा फल नहीं दे पाएगा।',-1.0),
('b_lord','hi','भावेश जिस राशि में बैठा है, वहाँ उसके अपने BAV 4+','भावेश {lord} अपनी स्थिति-राशि में {bav} बिंदु रखता है — भाव को भीतर से बल देता है।',0.5),
('b_lord_low','hi','भावेश के अपने BAV ≤3','भावेश {lord} के अपनी राशि में केवल {bav} बिंदु — भाव को स्वामी से बल नहीं मिलता।',-0.5),
('b_karaka','hi','भाव के कारक ग्रह के अपने BAV 5+ (अपनी स्थिति-राशि में)','कारक {karaka} बिंदु-बल से पुष्ट — इस विषय का भीतरी सुख बना रहता है।',0.5)
ON DUPLICATE KEY UPDATE situation=VALUES(situation),sentence_template=VALUES(sentence_template),score=VALUES(score);

CREATE TABLE IF NOT EXISTS av_gochar_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(20) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    situation VARCHAR(96) NULL, phal_text TEXT NOT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_avg (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO av_gochar_rules (rule_key,language,situation,phal_text) VALUES
('g_bav5','hi','गोचर ग्रह के, गोचर-राशि में अपने BAV 5+','यह गोचर शुभ फल देगा।'),
('g_bav4','hi','बिंदु 4','गोचर के फल साधारण।'),
('g_bav3','hi','बिंदु ≤3','शुभ दिखने वाला गोचर भी फीका/प्रतिकूल रहेगा।'),
('g_malefic_low','hi','शनि/मंगल/राहु का गोचर <25 SAV वाले भाव से','कष्ट की तीव्रता अधिक — वह अवधि सावधानी की।'),
('g_malefic_high','hi','अशुभ ग्रह का गोचर 30+ SAV वाले भाव से','हानि बहुत घट जाती है।'),
('g_sadesati','hi','साढ़े साती की तीव्रता','12वें, 1ले, 2रे भावों का SAV जितना ऊँचा, साढ़े साती उतनी सहनीय।')
ON DUPLICATE KEY UPDATE situation=VALUES(situation),phal_text=VALUES(phal_text);

INSERT INTO house_engine_config (cfg_key,cfg_value,notes) VALUES
('av_band_uttam','30','SAV >= 30 = बलवान'),('av_band_shubh','28','28-29 = शुभ'),
('av_band_samanya','25','25-27 = सामान्य'),('av_band_durbal','23','23-24 = कमजोर; <=22 अति पीड़ित'),
('av_h10_vishesh','36','10वें भाव का विशेष उच्च-पद योग'),('av_bav_samarth','5','occupant/गोचर BAV शुभ सीमा'),
('av_bav_lord_ok','4','भावेश BAV बल सीमा'),('av_section_weight','0.5','House verdict में अष्टकवर्ग खंड का भार')
ON DUPLICATE KEY UPDATE cfg_value=cfg_value;
-- End of migration 012.