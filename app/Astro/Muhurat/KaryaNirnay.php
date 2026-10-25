<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

use AutoBusiness\Astro\Muhurat\PanchangNiyamData as P;
use AutoBusiness\Astro\Time\JulianDay;

/**
 * ⚖️ एक क्षण पर एक कार्य का मुहूर्त-निर्णय — कार्यपुस्तिका के नियमों से।
 *
 * क्रम वही जो कार्यपुस्तिका की "मुहूर्त खोजने की क्रमबद्ध विधि" में है: वर्जित
 * काल (मास/अस्त) → पक्ष → तिथि → वार → नक्षत्र → योग-करण → विशेष योग → लग्न →
 * दिन के भीतर का समय → दिशा/वास → व्यक्तिगत बल → अबूझ/अपवाद।
 *
 * हर जाँच एक पंक्ति देती है: ✔ ok · ★ good (अंक बढ़ते) · ⚠ warn (अंक घटते) ·
 * ✘ bad (कठोर — यह क्षण इस कार्य हेतु वर्जित) · ℹ info। अंक 100 से आरम्भ;
 * किसी "bad" के रहते क्षण अशुभ। "प्राथमिकता" (तिथि 1 < नक्षत्र 4 < वार 8 < करण
 * 16 < योग 100 < चंद्र बल < लग्न) के क्रम में ही दण्ड बढ़ते हैं; महादोष
 * (व्यतीपात-वैधृति, पृथ्वी की भद्रा, ग्रहण, अस्त/मास-दोष) किसी बल से नहीं ढकते,
 * जबकि सर्वार्थ/अमृत सिद्धि व रवि योग छोटे सामान्य दोषों को शमन करते हैं।
 */
final class KaryaNirnay
{
    public const SHUBH = 80;
    public const MADHYAM = 60;
    /** पंचांग-अंग शुभ सूची से बाहर — क्षण अधिकतम "मध्यम" */
    private const LIMB_MISS = ['तिथि सामान्य', 'तिथि मध्यम', 'वार सामान्य', 'वार मध्यम', 'नक्षत्र सामान्य', 'नक्षत्र मध्यम'];
    /** जाँच-शीर्षक जो अकेले कोई दोष/बल नहीं बताते */
    private const BARE = ['मास', 'अयन', 'पक्ष', 'तिथि', 'वार', 'नक्षत्र', 'योग', 'करण', 'लग्न', 'समय', 'चौघड़िया', 'होरा'];

    private const NANDA = [1, 6, 11]; private const BHADRA_T = [2, 7, 12]; private const JAYA = [3, 8, 13];
    private const RIKTA = [4, 9, 14]; private const PURNA = [5, 10, 15];
    private const MALEFIC = ['Sun', 'Mars', 'Saturn', 'Rahu', 'Ketu'];
    private const LORD = ['Mars', 'Venus', 'Mercury', 'Moon', 'Sun', 'Mercury', 'Venus', 'Mars', 'Jupiter', 'Saturn', 'Saturn', 'Jupiter'];
    private const NEECH = ['Sun' => 6, 'Moon' => 7, 'Mars' => 3, 'Mercury' => 11, 'Jupiter' => 9, 'Venus' => 5, 'Saturn' => 0];
    private const SIGN_HI = ['मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या', 'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुम्भ', 'मीन'];
    public const MAAS_HI = ['चैत्र', 'वैशाख', 'ज्येष्ठ', 'आषाढ़', 'श्रावण', 'भाद्रपद', 'आश्विन', 'कार्तिक', 'मार्गशीर्ष', 'पौष', 'माघ', 'फाल्गुन'];
    private const RITU_HI = [1 => 'वसंत', 2 => 'ग्रीष्म', 3 => 'वर्षा', 4 => 'शरद', 5 => 'हेमन्त', 6 => 'शिशिर'];
    private const NAK_SHOOL = [18 => 'पूर्व', 25 => 'दक्षिण', 4 => 'पश्चिम', 12 => 'उत्तर'];

    public static function signHi(int $s): string { return self::SIGN_HI[(($s % 12) + 12) % 12]; }

    /** हिन्दी क्रमवाचक — 1ला, 2रा, 3रा, 4था, 6ठा, 5वाँ …; तिर्यक (1ले/…वें) व स्त्रीलिंग (1ली/…वीं) */
    public static function ord(int $n): string { return $n . ([1 => 'ला', 2 => 'रा', 3 => 'रा', 4 => 'था', 6 => 'ठा'][$n] ?? 'वाँ'); }
    public static function ordO(int $n): string { return $n . ([1 => 'ले', 2 => 'रे', 3 => 'रे', 4 => 'थे', 6 => 'ठे'][$n] ?? 'वें'); }
    public static function ordF(int $n): string { return $n . ([1 => 'ली', 2 => 'री', 3 => 'री', 4 => 'थी', 6 => 'ठी'][$n] ?? 'वीं'); }

    /** सामान्य दोष (सा.दो.) का यह अंग इस कार्य पर लागू है? */
    private static function sado(array $t, string $code): bool
    {
        $s = $t['sado'] ?? 'full';
        if ($s === 'full') { return $code !== 'bhadra_soft'; }
        if ($s === 'relaxed') { return in_array($code, ['bhadra', 'grahan'], true); }
        if ($s === 'none') { return false; }
        return in_array($code, (array) $s, true);
    }

