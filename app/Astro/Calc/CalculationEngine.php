<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

use AutoBusiness\Astro\Ayanamsa;
use AutoBusiness\Astro\Ephemeris\EphemerisProviderInterface;
use AutoBusiness\Astro\Time\JulianDay;

/**
 * Agent 1 — the Calculation Engine.
 *
 * Pure computation, runs FIRST and exactly once. It produces the immutable chart
 * JSON that every book agent consumes (never recomputed per agent):
 *   - D1 (Rasi) with planet degrees, signs, houses, nakshatras, retrograde
 *   - D9 (Navamsa)
 *   - sidereal Ascendant (Lagna) + MC, using the admin-selectable ayanamsa
 *   - Vimshottari (+ the Mudda/annual dasha is produced by Varshaphal)
 *   - Shadbala (partial — see Shadbala class)
 *   - Gochar (transits) for a requested date, against birth OR current location
 *   - Varshaphal annual chart (delegated to Varshaphal)
 *
 * Planetary positions come from the injected ephemeris provider (Swiss Ephemeris
 * preferred; pure-PHP analytic fallback). The engine subtracts the ayanamsa to
 * obtain sidereal positions.
 */
final class CalculationEngine
{
    public function __construct(
        private readonly EphemerisProviderInterface $eph,
        private readonly string $ayanamsa = 'lahiri'
    ) {
    }

    /**
     * Build the natal chart payload (D1 + D9 + Ascendant + dasha + shadbala).
     *
     * @param float $jdUt   birth instant, Julian Day UT
     * @param float $lat    geographic latitude (deg, north +)
     * @param float $lonEast geographic longitude (deg, east +)
     * @return array<string,mixed> the immutable chart JSON
     */
    public function computeChart(float $jdUt, float $lat, float $lonEast): array
    {
        $ayan = Ayanamsa::degrees($this->ayanamsa, $jdUt);
        $positions = $this->eph->positions($jdUt);

        // Sidereal planet longitudes + per-planet derived fields.
        $sidereal = [];
        $speeds = [];
        $planets = [];
        foreach ($positions as $name => $p) {
            $sid = Charts::norm($p['lon'] - $ayan);
            $sidereal[$name] = $sid;
            $speeds[$name] = $p['speed'];
        }

        // Ascendant + MC (sidereal).
        [$ascTrop, $mcTrop] = $this->ascendantMc($jdUt, $lat, $lonEast);
        $ascSid = Charts::norm($ascTrop - $ayan);
        $mcSid = Charts::norm($mcTrop - $ayan);
        $ascSign = Charts::signIndex($ascSid);

        foreach ($positions as $name => $p) {
            $sid = $sidereal[$name];
            $planets[$name] = [
                'sidereal_lon' => round($sid, 4),
                'tropical_lon' => round(Charts::norm($p['lon']), 4),
                'sign' => Charts::signName($sid),
                'sign_index' => Charts::signIndex($sid),
                'deg_in_sign' => round(Charts::degInSign($sid), 4),
                'formatted' => Charts::format($sid),
                'house' => Charts::houseFromAsc($sid, $ascSign),
                'nakshatra' => Charts::nakshatra($sid),
                'retro' => $p['retro'],
                'speed' => round($p['speed'], 4),
                'navamsa_sign' => Charts::SIGNS[Charts::navamsaSignIndex($sid)],
            ];
        }

        // Dasha + Shadbala.
        $dasha = VimshottariDasha::sequence($sidereal['Moon'], $jdUt);
        $running = VimshottariDasha::running($sidereal['Moon'], $jdUt, $jdUt);
        $shadbala = Shadbala::compute($sidereal, $speeds, $ascSid, $mcSid, $jdUt, $lat, $lonEast, $ayan);

        // Ashtakavarga (SAV per sign) + Bhava Bala (per house).
        $avSigns = ['Lagna' => $ascSign];
        foreach (['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'] as $pl) {
            $avSigns[$pl] = Charts::signIndex($sidereal[$pl]);
        }
        $ashtakavarga = Ashtakavarga::compute($avSigns);
        $bhavaBala = BhavaBala::compute($ascSid, $ascSign, $shadbala, $sidereal);

        // Per-house summary (house no, sign, planets, lord, AV bindus, Bhava Bala).
        $houses = [];
        for ($hh = 1; $hh <= 12; $hh++) {
            $hsign = (($ascSign + $hh - 1) % 12 + 12) % 12;
            $houses[$hh] = [
                'house' => $hh,
                'sign_index' => $hsign,
                'sign' => Charts::SIGNS[$hsign],
                'rashi_num' => $hsign + 1,
                'lord' => Charts::signLord($hsign),
                'av' => $ashtakavarga['sav'][$hsign],
                'bb' => $bhavaBala[$hh]['rupa'],
                'planets' => [],
            ];
        }
        foreach ($planets as $pname => $pp) {
            if (isset($houses[$pp['house']])) {
                $houses[$pp['house']]['planets'][] = $pname;
            }
        }

        return [
            'meta' => [
                'jd_ut' => $jdUt,
                'ayanamsa_name' => $this->ayanamsa,
                'ayanamsa_deg' => round($ayan, 4),
                'ephemeris' => $this->eph->name(),
                'latitude' => $lat,
                'longitude_east' => $lonEast,
            ],
            'ascendant' => [
                'sidereal_lon' => round($ascSid, 4),
                'sign' => Charts::signName($ascSid),
                'sign_index' => $ascSign,
                'deg_in_sign' => round(Charts::degInSign($ascSid), 4),
                'formatted' => Charts::format($ascSid),
                'nakshatra' => Charts::nakshatra($ascSid),
                'navamsa_sign' => Charts::SIGNS[Charts::navamsaSignIndex($ascSid)],
            ],
            'mc' => [
                'sidereal_lon' => round($mcSid, 4),
                'formatted' => Charts::format($mcSid),
            ],
            'planets' => $planets,
            'dasha' => [
                'balance' => $dasha['balance'],
                'mahadashas' => $dasha['mahadashas'],
                'running' => $running,
            ],
            'shadbala' => $shadbala,
            'ashtakavarga' => $ashtakavarga,
            'bhava_bala' => $bhavaBala,
            'houses' => $houses,
        ];
    }

