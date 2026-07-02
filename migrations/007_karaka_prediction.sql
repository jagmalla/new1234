-- =============================================================================
-- Auto Business — Karaka Prediction rule tables (editable via admin)
-- Migration 007: 3 rule tables from Karaka_Prediction_Rules.xlsx (Hindi).
-- Each house is judged twice — from the Lagna (outer) and from its natural
-- karaka planet (inner). The engine counts the house FROM the karaka; these
-- tables supply the meanings + comparison phrasing. Self-contained/idempotent.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS karaka_map (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet VARCHAR(16) NOT NULL, title_heading VARCHAR(255) NULL,
    houses_judged VARCHAR(48) NULL, signifies VARCHAR(255) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_karaka (planet)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS karaka_house_meaning (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    karaka VARCHAR(16) NOT NULL, house TINYINT UNSIGNED NOT NULL,
    karaka_meaning TEXT NULL, lagna_view TEXT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_km (karaka, house)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS karaka_sentences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(8) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    situation VARCHAR(255) NULL, sentence_template TEXT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_ks (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO karaka_map (planet,title_heading,houses_judged,signifies) VALUES
('Moon','चंद्र: माता (Moon: Mother)','2, 4, 9, 11','मन, पोषण, माता, समृद्धि, जनता'),
('Mars','मंगल: पराक्रम / छोटे भाई-बहन (Mars: Courage / Younger Siblings)','3','पराक्रम, साहस, छोटे भाई-बहन, ऊर्जा'),
('Mercury','बुध: बुद्धि / मामा (Mercury: Intellect / Maternal Uncle)','6','बुद्धि, तर्क, वाद-विवाद, मामा, प्रतियोगिता'),
('Jupiter','गुरु: संतान / ज्ञान (Jupiter: Children / Wisdom)','5','संतान, ज्ञान, विवेक, पूर्व पुण्य'),
('Venus','शुक्र: विवाह / जीवनसाथी (Venus: Marriage / Spouse)','7','विवाह, प्रेम, जीवनसाथी, वैवाहिक सुख'),
('Saturn','शनि: आयु / दुःख / वैराग्य (Saturn: Longevity / Sorrow / Detachment)','8, 12','आयु, मृत्यु, हानि, दुःख, वैराग्य, कर्म'),
('Sun','सूर्य: पिता (Sun: Father)','9, 10, 11','पिता, आत्मा, कर्म (सच्चा उद्देश्य), अधिकार')
ON DUPLICATE KEY UPDATE title_heading=VALUES(title_heading),houses_judged=VALUES(houses_judged),signifies=VALUES(signifies);

INSERT INTO karaka_house_meaning (karaka,house,karaka_meaning,lagna_view) VALUES
('Moon',2,'चंद्र से द्वितीय — अर्जित धन और परिवार से मानसिक संतुष्टि व पोषण मिलेगा या नहीं','लग्न से द्वितीय — अर्जित धन और कुटुंब (बाहरी)'),
('Moon',4,'चंद्र से चतुर्थ — वास्तविक मानसिक शांति और माता का सुख','लग्न से चतुर्थ — भौतिक सुख: घर, वाहन (बाहरी)'),
('Moon',9,'चंद्र से नवम — दैवीय कृपा और सामाजिक प्रसिद्धि (जन-कृपा)','लग्न से नवम — भाग्य और पिता (बाहरी)'),
('Moon',11,'चंद्र से एकादश — इच्छापूर्ति से मिलने वाली भावनात्मक संतुष्टि','लग्न से एकादश — आय और लाभ (बाहरी)'),
('Mars',3,'मंगल से तृतीय — आंतरिक शक्ति और वास्तविक साहस; भाई-बहनों का सहयोग','लग्न से तृतीय — पराक्रम दिखाने के अवसर; छोटे भाई-बहन (बाहरी)'),
('Mercury',6,'बुध से षष्ठ — बुद्धि व तर्क से शत्रु/विवादों पर विजय; मामा का सुख','लग्न से षष्ठ — शत्रु, रोग, ऋण का प्रकार (बाहरी)'),
('Jupiter',5,'गुरु से पंचम — संतान का सुख, उसकी प्रगति और भाग्य','लग्न से पंचम — संतान होने की संभावना और समय (बाहरी)'),
('Venus',7,'शुक्र से सप्तम — वैवाहिक जीवन का वास्तविक सुख, प्रेम, सामंजस्य और रिश्ते की गुणवत्ता','लग्न से सप्तम — विवाह का समय, जीवनसाथी का स्वरूप व सामाजिक स्थिति (बाहरी)'),
('Saturn',8,'शनि से अष्टम — वास्तविक जीवंतता और मृत्यु के गहरे कार्मिक कारण','लग्न से अष्टम — आयु और मृत्यु का कारण (बाहरी)'),
('Saturn',12,'शनि से द्वादश — दुःख, वियोग, वैराग्य और मोक्ष के मार्ग की बाधा/सहयोग; कर्म बंधन का अंतिम लेखा','लग्न से द्वादश — भौतिक हानि, व्यय, बंधन (बाहरी)'),
('Sun',9,'सूर्य से नवम भाव — पिता के साथ आत्मिक/कार्मिक संबंध; पिता का भाग्य हमारी आत्मा की उन्नति में सहायक है या बाधक','लग्न से नवम — पिता का गुरु-तुल्य स्वरूप, उनका धर्म, ज्ञान और संस्कार (बाहरी)'),
('Sun',10,'सूर्य से दशम भाव — आत्मा का कर्म (soul''s true calling); वह कार्य जिससे आत्मा को सच्ची संतुष्टि मिले','लग्न से दशम — सांसारिक पेशा और पिता का सामाजिक पद, अधिकार (बाहरी)'),
('Sun',11,'सूर्य से एकादश भाव — कर्मों से मिलने वाली आत्मिक संतुष्टि और प्रसन्नता (भौतिक लाभ से बढ़कर)','लग्न से एकादश — सांसारिक पेशे से भौतिक लाभ और आय (बाहरी)')
ON DUPLICATE KEY UPDATE karaka_meaning=VALUES(karaka_meaning),lagna_view=VALUES(lagna_view);

INSERT INTO karaka_sentences (rule_key,language,situation,sentence_template) VALUES
('SS','hi','Strong from Lagna AND strong from karaka','{title} — {house} भाव के विषय बाहर से भी बलवान हैं और भीतर से भी सुखद: {karaka_meaning}। यह पूर्ण और शुभ फल का संकेत है।'),
('SW','hi','Strong from Lagna BUT weak from karaka','{title} — {house_significance} तो प्राप्त है, परन्तु उसका वास्तविक सुख नहीं मिलता; {karaka_meaning} की कमी रहती है।'),
('WS','hi','Weak from Lagna BUT strong from karaka','{title} — बाहरी {house_significance} भले सीमित हो, फिर भी भीतर {karaka_meaning} की अनुभूति बनी रहती है।'),
('WW','hi','Weak from Lagna AND weak from karaka','{title} — {house} भाव के बाहरी और आंतरिक, दोनों पक्षों को बल देने की आवश्यकता है; इस विषय में सावधानी रखें।')
ON DUPLICATE KEY UPDATE situation=VALUES(situation),sentence_template=VALUES(sentence_template);

-- End of migration 007.
