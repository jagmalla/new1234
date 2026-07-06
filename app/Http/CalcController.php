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

            $vargas = $engine->vargaCharts($chart);
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
            'vargas' => $vargas ?? null,
            'meta' => $meta,
            'birthJs' => $birthJs ?? null,
            'dashaNow' => $dashaNow ?? null,
            'varshaNorth' => $varshaNorth ?? null,
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
            'phala_yoga' => $this->phalaYoga($chart, (string) ($_GET['phala_lang'] ?? 'hi')),
            // Poorva-Shaap (BPHS ch.86) santaan rules + remedies (migration 026)
            // — a separate "शाप-दोष / सन्तान योग" prediction option.
            'shaap' => $this->shaap($chart, (string) ($_GET['phala_lang'] ?? 'hi')),
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
        ];
        // Layout redesign: the v2 shell is now the default. Legacy page still
        // reachable at ?layout=old for side-by-side comparison.
        $tpl = (($_GET['layout'] ?? '') === 'old') ? 'calc.php' : 'calc-v2.php';
        require dirname(__DIR__) . '/Http/views/' . $tpl;
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
    private function shaap(?array $chart, string $lang): ?array
    {
        if ($chart === null) {
            return null;
        }
        $rules = \AutoBusiness\Astro\Phala\ShaapRepository::load($lang);
        $data = $this->safe(
            static fn() => \AutoBusiness\Astro\Phala\ShaapEngine::compute($chart, $rules),
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
    private function phalaYoga(?array $chart, string $lang): ?array
    {
        if ($chart === null) {
            return null;
        }
        $rules = \AutoBusiness\Astro\Phala\PhaladeepikaYogaRepository::load($lang);
        $data = $this->safe(
            static fn() => \AutoBusiness\Astro\Phala\PhaladeepikaYogaEngine::compute($chart, $rules),
            null
        );
        if (is_array($data)) {
            $data['error'] = \AutoBusiness\Astro\Phala\PhaladeepikaYogaRepository::lastError();
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
                'row3_html' => $frag('_varsha_bala_cards.php'),
                'positions_html' => $frag('_varsha_positions.php'),
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
