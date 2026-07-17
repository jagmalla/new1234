<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Tajik;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the Tajik drishti / 16-yoga rule tables (migration 016): the dhruvanka
 * (sphuta-drishti anchor) row, deeptamsha orbs, the hadda (Egyptian terms)
 * segments, harsha sthana, the 16 yoga definitions + phal sentences, the
 * Kamboola 16-bheda grid and the tajik_* config. Resilient like
 * {@see \AutoBusiness\Astro\Saham\SahamRepository}: on a DB error load()
 * returns the baked classical defaults (the migration seed) so the panel
 * still works; lastError() surfaces the reason for staff.
 */
final class TajikRepository
{
    private static ?string $lastError = null;

    /** rashi_diff (0..11) => dhruvanka kala. Houses 2/6/8/12 anchor at 0. */
    private const DEFAULT_DHRUVANKA = [60, 0, 40, 15, 45, 0, 60, 0, 45, 15, 10, 0];

    /** Deeptamsha orbs (deg) per planet — Tajik Neelkanthi p.79. */
    private const DEFAULT_DEEPTAMSHA = [
        'Sun' => 15.0, 'Moon' => 12.0, 'Mars' => 8.0, 'Mercury' => 7.0,
        'Jupiter' => 9.0, 'Venus' => 7.0, 'Saturn' => 9.0,
    ];

    /**
     * Hadda (Egyptian terms): per sign index (0=Aries..11=Pisces) a list of
     * [upper°, lord] bands — the lord rules from the previous band's upper
     * bound up to (excl.) its own.
     */
    private const DEFAULT_HADDA = [
        0 => [[6, 'Jupiter'], [12, 'Venus'], [20, 'Mercury'], [25, 'Mars'], [30, 'Saturn']],
        1 => [[8, 'Venus'], [14, 'Mercury'], [22, 'Jupiter'], [27, 'Saturn'], [30, 'Mars']],
        2 => [[6, 'Mercury'], [12, 'Jupiter'], [17, 'Venus'], [24, 'Mars'], [30, 'Saturn']],
        3 => [[7, 'Mars'], [13, 'Venus'], [19, 'Mercury'], [26, 'Jupiter'], [30, 'Saturn']],
        4 => [[6, 'Jupiter'], [11, 'Venus'], [18, 'Saturn'], [24, 'Mercury'], [30, 'Mars']],
        5 => [[7, 'Mercury'], [17, 'Venus'], [21, 'Jupiter'], [28, 'Mars'], [30, 'Saturn']],
        6 => [[6, 'Saturn'], [14, 'Mercury'], [21, 'Jupiter'], [28, 'Venus'], [30, 'Mars']],
        7 => [[7, 'Mars'], [11, 'Venus'], [19, 'Mercury'], [24, 'Jupiter'], [30, 'Saturn']],
        8 => [[12, 'Jupiter'], [17, 'Venus'], [21, 'Mercury'], [26, 'Saturn'], [30, 'Mars']],
        9 => [[7, 'Mercury'], [14, 'Jupiter'], [22, 'Venus'], [26, 'Saturn'], [30, 'Mars']],
        10 => [[7, 'Mercury'], [13, 'Venus'], [20, 'Jupiter'], [25, 'Mars'], [30, 'Saturn']],
        11 => [[12, 'Venus'], [16, 'Jupiter'], [19, 'Mercury'], [28, 'Mars'], [30, 'Saturn']],
    ];

    /** Harsha sthana house per planet (shloka 76). */
    private const DEFAULT_HARSHA = [
        'Sun' => 9, 'Moon' => 3, 'Mars' => 6, 'Mercury' => 1,
        'Jupiter' => 11, 'Venus' => 5, 'Saturn' => 12,
    ];