    /**
     * @param array $t   तैयार कार्य ({@see KaryaTithiKhoj::prepare})
     * @param array $f   क्षण के तथ्य ({@see KaryaKaal::facts})
     * @param array $day दिन ({@see KaryaKaal::day})
     * @param array $ctx fest (आज के पर्व-कूट), eclipses, natal, personal, opts
     * @return array{hard: bool, score: int, grade: string, tone: string, bad: list<string>,
     *               warn: list<string>, good: list<string>, abujh: ?string, checks: list<array>}
     */
    public static function judge(array $t, array $f, array $day, array $ctx, bool $verbose = false): array
    {
        $checks = []; $bad = []; $warn = []; $good = [];
        $score = 100; $hard = false; $hardOther = false;
        $add = static function (string $grp, string $label, string $val, string $st, int $pts = 0)
            use (&$checks, &$bad, &$warn, &$good, &$score, &$hard, &$hardOther, $verbose): void {
            // सूचियों (बाधा/सावधानी/बल) में केवल शीर्षक ("पक्ष", "मास") कुछ नहीं बताता — तब मान ही लिखें
            $tag = in_array($label, self::BARE, true) ? $val : $label;
            if ($st === 'bad') { $hard = true; $bad[] = $tag; if ($grp !== 'लग्न') { $hardOther = true; } }
            elseif ($st === 'warn') { $warn[] = $tag; }
            elseif ($st === 'good') { $good[] = $tag; }
            $score += $pts;
            if ($verbose) { $checks[] = ['g' => $grp, 'l' => $label, 'v' => $val, 's' => $st, 'p' => $pts]; }
        };

        $vaar = $f['vaar']; $tithi = $f['tithi']; $tp = $f['tp']; $nak = $f['nak']; $pk = $f['paksha'];
        $tcode = $tithi === 30 ? 30 : ($tithi <= 15 ? $tithi : $tithi - 15);
        $tName = P::TITHI[$tithi]['name'] . ' (' . ($pk === 's' ? 'शुक्ल' : 'कृष्ण') . ')';
        $nName = P::NAK[$nak]['name'];
        $vName = P::VAAR[$vaar]['name'];
        $fest = $ctx['fest'] ?? [];
        $varjit = $t['varjit'] ?? [];
        $soft = $t['soft'] ?? [];
        $catSoft = $t['catSoft'] ?? [];
        $persons = self::persons($ctx);
        $multi = count($persons) > 1;
        $X = $ctx['extra'] ?? [];
        $cap = [];          // व्यक्ति/स्थिति के कारण — इनके रहते क्षण अधिकतम "मध्यम"
        $fixedDay = false;  // शिशु का नियत दिन (नामकरण 11/12वाँ) — तिथि-वार विचार शिथिल
        if (($t['age'] ?? '') === 'namkaran') {
            foreach ($persons as $P) {
                if ($P['role'] === 'child' && $P['birthJd'] !== null && in_array((int) floor($f['jd'] - $P['birthJd']) + 1, [11, 12], true)) { $fixedDay = true; }
            }
        }

        // ── अबूझ / पर्व ── (पंचांग-शुद्धि शिथिल, पर मास/अस्त दोष बने रहते हैं)
        $abujh = null;
        foreach ($t['abujh'] ?? [] as $k) {
            if (isset($fest[$k])) { $abujh = $fest[$k]; break; }
        }
        $waive = $abujh !== null;

        // ── विशेष योग पहले — इनसे शमन तय होता है ──
        $an = P::ANANDADI[$vaar][$f['nak28']] ?? ['n' => '', 'bad' => false, 'x' => []];
        $sarvartha = in_array('सर्वार्थ सिद्धि', $an['x'], true);
        $amrit = in_array('अमृत सिद्धि', $an['x'], true);
        $vish = $amrit && ([1 => 5, 2 => 6, 3 => 7, 4 => 8, 5 => 9, 6 => 10, 7 => 11][$vaar] === $tp);
        $pushya = in_array('रवि/गुरु-पुष्य', $an['x'], true);
        $yamaghant = in_array('यमघण्ट', $an['x'], true);
        $raviCount = (($nak - $f['sunNak'] + 27) % 27) + 1;
        $raviYoga = in_array($raviCount, [4, 6, 9, 10, 13, 20], true);
        $shaman = $sarvartha || ($amrit && !$vish) || $raviYoga;

        // ═════ 1. वर्जित काल (मास / अस्त / पर्व-काल) ═════
        $period = static function (string $code, bool $on, string $val) use ($varjit, $soft, $catSoft, $add): void {
            if (!$on) { return; }
            $label = KaryaNiyamVidhi::VARJIT_LABEL[$code] ?? $code;
            if (in_array($code, $varjit, true)) { $add('काल', $label, $val, 'bad'); }
            elseif (in_array($code, $soft, true)) { $add('काल', $label, $val . ' (मत/लोक — सावधानी)', 'warn', -10); }
            elseif (in_array($code, $catSoft, true)) { $add('काल', $label, $val . ' (श्रेणी-नियम — सावधानी)', 'warn', -8); }
        };
        $m = $f['maas'];
        $maasName = ($m['adhik'] ? 'अधिक ' : '') . self::MAAS_HI[$m['pm']];
        $period('kharmas', in_array($f['sunSign'], [8, 11], true), 'सूर्य ' . self::signHi($f['sunSign']) . ' में');
        $period('adhik', $m['adhik'], $maasName . ' — अधिक मास');
        $chatur = (!$m['adhik'] && $m['am'] === 3 && $tithi >= 11) || in_array($m['am'], [4, 5, 6], true) || ($m['am'] === 7 && $tithi <= 10);
        $devata = (string) ($t['devata'] ?? '');
        $chaturOk = ($devata === 'shiva' && $m['pm'] === 4) ? 'शिव-प्रतिष्ठा श्रावण में' : (($devata === 'durga' && isset($fest['navratri'])) ? 'देवी-प्रतिष्ठा नवरात्र में' : '');
        if ($chatur && $chaturOk !== '') { $add('काल', 'चातुर्मास', 'अपवाद — ' . $chaturOk . ' ग्राह्य', 'info'); $chatur = false; }
        $period('chaturmas', $chatur, $maasName);
        $period('holashtak', !$m['adhik'] && $m['am'] === 11 && $tithi >= 8 && $tithi <= 15, 'फाल्गुन शुक्ल 8 → पूर्णिमा');
        $period('pitru', !$m['adhik'] && $m['am'] === 5 && $tithi >= 15, 'भाद्रपद पूर्णिमा → आश्विन अमावस्या');
        $as = $day['asta'];
        $asta = [];
        if ($as['Jupiter']) { $asta[] = 'गुरु'; }
        if ($as['Venus']) { $asta[] = 'शुक्र'; }
        $period('asta', $asta !== [], implode(' व ', $asta) . ' अस्त');
        $period('guru_asta', $as['Jupiter'], 'गुरु अस्त');
        $period('shukra_asta', $as['Venus'], 'शुक्र अस्त');
        $period('budh_asta', $as['Mercury'], 'बुध अस्त');
        $bv = [];
        foreach (['Jupiter' => 'गुरु', 'Venus' => 'शुक्र'] as $pl => $hi) {
            if (($day['bv'][$pl] ?? null) !== null) { $bv[] = $hi . ' ' . ($day['bv'][$pl] === 'bal' ? 'बाल (उदय के बाद)' : 'वृद्ध (अस्त से पहले)'); }
        }
        if ($bv !== [] && (in_array('balvriddha', $varjit, true) || in_array('balvriddha', $soft, true))) {
            $add('काल', 'बाल-वृद्ध दोष', implode(', ', $bv) . ' — दिन-संख्या में पंचांग-भेद', 'warn', -10);
        }
        $period('simhastha', $f['signs']['Jupiter'] === 4, 'गुरु सिंह राशि में (क्षेत्र-विशेष)');
        $period('budh_vakri', !empty($as['_retro']['Mercury']), 'बुध वक्री');
        $period('sun_dvisvabhava', in_array($f['sunSign'], [2, 5, 8, 11], true), 'सूर्य ' . self::signHi($f['sunSign']) . ' (द्विस्वभाव) में');
        $period('ravi_hasta', $vaar === 1 && $nak === 13, 'रविवार + हस्त');
        $period('vriddhi_yoga', $f['yoga'] === 11, 'वृद्धि योग — ऋण बढ़ता है');
        $dagdhaT = [8 => 2, 11 => 2, 1 => 4, 10 => 4, 0 => 6, 3 => 6, 2 => 8, 5 => 8, 4 => 10, 7 => 10, 6 => 12, 9 => 12][$f['sunSign']];
        $period('dagdha_tithi', $tp === $dagdhaT, 'सूर्य ' . self::signHi($f['sunSign']) . ' में ' . P::TITHI[$tp]['name'] . ' (दशदोष)');
        if (in_array('latta', $varjit, true)) {
            $n0 = $nak - 1; $sg = $f['lon'];
            $nk = static fn (string $pl): int => (int) floor($sg[$pl] / KaryaKaal::NAK) % 27;
            $kick = ['सूर्य' => ($nk('Sun') + 11) % 27, 'मंगल' => ($nk('Mars') + 2) % 27, 'गुरु' => ($nk('Jupiter') + 5) % 27,
                'शनि' => ($nk('Saturn') + 7) % 27, 'शुक्र' => ($nk('Venus') + 23) % 27, 'बुध' => ($nk('Mercury') + 21) % 27,
                'राहु' => ($nk('Rahu') + 19) % 27];
            $hit = array_keys(array_filter($kick, static fn ($v) => $v === $n0));
            $period('latta', $hit !== [], implode(', ', $hit) . ' की लत्ता ' . $nName . ' पर');
        }
        if (in_array('yuti', $varjit, true)) {
            $with = [];
            // दशदोष की युति — विवाह-नक्षत्र में ही (चंद्र के साथ उसी नक्षत्र में) पाप ग्रह
            foreach (self::MALEFIC as $pl) {
                if ((int) floor($f['lon'][$pl] / KaryaKaal::NAK) + 1 === $nak) { $with[] = KaryaKaal::HI[$pl]; }
            }
            $period('yuti', $with !== [], $nName . ' में चंद्र के साथ ' . implode(', ', $with));
        }
        $period('panchak', $f['moon'] >= 300.0, 'चंद्र धनिष्ठा उत्तरार्ध–रेवती (कुम्भ-मीन)');
        $sankDay = false;
        foreach ($day['sankranti'] as [$tk]) { if ($tk >= $day['rise'] && $tk < $day['next']) { $sankDay = true; } }
        $period('sankranti_day', $sankDay, 'आज सूर्य-संक्रान्ति');
        // ग्रहण — उस दिन व ±3 दिन (संस्कार)
        foreach ($day['eclipseNear'] as $e) {
            $nm = $e['type'] === 'surya' ? 'सूर्य ग्रहण' : 'चंद्र ग्रहण';
            $when = $e['days'] === 0 ? 'आज' : ($e['days'] > 0 ? $e['days'] . ' दिन बाद' : abs($e['days']) . ' दिन पहले');
            if ($e['days'] === 0 && self::sado($t, 'grahan')) {
                $add('काल', $nm . ' का दिन', $when . ' — पृथ्वी पर कहीं दृश्य; स्थानीय दृश्यता पंचांग से जाँचें', 'bad');
            } else {
                $period('grahan', true, $nm . ' ' . $when . ' (±3 दिन संस्कार वर्जित — मत: 7)');
            }
        }
        if (in_array('grahan_viddha', $varjit, true)) {
            foreach ($ctx['eclipses'] ?? [] as $e) {
                if ($e['jd'] < $f['jd'] && $f['jd'] - $e['jd'] < 183 && $e['nak'] === $nak) {
                    $add('काल', 'ग्रहण-विद्ध नक्षत्र', $nName . ' में ' . ($e['type'] === 'surya' ? 'सूर्य' : 'चंद्र') . ' ग्रहण हुआ था — 6 मास वर्जित (मत: 1 मास)', 'bad');
                    break;
                }
            }
        }
        if (!empty($t['gem']) && in_array('graha_asta', $varjit, true)) {
            $g = $t['gem'];
            $astaG = $g === 'Sun' ? false : (bool) ($as[$g] ?? false);
            $neech = isset(self::NEECH[$g]) && $f['signs'][$g] === self::NEECH[$g];
            if ($astaG || $neech) {
                $add('काल', 'रत्न का ग्रह पीड़ित', KaryaKaal::HI[$g] . ($astaG ? ' अस्त' : '') . ($neech ? ' नीच राशि (' . self::signHi(self::NEECH[$g]) . ') में' : ''), 'bad');
            } else {
                $add('काल', 'रत्न का ग्रह', KaryaKaal::HI[$g] . ' उदित, नीच नहीं', 'ok');
            }
        }

        // ── मास / अयन ──
        $ms = $t['maas'] ?? [];
        if (isset($ms['sun'])) {
            $okSun = in_array($f['sunSign'], $ms['sun'], true);
            $add('काल', $okSun ? 'सूर्य-मास' : 'सूर्य-मास अविहित', 'सूर्य ' . self::signHi($f['sunSign']) . ' में' . ($okSun ? ' — विहित' : ' — विहित नहीं'), $okSun ? 'ok' : 'bad');
        }
        if (isset($ms['months'])) {
            $pm = $m['pm'];
            if (in_array($pm, $ms['months'], true)) { $add('काल', 'शुभ मास: ' . $maasName, 'विहित मास', 'good', 3); }
            elseif (in_array($pm, $ms['months_madhyam'] ?? [], true)) { $add('काल', 'मास', $maasName . ' — मध्यम', 'warn', -6); }
            elseif (in_array($pm, $ms['months_bad'] ?? [], true) && !empty($ms['hard'])) { $add('काल', 'मास वर्जित', $maasName, 'bad'); }
            elseif (!empty($ms['hard'])) { $add('काल', 'मास वर्जित', $maasName . ' — विहित मासों में नहीं', 'bad'); }
            else { $add('काल', 'मास', $maasName . ' — विहित मासों में नहीं', 'warn', in_array($pm, $ms['months_bad'] ?? [], true) ? -12 : -8); }
        } elseif (isset($ms['months_bad']) && in_array($m['pm'], $ms['months_bad'], true)) {
            $add('काल', 'मास', $maasName . ' — वर्जित (मत)', 'warn', -10);
        }
        if (isset($ms['months_pref']) && in_array($m['pm'], $ms['months_pref'], true)) { $add('काल', 'मास', $maasName . ' — श्रेष्ठ', 'good', 6); }
        if (isset($ms['sun_pref']) && in_array($f['sunSign'], $ms['sun_pref'], true)) { $add('काल', 'ऋतु', 'वर्षा ऋतु — श्रेष्ठ', 'good', 5); }
        if (($ms['uttarayan'] ?? null) !== null && !$f['uttarayan']) {
            if ($ms['uttarayan'] === 'hard') { $add('काल', 'अयन', 'दक्षिणायन — उत्तरायण आवश्यक', 'bad'); }
            else { $add('काल', 'अयन', 'दक्षिणायन — उत्तरायण श्रेष्ठ', 'warn', -6); }
        }
        if (!empty($t['ritu'])) {
            $yr = $f['yearRitu'];
            $add('काल', 'वर्ष-ऋतु', self::RITU_HI[$yr] . ($yr === $t['ritu'] ? ' — कर्म की ऋतु' : ' (कर्म की ऋतु ' . self::RITU_HI[$t['ritu']] . ')'), $yr === $t['ritu'] ? 'good' : 'info', $yr === $t['ritu'] ? 4 : 0);
        }

        // ═════ 2. पक्ष व तिथि ═════
        $pmode = $t['paksha'] ?? 'auto';
        if ($pmode === 'auto') { $pmode = in_array($t['nature'] ?? 'mangal', ['mangal'], true) ? 'mangal' : 'none'; }
        if (!$waive) {
            if ($pmode === 'shukla' && $pk === 'k') { $add('तिथि', 'पक्ष', 'कृष्ण पक्ष — शुक्ल पक्ष आवश्यक', 'bad'); }
            elseif ($pmode === 'shukla_pref' && $pk === 'k') { $add('तिथि', 'पक्ष', 'कृष्ण पक्ष — शुक्ल श्रेष्ठ', 'warn', -8); }
            elseif ($pmode === 'krishna' && $pk === 's') { $add('तिथि', 'पक्ष', 'शुक्ल पक्ष — इस कर्म में कृष्ण पक्ष श्रेष्ठ', 'warn', -8); }
            elseif ($pmode === 'mangal' && $pk === 'k') {
                if ($tp <= 10) { $add('तिथि', 'पक्ष', 'कृष्ण पक्ष (दशमी तक मध्यम)', 'warn', -4); }
                else { $add('तिथि', 'पक्ष', 'कृष्ण पक्ष दशमी के बाद — चंद्र क्षीण', 'warn', -10); }
            } else { $add('तिथि', 'पक्ष', $pk === 's' ? 'शुक्ल पक्ष' : 'कृष्ण पक्ष', 'ok'); }
        }
        $ov = $t['tithi'] ?? [];
        $tlabel = 'तिथि वर्जित: ' . P::TITHI[$tithi]['name'];
        $dev = $t['devataTithi'] ?? [];
        if ($waive || $fixedDay) {
            $add('तिथि', 'तिथि', $tName . ($waive ? ' — अबूझ दिन, विचार शिथिल' : ' — नामकरण का नियत दिन, तिथि-विचार शिथिल'), 'info');
        } elseif (in_array($tithi, $dev, true)) {
            $add('तिथि', 'तिथि', $tName . ' — देवता की निज तिथि (रिक्ता दोष नहीं)', 'good', 6);
        } elseif ($tithi === 30 && !empty($t['amavasyaGood'])) {
            $add('तिथि', 'तिथि', $tName . ' — पितृ कार्य में शुभ', 'good', 10);
        } elseif ($tithi === 30 && isset($fest['diwali']) && !empty($t['diwaliOk'])) {
            $add('तिथि', 'तिथि', 'दीपावली अमावस्या — अपवादस्वरूप शुभ', 'good', 6);
        } elseif (in_array($tithi, $ov['ok'] ?? [], true)) {
            $add('तिथि', 'तिथि', $tName . ' — शुभ (टिप्पणी)', 'ok');
        } elseif (in_array($tithi, $ov['bad'] ?? [], true)) {
            $add('तिथि', $tlabel, $tName . ' — इस कार्य में त्याज्य (टिप्पणी)', 'bad');
        } elseif (in_array($tithi, $ov['madhyam'] ?? [], true)) {
            $add('तिथि', 'तिथि मध्यम', $tName . ' — मध्यम', 'warn', -4);
        } elseif (in_array($tithi, $ov['neutral'] ?? [], true)) {
            $add('तिथि', 'तिथि', $tName . ' — सामान्य', 'warn', -4);
        } elseif (in_array($tcode, $t['t+'], true)) {
            $add('तिथि', 'तिथि', $tName . ' — शुभ तिथि', 'ok');
        } elseif (in_array($tcode, $t['t-'], true)) {
            $add('तिथि', $tlabel, $tName . ' — वर्जित तिथि', 'bad');
        } elseif ($t['t+'] !== [] && in_array('t', $t['strict'] ?? [], true)) {
            $add('तिथि', 'तिथि अविहित: ' . P::TITHI[$tithi]['name'], $tName . ' — इस कर्म की तिथि नहीं', 'bad');
        } elseif ($t['t+'] !== []) {
            $add('तिथि', 'तिथि सामान्य', $tName . ' — शुभ सूची में नहीं', 'warn', -6);
        } else {
            $add('तिथि', 'तिथि', $tName, 'ok');
        }
        if ($tithi === 15 && !empty($t['purnimaGood'])) { $add('तिथि', 'पूर्णिमा', 'मोती हेतु पूर्णिमा श्रेष्ठ', 'good', 10); }
        $varga = in_array($tp, self::NANDA, true) ? 'नंदा' : (in_array($tp, self::BHADRA_T, true) ? 'भद्रा' : (in_array($tp, self::JAYA, true) ? 'जया' : (in_array($tp, self::RIKTA, true) ? 'रिक्ता' : 'पूर्णा')));
        if ($verbose) { $add('तिथि', 'तिथि-वर्ग · देवता · चंद्र-बल', $varga . ' · ' . P::TITHI[$tithi]['devata'] . ' · ' . P::TITHI[$tithi]['bal'], 'info'); }
        if (!$waive && $f['kshaya'] !== false && self::sado($t, 'kshaya')) { $add('तिथि', 'क्षय तिथि', P::TITHI[(int) $f['kshaya']]['name'] . ' — दो सूर्योदय नहीं छूती', 'bad'); }
        if (!$waive && $f['vriddhi'] !== false && self::sado($t, 'kshaya')) { $add('तिथि', 'वृद्धि तिथि', 'तिथि दो सूर्योदयों को छूती है', 'warn', -4); }
        if (!$waive && $f['randhra'] !== false && self::sado($t, 'randhra')) { $add('तिथि', 'पक्षरन्ध्र', P::TITHI[(int) $f['randhra']]['name'] . ' की आरम्भिक त्याज्य घटियाँ', 'bad'); }
        if (!$waive && $f['gandantT'] && self::sado($t, 'gandant')) { $add('तिथि', 'तिथि-गण्डान्त', 'पूर्णा-नंदा संधि (±1 घटी)', 'bad'); }

        // ═════ 3. वार ═════
        if ($waive || $fixedDay) {
            $add('वार', 'वार', $vName . ($fixedDay && !$waive ? ' — नियत दिन, वार-विचार शिथिल' : ''), 'info');
        } elseif (in_array($vaar, $t['v+'], true)) {
            $add('वार', 'वार', $vName . ' — शुभ वार', 'ok');
            if (!empty($t['vaarBonus'][$vaar])) { $add('वार', 'वार', $t['vaarBonus'][$vaar], 'good', 5); }
        } elseif (in_array($vaar, $t['v-'], true)) {
            $add('वार', 'वार वर्जित: ' . $vName, P::VAAR[$vaar]['svabhav'] . ' — इस कार्य में वर्जित', 'bad');
        } elseif (in_array($vaar, $t['vaar_madhyam'] ?? [], true)) {
            $add('वार', 'वार मध्यम', $vName . ' — मध्यम', 'warn', -5);
        } elseif ($t['v+'] !== [] && in_array('v', $t['strict'] ?? [], true)) {
            $add('वार', 'वार अविहित: ' . $vName, implode('/', array_map(static fn ($x) => P::VAAR[$x]['name'], $t['v+'])) . ' ही विहित', 'bad');
        } elseif ($t['v+'] !== []) {
            $add('वार', 'वार सामान्य', $vName . ' — शुभ सूची में नहीं', 'warn', -8);
        } else {
            $add('वार', 'वार', $vName, 'ok');
        }

        // ═════ 4. नक्षत्र ═════
        if ($waive) {
            $add('नक्षत्र', 'नक्षत्र', $nName, 'info');
        } elseif (in_array($nak, $t['n+'], true)) {
            $add('नक्षत्र', 'नक्षत्र', $nName . ' (' . P::NAK[$nak]['varga'] . ') — शुभ', 'ok');
        } elseif (in_array($nak, $t['n-'], true)) {
            if (!empty($t['shantiNaks'])) { $add('नक्षत्र', 'शांति आवश्यक: ' . $nName, P::NAK[$nak]['chihn'] . ' — पंचक-शांति / त्रिपुष्कर-शांति सहित', 'bad'); }
            else { $add('नक्षत्र', 'नक्षत्र वर्जित: ' . $nName, P::NAK[$nak]['varga'] . ' — इस कार्य में वर्जित', 'bad'); }
        } elseif (in_array($nak, $t['nak_madhyam'] ?? [], true)) {
            $add('नक्षत्र', 'नक्षत्र मध्यम', $nName . ' — मध्यम', 'warn', -6);
        } elseif ($t['n+'] !== [] && in_array('n', $t['strict'] ?? [], true)) {
            $add('नक्षत्र', 'नक्षत्र अविहित: ' . $nName, 'इस कर्म का नक्षत्र नहीं', 'bad');
        } elseif ($t['n+'] !== []) {
            $add('नक्षत्र', 'नक्षत्र सामान्य', $nName . ' (' . P::NAK[$nak]['varga'] . ') — शुभ सूची में नहीं', 'warn', (int) ($t['nakPenalty'] ?? -10));
        } else {
            $add('नक्षत्र', 'नक्षत्र', $nName, 'ok');
        }
        if (!$waive) {
            $mukh = P::NAK[$nak]['mukh'];
            if (!empty($t['urdhva']) && $mukh === 'ऊर्ध्वमुख') { $add('नक्षत्र', 'ऊर्ध्वमुख नक्षत्र', 'ऊपर उठने वाले कार्य हेतु श्रेष्ठ', 'good', 5); }
            if (!empty($t['adho']) && $mukh === 'अधोमुख') { $add('नक्षत्र', 'अधोमुख नक्षत्र', 'खुदाई हेतु (मत)', 'good', 3); }
            if (!empty($t['tiryak']) && $mukh === 'तिर्यक्मुख') { $add('नक्षत्र', 'तिर्यक्मुख नक्षत्र', 'जुताई हेतु श्रेष्ठ', 'good', 5); }
            if (in_array($nak, $t['nakBonus'] ?? [], true)) { $add('नक्षत्र', 'विशेष नक्षत्र', $nName . ' (' . P::NAK[$nak]['devata'] . ')', 'good', 6); }
            if ($f['gandantN'] && self::sado($t, 'gandant')) { $add('नक्षत्र', 'नक्षत्र-गण्डान्त', 'आश्लेषा-मघा / ज्येष्ठा-मूल / रेवती-अश्विनी संधि', 'bad'); }
            if ($f['varjyam'] && self::sado($t, 'varjyam')) { $add('नक्षत्र', 'विष घटी (वर्ज्यम्)', $nName . ' की विष घटियाँ', 'bad'); }
        }
        if ($verbose) { $add('नक्षत्र', 'देवता · गण · मुख', P::NAK[$nak]['devata'] . ' · ' . P::NAK[$nak]['gana'] . ' · ' . P::NAK[$nak]['mukh'] . ' · पाद ' . $f['pada'], 'info'); }

        // ═════ 5. योग व करण (भद्रा) ═════
        $yg = $f['yoga'];
        $yName = P::YOGA[$yg]['name'];
        if (in_array($yg, [17, 27], true) && self::sado($t, 'vyatipat')) {
            $add('योग-करण', $yName . ' योग', 'सम्पूर्ण त्याज्य — सभी शुभ कार्य वर्जित', 'bad');
        } elseif ($f['yogaTyajya'] !== false && self::sado($t, 'yoga_tyajya') && !$waive) {
            $add('योग-करण', $yName . ' (त्याज्य घटी)', P::YOGA[$yg]['tyajya'] . ' — इसके बाद सामान्य', 'bad');
        } elseif (in_array($yg, $t['yoga_good'] ?? [], true)) {
            $add('योग-करण', 'योग: ' . $yName, 'इस कार्य हेतु श्रेष्ठ', 'good', 4);
        } else {
            $add('योग-करण', 'योग', $yName . ' (' . P::YOGA[$yg]['svabhav'] . ')', P::YOGA[$yg]['svabhav'] === 'शुभ' ? 'ok' : 'info');
        }
        if ($f['vishti']) {
            $vaas = in_array($f['moonSign'], [3, 4, 10, 11], true) ? 'पृथ्वी' : (in_array($f['moonSign'], [0, 1, 2, 7], true) ? 'स्वर्ग' : 'पाताल');
            if (!empty($t['bhadra_ok'])) { $add('योग-करण', 'भद्रा', 'उग्र कर्म में ग्राह्य', 'info'); }
            elseif (self::sado($t, 'bhadra_soft')) { $add('योग-करण', 'भद्रा', 'कुछ मत में शत्रु-कार्य हेतु ग्राह्य', 'warn', -8); }
            elseif (!empty($t['bhadra_strict']) && self::sado($t, 'bhadra')) { $add('योग-करण', 'भद्रा (विष्टि करण)', 'इस कार्य में विष्टि करण पूर्ण वर्जित', 'bad'); }
            elseif (self::sado($t, 'bhadra') && $vaas === 'पृथ्वी') {
                if ($f['bhadraPuchh']) { $add('योग-करण', 'भद्रा-पुच्छ', 'अन्तिम 3 घटी — शुभ कार्यों में ग्राह्य (अपवाद)', 'warn', -6); }
                else { $add('योग-करण', 'भद्रा (पृथ्वी-वास)', ($f['bhadraMukh'] ? 'भद्रा-मुख — अति-अशुभ' : 'मृत्युलोक की भद्रा — पूर्ण दोषकारी'), 'bad'); }
            } else { $add('योग-करण', 'भद्रा', $vaas . '-वास — दोष नहीं', 'info'); }
        } elseif ($verbose) {
            $add('योग-करण', 'करण', $f['karanaName'], 'ok');
        }

        // ═════ 6. विशेष योग ═════
        if (!$waive) {
            if ($sarvartha) { $add('विशेष योग', 'सर्वार्थ सिद्धि योग', $vName . ' + ' . $nName . ' — सब कार्यों में सिद्धि', 'good', 10); }
            if ($amrit) {
                if ($vish) { $add('विशेष योग', 'विष योग', 'अमृत सिद्धि + तिथि ' . $tp . ' (मत)', 'warn', -10); }
                else { $add('विशेष योग', 'अमृत सिद्धि योग', 'अत्यंत शुभ, कार्य-सिद्धि', 'good', 12); }
            }
            if ($pushya && !in_array(8, $t['n-'], true)) { $add('विशेष योग', $vaar === 5 ? 'गुरु-पुष्य' : 'रवि-पुष्य', 'स्वर्ण, आभूषण, व्यापार, यंत्र, विद्या, औषधि हेतु श्रेष्ठ', 'good', 10); }
            if ($raviYoga) { $add('विशेष योग', 'रवि योग', 'सूर्य-नक्षत्र से ' . $raviCount . 'वाँ — अनेक दोषों का नाशक', 'good', 6); }
            $siddha = ($vaar === 6 && in_array($tp, self::NANDA, true)) || ($vaar === 4 && in_array($tp, self::BHADRA_T, true))
                || ($vaar === 3 && in_array($tp, self::JAYA, true)) || ($vaar === 7 && in_array($tp, self::RIKTA, true))
                || ($vaar === 5 && in_array($tp, self::PURNA, true));
            if ($siddha) { $add('विशेष योग', 'सिद्ध योग', $varga . ' तिथि + ' . $vName . ' — तिथि-दोष शमन', 'good', 5); }
            $mrityu = (in_array($vaar, [1, 3], true) && in_array($tp, self::NANDA, true)) || (in_array($vaar, [2, 6], true) && in_array($tp, self::BHADRA_T, true))
                || ($vaar === 4 && in_array($tp, self::JAYA, true)) || ($vaar === 5 && in_array($tp, self::RIKTA, true))
                || ($vaar === 7 && in_array($tp, self::PURNA, true));
            $soften = static function (string $code, bool $on, string $label, string $val) use ($t, $shaman, $add): void {
                if (!$on || !self::sado($t, $code)) { return; }
                if ($shaman) { $add('विशेष योग', $label, $val . ' — सर्वार्थ/अमृत सिद्धि/रवि योग से शमित', 'warn', -8); }
                else { $add('विशेष योग', $label, $val, 'bad'); }
            };
            $soften('mrityu_yoga', $mrityu, 'मृत्यु योग', $varga . ' तिथि + ' . $vName);
            $dagdha = [1 => 12, 2 => 11, 3 => 5, 4 => 3, 5 => 6, 6 => 8, 7 => 9][$vaar] === $tp;
            $soften('dagdha_yoga', $dagdha, 'दग्ध योग', $vName . ' + ' . P::TITHI[$tp]['name']);
            if ($tp + $vaar === 13 && self::sado($t, 'krakach')) { $add('विशेष योग', 'क्रकच योग', 'तिथि + वार = 13', 'bad'); }
            if ((($vaar === 1 && $tp === 7) || ($vaar === 4 && $tp === 1)) && self::sado($t, 'samvartak')) { $add('विशेष योग', 'संवर्तक योग', $vName . ' + ' . P::TITHI[$tp]['name'], 'bad'); }
            if ($yamaghant && self::sado($t, 'yamaghant')) { $add('विशेष योग', 'यमघण्ट योग', $vName . ' + ' . $nName . ' — यात्रा व शुभ कार्य वर्जित', 'bad'); }
            $jwala = [1 => 19, 5 => 2, 8 => 3, 9 => 4, 10 => 9][$tp] ?? 0;
            if ($jwala === $nak && self::sado($t, 'jwalamukhi')) { $add('विशेष योग', 'ज्वालामुखी योग', P::TITHI[$tp]['name'] . ' + ' . $nName, 'bad'); }
            // आनन्दादि ✘ — "उत्पात" व "मृत्यु" विधि (चरण 8) में नाम से त्याज्य, इसलिए कठोर;
            // शेष ✘ (कालदण्ड, धूम्र, ध्वांक्ष, वज्र, मुद्गर, लुम्ब, काण, मुसल, गद, राक्षस)
            // अशुभ पर शमन-योग्य — अंक घटाते हैं, दिन नहीं काटते।
            if ($an['bad'] && in_array($an['n'], ['उत्पात', 'मृत्यु'], true)) {
                $soften('anandadi', true, 'आनन्दादि: ' . $an['n'], $vName . ' + ' . $nName . ' — त्याज्य योग');
            } elseif ($an['bad'] && self::sado($t, 'anandadi')) {
                $add('विशेष योग', 'आनन्दादि: ' . $an['n'], $vName . ' + ' . $nName . ' — अशुभ योग' . ($shaman ? ' (शमित)' : ''), 'warn', $shaman ? -4 : -10);
            }
            elseif ($verbose && $an['n'] !== '') { $add('विशेष योग', 'आनन्दादि योग', $an['n'], 'ok'); }
            $dvi = in_array($vaar, [1, 3, 7], true) && in_array($tp, self::BHADRA_T, true) && in_array($nak, [5, 14, 23], true);
            $tri = in_array($vaar, [1, 3, 7], true) && in_array($tp, self::BHADRA_T, true) && in_array($nak, [3, 7, 12, 16, 21, 25], true);
            if ($dvi || $tri) {
                $pn = $dvi ? 'द्विपुष्कर योग' : 'त्रिपुष्कर योग';
                if (($t['pushkar'] ?? '') === 'good') { $add('विशेष योग', $pn, 'जो कार्य हो वह ' . ($dvi ? 'दोहराता' : 'तीन गुना होता') . ' है — इस कार्य में शुभ', 'good', 8); }
                elseif (in_array('pushkar', $varjit, true)) { $add('विशेष योग', $pn, 'कार्य दोहराता है — इस कार्य में वर्जित', 'bad'); }
                else { $add('विशेष योग', $pn, 'कार्य दोहराता है — हानि/रोग/ऋण के कार्य में सावधानी', 'info'); }
            }
        }

        // ═════ 7. लग्न ═════
        $L = $t['lagna'] ?? [];
        if ($L !== false) {
            $as1 = $f['ascSign'];
            $house = static fn (int $sg): int => (($sg - $as1 + 12) % 12) + 1;
            $lname = self::signHi($as1);
            if ($t['l+'] !== []) {
                if (in_array($as1 + 1, $t['l+'], true)) { $add('लग्न', 'लग्न', $lname . ' — शुभ लग्न', 'ok'); }
                elseif ($waive) { $add('लग्न', 'लग्न अविहित (अबूझ में क्षम्य)', $lname . ' — विहित लग्न नहीं (अबूझ दिन)', 'warn', -6); }
                else { $add('लग्न', 'लग्न अविहित: ' . $lname, 'विहित लग्नों में नहीं', 'bad'); }
            } elseif (!empty($L['svabhav'])) {
                $sv = [0 => 'char', 3 => 'char', 6 => 'char', 9 => 'char', 1 => 'sthir', 4 => 'sthir', 7 => 'sthir', 10 => 'sthir'][$as1] ?? 'dvi';
                if (in_array($sv, $L['svabhav'], true)) { $add('लग्न', 'लग्न', $lname . ' — स्वभाव अनुकूल', 'ok'); }
                else { $add('लग्न', 'लग्न-स्वभाव', $lname . ' (चर) — स्थिर/द्विस्वभाव श्रेष्ठ', 'warn', -8); }
            } else {
                $add('लग्न', 'लग्न', $lname, 'info');
            }
            if (!empty($L['svabhav_pref']) && in_array($as1, [1, 4, 7, 10], true)) { $add('लग्न', 'स्थिर लग्न', 'स्थायित्व हेतु श्रेष्ठ', 'good', 4); }
            if (!empty($L['leo_bonus']) && $as1 === 4) { $add('लग्न', 'सिंह लग्न', 'शपथ हेतु विशेष', 'good', 4); }
            if ($f['gandantL'] && self::sado($t, 'gandant')) { $add('लग्न', 'लग्न-गण्डान्त', 'कर्क-सिंह / वृश्चिक-धनु / मीन-मेष संधि', 'bad'); }
            $occ = [];
            foreach (KaryaKaal::PLANETS as $pl) { $occ[$house($f['signs'][$pl])][] = $pl; }
            $hiList = static fn (array $pls): string => implode(', ', array_map(static fn ($p) => KaryaKaal::HI[$p], $pls));
            // 8वाँ भाव
            $eighthHard = !empty($L['eighth_hard']) || in_array(8, $L['empty'] ?? [], true);
            if (!empty($occ[8])) {
                if ($eighthHard) { $add('लग्न', '8वाँ भाव भरा', $hiList($occ[8]) . ' — 8वाँ भाव रिक्त होना चाहिए', 'bad'); }
                else { $add('लग्न', '8वाँ भाव भरा', $hiList($occ[8]) . ' — 8वाँ रिक्त श्रेष्ठ', 'warn', -8); }
            } else { $add('लग्न', '8वाँ भाव', 'रिक्त', 'ok'); }
            foreach ($L['empty'] ?? [] as $h) {
                if ($h !== 8 && !empty($occ[$h])) { $add('लग्न', $h . 'वाँ भाव भरा', $hiList($occ[$h]) . ' — इस कार्य में ' . $h . 'वाँ रिक्त चाहिए', 'bad'); }
            }
            foreach ($L['bad'] ?? [] as $pl => $hs) {
                if (in_array($house($f['signs'][$pl]), $hs, true)) { $add('लग्न', KaryaKaal::HI[$pl] . ' ' . $house($f['signs'][$pl]) . 'वें भाव में', 'इस कार्य में वर्जित', 'bad'); }
            }
            $mh = $house($f['moonSign']);
            $moonBad = $L['moon_bad'] ?? [6, 8, 12];
            if (in_array($mh, $moonBad, true)) {
                if (!empty($L['moon_hard']) || isset($L['moon_bad'])) { $add('लग्न', 'चंद्र ' . $mh . 'वें भाव में', 'लग्न से 6/8/12 चंद्र वर्जित', 'bad'); }
                else { $add('लग्न', 'चंद्र ' . $mh . 'वें भाव में', 'लग्न से 6/8/12 चंद्र अशुभ', 'warn', -10); }
            }
            $isMal = static fn (string $p): bool => in_array($p, self::MALEFIC, true);
            $mal2 = array_filter($occ[2] ?? [], $isMal); $mal12 = array_filter($occ[12] ?? [], $isMal);
            if ($mal2 && $mal12) { $add('लग्न', 'पाप-कर्तरी', 'लग्न के दोनों ओर पाप ग्रह (' . $hiList(array_merge($mal12, $mal2)) . ')', 'warn', -10); }
            $strongMoon = $f['elong'] >= 72.0 && $f['elong'] <= 288.0;
            $benefic = ['Jupiter', 'Venus', 'Mercury'];
            if ($strongMoon) { $benefic[] = 'Moon'; }
            $inKT = [];
            foreach ([1, 4, 5, 7, 9, 10] as $h) { foreach ($occ[$h] ?? [] as $p) { if (in_array($p, $benefic, true)) { $inKT[] = $p; } } }
            $jh = $house($f['signs']['Jupiter']);
            if ($inKT) { $add('लग्न', 'केंद्र-त्रिकोण में शुभ ग्रह', $hiList(array_unique($inKT)), 'good', 5); }
            elseif (in_array($jh, [1, 5, 7, 9], true)) { $add('लग्न', 'लग्न पर गुरु-दृष्टि', 'गुरु ' . $jh . 'वें भाव से', 'ok'); }
            else { $add('लग्न', 'केंद्र-त्रिकोण में शुभ ग्रह नहीं', 'लग्न पर गुरु की दृष्टि भी नहीं', 'warn', -4); }
            $m3 = 0;
            foreach ([3, 6, 11] as $h) { foreach ($occ[$h] ?? [] as $p) { if ($isMal($p)) { $m3++; } } }
            if ($m3) { $add('लग्न', 'पाप ग्रह 3/6/11 में', $m3 . ' ग्रह — शुभ', 'good', min(6, 3 * $m3)); }
            $malL = array_filter($occ[1] ?? [], $isMal);
            if ($malL) { $add('लग्न', 'लग्न में पाप ग्रह', $hiList($malL), 'warn', -4); }
            $lord = self::LORD[$as1];
            $lh = $house($f['signs'][$lord]);
            if (in_array($lh, [6, 8, 12], true)) {
                if (!empty($L['lagnesh_hard'])) { $add('लग्न', 'लग्नेश ' . $lh . 'वें भाव में', KaryaKaal::HI[$lord] . ' — वर्जित', 'bad'); }
                else { $add('लग्न', 'लग्नेश ' . $lh . 'वें भाव में', KaryaKaal::HI[$lord] . ' — निर्बल', 'warn', -6); }
            }
            foreach ($L['benefic_lagna'] ?? [] as $pl) {
                if ($house($f['signs'][$pl]) === 1) { $add('लग्न', KaryaKaal::HI[$pl] . ' लग्न में', 'इस कार्य में शुभ', 'good', 6); }
            }
            if (!empty($L['benefic_kendra'])) {
                $bk = $house($f['signs'][$L['benefic_kendra']]);
                $ok = in_array($bk, [1, 4, 5, 7, 9, 10], true);
                $add('लग्न', KaryaKaal::HI[$L['benefic_kendra']] . ' केंद्र/त्रिकोण में', $bk . 'वाँ भाव', $ok ? 'good' : 'warn', $ok ? 6 : -6);
            }
            foreach ($L['bhav'] ?? [] as $h) {
                $ben = array_values(array_filter($occ[$h] ?? [], static fn ($p) => in_array($p, $benefic, true)));
                $mal = array_values(array_filter($occ[$h] ?? [], $isMal));
                if ($ben) { $add('लग्न', 'कार्य-भाव ' . $h . ' में शुभ ग्रह', $hiList($ben), 'good', 4); }
                if ($mal && !in_array($h, [3, 6, 11], true)) { $add('लग्न', 'कार्य-भाव ' . $h . ' में पाप ग्रह', $hiList($mal), 'warn', -4); }
            }
            foreach ($L['bhav_malefic_good'] ?? [] as $h) {
                if (array_filter($occ[$h] ?? [], $isMal)) { $add('लग्न', $h . 'वें भाव में पाप ग्रह', 'शत्रु/रोग-भाव पीड़ित — इस कार्य में शुभ', 'good', 5); }
            }
            foreach ($L['bhav_malefic_bad'] ?? [] as $h) {
                if (array_filter($occ[$h] ?? [], $isMal)) { $add('लग्न', $h . 'वाँ भाव पीड़ित', 'ऋण-भाव पर पाप ग्रह', 'warn', -6); }
            }
            foreach ($L['jup_aspect'] ?? [] as $h) {
                $asp = [$jh, ($jh + 3) % 12 + 1, ($jh + 5) % 12 + 1, ($jh + 7) % 12 + 1];
                if (in_array($h, $asp, true)) { $add('लग्न', 'गुरु-दृष्टि ' . $h . 'वें भाव पर', 'शुभ', 'good', 5); break; }
            }
            if (!empty($t['moonCharBonus']) && in_array($f['moonSign'], [0, 3, 6, 9], true)) { $add('लग्न', 'चंद्र चर राशि में', 'यात्रा हेतु शुभ', 'good', 4); }
            foreach ($persons as $P) {
                $h8a = (($as1 - $P['moonSign'] + 12) % 12) + 1;
                $h8b = $P['ascSign'] === null ? 0 : (($as1 - $P['ascSign'] + 12) % 12) + 1;
                if ($h8a === 8 || $h8b === 8) {
                    $add('लग्न', ($multi ? $P['short'] . ': ' : '') . 'लग्न जन्म से 8वाँ',
                        'मुहूर्त-लग्न ' . $P['short'] . ' की ' . ($h8a === 8 ? ($P['src'] === 'akshar' ? 'नाम राशि' : 'जन्म राशि') : 'जन्म लग्न') . ' से 8वाँ', 'bad');
                }
            }
        }

        // ═════ 8. दिन के भीतर का समय ═════
        $time = $t['time'] ?? 'any';
        if ($time === 'rahukaal') {
            $add('समय', 'राहुकाल', $f['rahukaal'] ? 'राहु-पूजा राहुकाल में ही' : 'राहुकाल नहीं', $f['rahukaal'] ? 'good' : 'warn', $f['rahukaal'] ? 12 : -6);
        } elseif ($f['rahukaal'] && self::sado($t, 'rahukaal')) {
            $add('समय', 'राहुकाल', 'राहुकाल वर्जित', 'bad');
        }
        if ($f['yamaganda'] && self::sado($t, 'yamaganda')) { $add('समय', 'यमगण्ड', 'यमगण्ड काल', 'bad'); }
        if ($f['gulika'] && self::sado($t, 'gulika')) { $add('समय', 'गुलिक काल', 'गुलिक काल', 'bad'); }
        if ($f['durmuhurt'] && self::sado($t, 'durmuhurt')) { $add('समय', 'दुर्मुहूर्त', $vName . ' का दुर्मुहूर्त', 'bad'); }
        if ($f['sankranti'] !== false && self::sado($t, 'sankranti')) { $add('समय', 'संक्रान्ति ±16 घटी', 'सूर्य ' . self::signHi((int) $f['sankranti']) . ' में प्रवेश', 'bad'); }
        if ($f['sutak'] !== false && self::sado($t, 'grahan')) { $add('समय', 'ग्रहण-सूतक', ($f['sutak'] === 'surya' ? 'सूर्य' : 'चंद्र') . ' ग्रहण का सूतक', 'bad'); }
        $southTravel = ($ctx['opts']['dir'] ?? '') === 'दक्षिण';
        if ($f['abhijit']) {
            if ($vaar === 4) { $add('समय', 'अभिजित', 'बुधवार को निष्फल', 'info'); }
            elseif ($southTravel && !empty($t['travel'])) { $add('समय', 'अभिजित', 'दक्षिण यात्रा में नहीं', 'info'); }
            else { $add('समय', 'अभिजित मुहूर्त', 'मध्याह्न — सर्वदोषनाशक', 'good', 6); }
        }
        $ch = $f['chogh'];
        if (!empty($t['chogh'])) {
            $goodCh = $t['chogh'] === 'char' ? ['चर', 'अमृत', 'शुभ', 'लाभ'] : ['अमृत', 'शुभ', 'लाभ'];
            if (in_array($ch, $goodCh, true)) { $add('समय', 'चौघड़िया', $ch . ' — शुभ', 'good', 4); }
            elseif (in_array($ch, ['रोग', 'काल', 'उद्वेग'], true)) { $add('समय', 'चौघड़िया: ' . $ch, 'अशुभ चौघड़िया', 'warn', -10); }
            else { $add('समय', 'चौघड़िया', $ch, 'info'); }
        } elseif ($verbose) {
            $add('समय', 'चौघड़िया', $ch, in_array($ch, ['रोग', 'काल', 'उद्वेग'], true) ? 'info' : 'ok');
        }
        $hora = $f['hora'];
        if (!empty($t['hora'])) {
            if (in_array($hora, $t['hora'], true)) { $add('समय', 'होरा', KaryaKaal::HI[$hora] . ' होरा — इस कार्य की', 'good', 8); }
            elseif (!empty($t['hora_strict'])) { $add('समय', 'होरा अविहित: ' . KaryaKaal::HI[$hora], implode('/', array_map(static fn ($p) => KaryaKaal::HI[$p], $t['hora'])) . ' की होरा में ही धारण', 'bad'); }
            elseif ($verbose) { $add('समय', 'होरा', KaryaKaal::HI[$hora], 'info'); }
        } elseif ($verbose) {
            $add('समय', 'होरा', KaryaKaal::HI[$hora], 'info');
        }
        $tw = static function (bool $in, string $ok, string $no, int $plus, int $minus) use ($add): void {
            $add('समय', 'समय', $in ? $ok : $no, $in ? 'good' : 'warn', $in ? $plus : $minus);
        };
        switch ($time) {
            case 'day': if (!$f['isDay']) { $add('समय', 'रात्रि', 'यह कार्य दिन में ही (सूर्योदय-सूर्यास्त)', 'bad'); } break;
            case 'day_morning':
                if (!$f['isDay']) { $add('समय', 'रात्रि', 'रत्न दिन में धारण करें', 'bad'); }
                elseif ($f['morning']) { $add('समय', 'प्रातः', 'धारण का श्रेष्ठ काल', 'good', 6); }
                break;
            case 'night': if ($f['isDay']) { $add('समय', 'दिन', 'यह कार्य केवल रात्रि में', 'bad'); } break;
            case 'day_no_sandhya':
                if (!$f['isDay']) { $add('समय', 'रात्रि', 'रात्रि में वर्जित', 'bad'); }
                elseif ($f['sandhya']) { $add('समय', 'संध्या', 'संध्या काल वर्जित', 'bad'); }
                break;
            case 'purvahna': $tw($f['purvahna'], 'पूर्वाह्न', 'पूर्वाह्न श्रेष्ठ — यह उसके बाहर है', 3, -10); break;
            case 'pradosh': $tw($f['pradosh'], 'प्रदोष काल', 'प्रदोष काल श्रेष्ठ', 10, -8); break;
            case 'pradosh_morning': $tw($f['pradosh'] || $f['morning'], $f['pradosh'] ? 'प्रदोष काल' : 'प्रातः', 'प्रदोष काल या प्रातः श्रेष्ठ', 8, -6); break;
            case 'kutup':
                if ($f['kutup']) { $add('समय', 'कुतुप काल', 'पितृ कार्य का श्रेष्ठ काल', 'good', 10); }
                elseif ($f['aparahna']) { $add('समय', 'अपराह्न', 'पितृ कार्य हेतु ग्राह्य', 'good', 4); }
                else { $add('समय', 'समय', 'कुतुप/अपराह्न श्रेष्ठ', 'warn', -8); }
                break;
            case 'evening': $tw($f['evening'], 'सायंकाल', 'सायंकाल श्रेष्ठ', 8, -6); break;
            case 'morning': $tw($f['morning'], 'प्रातः', 'प्रातः श्रेष्ठ', 6, -6); break;
            case 'brahma': if ($f['brahma']) { $add('समय', 'ब्रह्म मुहूर्त', 'साधना हेतु श्रेष्ठ', 'good', 10); } break;
            default:
                if (str_starts_with($time, 'ritu:')) {
                    $want = (int) substr($time, 5);
                    $add('समय', 'दिन-भाग', $f['ritu'] === $want ? self::RITU_HI[$want] . ' भाग (कर्म का काल)' : self::RITU_HI[$want] . ' भाग चाहिए (सूर्योदय से ' . (($want - 1) * 96) . '–' . ($want * 96) . ' मि.)',
                        $f['ritu'] === $want ? 'good' : 'warn', $f['ritu'] === $want ? 10 : -10);
                }
        }

        // ═════ 9. दिशा / वास ═════
        $dir = (string) ($ctx['opts']['dir'] ?? '');
        $needDir = !empty($t['travel']) || in_array('dir', $t['options'] ?? [], true);
        if ($needDir) {
            $shool = P::VAAR[$vaar]['shool'];
            $yog = P::YOGINI[$tithi] ?? '';
            $cvDir = ['पूर्व', 'दक्षिण', 'पश्चिम', 'उत्तर'][[0 => 0, 4 => 0, 8 => 0, 1 => 1, 5 => 1, 9 => 1, 2 => 2, 6 => 2, 10 => 2, 3 => 3, 7 => 3, 11 => 3][$f['moonSign']]];
            if ($dir === '') {
                $add('दिशा', 'दिशाशूल', $shool . ' — इस ओर यात्रा वर्जित (परिहार: ' . P::VAAR[$vaar]['parihar'] . ')', 'info');
                $add('दिशा', 'योगिनी · चंद्र वास', 'योगिनी ' . $yog . ' · चंद्र ' . $cvDir . ' — दिशा चुनें तो जाँच होगी', 'info');
            } else {
                $rel = static function (string $target) use ($dir): string {
                    $d = ((KaryaNiyamVidhi::DIRS[$target] ?? 0) - (KaryaNiyamVidhi::DIRS[$dir] ?? 0) + 360) % 360;
                    return $d <= 45 || $d >= 315 ? 'सम्मुख' : ($d < 135 ? 'दाहिने' : ($d <= 225 ? 'पीछे' : 'बाएँ'));
                };
                if ($shool === $dir) {
                    if ($f['abhijit'] && $dir !== 'दक्षिण' && $vaar !== 4) { $add('दिशा', 'दिशाशूल', $dir . ' — अभिजित मुहूर्त से परिहार', 'warn', -6); }
                    else { $add('दिशा', 'दिशाशूल: ' . $dir, 'वार का शूल इसी दिशा में (परिहार: ' . P::VAAR[$vaar]['parihar'] . ')', 'bad'); }
                } else { $add('दिशा', 'दिशाशूल', 'आज ' . $shool . ' — आपकी दिशा मुक्त', 'ok'); }
                if ((self::NAK_SHOOL[$nak] ?? '') === $dir) { $add('दिशा', 'नक्षत्र शूल: ' . $dir, $nName . ' में इस दिशा की यात्रा वर्जित', 'bad'); }
                $ry = $rel($yog);
                if ($ry === 'सम्मुख') { $add('दिशा', 'योगिनी सम्मुख', 'योगिनी ' . $yog . ' में — सामने', 'bad'); }
                elseif ($ry === 'दाहिने') { $add('दिशा', 'योगिनी दाहिने', 'योगिनी ' . $yog . ' में', 'warn', -8); }
                else { $add('दिशा', 'योगिनी', $yog . ' — ' . $ry . ' (शुभ)', 'good', 4); }
                $rc = $rel($cvDir);
                $cvMap = ['सम्मुख' => ['good', 6, 'धन-लाभ'], 'दाहिने' => ['good', 4, 'सुख'], 'पीछे' => ['bad', 0, 'प्राण-भय'], 'बाएँ' => ['warn', -8, 'धन-हानि']];
                [$cs, $cp, $cf] = $cvMap[$rc];
                $add('दिशा', $cs === 'bad' ? 'चंद्र पीछे' : 'चंद्र वास', 'चंद्र ' . $cvDir . ' में — ' . $rc . ' (' . $cf . ')', $cs, $cp);
                $venusDir = !empty($as['_venusEast']) ? 'पूर्व' : 'पश्चिम';
                if ($rel($venusDir) === 'सम्मुख' && !$as['Venus']) {
                    if (in_array('pratishukra', $varjit, true)) { $add('दिशा', 'प्रतिशुक्र', 'शुक्र ' . $venusDir . ' में सम्मुख', 'bad'); }
                    elseif (!empty($t['travel'])) { $add('दिशा', 'शुक्र सम्मुख', 'शुक्र ' . $venusDir . ' में सामने', 'warn', -6); }
                }
                if ($dir === 'दक्षिण' && $f['moon'] >= 300.0) { $add('दिशा', 'पंचक में दक्षिण यात्रा', 'पंचक में दक्षिण दिशा वर्जित', 'bad'); }
            }
        }
        // वाम रवि — गृह-प्रवेश के क्षण सूर्य प्रवेश करने वाले की बाईं ओर (मुख्य द्वार की दिशा से):
        // पूर्वमुखी द्वार में लग्न से 8–12, उत्तरमुखी 11–3, पश्चिममुखी 2–6, दक्षिणमुखी 5–9 भाव
        $dwar = (string) ($ctx['opts']['dwar'] ?? '');
        if ($dwar !== '' && ($t['lagna'] ?? []) !== false) {
            $sh = (($f['sunSign'] - $f['ascSign'] + 12) % 12) + 1;
            $okH = ['पूर्व' => [8, 9, 10, 11, 12], 'उत्तर' => [11, 12, 1, 2, 3], 'पश्चिम' => [2, 3, 4, 5, 6], 'दक्षिण' => [5, 6, 7, 8, 9]][$dwar] ?? [];
            if (in_array($sh, $okH, true)) { $add('दिशा', 'वाम रवि', $dwar . 'मुखी द्वार — सूर्य लग्न से ' . self::ordO($sh) . ' भाव में, प्रवेश के समय बाईं ओर', 'good', 6); }
            elseif ($okH !== []) {
                $add('दिशा', 'वाम रवि नहीं', $dwar . 'मुखी द्वार हेतु सूर्य लग्न से ' . $okH[0] . '–' . $okH[4] . ' भाव में चाहिए (अभी ' . self::ord($sh) . ')', 'warn', -8);
                $cap[] = 'वाम रवि नहीं';
            }
        }
        if ($verbose && !$needDir) {
            // पुस्तिका की "मुहूर्त जाँच" की तरह — हर कार्य में सूचना-मात्र (अंक पर असर नहीं)
            $add('दिशा', 'दिशाशूल · योगिनी', P::VAAR[$vaar]['shool'] . ' में दिशाशूल · योगिनी ' . (P::YOGINI[$tithi] ?? '') . ' — यात्रा हो तो इन दिशाओं से बचें', 'info');
        }
        $agniNm = [0 => 'पृथ्वी', 3 => 'पृथ्वी', 1 => 'आकाश', 2 => 'पाताल'][($tithi + $vaar + 1) % 4];
        $shivV = ($tithi * 2 + 5) % 7;
        $shivNm = [1 => 'कैलाश (सुख)', 2 => 'गौरी-सहित (सम्पदा)', 3 => 'वृषारूढ़ (अभीष्ट-सिद्धि)', 4 => 'सभा (संताप)', 5 => 'भोजन (पीड़ा)', 6 => 'क्रीड़ा (कष्ट)', 0 => 'श्मशान (मृत्यु-भय)'][$shivV];
        if ($verbose && empty($t['agnivas'])) {
            $add('दिशा', 'अग्निवास', $agniNm . ' — केवल हवन/यज्ञ हेतु देखा जाता है', 'info');
        }
        if ($verbose && empty($t['shivvas'])) {
            $add('दिशा', 'शिववास', $shivNm . ' — केवल रुद्राभिषेक हेतु देखा जाता है', 'info');
        }
        if (!empty($t['agnivas'])) {
            $nm = $agniNm;
            if (!empty($t['agnivasExempt'])) { $add('दिशा', 'अग्निवास', $nm . ' — ' . $t['agnivasExempt'] . ' में नहीं देखा जाता', 'info'); }
            elseif ($nm === 'पृथ्वी') { $add('दिशा', 'अग्निवास', 'पृथ्वी — हवन शुभ', 'good', 4); }
            else { $add('दिशा', 'अग्निवास: ' . $nm, $nm === 'आकाश' ? 'प्राण-भय — काम्य हवन वर्जित' : 'धन-हानि — काम्य हवन वर्जित', 'bad'); }
        }
        if (!empty($t['shivvas'])) {
            $exempt = isset($fest['shravan']) || isset($fest['shivratri']) || isset($fest['masik_shivratri']) || $tp === 13;
            $v = $shivV;
            $nm = $shivNm;
            if ($exempt) { $add('दिशा', 'शिववास', $nm . ' — श्रावण/शिवरात्रि/प्रदोष में नहीं देखा जाता', 'info'); }
            elseif (in_array($v, [1, 2, 3], true)) { $add('दिशा', 'शिववास', $nm, 'good', 6); }
            else { $add('दिशा', 'शिववास: ' . $nm, 'काम्य अभिषेक हेतु अशुभ वास', 'bad'); }
        }
        if (!empty($t['vastuNaag']) && $verbose) {
            $head = [5 => 'पूर्व', 6 => 'पूर्व', 7 => 'पूर्व', 8 => 'दक्षिण', 9 => 'दक्षिण', 10 => 'दक्षिण', 11 => 'पश्चिम', 0 => 'पश्चिम', 1 => 'पश्चिम', 2 => 'उत्तर', 3 => 'उत्तर', 4 => 'उत्तर'][$m['pm']];
            $add('दिशा', 'वास्तु-नाग (राहु-मुख)', 'नाग का सिर ' . $head . ' में — उस दिशा से खुदाई आरम्भ न करें (मत)', 'info');
        }

        // ═════ 10. व्यक्तिगत बल — जिनके लिए मुहूर्त ({@see KaryaVyakti}) ═════
        // हर व्यक्ति का चंद्र-तारा बल, जन्म नक्षत्र/मास; भूमिका से त्रिबल (कन्या का गुरु,
        // वर का सूर्य, बटुक का गुरु); शिशु के आयु-नियम; यात्रा/मुकदमा/शल्य में घात चक्र।
        $firstBorn = [];
        if (($t['personalOff'] ?? false) === false) {
            $grahaBal = static function (string $who, string $pl, int $h, int $sg, string $mode, string $remedy) use ($add, &$cap): void {
                $jup = $pl === 'Jupiter';
                $nm = $jup ? 'गुरु बल' : 'सूर्य बल';
                $okH = $jup ? [2, 5, 7, 9, 11] : [3, 6, 10, 11];
                $midH = $jup ? [1, 3, 6, 10] : [1, 2, 5, 7, 9];
                if (in_array($h, $okH, true)) { $add('व्यक्तिगत', $who . $nm, self::ord($h) . ' — शुभ', 'good', 4); return; }
                if (in_array($h, $midH, true)) { $add('व्यक्तिगत', $who . $nm . ' मध्यम', self::ord($h) . ' — ' . $remedy . ' से शुभ', 'warn', -6); return; }
                if (in_array($sg, $jup ? [3, 8, 11] : [0, 4], true)) {   // स्वगृही/उच्च ग्रह 4/8/12 में भी दोष नहीं देता (मत)
                    $add('व्यक्तिगत', $who . $nm . ' (स्वगृही/उच्च)', self::ord($h) . ', पर ' . ($jup ? 'गुरु' : 'सूर्य') . ' ' . self::signHi($sg) . ' में — दोष शमित (मत)', 'warn', -8);
                    return;
                }
                if ($mode === 'strict') {
                    $add('व्यक्तिगत', $who . $nm . ' हीन', self::ord($h) . ' — अशुभ; ' . $remedy . ' करके ही, पूर्ण शुभ नहीं', 'warn', -10);
                    $cap[] = $who . $nm . ' हीन (' . self::ord($h) . ')';
                } else {
                    $add('व्यक्तिगत', $who . $nm . ' हीन', self::ord($h) . ' — अशुभ (' . $remedy . ')', 'warn', -8);
                }
            };
            foreach ($persons as $P) {
                $who = $multi ? $P['short'] . ': ' : '';
                $ch2 = (($f['moonSign'] - $P['moonSign'] + 12) % 12) + 1;
                $rashiW = $P['src'] === 'akshar' ? 'नाम राशि' : 'जन्म राशि';
                if ($ch2 === 8) {
                    if (in_array('chandrashtam', $varjit, true) || self::sado($t, 'chandratara')) { $add('व्यक्तिगत', $who . 'चंद्राष्टम', $rashiW . ' से 8वाँ चंद्र — सर्वाधिक अशुभ', 'bad'); }
                    else { $add('व्यक्तिगत', $who . 'चंद्राष्टम', $rashiW . ' से 8वाँ चंद्र', 'warn', -15); }
                } elseif (in_array($ch2, [4, 12], true)) { $add('व्यक्तिगत', $who . 'चंद्र बल हीन', $rashiW . ' से ' . self::ord($ch2) . ' चंद्र — अशुभ', 'warn', -12); }
                elseif (in_array($ch2, [2, 5, 9], true) && $pk === 'k') { $add('व्यक्तिगत', $who . 'चंद्र बल', self::ord($ch2) . ' — केवल शुक्ल पक्ष में शुभ', 'warn', -5); }
                else { $add('व्यक्तिगत', $who . 'चंद्र बल', $rashiW . ' से ' . self::ord($ch2) . ' चंद्र — शुभ', 'good', 2); }
                $cnt = (($nak - $P['nak'] + 27) % 27) + 1;
                $tara = $cnt % 9 === 0 ? 9 : $cnt % 9;
                $tr = P::TARA[$tara];
                if (in_array($tara, [3, 5, 7], true)) {
                    $add('व्यक्तिगत', $who . 'तारा: ' . $tr['name'], $tr['prabhav'] . ($tr['parihar'] !== '—' ? ' — परिहार: ' . $tr['parihar'] : '') . ($pk === 'k' ? ' (कृष्ण पक्ष में तारा बल प्रधान)' : ''), 'warn', $pk === 'k' ? -16 : -12);
                } elseif ($tara === 1) {
                    $add('व्यक्तिगत', $who . 'जन्म तारा', 'मिश्र — शरीर-पीड़ा का भय', 'warn', -4);
                } else {
                    $bonus = !empty($t['taraMitra']) && in_array($tara, [8, 9], true) ? 6 : 3;
                    $add('व्यक्तिगत', $who . 'तारा बल', $tr['name'] . ' — ' . $tr['prabhav'], 'good', $bonus);
                }
                if ($nak === $P['nak']) {
                    if (in_array('janma_nak', $varjit, true)) { $add('व्यक्तिगत', $who . 'जन्म नक्षत्र', $nName . ' — इस कार्य में वर्जित', 'bad'); }
                    elseif (in_array('janma_nak', $soft, true)) { $add('व्यक्तिगत', $who . 'जन्म नक्षत्र', $nName . ' (मत)', 'warn', -8); }
                }
                if ($P['am'] !== null && !$m['adhik'] && $m['am'] === $P['am']) {
                    if (in_array('janma_maas', $varjit, true)) { $add('व्यक्तिगत', $who . 'जन्म मास', self::MAAS_HI[$m['am']] . ' (अमान्त) — वर्जित', 'bad'); }
                    elseif (in_array('janma_maas', $soft, true)) { $add('व्यक्तिगत', $who . 'जन्म मास', self::MAAS_HI[$m['am']] . ' (मत)', 'warn', -8); }
                }
                $jupH = (($f['signs']['Jupiter'] - $P['moonSign'] + 12) % 12) + 1;
                $sunH = (($f['sunSign'] - $P['moonSign'] + 12) % 12) + 1;
                if (!empty($t['tribal']) && $P['role'] === 'kanya') { $grahaBal($who, 'Jupiter', $jupH, $f['signs']['Jupiter'], 'strict', 'पीली (गुरु) पूजा'); }
                if (!empty($t['tribal']) && $P['role'] === 'var') { $grahaBal($who, 'Sun', $sunH, $f['sunSign'], 'strict', 'लाल (सूर्य) पूजा'); }
                if (!empty($t['tribalSoft']) && $P['role'] === 'kanya') { $grahaBal($who, 'Jupiter', $jupH, $f['signs']['Jupiter'], 'soft', 'गुरु-पूजन'); }
                if (!empty($t['guruBal']) && $P['role'] === 'child') {
                    $grahaBal($who, 'Jupiter', $jupH, $f['signs']['Jupiter'], 'strict', 'गुरु-शांति');
                    $grahaBal($who, 'Sun', $sunH, $f['sunSign'], 'soft', 'सूर्य-अर्घ्य');
                }
                if ($P['first']) { $firstBorn[] = $P['short']; }
                if (!empty($t['no_birthday']) && $P['birthMd'] !== null && $P['birthMd'] === sprintf('%02d-%02d', $day['d'], $day['m'])) {
                    $add('व्यक्तिगत', $who . 'जन्मदिन', 'जन्मदिन पर यह कार्य वर्जित', 'bad');
                }
                if (!empty($t['ghat'])) {
                    $G = KaryaVyakti::GHAT[$P['moonSign']];
                    if ($f['moonSign'] === $G['chandra']) { $add('व्यक्तिगत', $who . 'घात चंद्र', 'चंद्र ' . self::signHi($G['chandra']) . ' में — घात चक्र (मत)', 'warn', -10); }
                    if ($nak === $G['nak']) { $add('व्यक्तिगत', $who . 'घात नक्षत्र', $nName . ' — घात चक्र (मत)', 'warn', -8); }
                    if ($vaar === $G['vaar']) { $add('व्यक्तिगत', $who . 'घात वार', $vName . ' — घात चक्र (मत)', 'warn', -6); }
                    if (in_array($tp, $G['tithi'], true)) { $add('व्यक्तिगत', $who . 'घात तिथि', $tName . ' — घात चक्र (मत)', 'warn', -6); }
                    if (!$m['adhik'] && $m['pm'] === $G['maas']) { $add('व्यक्तिगत', $who . 'घात मास', $maasName . ' — घात चक्र (मत)', 'warn', -4); }
                    if ($verbose && $f['moonSign'] !== $G['chandra'] && $nak !== $G['nak'] && $vaar !== $G['vaar'] && !in_array($tp, $G['tithi'], true)) {
                        $add('व्यक्तिगत', $who . 'घात चक्र', 'आज घात चंद्र/नक्षत्र/वार/तिथि नहीं (घात चंद्र: ' . self::signHi($G['chandra']) . ', नक्षत्र: ' . P::NAK[$G['nak']]['name'] . ', वार: ' . P::VAAR[$G['vaar']]['name'] . ')', 'ok');
                    }
                }
                if (!empty($t['age']) && $P['role'] === 'child') { self::ageRule((string) $t['age'], $P, $f, $ctx, $add, $cap, $fixedDay); }
            }
            // ज्येष्ठ संतान का ज्येष्ठ मास — विवाह में दोनों ज्येष्ठ हों तो वर्जित, एक हो तो सावधानी (मत)
            if ($firstBorn !== [] && !empty($t['jyeshtha']) && !$m['adhik'] && $m['pm'] === 2) {
                $txt = implode(' व ', $firstBorn) . ' ज्येष्ठ संतान — ज्येष्ठ मास';
                if ($t['jyeshtha'] === 'pair' && count($firstBorn) < 2) { $add('व्यक्तिगत', 'ज्येष्ठ मास (एक ज्येष्ठ संतान)', $txt . ' — एक के ज्येष्ठ होने पर सावधानी (मत)', 'warn', -8); }
                else { $add('व्यक्तिगत', 'ज्येष्ठ मास में ज्येष्ठ संतान', $txt . ' में वर्जित', 'bad'); }
            }
        }
        // चंद्र ऑपरेशन वाले अंग की राशि में न हो (9.03 — पाश्चात्य मूल का नियम)
        $ang = (string) ($ctx['opts']['ang'] ?? '');
        if ($ang !== '' && isset(KaryaVyakti::ANG_SIGN[$ang])) {
            $as2 = KaryaVyakti::ANG_SIGN[$ang];
            if ($f['moonSign'] === $as2) { $add('व्यक्तिगत', 'चंद्र अंग की राशि में', 'चंद्र ' . self::signHi($as2) . ' (उसी अंग की राशि) में — इस अंग का ऑपरेशन टालें (पाश्चात्य मत)', 'warn', -10); $cap[] = 'चंद्र ऑपरेशन वाले अंग की राशि में'; }
            elseif ($verbose) { $add('व्यक्तिगत', 'अंग-राशि', 'चंद्र ' . self::signHi($f['moonSign']) . ' में — अंग (' . self::signHi($as2) . ') से भिन्न', 'ok'); }
        }

        // ═════ 10a. स्थिति के उत्तर (विवाह-तिथि, गर्भ-मास, ऋतुकाल, गर्भवती …) ═════
        $dn = static fn (int $y, int $mo, int $d): int => (int) round(JulianDay::fromGregorian($y, $mo, $d, 12, 0, 0.0, 0.0));
        $today = $dn($day['y'], $day['m'], $day['d']);
        if (isset($X['ritu'])) {   // गर्भाधान — ऋतुकाल की रात्रि (दिन के बाद की रात उसी दिन की गिनी जाती है)
            $k = $today - $dn($X['ritu']['y'], $X['ritu']['m'], $X['ritu']['d']) + 1;
            if ($k < 1) { $add('व्यक्तिगत', 'ऋतुकाल से पहले', 'दी गई ऋतु-तिथि से पहले', 'bad'); }
            elseif ($k <= 3) { $add('व्यक्तिगत', 'ऋतु की ' . self::ordF($k) . ' रात्रि', 'प्रथम 3 रात्रियाँ वर्जित', 'bad'); }
            elseif ($k === 11 || $k === 13) { $add('व्यक्तिगत', 'ऋतु की ' . self::ordF($k) . ' रात्रि', '11वीं व 13वीं रात्रि वर्जित', 'bad'); }
            elseif ($k > 16) { $add('व्यक्तिगत', 'ऋतुकाल बीता', self::ordF($k) . ' रात्रि — 16 रात्रि का ऋतुकाल समाप्त (अगला चक्र)', 'bad'); }
            elseif ($k === 4) { $add('व्यक्तिगत', 'ऋतु की 4थी रात्रि', 'पुस्तिका में ग्राह्य; मनु-मत में प्रथम चार निषिद्ध', 'warn', -4); }
            else { $add('व्यक्तिगत', 'ऋतुकाल की ' . self::ordF($k) . ' रात्रि', 'विहित (4–16, 11वीं-13वीं छोड़कर)', 'good', 6); }
        }
        if (isset($X['lmp'])) {   // गोद भराई / पुंसवन / सीमन्त — गर्भ का मास
            $gm = (int) floor(($f['jd'] - $X['lmp']['jd']) / 30.4375) + 1;
            $kind = (string) ($ctx['opts']['garbh'] ?? 'godbharai');
            [$okM, $midM, $kn] = ['pumsavan' => [[2, 3], [], 'पुंसवन'], 'simanta' => [[6, 8], [4], 'सीमन्तोन्नयन'],
                'godbharai' => [[7], [5, 8, 9], 'गोद भराई (लोक)']][$kind] ?? [[7], [5, 8, 9], 'गोद भराई (लोक)'];
            if ($gm < 1 || $gm > 10) { $add('व्यक्तिगत', 'गर्भ-मास ' . $gm, 'दी गई LMP से यह तिथि गर्भ-काल में नहीं', 'bad'); }
            elseif (in_array($gm, $okM, true)) { $add('व्यक्तिगत', 'गर्भ का ' . self::ord($gm) . ' मास', $kn . ' हेतु विहित', 'good', 8); }
            elseif (in_array($gm, $midM, true)) { $add('व्यक्तिगत', 'गर्भ का ' . self::ord($gm) . ' मास', $kn . ' — मत/लोक-भेद से ग्राह्य (श्रेष्ठ ' . implode('/', $okM) . 'वाँ)', 'warn', -4); $cap[] = 'गर्भ-मास श्रेष्ठ नहीं'; }
            else { $add('व्यक्तिगत', 'गर्भ-मास अविहित: ' . self::ord($gm) . '', $kn . ' ' . implode('/', $okM) . 'वें मास में', 'bad'); }
        }
        if (isset($X['vivah'])) {   // द्विरागमन — विवाह से विषम वर्ष
            if ($f['jd'] < $X['vivah']['jd']) { $add('व्यक्तिगत', 'विवाह से पहले', 'द्विरागमन विवाह के बाद ही', 'bad'); }
            else {
                $yr = (int) floor(($f['jd'] - $X['vivah']['jd']) / 365.2425) + 1;
                if ($yr % 2 === 1) { $add('व्यक्तिगत', 'विवाह से ' . self::ord($yr) . ' वर्ष', 'विषम वर्ष — द्विरागमन विहित', 'good', 6); }
                else { $add('व्यक्तिगत', 'विवाह से ' . self::ord($yr) . ' वर्ष (सम)', 'द्विरागमन विषम (1/3/5वें) वर्ष में', 'bad'); }
            }
        }
        if (!empty($X['mpreg'])) {   // मुंडन — माता 5 मास+ गर्भवती, बालक 5 वर्ष से छोटा
            $child = null;
            foreach ($persons as $P) { if ($P['role'] === 'child') { $child = $P; } }
            $young = $child === null || $child['birthJd'] === null || ($f['jd'] - $child['birthJd']) < 5 * 365.2425;
            if ($young) { $add('व्यक्तिगत', 'माता गर्भवती', '5 वर्ष से छोटे बालक का मुंडन माता के 5 मास से अधिक गर्भ में वर्जित' . ($child === null || $child['birthJd'] === null ? ' (बालक की आयु अज्ञात)' : ''), 'bad'); }
            elseif ($verbose) { $add('व्यक्तिगत', 'माता गर्भवती', 'बालक 5 वर्ष से बड़ा — दोष नहीं', 'ok'); }
        }
        if (!empty($X['wpreg'])) {
            $add('व्यक्तिगत', 'गृहस्वामिनी गर्भवती', 'नींव/गृहारम्भ इस काल में टालें (लोक-मत)', 'warn', -8);
            $cap[] = 'गृहस्वामिनी गर्भवती (लोक-मत)';
        }
        if (isset($X['death'])) {   // अंतिम संस्कार — मृत्यु से दिन (अस्थि-संचय 3/4/7, विसर्जन 10 दिन में)
            $dd = $today - $dn($X['death']['y'], $X['death']['m'], $X['death']['d']) + 1;
            if ($dd < 1) { $add('व्यक्तिगत', 'मृत्यु से पहले', 'दी गई मृत्यु-तिथि से पहले', 'bad'); }
            elseif (in_array($dd, [3, 4, 7], true)) { $add('व्यक्तिगत', 'मृत्यु से ' . self::ord($dd) . ' दिन', 'अस्थि-संचय हेतु विहित', 'good', 6); }
            elseif ($dd <= 10) { $add('व्यक्तिगत', 'मृत्यु से ' . self::ord($dd) . ' दिन', 'अस्थि-विसर्जन 10 दिन के भीतर', 'info'); }
            else { $add('व्यक्तिगत', 'मृत्यु से ' . self::ord($dd) . ' दिन', '10 दिन बीते — विसर्जन शीघ्र', 'info'); }
        }

        // ═════ 11. अबूझ ═════
        if ($abujh !== null) {
            $add('पर्व', '🌟 अबूझ: ' . $abujh, 'पंचांग-शुद्धि शिथिल (लोक) — मास/अस्त दोष फिर भी देखें', 'good', 0);
            if (!$hard) { $score = max($score, 92); }
        }

        // "शुभ" तभी जब पुस्तिका में इस कार्य के लिए गिनाए हर पंचांग-अंग (तिथि/वार/नक्षत्र)
        // की शुभ सूची पूरी हो; कोई अंग सूची से बाहर हो तो क्षण चलने योग्य है पर "मध्यम"।
        // (वरना हल्के नियम वाले कार्यों में साल का हर दिन "शुभ" निकलता — खोज बेकार।)
        if (!$hard && $abujh === null && !$fixedDay && array_intersect($warn, self::LIMB_MISS) !== []) {
            $score = min($score, self::SHUBH - 1);
        }
        // व्यक्ति/स्थिति की कमी (गुरु/सूर्य बल हीन, वाम रवि नहीं …) — अबूझ दिन पर भी
        if (!$hard && $cap !== []) {
            $score = min($score, self::SHUBH - 1);
        }
        $raw = $score;
        $score = max(0, min(100, $score));
        $grade = $hard ? 'अशुभ' : ($score >= self::SHUBH ? 'शुभ' : ($score >= self::MADHYAM ? 'मध्यम' : 'अशुभ'));
        return [
            'hard' => $hard, 'lagnaOnly' => $hard && !$hardOther, 'raw' => $raw,
            'score' => $hard ? min($score, 30) : $score, 'grade' => $grade,
            'tone' => $grade === 'शुभ' ? 'pos' : ($grade === 'मध्यम' ? 'info' : 'neg'),
            'bad' => $bad, 'warn' => $warn, 'good' => $good, 'abujh' => $abujh, 'checks' => $checks, 'cap' => $cap,
        ];
    }

