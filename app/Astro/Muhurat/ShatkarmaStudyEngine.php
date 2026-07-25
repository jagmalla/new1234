<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

/**
 * 📜 अभिचार / षट्कर्म — अध्ययन + अभ्यास-परीक्षा + तिथि-जाँच (Study tool).
 *
 * STUDY-ONLY: यह किसी कुण्डली से "किस पर कब प्रयोग करें" नहीं बताता। यह केवल
 * ग्रंथ के काल-वर्गीकरण को (1) पढ़ने योग्य कार्ड, (2) स्व-परीक्षा प्रश्न, तथा
 * (3) आज के गोचर-नक्षत्र की संज्ञा-जाँच के रूप में प्रस्तुत करता है — जिससे छात्र
 * अपनी पढ़ाई इंजन-गणना से मिलाकर सत्यापित कर सके।
 */
final class ShatkarmaStudyEngine
{
    private const NAK_ARC = 40.0 / 3.0;
    private const D  = MuhuratChintamaniData::class;
    private const SD = ShatkarmaData::class;

    /**
     * @param float $moonLon गोचर चन्द्र निरयन देशान्तर (तिथि-जाँच हेतु)
     */
    public static function compute(float $moonLon): array
    {
        $moonNak = (int) floor(fmod($moonLon + 360.0, 360.0) / self::NAK_ARC) % 27;
        $sanjna  = self::D::SANJNA[$moonNak];
        $map     = self::SD::SANJNA_KARMA[$sanjna] ?? ['karma' => '—', 'tone' => 'info', 'label' => 'मध्यम'];

        return [
            'ok' => true,
            'disclaimer' => self::SD::DISCLAIMER,
            'cards' => self::SD::SHATKARMA,          // अध्ययन-सन्दर्भ
            'quiz' => self::SD::QUIZ,                 // अभ्यास-परीक्षा
            'verify' => [                            // तिथि-जाँच (आज के गोचर-चन्द्र से)
                'nak' => self::D::NAK_HI[$moonNak],
                'sanjna' => $sanjna,
                'karma' => $map['karma'],
                'tone' => $map['tone'],   // pos/info/neg → छात्र-निर्णय से मिलान
                'label' => $map['label'], // सौम्य / मध्यम / उग्र
            ],
        ];
    }
}