    /** The 16 yoga definitions (migration 016 seed) — DB overrides. */
    private const DEFAULT_YOGAS = [
        'ikkabal' => ['name_hi' => 'इक्काबाल', 'nature' => 'शुभ', 'lakshan' => 'सभी ग्रह केन्द्र (1,4,7,10) या पणफर (2,5,8,11) में', 'phal_text' => 'वर्ष राज्य-सुख और सब प्रकार की सुख-सुविधा देता है।'],
        'induvar' => ['name_hi' => 'इन्दुवार', 'nature' => 'अशुभ', 'lakshan' => 'सभी ग्रह आपोक्लिम (3,6,9,12) में', 'phal_text' => 'वर्ष/मास-प्रवेश में शुभ नहीं — प्रयास बिखरते हैं, फल अधूरे।'],
        'ithasala' => ['name_hi' => 'इत्थशाल (मुथशिल)', 'nature' => 'शुभ', 'lakshan' => 'शीघ्र ग्रह मन्द से पीछे (भुक्तांश कम), परस्पर दृष्टि, दीप्तांश-भीतर — भेद: वर्तमान / पूर्ण (अंश समान) / भावी (राश्यन्त-सन्धि)', 'phal_text' => 'कार्य-सिद्धि — {between} के बीच; पूर्ण = तत्काल पूर्ण फल; भावी = फल आगे मिलेगा।'],
        'israfa' => ['name_hi' => 'ईसराफ (मुसरिफ)', 'nature' => 'अशुभ', 'lakshan' => 'शीघ्र ग्रह मन्द से एक अंश भी आगे निकल गया (separating)', 'phal_text' => 'कार्य-क्षय — बात बनते-बनते बिगड़ती है; शुभ-ग्रह-जनित हो तो हानि नहीं (हिल्लाज-मत)।'],
        'nakta' => ['name_hi' => 'नक्त', 'nature' => 'शुभ', 'lakshan' => 'लग्नेश-कार्येश में दृष्टि नहीं; बीच का शीघ्र ग्रह पीछे से तेज लेकर आगे को दे', 'phal_text' => 'मध्यस्थ व्यक्ति के द्वारा कार्य-सिद्धि होती है।'],
        'yamaya' => ['name_hi' => 'यमया', 'nature' => 'शुभ', 'lakshan' => 'लग्नेश-कार्येश में दृष्टि नहीं; बीच का मन्द ग्रह दोनों को दीप्तांश-भीतर देखता हुआ तेज-सेतु बने', 'phal_text' => 'किसी बड़े/श्रेष्ठ व्यक्ति के माध्यम से वांछित कार्य सिद्ध।'],
        'manau' => ['name_hi' => 'मणऊ', 'nature' => 'अशुभ', 'lakshan' => 'बनते इत्थशाल में मंगल/शनि 1-4-7 वैर-दृष्टि से शीघ्र ग्रह का तेज हर लें', 'phal_text' => 'बना-बनाया कार्य बिगड़ जाता है — क्रूर का हस्तक्षेप।'],
        'kamboola' => ['name_hi' => 'कम्बूल', 'nature' => 'अति शुभ', 'lakshan' => 'लग्नेश-कार्येश का इत्थशाल + चन्द्रमा का भी किसी एक से इत्थशाल', 'phal_text' => 'इत्थशाल का फल कई गुणा — भेद (16) के अनुसार पूर्ण-सिद्धि से कष्ट-सिद्धि तक।'],
        'gairikamboola' => ['name_hi' => 'गैरिकम्बूल', 'nature' => 'शुभ', 'lakshan' => 'शून्य-पद चन्द्र राश्यन्त में; प्रवेश-राशि का स्वगृही/उच्च ग्रह — उससे चन्द्र का इत्थशाल', 'phal_text' => 'तीसरे सहायक की सहायता से कार्य-सिद्धि; प्रवेश-ग्रह पद-हीन हो तो अशुभ।'],
        'khallasara' => ['name_hi' => 'खल्लासर', 'nature' => 'अशुभ', 'lakshan' => 'शून्य-मार्गी चन्द्र लग्नेश-कार्येश किसी से न इत्थशाल करे, न युति', 'phal_text' => 'कम्बूल का सारा सहारा नष्ट — फल फीका।'],
        'radda' => ['name_hi' => 'रद्द', 'nature' => 'अशुभ', 'lakshan' => 'दुर्बल ग्रह (अस्त/नीच/शत्रु-राशि/वक्री) का भावेश से इत्थशाल', 'phal_text' => 'तेज वहन नहीं होता — कार्य आदि-अन्त में असफल; केन्द्र↔आपोक्लिम भेद से आरम्भ/अन्त बिगड़ता।'],
        'duphalikuttha' => ['name_hi' => 'दुफालिकुत्थ', 'nature' => 'शुभ', 'lakshan' => 'पद-युक्त (स्वगृह/उच्च/हद्दा/द्रेष्काण/नवांश) मन्द को पद-हीन शीघ्र का इत्थशाल', 'phal_text' => 'ग्राहक बली होने से कार्य फिर भी सिद्ध — शीघ्र वक्री/नीच न हो।'],
        'dutthotthadavira' => ['name_hi' => 'दुत्थोत्थदिवीर', 'nature' => 'शुभ', 'lakshan' => 'लग्नेश-कार्येश दोनों निर्बल; पद-युक्त बली तृतीय ग्रह किसी से इत्थशाल कर सहायता दे', 'phal_text' => 'तीसरे बली की सहायता से कार्य बड़ी आसानी से सम्पन्न।'],
        'tambira' => ['name_hi' => 'तम्बीर', 'nature' => 'शुभ', 'lakshan' => 'मुथशिल-रहित स्थिति में बली ग्रह राशि के अन्तिम अंश से अगली राशि के ग्रह को दीप्तांश-द्वारा तेज दे', 'phal_text' => 'प्राप्तकर्ता बली हो तो अभीष्ट कार्य सिद्ध।'],
        'kuttha' => ['name_hi' => 'कुत्थ', 'nature' => 'शुभ', 'lakshan' => 'ग्रह बली — लग्न-स्थ > केन्द्र > केन्द्र-निकट पणफर; या स्वगृह/उच्च/हद्दा/नवांश-स्थ', 'phal_text' => 'बली लग्नेश/कार्येश कार्य साध देते हैं।'],
        'duraph' => ['name_hi' => 'दुरफ', 'nature' => 'अशुभ', 'lakshan' => 'ग्रह निर्बल — 6/8/12 में, शत्रु/नीच, वक्री, अस्त, क्रूर-युत/क्रूर-दृष्ट, राहु-मुख/पुच्छ पर', 'phal_text' => 'निर्बल ग्रह कार्य बिगाड़ता है; चन्द्र-दुर्बलता विशेष घातक।'],
    ];