    /** ctx से व्यक्ति — नया रूप persons (KaryaVyakti), पुराना natal + personal (एक जातक)। */
    private static function persons(array $ctx): array
    {
        if (isset($ctx['persons'])) { return $ctx['persons']; }
        $n = !empty($ctx['personal']) ? ($ctx['natal'] ?? null) : null;
        if ($n === null) { return []; }
        return [$n + ['role' => 'self', 'short' => 'जातक', 'first' => false, 'src' => 'birth', 'pada' => 0, 'birthMd' => null]];
    }

    /**
     * शिशु-संस्कारों के आयु-नियम (पुस्तिका का "काल" स्तम्भ)। आयु: जन्म का दिन = 1ला दिन,
     * पहले 30.44 दिन = 1ला मास, पहले 365.24 दिन = 1ला वर्ष; उपनयन का वर्ष गर्भ से (जन्म − 280 दिन)।
     */
    private static function ageRule(string $kind, array $P, array $f, array $ctx, callable $add, array &$cap, bool &$fixed): void
    {
        if ($P['birthJd'] === null) {
            $add('व्यक्तिगत', 'आयु-नियम', 'शिशु की जन्म-तिथि नहीं दी — नियत दिन/मास/वर्ष नहीं जाँचे', 'info');
            return;
        }
        $days = $f['jd'] - $P['birthJd'];
        if ($days < 0) { $add('व्यक्तिगत', 'जन्म से पहले', 'यह क्षण शिशु के जन्म से पहले का है', 'bad'); return; }
        $d = (int) floor($days) + 1;
        $mo = (int) floor($days / 30.4375) + 1;
        $y = (int) floor($days / 365.2425) + 1;
        $g = (string) $P['gender'];
        switch ($kind) {
            case 'namkaran':
                if ($d <= 10) { $add('व्यक्तिगत', 'सूतक काल', 'जन्म से ' . self::ord($d) . ' दिन — 10 दिन के सूतक में नामकरण नहीं', 'bad'); }
                elseif ($d === 11 || $d === 12) { $add('व्यक्तिगत', 'जन्म से ' . self::ord($d) . ' दिन', 'नियत दिन — तिथि-वार विचार शिथिल', 'good', 15); $fixed = true; }
                elseif (in_array($d, [13, 16, 19, 22], true)) { $add('व्यक्तिगत', 'जन्म से ' . self::ord($d) . ' दिन', 'कुल-परंपरा का नियत दिन', 'good', 8); }
                elseif ($mo === 3) { $add('व्यक्तिगत', '3रा मास', 'नियत दिन बीतने पर तीसरा मास विहित', 'good', 4); }
                elseif ($mo < 3) { $add('व्यक्तिगत', 'जन्म से ' . self::ord($d) . ' दिन', 'नियत दिन (11, 12, 13, 16, 19, 22) नहीं — अगला विहित: 3रा मास', 'warn', -6); $cap[] = 'नामकरण का नियत दिन नहीं'; }
                else { $add('व्यक्तिगत', 'नियत समय बीता', self::ord($mo) . ' मास — शुभ दिन पर करें', 'warn', -4); }
                break;
            case 'nishkraman':
                if ($d < 12) { $add('व्यक्तिगत', 'जन्म से ' . self::ord($d) . ' दिन', '12वें दिन से पहले घर से बाहर नहीं', 'bad'); }
                elseif ($mo === 4) { $add('व्यक्तिगत', '4था मास', 'निष्क्रमण का विहित मास', 'good', 8); }
                elseif ($d === 12 || $mo === 3) { $add('व्यक्तिगत', $d === 12 ? '12वाँ दिन' : '3रा मास', 'सूर्य-दर्शन — ग्राह्य', 'ok'); }
                elseif ($mo < 4) { $add('व्यक्तिगत', self::ord($mo) . ' मास', 'निष्क्रमण 4थे मास में (सूर्य-दर्शन 12वें दिन/3रे मास)', 'warn', -6); $cap[] = 'निष्क्रमण का मास नहीं'; }
                else { $add('व्यक्तिगत', self::ord($mo) . ' मास', 'विहित मास (4था) बीता — शुभ दिन पर', 'warn', -4); }
                break;
            case 'annaprashan':
                if ($mo < 5) { $add('व्यक्तिगत', self::ord($mo) . ' मास', 'अन्नप्राशन 5वें/6ठे मास से पहले नहीं', 'bad'); break; }
                if ($g === '') { $add('व्यक्तिगत', 'बालक या बालिका?', 'बालक का सम (6/8) और बालिका का विषम (5/7) मास — लिंग चुनें', 'warn', -6); $cap[] = 'शिशु का लिंग नहीं चुना'; break; }
                $even = $g === 'm';
                if (($mo % 2 === 0) === $even) {
                    if ($mo <= 12) { $add('व्यक्तिगत', self::ord($mo) . ' मास', ($even ? 'बालक: सम' : 'बालिका: विषम') . ' मास — विहित', 'good', 8); }
                    else { $add('व्यक्तिगत', self::ord($mo) . ' मास', 'विलम्ब, पर उचित ' . ($even ? 'सम' : 'विषम') . ' मास', 'ok'); }
                } else {
                    $add('व्यक्तिगत', self::ord($mo) . ' मास ' . ($even ? '(विषम)' : '(सम)'), ($even ? 'बालक का अन्नप्राशन सम (6/8/10/12)' : 'बालिका का विषम (5/7/9/11)') . ' मास में', 'bad');
                }
                break;
            case 'mundan':
                if ($y % 2 === 1) { $add('व्यक्तिगत', self::ord($y) . ' वर्ष (विषम)', 'मुंडन विहित', $y <= 7 ? 'good' : 'ok', $y <= 7 ? 6 : 0); }
                else { $add('व्यक्तिगत', self::ord($y) . ' वर्ष (सम)', 'मुंडन विषम वर्ष (1/3/5/7) में', 'bad'); }
                break;
            case 'karnavedh':
                if ($d < 12) { $add('व्यक्तिगत', 'जन्म से ' . self::ord($d) . ' दिन', '12वें दिन से पहले नहीं', 'bad'); }
                elseif ($d === 12 || $d === 16) { $add('व्यक्तिगत', 'जन्म से ' . self::ord($d) . ' दिन', 'कर्णवेध हेतु विहित', 'good', 8); }
                elseif ($y === 1 && in_array($mo, [6, 7, 8], true)) { $add('व्यक्तिगत', self::ord($mo) . ' मास', 'कर्णवेध हेतु विहित', 'good', 8); }
                elseif (in_array($y, [3, 5], true)) { $add('व्यक्तिगत', self::ord($y) . ' वर्ष', 'कर्णवेध हेतु विहित', 'good', 6); }
                elseif ($y > 5 && $y % 2 === 1) { $add('व्यक्तिगत', self::ord($y) . ' वर्ष (विषम)', 'ग्राह्य', 'ok'); }
                else { $add('व्यक्तिगत', 'आयु: ' . ($y === 1 ? self::ord($mo) . ' मास' : self::ord($y) . ' वर्ष'), 'विहित आयु नहीं (12/16वाँ दिन, 6/7/8वाँ मास, 3/5वाँ वर्ष)', 'warn', -6); $cap[] = 'कर्णवेध की विहित आयु नहीं'; }
                break;
            case 'vidyarambh':
                if ($y === 5) { $add('व्यक्तिगत', '5वाँ वर्ष', 'विद्यारम्भ हेतु विहित', 'good', 8); }
                elseif ($y === 3) { $add('व्यक्तिगत', '3रा वर्ष', 'विद्यारम्भ — कुछ मत में', 'ok'); }
                elseif ($y < 3) { $add('व्यक्तिगत', self::ord($y) . ' वर्ष', 'आयु कम — 5वाँ वर्ष श्रेष्ठ', 'warn', -6); }
                else { $add('व्यक्तिगत', self::ord($y) . ' वर्ष', 'बड़ी आयु — शुभ दिन पर्याप्त', 'info'); }
                break;
            case 'upanayan':
                $v = (string) ($ctx['opts']['varna'] ?? 'brahmin');
                $R = ['brahmin' => [8, 5, 16, 'ब्राह्मण'], 'kshatriya' => [11, 6, 22, 'क्षत्रिय'], 'vaishya' => [12, 8, 24, 'वैश्य']][$v] ?? null;
                if ($R === null) { $add('व्यक्तिगत', 'आयु-नियम', 'वर्ण-आयु परंपरा नहीं देखी', 'info'); break; }
                [$ideal, $kamya, $max, $vn] = $R;
                $gy = (int) floor(($f['jd'] - ($P['birthJd'] - 280.0)) / 365.2425) + 1;
                if ($gy === $ideal) { $add('व्यक्तिगत', 'गर्भ से ' . self::ord($gy) . ' वर्ष', $vn . ' हेतु विहित (मनु 2.36)', 'good', 10); }
                elseif ($gy === $kamya) { $add('व्यक्तिगत', 'गर्भ से ' . self::ord($gy) . ' वर्ष', 'काम्य उपनयन (मनु 2.37)', 'good', 6); }
                elseif ($gy < $kamya) { $add('व्यक्तिगत', 'गर्भ से ' . self::ord($gy) . ' वर्ष', 'आयु कम — ' . $vn . ' हेतु ' . $kamya . '/' . self::ord($ideal) . ' वर्ष', 'bad'); }
                elseif ($gy <= $max) { $add('व्यक्तिगत', 'गर्भ से ' . self::ord($gy) . ' वर्ष', 'विहित सीमा (' . $max . ' वर्ष) के भीतर; आदर्श ' . self::ord($ideal) . '', 'warn', -2); }
                else { $add('व्यक्तिगत', 'गर्भ से ' . self::ord($gy) . ' वर्ष', 'सावित्री-काल (' . $max . ') बीता — प्रायश्चित्त सहित (मत)', 'warn', -8); }
                break;
        }
    }
}
