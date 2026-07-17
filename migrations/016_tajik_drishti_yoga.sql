-- =============================================================================
-- Auto Business — Tajik Drishti + 16 Yogas (migration 016)
-- Dhruvanka (sphuta drishti), deeptamsha, hadda (60), harsha sthana,
-- 16 yoga definitions + phal, Kamboola 16 bheda, config.
-- Rules source: Tajik Neelkanthi, Drishti-phala + Shodasha-yoga adhyaya
-- (see docs/Tajik_Drishti_16Yoga_Rules_TajikNeelkanthi.md).
-- Requires Varshaphal engine + Mudda dasha (015) and house_engine_config (009).
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tajik_dhruvanka (
    rashi_diff TINYINT NOT NULL PRIMARY KEY, house TINYINT NOT NULL,
    dhruvanka TINYINT NOT NULL, drishti_type VARCHAR(32) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO tajik_dhruvanka (rashi_diff,house,dhruvanka,drishti_type) VALUES
(0,1,60,'प्रत्यक्ष वैर (एकर्क्ष) — योग-निर्माण में मान्य'),
(1,2,0,'दृष्टि नहीं'),
(2,3,40,'गुप्त स्नेह'),
(3,4,15,'गुप्त वैर'),
(4,5,45,'प्रत्यक्ष स्नेह'),
(5,6,0,'दृष्टि नहीं'),
(6,7,60,'प्रत्यक्ष वैर'),
(7,8,0,'दृष्टि नहीं'),
(8,9,45,'प्रत्यक्ष स्नेह'),
(9,10,15,'गुप्त वैर'),
(10,11,10,'गुप्त स्नेह'),
(11,12,0,'दृष्टि नहीं')
ON DUPLICATE KEY UPDATE dhruvanka=VALUES(dhruvanka),drishti_type=VALUES(drishti_type);

CREATE TABLE IF NOT EXISTS tajik_deeptamsha (
    planet VARCHAR(16) NOT NULL PRIMARY KEY, orb_deg DECIMAL(4,1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO tajik_deeptamsha (planet,orb_deg) VALUES
('Sun',15),
('Moon',12),
('Mars',8),
('Mercury',7),
('Jupiter',9),
('Venus',7),
('Saturn',9)
ON DUPLICATE KEY UPDATE orb_deg=VALUES(orb_deg);

CREATE TABLE IF NOT EXISTS tajik_hadda (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sign TINYINT NOT NULL, lord VARCHAR(16) NOT NULL,
    deg_from DECIMAL(4,1) NOT NULL, deg_to DECIMAL(4,1) NOT NULL,
    PRIMARY KEY (id), UNIQUE KEY uq_th (sign, deg_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO tajik_hadda (sign,lord,deg_from,deg_to) VALUES
(1,'Jupiter',0,6),
(1,'Venus',6,12),
(1,'Mercury',12,20),
(1,'Mars',20,25),
(1,'Saturn',25,30),
(2,'Venus',0,8),
(2,'Mercury',8,14),
(2,'Jupiter',14,22),
(2,'Saturn',22,27),
(2,'Mars',27,30),
(3,'Mercury',0,6),
(3,'Jupiter',6,12),
(3,'Venus',12,17),
(3,'Mars',17,24),
(3,'Saturn',24,30),
(4,'Mars',0,7),
(4,'Venus',7,13),
(4,'Mercury',13,19),
(4,'Jupiter',19,26),
(4,'Saturn',26,30),
(5,'Jupiter',0,6),
(5,'Venus',6,11),
(5,'Saturn',11,18),
(5,'Mercury',18,24),
(5,'Mars',24,30),
(6,'Mercury',0,7),
(6,'Venus',7,17),
(6,'Jupiter',17,21),
(6,'Mars',21,28),
(6,'Saturn',28,30),
(7,'Saturn',0,6),
(7,'Mercury',6,14),
(7,'Jupiter',14,21),
(7,'Venus',21,28),
(7,'Mars',28,30),
(8,'Mars',0,7),
(8,'Venus',7,11),
(8,'Mercury',11,19),
(8,'Jupiter',19,24),
(8,'Saturn',24,30),
(9,'Jupiter',0,12),
(9,'Venus',12,17),
(9,'Mercury',17,21),
(9,'Saturn',21,26),
(9,'Mars',26,30),
(10,'Mercury',0,7),
(10,'Jupiter',7,14),
(10,'Venus',14,22),
(10,'Saturn',22,26),
(10,'Mars',26,30),
(11,'Mercury',0,7),
(11,'Venus',7,13),
(11,'Jupiter',13,20),
(11,'Mars',20,25),
(11,'Saturn',25,30),
(12,'Venus',0,12),
(12,'Jupiter',12,16),
(12,'Mercury',16,19),
(12,'Mars',19,28),
(12,'Saturn',28,30)
ON DUPLICATE KEY UPDATE lord=VALUES(lord),deg_to=VALUES(deg_to);

CREATE TABLE IF NOT EXISTS tajik_harsha_sthana (
    planet VARCHAR(16) NOT NULL PRIMARY KEY, harsha_house TINYINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO tajik_harsha_sthana (planet,harsha_house) VALUES
('Sun',9),
('Moon',3),
('Mars',6),
('Mercury',1),
('Jupiter',11),
('Venus',5),
('Saturn',12)
ON DUPLICATE KEY UPDATE harsha_house=VALUES(harsha_house);

CREATE TABLE IF NOT EXISTS tajik_yoga_defs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    yoga_key VARCHAR(24) NOT NULL, language VARCHAR(8) NOT NULL DEFAULT 'hi',
    name_hi VARCHAR(48) NOT NULL, nature VARCHAR(12) NOT NULL,
    lakshan TEXT NOT NULL, phal_text TEXT NOT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_tyd (yoga_key, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO tajik_yoga_defs (yoga_key,language,name_hi,nature,lakshan,phal_text) VALUES
('ikkabal','hi','इक्काबाल','शुभ','सभी ग्रह केन्द्र (1,4,7,10) या पणफर (2,5,8,11) में','वर्ष राज्य-सुख और सब प्रकार की सुख-सुविधा देता है।'),
('induvar','hi','इन्दुवार','अशुभ','सभी ग्रह आपोक्लिम (3,6,9,12) में','वर्ष/मास-प्रवेश में शुभ नहीं — प्रयास बिखरते हैं, फल अधूरे।'),
('ithasala','hi','इत्थशाल (मुथशिल)','शुभ','शीघ्र ग्रह मन्द से पीछे (भुक्तांश कम), परस्पर दृष्टि, दीप्तांश-भीतर — भेद: वर्तमान / पूर्ण (अंश समान) / भावी (राश्यन्त-सन्धि)','कार्य-सिद्धि — {between} के बीच; पूर्ण = तत्काल पूर्ण फल; भावी = फल आगे मिलेगा।'),
('israfa','hi','ईसराफ (मुसरिफ)','अशुभ','शीघ्र ग्रह मन्द से एक अंश भी आगे निकल गया (separating)','कार्य-क्षय — बात बनते-बनते बिगड़ती है; शुभ-ग्रह-जनित हो तो हानि नहीं (हिल्लाज-मत)।'),
('nakta','hi','नक्त','शुभ','लग्नेश-कार्येश में दृष्टि नहीं; बीच का शीघ्र ग्रह पीछे से तेज लेकर आगे को दे','मध्यस्थ व्यक्ति के द्वारा कार्य-सिद्धि होती है।'),
('yamaya','hi','यमया','शुभ','लग्नेश-कार्येश में दृष्टि नहीं; बीच का मन्द ग्रह दोनों को दीप्तांश-भीतर देखता हुआ तेज-सेतु बने','किसी बड़े/श्रेष्ठ व्यक्ति के माध्यम से वांछित कार्य सिद्ध।'),
('manau','hi','मणऊ','अशुभ','बनते इत्थशाल में मंगल/शनि 1-4-7 वैर-दृष्टि से शीघ्र ग्रह का तेज हर लें','बना-बनाया कार्य बिगड़ जाता है — क्रूर का हस्तक्षेप।'),
('kamboola','hi','कम्बूल','अति शुभ','लग्नेश-कार्येश का इत्थशाल + चन्द्रमा का भी किसी एक से इत्थशाल','इत्थशाल का फल कई गुणा — भेद (16) के अनुसार पूर्ण-सिद्धि से कष्ट-सिद्धि तक।'),
('gairikamboola','hi','गैरिकम्बूल','शुभ','शून्य-पद चन्द्र राश्यन्त में; प्रवेश-राशि का अपनी राशि का/उच्च ग्रह — उससे चन्द्र का इत्थशाल','तीसरे सहायक की सहायता से कार्य-सिद्धि; प्रवेश-ग्रह पद-हीन हो तो अशुभ।'),
('khallasara','hi','खल्लासर','अशुभ','शून्य-मार्गी चन्द्र लग्नेश-कार्येश किसी से न इत्थशाल करे, न युति','कम्बूल का सारा सहारा नष्ट — फल फीका।'),
('radda','hi','रद्द','अशुभ','दुर्बल ग्रह (अस्त/नीच/शत्रु-राशि/वक्री) का भावेश से इत्थशाल','तेज वहन नहीं होता — कार्य आदि-अन्त में असफल; केन्द्र↔आपोक्लिम भेद से आरम्भ/अन्त बिगड़ता।'),
('duphalikuttha','hi','दुफालिकुत्थ','शुभ','पद-युक्त (स्वगृह/उच्च/हद्दा/द्रेष्काण/नवांश) मन्द को पद-हीन शीघ्र का इत्थशाल','ग्राहक बली होने से कार्य फिर भी सिद्ध — शीघ्र वक्री/नीच न हो।'),
('dutthotthadavira','hi','दुत्थोत्थदिवीर','शुभ','लग्नेश-कार्येश दोनों निर्बल; पद-युक्त बली तृतीय ग्रह किसी से इत्थशाल कर सहायता दे','तीसरे बली की सहायता से कार्य बड़ी आसानी से सम्पन्न।'),
('tambira','hi','तम्बीर','शुभ','मुथशिल-रहित स्थिति में बली ग्रह राशि के अन्तिम अंश से अगली राशि के ग्रह को दीप्तांश-द्वारा तेज दे','प्राप्तकर्ता बली हो तो अभीष्ट कार्य सिद्ध।'),
('kuttha','hi','कुत्थ','शुभ','ग्रह बली — लग्न-स्थ > केन्द्र > केन्द्र-निकट पणफर; या स्वगृह/उच्च/हद्दा/नवांश-स्थ','बली लग्नेश/कार्येश कार्य साध देते हैं।'),
('duraph','hi','दुरफ','अशुभ','ग्रह निर्बल — 6/8/12 में, शत्रु/नीच, वक्री, अस्त, क्रूर-युत/क्रूर-दृष्ट, राहु-मुख/पुच्छ पर','निर्बल ग्रह कार्य बिगाड़ता है; चन्द्र-दुर्बलता विशेष घातक।')
ON DUPLICATE KEY UPDATE name_hi=VALUES(name_hi),nature=VALUES(nature),lakshan=VALUES(lakshan),phal_text=VALUES(phal_text);

CREATE TABLE IF NOT EXISTS tajik_kamboola_bheda (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    moon_adhikar VARCHAR(12) NOT NULL, lords_adhikar VARCHAR(12) NOT NULL,
    language VARCHAR(8) NOT NULL DEFAULT 'hi', phal_text TEXT NOT NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_tkb (moon_adhikar, lords_adhikar, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO tajik_kamboola_bheda (moon_adhikar,lords_adhikar,language,phal_text) VALUES
('उत्तम','उत्तम','hi','उत्तमोत्तम — वांछा-सिद्धि निश्चित और शीघ्र।'),
('उत्तम','मध्यम','hi','उत्तममध्यम — पहले पूर्ण, फिर मध्यम फल।'),
('उत्तम','सम','hi','उत्तम — शुभ फल; चन्द्र-बल से सिद्धि।'),
('उत्तम','अधम','hi','उत्तमाधम — कठिनाई से, पर सिद्धि हो जाती है।'),
('मध्यम','उत्तम','hi','मध्यमोत्तम — उत्तम फल; प्राप्ति सुखद।'),
('मध्यम','मध्यम','hi','मध्यममध्यम — प्रयत्न से मध्यम सिद्धि।'),
('मध्यम','सम','hi','मध्यम — साधारण फल।'),
('मध्यम','अधम','hi','मध्यमाधम — विलंब व कष्ट से आंशिक फल।'),
('सम','उत्तम','hi','समोत्तम — फल शुभ; अधिकार लग्नेश-कार्येश का।'),
('सम','मध्यम','hi','सममध्यम — साधारण धन/फल-लाभ।'),
('सम','सम','hi','समसम — फल अनिश्चित; अन्य बल देखें।'),
('सम','अधम','hi','समाधम — फल में कमी।'),
('अधम','उत्तम','hi','अधमोत्तम — कुछ परिश्रम पर सिद्धि (चन्द्र अधम, स्वामी उत्तम)।'),
('अधम','मध्यम','hi','अधममध्यम — कठिनाई से थोड़ा फल।'),
('अधम','सम','hi','अधम — कठिनाई; फल संदिग्ध।'),
('अधम','अधम','hi','अधमाधम — कार्य-विध्वंस और दुःखदायक।')
ON DUPLICATE KEY UPDATE phal_text=VALUES(phal_text);

INSERT INTO house_engine_config (cfg_key,cfg_value,notes) VALUES
('tajik_orb_mode','faster','दीप्तांश-जाँच: faster / mean — PL से मिलाएँ'),
('tajik_ekarksha_drishti','1','एकर्क्ष दृष्टि/योग मान्य'),
('tajik_hillaja_mata','1','शुभ-जनित ईसराफ में कार्य-नाश नहीं'),
('tajik_vaam_dakshin','1','वाम दृष्टि दक्षिण से बलवती (flag)'),
('tajik_mode','varsha','varsha / prashna / natal'),
('tajik_poorna_orb_kala','30','पूर्ण इत्थशाल की कला-सीमा (30 कला = 0.5°)')
ON DUPLICATE KEY UPDATE cfg_value=cfg_value;
-- End of migration 016.