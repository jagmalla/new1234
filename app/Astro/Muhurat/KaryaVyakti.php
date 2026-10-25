<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Milan\GunaMilan;
use AutoBusiness\Astro\Milan\MilanRepository;
use AutoBusiness\Astro\Muhurat\PanchangNiyamData as P;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Http\CalcController;
use AutoBusiness\Http\MilanController;

/**
 * 👤 कार्य-मुहूर्त — "किसके लिए मुहूर्त?"
 *
 * हर कार्य में किसका (या किन दोनों का) बल देखा जाए: विवाह में वर व कन्या, गोद भराई
 * में गर्भवती माता, संस्कारों में शिशु, बाक़ी में एक व्यक्ति (गृहस्वामी, यजमान,
 * यात्री, रोगी …)। व्यक्ति जन्म-विवरण से बनता है, या जन्म-समय न पता हो तो नाम के
 * पहले अक्षर से (नाम-राशि व नक्षत्र-चरण)। साथ में स्थिति के अतिरिक्त प्रश्न (विवाह
 * की तिथि, LMP, ज्येष्ठ संतान …) और उन्हें जाँचने की तालिकाएँ (घात चक्र, अंग-राशि)।
 * नियम {@see KaryaNirnay} लगाता है; पन्ना {@see TithiKhojCatalog} से प्रोफ़ाइल पाता है।
 */
final class KaryaVyakti
{
    /** भूमिका => [पूरा नाम, छोटा नाम (सूची में आगे लगता है), चिह्न, नियत लिंग] */
    public const ROLES = [
        'self' => ['जातक (जिनके लिए मुहूर्त)', 'जातक', '👤', ''],
        'var' => ['वर (Boy)', 'वर', '🤵', 'm'],
        'kanya' => ['कन्या (Girl)', 'कन्या', '👰', 'f'],
        'child' => ['बालक / बालिका', 'शिशु', '👶', ''],
        'mother' => ['गर्भवती माता', 'माता', '🤰', 'f'],
        'wife' => ['पत्नी', 'पत्नी', '👩', 'f'],
        'husband' => ['पति', 'पति', '👨', 'm'],
        'partner' => ['साझेदार / दूसरा पक्ष', 'दूसरा पक्ष', '🤝', ''],
    ];

    /** स्थिति के अतिरिक्त प्रश्न — कूट => [प्रकार, लेबल, सहायक पंक्ति] */
    public const EXTRAS = [
        'first' => ['check', 'ज्येष्ठ (पहली) संतान', 'ज्येष्ठ मास में ज्येष्ठ संतान का यह संस्कार वर्जित'],
        'mpreg' => ['check', 'बच्चे की माता 5 मास से अधिक गर्भवती है', '5 वर्ष से छोटे बालक का मुंडन तब वर्जित'],
        'wpreg' => ['check', 'गृहस्वामिनी (पत्नी) गर्भवती है', 'लोक-मत: तब नींव/गृहारम्भ टालें'],
        'ritu' => ['date', 'मासिक धर्म (ऋतु) आरम्भ की तिथि', 'ऋतुकाल की 4–16वीं रात्रि (11वीं, 13वीं छोड़कर) — वैकल्पिक'],
        'lmp' => ['date', 'गर्भ-आरम्भ / अंतिम मासिक धर्म (LMP) की तिथि', 'इससे गर्भ का मास गिना जाता है'],
        'vivah' => ['date', 'विवाह की तिथि', 'द्विरागमन विवाह से विषम (1/3/5वें) वर्ष में'],
        'death' => ['datetime', 'मृत्यु की तिथि व समय (वैकल्पिक)', 'पंचक/त्रिपुष्कर में मृत्यु हो तो शांति; अस्थि-संचय के दिन'],
    ];

    /** श्रेणी के अनुसार एक व्यक्ति का नाम (कार्य-विशेष TASK में) — [चिह्न, लेबल] */
    private const SELF_LABEL = [
        2 => ['🏠', 'गृहस्वामी / मालिक'], 3 => ['🙏', 'यजमान (जिनके लिए पूजा)'], 4 => ['🕉️', 'साधक (कर्म करने वाले)'],
        5 => ['💼', 'व्यक्ति / स्वामी'], 6 => ['💍', 'धारक / क्रेता'], 7 => ['💎', 'रत्न-धारक'],
        8 => ['🧳', 'यात्री'], 9 => ['🩺', 'रोगी'], 10 => ['🌾', 'किसान / स्वामी'], 11 => ['👤', 'जातक'], 12 => ['👤', 'जातक'],
    ];

