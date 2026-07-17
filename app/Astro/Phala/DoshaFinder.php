<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

/**
 * D1 दोष-पैनल — classical chart-level doshas detected from the computed
 * placements, each with its description and परिहार (remedies):
 *
 *   कालसर्प (12 types by Rahu's house, पूर्ण/आंशिक), ग्रहण दोष (सूर्य/चन्द्र +
 *   राहु/केतु), गुरु-चांडाल, अंगारक, विष योग (शनि+चन्द्र), केमद्रुम (with the
 *   standard kendra cancellations), शकट योग (चन्द्र गुरु से 6/8/12), and
 *   पितृ दोष (नवम भाव / सूर्य पर शनि-राहु-केतु का प्रभाव).
 *
 * Pure computation — no DB; remedy texts are the standard classical ones and
 * live here so staff can edit them in one place. Manglik / Sade-Sati / Shaap
 * are covered by their own existing panels and are not repeated here.
 */
final class DoshaFinder
{
    /** Rahu-house => Kaal-Sarp type name. */
    private const KS_TYPE = [
        1 => 'अनन्त', 2 => 'कुलिक', 3 => 'वासुकि', 4 => 'शंखपाल', 5 => 'पद्म', 6 => 'महापद्म',
        7 => 'तक्षक', 8 => 'कर्कोटक', 9 => 'शंखचूड़', 10 => 'घातक', 11 => 'विषधर', 12 => 'शेषनाग',
    ];

