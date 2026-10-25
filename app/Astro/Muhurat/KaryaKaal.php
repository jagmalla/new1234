<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

use AutoBusiness\Astro\Calc\CalculationEngine;

/**
 * ⏱️ मुहूर्त का आकाश व समय — एक स्थान व तिथि-सीमा के लिए।
 *
 * तिथि-खोज हर दिन को सूर्योदय से अगले सूर्योदय तक देखती है (वार सूर्योदय से
 * बदलता है)। उस अवधि में तिथि, नक्षत्र, योग, करण, लग्न जिस-जिस क्षण बदलते हैं
 * वे सटीक (सेकंडों तक) निकाले जाते हैं; फिर दिन के सारे काल — राहुकाल, यमगण्ड,
 * गुलिक, दुर्मुहूर्त, अभिजित, चौघड़िया, होरा, प्रदोष, भद्रा का मुख-पुच्छ, अशुभ
 * योगों की त्याज्य घटियाँ, पक्षरन्ध्र, विष घटी (वर्ज्यम्), गण्डान्त, संक्रान्ति के
 * ±16 घटी, ग्रहण-सूतक — और इन सब के किनारों से दिन टुकड़ों (segments) में बँटता
 * है। हर टुकड़े के भीतर सब कुछ स्थिर है, इसलिए हर टुकड़े पर एक बार नियम लगाकर
 * शुभ समय-खिड़कियाँ निकलती हैं।
 *
 * ग्रह-स्थिति आधे-आधे दिन के जाल पर ephemeris से, बीच में घन (cubic) अन्तर्वेशन
 * से — चंद्र के लिए भी त्रुटि एक कला से कम। मास (पूर्णिमान्त/अमान्त, अधिक मास)
 * अमावस्याओं से; ग्रहण पूर्णिमा/अमावस्या पर चंद्र-शर (latitude) से।
 */