    /**
     * कार्य => ['who' => [[भूमिका, आवश्यकता, लेबल?, चिह्न?, छोटा नाम?], …], 'x' => [अतिरिक्त]]।
     * आवश्यकता: req (बिना इसके कार्य के मुख्य नियम नहीं जँचते), rec (अनुशंसित), opt।
     * जो कार्य यहाँ नहीं — उसमें श्रेणी का एक व्यक्ति (rec)।
     */
    private const TASK = [
        '1.01' => ['who' => [['wife', 'rec', 'पत्नी (गर्भधारण करने वाली)'], ['husband', 'opt']], 'x' => ['ritu']],
        '1.02' => ['who' => [['mother', 'rec']], 'x' => ['lmp']],
        '1.03' => ['who' => [['child', 'req']]],
        '1.04' => ['who' => [['child', 'req']]],
        '1.05' => ['who' => [['child', 'req']]],
        '1.06' => ['who' => [['child', 'req']], 'x' => ['first', 'mpreg']],
        '1.07' => ['who' => [['child', 'req']]],
        '1.08' => ['who' => [['child', 'req']]],
        '1.09' => ['who' => [['child', 'req', 'बटुक (जिसका जनेऊ)']]],
        '1.10' => ['who' => [['var', 'rec'], ['kanya', 'rec']]],
        '1.11' => ['who' => [['var', 'rec'], ['kanya', 'rec']], 'x' => ['first']],
        '1.12' => ['who' => [['kanya', 'rec', 'वधू (दुल्हन)', '👰', 'वधू'], ['var', 'opt']], 'x' => ['vivah']],
        '1.13' => ['who' => [], 'x' => ['death']],
        '2.02' => ['x' => ['wpreg']],
        '2.03' => ['x' => ['wpreg']],
        '3.01' => ['who' => [['self', 'rec', 'शिष्य (दीक्षा लेने वाले)', '🙏']]],
        '5.01' => ['who' => [['self', 'rec', 'व्यापारी / स्वामी', '💼', 'स्वामी'], ['partner', 'opt', 'साझेदार (वैकल्पिक)']]],
        '5.02' => ['who' => [['self', 'rec', 'नौकरी करने वाले', '💼']]],
        '5.04' => ['who' => [['self', 'rec', 'ऋण लेने वाले', '💼']]],
        '5.05' => ['who' => [['self', 'rec', 'ऋण चुकाने वाले', '💼']]],
        '5.08' => ['who' => [['self', 'rec', 'पहला पक्ष (आप)', '✍️', 'पहला पक्ष'], ['partner', 'opt', 'दूसरा पक्ष (वैकल्पिक)']]],
        '8.04' => ['who' => [['self', 'rec', 'वाहन-स्वामी', '🚗']]],
        '8.05' => ['who' => [['self', 'rec', 'वाहन-स्वामी', '🚗']]],
        '9.01' => ['who' => [['self', 'opt', 'औषधि बनाने वाले (वैद्य)', '🌿']]],
        '9.04' => ['who' => [['mother', 'rec', 'माता (प्रसूता)']]],
        '11.01' => ['who' => [['self', 'rec', 'शपथ लेने वाले', '🎖️']]],
        '11.02' => ['who' => [['self', 'rec', 'वादी (केस करने वाले)', '⚖️']]],
        '11.03' => ['who' => [['self', 'rec', 'पहला पक्ष (आप)', '🤝', 'पहला पक्ष'], ['partner', 'opt', 'दूसरा पक्ष (वैकल्पिक)']]],
        '11.04' => ['who' => [['self', 'rec', 'शस्त्र-धारक', '🛡️']]],
    ];

