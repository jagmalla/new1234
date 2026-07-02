-- =============================================================================
-- Auto Business — House Prediction FIX (migration 009)
-- Adds: planet dignity (exalt/debil/moolatrikona/own with degrees),
-- combustion orbs (per planet, with retro orbs + percentage), yuti (conjunction)
-- rules, Rahu/Ketu friendships, new output sentences, engine config flags.
-- Renumbered 007->009 (007/008 already used). Idempotent; safe to re-run. Import:
--   mysql -u USER -p auto_business < migrations/009_house_prediction_fix.sql
-- =============================================================================

SET NAMES utf8mb4;

-- 1) DIGNITY -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS planet_dignity (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet VARCHAR(16) NOT NULL,
    exalt_sign TINYINT NULL, deep_exalt_deg DECIMAL(5,2) NULL,
    debil_sign TINYINT NULL, deep_debil_deg DECIMAL(5,2) NULL,
    mt_sign TINYINT NULL, mt_deg_from DECIMAL(5,2) NULL, mt_deg_to DECIMAL(5,2) NULL,
    own_signs VARCHAR(16) NULL,           -- comma list of sign numbers 1..12
    notes TEXT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_dig (planet)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO planet_dignity (planet,exalt_sign,deep_exalt_deg,debil_sign,deep_debil_deg,mt_sign,mt_deg_from,mt_deg_to,own_signs,notes) VALUES
('Sun',     1,10, 7,10, 5, 0,20,'5',  'मूल त्रिकोण सिंह 0-20°, स्वराशि सिंह 20-30°'),
('Moon',    2, 3, 8, 3, 2, 3,30,'4',  'उच्च वृषभ 0-3°, मूल त्रिकोण वृषभ 3-30°, स्वराशि कर्क'),
('Mars',   10,28, 4,28, 1, 0,12,'1,8','मूल त्रिकोण मेष 0-12°'),
('Mercury', 6,15,12,15, 6,15,20,'3,6','उच्च कन्या 0-15°, मूल त्रिकोण 15-20°, स्वराशि 20-30° व मिथुन'),
('Jupiter', 4, 5,10, 5, 9, 0,10,'9,12','मूल त्रिकोण धनु 0-10°'),
('Venus',  12,27, 6,27, 7, 0,15,'2,7','मूल त्रिकोण तुला 0-15°'),
('Saturn',  7,20, 1,20,11, 0,20,'10,11','मूल त्रिकोण कुम्भ 0-20°'),
('Rahu',    2,NULL, 8,NULL,NULL,NULL,NULL,NULL,'परम्परा-भेद: admin config से बदल सकते हैं (कुछ मत: मिथुन उच्च)'),
('Ketu',    8,NULL, 2,NULL,NULL,NULL,NULL,NULL,'परम्परा-भेद: admin config से बदल सकते हैं (कुछ मत: धनु उच्च)')
ON DUPLICATE KEY UPDATE exalt_sign=VALUES(exalt_sign),deep_exalt_deg=VALUES(deep_exalt_deg),
debil_sign=VALUES(debil_sign),deep_debil_deg=VALUES(deep_debil_deg),mt_sign=VALUES(mt_sign),
mt_deg_from=VALUES(mt_deg_from),mt_deg_to=VALUES(mt_deg_to),own_signs=VALUES(own_signs),notes=VALUES(notes);

-- 2) COMBUSTION ORBS ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS combustion_orbs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet VARCHAR(16) NOT NULL,
    orb_deg DECIMAL(5,2) NOT NULL,       -- combust if |planet - Sun| < orb
    orb_deg_retro DECIMAL(5,2) NULL,     -- orb when the planet is retrograde
    notes TEXT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_comb (planet)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO combustion_orbs (planet,orb_deg,orb_deg_retro,notes) VALUES
