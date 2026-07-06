-- =============================================================================
-- Auto Business — Gochar Muhurat rules (migration 022)
-- Update: राहु काल, दिशा शूल, तिथि (नन्दा/भद्रा/जया/रिक्ता/पूर्णा), जन्म-नक्षत्र
-- का वारानुसार मासिक फल, अस्त-ग्रह चेतावनी (गुरु/शुक्र) व शनि-अष्टकवर्ग कष्ट-राशि।
-- Additive "मुहूर्त" category in the Gochar panel — does NOT change any existing
-- Gochar / Ashtakavarga / Sade-Sati text.
-- Source: docs/gochar_muhurat_rules_hindi.md (गोचर विचार, अध्याय 8, पृ.128–135).
-- =============================================================================
SET NAMES utf8mb4;

-- भाग 5 — तिथि नाम / स्वामी / अर्थ (हर समूह में 1..5)
CREATE TABLE IF NOT EXISTS muhurat_tithi (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tithi_no TINYINT NOT NULL,               -- 1..5 (नन्दा..पूर्णा)
    language VARCHAR(8) NOT NULL DEFAULT 'hi',
    name VARCHAR(24) NOT NULL, lord VARCHAR(12) NOT NULL, meaning TEXT NOT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_mt (tithi_no, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO muhurat_tithi (tithi_no,language,name,lord,meaning) VALUES
(1,'hi','नन्दा','शुक्र','आमोद-प्रमोद, विलास व शुभ कार्य। फिल्म-शूटिंग, सिनेमाघर की नींव, पिकनिक/पार्टी के लिए नन्दा तिथि + शुक्रवार सर्वोत्तम।'),
(2,'hi','भद्रा','बुध','भद्र व कल्याणकारी शुभ कर्म। परोपकार, यज्ञ, दान, गरीबों को भोजन के लिए भद्रा तिथि + बुधवार सर्वोत्तम।'),
(3,'hi','जया','मंगल','युद्ध-भूमि में विजय दिलाने वाली। सेना में रिपोर्ट, फौजदारी मुकदमे की अपील, क्रूर/साहसिक कार्य आरम्भ के लिए जया तिथि + मंगलवार सर्वोत्तम।'),
(4,'hi','रिक्ता','शनि','खाली, अभावग्रस्त तिथि। इसमें शुभ कार्य आरम्भ नहीं करना चाहिए।'),
(5,'hi','पूर्णा','गुरु','पूर्ण, सम्पन्न, धनी व विस्तृत। धन-वृद्धि व विस्तार सम्बन्धी पूर्ण कार्यों के लिए पूर्णा तिथि + गुरुवार उत्तम।')
ON DUPLICATE KEY UPDATE name=VALUES(name), lord=VALUES(lord), meaning=VALUES(meaning);

-- भाग 3 — दिशा शूल (वारानुसार वर्जित दिशा) 0=रवि..6=शनि
CREATE TABLE IF NOT EXISTS muhurat_disha_shul (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    weekday TINYINT NOT NULL,                 -- 0=Sun..6=Sat
    language VARCHAR(8) NOT NULL DEFAULT 'hi',
    forbidden_dir VARCHAR(48) NOT NULL, dir_lord VARCHAR(12) NOT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_mds (weekday, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO muhurat_disha_shul (weekday,language,forbidden_dir,dir_lord) VALUES
(0,'hi','पश्चिम','शनि'),
(1,'hi','दक्षिण-पूर्व (आग्नेय)','शुक्र'),
(2,'hi','उत्तर','बुध'),
(3,'hi','दक्षिण','मंगल'),
(4,'hi','दक्षिण-पश्चिम (नैऋत्य)','राहु'),
(5,'hi','पश्चिमोत्तर (वायव्य)','चन्द्र'),
(6,'hi','पूर्व','सूर्य')
ON DUPLICATE KEY UPDATE forbidden_dir=VALUES(forbidden_dir), dir_lord=VALUES(dir_lord);

-- भाग 7 — जन्म-नक्षत्र का वारानुसार मासिक फल (+ तिथि से वृद्धि)
CREATE TABLE IF NOT EXISTS muhurat_nak_vaar_phal (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    weekday TINYINT NOT NULL,                 -- 0=Sun..6=Sat
    language VARCHAR(8) NOT NULL DEFAULT 'hi',
    phal_text TEXT NOT NULL,
    bonus_tithis VARCHAR(48) NOT NULL DEFAULT '',   -- CSV of tithi numbers 1..15
    bonus_note VARCHAR(255) NOT NULL DEFAULT '',
    cond_text VARCHAR(255) NOT NULL DEFAULT '',
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_mnvp (weekday, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO muhurat_nak_vaar_phal (weekday,language,phal_text,bonus_tithis,bonus_note,cond_text) VALUES
(0,'hi','बेचैनी, व्यर्थ भ्रमण व कलह की सम्भावना।','','',''),
(1,'hi','खाने-पीने व पहनने की अच्छी वस्तुएँ, धन-वृद्धि। जन्मकालीन चन्द्र जितना बलवान/शुभ, उतनी अधिक अन्न-धन प्राप्ति।','','',''),
(2,'hi','आग के सम्पर्क की सम्भावना, रक्षा-विभाग कर्मियों से मेल, भाइयों से व्यवहार, पुरुषार्थ, गणित शिक्षा-अभ्यास, बिजली के यन्त्रों का लेन-देन।','3,8,13','तृतीया/अष्टमी/त्रयोदशी हो तो फल और अधिक।',''),
(3,'hi','परोपकार के कार्य, सद्भावना, सम्बन्धियों से सहयोग व लाभ, विद्या में मन, व्यापार अच्छा।','2,7,12','द्वितीया/सप्तमी/द्वादशी हो तो फल और अधिक।','जन्मकुण्डली में बुध गुरु आदि शुभ ग्रह से प्रभावित हो तब।'),
(4,'hi','ज्ञान-ग्रहण की प्रेरणा, मन में शान्ति व सुख, आचार्यों/गुरुओं/महात्माओं से सम्पर्क, राज्य-कर्मियों से सहयोग, धन-वृद्धि।','5,10,15','पंचमी/दशमी/पूर्णिमा हो तो फल और अधिक।','जन्मकुण्डली में गुरु बलवान हो तब।'),
(5,'hi','आमोद-प्रमोद के अवसर, उत्तम खान-पान व वस्त्र, स्त्री-वर्ग से सम्पर्क, संगीत-नृत्यादि में रुचि।','1,11','प्रथमा/एकादशी हो तो फल और अधिक।','शुक्र बलवान, शुभ ग्रहों से युक्त/दृष्ट हो तब।'),
(6,'hi','इस अध्याय-अंश में शनिवार का फल क्रमशः आगे (अगले पृष्ठों में) दिया गया है — यहाँ उपलब्ध नहीं।','','','')
ON DUPLICATE KEY UPDATE phal_text=VALUES(phal_text), bonus_tithis=VALUES(bonus_tithis), bonus_note=VALUES(bonus_note), cond_text=VALUES(cond_text);

-- भाग 4 — अस्त (combustion) चेतावनी — विवाह मुहूर्त (गुरु/शुक्र)
CREATE TABLE IF NOT EXISTS muhurat_combust (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    planet VARCHAR(12) NOT NULL,
    language VARCHAR(8) NOT NULL DEFAULT 'hi',
    warn_text TEXT NOT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_mc (planet, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO muhurat_combust (planet,language,warn_text) VALUES
('Jupiter','hi','गोचर का गुरु अस्त है — विवाह मुहूर्त में इसे छोड़ें; अन्यथा पति के लिए अनिष्ट कहा गया है (गुरु ही पति का कारक)।'),
('Venus','hi','गोचर का शुक्र अस्त है — विवाह मुहूर्त में इसे छोड़ें; अन्यथा स्त्री के लिए अनिष्ट कहा गया है।')
ON DUPLICATE KEY UPDATE warn_text=VALUES(warn_text);

-- Config toggles (additive; engine defaults to '1' when absent).
INSERT INTO house_engine_config (cfg_key, cfg_value) VALUES
('muhurat_show','1'),
('muhurat_rahu_kaal','1'),
('muhurat_disha_shul','1'),
('muhurat_tithi','1'),
('muhurat_janma_nak','1'),
('muhurat_combust','1'),
('muhurat_kashta','1'),
('muhurat_sunrise','6.0'),
('muhurat_sunset','18.0')
ON DUPLICATE KEY UPDATE cfg_value=VALUES(cfg_value);