    /**
     * नाम-अक्षर (अवकहड़ा चक्र) — नक्षत्र (1–27) => चार चरणों के अक्षर। जन्म-समय न पता
     * हो तो नाम के पहले अक्षर से नक्षत्र-चरण, उससे नाम-राशि।
     */
    public const AKSHAR = [
        1 => ['चू', 'चे', 'चो', 'ला'], 2 => ['ली', 'लू', 'ले', 'लो'], 3 => ['अ', 'इ', 'उ', 'ए'],
        4 => ['ओ', 'वा', 'वी', 'वू'], 5 => ['वे', 'वो', 'का', 'की'], 6 => ['कु', 'घ', 'ङ', 'छ'],
        7 => ['के', 'को', 'हा', 'ही'], 8 => ['हु', 'हे', 'हो', 'डा'], 9 => ['डी', 'डू', 'डे', 'डो'],
        10 => ['मा', 'मी', 'मू', 'मे'], 11 => ['मो', 'टा', 'टी', 'टू'], 12 => ['टे', 'टो', 'पा', 'पी'],
        13 => ['पू', 'ष', 'ण', 'ठ'], 14 => ['पे', 'पो', 'रा', 'री'], 15 => ['रू', 'रे', 'रो', 'ता'],
        16 => ['ती', 'तू', 'ते', 'तो'], 17 => ['ना', 'नी', 'नू', 'ने'], 18 => ['नो', 'या', 'यी', 'यू'],
        19 => ['ये', 'यो', 'भा', 'भी'], 20 => ['भू', 'धा', 'फा', 'ढा'], 21 => ['भे', 'भो', 'जा', 'जी'],
        22 => ['खी', 'खू', 'खे', 'खो'], 23 => ['गा', 'गी', 'गू', 'गे'], 24 => ['गो', 'सा', 'सी', 'सू'],
        25 => ['से', 'सो', 'दा', 'दी'], 26 => ['दू', 'थ', 'झ', 'ञ'], 27 => ['दे', 'दो', 'चा', 'ची'],
    ];

    /**
     * घात चक्र (यात्रा, मुकदमा, शल्य में विचार; विवाह आदि मंगल कार्यों में नहीं) —
     * जन्म/नाम राशि (0 मेष…11 मीन) => घात मास (0 चैत्र…), तिथि (1–15 दोनों पक्ष),
     * वार (1 रवि…7 शनि), नक्षत्र (1–27), चंद्र-राशि।
     */
    public const GHAT = [
        0 => ['maas' => 7, 'tithi' => [1, 6, 11], 'vaar' => 1, 'nak' => 10, 'chandra' => 0],
        1 => ['maas' => 8, 'tithi' => [5, 10, 15], 'vaar' => 7, 'nak' => 13, 'chandra' => 5],
        2 => ['maas' => 3, 'tithi' => [2, 7, 12], 'vaar' => 2, 'nak' => 15, 'chandra' => 10],
        3 => ['maas' => 9, 'tithi' => [2, 7, 12], 'vaar' => 4, 'nak' => 17, 'chandra' => 4],
        4 => ['maas' => 2, 'tithi' => [3, 8, 13], 'vaar' => 7, 'nak' => 19, 'chandra' => 9],
        5 => ['maas' => 5, 'tithi' => [5, 10, 15], 'vaar' => 7, 'nak' => 22, 'chandra' => 2],
        6 => ['maas' => 10, 'tithi' => [4, 9, 14], 'vaar' => 5, 'nak' => 24, 'chandra' => 8],
        7 => ['maas' => 6, 'tithi' => [1, 6, 11], 'vaar' => 6, 'nak' => 27, 'chandra' => 1],
        8 => ['maas' => 4, 'tithi' => [3, 8, 13], 'vaar' => 6, 'nak' => 2, 'chandra' => 11],
        9 => ['maas' => 1, 'tithi' => [4, 9, 14], 'vaar' => 3, 'nak' => 4, 'chandra' => 4],
        10 => ['maas' => 0, 'tithi' => [3, 8, 13], 'vaar' => 5, 'nak' => 6, 'chandra' => 8],
        11 => ['maas' => 11, 'tithi' => [5, 10, 15], 'vaar' => 6, 'nak' => 9, 'chandra' => 10],
    ];

    /** शल्य का अंग => राशि (कालपुरुष) — चंद्र उस राशि में न हो (9.03, पाश्चात्य मूल का नियम) */
    public const ANG_SIGN = [
        'sir' => 0, 'gala' => 1, 'bhuja' => 2, 'chhati' => 3, 'hriday' => 4, 'pet' => 5,
        'gurde' => 6, 'guhya' => 7, 'jangh' => 8, 'ghutne' => 9, 'pindli' => 10, 'pair' => 11,
    ];

    /** ग्रह => उसकी राशियाँ (0–11) — रत्न किस भाव का स्वामी */
    private const OWN = ['Sun' => [4], 'Moon' => [3], 'Mars' => [0, 7], 'Mercury' => [2, 5], 'Jupiter' => [8, 11],
        'Venus' => [1, 6], 'Saturn' => [9, 10]];