    /**
     * @param array<string,mixed> $chart CalculationEngine::computeChart output
     * @return list<array{key:string,name:string,detected:bool,severity:string,
     *               desc:string,why:string,remedies:list<string>}>
     */
    public static function compute(array $chart): array
    {
        $P = $chart['planets'] ?? [];
        if ($P === []) {
            return [];
        }
        $house = static fn (string $p): int => (int) ($P[$p]['house'] ?? 0);
        $lon = static fn (string $p): float => (float) ($P[$p]['sidereal_lon'] ?? 0.0);
        $seven = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
        $out = [];

        // ---------- कालसर्प: all seven classical planets on one side of the
        // Rahu→Ketu axis (by longitude arc). Any planet outside → आंशिक/भंग.
        $rl = $lon('Rahu');
        $inArc = static function (float $x) use ($rl): bool {
            // arc from Rahu forward 180° (Rahu→Ketu, zodiacal order)
            $d = fmod($x - $rl + 360.0, 360.0);
            return $d < 180.0;
        };
        $side1 = $side2 = [];
        foreach ($seven as $p) {
            if (!isset($P[$p])) { continue; }
            if ($inArc($lon($p))) { $side1[] = $p; } else { $side2[] = $p; }
        }
        $ksFull = ($side1 === [] || $side2 === []);
        $ksPartial = !$ksFull && (count($side1) <= 1 || count($side2) <= 1);
        $ksType = self::KS_TYPE[$house('Rahu')] ?? '';
        $out[] = [
            'key' => 'kaalsarp',
            'name' => 'कालसर्प दोष' . ($ksFull || $ksPartial ? ' — ' . $ksType : ''),
            'detected' => $ksFull || $ksPartial,
            'severity' => $ksFull ? 'पूर्ण' : ($ksPartial ? 'आंशिक' : ''),
            'desc' => 'सभी सात ग्रह राहु-केतु अक्ष के एक ओर हों तो कालसर्प — कार्यों में बार-बार बाधा, संघर्ष के बाद सफलता, स्वप्न-भय; राहु के भाव से ' . ($ksType !== '' ? $ksType . ' प्रकार का फल।' : 'प्रकार निर्धारित होता है।'),
            'why' => $ksFull
                ? 'सातों ग्रह एक ही ओर (राहु ' . $house('Rahu') . 'वें भाव में — ' . $ksType . ')'
                : ($ksPartial
                    ? 'केवल ' . implode('/', array_map(static fn ($x) => $x, count($side1) <= 1 ? $side1 : $side2)) . ' अक्ष से बाहर — दोष आंशिक/भंग'
                    : 'ग्रह अक्ष के दोनों ओर — दोष नहीं'),
            'remedies' => [
                'महामृत्युंजय मन्त्र का नित्य जाप; सोमवार को शिवलिंग पर जल-दूध अर्पण',
                'राहु के 18,000 जप (ॐ भ्रां भ्रीं भ्रौं सः राहवे नमः) व सर्प-सूक्त पाठ',
                'श्रावण मास या नाग-पंचमी को कालसर्प शान्ति; चाँदी का नाग-नागिन जोड़ा बहते जल में प्रवाहित करें',
                'बुधवार/शनिवार को जौ या कोयला बहते पानी में बहाएँ; गरीबों को भोजन कराएँ',
            ],
        ];

        // ---------- ग्रहण दोष (सूर्य / चन्द्र + राहु / केतु एक ही भाव में)
        foreach ([['Sun', 'सूर्य'], ['Moon', 'चन्द्र']] as [$lum, $lumHi]) {
            $with = [];
            foreach (['Rahu', 'Ketu'] as $node) {
                if ($house($lum) > 0 && $house($lum) === $house($node)) {
                    $with[] = $node === 'Rahu' ? 'राहु' : 'केतु';
                }
            }
            $det = $with !== [];
            $out[] = [
                'key' => 'grahan_' . strtolower($lum),
                'name' => $lumHi . ' ग्रहण दोष',
                'detected' => $det,
                'severity' => $det ? 'सामान्य' : '',
                'desc' => $lum === 'Sun'
                    ? 'सूर्य राहु/केतु के साथ — पिता-पक्ष, मान-सम्मान, सरकारी कार्य व आत्मविश्वास में ग्रहण; नेत्र/हृदय की चिंता।'
                    : 'चन्द्र राहु/केतु के साथ — मन में ग्रहण: वहम, चिंता, निर्णय-दुविधा; माता के सुख में कमी।',
                'why' => $det ? $lumHi . ' + ' . implode('+', $with) . ' (' . $house($lum) . 'वें भाव में)' : 'युति नहीं',
                'remedies' => $lum === 'Sun'
                    ? ['रविवार को आदित्य-हृदय स्तोत्र का पाठ; उगते सूर्य को जल-अर्घ्य', 'ग्रहण के समय दान (गेहूँ, गुड़, तांबा); पिता व पितरों की सेवा', 'राहु-शान्ति: जौ या कोयला बहते जल में']
                    : ['सोमवार व्रत; शिव-पार्वती पूजन; चन्द्र-मन्त्र (ॐ सों सोमाय नमः) 11,000 जप', 'ग्रहण के समय दूध-चावल-चाँदी का दान; माता का आशीर्वाद नित्य लें', 'चाँदी का चन्द्र-यन्त्र या मोती (सुयोग्य परामर्श से) धारण'],
            ];
        }

        // ---------- गुरु-चांडाल (गुरु + राहु)
        $det = $house('Jupiter') > 0 && $house('Jupiter') === $house('Rahu');
        $out[] = [
            'key' => 'chandal',
            'name' => 'गुरु-चांडाल योग',
            'detected' => $det,
            'severity' => $det ? 'सामान्य' : '',
            'desc' => 'गुरु-राहु की युति — गुरु/धर्म/शिक्षा के फल दूषित; निर्णय-दोष, बड़ों से मतभेद, अपयश का भय।',
            'why' => $det ? 'गुरु + राहु ' . $house('Jupiter') . 'वें भाव में' : 'युति नहीं',
            'remedies' => [
                'गुरुवार को पीली वस्तुओं (चना दाल, हल्दी, पीला वस्त्र) का दान; केले के वृक्ष की सेवा',
                'गुरु-मन्त्र (ॐ ग्रां ग्रीं ग्रौं सः गुरवे नमः) 19,000 जप; गुरुजनों/शिक्षकों का सम्मान',
                'गौ-सेवा व साधु-ब्राह्मण को भोजन; असत्य व अधार्मिक आचरण से दूर रहें',
            ],
        ];

        // ---------- अंगारक (मंगल + राहु/केतु)
        $with = [];
        foreach (['Rahu', 'Ketu'] as $node) {
            if ($house('Mars') > 0 && $house('Mars') === $house($node)) {
                $with[] = $node === 'Rahu' ? 'राहु' : 'केतु';
            }
        }
        $det = $with !== [];
        $out[] = [
            'key' => 'angarak',
            'name' => 'अंगारक योग',
            'detected' => $det,
            'severity' => $det ? 'सामान्य' : '',
            'desc' => 'मंगल-राहु/केतु की युति — क्रोध व आवेश की अधिकता, दुर्घटना/चोट, रक्त व अग्नि सम्बन्धी कष्ट, भाइयों से विवाद।',
            'why' => $det ? 'मंगल + ' . implode('+', $with) . ' (' . $house('Mars') . 'वें भाव में)' : 'युति नहीं',
            'remedies' => [
                'मंगलवार को हनुमान चालीसा; सिन्दूर का चोला; मीठी रोटी (गुड़ की) कुत्ते/गाय को',
                'मसूर दाल, लाल वस्त्र, तांबे का दान; क्रोध-नियन्त्रण का अभ्यास',
                'भूमि/वाहन/अग्नि के कार्यों में विशेष सावधानी; रक्तदान शुभ माना गया है',
            ],
        ];

        // ---------- विष योग (शनि + चन्द्र)
        $det = $house('Saturn') > 0 && $house('Saturn') === $house('Moon');
        $out[] = [
            'key' => 'vish',
            'name' => 'विष योग (शनि-चन्द्र)',
            'detected' => $det,
            'severity' => $det ? 'सामान्य' : '',
            'desc' => 'शनि-चन्द्र की युति — मन पर शनि का भार: उदासी, अकेलापन, ठंडेपन की प्रवृत्ति; माता के स्वास्थ्य की चिंता।',
            'why' => $det ? 'शनि + चन्द्र ' . $house('Moon') . 'वें भाव में' : 'युति नहीं',
            'remedies' => [
                'शनिवार को पीपल पर जल व सरसों के तेल का दीपक; शनि-मन्त्र जप',
                'सोमवार को शिव-अभिषेक (दूध-जल); माता की सेवा',
                'काले तिल व उड़द का दान; चाँदी धारण शुभ',
            ],
        ];

        // ---------- केमद्रुम (चन्द्र से 2रे/12वें व साथ में कोई ग्रह नहीं —
        // सूर्य/राहु/केतु की गिनती नहीं; केन्द्र-स्थित ग्रह से भंग)
        $mh = $house('Moon');
        $countable = ['Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
        $near = [];
        foreach ($countable as $p) {
            $d = (($house($p) - $mh) % 12 + 12) % 12;   // houses ahead of Moon
            if ($d === 0 || $d === 1 || $d === 11) {
                $near[] = $p;
            }
        }
        $kendraFromLagna = [];
        foreach ($countable as $p) {
            if (in_array($house($p), [1, 4, 7, 10], true)) {
                $kendraFromLagna[] = $p;
            }
        }
        $kemBase = $near === [];
        $kemBhang = $kemBase && $kendraFromLagna !== [];
        $out[] = [
            'key' => 'kemadruma',
            'name' => 'केमद्रुम दोष' . ($kemBhang ? ' (भंग)' : ''),
            'detected' => $kemBase && !$kemBhang,
            'severity' => $kemBase && !$kemBhang ? 'सामान्य' : '',
            'desc' => 'चन्द्र के आगे-पीछे व साथ कोई ग्रह न हो तो केमद्रुम — मानसिक अस्थिरता, आर्थिक उतार-चढ़ाव, अकेलापन; केन्द्र में ग्रह हों तो दोष भंग।',
            'why' => $kemBase
                ? ($kemBhang ? 'चन्द्र अकेला, परन्तु ' . implode(', ', $kendraFromLagna) . ' केन्द्र में — दोष भंग' : 'चन्द्र से 12/1/2 भाव रिक्त')
                : 'चन्द्र के समीप ग्रह: ' . implode(', ', $near),
            'remedies' => [
                'सोमवार का व्रत; शिव-पूजन; चन्द्र-यन्त्र की स्थापना',
                'पूर्णिमा को खीर का दान; माता व माता-तुल्य स्त्रियों की सेवा',
                'चाँदी के पात्र में दूध/पानी पिएँ; मोती (परामर्श से) धारण',
            ],
        ];

        // ---------- शकट योग (चन्द्र गुरु से 6/8/12)
        $dj = (($mh - $house('Jupiter')) % 12 + 12) % 12 + 1;   // Moon counted from Jupiter
        $det = in_array($dj, [6, 8, 12], true) && !in_array($mh, [1, 4, 7, 10], true);
        $out[] = [
            'key' => 'shakat',
            'name' => 'शकट योग',
            'detected' => $det,
            'severity' => $det ? 'सामान्य' : '',
            'desc' => 'चन्द्र गुरु से 6/8/12वें हो तो शकट — भाग्य गाड़ी के पहिये जैसा ऊपर-नीचे; बार-बार बनते-बिगड़ते काम (चन्द्र केन्द्र में हो तो भंग)।',
            'why' => $det ? 'चन्द्र गुरु से ' . $dj . 'वें स्थान पर' : 'स्थिति नहीं बनती',
            'remedies' => [
                'गुरुवार को गुरु-पूजन व पीली वस्तुओं का दान; सोमवार को शिव-पूजन',
                'श्री विष्णु सहस्रनाम या गुरु-स्तोत्र का पाठ',
                'आय-व्यय में संयम; एक साथ बड़े जोखिम से बचें',
            ],
        ];

        // ---------- पितृ दोष (नवम भाव में राहु/केतु/शनि, या सूर्य पर शनि/राहु/केतु की युति)
        $ninth = [];
        foreach (['Rahu', 'Ketu', 'Saturn'] as $p) {
            if ($house($p) === 9) {
                $ninth[] = $p === 'Rahu' ? 'राहु' : ($p === 'Ketu' ? 'केतु' : 'शनि');
            }
        }
        $sunAff = [];
        foreach (['Saturn', 'Rahu', 'Ketu'] as $p) {
            if ($house('Sun') > 0 && $house('Sun') === $house($p)) {
                $sunAff[] = $p === 'Rahu' ? 'राहु' : ($p === 'Ketu' ? 'केतु' : 'शनि');
            }
        }
        $det = $ninth !== [] || $sunAff !== [];
        $out[] = [
            'key' => 'pitru',
            'name' => 'पितृ दोष',
            'detected' => $det,
            'severity' => $det ? 'सामान्य' : '',
            'desc' => 'नवम (पितृ) भाव या सूर्य पर शनि/राहु/केतु का प्रभाव — पितरों की अतृप्ति का संकेत; भाग्योदय में विलम्ब, गृह-कलह, सन्तान-चिंता।',
            'why' => $det
                ? trim(($ninth !== [] ? 'नवम भाव में ' . implode('+', $ninth) : '') . ($ninth !== [] && $sunAff !== [] ? ' · ' : '') . ($sunAff !== [] ? 'सूर्य + ' . implode('+', $sunAff) : ''))
                : 'नवम भाव व सूर्य निर्दोष',
            'remedies' => [
                'अमावस्या को पितरों के निमित्त तर्पण/श्राद्ध; पीपल पर जल',
                'कौवे, गाय व कुत्ते को भोजन का अंश; ब्राह्मण-भोजन',
                'पिता व कुल के वृद्धजनों की सेवा; गया-श्राद्ध (सम्भव हो तो)',
            ],
        ];

        return $out;
    }
}
