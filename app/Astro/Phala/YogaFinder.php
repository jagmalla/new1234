<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

/**
 * Detects a small set of unambiguous classical yogas from the chart facts the
 * engine already computed (sign/house placements only — no new astronomy).
 * Used by the layout-v2 prediction panel (योग cards) and the overview tile.
 * Each yoga returns: name, कारण (why it formed) and फल (result), in Hindi.
 */
final class YogaFinder
{
    /** Own signs (index 0=Aries … 11=Pisces) for the five tara-grahas. */
    private const OWN = [
        'Mars' => [0, 7], 'Mercury' => [2, 5], 'Jupiter' => [8, 11],
        'Venus' => [1, 6], 'Saturn' => [9, 10],
    ];
    /** Exaltation sign per planet. */
    private const EXALT = ['Mars' => 9, 'Mercury' => 5, 'Jupiter' => 3, 'Venus' => 11, 'Saturn' => 6];

    /** Panch-Mahapurusha yoga name + result per planet. */
    private const MAHAPURUSHA = [
        'Mars' => ['रुचक योग', 'साहस, नेतृत्व-क्षमता और शारीरिक बल देता है; व्यक्ति निर्भीक व विजयी होता है।'],
        'Mercury' => ['भद्र योग', 'तीव्र बुद्धि, वाणी-कौशल और व्यापार में सफलता देता है।'],
        'Jupiter' => ['हंस योग', 'ज्ञान, धर्मपरायणता, सम्मान और सुखी गृहस्थ जीवन देता है।'],
        'Venus' => ['मालव्य योग', 'सौंदर्य, वैभव, वाहन-सुख और कला में सिद्धि देता है।'],
        'Saturn' => ['शश योग', 'अनुशासन, अधिकार, दीर्घायु और जन-समर्थन देता है।'],
    ];

    private const SIGN_HI = [
        'मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या',
        'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुंभ', 'मीन',
    ];
    private const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];

    /**
     * @param array<string,mixed> $chart computed chart (planets: sign_index, house)
     * @return list<array{name:string,why:string,result:string,good:bool}>
     */
    public static function find(array $chart): array
    {
        $P = $chart['planets'] ?? [];
        $sign = static fn(string $p): int => (int) ($P[$p]['sign_index'] ?? -99);
        $house = static fn(string $p): int => (int) ($P[$p]['house'] ?? 0);
        $hi = static fn(string $p): string => self::PLANET_HI[$p] ?? $p;
        $out = [];

        // --- Gajakesari: Jupiter in a kendra (1/4/7/10) from the Moon ---
        if (isset($P['Jupiter'], $P['Moon'])) {
            $rel = ((($sign('Jupiter') - $sign('Moon')) % 12) + 12) % 12 + 1; // 1..12 from Moon
            if (in_array($rel, [1, 4, 7, 10], true)) {
                $out[] = [
                    'name' => 'गजकेसरी योग', 'good' => true,
                    'why' => sprintf('गुरु, चंद्रमा से केंद्र (%d) में स्थित है।', $rel),
                    'result' => 'कीर्ति, बुद्धिमत्ता, स्थायी संपत्ति और समाज में उच्च प्रतिष्ठा देता है।',
                ];
            }
        }

        // --- Budhaditya: Sun + Mercury in the same sign ---
        if (isset($P['Sun'], $P['Mercury']) && $sign('Sun') === $sign('Mercury')) {
            $out[] = [
                'name' => 'बुधादित्य योग', 'good' => true,
                'why' => sprintf('सूर्य और बुध एक ही राशि (%s) में युति कर रहे हैं।', self::SIGN_HI[$sign('Sun')] ?? ''),
                'result' => 'तीक्ष्ण बुद्धि, प्रशासनिक योग्यता और राज-सम्मान देता है।',
            ];
        }

        // --- Chandra-Mangal: Moon + Mars conjunction ---
        if (isset($P['Moon'], $P['Mars']) && $sign('Moon') === $sign('Mars')) {
            $out[] = [
                'name' => 'चंद्र-मंगल योग', 'good' => true,
                'why' => sprintf('चंद्र और मंगल एक ही राशि (%s) में हैं।', self::SIGN_HI[$sign('Moon')] ?? ''),
                'result' => 'अर्जन-शक्ति और व्यावसायिक कुशलता बढ़ाता है; धन-संचय कराता है।',
            ];
        }

        // --- Guru-Chandal: Jupiter + Rahu conjunction (challenging) ---
        if (isset($P['Jupiter'], $P['Rahu']) && $sign('Jupiter') === $sign('Rahu')) {
            $out[] = [
                'name' => 'गुरु-चांडाल योग', 'good' => false,
                'why' => sprintf('गुरु और राहु एक ही राशि (%s) में युति कर रहे हैं।', self::SIGN_HI[$sign('Jupiter')] ?? ''),
                'result' => 'गुरु के शुभ फलों में बाधा; नीति-निर्णयों में भ्रम — गुरु की उपासना लाभकारी।',
            ];
        }

        // --- Panch Mahapurusha: planet in own/exalted sign AND in a kendra from Lagna ---
        foreach (self::MAHAPURUSHA as $pl => [$name, $result]) {
            if (!isset($P[$pl])) { continue; }
            $s = $sign($pl);
            $own = in_array($s, self::OWN[$pl] ?? [], true);
            $ex = (self::EXALT[$pl] ?? -1) === $s;
            if (($own || $ex) && in_array($house($pl), [1, 4, 7, 10], true)) {
                $out[] = [
                    'name' => $name, 'good' => true,
                    'why' => sprintf(
                        '%s %s राशि (%s) में, लग्न से केंद्र (%d भाव) में स्थित है।',
                        $hi($pl), $ex ? 'उच्च' : 'स्व', self::SIGN_HI[$s] ?? '', $house($pl)
                    ),
                    'result' => $result,
                ];
            }
        }

        // --- Kemadruma: no graha (excl. Sun/nodes) in the 2nd or 12th from the Moon ---
        if (isset($P['Moon'])) {
            $moonSign = $sign('Moon');
            $flank = false;
            foreach (['Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'] as $pl) {
                if (!isset($P[$pl])) { continue; }
                $rel = ((($sign($pl) - $moonSign) % 12) + 12) % 12 + 1;
                if ($rel === 2 || $rel === 12) { $flank = true; break; }
            }
            if (!$flank) {
                $out[] = [
                    'name' => 'केमद्रुम योग', 'good' => false,
                    'why' => 'चंद्रमा से द्वितीय और द्वादश — दोनों भाव ग्रह-रहित हैं।',
                    'result' => 'मानसिक अस्थिरता व आर्थिक उतार-चढ़ाव संभव; चंद्र-उपाय (सोमवार व्रत, मोती) सहायक।',
                ];
            }
        }

        return $out;
    }
}