    /** कालसर्प के 12 नाम — राहु लग्न से जिस भाव में */
    private const KAALSARP = [1 => 'अनन्त', 2 => 'कुलिक', 3 => 'वासुकि', 4 => 'शंखपाल', 5 => 'पद्म', 6 => 'महापद्म',
        7 => 'तक्षक', 8 => 'कर्कोटक', 9 => 'शंखचूड़', 10 => 'घातक', 11 => 'विषधर', 12 => 'शेषनाग'];

    // ───────────────────────── प्रोफ़ाइल ─────────────────────────

    /**
     * कार्य के व्यक्ति-खाने व अतिरिक्त प्रश्न (पन्ने व सर्वर दोनों यही पढ़ते हैं)।
     *
     * @return array{who: list<array{role: string, need: string, label: string, icon: string, short: string, gender: string}>, x: list<string>}
     */
    public static function profile(string $id): array
    {
        $d = KaryaNiyamData::TASKS[$id] ?? null;
        if ($d === null) { return ['who' => [], 'x' => []]; }
        $cfg = self::TASK[$id] ?? [];
        $rows = $cfg['who'] ?? [['self', 'rec']];
        $who = [];
        foreach ($rows as $r) {
            [$role, $need] = $r;
            $R = self::ROLES[$role];
            $label = $r[2] ?? ($role === 'self' ? (self::SELF_LABEL[$d['c']][1] ?? $R[0]) : $R[0]);
            $icon = $r[3] ?? ($role === 'self' ? (self::SELF_LABEL[$d['c']][0] ?? $R[2]) : $R[2]);
            $who[] = ['role' => $role, 'need' => $need, 'label' => $label, 'icon' => $icon, 'short' => $r[4] ?? $R[1], 'gender' => $R[3]];
        }
        return ['who' => $who, 'x' => $cfg['x'] ?? []];
    }

    // ───────────────────────── व्यक्ति बनाना ─────────────────────────

    /**
     * जन्म-विवरण से व्यक्ति — चंद्र राशि/नक्षत्र/चरण, जन्म लग्न, अमान्त जन्म-मास,
     * ग्रहों की राशियाँ (रत्न/कालसर्प/गुण-मिलान हेतु) व जन्म का JD।
     *
     * @param array{date: string, time?: string, lat?: float, lon?: float, tz?: float, gender?: string, name?: string} $b
     */
    public static function fromBirth(CalculationEngine $eng, array $b): ?array
    {
        $date = trim((string) ($b['date'] ?? ''));
        if ($date === '' || !preg_match('/^\d{1,4}[-\/.]\d{1,2}[-\/.]\d{1,4}$/', $date)) { return null; }
        [$Y, $Mo, $D] = CalcController::parseDate($date);
        if (!checkdate($Mo, $D, $Y) || $Y < 1800 || $Y > 2200) { return null; }
        $time = trim((string) ($b['time'] ?? ''));
        $known = $time !== '';
        [$hh, $mm] = CalcController::parseTime($known ? $time : '12:00');
        $tz = (float) ($b['tz'] ?? 5.5);
        $lat = (float) ($b['lat'] ?? 28.61);
        $lon = (float) ($b['lon'] ?? 77.21);
        $jd = JulianDay::fromGregorian($Y, $Mo, $D, $hh, $mm, 0.0, $tz);
        $chart = $eng->computeChart($jd, $lat, $lon);
        $moon = (float) $chart['planets']['Moon']['sidereal_lon'];
        $sun = (float) $chart['planets']['Sun']['sidereal_lon'];
        // जन्म का अमान्त मास — पिछली अमावस्या पर सूर्य की राशि से
        $nm = $jd - KaryaKaal::norm($moon - $sun) / 12.19;
        for ($i = 0; $i < 6; $i++) {
            $q = $eng->siderealPositions($nm);
            $nm -= KaryaKaal::wrap180($q['Moon']['lon'] - $q['Sun']['lon']) / 12.19;
        }
        $sunAtNm = (int) floor($eng->siderealPositions($nm)['Sun']['lon'] / 30.0) % 12;
        $lons = [];
        foreach ($chart['planets'] as $pl => $p) { $lons[$pl] = (float) $p['sidereal_lon']; }
        $g = self::gender((string) ($b['gender'] ?? ''));
        return [
            'src' => 'birth', 'name' => trim((string) ($b['name'] ?? '')),
            'moonSign' => (int) floor($moon / 30.0) % 12, 'moonLon' => $moon,
            'nak' => min(27, (int) floor($moon / KaryaKaal::NAK) + 1),
            'pada' => min(4, (int) floor(fmod($moon, KaryaKaal::NAK) / (KaryaKaal::NAK / 4.0)) + 1),
            // समय न पता हो तो जन्म-लग्न अविश्वसनीय — उससे कोई नियम न लगे
            'ascSign' => $known ? (int) $chart['ascendant']['sign_index'] : null,
            'am' => ($sunAtNm + 1) % 12, 'gender' => $g, 'birthJd' => $jd, 'timeKnown' => $known,
            'birthMd' => sprintf('%02d-%02d', $D, $Mo), 'lons' => $lons,
            '_chart' => $known ? $chart : null,
        ];
    }

