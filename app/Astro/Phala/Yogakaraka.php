<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

/**
 * Yogakaraka classification — Brihat Parashara Hora Shastra, Adhyaya 32.
 *
 * For the chart's lagna, classifies every graha as योगकारक / शुभ / पाप / मारक /
 * सम from its house-lordship (kendra+trikona → yogakaraka; trikonesh/lagnesh →
 * benefic; trishadaya-3/6/11 or 8th → malefic; 2/7 lord → marak; kendra-only or
 * 2/12 lord → neutral, with the kendradhipati-dosha note). The classical
 * twelve-lagna summary table (shlokas 19-39) is baked for cross-reference.
 *
 * This is the shared "which planet does what for THIS lagna" layer — the yoga
 * and shaap engines use it to tag each yoga's फल-दशा with the karaka's role.
 */
final class Yogakaraka
{
    private const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];
    private const SIGN_HI = ['मेष', 'वृष', 'मिथुन', 'कर्क', 'सिंह', 'कन्या', 'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुम्भ', 'मीन'];
    private const CLASSICAL = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];

    /** Baked 12-lagna summary (shlokas 19-39), keyed by lagna sign index 0..11. */
    public const LAGNA_TABLE = [
        0 => ['paap' => 'शनि, बुध, शुक्र', 'shubh' => 'सूर्य, गुरु', 'yogakaraka' => '— (शनि व गुरु अन्य योगकारक से मिलकर)', 'marak' => 'शुक्र (2,7 स्वामी); मारकेश-सम्बन्ध से शनि आदि भी', 'sam' => 'शनि व गुरु मिश्र', 'shloka' => '19–21'],
        1 => ['paap' => 'गुरु, शुक्र, चन्द्र', 'shubh' => 'सूर्य, शनि', 'yogakaraka' => 'शनि (साक्षात् राजयोगकारक)', 'marak' => 'गुरु, मंगल, बुध (मारक-स्वभाव)', 'sam' => '—', 'shloka' => '22–23'],
        2 => ['paap' => 'मंगल, गुरु, सूर्य', 'shubh' => 'शुक्र (निर्विवाद), बुध (लग्नेश)', 'yogakaraka' => '—', 'marak' => 'मारकत्व रहित (चन्द्र)', 'sam' => 'शनि व गुरु साहचर्य से; चन्द्र साधारण शुभ', 'shloka' => '24–25'],
        3 => ['paap' => 'शुक्र, बुध', 'shubh' => 'मंगल, गुरु, चन्द्र', 'yogakaraka' => 'मंगल (साक्षात्)', 'marak' => 'शनि (साहचर्य से)', 'sam' => 'सूर्य, राहु आदि साहचर्य से', 'shloka' => '25–26'],
        4 => ['paap' => 'बुध, शुक्र, शनि', 'shubh' => 'मंगल, गुरु, सूर्य', 'yogakaraka' => '— (गुरु-शुक्र केवल सम्बन्ध से फल नहीं)', 'marak' => 'शनि (मारक)', 'sam' => 'चन्द्र साहचर्य से', 'shloka' => '27–28'],
        5 => ['paap' => 'मंगल, गुरु, चन्द्र', 'shubh' => 'बुध, शुक्र', 'yogakaraka' => 'बुध, शुक्र', 'marak' => 'शुक्र (मारक भी)', 'sam' => 'सूर्य साहचर्य से', 'shloka' => '29–30'],
        6 => ['paap' => 'गुरु, सूर्य, मंगल', 'shubh' => 'शनि, बुध', 'yogakaraka' => 'बुध-चन्द्र (राजयोगकारी)', 'marak' => 'मंगल; गुरु आदि पाप-मारक', 'sam' => 'शुक्र सम', 'shloka' => '30–31'],
        7 => ['paap' => 'शुक्र, बुध, शनि', 'shubh' => 'गुरु, चन्द्र, मंगल', 'yogakaraka' => 'सूर्य-चन्द्र', 'marak' => 'शुक्रादि शेष पापी मारक', 'sam' => 'मंगल शुभ', 'shloka' => '32–33'],
        8 => ['paap' => 'शुक्र', 'shubh' => 'मंगल, सूर्य', 'yogakaraka' => 'सूर्य-बुध', 'marak' => 'शनि', 'sam' => 'गुरु सम; शुक्र अशुभ', 'shloka' => '34–35'],
        9 => ['paap' => 'मंगल, गुरु, चन्द्र', 'shubh' => '— (शुक्र योगकारक)', 'yogakaraka' => 'शुक्र', 'marak' => 'मंगलादि पाप ग्रह; शनि स्वयं मारक नहीं', 'sam' => 'शुक्र-बुध सम; सूर्य सम', 'shloka' => '36–37'],
        10 => ['paap' => 'गुरु, चन्द्र, मंगल', 'shubh' => 'शुक्र, शनि', 'yogakaraka' => 'शुक्र (राजयोगकारक)', 'marak' => '—', 'sam' => 'बुध मध्यम', 'shloka' => '38–39'],
        11 => ['paap' => 'शनि, शुक्र, सूर्य, बुध', 'shubh' => 'मंगल, चन्द्र', 'yogakaraka' => 'गुरु-मंगल (मंगल विशेष योगकारक)', 'marak' => 'शनि, बुध', 'sam' => '—', 'shloka' => '39'],
    ];

    /**
     * @param array<string,mixed> $chart CalculationEngine::computeChart output
     * @return array{lagna:int, lagna_hi:string, roles:array<string,array<string,mixed>>, table:array<string,string>}
     */
    public static function classify(array $chart): array
    {
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $P = $chart['planets'] ?? [];
        $elong = fmod(((float) ($P['Moon']['sidereal_lon'] ?? 0.0)) - ((float) ($P['Sun']['sidereal_lon'] ?? 0.0)) + 360.0, 360.0);
        $moonWax = $elong >= 90.0 && $elong <= 270.0;

        // Houses each classical planet owns (whole-sign, from the lagna).
        $owns = array_fill_keys(self::CLASSICAL, []);
        foreach (($chart['houses'] ?? []) as $hn => $H) {
            $lord = (string) ($H['lord'] ?? '');
            if (isset($owns[$lord])) { $owns[$lord][] = (int) $hn; }
        }

        $roles = [];
        foreach (self::CLASSICAL as $p) {
            $o = $owns[$p];
            $hasKendra = array_intersect($o, [4, 7, 10]) !== [];
            $hasTrikona = array_intersect($o, [5, 9]) !== [];
            $isLagnesh = in_array(1, $o, true);
            $hasTrishad = array_intersect($o, [3, 6, 11]) !== [];
            $hasEighth = in_array(8, $o, true);
            $isMarak = array_intersect($o, [2, 7]) !== [];
            $naturalBenefic = in_array($p, ['Jupiter', 'Venus', 'Mercury'], true) || ($p === 'Moon' && $moonWax);

            if ($hasKendra && $hasTrikona) {
                $role = 'yogakaraka';
                $note = 'केन्द्र + त्रिकोण दोनों का स्वामी — योगकारक (राजयोगकारी)।';
                if ($hasTrishad || $hasEighth) { $note .= ' (3/6/8/11 का भी स्वामी — पूर्ण फल हेतु अन्य योगकारक-सम्बन्ध आवश्यक।)'; }
            } elseif ($isLagnesh || $hasTrikona) {
                $role = 'benefic';
                $note = $isLagnesh ? 'लग्नेश — सदैव शुभ।' : 'त्रिकोणेश (5/9) — शुभ।';
                if ($hasTrishad) { $note .= ' परन्तु त्रिषडाय (3/6/11) का भी स्वामी — मिश्र।'; }
            } elseif ($hasTrishad || ($hasEighth && !in_array($p, ['Sun', 'Moon'], true))) {
                $role = 'malefic';
                $note = $hasTrishad ? 'त्रिषडायेश (3/6/11) — पाप फल।' : 'अष्टमेश — प्रायः अशुभ।';
            } else {
                $role = 'neutral';
                if ($hasKendra) {
                    $note = 'केवल केन्द्रेश — केन्द्राधिपत्य दोष' . ($naturalBenefic ? ' (शुभ फल स्थगित)।' : ' (पाप फल स्थगित)।');
                } elseif ($hasEighth) {
                    $note = 'अष्टमेश (सूर्य/चन्द्र) — विशेष पापी नहीं।';
                } else {
                    $note = '2/12 का स्वामी — साहचर्य पर निर्भर (सम)।';
                }
            }

            $roleHi = ['yogakaraka' => 'योगकारक', 'benefic' => 'शुभ', 'malefic' => 'पाप', 'neutral' => 'सम'][$role];
            $roles[$p] = [
                'planet_hi' => self::PLANET_HI[$p], 'role' => $role, 'role_hi' => $roleHi,
                'owns' => $o, 'is_marak' => $isMarak, 'note' => $note,
            ];
        }

        // Rahu/Ketu — no rashi lordship; classified by tenancy/association (shloka 17-18).
        foreach (['Rahu', 'Ketu'] as $node) {
            if (!isset($P[$node])) { continue; }
            $h = (int) ($P[$node]['house'] ?? 0);
            $inDus = in_array($h, [6, 8, 12], true);
            $roles[$node] = [
                'planet_hi' => self::PLANET_HI[$node],
                'role' => $inDus ? 'malefic' : 'neutral',
                'role_hi' => $inDus ? 'पाप' : 'सम',
                'owns' => [], 'is_marak' => false,
                'note' => 'जिस भाव (' . $h . ') में या जिस भावेश के साथ — वैसा फल (श्लोक 17-18)।',
            ];
        }

        return [
            'lagna' => $ascSign,
            'lagna_hi' => self::SIGN_HI[$ascSign] ?? '',
            'roles' => $roles,
            'table' => self::LAGNA_TABLE[$ascSign] ?? [],
        ];
    }

    /** Vimshottari Mahadasha period of a planet from the natal dasha, as [startJd,endJd]|null. */
    public static function dashaPeriod(array $chart, string $planet): ?array
    {
        foreach (($chart['dasha']['mahadashas'] ?? []) as $md) {
            if (($md['lord'] ?? '') === $planet) {
                return [(float) $md['start_jd'], (float) $md['end_jd']];
            }
        }
        return null;
    }

    public static function planetHi(string $p): string
    {
        return self::PLANET_HI[$p] ?? $p;
    }
}
