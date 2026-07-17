-- =============================================================================
-- Auto Business — Karaka Prediction FIX (migration 010)  [कारक फल v2]
-- Adds to the karaka engine: (1) planets sitting WITH the karaka (shubh & paap
-- yuti incl. classical specials), (2) karaka combustion with % + signification
-- loss, (3) occupants/drishti of the judged house counted FROM the karaka,
-- (4) कारको भावो नाशाय rule, (5) computed strong/weak scores. Reuses the shared
-- PlanetCondition service (dignity/combustion/maitri from the house fix).
--
-- Renumbered 008 -> 010 (008 = graha fix, 009 = house fix already used).
-- Reconciled with those: karaka_combust_loss + house_engine_config already
-- exist (created by 008/009); this migration matches their schema (loss keyed
-- by `planet`) and only upserts. Self-contained + idempotent.
-- =============================================================================

SET NAMES utf8mb4;

-- 1) KARAKA YUTI RULES ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS karaka_yuti_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(48) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    karaka VARCHAR(16) NULL,          -- specific karaka, or NULL = any karaka
    with_planet VARCHAR(16) NULL,     -- specific companion, or NULL = generic class
    sentence_template TEXT NULL, good_bad VARCHAR(16) NULL, score DECIMAL(4,2) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_kyr (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO karaka_yuti_rules (rule_key,language,karaka,with_planet,sentence_template,good_bad,score) VALUES
-- generic benefic companions
('k_with_jupiter','hi',NULL,'Jupiter','कारक {karaka} के साथ गुरु की युति है — ज्ञान, विस्तार और आशीर्वाद से {signifies} का कारकत्व पुष्ट होता है।','शुभ',1.00),
('k_with_venus','hi',NULL,'Venus','कारक {karaka} के साथ शुक्र की युति है — सुख, सौंदर्य और सामंजस्य से {signifies} के फल मधुर होते हैं।','शुभ',0.75),
('k_with_shubh_mercury','hi',NULL,'Mercury','कारक {karaka} के साथ शुभ बुध की युति है — बुद्धि, विवेक और संवाद से {signifies} को समर्थन मिलता है।','शुभ',0.75),
('k_with_shubh_moon','hi',NULL,'Moon','कारक {karaka} के साथ शुभ (बलवान) चन्द्रमा की युति है — मानसिक अनुकूलता और पोषण से {signifies} सुखद होते हैं।','शुभ',0.75),
-- generic malefic companions
('k_with_saturn','hi',NULL,'Saturn','कारक {karaka} के साथ शनि की युति है — {signifies} के फल मिलते हैं परन्तु विलंब, दूरी और परिश्रम के साथ।','अशुभ',-0.75),
('k_with_mars','hi',NULL,'Mars','कारक {karaka} के साथ मंगल की युति है — {signifies} में आवेश, झगड़ा और जल्दबाजी का दोष आता है।','अशुभ',-0.75),
('k_with_rahu','hi',NULL,'Rahu','कारक {karaka} के साथ राहु की युति है — {signifies} में भ्रम, अपरंपरागत स्थिति और अचानक उतार-चढ़ाव आते हैं।','अशुभ',-1.00),
('k_with_ketu','hi',NULL,'Ketu','कारक {karaka} के साथ केतु की युति है — {signifies} में विच्छेद, अलगाव या वैराग्य की प्रवृत्ति आती है।','अशुभ',-1.00),
-- classical SPECIAL combinations — override the generic row for that pair
('sun_rahu_pitra','hi','Sun','Rahu','पितृ कारक सूर्य राहु के साथ है — पितृ दोष का संकेत; पिता-पक्ष, यश और अधिकार से जुड़ी बाधाएँ संभव।','अशुभ',-1.50),
('sun_ketu_pitra','hi','Sun','Ketu','पितृ कारक सूर्य केतु के साथ है — पितृ दोष का संकेत; पिता से दूरी या पिता-पक्ष के अधूरे कर्तव्य का भार।','अशुभ',-1.50),
('sun_saturn','hi','Sun','Saturn','पितृ कारक सूर्य अपने शत्रु शनि के साथ है — पिता से मतभेद, अधिकार-क्षेत्र में संघर्ष।','अशुभ',-1.00),
('moon_rahu_grahan','hi','Moon','Rahu','मातृ कारक चन्द्रमा राहु के साथ (ग्रहण योग) — माता के स्वास्थ्य और अपनी मानसिक शांति पर विशेष ध्यान आवश्यक।','अशुभ',-1.50),
('moon_ketu_grahan','hi','Moon','Ketu','मातृ कारक चन्द्रमा केतु के साथ (ग्रहण योग) — मन में अकारण बेचैनी; माता से भावनात्मक दूरी संभव।','अशुभ',-1.50),
('moon_saturn_visha','hi','Moon','Saturn','मातृ कारक चन्द्रमा शनि के साथ (विष योग) — मन में भारीपन, निराशा की प्रवृत्ति; माता को कष्ट या उनसे दूरी।','अशुभ',-1.25),
('venus_saturn_vilamb','hi','Venus','Saturn','विवाह कारक शुक्र शनि के साथ है — विवाह/संबंध में विलंब; परिपक्व, व्यावहारिक परन्तु धीमा वैवाहिक सुख।','अशुभ',-0.75),
('venus_rahu','hi','Venus','Rahu','विवाह कारक शुक्र राहु के साथ है — संबंधों में तीव्र आकर्षण परन्तु भ्रम व अपरंपरागत स्थितियाँ।','अशुभ',-1.00),
('venus_mars','hi','Venus','Mars','विवाह कारक शुक्र मंगल के साथ है — संबंधों में आवेश और उग्रता; संयम से ही सामंजस्य।','अशुभ',-0.50),
('jupiter_rahu_chandal','hi','Jupiter','Rahu','संतान/ज्ञान कारक गुरु राहु के साथ (गुरु-चांडाल योग) — ज्ञान व संतान के विषयों में अपरंपरागत मार्ग और बाधाएँ।','अशुभ',-1.25),
('mars_saturn','hi','Mars','Saturn','पराक्रम कारक मंगल शनि के साथ है — भाई-पक्ष में संघर्ष; साहस पर अवरोध, प्रयासों में विलंब।','अशुभ',-1.00)
ON DUPLICATE KEY UPDATE karaka=VALUES(karaka),with_planet=VALUES(with_planet),
sentence_template=VALUES(sentence_template),good_bad=VALUES(good_bad),score=VALUES(score);

-- 2) KARAKA ENGINE SENTENCES (rule_key fits karaka_sentences.VARCHAR(8)) --------
INSERT INTO karaka_sentences (rule_key,language,situation,sentence_template) VALUES
('KST','hi','Karaka status line (always first)','कारक {karaka} {house} भाव में {rashi} राशि में {dignity_word} है{retro} — कारकत्व ({signifies}) {strength_word}।'),
('KCF','hi','Karaka fully combust >=75%','कारक {karaka} सूर्य से केवल {sep}° पर पूर्ण अस्त है ({pct}% अस्त) — {loss_text} अत्यंत क्षीण हो जाता है।'),
('KCM','hi','Karaka combust 40-75%','कारक {karaka} अस्त है ({pct}% अस्त) — {loss_text} की शक्ति स्पष्ट रूप से घटती है।'),
('KCP','hi','Karaka partially combust <40%','कारक {karaka} आंशिक अस्त है ({pct}% अस्त) — {loss_text} में कुछ कमी रहती है।'),
('KOB','hi','Benefic occupant in house counted from karaka','{karaka} से {n}वें भाव में शुभ ग्रह {planet} स्थित है — {inner_meaning} को भीतर से समर्थन मिलता है।'),
('KOM','hi','Malefic occupant in house counted from karaka','{karaka} से {n}वें भाव में अशुभ ग्रह {planet} स्थित है — {inner_meaning} में भीतर से बाधा और कमी आती है।'),
('KDB','hi','Benefic drishti on house counted from karaka','{karaka} से {n}वें भाव पर शुभ ग्रह {planet} की दृष्टि है — आंतरिक अनुभव को बल मिलता है।'),
('KDM','hi','Malefic drishti on house counted from karaka','{karaka} से {n}वें भाव पर अशुभ ग्रह {planet} की दृष्टि है — आंतरिक अनुभव में संघर्ष आता है।'),
('KBN','hi','Karako bhavo nashaya','कारक {karaka} स्वयं {house} भाव (अपने ही कारक-भाव) में बैठा है — "कारको भावो नाशाय": इस भाव के विषयों में अति-सक्रियता से हानि/विलंब संभव; सावधानी रखें।'),
('KBN_SAT8','hi','Exception: Saturn in 8th','आयु कारक शनि स्वयं अष्टम भाव में है — यह अपवाद है: दीर्घायु देता है, परन्तु जीवन में परिश्रम और वैराग्य का अनुभव भी।')
ON DUPLICATE KEY UPDATE situation=VALUES(situation),sentence_template=VALUES(sentence_template);

