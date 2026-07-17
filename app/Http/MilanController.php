<?php
declare(strict_types=1);

namespace AutoBusiness\Http;

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Calc\Charts;
use AutoBusiness\Astro\Calc\Drishti;
use AutoBusiness\Astro\Ephemeris\EphemerisFactory;
use AutoBusiness\Astro\Milan\GunaMilan;
use AutoBusiness\Astro\Milan\MilanRepository;
use AutoBusiness\Astro\Time\JulianDay;
use AutoBusiness\Core\AdminGuard;

/**
 * Kundali Milan (Guna Milan / Ashtakoot) page. Takes two births — वर (boy) and
 * कन्या (girl) — computes each chart with the existing {@see CalculationEngine},
 * then scores the 36-guna match via {@see GunaMilan}. Renders both D1 + D9
 * charts, both planet tables, the eight koota cards, parihara, Mangal and the
 * summary. Read-only computation, staff-gated like the main calculator.
 */
final class MilanController
{
    public function show(): void
    {
        AdminGuard::require();
        \AutoBusiness\Core\Asset::noCacheHtml();

        // Two persons. Sensible demo defaults so the page is meaningful on first
        // load; any field is overridable via the forms (GET).
        $boyIn = self::personInput('boy', ['date' => '01-12-1980', 'time' => '12:31', 'lat' => "30N48'00", 'lon' => "75E10'00", 'name' => 'वर']);
        $girlIn = self::personInput('girl', ['date' => '15-08-1985', 'time' => '09:20', 'lat' => "28N39'00", 'lon' => "77E13'00", 'name' => 'कन्या']);
        $ayanamsa = (string) ($_GET['ayanamsa'] ?? 'lahiri');
        $lang = (string) ($_GET['phala_lang'] ?? 'hi');

        $error = null;
        $milan = $boyChart = $girlChart = null;

        try {
            $engine = new CalculationEngine(EphemerisFactory::create(), $ayanamsa);
            $boyChart = self::buildChart($engine, $boyIn);
            $girlChart = self::buildChart($engine, $girlIn);

            $rules = MilanRepository::load($lang);
            $cfg = $rules['config'] ?? [];
            $milan = GunaMilan::compute(
                self::milanPerson($boyIn['name'], $boyChart['chart']),
                self::milanPerson($girlIn['name'], $girlChart['chart']),
                $rules ?? [],
                $cfg
            );
            $milan['rules_error'] = MilanRepository::lastError();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $view = [
            'boyIn' => $boyIn,
            'girlIn' => $girlIn,
            'ayanamsa' => $ayanamsa,
            'lang' => $lang,
            'error' => $error,
            'milan' => $milan,
            'boy' => $boyChart,   // ['chart'=>..,'d1'=>payload,'d9'=>payload,'planets'=>rows]
            'girl' => $girlChart,
        ];
        require dirname(__DIR__) . '/Http/views/milan.php';
    }

    /** Collect one person's birth fields from GET with defaults. */
    private static function personInput(string $p, array $def): array
    {
        return [
            'name' => (string) ($_GET[$p . '_name'] ?? $def['name']),
            'date' => (string) ($_GET[$p . '_date'] ?? $def['date']),
            'time' => (string) ($_GET[$p . '_time'] ?? $def['time']),
            'lat' => (string) ($_GET[$p . '_lat'] ?? $def['lat']),
            'lon' => (string) ($_GET[$p . '_lon'] ?? $def['lon']),
            'tz' => (string) ($_GET[$p . '_tz'] ?? '5:30'),
            'place' => (string) ($_GET[$p . '_place'] ?? ''),
        ];
    }

    /**
     * Compute one person's chart + the render payloads the milan view needs.
     *
     * @return array{chart:array<string,mixed>, d1:array<string,mixed>, d9:array<string,mixed>, planets:list<array<string,mixed>>}
     */
    private static function buildChart(CalculationEngine $engine, array $in): array
    {
        $lat = CalcController::parseAngle($in['lat']);
        $lon = CalcController::parseAngle($in['lon']);
        $tz = CalcController::parseTz($in['tz']);
        [$Y, $Mo, $D] = CalcController::parseDate($in['date']);
        [$H, $Mi] = CalcController::parseTime($in['time'] !== '' ? $in['time'] : '12:00');
        $jd = JulianDay::fromGregorian($Y, $Mo, $D, $H, $Mi, 0.0, $tz);
        $chart = $engine->computeChart($jd, $lat, $lon);

        $vargas = $engine->vargaCharts($chart);
        $abbrHi = [
            'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
            'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
        ];
        $planets = [];
        foreach (($chart['planets'] ?? []) as $name => $p) {
            $planets[] = [
                'name' => $name,
                'name_hi' => $abbrHi[$name] ?? $name,
                'sign' => (string) $p['sign'],
                'sign_index' => (int) $p['sign_index'],
                'deg' => (float) $p['deg_in_sign'],
                'house' => (int) $p['house'],
                'nak' => (string) ($p['nakshatra']['name'] ?? ''),
                'pada' => (int) ($p['nakshatra']['pada'] ?? 0),
                'retro' => (bool) ($p['retro'] ?? false),
            ];
        }

        return [
            'chart' => $chart,
            'd1' => $engine->northPayload($chart),
            'd9' => $vargas['D9'] ?? null,
            'planets' => $planets,
        ];
    }

    /**
     * Reduce a computed chart to the Guna-Milan inputs (Moon rashi/nak/pada +
     * Mars houses from the three reference points + the Mangal cancellation flags).
     *
     * @return array<string,mixed>
     */
    public static function milanPerson(string $name, array $chart): array
    {
        $moon = $chart['planets']['Moon'] ?? [];
        $mars = $chart['planets']['Mars'] ?? [];
        $ascSign = (int) ($chart['ascendant']['sign_index'] ?? 0);
        $moonSign = (int) ($moon['sign_index'] ?? 0);
        $venusSign = (int) ($chart['planets']['Venus']['sign_index'] ?? 0);
        $marsSid = (float) ($mars['sidereal_lon'] ?? 0.0);
        $marsSign = (int) ($mars['sign_index'] ?? 0);
        $marsHouseLagna = (int) ($mars['house'] ?? 0);

        // Jupiter aspects Mars: does Jupiter cast a graha-drishti onto Mars's house?
        $jupAspects = false;
        foreach (($chart['houses'][$marsHouseLagna]['drishti'] ?? []) as $ab) {
            if ((Drishti::FULL[$ab] ?? $ab) === 'Jupiter') {
                $jupAspects = true;
                break;
            }
        }
        $lagnaOccupants = $chart['houses'][1]['planets'] ?? [];
        $lagnaHasJupVenus = in_array('Jupiter', $lagnaOccupants, true) || in_array('Venus', $lagnaOccupants, true);

        return [
            'name' => $name,
            'rashi' => $moonSign + 1,
            'nak' => (int) ($moon['nakshatra']['index'] ?? 0) + 1,
            'pada' => (int) ($moon['nakshatra']['pada'] ?? 1),
            'moon_deg' => (float) ($moon['deg_in_sign'] ?? 0.0),
            'mars' => [
                'from_lagna' => $marsHouseLagna,
                'from_moon' => Charts::houseFromAsc($marsSid, $moonSign),
                'from_venus' => Charts::houseFromAsc($marsSid, $venusSign),
                'sign_index' => $marsSign,
                // Mangal cancellation mg_c1: Mars in own Aries(0)/Scorpio(7) or exalted Capricorn(9).
                'own_or_exalt' => in_array($marsSign, [0, 7, 9], true),
            ],
            'jupiter_aspects_mars' => $jupAspects,
            'lagna_has_jup_or_venus' => $lagnaHasJupVenus,
        ];
    }
}