    /** नाम-अक्षर / नक्षत्र-चरण से व्यक्ति (नाम-राशि) — लग्न, जन्म-मास, आयु अज्ञात। */
    public static function fromAkshar(int $nak, int $pada, string $gender = '', string $name = ''): ?array
    {
        if ($nak < 1 || $nak > 27 || $pada < 1 || $pada > 4) { return null; }
        $lon = ($nak - 1) * KaryaKaal::NAK + ($pada - 0.5) * KaryaKaal::NAK / 4.0;
        return [
            'src' => 'akshar', 'name' => trim($name), 'moonSign' => (int) floor($lon / 30.0) % 12, 'moonLon' => $lon,
            'nak' => $nak, 'pada' => $pada, 'ascSign' => null, 'am' => null, 'gender' => self::gender($gender),
            'birthJd' => null, 'timeKnown' => false, 'birthMd' => null, 'lons' => null, '_chart' => null,
        ];
    }

    /**
     * अनुरोध से कार्य के व्यक्ति व अतिरिक्त उत्तर। हर भूमिका के मान उसके कूट से:
     * <role>_m (birth|akshar|off), _d तिथि, _t समय, _lat, _lon, _tz, _g लिंग, _n नाम,
     * _k "नक्षत्र.चरण" (अक्षर), _f ज्येष्ठ संतान। पुराने bdate/btime… पहले खाने के लिए।
     *
     * @param array<string, mixed> $q
     * @return array{persons: list<array>, extra: array<string, mixed>, missing: list<string>}
     */
    public static function fromRequest(CalculationEngine $eng, string $id, array $q, float $placeTz): array
    {
        $prof = self::profile($id);
        $s = static fn (string $k): string => is_string($q[$k] ?? null) ? trim((string) $q[$k]) : '';
        $off = $s('personal') === '0';
        $persons = [];
        $missing = [];
        foreach ($prof['who'] as $i => $slot) {
            $r = $slot['role'];
            $mode = $s($r . '_m');
            $P = null;
            if (!$off && $mode === '' && $i === 0 && $s('bdate') !== '') {   // पुराना रूप — पहला खाना
                $P = self::fromBirth($eng, ['date' => $s('bdate'), 'time' => $s('btime'),
                    'lat' => CalcController::parseAngle($s('blat') ?: '0'), 'lon' => CalcController::parseAngle($s('blon') ?: '0'),
                    'tz' => CalcController::parseTz($s('btz') ?: '5.5'), 'gender' => $s('gender')]);
            } elseif (!$off && $mode === 'birth') {
                $P = self::fromBirth($eng, ['date' => $s($r . '_d'), 'time' => $s($r . '_t'),
                    'lat' => CalcController::parseAngle($s($r . '_lat') ?: '28.6139'), 'lon' => CalcController::parseAngle($s($r . '_lon') ?: '77.2090'),
                    'tz' => CalcController::parseTz($s($r . '_tz') ?: '5.5'), 'gender' => $s($r . '_g'), 'name' => $s($r . '_n')]);
            } elseif (!$off && $mode === 'akshar') {
                [$nk, $pd] = array_map('intval', explode('.', $s($r . '_k') . '.0'));
                $P = self::fromAkshar($nk, $pd, $s($r . '_g'), $s($r . '_n'));
            }
            if ($P === null) {
                if ($slot['need'] !== 'opt' && $mode !== 'off' && !$off) { $missing[] = $slot['role']; }
                continue;
            }
            if ($slot['gender'] !== '') { $P['gender'] = $slot['gender']; }
            $persons[] = $P + ['role' => $r, 'label' => $slot['label'], 'short' => $slot['short'], 'icon' => $slot['icon'],
                'first' => $s($r . '_f') === '1'];
        }
        $extra = [];
        foreach ($prof['x'] as $x) {
            $type = self::EXTRAS[$x][0];
            if ($type === 'check') { $extra[$x] = $s('x_' . $x) === '1'; continue; }
            $v = $s('x_' . $x);
            if ($v === '' || !preg_match('/^\d{1,4}[-\/.]\d{1,2}[-\/.]\d{1,4}$/', $v)) { continue; }
            [$Y, $Mo, $D] = CalcController::parseDate($v);
            if (!checkdate($Mo, $D, $Y)) { continue; }
            $hm = $type === 'datetime' && $s('x_' . $x . '_t') !== '' ? CalcController::parseTime($s('x_' . $x . '_t')) : [12, 0];
            $extra[$x] = ['y' => $Y, 'm' => $Mo, 'd' => $D, 'jd' => JulianDay::fromGregorian($Y, $Mo, $D, $hm[0], $hm[1], 0.0, $placeTz),
                'timed' => $type === 'datetime' && $s('x_' . $x . '_t') !== ''];
        }
        return ['persons' => $persons, 'extra' => $extra, 'missing' => $missing];
    }