('Moon',   12.00, NULL,  'चन्द्रमा शीघ्र अस्त/क्षीण — अमावस्या-सन्निकट विशेष दुर्बल'),
('Mars',   17.00, NULL,  NULL),
('Mercury',14.00, 12.00, 'वक्री होने पर 12°'),
('Jupiter',11.00, NULL,  NULL),
('Venus',  10.00,  8.00, 'वक्री होने पर 8°'),
('Saturn', 15.00, NULL,  NULL)
ON DUPLICATE KEY UPDATE orb_deg=VALUES(orb_deg),orb_deg_retro=VALUES(orb_deg_retro),notes=VALUES(notes);
-- Rahu/Ketu कभी अस्त नहीं होते; सूर्य पर लागू नहीं।

-- 3) RAHU/KETU NAISARGIKA FRIENDSHIP (missing in migration 006) ---------------
INSERT INTO planet_friendship (planet,toward_planet,relation) VALUES
('Rahu','Sun','E'),('Rahu','Moon','E'),('Rahu','Mars','E'),
('Rahu','Mercury','F'),('Rahu','Jupiter','N'),('Rahu','Venus','F'),('Rahu','Saturn','F'),
('Ketu','Sun','E'),('Ketu','Moon','E'),('Ketu','Mars','F'),
('Ketu','Mercury','N'),('Ketu','Jupiter','N'),('Ketu','Venus','F'),('Ketu','Saturn','F')
ON DUPLICATE KEY UPDATE relation=VALUES(relation);

