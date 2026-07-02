-- =============================================================================
-- Auto Business — House Prediction rule tables (editable via admin)
-- Migration 006: 6 rule tables from House_Prediction_Rules.xlsx (Hindi).
-- The engine COMBINES these rules with each chart's facts to generate the
-- per-house reading. Self-contained & idempotent. Import once:
--   mysql -u USER -p auto_business < migrations/006_house_prediction.sql
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rashi_elements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rashi_num TINYINT UNSIGNED NOT NULL, rashi VARCHAR(32) NOT NULL,
    quality VARCHAR(16) NULL, element VARCHAR(16) NULL, lord VARCHAR(16) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_rashi (rashi_num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planet_elements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet VARCHAR(16) NOT NULL, element VARCHAR(16) NULL, nature VARCHAR(16) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_planet (planet)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS element_reactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet_element VARCHAR(16) NOT NULL, rashi_element VARCHAR(16) NOT NULL,
    reaction_result TEXT NULL, good_bad VARCHAR(16) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_react (planet_element, rashi_element)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planet_friendship (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet VARCHAR(16) NOT NULL, toward_planet VARCHAR(16) NOT NULL, relation VARCHAR(4) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_friend (planet, toward_planet)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planet_house_nature (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet VARCHAR(16) NOT NULL, good_houses VARCHAR(64) NULL, bad_houses VARCHAR(64) NULL, notes TEXT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_phn (planet)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS house_pred_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(48) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    sentence_template TEXT NULL, label VARCHAR(255) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_tpl (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO rashi_elements (rashi_num,rashi,quality,element,lord) VALUES
(1,'Aries','Movable','Fire','Mars'),
(2,'Taurus','Fixed','Earth','Venus'),
(3,'Gemini','Dual','Air','Mercury'),
(4,'Cancer','Movable','Water','Moon'),
(5,'Leo','Fixed','Fire','Sun'),
(6,'Virgo','Dual','Earth','Mercury'),
(7,'Libra','Movable','Air','Venus'),
(8,'Scorpio','Fixed','Water','Mars'),
(9,'Sagittarius','Dual','Fire','Jupiter'),
(10,'Capricorn','Movable','Earth','Saturn'),
(11,'Aquarius','Fixed','Air','Saturn'),
(12,'Pisces','Dual','Water','Jupiter')
ON DUPLICATE KEY UPDATE rashi=VALUES(rashi),quality=VALUES(quality),element=VALUES(element),lord=VALUES(lord);

INSERT INTO planet_elements (planet,element,nature) VALUES
('Sun','Fire','Malefic'),
('Moon','Water','Benefic'),
('Mars','Fire','Malefic'),
('Mercury','Earth','Neutral'),
('Jupiter','Ether/Sky','Benefic'),
('Venus','Water','Benefic'),
('Saturn','Air','Malefic'),
('Rahu','Air','Malefic'),
('Ketu','Fire','Malefic')
ON DUPLICATE KEY UPDATE element=VALUES(element),nature=VALUES(nature);

INSERT INTO element_reactions (planet_element,rashi_element,reaction_result,good_bad) VALUES
('Fire','Fire','प्राकृतिक ऊर्जा, साहस और जीवन शक्ति का शक्तिशाली विस्तार होता है।','शुभ'),
('Fire','Earth','ऊर्जा नियंत्रित होती है और व्यावहारिक स्थिरता की ओर केंद्रित होती है।','सम'),
('Fire','Air','गतिशील क्रिया और बौद्धिक महत्वाकांक्षाओं का तेजी से विस्तार होता है।','शुभ'),
('Fire','Water','विरोधी तत्व टकराते हैं, जिससे आंतरिक या भावनात्मक उथल-पुथल होती है।','अशुभ'),
('Earth','Fire','पृथ्वी की नमी सूख जाती है, जिससे कठोरता और रूखापन आता है।','सम'),
('Earth','Earth','गहरी जड़ें असीम शारीरिक शक्ति और भौतिक स्थिरता प्रदान करती हैं।','शुभ'),
('Earth','Air','वायु पृथ्वी को बिखेर देती है, जिससे अस्थिरता और मानसिक भटकाव होता है।','अशुभ'),
('Earth','Water','जल पृथ्वी को पोषित करता है, जिससे वह उपजाऊ और निर्माणकारी बनती है।','शुभ'),
('Air','Fire','अग्नि वायु की ऊर्जा को तेज करती है, जिससे यह विस्फोटक बन जाती है।','शुभ'),
('Air','Earth','पृथ्वी वायु के प्रवाह को रोकती है, जिससे विचारों में जड़ता आती है।','सम'),
('Air','Air','बौद्धिक शक्ति और स्वतंत्रता का असीमित विस्तार होता है।','शुभ'),
('Air','Water','जल को अशांत करता है, जिससे गहरी भावनात्मक उथल-पुथल (तूफान) पैदा होती है।','अशुभ'),
('Water','Fire','अग्नि जल को वाष्पीकृत कर देती है, जिससे बेचैनी और भावनात्मक ह्रास होता है।','अशुभ'),
('Water','Earth','पृथ्वी जल को दिशा देती है, जिससे जीवन और भावनात्मक स्थिरता मिलती है।','शुभ'),
('Water','Air','वायु जल को अशांत करती है, जिससे मन में भटकाव पैदा होता है।','अशुभ'),
('Water','Water','असीमित गहराई, संवेदनशीलता और प्रवाहमय भावनात्मक प्रतिध्वनि मिलती है।','शुभ'),
('Ether/Sky','Fire','आकाश ज्ञान और सकारात्मक ऊर्जा के लिए असीम स्थान प्रदान करता है।','शुभ'),
('Ether/Sky','Earth','आकाश तत्व सीमित हो जाता है और भौतिक सीमाओं के भीतर काम करता है।','सम'),
('Ether/Sky','Air','आध्यात्मिक और वैचारिक विस्तार के मुक्त प्रवाह को अधिकतम करता है।','शुभ'),
('Ether/Sky','Water','भावनाओं और ज्ञान को असीम गहराई तथा विशालता प्रदान करता है।','शुभ')
ON DUPLICATE KEY UPDATE reaction_result=VALUES(reaction_result),good_bad=VALUES(good_bad);

INSERT INTO planet_friendship (planet,toward_planet,relation) VALUES
('Sun','Moon','F'),
('Sun','Mars','F'),
('Sun','Mercury','N'),
('Sun','Jupiter','F'),
('Sun','Venus','E'),
('Sun','Saturn','E'),
('Moon','Sun','F'),
('Moon','Mars','N'),
('Moon','Mercury','F'),
('Moon','Jupiter','N'),
('Moon','Venus','N'),
('Moon','Saturn','N'),
('Mars','Sun','F'),
('Mars','Moon','F'),
('Mars','Mercury','E'),
('Mars','Jupiter','F'),
('Mars','Venus','N'),
('Mars','Saturn','N'),
('Mercury','Sun','F'),
('Mercury','Moon','E'),
('Mercury','Mars','N'),
('Mercury','Jupiter','N'),
('Mercury','Venus','F'),
('Mercury','Saturn','N'),
('Jupiter','Sun','F'),
('Jupiter','Moon','N'),
('Jupiter','Mars','F'),
('Jupiter','Mercury','E'),
('Jupiter','Venus','E'),
('Jupiter','Saturn','N'),
('Venus','Sun','E'),
('Venus','Moon','E'),
('Venus','Mars','N'),
('Venus','Mercury','F'),
('Venus','Jupiter','N'),
('Venus','Saturn','F'),
('Saturn','Sun','E'),
('Saturn','Moon','E'),
('Saturn','Mars','E'),
('Saturn','Mercury','F'),
('Saturn','Jupiter','N'),
('Saturn','Venus','F')
ON DUPLICATE KEY UPDATE relation=VALUES(relation);

INSERT INTO planet_house_nature (planet,good_houses,bad_houses,notes) VALUES
('Sun','3, 6, 11','1, 5, 8, 9, 12','एक पाप ग्रह के रूप में, यह 3 और 6 में विजय दिलाता है, लेकिन 1 या 8 में होने पर यह स्वास्थ्य जोखिम पैदा करता है, और 5 में होने पर यह माता/भाइयों को नुकसान पहुँचाता है।'),
('Moon','1, 4, 5, 7, 9, 10, 11','6, 8, 12','6, 8 या 12 भाव में होने और पाप ग्रहों से दृष्ट होने पर, यह तत्काल खतरा (बालारिष्ट) पैदा करता है।'),
('Mars','3, 6, 11','1, 5, 8, 9, 12','एक पाप ग्रह जो 3 और 6 में विजय देता है, लेकिन 1 या 8 में होने पर गंभीर स्वास्थ्य जोखिम पैदा करता है।'),
('Mercury','1, 4, 5, 7, 9, 10, 11','6, 8, 12','केंद्र (1, 4, 7, 10) में एक अकेला मजबूत शुभ ग्रह सभी दुर्भाग्य (अरिष्ट) को नष्ट कर देता है।'),
('Jupiter','1, 4, 5, 7, 9, 10, 11','6, 8, 12','लग्न (1) में एक अकेला मजबूत गुरु सभी दोषों को निरस्त कर देता है।'),
('Venus','1, 4, 5, 7, 9, 10, 11','6, 8, 12','केंद्र/त्रिकोण या 11वें भाव में स्थिति दशा के दौरान राजसम्मान और धन लाती है।'),
('Saturn','3, 6, 11','1, 5, 8, 9, 12','एक पाप ग्रह जो 3 और 6 में विजय लाता है, लेकिन 1 या 8 में होने पर गंभीर स्वास्थ्य जोखिम पैदा करता है।'),
('Rahu','3, 6, 11','5, 8, 9, 12','दशा राशि से 5, 9 और 8वें भाव में पाप ग्रह दुःख लाते हैं, जबकि 3 और 6 विजय प्रदान करते हैं।'),
('Ketu','3, 6, 11','5, 8, 9, 12','दशा राशि से 5, 9 और 8वें भाव में पाप ग्रह दुःख लाते हैं, जबकि 3 और 6 विजय प्रदान करते हैं।')
ON DUPLICATE KEY UPDATE good_houses=VALUES(good_houses),bad_houses=VALUES(bad_houses),notes=VALUES(notes);

INSERT INTO house_pred_templates (rule_key,language,sentence_template,label) VALUES
('planet_friend_sign','hi','{planet} यहाँ अपने मित्र की राशि {rashi} में है, इसलिए यह {house} भाव के शुभ फलों में वृद्धि करता है।','Planet in FRIEND''s sign in this house'),
('planet_enemy_sign','hi','{planet} यहाँ अपने शत्रु की राशि {rashi} में है, जिससे {house} भाव के फलों में संघर्ष और कमी आती है।','Planet in ENEMY''s sign in this house'),
('planet_neutral_sign','hi','{planet} यहाँ सम राशि {rashi} में है, इसलिए {house} भाव के लिए इसके फल सामान्य और संतुलित रहेंगे।','Planet in NEUTRAL sign in this house'),
('element_good','hi','{planet} का तत्व {rashi} राशि के तत्व के अनुकूल है, जो {house} भाव की प्राकृतिक ऊर्जा और शुभता को शक्तिशाली रूप से बढ़ाता है।','Planet element AGREES with rashi element (good reaction)'),
('element_bad','hi','{planet} का तत्व {rashi} राशि के तत्व का विरोधी है, जिससे {house} भाव के मामलों में उथल-पुथल और बाधाएँ उत्पन्न होती हैं।','Planet element CLASHES with rashi element (bad reaction)'),
('drishti_benefic','hi','शुभ ग्रह {planet} की दृष्टि {house} भाव पर पड़ रही है, जो इस भाव की समृद्धि को बढ़ाती है और शुभ फल देती है।','Benefic planet gives DRISHTI to this house'),
('drishti_malefic','hi','पाप ग्रह {planet} की दृष्टि {house} भाव पर पड़ रही है, जो इस भाव के फलों में देरी, संघर्ष या हानि का संकेत देती है।','Malefic planet gives DRISHTI to this house'),
('lord_protects','hi','{lord}, जो कि {house} भाव का स्वामी है, अपने ही भाव को देख रहा है, जिससे इस भाव को मजबूत सुरक्षा और वृद्धि प्राप्त होती है।','The house LORD gives drishti to its OWN house (protection)'),
('planet_works_well','hi','{planet} यहाँ {house} भाव में मजबूत और शुभ स्थिति में है, जो इस क्षेत्र में सफलता और अनुकूल परिणाम सुनिश्चित करता है।','A planet that works WELL sits in this house'),
('planet_works_badly','hi','{planet} यहाँ {house} भाव में कमजोर या अशुभ स्थिति में है, जिससे इस भाव से संबंधित मामलों में कष्ट या हानि हो सकती है।','A planet that works BADLY sits in this house'),
('lord_in_good','hi','{house} भाव का स्वामी {lord} एक शुभ भाव में गया है, जो {house} भाव के मामलों में समृद्धि और सकारात्मक वृद्धि सुनिश्चित करता है।','The lord of this house goes to a GOOD house'),
('lord_in_difficult','hi','{house} भाव का स्वामी {lord} त्रिक भाव (6, 8, या 12) में गया है, जो {house} भाव के लिए नुकसान, बाधाओं या कमजोरी को दर्शाता है।','The lord of this house goes to a DIFFICULT house (6/8/12)')
ON DUPLICATE KEY UPDATE sentence_template=VALUES(sentence_template),label=VALUES(label);

-- End of migration 006.