    /**
     * Gochar (transits) for a requested date. By default computed against the
     * BIRTH location; callers may pass current-location lat/lon for daily/monthly
     * transit work (Module 3c). Houses are reported from BOTH the natal Ascendant
     * and the natal Moon (Chandra lagna), as classical gochar is Moon-based.
     *
     * @param array<string,mixed> $natalChart
     * @return array<string,mixed>
     */
    public function gochar(array $natalChart, float $atJdUt, float $lat, float $lonEast): array
    {
        $ayan = Ayanamsa::degrees($this->ayanamsa, $atJdUt);
        $positions = $this->eph->positions($atJdUt);

        $natalAscSign = (int) $natalChart['ascendant']['sign_index'];
        $natalMoonSign = (int) $natalChart['planets']['Moon']['sign_index'];

        $transits = [];
        foreach ($positions as $name => $p) {
            $sid = Charts::norm($p['lon'] - $ayan);
            $transits[$name] = [
                'formatted' => Charts::format($sid),
                'sign' => Charts::signName($sid),
                'sign_index' => Charts::signIndex($sid),
                'deg' => (int) floor(Charts::degInSign($sid)),
                'retro' => $p['retro'],
                'house_from_lagna' => Charts::houseFromAsc($sid, $natalAscSign),
                'house_from_moon' => Charts::houseFromAsc($sid, $natalMoonSign),
            ];
        }

        // Transit Ascendant for the requested moment + place (so the gochar
        // chart has its own rising sign, like mainstream software).
        [$ascTrop] = $this->ascendantMc($atJdUt, $lat, $lonEast);
        $ascSid = Charts::norm($ascTrop - $ayan);

        return [
            'jd_ut' => $atJdUt,
            'location' => ['latitude' => $lat, 'longitude_east' => $lonEast],
            'ascendant' => [
                'sidereal_lon' => round($ascSid, 4),
                'sign' => Charts::signName($ascSid),
                'sign_index' => Charts::signIndex($ascSid),
                'formatted' => Charts::format($ascSid),
            ],
            'transits' => $transits,
        ];
    }

    /**
     * Build the divisional (varga) charts for the chart view — each as a
     * lagna sign + the sign each planet occupies in that division. Computed from
     * the already-built natal chart's sidereal longitudes (no recomputation).
     *
     * @param array<string,mixed> $chart
     * @return array<string,array{label:string, asc_sign:int, planets:list<array<string,mixed>>}>
     */
    public function vargaCharts(array $chart): array
    {
        $abbr = [
            'Sun' => 'Su', 'Moon' => 'Mo', 'Mars' => 'Ma', 'Mercury' => 'Me',
            'Jupiter' => 'Ju', 'Venus' => 'Ve', 'Saturn' => 'Sa', 'Rahu' => 'Ra', 'Ketu' => 'Ke',
        ];
        $ascLon = (float) $chart['ascendant']['sidereal_lon'];

        $out = [];
        foreach (Varga::CHARTS as $v => $label) {
            $planets = [];
            foreach (($chart['planets'] ?? []) as $name => $p) {
                $lon = (float) $p['sidereal_lon'];
                $planets[] = [
                    'name' => $name,
                    'abbr' => $abbr[$name] ?? substr((string) $name, 0, 2),
                    'sign' => Varga::sign($v, $lon),
                    'deg' => (int) floor(Charts::degInSign($lon)), // D1 whole degree, for labels
                    'retro' => (bool) ($p['retro'] ?? false),
                ];
            }
            $out[$v] = [
                'label' => $label,
                'asc_sign' => Varga::sign($v, $ascLon),
                'asc_deg' => (int) floor(Charts::degInSign($ascLon)),
                'planets' => $planets,
            ];
        }
        return $out;
    }