    private static function gender(string $g): string
    {
        $g = mb_strtolower(trim($g));
        if ($g === '') { return ''; }
        if (str_starts_with($g, 'f') || in_array($g, ['स्त्री', 'महिला', 'कन्या', 'बालिका'], true)) { return 'f'; }
        if (str_starts_with($g, 'm') || in_array($g, ['पुरुष', 'बालक'], true)) { return 'm'; }
        return '';
    }

    // ───────────────────────── सार (पन्ने हेतु) ─────────────────────────

    /**
     * व्यक्ति का दिखाने-योग्य सार — राशि, नक्षत्र-चरण, लग्न, नाम-अक्षर, आयु, और कार्य
     * के अनुसार टिप्पणियाँ (साढ़ेसाती, कालसर्प, रत्न की अनुकूलता, नामकरण का अक्षर)।
     */
    public static function summary(CalculationEngine $eng, array $P, string $id, float $jdNow): array
    {
        $sum = [
            'role' => $P['role'], 'label' => $P['label'], 'short' => $P['short'], 'icon' => $P['icon'],
            'name' => $P['name'], 'src' => $P['src'], 'gender' => $P['gender'], 'first' => $P['first'],
            'rashi' => KaryaNirnay::signHi($P['moonSign']), 'nak' => P::NAK[$P['nak']]['name'], 'pada' => $P['pada'],
            'akshar' => self::AKSHAR[$P['nak']][$P['pada'] - 1] ?? '',
            'lagna' => $P['ascSign'] !== null ? KaryaNirnay::signHi($P['ascSign']) : null,
            'age' => $P['birthJd'] !== null ? self::ageText($jdNow - $P['birthJd']) : null,
            'notes' => [],
        ];
        $notes = &$sum['notes'];
        $c = (int) (KaryaNiyamData::TASKS[$id]['c'] ?? 0);
        $v = KaryaNiyamVidhi::rule($id);
        if ($id === '1.03') {
            $notes[] = 'नाम का पहला अक्षर (जन्म नक्षत्र ' . $sum['nak'] . '-' . $P['pada'] . ' से): "' . $sum['akshar'] . '" — इसी चरण के अन्य अक्षर: '
                . implode(', ', self::AKSHAR[$P['nak']]);
        }
        if ($c === 1 && $P['role'] === 'child' && $P['birthJd'] === null) {
            $notes[] = 'आयु के नियम (नियत दिन/मास/वर्ष) जन्म-तिथि से ही जँचते हैं — जन्म-विवरण भरें।';
        }
        if (in_array($id, ['3.08', '3.09', '3.10'], true) || $c === 7) {
            $now = $eng->siderealPositions($jdNow);
            if ($id === '3.08') {
                $h = (((int) floor($now['Saturn']['lon'] / 30.0) - $P['moonSign'] + 12) % 12) + 1;
                $notes[] = match ($h) {
                    12 => 'शनि जन्म-राशि से 12वाँ — साढ़ेसाती का पहला चरण: शनि-शांति उपयुक्त।',
                    1 => 'शनि जन्म-राशि पर — साढ़ेसाती का दूसरा (मध्य) चरण: शनि-शांति उपयुक्त।',
                    2 => 'शनि जन्म-राशि से 2रा — साढ़ेसाती का अंतिम चरण: शनि-शांति उपयुक्त।',
                    4 => 'शनि जन्म-राशि से 4था — ढैय्या (कंटक शनि): शनि-शांति उपयुक्त।',
                    8 => 'शनि जन्म-राशि से 8वाँ — अष्टम ढैय्या: शनि-शांति उपयुक्त।',
                    default => 'अभी साढ़ेसाती/ढैय्या नहीं (शनि जन्म-राशि से ' . KaryaNirnay::ord($h) . ') — शांति सामान्य उपाय हेतु।',
                };
            }
            if ($id === '3.10' && $P['lons'] !== null) {
                $notes[] = self::kaalSarp($P);
            }
            if ($c === 7 && !empty($v['gem'])) {
                $notes[] = self::gemFit((string) $v['gem'], $P);
            }
        }
        return $sum;
    }