    /** Kamboola bheda phal: "moonAdhikar|lordsAdhikar" => text. */
    private const DEFAULT_KAMBOOLA_BHEDA = [
        'उत्तम|उत्तम' => 'उत्तमोत्तम — वांछा-सिद्धि निश्चित और शीघ्र।',
        'उत्तम|मध्यम' => 'उत्तममध्यम — पहले पूर्ण, फिर मध्यम फल।',
        'उत्तम|सम' => 'उत्तम — शुभ फल; चन्द्र-बल से सिद्धि।',
        'उत्तम|अधम' => 'उत्तमाधम — कठिनाई से, पर सिद्धि हो जाती है।',
        'मध्यम|उत्तम' => 'मध्यमोत्तम — उत्तम फल; प्राप्ति सुखद।',
        'मध्यम|मध्यम' => 'मध्यममध्यम — प्रयत्न से मध्यम सिद्धि।',
        'मध्यम|सम' => 'मध्यम — साधारण फल।',
        'मध्यम|अधम' => 'मध्यमाधम — विलंब व कष्ट से आंशिक फल।',
        'सम|उत्तम' => 'समोत्तम — फल शुभ; अधिकार लग्नेश-कार्येश का।',
        'सम|मध्यम' => 'सममध्यम — साधारण धन/फल-लाभ।',
        'सम|सम' => 'समसम — फल अनिश्चित; अन्य बल देखें।',
        'सम|अधम' => 'समाधम — फल में कमी।',
        'अधम|उत्तम' => 'अधमोत्तम — कुछ परिश्रम पर सिद्धि (चन्द्र अधम, स्वामी उत्तम)।',
        'अधम|मध्यम' => 'अधममध्यम — कठिनाई से थोड़ा फल।',
        'अधम|सम' => 'अधम — कठिनाई; फल संदिग्ध।',
        'अधम|अधम' => 'अधमाधम — कार्य-विध्वंस और दुःखदायक।',
    ];

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   dhruvanka: array<int,int>, deeptamsha: array<string,float>,
     *   hadda: array<int,list<array{0:float,1:string}>>, harsha: array<string,int>,
     *   yogas: array<string,array<string,string>>, kamboola_bheda: array<string,string>,
     *   config: array<string,string>
     * }
     */
    public static function load(string $language = 'hi'): array
    {
        self::$lastError = null;
        $out = self::defaults();
        try {
            $pdo = Database::pdo();

            $dh = [];
            foreach ($pdo->query('SELECT rashi_diff, dhruvanka FROM tajik_dhruvanka') as $r) {
                $dh[(int) $r['rashi_diff']] = (int) $r['dhruvanka'];
            }
            if (count($dh) === 12) { $out['dhruvanka'] = $dh; }

            $dt = [];
            foreach ($pdo->query('SELECT planet, orb_deg FROM tajik_deeptamsha') as $r) {
                $dt[(string) $r['planet']] = (float) $r['orb_deg'];
            }
            if ($dt !== []) { $out['deeptamsha'] = $dt + $out['deeptamsha']; }

            $hd = [];
            foreach ($pdo->query('SELECT sign, lord, deg_to FROM tajik_hadda ORDER BY sign, deg_from') as $r) {
                $hd[(int) $r['sign'] - 1][] = [(float) $r['deg_to'], (string) $r['lord']];   // DB signs are 1-based
            }
            if (count($hd) === 12) { $out['hadda'] = $hd; }

            $hs = [];
            foreach ($pdo->query('SELECT planet, harsha_house FROM tajik_harsha_sthana') as $r) {
                $hs[(string) $r['planet']] = (int) $r['harsha_house'];
            }
            if ($hs !== []) { $out['harsha'] = $hs + $out['harsha']; }

            $yg = [];
            $stmt = $pdo->prepare('SELECT yoga_key, name_hi, nature, lakshan, phal_text FROM tajik_yoga_defs WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $yg[(string) $r['yoga_key']] = [
                    'name_hi' => (string) $r['name_hi'], 'nature' => (string) $r['nature'],
                    'lakshan' => (string) $r['lakshan'], 'phal_text' => (string) $r['phal_text'],
                ];
            }
            if ($yg !== []) { $out['yogas'] = $yg + $out['yogas']; }

            $kb = [];
            $stmt = $pdo->prepare('SELECT moon_adhikar, lords_adhikar, phal_text FROM tajik_kamboola_bheda WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $kb[(string) $r['moon_adhikar'] . '|' . (string) $r['lords_adhikar']] = (string) $r['phal_text'];
            }
            if ($kb !== []) { $out['kamboola_bheda'] = $kb + $out['kamboola_bheda']; }

            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    if (str_starts_with((string) $r['cfg_key'], 'tajik_')) {
                        $out['config'][(string) $r['cfg_key']] = (string) $r['cfg_value'];
                    }
                }
            } catch (Throwable $e) { /* config optional */ }

            return $out;
        } catch (Throwable $e) {
            // DB unreachable / tables not imported yet: baked classical data keeps
            // the panel alive (the production DB, once migrated, overrides this).
            self::$lastError = $e->getMessage();
            error_log('Tajik rules load failed (using baked fallback): ' . $e->getMessage());
            return $out;
        }
    }

    /** @return array<string,mixed> */
    private static function defaults(): array
    {
        return [
            'dhruvanka' => self::DEFAULT_DHRUVANKA,
            'deeptamsha' => self::DEFAULT_DEEPTAMSHA,
            'hadda' => self::DEFAULT_HADDA,
            'harsha' => self::DEFAULT_HARSHA,
            'yogas' => self::DEFAULT_YOGAS,
            'kamboola_bheda' => self::DEFAULT_KAMBOOLA_BHEDA,
            'config' => [
                'tajik_orb_mode' => 'faster',          // दीप्तांश-जाँच: faster / mean
                'tajik_ekarksha_drishti' => '1',       // एकर्क्ष दृष्टि/योग मान्य
                'tajik_hillaja_mata' => '1',           // शुभ-जनित ईसराफ में कार्य-नाश नहीं
                'tajik_vaam_dakshin' => '1',           // वाम दृष्टि दक्षिण से बलवती
                'tajik_mode' => 'varsha',
                'tajik_poorna_orb_kala' => '30',       // पूर्ण इत्थशाल की कला-सीमा (30 कला = 0.5°)
            ],
        ];
    }
}