-- 3) KARAKA COMBUST LOSS TEXT — keyed by `planet` to match migration 008 --------
CREATE TABLE IF NOT EXISTS karaka_combust_loss (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet VARCHAR(16) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    loss_text TEXT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_kcl (planet, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO karaka_combust_loss (planet,language,loss_text) VALUES
('Moon','hi','माता का सुख और अपनी मानसिक शांति का कारकत्व'),
('Mars','hi','भाई-बहनों का सहयोग और साहस का कारकत्व'),
('Mercury','hi','बुद्धि, तर्क और मामा-पक्ष का कारकत्व'),
('Jupiter','hi','संतान-सुख और ज्ञान का कारकत्व'),
('Venus','hi','वैवाहिक सुख और जीवनसाथी का कारकत्व'),
('Saturn','hi','कर्म-स्थिरता और आयु-बल का कारकत्व')
ON DUPLICATE KEY UPDATE loss_text=VALUES(loss_text);
-- Sun is the combustor; Rahu/Ketu are not phala-karakas here.

-- 4) CONFIG ----------------------------------------------------------------------
INSERT INTO house_engine_config (cfg_key,cfg_value,notes) VALUES
('karaka_use_bhavo_nashaya','1','कारको भावो नाशाय नियम चालू/बंद'),
('karaka_strong_threshold','0.5','score >= यह मान → बलवान (S); <= -0.5 → निर्बल (W); बीच में → चिन्ह से'),
('karaka_occupant_weight','0.5','कारक से गिने भाव के occupants/दृष्टि का भार')
ON DUPLICATE KEY UPDATE cfg_value=VALUES(cfg_value),notes=VALUES(notes);

-- End of migration 010.