    /** "4 वर्ष 2 मास" / "3 मास 10 दिन" / "12 दिन" */
    public static function ageText(float $days): string
    {
        if ($days < 0) { return 'अभी जन्म नहीं'; }
        $y = (int) floor($days / 365.2425);
        $mo = (int) floor(($days - $y * 365.2425) / 30.4375);
        $d = (int) floor($days - $y * 365.2425 - $mo * 30.4375);
        if ($y >= 2) { return $y . ' वर्ष ' . $mo . ' मास'; }
        if ($y === 1) { return '1 वर्ष ' . $mo . ' मास'; }
        if ($mo >= 1) { return $mo . ' मास ' . $d . ' दिन'; }
        return KaryaNirnay::ord((int) floor($days) + 1) . ' दिन';
    }

    /** रत्न का ग्रह जन्म-लग्न से किन भावों का स्वामी — पाराशरी सामान्य नियम। */
    private static function gemFit(string $pl, array $P): string
    {
        $hi = KaryaKaal::HI[$pl] ?? $pl;
        if ($P['ascSign'] === null) { return 'रत्न की अनुकूलता जन्म-लग्न से देखी जाती है — जन्म-समय सहित विवरण भरें।'; }
        if (!isset(self::OWN[$pl])) {
            $h = $P['lons'] !== null ? (((int) floor($P['lons'][$pl] / 30.0) - $P['ascSign'] + 12) % 12) + 1 : 0;
            return $hi . ' जन्म-कुंडली में ' . KaryaNirnay::ordO($h) . ' भाव में — राहु/केतु का रत्न प्रायः 3/6/11 भाव में या शुभ युति हो तब; कुंडली देखकर ही।';
        }
        $hs = array_map(static fn ($s) => (($s - $P['ascSign'] + 12) % 12) + 1, self::OWN[$pl]);
        sort($hs);
        $txt = $hi . ' आपकी ' . KaryaNirnay::signHi($P['ascSign']) . ' लग्न के लिए ' . implode(' व ', array_map([KaryaNirnay::class, 'ordO'], $hs)) . ' भाव का स्वामी — ';
        $tri = array_intersect($hs, [1, 5, 9]);
        $ken = array_intersect($hs, [4, 7, 10]);
        $dus = array_intersect($hs, [6, 8, 12]);
        if ($tri && $ken && !in_array(1, $hs, true)) { return $txt . 'योगकारक: रत्न अनुकूल।'; }
        if ($tri) { return $txt . 'लग्न/त्रिकोण का स्वामी: रत्न अनुकूल।'; }
        if ($dus) { return $txt . '6/8/12 का स्वामी (अकारक): रत्न सावधानी से, कुंडली दिखाकर ही।'; }
        return $txt . 'सम ग्रह: आवश्यकता होने पर ही धारण करें।';
    }

    /** जन्म-कुंडली में कालसर्प योग — सातों ग्रह राहु-केतु अक्ष के एक ही ओर। */
    private static function kaalSarp(array $P): string
    {
        $r = $P['lons']['Rahu'];
        $side = static fn (float $x): bool => KaryaKaal::norm($x - $r) < 180.0;
        $a = 0; $b = 0;
        foreach (['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'] as $pl) {
            if ($side($P['lons'][$pl])) { $a++; } else { $b++; }
        }
        if ($a !== 7 && $b !== 7) {
            return 'जन्म-कुंडली में कालसर्प योग नहीं (ग्रह राहु-केतु के दोनों ओर)' . (min($a, $b) === 1 ? ' — केवल एक ग्रह बाहर (आंशिक, मत)' : '') . '।';
        }
        $name = '';
        if ($P['ascSign'] !== null) { $name = (self::KAALSARP[(((int) floor($r / 30.0) - $P['ascSign'] + 12) % 12) + 1] ?? '') . ' '; }
        return 'जन्म-कुंडली में ' . $name . 'कालसर्प योग है (सातों ग्रह राहु-केतु के एक ओर) — शांति उपयुक्त।';
    }

