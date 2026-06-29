-- =============================================================================
-- Auto Business — Dasha Prediction (दशा फल)
-- Migration 002: dasha_phala table + Sun (Surya) Mahadasha seed (Hindi)
-- -----------------------------------------------------------------------------
-- Holds the 9x9 = 81 Mahadasha/Antardasha summaries, per language. Each
-- combination (maha_lord, antar_lord, language) is unique so admin edits upsert
-- cleanly. Run once on the existing database:
--   mysql -u USER -p auto_business < migrations/002_dasha_phala.sql
--
-- NOTE: the Sun-Mahadasha rows seeded below are PLACEHOLDER Hindi summaries so
-- the feature can be demonstrated end-to-end. Replace setting_value via the
-- admin panel (or re-run this file after editing) with the exact text from your
-- document. The remaining 72 combinations are intentionally left empty and can
-- be added later — the UI shows a friendly placeholder for any missing one.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dasha_phala (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    maha_lord           ENUM('Sun','Moon','Mars','Mercury','Jupiter','Venus','Saturn','Rahu','Ketu') NOT NULL,
    antar_lord          ENUM('Sun','Moon','Mars','Mercury','Jupiter','Venus','Saturn','Rahu','Ketu') NOT NULL,
    language            VARCHAR(16)     NOT NULL DEFAULT 'hi',
    positive_text       TEXT            NULL,
    negative_text       TEXT            NULL,
    remedy_text         TEXT            NULL,
    updated_by_staff_id BIGINT UNSIGNED NULL,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dasha_phala_combo (maha_lord, antar_lord, language),
    CONSTRAINT fk_dasha_phala_staff FOREIGN KEY (updated_by_staff_id)
        REFERENCES staff (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Surya (Sun) Mahadasha — all 9 antardashas, Hindi (language = 'hi').
-- PLACEHOLDER text — replace with the exact summaries from your document.
-- -----------------------------------------------------------------------------
INSERT INTO dasha_phala (maha_lord, antar_lord, language, positive_text, negative_text, remedy_text) VALUES
('Sun','Sun','hi',
 'आत्मविश्वास, नेतृत्व क्षमता और सामाजिक प्रतिष्ठा में वृद्धि होती है। पिता एवं उच्चाधिकारियों से लाभ तथा सरकारी कार्यों में सफलता मिलती है।',
 'अहंकार, क्रोध और रक्तचाप संबंधी समस्याएँ हो सकती हैं। पिता के स्वास्थ्य की चिंता तथा अधिकारियों से मतभेद संभव है।',
 'प्रातः सूर्य को जल अर्पित करें और रविवार को आदित्य हृदय स्तोत्र का पाठ करें।'),
('Sun','Moon','hi',
 'माता का सहयोग, मानसिक शांति और धन लाभ के योग बनते हैं। यात्रा एवं नए संबंधों से लाभ मिलता है।',
 'मन चंचल रहता है; सर्दी-जुकाम व नेत्र संबंधी कष्ट हो सकते हैं। माता के स्वास्थ्य की चिंता रहती है।',
 'सोमवार को शिवजी पर जल चढ़ाएँ तथा सफेद वस्तुओं का दान करें।'),
('Sun','Mars','hi',
 'साहस, ऊर्जा और भूमि-भवन से लाभ। प्रतियोगिता एवं विवाद में विजय मिलती है।',
 'क्रोध, दुर्घटना तथा रक्त व अग्नि से हानि का भय। भाइयों से मतभेद संभव।',
 'मंगलवार को हनुमान चालीसा का पाठ करें और गुड़ का दान करें।'),
('Sun','Mercury','hi',
 'बुद्धि, व्यापार और शिक्षा में सफलता। लेखन, वाणी एवं गणना संबंधी कार्यों से लाभ।',
 'त्वचा रोग, वाणी दोष तथा व्यापार में अस्थिरता संभव।',
 'बुधवार को हरी वस्तुओं का दान करें और गणेश जी की उपासना करें।'),
('Sun','Jupiter','hi',
 'धर्म, ज्ञान और संतान सुख। उच्च पद, सम्मान एवं आर्थिक उन्नति के योग।',
 'अति आत्मविश्वास, यकृत संबंधी रोग तथा गुरुजनों से मतभेद।',
 'गुरुवार को केला व पीली वस्तुओं का दान करें और विष्णु सहस्रनाम का पाठ करें।'),
('Sun','Venus','hi',
 'सुख-सुविधा, वाहन और कला-संगीत में रुचि व लाभ। दाम्पत्य सुख की प्राप्ति।',
 'विलासिता की ओर झुकाव, नेत्र रोग तथा स्त्री-पक्ष से चिंता।',
 'शुक्रवार को सफेद मिष्ठान्न का दान करें और लक्ष्मी जी की उपासना करें।'),
('Sun','Saturn','hi',
 'परिश्रम से धीरे-धीरे स्थायित्व व लाभ। नौकरी में उन्नति संभव।',
 'पिता-पुत्र में मतभेद, स्वास्थ्य व मानसिक तनाव तथा कार्यों में विलंब।',
 'शनिवार को तेल, काले तिल व लोहे का दान करें तथा हनुमान जी की उपासना करें।'),
('Sun','Rahu','hi',
 'अकस्मात लाभ, विदेश यात्रा व तकनीकी कार्यों में सफलता संभव।',
 'मानसिक भ्रम, अपयश, षड्यंत्र तथा अधिकारियों से कष्ट का भय।',
 'राहु के निमित्त नारियल बहते जल में प्रवाहित करें और सरस्वती उपासना करें।'),
('Sun','Ketu','hi',
 'आध्यात्मिक रुचि, गूढ़ विद्या व मोक्ष की ओर झुकाव। अकस्मात सफलता।',
 'स्वास्थ्य कष्ट, पिता को चिंता तथा अकारण भय व अस्थिरता।',
 'गणेश जी की उपासना करें और कुत्ते को भोजन कराएँ।')
ON DUPLICATE KEY UPDATE
    positive_text = VALUES(positive_text),
    negative_text = VALUES(negative_text),
    remedy_text   = VALUES(remedy_text);

-- =============================================================================
-- End of migration 002.
-- =============================================================================
