-- =============================================================================
-- Auto Business — Planet Prediction FIX (migration 008)  [ग्रह फल v2]
-- Adds a computed "ग्रह स्थिति" block to each planet tab of the Planet
-- Prediction panel, ABOVE the untouched (A) Bhavesh Phal and (B) Graha-in-Bhava
-- sections: (1) status line (dignity + retrograde + strength), (2) combustion
-- with percentage + signification-loss, (3) companion lines (nature × mutual
-- maitri) with classical named pair-yogas overriding the generic line, and
-- (4) a verdict chip.
--
-- Self-contained: creates every table it needs (the referenced "house-fix" /
-- "karaka-fix" migrations are not present in this repo, so their tables —
-- karaka_combust_loss, house_engine_config — and the Rahu/Ketu friendship rows
-- are created here). Idempotent (CREATE IF NOT EXISTS + ON DUPLICATE KEY UPDATE).
-- Numbered 008 to stay unique & sequential (007 is the current highest).
-- =============================================================================

SET NAMES utf8mb4;

-- 0) PREREQUISITE TABLES (created here since the referenced migrations are absent)

-- Engine config: small key/value switches read by the generator.
CREATE TABLE IF NOT EXISTS house_engine_config (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cfg_key VARCHAR(64) NOT NULL, cfg_value VARCHAR(255) NULL, notes TEXT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_cfg (cfg_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Combustion signification-loss text per planet (the karaka significations that
-- weaken when the planet is combust). Sun is never combust; Rahu/Ketu never
-- combust — so only Moon..Saturn carry a row.
CREATE TABLE IF NOT EXISTS karaka_combust_loss (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet VARCHAR(16) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    loss_text TEXT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_kcl (planet, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO karaka_combust_loss (planet,language,loss_text) VALUES
('Moon','hi','मन, माता-सुख और भावनात्मक स्थिरता का कारकत्व'),
('Mars','hi','साहस, बल और भ्रातृ-सुख का कारकत्व'),
('Mercury','hi','बुद्धि, वाणी और विवेक का कारकत्व'),
('Jupiter','hi','संतान-सुख और ज्ञान का कारकत्व'),
('Venus','hi','दांपत्य-सुख, प्रेम और भोग का कारकत्व'),
('Saturn','hi','आयु, धैर्य और कर्म-निष्ठा का कारकत्व')
ON DUPLICATE KEY UPDATE loss_text=VALUES(loss_text);

-- Naisargika friendship rows for the nodes (the base table from migration 006
-- seeds only Sun..Saturn). Used one-directionally for node maitri.
CREATE TABLE IF NOT EXISTS planet_friendship (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet VARCHAR(16) NOT NULL, toward_planet VARCHAR(16) NOT NULL,
    relation VARCHAR(4) NOT NULL,   -- 'F' | 'N' | 'E'
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_friend (planet, toward_planet)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO planet_friendship (planet,toward_planet,relation) VALUES
('Rahu','Mercury','F'),('Rahu','Venus','F'),('Rahu','Saturn','F'),
('Rahu','Jupiter','N'),('Rahu','Sun','E'),('Rahu','Moon','E'),('Rahu','Mars','E'),
('Ketu','Mars','F'),('Ketu','Venus','F'),('Ketu','Saturn','F'),
('Ketu','Jupiter','N'),('Ketu','Mercury','N'),('Ketu','Sun','E'),('Ketu','Moon','E')
ON DUPLICATE KEY UPDATE relation=VALUES(relation);

-- 1) NATURE x MAITRI MATRIX (generic companion lines) ---------------------------
CREATE TABLE IF NOT EXISTS graha_yuti_sentences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(32) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    companion_nature VARCHAR(8) NOT NULL,   -- 'benefic' | 'malefic'
    maitri VARCHAR(8) NOT NULL,             -- 'friend' | 'neutral' | 'enemy'
    sentence_template TEXT NULL, score DECIMAL(4,2) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_gys (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO graha_yuti_sentences (rule_key,language,companion_nature,maitri,sentence_template,score) VALUES
('g_ben_friend','hi','benefic','friend','{planet} के साथ शुभ एवं मित्र ग्रह {companion} की युति है — {planet} के फल पुष्ट, सहज और वर्धित होते हैं।',1.25),
('g_ben_neutral','hi','benefic','neutral','{planet} के साथ शुभ ग्रह {companion} (सम संबंध) की युति है — फलों में सामान्य वृद्धि होती है।',0.75),
('g_ben_enemy','hi','benefic','enemy','{planet} के साथ शुभ ग्रह {companion} की युति है, परन्तु दोनों में शत्रुता है — फल शुभ तो हैं पर सहज नहीं; खींचतान के साथ मिलते हैं।',0.25),
('g_mal_friend','hi','malefic','friend','{planet} के साथ पाप ग्रह {companion} की युति है, परन्तु मित्रता होने से हानि सीमित रहती है — ऊर्जा और बल भी मिलता है।',-0.50),
('g_mal_neutral','hi','malefic','neutral','{planet} के साथ पाप ग्रह {companion} (सम संबंध) की युति है — फलों में बाधा, विलंब और कठोरता आती है।',-0.75),
('g_mal_enemy','hi','malefic','enemy','{planet} के साथ पाप एवं शत्रु ग्रह {companion} की युति है — फल दूषित होते हैं; संघर्ष, हानि और तनाव का योग।',-1.25)
ON DUPLICATE KEY UPDATE sentence_template=VALUES(sentence_template),score=VALUES(score),
companion_nature=VALUES(companion_nature),maitri=VALUES(maitri);

-- 2) NAMED PAIR YOGAS (override the generic matrix line for that pair) ----------
CREATE TABLE IF NOT EXISTS graha_pair_yogas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(32) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    planet_a VARCHAR(16) NOT NULL, planet_b VARCHAR(16) NOT NULL,  -- unordered pair
    yoga_name VARCHAR(64) NULL, sentence_template TEXT NULL,
    good_bad VARCHAR(16) NULL, score DECIMAL(4,2) NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_gpy (rule_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO graha_pair_yogas (rule_key,language,planet_a,planet_b,yoga_name,sentence_template,good_bad,score) VALUES
('budhaditya','hi','Sun','Mercury','बुधादित्य योग','{planet} पर बुधादित्य योग का प्रभाव है (सूर्य-बुध युति) — बुद्धि, प्रशासनिक कौशल और यश देता है। {combust_note}','शुभ',1.00),
('chandra_mangal','hi','Moon','Mars','चन्द्र-मंगल (धन) योग','{planet} पर चन्द्र-मंगल योग है — धन-अर्जन की क्षमता और व्यावहारिक ऊर्जा बढ़ती है; मन में आवेश का ध्यान रखें।','शुभ',0.75),
('guru_chandal','hi','Jupiter','Rahu','गुरु-चांडाल योग','{planet} पर गुरु-चांडाल योग है (गुरु-राहु युति) — ज्ञान/नीति में अपरंपरागत मार्ग, भ्रम और मान्यताओं में विचलन संभव।','अशुभ',-1.25),
('grahan_su_ra','hi','Sun','Rahu','ग्रहण योग (सूर्य-राहु)','{planet} पर ग्रहण योग है — यश, अधिकार और पिता-पक्ष के फलों में ग्रहण-दोष; आत्मविश्वास में उतार-चढ़ाव।','अशुभ',-1.50),
('grahan_su_ke','hi','Sun','Ketu','ग्रहण योग (सूर्य-केतु)','{planet} पर ग्रहण योग है — अधिकार-क्षेत्र में अचानक रुकावट; पिता-पक्ष से जुड़े विषयों में दूरी।','अशुभ',-1.50),
('grahan_mo_ra','hi','Moon','Rahu','ग्रहण योग (चन्द्र-राहु)','{planet} पर ग्रहण योग है — मानसिक अशांति, भ्रम और निर्णय-दुविधा; मन के फल दूषित होते हैं।','अशुभ',-1.50),
('grahan_mo_ke','hi','Moon','Ketu','ग्रहण योग (चन्द्र-केतु)','{planet} पर ग्रहण योग है — मन में वैराग्य, अकारण भय और अस्थिरता आती है।','अशुभ',-1.50),
('visha','hi','Moon','Saturn','विष योग','{planet} पर विष योग है (चन्द्र-शनि युति) — मन में भारीपन, निराशा की प्रवृत्ति; धैर्य और नियमित दिनचर्या से शमन।','अशुभ',-1.25),
('angarak','hi','Mars','Rahu','अंगारक योग','{planet} पर अंगारक योग है (मंगल-राहु युति) — आवेश, दुर्घटना-भय और विवाद की तीव्रता; क्रोध-नियंत्रण आवश्यक।','अशुभ',-1.25),
('shrapit','hi','Saturn','Rahu','श्रापित दोष','{planet} पर श्रापित दोष है (शनि-राहु युति) — कार्यों में बार-बार बाधा और कार्मिक विलंब का अनुभव।','अशुभ',-1.25)
ON DUPLICATE KEY UPDATE yoga_name=VALUES(yoga_name),sentence_template=VALUES(sentence_template),
good_bad=VALUES(good_bad),score=VALUES(score),planet_a=VALUES(planet_a),planet_b=VALUES(planet_b);

-- 3) GRAHA STATUS + COMBUSTION SENTENCES (into the shared templates table) -------
INSERT INTO house_pred_templates (rule_key,language,sentence_template,label) VALUES
('graha_status','hi','{planet} {house} भाव में {rashi} राशि में {dignity_word} है{retro} — {strength_word}।','Planet status line (top of planet tab)'),
('graha_combust_full','hi','{planet} सूर्य से केवल {sep}° दूर होने से पूर्ण अस्त है ({pct}% अस्त) — {loss_text} अत्यंत क्षीण हो जाता है; नीचे (A) व (B) के शुभ फल भी इसी अनुपात में घटेंगे।','Planet FULLY combust >=75%'),
('graha_combust_moderate','hi','{planet} अस्त है ({pct}% अस्त) — {loss_text} की शक्ति स्पष्ट रूप से घटती है।','Planet combust 40-75%'),
('graha_combust_partial','hi','{planet} आंशिक अस्त है ({pct}% अस्त) — {loss_text} में कुछ कमी रहती है।','Planet partially combust <40%'),
('graha_alone','hi','{planet} इस भाव में अकेला स्थित है — फल मुख्यतः इसकी अपनी गरिमा और दृष्टियों पर निर्भर।','No companions in the house'),
('budhaditya_combust_note','hi','(ध्यान दें: बुध {pct}% अस्त है — योग का बल उसी अनुपात में घटेगा।)','Combust note inside Budhaditya line')
ON DUPLICATE KEY UPDATE sentence_template=VALUES(sentence_template),label=VALUES(label);

-- 4) CONFIG ----------------------------------------------------------------------
INSERT INTO house_engine_config (cfg_key,cfg_value,notes) VALUES
('graha_pair_yoga_overrides','1','named pair yoga replaces that pair''s generic matrix line'),
('graha_node_maitri_mode','node_row','node (Rahu/Ketu) maitri: use the node''s own friendship row one-directionally')
ON DUPLICATE KEY UPDATE cfg_value=VALUES(cfg_value),notes=VALUES(notes);

-- End of migration 008.
