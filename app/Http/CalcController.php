<?php
declare(strict_types=1);

namespace AutoBusiness\Http;

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Varshaphal;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Core\AdminGuard;

/**
 * Browser-based Calculation Engine tester (the web twin of calc_test.php).
 *
 * Renders a birth-details form and the resulting D1/D9/dasha/shadbala/
 * varshaphal/gochar so the engine can be verified on screen (and screenshotted)
 * without the command line. Read-only computation — no database, no secrets —
 * but still gated behind AdminGuard so it is staff-only in production.
 */
final class CalcController
{
    public function show(): void
    {
        AdminGuard::require();
        \AutoBusiness\Core\Asset::noCacheHtml(); // HTML always revalidated (cache-busting)

        // Access log: record this visit (location / date / time / IP + per-IP
        // repeat counter). Fail-safe — never throws into the page. The returned
        // visit id is handed to the page so its heartbeat can report duration.
        $accessVid = \AutoBusiness\Core\AccessLog::begin();

        // Defaults = the Moga reference birth (matches calc_test.php).
        // Birth date is entered DD-MM-YYYY.
        $date = (string) ($_GET['date'] ?? '01-12-1980');
        $time = (string) ($_GET['time'] ?? '12:31');
        $latIn = (string) ($_GET['lat'] ?? "30N48'00");
        $lonIn = (string) ($_GET['lon'] ?? "75E10'00");
        $tzIn = (string) ($_GET['tz'] ?? '5:30');
        $ayanamsa = (string) ($_GET['ayanamsa'] ?? 'lahiri');
        $name = (string) ($_GET['name'] ?? '');
        $gender = (string) ($_GET['gender'] ?? '');
        $place = (string) ($_GET['place'] ?? '');   // birth place name (for display)
        // When no year is given, default to the *currently running* annual chart
        // (computed from the birth date below) so today's running Mudda dasha is
        // meaningful; an explicit ?year= always overrides.
        $hasYear = isset($_GET['year']);
        $forYear = (int) ($_GET['year'] ?? (int) date('Y'));
        // Gochar defaults to NOW (current date + time); both are adjustable.
        $gocharIn = (string) ($_GET['gochar'] ?? date('Y-m-d'));
        $gocharTimeIn = (string) ($_GET['gochar_time'] ?? date('H:i'));

        $error = null;
        $chart = $vp = $gochar = null;
        $sadeSati = $manglik = $upcomingGochar = null;
        $meta = [];

        try {
            $lat = self::parseAngle($latIn);
            $lon = self::parseAngle($lonIn);
            $tz = self::parseTz($tzIn);
            [$Y, $Mo, $D] = self::parseDate($date);   // accepts DD-MM-YYYY or YYYY-MM-DD
            [$H, $Mi] = self::parseTime($time);       // accepts HH:MM or HH MM

            // Active Varsha year = the annual chart whose Varsha Pravesh (≈ the
            // birthday) contains today. Before this year's birthday it is last year.
            if (!$hasYear) {
                $ty = (int) date('Y');
                $tm = (int) date('n');
                $td = (int) date('j');
                $forYear = ($tm < $Mo || ($tm === $Mo && $td < $D)) ? $ty - 1 : $ty;
            }

            $jd = JulianDay::fromGregorian($Y, $Mo, $D, $H, $Mi, 0.0, $tz);
            $engine = new CalculationEngine(EphemerisFactory::create(), $ayanamsa);
            $chart = $engine->computeChart($jd, $lat, $lon);

            // Live dasha chain (running Maha/Antar/Pratyantar + next Antar) at now.
            $nowJd = JulianDay::fromGregorian(
                (int) date('Y'), (int) date('m'), (int) date('d'),
                (int) date('H'), (int) date('i'), 0.0, $tz
            );
            $dashaNow = \AutoBusiness\Astro\Calc\VimshottariDasha::runningChain(
                (float) $chart['planets']['Moon']['sidereal_lon'], $jd, $nowJd
            );
            $vp = Varshaphal::compute($engine, $chart, $Y, $Mo, $D, $H, $Mi, $tz, $lat, $lon, $forYear);

            [$gy, $gm, $gd] = array_map('intval', explode('-', $gocharIn));
            [$gH, $gMi] = array_map('intval', array_pad(explode(':', $gocharTimeIn), 2, '0'));
            $jdG = JulianDay::fromGregorian($gy, $gm, $gd, $gH, $gMi, 0.0, $tz);
            $gochar = $engine->gochar($chart, $jdG, $lat, $lon);

            // Sade-Sati / Dhaiyya timeline (running or next-upcoming window +
            // dates) and Manglik (Mangal-dosha) status — surfaced in the D1
            // General Overview. Both are wrapped so an edge-case never blanks
            // the page.
            $sadeSati = $this->safe(static fn () => \AutoBusiness\Astro\Gochar\SadeSatiTimeline::compute(
                (int) $chart['planets']['Moon']['sign_index'],
                $nowJd,
                static fn (float $j): float => $engine->planetSiderealLon('Saturn', $j)
            ), null);
            $manglik = $this->safe(fn () => $this->manglik($chart), null);
            // आगामी गोचर summary (next ingress / retro / combustion / paksha /
            // nakshatra / Sade-Sati) from NOW — shown on the Gochar + Profile screens.
            $upcomingGochar = $this->safe(static fn () => \AutoBusiness\Astro\Gochar\UpcomingGochar::compute(
                $engine, $nowJd, $tz, (int) ($chart['planets']['Moon']['sign_index'] ?? 0)
            ), null);
            // आज का सामान्य मुहूर्त (पंचांग-शुद्धि) — for the Today Consult + Profile
            // signal bar. Computed from NOW's Sun/Moon (independent of the gochar
            // date picker) so "आज" always means today.
            $todayMuhurat = $this->safe(static function () use ($engine, $nowJd) {
                $sun = $engine->planetSiderealLon('Sun', $nowJd);
                $moon = $engine->planetSiderealLon('Moon', $nowJd);
                $moonSign = (int) floor(fmod($moon + 360.0, 360.0) / 30.0) % 12;
                $wd = ((int) floor($nowJd + 0.5) + 1) % 7;
                return \AutoBusiness\Astro\Muhurat\GeneralMuhuratEngine::compute($sun, $moon, (int) $wd, $moonSign);
            }, null);

            $vargas = $engine->vargaCharts($chart);
            // विदेश यात्रा व स्थायी निवास — computed foreign-settlement analysis
            // (promise / reason / settle-vs-return / PR timing / obstruction +
            // remedy). Own try/catch so an edge-case never blanks the chart page.
            try {
                $videsh = \AutoBusiness\Astro\Phala\VideshEngine::compute(
                    $chart, $vargas,
                    (float) ($chart['planets']['Moon']['sidereal_lon'] ?? 0.0),
                    $jd, $nowJd, $tz,
                    // engine + birth params → auto गुरु-शनि गोचर + वर्षफल per PR window
                    $engine, [$Y, $Mo, $D, $H, $Mi, $lat, $lon]
                );
            } catch (\Throwable $e) {
                $videsh = null;
            }
            // संतान-योग — computed child-birth analysis (4 pillars · yogas · shrap ·
            // obstruction+remedy · dasha-gochar timing · boy/girl · sphuta · vargas).
            // Own try/catch so an edge-case never blanks the chart page.
            try {
                $santan = \AutoBusiness\Astro\Phala\SantanEngine::compute(
                    $chart, $vargas,
                    (float) ($chart['planets']['Moon']['sidereal_lon'] ?? 0.0),
                    $jd, $nowJd, $tz, $engine, [$Y, $Mo, $D, $H, $Mi, $lat, $lon],
                    strtolower($gender) === 'female' || $gender === 'स्त्री' ? 'female'
                        : (strtolower($gender) === 'male' || $gender === 'पुरुष' ? 'male' : '')
                );
            } catch (\Throwable $e) {
                $santan = null;
            }
            // करियर / नौकरी-व्यवसाय — computed career analysis (10th house · Navamsha
            // Karmajiva · D-10 · Shadbala · Vimshopaka · Bhavat-Bhavam/7th · yogas ·
            // Saturn/6th · profession · job-vs-business · dasha · promotion/downfall).
            try {
                $career = \AutoBusiness\Astro\Phala\CareerEngine::compute(
                    $chart, $vargas,
                    (float) ($chart['planets']['Moon']['sidereal_lon'] ?? 0.0),
                    $jd, $nowJd, $tz, $engine, [$Y, $Mo, $D, $H, $Mi, $lat, $lon]
                );
            } catch (\Throwable $e) {
                $career = null;
            }
            // राजनीति — computed political-career analysis (houses 10/6/11 · Sun/
            // Mars/Saturn/Rahu · raj/viparita/neechbhanga/mahapurusha yogas · D9/D10 ·
            // shadbala/ashtakavarga/vimshopaka · dasha · varshaphal · gochar · level
            // scale · promotion). Own try/catch so an edge-case never blanks the page.
            try {
                $politics = \AutoBusiness\Astro\Phala\PoliticsEngine::compute(
                    $chart, $vargas,
                    (float) ($chart['planets']['Moon']['sidereal_lon'] ?? 0.0),
                    $jd, $nowJd, $tz, $engine, [$Y, $Mo, $D, $H, $Mi, $lat, $lon], $vp,
                    $career   // cross-check: does the career profile point to politics?
                );
            } catch (\Throwable $e) {
                $politics = null;
            }
            // धन-योग — computed wealth analysis (0-10 scale · sources · savings ·
            // houses/bhavesh · drishti · yogas · Shadbala/BhavaBala/Ashtakavarga/
            // Vimshopaka/Navamsa · D2/D4/D9/D10 · dasha · past+upcoming profit-loss).
            try {
                $wealth = \AutoBusiness\Astro\Phala\WealthEngine::compute(
                    $chart, $vargas,
                    (float) ($chart['planets']['Moon']['sidereal_lon'] ?? 0.0),
                    $jd, $nowJd, $tz, $engine, [$Y, $Mo, $D, $H, $Mi, $lat, $lon]
                );
            } catch (\Throwable $e) {
                $wealth = null;
            }
            // North-chart payload of the annual chart for the v2 chart selector
            // (render-ready; same shape the varshaphal JSON endpoint returns).
            $varshaNorth = $vp !== null ? $engine->northPayload($vp['varsha_chart']) : null;
            $meta = ['lat' => $lat, 'lon' => $lon, 'tz' => $tz, 'jd' => $jd];
            // Birth params handed to the browser so the interactive gochar panel
            // can rebuild the natal chart for any transit instant/place.
            $birthJs = [
                'date' => sprintf('%04d-%02d-%02d', $Y, $Mo, $D), // ISO for JS endpoints
                'time' => sprintf('%02d:%02d', $H, $Mi),          // normalised HH:MM

                'lat' => $lat, 'lon' => $lon, 'tz' => $tz, 'ayanamsa' => $ayanamsa,
            ];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        // Expose for the view.
        $view = [
            'in' => compact('date', 'time', 'latIn', 'lonIn', 'tzIn', 'ayanamsa', 'name', 'gender', 'place', 'forYear', 'gocharIn', 'gocharTimeIn'),
            'error' => $error,
            'chart' => $chart,
            'vp' => $vp,
            'gochar' => $gochar,
            'today_muhurat' => $todayMuhurat ?? null,
            'vargas' => $vargas ?? null,
            'videsh' => $videsh ?? null,
            'santan' => $santan ?? null,
            'career' => $career ?? null,
            'wealth' => $wealth ?? null,
            'politics' => $politics ?? null,
            'meta' => $meta,
            'birthJs' => $birthJs ?? null,
            'dashaNow' => $dashaNow ?? null,
            'varshaNorth' => $varshaNorth ?? null,
            // Sade-Sati / Dhaiyya window (current or next, with dates) + Manglik
            // status — both consumed by the D1 General Overview summary.
            'sade_sati' => $sadeSati,
            'manglik' => $manglik,
            'upcoming_gochar' => $upcomingGochar,
            // Dasha Prediction: dropdowns default to the running Maha/Antar; the
            // matching phala text (if seeded) is pre-rendered so it shows at once.
            'phala' => [
                'lang' => (string) ($_GET['phala_lang'] ?? 'hi'),
                'maha' => $dashaNow['maha']['lord'] ?? 'Sun',
                'antar' => $dashaNow['antar']['lord'] ?? 'Sun',
                'text' => \AutoBusiness\Astro\Phala\DashaPhalaRepository::find(
                    $dashaNow['maha']['lord'] ?? 'Sun',
                    $dashaNow['antar']['lord'] ?? 'Sun',
                    (string) ($_GET['phala_lang'] ?? 'hi')
                ),
                // DB error (if any) so staff see the real reason, not just a
                // silent "not available" placeholder.
                'error' => \AutoBusiness\Astro\Phala\DashaPhalaRepository::lastError(),
            ],
            // Calculated Dasha engine (दशा फल v2): chart-specific cards for the
            // running maha/antar. Recomputed on dropdown change via /calc/dashaEngine.
            'dashaEngine' => $this->dashaEngine(
                $chart,
                $dashaNow['maha']['lord'] ?? 'Sun',
                $dashaNow['antar']['lord'] ?? 'Sun',
                (string) ($_GET['phala_lang'] ?? 'hi')
            ),
            // Planet Prediction: per-planet (A) Bhavesh Phal (as house-lord) and
            // (B) Graha-in-Bhava (as placement). Built from the chart's own
            // ruled-house + placed-house knowledge.
            'planetPhala' => $this->planetPhala($chart, (string) ($_GET['phala_lang'] ?? 'hi')),
            // Prediction-confidence meter: per-planet प्रबल/मध्यम/क्षीण verdict
            // (Shadbala + Ashtakavarga + D9) with reasons, plus each planet's
            // dasha फल-काल windows — shown on the Planet/House prediction cards.
            'strength' => $this->safe(
                static fn () => $chart !== null
                    ? \AutoBusiness\Astro\Phala\StrengthMeter::compute($chart, (float) ($meta['tz'] ?? 0.0), $nowJd ?? null)
                    : null,
                null
            ),
            // D1 दोष-पैनल: कालसर्प / ग्रहण / चांडाल / अंगारक / विष / केमद्रुम /
            // शकट / पितृ — detection + परिहार, the "दोष" prediction option.
            'doshas' => $this->safe(
                static fn () => $chart !== null ? \AutoBusiness\Astro\Phala\DoshaFinder::compute($chart) : [],
                []
            ),
            // आगामी 12 महीने की समय-रेखा: ingress + वक्री/मार्गी + दशा-परिवर्तन +
            // गोचर-जन्म ±3° संयोग — one merged calendar (Gochar section + Today).
            'year_timeline' => $this->safe(
                static function () use ($chart, &$engine, &$nowJd, $meta) {
                    if ($chart === null || !isset($engine, $nowJd)) {
                        return null;
                    }
                    return \AutoBusiness\Astro\Gochar\YearTimeline::compute(
                        $engine, $chart, (float) $nowJd, (float) ($meta['tz'] ?? 0.0)
                    );
                },
                null
            ),
            // House Prediction: combines the editable rule tables with the chart
            // facts to write a per-house Hindi reading.
            'housePred' => $housePred = $this->housePred($chart, (string) ($_GET['phala_lang'] ?? 'hi')),
            // Karaka Prediction: karaka assessment + houses judged from the karaka;
            // reuses the House v2 per-house score as the lagna-side verdict.
            'karakaPred' => $this->karakaPred($chart, (string) ($_GET['phala_lang'] ?? 'hi'), $housePred['houses'] ?? []),
            // Yoga list (layout v2): classical yogas detected from the computed
            // placements — presentation layer only, no engine changes. Wrapped so
            // a detector edge-case can never blank the whole chart page.
            'yogas' => $this->safe(static fn() => $chart !== null ? \AutoBusiness\Astro\Phala\YogaFinder::find($chart) : [], []),
            // Phaladeepika 106-yoga catalogue (migration 025) — grouped by
            // category, computable subset auto-detected. Shown under the same
            // "योग" prediction option with a category dropdown.
            'phala_yoga' => $this->phalaYoga($chart, (string) ($_GET['phala_lang'] ?? 'hi'), (float) ($meta['tz'] ?? 0.0)),
            // Poorva-Shaap (BPHS ch.86) santaan rules + remedies (migration 026)
            // — a separate "शाप-दोष / सन्तान योग" prediction option.
            'shaap' => $this->shaap($chart, (string) ($_GET['phala_lang'] ?? 'hi'), (float) ($meta['tz'] ?? 0.0)),
            // Saham (50 Tajik sahams) computed from the Varshaphal chart, shown in
            // the Varshaphal prediction panel with the active Mudda-mahadasha's
            // related sahams highlighted.
            'saham' => $this->saham($vp ?? null, (float) ($meta['tz'] ?? 0.0), (string) ($_GET['phala_lang'] ?? 'hi')),
            // Tajik drishti + 16 yogas (migration 016) from the same Varshaphal
            // chart — the "ताजिक योग" option of the Varshaphal prediction dropdown.
            'tajik' => $this->tajik($vp ?? null, (float) ($meta['tz'] ?? 0.0), (string) ($_GET['phala_lang'] ?? 'hi')),
            // Varshesh (year-lord) selection + phal (migration 018) — the
            // "वर्षेश फल" option of the Varshaphal prediction dropdown.
            'varshesh' => $this->varshesh($vp ?? null, $chart, (float) ($meta['tz'] ?? 0.0), (string) ($_GET['phala_lang'] ?? 'hi')),
            // Muntha phal (migration 019) — the "मुंथा फल" option of the
            // Varshaphal prediction dropdown.
            'muntha' => $this->muntha($vp ?? null, $chart, (string) ($_GET['phala_lang'] ?? 'hi')),
            // Tajik-Neelakanthi Bhava-Phal (migration 023) — the "भाव-फल" option
            // of the Varshaphal prediction dropdown; 262 rules with the
            // computable subset auto-marked against the varsha chart.
            'tajik_bhava' => $this->tajikBhava($vp ?? null, $chart, (string) ($_GET['phala_lang'] ?? 'hi')),
            // Tajik-Neelakanthi Dasha-Phal (migration 024) — the "दशा-फल" option
            // of the Varshaphal prediction dropdown; Patyayini dasha with a
            // dasha/antardasha selector.
            'dasha_phal' => $this->dashaPhal($vp ?? null, $chart, (float) ($meta['tz'] ?? 0.0), (string) ($_GET['phala_lang'] ?? 'hi')),
            // Lal Kitab (लाल किताब) reading — the fixed-Aries teva chart plus its
            // categorised predictions & remedies (planet, house, karak, yoga,
            // parental-debt/shraap, sade-sati, manglik). Wrapped so an edge-case
            // never blanks the page; text is baked/owner-editable in LalKitabData.
            'lalkitab' => $this->safe(
                static function () use ($chart, $date, &$dashaNow, &$sadeSati) {
                    if ($chart === null) {
                        return ['ok' => false, 'error' => 'चार्ट उपलब्ध नहीं'];
                    }
                    // Native's current age (for the वर्ष कुंडली ज्ञान चक्र row).
                    $age = null;
                    try {
                        [$by, $bm, $bd] = self::parseDate($date);
                        $age = (int) date('Y') - $by;
                        if ((int) date('n') < $bm || ((int) date('n') === $bm && (int) date('j') < $bd)) {
                            $age--;
                        }
                    } catch (\Throwable $e) { /* age optional */ }
                    // Activation context: running Maha/Antar lords + the current
                    // Sade-Sati/Dhaiya state — drives the 🔥 strip + priority score.
                    $lkActive = [
                        'maha'  => $dashaNow['maha']['lord'] ?? null,
                        'antar' => $dashaNow['antar']['lord'] ?? null,
                        'sadesati' => (is_array($sadeSati) && !empty($sadeSati['active'])) ? [
                            'kind'  => (string) ($sadeSati['kind'] ?? ''),
                            'phase' => $sadeSati['phase'] ?? null,
                        ] : null,
                    ];
                    return \AutoBusiness\Astro\LalKitab\LalKitabEngine::compute($chart, $age, $lkActive);
                },
                ['ok' => false, 'error' => 'लाल किताब गणना विफल']
            ),
        ];
        // Layout redesign: the v2 shell is now the default. Legacy page still
        // reachable at ?layout=old for side-by-side comparison.
        $view['accessVid'] = $accessVid ?? '';
        $tpl = (($_GET['layout'] ?? '') === 'old') ? 'calc.php' : 'calc-v2.php';
        require dirname(__DIR__) . '/Http/views/' . $tpl;
    }

    /**
     * Manglik (Mangal-dosha) status for a single D1 chart — reuses the EXACT
     * calculation Kundali Milan performs, so the two never disagree. The chart is
     * reduced to the Milan person-shape (Mars houses + cancellation flags) and run
     * through GunaMilan::mangalPerson. Also carries Mars' sign/house for display.
     *
     * @param array<string,mixed> $chart
     * @return array<string,mixed>
     */
    private function manglik(array $chart): array
    {
        $person = \AutoBusiness\Http\MilanController::milanPerson('', $chart);
        $mg = \AutoBusiness\Astro\Milan\GunaMilan::mangalPerson($person);
        $mars = $chart['planets']['Mars'] ?? [];
        $mg['mars_sign_index'] = (int) ($mars['sign_index'] ?? 0);
        $mg['mars_house_lagna'] = (int) ($mars['house'] ?? 0);
        // Convenience flag: dosha present but cancelled (shown as "दोष-भंग").
        $mg['partial'] = !empty($mg['raw']) && empty($mg['manglik']);
        return $mg;
    }

    /**
     * Access-log heartbeat endpoint (GET|POST calc/ping?vid=…). The page pings
     * this every so often (and on unload) so the visit's duration in the log is
     * kept current. Returns 204 with no body; always succeeds silently.
     */
    public function ping(): void
    {
        $vid = (string) ($_REQUEST['vid'] ?? '');
        \AutoBusiness\Core\AccessLog::ping($vid);
        http_response_code(204);
        header('Content-Type: text/plain');
    }

    /**
     * Translate proxy (POST calc/translate). The browser cannot call the public
     * translation service directly (CORS blocks it), so it POSTs the Hindi
     * strings here and we fetch the English server-side (same origin → no CORS).
     * Body: {"q":["…","…"]}  →  {"t":["…","…"]} in the same order. On any failure
     * the original strings are returned so the caller degrades gracefully.
     */
    public function translateJson(): void
    {
        header('Content-Type: application/json');
        $raw = file_get_contents('php://input') ?: '';
        $in = json_decode($raw, true);
        $q = (is_array($in) && isset($in['q']) && is_array($in['q'])) ? array_values($in['q']) : [];
        // Coerce to strings + cap the request size.
        $q = array_slice(array_map(static fn($s): string => (string) $s, $q), 0, 200);
        if ($q === []) {
            echo json_encode(['t' => []]);
            return;
        }
        $sl = (string) ($_GET['sl'] ?? 'hi');
        $tl = (string) ($_GET['tl'] ?? 'en');
        if (!preg_match('/^[a-z]{2}$/', $sl)) { $sl = 'hi'; }
        if (!preg_match('/^[a-z]{2}$/', $tl)) { $tl = 'en'; }

        $out = [];
        foreach (array_chunk($q, 40) as $batch) {
            $qs = implode('&', array_map(static fn(string $s): string => 'q=' . rawurlencode($s), $batch));
            $url = "https://translate.googleapis.com/translate_a/t?client=gtx&sl={$sl}&tl={$tl}&{$qs}";
            $resp = self::httpGet($url);
            $data = $resp !== null ? json_decode($resp, true) : null;
            if (is_array($data) && count($batch) > 1) {
                // Multiple q → flat array of translations (["en1","en2",…]).
                foreach ($batch as $i => $orig) {
                    $t = $data[$i] ?? null;
                    $out[] = is_string($t) ? $t : (is_array($t) && isset($t[0]) && is_string($t[0]) ? $t[0] : $orig);
                }
            } elseif (is_array($data) && count($batch) === 1) {
                // Single q → the API returns just the translated string (or ["en"]).
                $t = is_string($data) ? $data : ($data[0] ?? null);
                $out[] = is_string($t) ? $t : $batch[0];
            } else {
                foreach ($batch as $orig) { $out[] = $orig; }   // failed → echo originals
            }
        }
        echo json_encode(['t' => $out], JSON_UNESCAPED_UNICODE);
    }

    /** Simple HTTPS GET (cURL preferred — respects proxies; stream fallback). */
    private static function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_USERAGENT => 'Mozilla/5.0',
            ]);
            $r = curl_exec($ch);
            $ok = ($r !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400);
            curl_close($ch);
            if ($ok) { return (string) $r; }
        }
        $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
        $r = @file_get_contents($url, false, $ctx);
        return $r === false ? null : $r;
    }

    /**
     * Build the Karaka Prediction payload: load the rule tables and generate the
     * per-karaka reading. Degrades to an error note if the DB is unreachable.
     *
     * @return array{lang:string, error:?string, karakas:list<mixed>}|null
     */
    private function karakaPred(?array $chart, string $lang, array $houseData = []): ?array
    {
        if ($chart === null) {
            return null;
        }
        // Lagna-side verdict per house = the House v2 numeric score (reused).
        $houseScores = [];
        foreach ($houseData as $hn => $hd) {
            $houseScores[(int) $hn] = (float) ($hd['score'] ?? 0.0);
        }
        $rules = \AutoBusiness\Astro\Phala\KarakaPredictionRepository::load($lang);
        return [
            'lang' => $lang,
            'error' => \AutoBusiness\Astro\Phala\KarakaPredictionRepository::lastError(),
            'karakas' => $rules !== null
                ? $this->safe(static fn() => \AutoBusiness\Astro\Phala\KarakaPrediction::generate($chart, $rules, $houseScores), [])
                : [],
        ];
    }

    /**
     * Build the calculated Dasha-engine payload (दशा फल v2) for a maha/antar
     * pair. Fail-safe: a DB/edge failure degrades to null and the panel falls
     * back to the classical 81-combo block.
     *
     * @return array{lang:string,error:?string,data:array<string,mixed>|null}|null
     */
    private function dashaEngine(?array $chart, string $maha, string $antar, string $lang): ?array
    {
        if ($chart === null) {
            return null;
        }
        $rules = \AutoBusiness\Astro\Phala\DashaEngineRepository::load($lang);
        return [
            'lang' => $lang,
            'error' => \AutoBusiness\Astro\Phala\DashaEngineRepository::lastError(),
            'data' => $rules !== null
                ? $this->safe(static fn() => \AutoBusiness\Astro\Phala\DashaPhalEngine::compute($chart, $maha, $antar, $rules, $lang), null)
                : null,
        ];
    }

    /**
     * JSON endpoint for the Dasha-engine dropdowns: rebuilds the natal chart from
     * the birth params, computes the engine for the selected maha/antar, renders
     * the cards partial and returns the HTML so the panel updates without reload.
     */
    public function dashaEngineJson(): void
    {
        AdminGuard::require();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $ayanamsa = (string) ($_GET['ayanamsa'] ?? 'lahiri');
            $lat = self::parseAngle((string) ($_GET['blat'] ?? '0'));
            $lon = self::parseAngle((string) ($_GET['blon'] ?? '0'));
            $tz = self::parseTz((string) ($_GET['btz'] ?? '0'));
            [$bY, $bMo, $bD] = array_map('intval', explode('-', (string) ($_GET['bdate'] ?? date('Y-m-d'))));
            [$bH, $bMi] = array_map('intval', array_pad(explode(':', (string) ($_GET['btime'] ?? '12:00')), 2, '0'));
            $maha = (string) ($_GET['maha'] ?? 'Sun');
            $antar = (string) ($_GET['antar'] ?? 'Sun');
            $lang = (string) ($_GET['lang'] ?? 'hi');

            $engine = new CalculationEngine(EphemerisFactory::create(), $ayanamsa);
            $chart = $engine->computeChart(JulianDay::fromGregorian($bY, $bMo, $bD, $bH, $bMi, 0.0, $tz), $lat, $lon);

            $rules = \AutoBusiness\Astro\Phala\DashaEngineRepository::load($lang);
            if ($rules === null) {
                echo json_encode(['error' => \AutoBusiness\Astro\Phala\DashaEngineRepository::lastError() ?? 'unavailable']);
                return;
            }
            $eng = \AutoBusiness\Astro\Phala\DashaPhalEngine::compute($chart, $maha, $antar, $rules, $lang);

            $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
            ob_start();
            require dirname(__DIR__) . '/Http/views/_dasha_cards.php';
            $html = (string) ob_get_clean();
            echo json_encode(['html' => $html], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            error_log('dashaEngineJson failed: ' . $e->getMessage());
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Run a prediction generator so any exception is contained: the feature
     * degrades to $fallback and the page still renders. The real error is logged
     * (visible in the server log) so a broken rule/chart combo can be diagnosed
     * without 500-ing the whole chart page.
     *
     * @template T
     * @param callable():T $fn
     * @param T $fallback
     * @return T
     */
    private function safe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log('Prediction generation failed: ' . $e->getMessage());
            return $fallback;
        }
    }

    /**
     * Build the House Prediction payload: load the rule tables and combine them
     * with the chart facts. Degrades to an error note if the DB is unreachable.
     *
     * @return array{lang:string, error:?string, houses:array<int,mixed>}|null
     */
    private function housePred(?array $chart, string $lang): ?array
    {
        if ($chart === null) {
            return null;
        }
        $rules = \AutoBusiness\Astro\Phala\HousePredictionRepository::load($lang);
        return [
            'lang' => $lang,
            'error' => \AutoBusiness\Astro\Phala\HousePredictionRepository::lastError(),
            'houses' => $rules !== null
                ? $this->safe(static fn() => \AutoBusiness\Astro\Phala\HousePrediction::generate($chart, $rules), [])
                : [],
        ];
    }

    /**
     * Build the Saham payload for the Varshaphal panel: 50 sahams from the
     * annual chart + the active Mudda-mahadasha lord (so related sahams can be
     * highlighted). @return array<string,mixed>|null
     */
    private function saham(?array $vp, float $tz, string $lang): ?array
    {
        if ($vp === null) {
            return null;
        }
        $rules = \AutoBusiness\Astro\Saham\SahamRepository::load($lang);   // baked fallback when DB down
        if ($rules === null) {
            return ['error' => \AutoBusiness\Astro\Saham\SahamRepository::lastError(), 'sahams' => []];
        }
        // Active Mudda mahadasha = the annual period containing "now".
        $nowJd = \AutoBusiness\Astro\Time\JulianDay::fromGregorian(
            (int) date('Y'), (int) date('m'), (int) date('d'), (int) date('H'), (int) date('i'), 0.0, $tz
        );
        $activeLord = null;
        foreach (($vp['mudda_dasha'] ?? []) as $md) {
            if ($nowJd >= (float) $md['start_jd'] && $nowJd < (float) $md['end_jd']) { $activeLord = (string) $md['lord']; break; }
        }
        if ($activeLord === null && !empty($vp['mudda_dasha'])) {
            $activeLord = (string) $vp['mudda_dasha'][0]['lord'];
        }
        $data = $this->safe(
            static fn() => \AutoBusiness\Astro\Saham\SahamEngine::compute($vp, $rules, $activeLord),
            ['sahams' => [], 'is_day' => true, 'active_lord' => $activeLord]
        );
        $data['error'] = \AutoBusiness\Astro\Saham\SahamRepository::lastError();
        $data['active_lord'] = $activeLord;
        $data['tz'] = $tz;
        // Mudda-dasha sequence (in order, one per lord) for the "मुद्दा-दशा चुनें"
        // dropdown so any dasha period's active sahams can be viewed.
        $muddaSeq = [];
        $seenLord = [];
        foreach (($vp['mudda_dasha'] ?? []) as $md) {
            $l = (string) $md['lord'];
            if (isset($seenLord[$l])) { continue; }
            $seenLord[$l] = true;
            $muddaSeq[] = ['lord' => $l, 'start_jd' => (float) $md['start_jd'], 'end_jd' => (float) $md['end_jd']];
        }
        $data['mudda_seq'] = $muddaSeq;
        return $data;
    }

    /**
     * Build the Tajik-yoga payload for the Varshaphal panel: sphuta drishti
     * matrix + 16-yoga records from the annual chart, with the active Mudda
     * mahadasha lord (default view) and the year's office-bearers (मुख्य योग
     * view). Degrades to an error note when the chart is unavailable.
     *
     * @return array<string,mixed>|null
     */
    private function tajik(?array $vp, float $tz, string $lang): ?array
    {
        if ($vp === null) {
            return null;
        }
        $rules = \AutoBusiness\Astro\Tajik\TajikRepository::load($lang);   // baked fallback when DB down

        // Active Mudda mahadasha = the annual period containing "now".
        $nowJd = \AutoBusiness\Astro\Time\JulianDay::fromGregorian(
            (int) date('Y'), (int) date('m'), (int) date('d'), (int) date('H'), (int) date('i'), 0.0, $tz
        );
        $activeLord = null;
        $activePeriod = null;
        foreach (($vp['mudda_dasha'] ?? []) as $md) {
            if ($nowJd >= (float) $md['start_jd'] && $nowJd < (float) $md['end_jd']) {
                $activeLord = (string) $md['lord'];
                $activePeriod = $md;
                break;
            }
        }
        if ($activeLord === null && !empty($vp['mudda_dasha'])) {
            $activeLord = (string) $vp['mudda_dasha'][0]['lord'];
            $activePeriod = $vp['mudda_dasha'][0];
        }

        $data = $this->safe(
            static fn() => \AutoBusiness\Astro\Tajik\TajikYogaEngine::compute($vp, $rules, $activeLord),
            ['chart_yogas' => [], 'yogas' => [], 'chips' => [], 'drishti' => [], 'roles' => [], 'is_day' => true]
        );
        $data['error'] = \AutoBusiness\Astro\Tajik\TajikRepository::lastError();
        $data['active_period'] = $activePeriod;
        $data['tz'] = $tz;
        return $data;
    }

    /**
     * Build the Varshesh (year-lord) payload for the Varshaphal panel: the
     * classical selection trail + band-graded phal + shloka 37–44 modifiers.
     * Needs the natal chart (shloka-37 natal band) and the Tajik rules (lagna
     * drishti + yoga detection). @return array<string,mixed>|null
     */
    private function varshesh(?array $vp, ?array $chart, float $tz, string $lang): ?array
    {
        if ($vp === null || $chart === null) {
            return null;
        }
        $tajik = \AutoBusiness\Astro\Tajik\TajikRepository::load($lang);
        $rules = \AutoBusiness\Astro\Varshesh\VarsheshRepository::load($lang);
        $nowJd = \AutoBusiness\Astro\Time\JulianDay::fromGregorian(
            (int) date('Y'), (int) date('m'), (int) date('d'), (int) date('H'), (int) date('i'), 0.0, $tz
        );
        $activeLord = null;
        foreach (($vp['mudda_dasha'] ?? []) as $md) {
            if ($nowJd >= (float) $md['start_jd'] && $nowJd < (float) $md['end_jd']) { $activeLord = (string) $md['lord']; break; }
        }
        $data = $this->safe(
            static fn() => \AutoBusiness\Astro\Varshesh\VarsheshEngine::compute($vp, $chart, $tajik, $rules, $activeLord),
            null
        );
        if (is_array($data)) {
            $data['error'] = \AutoBusiness\Astro\Varshesh\VarsheshRepository::lastError();
            $data['tz'] = $tz;
        }
        return $data;
    }

    /**
     * Build the Muntha-phal payload for the Varshaphal panel: bhava + graha +
     * Rahu-zone + shloka 3/17–23/33–36 modifiers. Needs the natal chart (the
     * janma-varsha bridge) and the Tajik rules. @return array<string,mixed>|null
     */
    private function muntha(?array $vp, ?array $chart, string $lang): ?array
    {
        if ($vp === null || $chart === null) {
            return null;
        }
        $tajik = \AutoBusiness\Astro\Tajik\TajikRepository::load($lang);
        $rules = \AutoBusiness\Astro\Muntha\MunthaRepository::load($lang);
        $data = $this->safe(
            static fn() => \AutoBusiness\Astro\Muntha\MunthaPhalEngine::compute($vp, $chart, $tajik, $rules),
            null
        );
        if (is_array($data)) {
            $data['error'] = \AutoBusiness\Astro\Muntha\MunthaRepository::lastError();
        }
        return $data;
    }

    /**
     * Build the Tajik-Neelakanthi Bhava-Phal payload (migration 023): all 262
     * bhava rules grouped by house, with the reliably-computable subset
     * auto-marked against the varsha chart. Needs the natal chart (pad = janma
     * rashi). @return array<string,mixed>|null
     */
    private function tajikBhava(?array $vp, ?array $natal, string $lang): ?array
    {
        if ($vp === null || $natal === null) {
            return null;
        }
        $rules = \AutoBusiness\Astro\Varshaphal\TajikBhavaRepository::load($lang);
        $data = $this->safe(
            static fn() => \AutoBusiness\Astro\Varshaphal\TajikBhavaEngine::compute($vp, $natal, $rules),
            null
        );
        if (is_array($data)) {
            $data['error'] = \AutoBusiness\Astro\Varshaphal\TajikBhavaRepository::lastError();
        }
        return $data;
    }

    /**
     * Build the Poorva-Shaap payload (migration 026): the 105 santaan rules
     * grouped by category with the computable subset auto-detected, plus the
     * traditional remedies for any dosha category that fired. Presented as
     * informational/traditional reference. @return array<string,mixed>|null
     */
    private function shaap(?array $chart, string $lang, float $tz = 0.0): ?array
    {
        if ($chart === null) {
            return null;
        }
        $rules = \AutoBusiness\Astro\Phala\ShaapRepository::load($lang);
        $data = $this->safe(
            static fn() => \AutoBusiness\Astro\Phala\ShaapEngine::compute($chart, $rules, $tz),
            null
        );
        if (is_array($data)) {
            $data['error'] = \AutoBusiness\Astro\Phala\ShaapRepository::lastError();
        }
        return $data;
    }

    /**
     * Build the Phaladeepika yoga payload (migration 025): the 106-yoga
     * catalogue grouped by category with the computable subset auto-detected
     * from the D1 chart. @return array<string,mixed>|null
     */
    private function phalaYoga(?array $chart, string $lang, float $tz = 0.0): ?array
    {
        if ($chart === null) {
            return null;
        }
        $rules = \AutoBusiness\Astro\Phala\PhaladeepikaYogaRepository::load($lang);
        $data = $this->safe(
            static fn() => \AutoBusiness\Astro\Phala\PhaladeepikaYogaEngine::compute($chart, $rules, $tz),
            null
        );
        if (is_array($data)) {
            $data['error'] = \AutoBusiness\Astro\Phala\PhaladeepikaYogaRepository::lastError();
            // BPHS Adhyaya-36 Raja Yogas (RY/KA/MN + Bhanga), deduped against the
            // Adhyaya-32 yogakaraka engine. A separate, additive classical set.
            $data['rajayoga'] = $this->safe(
                static fn () => \AutoBusiness\Astro\Phala\RajaYoga::compute($chart),
                null
            );
        }
        return $data;
    }

    /**
     * Build the Tajik-Neelakanthi Dasha-Phal payload (migration 024): the
     * Patyayini annual dasha with per-dasha strength phal, antardasha grading
     * and the bhavastha-graha layer. The pane lets the client pick any dasha /
     * antardasha. @return array<string,mixed>|null
     */
    private function dashaPhal(?array $vp, ?array $natal, float $tz, string $lang): ?array
    {
        if ($vp === null || $natal === null) {
            return null;
        }
        $rules = \AutoBusiness\Astro\Varshaphal\DashaPhalRepository::load($lang);
        $nowJd = JulianDay::fromGregorian(
            (int) date('Y'), (int) date('m'), (int) date('d'), (int) date('H'), (int) date('i'), 0.0, $tz
        );
        $data = $this->safe(
            static fn() => \AutoBusiness\Astro\Varshaphal\DashaPhalEngine::compute($vp, $natal, $rules, $nowJd),
            null
        );
        if (is_array($data)) {
            $data['error'] = \AutoBusiness\Astro\Varshaphal\DashaPhalRepository::lastError();
            $data['tz'] = $tz;
        }
        return $data;
    }

    /**
     * Build the Planet Prediction payload: for each planet, the house(s) it
     * rules (with the Bhavesh Phal text for "lord of that house placed in its
     * current house") and its placement (Graha-in-Bhava, populated later).
     *
     * @return array{lang:string, error:?string, planets:array<int,array<string,mixed>>}|null
     */
    private function planetPhala(?array $chart, string $lang): ?array
    {
        if ($chart === null) {
            return null;
        }

        $repo = \AutoBusiness\Astro\Phala\BhavPhalaRepository::class;
        $order = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];
        $houses = $chart['houses'] ?? [];

        // ग्रह स्थिति block (migration 008): computed dignity/combustion/companions
        // per planet. Fail-safe — a rule/data edge case degrades to an empty
        // block rather than 500-ing the panel.
        $gcRules = \AutoBusiness\Astro\Phala\GrahaConditionRepository::load($lang);
        $condition = $gcRules !== null
            ? $this->safe(static fn() => \AutoBusiness\Astro\Phala\GrahaCondition::generate($chart, $gcRules), [])
            : [];

        $out = [];
        foreach ($order as $pl) {
            if (!isset($chart['planets'][$pl])) {
                continue;
            }
            $placed = (int) ($chart['planets'][$pl]['house'] ?? 0);

            // Houses this planet rules = houses whose sign-lord is this planet.
            $ruled = [];
            foreach ($houses as $hn => $H) {
                if (($H['lord'] ?? null) === $pl) {
                    $ruled[] = (int) $hn;
                }
            }
            sort($ruled);

            $lordEntries = [];
            foreach ($ruled as $rh) {
                $lordEntries[] = [
                    'ruled_house' => $rh,
                    'placed_house' => $placed,
                    'text' => $repo::bhavesh($rh, $placed, $lang),
                ];
            }

            $out[] = [
                'planet' => $pl,
                'placed_house' => $placed,
                'condition' => $condition[$pl] ?? null,     // ग्रह स्थिति block (dignity/combust/companions)
                'lord_entries' => $lordEntries,            // (A) Bhavesh Phal
                'placement' => $repo::grahaBhava($pl, $placed, $lang), // (B) Graha-in-Bhava {positive,negative}|null
            ];
        }

        return [
            'lang' => $lang,
            'error' => \AutoBusiness\Astro\Phala\BhavPhalaRepository::lastError(),
            'planets' => $out,
        ];
    }

    /**
     * JSON endpoint for the Dasha Prediction dropdowns: returns the Positive /
     * Negative / Remedy summary for a Maha/Antar combination in the requested
     * language, or available=false when that combination has no text yet.
     */
    public function dashaPhalaJson(): void
    {
        AdminGuard::require();
        header('Content-Type: application/json; charset=utf-8');

        $maha  = (string) ($_GET['maha'] ?? '');
        $antar = (string) ($_GET['antar'] ?? '');
        $lang  = (string) ($_GET['lang'] ?? 'hi');

        $row = \AutoBusiness\Astro\Phala\DashaPhalaRepository::find($maha, $antar, $lang);

        $payload = [
            'available' => $row !== null,
            'maha'      => $maha,
            'antar'     => $antar,
            'language'  => $lang,
            'positive'  => $row['positive_text'] ?? null,
            'negative'  => $row['negative_text'] ?? null,
            'remedy'    => $row['remedy_text'] ?? null,
            'error'     => \AutoBusiness\Astro\Phala\DashaPhalaRepository::lastError(),
        ];
        // ?debug=1 adds connection/table/row diagnostics (staff-only route).
        if (($_GET['debug'] ?? '') === '1') {
            $payload['diagnostics'] = \AutoBusiness\Astro\Phala\DashaPhalaRepository::diagnostics($maha, $antar, $lang);
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSON endpoint for the interactive gochar panel: rebuilds the natal chart
     * from the birth params, then returns transits for the requested instant and
     * place. Read-only, AdminGuard-gated like show().
     */
    public function gocharJson(): void
    {
        AdminGuard::require();
        header('Content-Type: application/json');

        try {
            $tz = self::parseTz((string) ($_GET['tz'] ?? '0'));
            $lat = self::parseAngle((string) ($_GET['lat'] ?? '0'));
            $lon = self::parseAngle((string) ($_GET['lon'] ?? '0'));
            [$gy, $gm, $gd] = array_map('intval', explode('-', (string) ($_GET['date'] ?? date('Y-m-d'))));
            [$gH, $gMi] = array_map('intval', array_pad(explode(':', (string) ($_GET['time'] ?? '00:00')), 2, '0'));

            // Birth params (to rebuild the natal chart for house-from references).
            $ayanamsa = (string) ($_GET['ayanamsa'] ?? 'lahiri');
            $bLat = self::parseAngle((string) ($_GET['blat'] ?? (string) $lat));
            $bLon = self::parseAngle((string) ($_GET['blon'] ?? (string) $lon));
            $bTz = self::parseTz((string) ($_GET['btz'] ?? (string) $tz));
            [$bY, $bMo, $bD] = array_map('intval', explode('-', (string) ($_GET['bdate'] ?? date('Y-m-d'))));
            [$bH, $bMi] = array_map('intval', array_pad(explode(':', (string) ($_GET['btime'] ?? '12:00')), 2, '0'));

            $engine = new CalculationEngine(EphemerisFactory::create(), $ayanamsa);
            $natalJd = JulianDay::fromGregorian($bY, $bMo, $bD, $bH, $bMi, 0.0, $bTz);
            $natal = $engine->computeChart($natalJd, $bLat, $bLon);

            $jdG = JulianDay::fromGregorian($gy, $gm, $gd, $gH, $gMi, 0.0, $tz);
            $gochar = $engine->gochar($natal, $jdG, $lat, $lon);
            $gochar['label'] = sprintf('%04d-%02d-%02d %02d:%02d', $gy, $gm, $gd, $gH, $gMi);

            // Gochar Phal (migration 017): 3-layer transit predictions from the
            // natal chart + this transit snapshot, rendered with the shared view
            // partial so the panel updates whenever the date/place changes.
            $lang = (string) ($_GET['lang'] ?? 'hi');
            $rules = \AutoBusiness\Astro\Gochar\GocharRepository::load($lang);
            // Civil weekday (0=Sun..6=Sat) of the muhurat's LOCAL date — passed to
            // the Muhurat rules so Rahu Kaal / Disha Shul / janma-nakshatra phal
            // use the day the user actually selected (not the UT date).
            $wdJd = JulianDay::fromGregorian($gy, $gm, $gd, 12, 0, 0.0, 0.0);
            $weekday = ((int) floor($wdJd + 0.5) + 1) % 7;
            $gp = $this->safe(
                static fn() => \AutoBusiness\Astro\Gochar\GocharPhalEngine::compute($natal, $gochar['transits'], $rules, $jdG, $weekday),
                null
            );
            if (is_array($gp)) {
                $gp['error'] = \AutoBusiness\Astro\Gochar\GocharRepository::lastError();
                // Full Sade-Sati / Dhaiyya TIMELINE (all periods birth→future, both
                // Moon- and Lagna-based, 5-layer detail) for the साढ़े साती category.
                $gp['sade_timeline'] = $this->safe(static fn () => \AutoBusiness\Astro\Gochar\SadeSatiTimeline::fullTimeline(
                    (int) ($natal['planets']['Moon']['sign_index'] ?? 0),
                    (int) ($natal['ascendant']['sign_index'] ?? 0),
                    $natalJd,
                    $jdG,
                    static fn (float $j): float => $engine->planetSiderealLon('Saturn', $j),
                    $natal['ashtakavarga']['bav']['Saturn'] ?? array_fill(0, 12, 0),
                    static fn (float $j): float => $engine->planetSiderealLon('Jupiter', $j)
                ), null);
                $gp['sade_search_jd'] = $jdG;
                $gp['sade_tz'] = $tz;
            }
            $views = dirname(__DIR__) . '/Http/views/';
            require $views . '_varsha_helpers.php';   // $h/$pcolor/$grahaHi/$rashiHi
            ob_start();
            require $views . '_gochar_phal.php';
            $gochar['phal_html'] = (string) ob_get_clean();

            echo json_encode($gochar, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * 🔍 JSON endpoint — मुहूर्त तिथि-खोज: चुने प्रकार हेतु तिथि-सीमा में शुभ
     * दिन खोजता है। पंचांग-आधारित (कुण्डली-निरपेक्ष)। AdminGuard-gated like show().
     */
    public function muhuratScanJson(): void
    {
        AdminGuard::require();
        header('Content-Type: application/json');
        try {
            $type = (string) ($_GET['type'] ?? 'general');
            $tz = self::parseTz((string) ($_GET['tz'] ?? '5:30'));
            $ayanamsa = (string) ($_GET['ayanamsa'] ?? 'lahiri');
            $from = self::parseDate((string) ($_GET['from'] ?? ''));
            $to = self::parseDate((string) ($_GET['to'] ?? ''));
            $engine = new CalculationEngine(EphemerisFactory::create(), $ayanamsa);
            $res = \AutoBusiness\Astro\Muhurat\MuhuratDateScanner::scan($engine, $type, $from, $to, $tz);
            // पट्टी-HTML भी जोड़ें ताकि क्लाइंट सीधे दिखा सके।
            if (!empty($res['ok'])) {
                foreach (['rows', 'best'] as $k) {
                    foreach ($res[$k] as &$row) {
                        $row['bar_html'] = \AutoBusiness\Astro\Muhurat\Auspiciousness::barHtml((int) $row['score'], (string) $row['grade']);
                    }
                    unset($row);
                }
            }
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'तिथि-प्रारूप DD-MM-YYYY में दें।']);
        }
    }

    /**
     * JSON endpoint for the Varshaphal question box: rebuilds the natal chart
     * from the birth params, computes the annual (solar-return) chart + Mudda
     * dasha for the requested year, and returns a render-ready payload.
     */
    public function varshaphalJson(): void
    {
        AdminGuard::require();
        header('Content-Type: application/json');

        try {
            $ayanamsa = (string) ($_GET['ayanamsa'] ?? 'lahiri');
            $lat = self::parseAngle((string) ($_GET['blat'] ?? '0'));
            $lon = self::parseAngle((string) ($_GET['blon'] ?? '0'));
            $tz = self::parseTz((string) ($_GET['btz'] ?? '0'));
            [$bY, $bMo, $bD] = array_map('intval', explode('-', (string) ($_GET['bdate'] ?? date('Y-m-d'))));
            [$bH, $bMi] = array_map('intval', array_pad(explode(':', (string) ($_GET['btime'] ?? '12:00')), 2, '0'));
            $forYear = (int) ($_GET['year'] ?? (int) date('Y'));

            $engine = new CalculationEngine(EphemerisFactory::create(), $ayanamsa);
            $natalJd = JulianDay::fromGregorian($bY, $bMo, $bD, $bH, $bMi, 0.0, $tz);
            $natal = $engine->computeChart($natalJd, $lat, $lon);
            $vp = Varshaphal::compute($engine, $natal, $bY, $bMo, $bD, $bH, $bMi, $tz, $lat, $lon, $forYear);

            // Muntha sign index = natal Lagna sign advanced one sign per year.
            $natalAscSign = (int) $natal['ascendant']['sign_index'];
            $munthaSignIndex = (($natalAscSign + (int) $vp['age_completed']) % 12 + 12) % 12;

            // Year-dependent page fragments: the prediction panes (सहम, ताजिक
            // योग), the Panchavargeeya-Bala/Year-Lord cards and the annual
            // positions table are re-rendered server-side with the SAME
            // partials calc-v2 uses, so a year change updates every
            // prediction/calculation, not just the chart and Mudda dasha.
            $lang = (string) ($_GET['lang'] ?? 'hi');
            $view = [
                'saham' => $this->saham($vp, $tz, $lang),
                'tajik' => $this->tajik($vp, $tz, $lang),
                'varshesh' => $this->varshesh($vp, $natal, $tz, $lang),
                'muntha' => $this->muntha($vp, $natal, $lang),
                'tajik_bhava' => $this->tajikBhava($vp, $natal, $lang),
                'dasha_phal' => $this->dashaPhal($vp, $natal, $tz, $lang),
            ];
            $chart = $natal;
            $views = dirname(__DIR__) . '/Http/views/';
            require $views . '_varsha_helpers.php';   // $h/$pcolor/$grahaHi/$rashiHi
            $frag = static function (string $file) use ($views, $view, $vp, $chart, $forYear, $h, $pcolor, $grahaHi, $rashiHi): string {
                ob_start();
                require $views . $file;
                return (string) ob_get_clean();
            };

            echo json_encode([
                'year' => $forYear,
                'age_completed' => $vp['age_completed'],
                'varsha_lagna' => $vp['varsha_lagna'],
                'muntha' => $vp['muntha'],
                'muntha_sign_index' => $munthaSignIndex,
                'varshesh' => $vp['varshesh'] ?? null,
                // Varsha Pravesh (solar-return) start date, DD-MM-YYYY at birth tz.
                'varsha_start' => JulianDay::toDmy((float) $vp['solar_return_jd'], $tz),
                'chart' => $engine->northPayload($vp['varsha_chart']),
                'ascendant_formatted' => $vp['varsha_chart']['ascendant']['formatted'],
                'mudda_dasha' => $vp['mudda_dasha'],
                'saham_html' => $frag('_saham_pane.php'),
                'tajik_html' => $frag('_tajik_yoga.php'),
                'varshesh_html' => $frag('_varshesh_phal.php'),
                'muntha_html' => $frag('_muntha_phal.php'),
                'bhava_html' => $frag('_tajik_bhava.php'),
                'dasha_phal_html' => $frag('_dasha_phal.php'),
                'general_html' => $frag('_vp_general.php'),
                'row3_html' => $frag('_varsha_bala_cards.php'),
                'positions_html' => $frag('_varsha_positions.php'),
                'saar_html' => $frag('_varsha_saar.php'),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Parse a date entered as DD-MM-YYYY (preferred) or YYYY-MM-DD into
     * [year, month, day]. Separators -, / or . are accepted.
     *
     * @return array{0:int,1:int,2:int}
     */
    public static function parseDate(string $s): array
    {
        // Separators: dash, slash, dot or whitespace (e.g. 01-12-1980 or 1 12 1980).
        $parts = preg_split('/[-\/.\s]+/', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) !== 3) {
            return [(int) date('Y'), 1, 1];
        }
        [$a, $b, $c] = array_map('intval', $parts);
        // A 4-digit first field means YYYY-MM-DD; otherwise DD-MM-YYYY.
        return $a > 31 ? [$a, $b, $c] : [$c, $b, $a];
    }

    /**
     * Parse a time entered as HH:MM (preferred) or HH MM into [hour, minute].
     * Colon or whitespace separators are accepted.
     *
     * @return array{0:int,1:int}
     */
    public static function parseTime(string $s): array
    {
        $parts = preg_split('/[:\s.]+/', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        return [$h, $m];
    }

    /** Decimal or DMS-with-direction-letter angle -> signed decimal degrees. */
    public static function parseAngle(string $s): float
    {
        $s = trim($s);
        $dir = '';
        if (preg_match('/[NSEWnsew]/', $s, $m)) {
            $dir = strtoupper($m[0]);
        }
        $clean = trim(preg_replace('/[NSEWnsew]/', ' ', $s) ?? $s);

        if (preg_match('/^[-+]?\d+(\.\d+)?$/', $clean)) {
            $val = (float) $clean;
        } else {
            preg_match_all('/\d+(?:\.\d+)?/', $clean, $mm);
            $p = $mm[0];
            $val = (float) ($p[0] ?? 0) + (float) ($p[1] ?? 0) / 60.0 + (float) ($p[2] ?? 0) / 3600.0;
            if (str_starts_with($clean, '-')) {
                $val = -$val;
            }
        }
        if ($dir === 'S' || $dir === 'W') {
            return -abs($val);
        }
        if ($dir === 'N' || $dir === 'E') {
            return abs($val);
        }
        return $val;
    }

    /** Decimal hours or H:M[:S] (east positive) -> decimal hours. */
    public static function parseTz(string $s): float
    {
        $s = trim($s);
        if (preg_match('/^[-+]?\d+(\.\d+)?$/', $s)) {
            return (float) $s;
        }
        $sign = str_starts_with($s, '-') ? -1.0 : 1.0;
        $p = explode(':', ltrim($s, '+-'));
        return $sign * ((float) ($p[0] ?? 0) + (float) ($p[1] ?? 0) / 60.0 + (float) ($p[2] ?? 0) / 3600.0);
    }
}