    /** अंतिम संस्कार — मृत्यु के क्षण पंचक / त्रिपुष्कर (शांति-कर्म हेतु)। */
    public static function deathNotes(CalculationEngine $eng, array $x): array
    {
        $p = $eng->siderealPositions((float) $x['jd']);
        $moon = (float) $p['Moon']['lon'];
        $nak = min(27, (int) floor($moon / KaryaKaal::NAK) + 1);
        $tithi = (int) floor(KaryaKaal::norm($moon - (float) $p['Sun']['lon']) / 12.0) + 1;
        $tp = ($tithi - 1) % 15 + 1;
        $vaar = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $x['y'], $x['m'], $x['d'])))->format('w') + 1;
        $nm = P::NAK[$nak]['name'];
        $out = [];
        $out[] = $moon >= 300.0
            ? 'मृत्यु पंचक में (चंद्र ' . $nm . ' में) — दाह के साथ 5 कुश-पुतलों का दाह व पंचक-शांति आवश्यक।'
            : 'मृत्यु पंचक में नहीं (चंद्र ' . $nm . ' में) — पंचक-शांति आवश्यक नहीं।';
        if (in_array($vaar, [1, 3, 7], true) && in_array($tp, [2, 7, 12], true) && in_array($nak, [3, 7, 12, 16, 21, 25], true)) {
            $out[] = 'मृत्यु त्रिपुष्कर योग में (वार-तिथि-नक्षत्र) — त्रिपुष्कर-शांति आवश्यक (मत: दोष तीन गुना)।';
        }
        if (empty($x['timed'])) { $out[] = 'मृत्यु का समय न देने से गणना दोपहर 12 बजे की मानी — समय भरें तो सटीक।'; }
        return $out;
    }

    // ───────────────────────── गुण-मिलान (वर-कन्या) ─────────────────────────

    /** वर-कन्या दोनों हों तो अष्टकूट गुण-मिलान का संक्षेप (मिलान पन्ने वाला ही इंजन)। */
    public static function milan(array $persons): ?array
    {
        $var = $kanya = null;
        foreach ($persons as $P) {
            if ($P['role'] === 'var') { $var = $P; }
            if ($P['role'] === 'kanya') { $kanya = $P; }
        }
        if ($var === null || $kanya === null) { return null; }
        $mk = static function (array $P): array {
            if ($P['_chart'] !== null) { return MilanController::milanPerson($P['name'], $P['_chart']); }
            return ['name' => $P['name'], 'rashi' => $P['moonSign'] + 1, 'nak' => $P['nak'], 'pada' => $P['pada'],
                'moon_deg' => fmod($P['moonLon'], 30.0), 'mars' => ['from_lagna' => 0, 'from_moon' => 0, 'from_venus' => 0, 'sign_index' => 0, 'own_or_exalt' => false],
                'jupiter_aspects_mars' => false, 'lagna_has_jup_or_venus' => false];
        };
        try { $rules = MilanRepository::load('hi') ?? []; } catch (\Throwable) { $rules = []; }
        $m = GunaMilan::compute($mk($var), $mk($kanya), $rules, $rules['config'] ?? []);
        $bad = [];
        foreach ($m['doshas'] ?? [] as $d) { if (empty($d['resolved'])) { $bad[] = ($d['dosha'] ?? 'दोष') . ' दोष'; } }
        $both = $var['_chart'] !== null && $kanya['_chart'] !== null;
        return [
            'total' => $m['total'], 'max' => $m['max'], 'text' => $m['band']['text'] ?? '', 'tier' => $m['band']['tier'] ?? '',
            'doshas' => $bad, 'overrides' => $m['overrides'] ?? [],
            'mangal' => $both ? ($m['mangal']['result']['text'] ?? '') : 'मंगल-दोष हेतु दोनों का जन्म-समय चाहिए।',
        ];
    }
}