    /**
     * North-Indian render payload for a single chart (D1-style): ascendant sign
     * + each planet's sign/degree/retro. Reused by the divisional D1 and by the
     * annual (Varshaphal) chart so the front-end renderer stays uniform.
     *
     * @return array{asc_sign:int, asc_deg:int, planets:list<array{abbr:string,sign:int,deg:int,retro:bool}>}
     */
    public function northPayload(array $chart): array
    {
        $abbr = [
            'Sun' => 'Su', 'Moon' => 'Mo', 'Mars' => 'Ma', 'Mercury' => 'Me',
            'Jupiter' => 'Ju', 'Venus' => 'Ve', 'Saturn' => 'Sa', 'Rahu' => 'Ra', 'Ketu' => 'Ke',
        ];
        $ascLon = (float) $chart['ascendant']['sidereal_lon'];
        $planets = [];
        foreach (($chart['planets'] ?? []) as $name => $p) {
            $lon = (float) $p['sidereal_lon'];
            $planets[] = [
                'abbr' => $abbr[$name] ?? substr((string) $name, 0, 2),
                'sign' => Charts::signIndex($lon),
                'deg' => (int) floor(Charts::degInSign($lon)),
                'retro' => (bool) ($p['retro'] ?? false),
            ];
        }
        return [
            'asc_sign' => Charts::signIndex($ascLon),
            'asc_deg' => (int) floor(Charts::degInSign($ascLon)),
            'planets' => $planets,
        ];
    }

    /**
     * Sidereal positions only (helper for Find My Rashi / lightweight callers).
     *
     * @return array{moon:float, sun:float, ascendant:float}
     */
    public function keySigns(float $jdUt, float $lat, float $lonEast): array
    {
        $ayan = Ayanamsa::degrees($this->ayanamsa, $jdUt);
        $pos = $this->eph->positions($jdUt);
        [$ascTrop] = $this->ascendantMc($jdUt, $lat, $lonEast);
        return [
            'moon' => Charts::norm($pos['Moon']['lon'] - $ayan),
            'sun' => Charts::norm($pos['Sun']['lon'] - $ayan),
            'ascendant' => Charts::norm($ascTrop - $ayan),
        ];
    }

    public function ayanamsaName(): string
    {
        return $this->ayanamsa;
    }

    public function ephemerisName(): string
    {
        return $this->eph->name();
    }

    /**
     * Tropical Ascendant and MC ecliptic longitudes (degrees) for an instant and
     * place. Uses the IAU mean sidereal-time formula (Meeus) for accuracy.
     *
     * @return array{0: float, 1: float} [ascendant, mc] tropical degrees
     */
    private function ascendantMc(float $jdUt, float $lat, float $lonEast): array
    {
        $t = JulianDay::centuriesSinceJ2000($jdUt);

        // Greenwich Mean Sidereal Time (degrees), then local + RA of the MC.
        $gmst = 280.46061837
            + 360.98564736629 * ($jdUt - 2451545.0)
            + 0.000387933 * $t * $t
            - ($t * $t * $t) / 38710000.0;
        $ramc = $this->norm($gmst + $lonEast);

        // Mean obliquity of the ecliptic.
        $eps = 23.4392911 - 0.0130042 * $t - 1.64e-7 * $t * $t;

        $ramcR = deg2rad($ramc);
        $epsR = deg2rad($eps);
        $latR = deg2rad($lat);

        // MC ecliptic longitude.
        $mc = rad2deg(atan2(sin($ramcR), cos($ramcR) * cos($epsR)));

        // Ascendant (Meeus): rising ecliptic longitude on the eastern horizon.
        $asc = rad2deg(atan2(
            cos($ramcR),
            -(sin($ramcR) * cos($epsR) + tan($latR) * sin($epsR))
        ));

        return [$this->norm($asc), $this->norm($mc)];
    }

    private function norm(float $deg): float
    {
        $deg = fmod($deg, 360.0);
        return $deg < 0 ? $deg + 360.0 : $deg;
    }
}