final class KaryaKaal
{
    public const NAK = 40.0 / 3.0;
    public const GHATI = 1.0 / 60.0;                // 24 मिनट (दिन में)
    private const STEP = 0.5;                       // जाल (दिन)
    private const SCAN = 1.0 / 144.0;               // 10 मिनट
    public const PLANETS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];
    public const HI = ['Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु'];
    /** होरा-क्रम (सूर्य→शुक्र→बुध→चंद्र→शनि→गुरु→मंगल)। */
    private const HORA_ORDER = ['Sun', 'Venus', 'Mercury', 'Moon', 'Saturn', 'Jupiter', 'Mars'];
    /** वार (1 रवि…7 शनि) का स्वामी। */
    public const VAAR_LORD = [1 => 'Sun', 2 => 'Moon', 3 => 'Mars', 4 => 'Mercury', 5 => 'Jupiter', 6 => 'Venus', 7 => 'Saturn'];
    /** दुर्मुहूर्त (दिन के 15 मुहूर्तों में; मंगल को रात्रि का 7वाँ भी) — वार शीट। */
    private const DURMUHURT = [1 => [[14], []], 2 => [[9, 12], []], 3 => [[4], [7]], 4 => [[8], []],
        5 => [[6, 12], []], 6 => [[4, 9], []], 7 => [[1, 2], []]];
    /** अस्त-सीमा (अंश) — [मार्गी, वक्री]। */
    private const ASTA = ['Mars' => [17, 17], 'Mercury' => [14, 12], 'Jupiter' => [11, 11],
        'Venus' => [10, 8], 'Saturn' => [15, 15]];
    /** अशुभ नित्य-योगों की आरम्भिक त्याज्य घटियाँ (योग शीट); 'half' = पूर्वार्ध, 'all' = सम्पूर्ण। */
    private const YOGA_TYAJYA = [1 => 3, 6 => 6, 9 => 5, 10 => 6, 13 => 9, 15 => 3, 19 => 'half', 17 => 'all', 27 => 'all'];
    /** पक्षरन्ध्र — पक्ष-तिथि => त्याज्य आरम्भिक घटी। */
    private const RANDHRA = [4 => 8, 6 => 9, 8 => 14, 9 => 25, 12 => 10, 14 => 5];

    /** @var array<int, array<string, float>> */
    private array $grid = [];
    private float $g0;
    /** @var list<array{0: float, 1: int}> अमावस्या [jd, सूर्य-राशि] */
    private array $nm = [];
    /** @var list<array{jd: float, type: string, nak: int, lat: float}> */
    private array $eclipses = [];
    /** @var array<int, array<string, bool>> */
    private array $astaCache = [];

    public function __construct(
        private readonly CalculationEngine $eng,
        public readonly float $lat,
        public readonly float $lon,
        private readonly ?\DateTimeZone $zone,
        private readonly float $tzFixed,
        float $jdFrom,
        float $jdTo
    ) {
        $this->g0 = floor($jdFrom) - 3.0;
        $this->buildLunations($jdFrom - 200.0, $jdTo + 45.0);
    }

    // ───────────────────────────── ग्रह-स्थिति ─────────────────────────────

    private function point(int $k): array
    {
        if (!isset($this->grid[$k])) {
            $p = $this->eng->siderealPositions($this->g0 + $k * self::STEP);
            $row = [];
            foreach (self::PLANETS as $pl) { $row[$pl] = (float) ($p[$pl]['lon'] ?? 0.0); }
            $row['moonLat'] = (float) ($p['Moon']['lat'] ?? 0.0);
            $this->grid[$k] = $row;
        }
        return $this->grid[$k];
    }

    /**
     * अन्तर्वेशित निरयन देशान्तर (और चंद्र-शर)।
     * @param list<string>|null $only केवल ये ग्रह (गति हेतु)
     * @return array<string, float>
     */
    public function sky(float $jd, ?array $only = null): array
    {
        $x = ($jd - $this->g0) / self::STEP;
        $k = (int) floor($x);
        $t = $x - $k;
        $w = [-$t * ($t - 1) * ($t - 2) / 6.0, ($t + 1) * ($t - 1) * ($t - 2) / 2.0,
            -($t + 1) * $t * ($t - 2) / 2.0, ($t + 1) * $t * ($t - 1) / 6.0];
        $pts = [$this->point($k - 1), $this->point($k), $this->point($k + 1), $this->point($k + 2)];
        $out = [];
        foreach ($only ?? self::PLANETS as $pl) {
            $base = $pts[1][$pl];
            $v = $base;
            for ($i = 0; $i < 4; $i++) { $v += $w[$i] * self::wrap180($pts[$i][$pl] - $base); }
            $out[$pl] = self::norm($v);
        }
        if ($only === null) {
            $v = 0.0;
            for ($i = 0; $i < 4; $i++) { $v += $w[$i] * $pts[$i]['moonLat']; }
            $out['moonLat'] = $v;
        }
        return $out;
    }

    public function asc(float $jd): float
    {
        return $this->eng->ascendantSidereal($jd, $this->lat, $this->lon);
    }

    // ───────────────────────────── पंचांग-अंग ─────────────────────────────

    /** @return array{tithi:int, karana:int, nak:int, yoga:int, moonSign:int, sunSign:int, elong:float, sun:float, moon:float} */
    public function panch(float $jd): array
    {
        $s = $this->sky($jd, ['Sun', 'Moon']);
        $el = self::norm($s['Moon'] - $s['Sun']);
        return [
            'tithi' => min(30, (int) floor($el / 12.0) + 1),
            'karana' => min(59, (int) floor($el / 6.0)),
            'nak' => min(27, (int) floor($s['Moon'] / self::NAK) + 1),
            'yoga' => min(27, (int) floor(self::norm($s['Sun'] + $s['Moon']) / self::NAK) + 1),
            'moonSign' => (int) floor($s['Moon'] / 30.0) % 12,
            'sunSign' => (int) floor($s['Sun'] / 30.0) % 12,
            'elong' => $el, 'sun' => $s['Sun'], 'moon' => $s['Moon'],
        ];
    }

    /** करण-क्रमांक (0–59) → [नाम, विष्टि?] */
    public static function karanaName(int $k): array
    {
        static $mov = ['बव', 'बालव', 'कौलव', 'तैतिल', 'गर', 'वणिज', 'विष्टि (भद्रा)'];
        if ($k === 0) { return ['किंस्तुघ्न', false]; }
        if ($k >= 57) { return [[57 => 'शकुनि', 58 => 'चतुष्पद', 59 => 'नाग'][$k], false]; }
        $i = ($k - 1) % 7;
        return [$mov[$i], $i === 6];
    }

    /**
     * कई अंगों के अन्तराल एक साथ — [a, b] पर 10-मिनट के नमूने एक बार, फिर हर अंग के
     * बदलाव का क्षण अलग से (≈1 सेकंड); पहले अन्तराल का आरम्भ व अन्तिम का अन्त
     * सीमा के बाहर तक खोजे जाते हैं (पक्षरन्ध्र, विष घटी, त्याज्य घटी के लिए ज़रूरी)।
     *
     * @param array<string, array{0: callable(array): int, 1: float}> $keys नाम => [panch से मान, अधिकतम अवधि (दिन)]
     * @return array<string, list<array{0: int, 1: float, 2: float}>>
     */
    private function multiIntervals(array $keys, float $a, float $b): array
    {
        $samples = [];
        for ($t = $a; ; $t += self::SCAN) {
            if ($t > $b) { $t = $b; }
            $samples[] = [$t, $this->panch($t)];
            if ($t >= $b) { break; }
        }
        $out = [];
        foreach ($keys as $name => [$f, $span]) {
            $key = fn (float $t): int => $f($this->panch($t));
            $list = [];
            $cur = $f($samples[0][1]);
            $curStart = $this->firstTrue($key, $a - $span, $a, $cur);
            for ($i = 1, $n = count($samples); $i < $n; $i++) {
                $v = $f($samples[$i][1]);
                if ($v !== $cur) {
                    $x = $this->firstTrue($key, $samples[$i - 1][0], $samples[$i][0], $v);
                    $list[] = [$cur, $curStart, $x];
                    $cur = $v;
                    $curStart = $x;
                }
            }
            $list[] = [$cur, $curStart, $this->lastTrue($key, $b, $b + $span, $cur)];
            $out[$name] = $list;
        }
        return $out;
    }

    /** [lo, hi] में पहला क्षण जब key == v (उसके बाद hi तक v ही) — ≈1 सेकंड। */
    private function firstTrue(callable $key, float $lo, float $hi, int $v): float
    {
        if ($key($lo) === $v) { return $lo; }
        for ($i = 0; $i < 22 && $hi - $lo > 1e-5; $i++) {
            $m = ($lo + $hi) / 2;
            if ($key($m) === $v) { $hi = $m; } else { $lo = $m; }
        }
        return $hi;
    }

    /** [lo, hi] में अन्तिम क्षण जब key == v। */
    private function lastTrue(callable $key, float $lo, float $hi, int $v): float
    {
        if ($key($hi) === $v) { return $hi; }
        for ($i = 0; $i < 22 && $hi - $lo > 1e-5; $i++) {
            $m = ($lo + $hi) / 2;
            if ($key($m) === $v) { $lo = $m; } else { $hi = $m; }
        }
        return $lo;
    }

    /** लग्न-राशि के अन्तराल (दिन के भीतर) — 10-मिनट नमूने + द्विभाजन। */
    private function ascIntervals(float $a, float $b): array
    {
        $key = fn (float $t): int => (int) floor($this->asc($t) / 30.0) % 12;
        $list = [];
        $prevT = $a;
        $cur = $key($a);
        $curStart = $a;
        for ($t = $a + self::SCAN; ; $t += self::SCAN) {
            if ($t > $b) { $t = $b; }
            $v = $key($t);
            if ($v !== $cur) {
                $x = $this->firstTrue($key, $prevT, $t, $v);
                $list[] = [$cur, $curStart, $x];
                $cur = $v;
                $curStart = $x;
            }
            $prevT = $t;
            if ($t >= $b) { break; }
        }
        $list[] = [$cur, $curStart, $b];
        return $list;
    }

    // ───────────────────────────── मास, ग्रहण, अस्त ─────────────────────────────

    private function buildLunations(float $a, float $b): void
    {
        $p = $this->eng->siderealPositions($a);
        $el = self::norm($p['Moon']['lon'] - $p['Sun']['lon']);
        $jd = $this->refine($a - $el / 12.19, 0.0);
        while ($jd < $b + 30.0) {
            $q = $this->eng->siderealPositions($jd);
            $this->nm[] = [$jd, (int) floor($q['Sun']['lon'] / 30.0) % 12];
            if (abs($q['Moon']['lat']) < 1.45) {
                $this->eclipses[] = ['jd' => $jd, 'type' => 'surya', 'nak' => (int) floor($q['Moon']['lon'] / self::NAK) + 1, 'lat' => $q['Moon']['lat']];
            }
            $fm = $this->refine($jd + 14.77, 180.0);
            $f = $this->eng->siderealPositions($fm);
            if (abs($f['Moon']['lat']) < 1.0) {
                $this->eclipses[] = ['jd' => $fm, 'type' => 'chandra', 'nak' => (int) floor($f['Moon']['lon'] / self::NAK) + 1, 'lat' => $f['Moon']['lat']];
            }
            $jd = $this->refine($jd + 29.53, 0.0);
        }
    }

    private function refine(float $jd, float $target): float
    {
        for ($i = 0; $i < 7; $i++) {
            $p = $this->eng->siderealPositions($jd);
            $e = self::wrap180($p['Moon']['lon'] - $p['Sun']['lon'] - $target);
            $rate = (float) $p['Moon']['speed'] - (float) $p['Sun']['speed'];
            if ($rate < 9.0 || $rate > 17.0) { $rate = 12.19; }
            $d = $e / $rate;
            $jd -= $d;
            if (abs($d) < 2e-5) { break; }
        }
        return $jd;
    }

    /**
     * चान्द्र मास — am अमान्त (0 चैत्र…), pm पूर्णिमान्त, adhik।
     * @return array{am: int, pm: int, adhik: bool}
     */
    public function maas(float $jd, int $tithi): array
    {
        $n = count($this->nm);
        for ($i = $n - 2; $i >= 0; $i--) {
            if ($this->nm[$i][0] <= $jd) {
                $am = ($this->nm[$i][1] + 1) % 12;
                $adhik = $this->nm[$i][1] === $this->nm[$i + 1][1];
                return ['am' => $am, 'pm' => $tithi > 15 ? ($am + 1) % 12 : $am, 'adhik' => $adhik];
            }
        }
        return ['am' => 0, 'pm' => 0, 'adhik' => false];
    }

    /** @return list<array{jd: float, type: string, nak: int, lat: float}> */
    public function eclipses(): array
    {
        return $this->eclipses;
    }

    /** दिन (दोपहर) पर ग्रहों का अस्त होना। @return array<string, bool> */
    public function asta(float $jdNoon): array
    {
        $key = (int) floor($jdNoon);
        if (!isset($this->astaCache[$key])) {
            $p = $this->eng->siderealPositions($jdNoon);
            $out = [];
            foreach (self::ASTA as $pl => [$lim, $limR]) {
                $d = abs(self::wrap180($p[$pl]['lon'] - $p['Sun']['lon']));
                $out[$pl] = $d < ($p[$pl]['retro'] ? $limR : $lim);
            }
            $out['Moon'] = abs(self::wrap180($p['Moon']['lon'] - $p['Sun']['lon'])) < 12.0;
            $out['_retro'] = ['Mercury' => (bool) $p['Mercury']['retro'], 'Venus' => (bool) $p['Venus']['retro']];
            $out['_venusEast'] = self::wrap180($p['Venus']['lon'] - $p['Sun']['lon']) < 0;   // प्रातः तारा = पूर्व में
            $this->astaCache[$key] = $out;
        }
        return $this->astaCache[$key];
    }

    /** गुरु/शुक्र बाल-वृद्ध — अस्त से पहले/बाद के N दिन। @return array<string, string|null> */
    public function balVriddha(float $jdNoon): array
    {
        $out = [];
        foreach (['Jupiter' => 15, 'Venus' => 7] as $pl => $n) {
            $out[$pl] = null;
            if ($this->asta($jdNoon)[$pl]) { continue; }
            for ($i = 1; $i <= $n; $i++) {
                if ($this->asta($jdNoon - $i)[$pl]) { $out[$pl] = 'bal'; break; }
                if ($this->asta($jdNoon + $i)[$pl]) { $out[$pl] = 'vriddha'; break; }
            }
        }
        return $out;
    }

    public function tzFor(int $y, int $m, int $d): float
    {
        if ($this->zone === null) { return $this->tzFixed; }
        $dt = new \DateTimeImmutable(sprintf('%04d-%02d-%02d 12:00:00', $y, $m, $d), $this->zone);
        return $this->zone->getOffset($dt) / 3600.0;
    }

    // ───────────────────────────── एक दिन ─────────────────────────────

    /**
     * एक दिन (सूर्योदय → अगला सूर्योदय) के सारे अंग, काल व खिड़कियाँ।
     * @param array<string, bool> $need कौन-से वैकल्पिक कट चाहिए: hora, chogh, ritu
     */
    public function day(int $y, int $m, int $d, array $need = []): array
    {
        $tz = $this->tzFor($y, $m, $d);
        $st = SunTimes::forDate($y, $m, $d, $this->lat, $this->lon, $tz);
        $n = (new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $d)))->modify('+1 day');
        $ny = (int) $n->format('Y'); $nm = (int) $n->format('n'); $nd = (int) $n->format('j');
        $st2 = SunTimes::forDate($ny, $nm, $nd, $this->lat, $this->lon, $this->tzFor($ny, $nm, $nd));
        $rise = $st['rise']; $set = $st['set']; $next = $st2['rise'];
        $vaar = (int) date('w', (int) gmmktime(12, 0, 0, $m, $d, $y)) + 1;
        $dayLen = $set - $rise; $nightLen = $next - $set;

        $iv = $this->multiIntervals([
            'tithi' => [static fn (array $p): int => $p['tithi'], 1.3],
            'nak' => [static fn (array $p): int => $p['nak'], 1.3],
            'yoga' => [static fn (array $p): int => $p['yoga'], 1.3],
            'karana' => [static fn (array $p): int => $p['karana'], 0.7],
            'moonSign' => [static fn (array $p): int => $p['moonSign'], 2.8],
        ], $rise, $next);
        $iv['asc'] = $this->ascIntervals($rise, $next);
        // सूर्य-संक्रान्ति — दिन के ±16 घटी तक असर, इसलिए सीमा से थोड़ा बाहर तक
        $sunK = fn (float $t): int => $this->panch($t)['sunSign'];
        $sunIv = [];
        $edgeA = $rise - 0.27; $edgeB = $next + 0.27;
        $sa = $sunK($edgeA); $sb = $sunK($edgeB);
        if ($sa !== $sb) { $sunIv[] = [$sb, $this->firstTrue($sunK, $edgeA, $edgeB, $sb)]; }

        $W = [];
        $add = static function (string $k, float $a, float $b, $tag = null) use (&$W): void {
            if ($b > $a) { $W[$k][] = [$a, $b, $tag]; }
        };
        $d8 = $dayLen / 8.0;
        $vrow = PanchangNiyamData::VAAR[$vaar];
        $add('rahukaal', $rise + ($vrow['rahu'] - 1) * $d8, $rise + $vrow['rahu'] * $d8);
        $add('yamaganda', $rise + ($vrow['yama'] - 1) * $d8, $rise + $vrow['yama'] * $d8);
        $add('gulika', $rise + ($vrow['gulika'] - 1) * $d8, $rise + $vrow['gulika'] * $d8);
        $dm = $dayLen / 15.0; $nmu = $nightLen / 15.0;
        foreach (self::DURMUHURT[$vaar][0] as $i) { $add('durmuhurt', $rise + ($i - 1) * $dm, $rise + $i * $dm); }
        foreach (self::DURMUHURT[$vaar][1] as $i) { $add('durmuhurt', $set + ($i - 1) * $nmu, $set + $i * $nmu); }
        $add('abhijit', $rise + 7 * $dm, $rise + 8 * $dm);
        $add('kutup', $rise + 7 * $dm, $rise + 8 * $dm);
        $mid = ($rise + $set) / 2.0;
        $add('purvahna', $rise, $mid);
        $add('aparahna', $mid, $set);
        $add('day', $rise, $set);
        $add('night', $set, $next);
        $add('pradosh', $set, $set + 1.5 / 24.0);
        $add('morning', $rise, $rise + $dayLen / 4.0);
        $add('evening', $set - 1.0 / 24.0, $set + 1.5 / 24.0);
        $add('brahma', $next - 2 * $nmu, $next - $nmu);
        $add('sandhya', $rise - self::GHATI, $rise + self::GHATI);
        $add('sandhya', $set - self::GHATI, $set + self::GHATI);
        for ($i = 0; $i < 6; $i++) { $add('ritu', $rise + $i * 4 * self::GHATI, $rise + ($i + 1) * 4 * self::GHATI, $i + 1); }

        // संक्रान्ति ±16 घटी
        $sankranti = [];
        foreach ($sunIv as [$sg, $tk]) {
            $sankranti[] = [$tk, $sg];
            $add('sankranti', $tk - 16 * self::GHATI, $tk + 16 * self::GHATI, $sg);
        }
        // भद्रा (विष्टि) — मुख आरम्भिक 5 घटी, पुच्छ अन्तिम 3 घटी
        foreach ($iv['karana'] as [$k, $a, $b]) {
            if (self::karanaName($k)[1]) {
                $add('bhadra', $a, $b);
                $add('bhadra_mukh', $a, min($b, $a + 5 * self::GHATI));
                $add('bhadra_puchh', max($a, $b - 3 * self::GHATI), $b);
            }
        }
        // अशुभ योग — त्याज्य भाग
        foreach ($iv['yoga'] as [$yg, $a, $b]) {
            $ty = self::YOGA_TYAJYA[$yg] ?? null;
            if ($ty === 'all') { $add('yoga_tyajya', $a, $b, $yg); }
            elseif ($ty === 'half') { $add('yoga_tyajya', $a, ($a + $b) / 2, $yg); }
            elseif (is_int($ty)) { $add('yoga_tyajya', $a, min($b, $a + $ty * self::GHATI), $yg); }
        }
        // पक्षरन्ध्र, क्षय/वृद्धि तिथि, तिथि-गण्डान्त
        foreach ($iv['tithi'] as [$ti, $a, $b]) {
            $tp = ($ti - 1) % 15 + 1;
            if (isset(self::RANDHRA[$tp]) && $ti !== 30) { $add('paksharandhra', $a, min($b, $a + self::RANDHRA[$tp] * self::GHATI), $tp); }
            if ($a > $rise && $b < $next) { $add('kshaya', $a, $b, $ti); }
            if ($a < $rise && $b > $next) { $add('vriddhi', $a, $b, $ti); }
            if (in_array($ti, [5, 10, 15, 20, 25, 30], true)) { $add('gandant_t', $b - self::GHATI, $b + self::GHATI, $ti); }
        }
        // नक्षत्र — विष घटी (वर्ज्यम्) व गण्डान्त
        foreach ($iv['nak'] as [$nk, $a, $b]) {
            $vish = (int) (PanchangNiyamData::NAK[$nk]['vish'] ?? 0);
            if ($vish > 0) {
                $dur = $b - $a;
                $vs = $a + $dur * $vish / 60.0;
                $add('varjyam', $vs, $vs + $dur * 4.0 / 60.0, $nk);
            }
            if (in_array($nk, [9, 18, 27], true)) { $add('gandant_n', $b - self::GHATI, $b + self::GHATI, $nk); }
        }
        // लग्न-गण्डान्त — कर्क/सिंह, वृश्चिक/धनु, मीन/मेष की संधि ±½°
        foreach ($iv['asc'] as $i => [$sg, $a, $b]) {
            if ($i > 0 && in_array($sg, [4, 8, 0], true)) {
                $rate = self::wrap180($this->asc($a + 1 / 1440) - $this->asc($a - 1 / 1440)) / (2 / 1440);
                $dt = $rate > 0 ? 0.5 / $rate : 3 / 1440;
                $add('gandant_l', $a - $dt, $a + $dt, $sg);
            }
        }
        // ग्रहण — सूतक (सूर्य 12 घंटे, चंद्र 9 घंटे पहले से, ~2 घंटे बाद तक)
        $eclDay = [];
        foreach ($this->eclipses as $e) {
            $before = $e['type'] === 'surya' ? 0.5 : 0.375;
            $add('sutak', $e['jd'] - $before, $e['jd'] + 2.0 / 24.0, $e['type']);
            $ed = $e['jd'] + $tz / 24.0;
            $delta = floor($ed + 0.5) - floor($rise + $tz / 24.0 + 0.5);
            if (abs($delta) <= 3) { $eclDay[] = $e + ['days' => (int) $delta]; }
        }

        // कट-बिन्दु → टुकड़े
        $cuts = [$rise, $next, $set];
        foreach ($iv as $list) {
            foreach ($list as [, $a, $b]) { $cuts[] = $a; $cuts[] = $b; }
        }
        foreach ($W as $k => $list) {
            if (($k === 'ritu' && empty($need['ritu']))) { continue; }
            foreach ($list as [$a, $b]) { $cuts[] = $a; $cuts[] = $b; }
        }
        if (!empty($need['hora'])) {
            for ($i = 1; $i < 12; $i++) { $cuts[] = $rise + $i * $dayLen / 12; $cuts[] = $set + $i * $nightLen / 12; }
        }
        if (!empty($need['chogh'])) {
            for ($i = 1; $i < 8; $i++) { $cuts[] = $rise + $i * $d8; $cuts[] = $set + $i * $nightLen / 8; }
        }
        $cuts = array_values(array_filter($cuts, static fn ($c) => $c >= $rise && $c <= $next));
        sort($cuts);
        $segs = [];
        $prev = null;
        foreach ($cuts as $c) {
            if ($prev !== null && $c - $prev > 30 / 86400) { $segs[] = [$prev, $c]; }
            if ($prev === null || $c - $prev > 30 / 86400) { $prev = $c; }
        }
        if ($prev !== null && $next - $prev > 30 / 86400) { $segs[] = [$prev, $next]; }

        $jdNoon = ($rise + $set) / 2.0;
        return [
            'y' => $y, 'm' => $m, 'd' => $d, 'date' => sprintf('%02d-%02d-%04d', $d, $m, $y), 'tz' => $tz,
            'vaar' => $vaar, 'rise' => $rise, 'set' => $set, 'next' => $next, 'polar' => $st['polar'],
            'dayLen' => $dayLen, 'nightLen' => $nightLen,
            'iv' => $iv, 'win' => $W, 'segs' => $segs, 'sankranti' => $sankranti,
            'asta' => $this->asta($jdNoon), 'bv' => $this->balVriddha($jdNoon), 'eclipseNear' => $eclDay,
        ];
    }

    /** किसी क्षण के सारे तथ्य (पंचांग + काल + ग्रह) — नियम इन्हीं पर लगते हैं। */
    public function facts(array $day, float $jd): array
    {
        $sky = $this->sky($jd);
        $el = self::norm($sky['Moon'] - $sky['Sun']);
        $tithi = min(30, (int) floor($el / 12.0) + 1);
        $k = min(59, (int) floor($el / 6.0));
        [$kName, $vishti] = self::karanaName($k);
        $moon = $sky['Moon'];
        $nak = min(27, (int) floor($moon / self::NAK) + 1);
        $nak28 = ($moon >= 276.0 + 2.0 / 3.0 && $moon < 280.0 + 8.0 / 9.0) ? 28 : $nak;   // अभिजित
        $asc = $this->asc($jd);
        $signs = [];
        foreach (self::PLANETS as $pl) { $signs[$pl] = (int) floor($sky[$pl] / 30.0) % 12; }
        $in = static function (string $key) use ($day, $jd) {
            foreach ($day['win'][$key] ?? [] as [$a, $b, $tag]) {
                if ($jd >= $a && $jd < $b) { return $tag ?? true; }
            }
            return false;
        };
        $isDay = $jd >= $day['rise'] && $jd < $day['set'];
        if ($isDay) {
            $p8 = (int) floor(($jd - $day['rise']) / ($day['dayLen'] / 8));
            $chogh = PanchangNiyamData::CHOGH_DAY[$day['vaar']][min(7, $p8)];
            $hIdx = (int) floor(($jd - $day['rise']) / ($day['dayLen'] / 12));
        } else {
            $p8 = (int) floor(($jd - $day['set']) / ($day['nightLen'] / 8));
            $chogh = PanchangNiyamData::CHOGH_NIGHT[$day['vaar']][max(0, min(7, $p8))];
            $hIdx = 12 + (int) floor(($jd - $day['set']) / ($day['nightLen'] / 12));
        }
        $start = array_search(self::VAAR_LORD[$day['vaar']], self::HORA_ORDER, true);
        $hora = self::HORA_ORDER[($start + max(0, min(23, $hIdx))) % 7];
        $maas = $this->maas($jd, $tithi);
        $sunSign = $signs['Sun'];
        return [
            'jd' => $jd, 'vaar' => $day['vaar'], 'tithi' => $tithi, 'tp' => ($tithi - 1) % 15 + 1,
            'paksha' => $tithi <= 15 ? 's' : 'k', 'karana' => $k, 'karanaName' => $kName, 'vishti' => $vishti,
            'nak' => $nak, 'nak28' => $nak28, 'pada' => (int) floor(fmod($moon, self::NAK) / (self::NAK / 4)) + 1,
            'yoga' => min(27, (int) floor(self::norm($sky['Sun'] + $moon) / self::NAK) + 1),
            'sunNak' => min(27, (int) floor($sky['Sun'] / self::NAK) + 1),
            'elong' => $el, 'moon' => $moon, 'sun' => $sky['Sun'], 'moonLat' => $sky['moonLat'],
            'moonSign' => $signs['Moon'], 'sunSign' => $sunSign, 'asc' => $asc, 'ascSign' => (int) floor($asc / 30.0) % 12,
            'lon' => $sky, 'signs' => $signs,
            'isDay' => $isDay, 'chogh' => $chogh, 'hora' => $hora,
            'maas' => $maas,
            'rahukaal' => (bool) $in('rahukaal'), 'yamaganda' => (bool) $in('yamaganda'), 'gulika' => (bool) $in('gulika'),
            'durmuhurt' => (bool) $in('durmuhurt'), 'abhijit' => (bool) $in('abhijit'), 'kutup' => (bool) $in('kutup'),
            'purvahna' => (bool) $in('purvahna'), 'aparahna' => (bool) $in('aparahna'), 'pradosh' => (bool) $in('pradosh'),
            'morning' => (bool) $in('morning'), 'evening' => (bool) $in('evening'), 'brahma' => (bool) $in('brahma'),
            'sandhya' => (bool) $in('sandhya'), 'ritu' => (int) $in('ritu'),
            'sankranti' => $in('sankranti'), 'bhadra' => $vishti, 'bhadraMukh' => (bool) $in('bhadra_mukh'),
            'bhadraPuchh' => (bool) $in('bhadra_puchh'), 'yogaTyajya' => $in('yoga_tyajya'),
            'randhra' => $in('paksharandhra'), 'kshaya' => $in('kshaya'), 'vriddhi' => $in('vriddhi'),
            'gandantT' => (bool) $in('gandant_t'), 'gandantN' => (bool) $in('gandant_n'), 'gandantL' => (bool) $in('gandant_l'),
            'varjyam' => (bool) $in('varjyam'), 'sutak' => $in('sutak'),
            'uttarayan' => in_array($sunSign, [9, 10, 11, 0, 1, 2], true),
            'yearRitu' => [11 => 1, 0 => 1, 1 => 2, 2 => 2, 3 => 3, 4 => 3, 5 => 4, 6 => 4, 7 => 5, 8 => 5, 9 => 6, 10 => 6][$sunSign],
        ];
    }

    public static function norm(float $d): float
    {
        $d = fmod($d, 360.0);
        return $d < 0 ? $d + 360.0 : $d;
    }

    public static function wrap180(float $d): float
    {
        $d = fmod($d + 540.0, 360.0) - 180.0;
        return $d;
    }
}