-- 4) YUTI (conjunction) RULES --------------------------------------------------
CREATE TABLE IF NOT EXISTS yuti_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(48) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    with_planet VARCHAR(16) NULL,        -- specific malefic, or NULL for generic
    sentence_template TEXT NULL, good_bad VARCHAR(16) NULL, score DECIMAL(4,2) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_yuti (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO yuti_rules (rule_key,language,with_planet,sentence_template,good_bad,score) VALUES
('benefic_with_rahu','hi','Rahu','शुभ ग्रह {planet} राहु के साथ युति में है — फलों में भ्रम, अचानक उतार-चढ़ाव और ग्रहण-दोष जैसा प्रभाव आता है।','अशुभ',-1.00),
('benefic_with_ketu','hi','Ketu','शुभ ग्रह {planet} केतु के साथ युति में है — फलों में विच्छेद, अलगाव और अचानक रुकावट की प्रवृत्ति आती है।','अशुभ',-1.00),
('benefic_with_saturn','hi','Saturn','शुभ ग्रह {planet} शनि के साथ युति में है — फल मिलते हैं परन्तु विलंब, दबाव और अधिक परिश्रम के बाद।','अशुभ',-0.75),
('benefic_with_mars','hi','Mars','शुभ ग्रह {planet} मंगल के साथ युति में है — आवेश, जल्दबाजी और विवाद से शुभ फल दूषित होते हैं।','अशुभ',-0.75),
('malefic_with_benefic','hi',NULL,'पाप ग्रह {planet} शुभ ग्रह {benefic} की युति से संयमित हो जाता है — हानि की मात्रा घटती है।','शुभ',0.50),
('two_malefics_in_house','hi',NULL,'{house} भाव में एक से अधिक पाप ग्रहों की युति है — भाव पीड़ित होकर संघर्ष, विलंब और हानि देता है।','अशुभ',-1.50),
('moon_with_rahu','hi','Rahu','चन्द्रमा राहु के साथ है (ग्रहण योग) — मानसिक अशांति, भ्रम और निर्णय-दुविधा देता है।','अशुभ',-1.25),
('moon_with_ketu','hi','Ketu','चन्द्रमा केतु के साथ है (ग्रहण योग) — मन में वैराग्य, अकारण भय और अस्थिरता देता है।','अशुभ',-1.25)
ON DUPLICATE KEY UPDATE with_planet=VALUES(with_planet),sentence_template=VALUES(sentence_template),
good_bad=VALUES(good_bad),score=VALUES(score);

-- 5) NEW OUTPUT SENTENCES (dignity + combustion + neecha bhanga) ---------------
INSERT INTO house_pred_templates (rule_key,language,sentence_template,label) VALUES
('dignity_exalted','hi','{planet} {house} भाव में {rashi} राशि में उच्च का है{deep} — इस भाव के फलों में असाधारण वृद्धि और श्रेष्ठ परिणाम देता है।','Planet EXALTED in this house'),
('dignity_moolatrikona','hi','{planet} {house} भाव में अपनी मूल त्रिकोण राशि {rashi} में है — भाव के फल उत्तम, बलवान और स्थायी होते हैं।','Planet in MOOLATRIKONA'),
('dignity_own','hi','{planet} {house} भाव में स्वराशि {rashi} में है — भाव को बल, सुरक्षा और स्थिर शुभ फल प्राप्त होते हैं।','Planet in OWN sign'),
('dignity_debilitated','hi','{planet} {house} भाव में {rashi} राशि में नीच का है — इस भाव के फलों में दुर्बलता, संघर्ष और बाधा आती है।','Planet DEBILITATED'),
('dignity_neecha_bhanga','hi','किन्तु नीच भंग राजयोग बन रहा है ({reason}) — प्रारंभिक कठिनाई के बाद उल्लेखनीय उन्नति संभव है।','Neecha Bhanga cancellation'),
('compound_adhimitra','hi','{planet} यहाँ अति-मित्र राशि {rashi} में है (नैसर्गिक और तात्कालिक दोनों मैत्री) — शुभ फलों में विशेष वृद्धि।','Compound GREAT-FRIEND sign'),
('compound_adhishatru','hi','{planet} यहाँ अति-शत्रु राशि {rashi} में है — भाव के फलों में गंभीर संघर्ष और क्षीणता आती है।','Compound GREAT-ENEMY sign'),
('combust_full','hi','{planet} सूर्य से केवल {sep}° दूर होने से पूर्ण अस्त है ({pct}% अस्त) — इसके कारकत्व और इस भाव के फल अत्यंत क्षीण हो जाते हैं।','FULLY combust (>=75%)'),
('combust_moderate','hi','{planet} सूर्य के निकट अस्त है ({pct}% अस्त) — फल देने की शक्ति स्पष्ट रूप से घट जाती है।','Combust (40-75%)'),
('combust_partial','hi','{planet} सूर्य के निकट होने से आंशिक अस्त है ({pct}% अस्त) — फलों की शक्ति कुछ घटती है।','Partially combust (<40%)'),
('moon_amavasya','hi','चन्द्रमा सूर्य के अति निकट (क्षीण, अमावस्या-सन्निकट) है — मानसिक बल और इस भाव के फल दुर्बल होते हैं।','Weak new Moon'),
('element_flavor','hi','(तत्व-संकेत: {planet} का {p_element} तत्व {rashi} के {r_element} तत्व के साथ {reaction})','OPTIONAL element flavor line (config)')
ON DUPLICATE KEY UPDATE sentence_template=VALUES(sentence_template),label=VALUES(label);

-- 6) ENGINE CONFIG ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS house_engine_config (
    cfg_key VARCHAR(48) NOT NULL PRIMARY KEY, cfg_value VARCHAR(64) NOT NULL,
    notes VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO house_engine_config (cfg_key,cfg_value,notes) VALUES
('use_element_reaction','0','0 = तत्व-नियम फल-निर्णय से बाहर (केवल flavor, वह भी बंद); 1 = flavor line दिखाएँ'),
('use_tatkalika_maitri','1','पंचधा मैत्री (नैसर्गिक+तात्कालिक) से मित्र/शत्रु तय हो'),
('rahu_exalt_sign','2','परम्परा अनुसार बदलें (2=वृषभ, 3=मिथुन)'),
('ketu_exalt_sign','8','परम्परा अनुसार बदलें (8=वृश्चिक, 9=धनु)'),
('combust_show_percent','1','अस्त प्रतिशत दिखाएँ'),
('moon_amavasya_orb','12','चन्द्र-क्षीणता चेतावनी की कोणीय सीमा (डिग्री)')
ON DUPLICATE KEY UPDATE cfg_value=VALUES(cfg_value),notes=VALUES(notes);

-- End of migration 009.
