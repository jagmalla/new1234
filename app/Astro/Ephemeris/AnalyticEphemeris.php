<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Ephemeris;

use AutoBusiness\Astro\Time\JulianDay;

/**
 * Pure-PHP analytic ephemeris (Paul Schlyter's well-documented method:
 * https://stjarnhimlen.se/comp/ppcomp.html), with the principal Moon and
 * Jupiter/Saturn perturbation terms included.
 *
 * WHY THIS EXISTS / ACCURACY: The platform prefers Swiss Ephemeris, but on
 * locked-down shared hosting (no exec(), no custom extensions) it may be
 * unavailable. This provider needs no binaries, extensions, or data files, so
 * the engine always runs. Accuracy is roughly:
 *   - Sun: ~0.5'   - Moon: ~1-2'   - planets: ~1-2'   (modern era)
 * That is enough to match mainstream Jyotish software to the sign, nakshatra,
 * and usually the exact degree. For sub-arc-second work, wire in Swiss
 * Ephemeris (SwissEphemeris provider) — the engine swaps providers transparently.
 *
 * All angles are handled in degrees; trig helpers convert internally.
 */
final class AnalyticEphemeris implements EphemerisProviderInterface
{
    /**
     * @param string $nodeType 'true' (osculating, matches most modern software
     *               incl. Parashara's Light) or 'mean'.
     */
    public function __construct(private readonly string $nodeType = 'true')
    {
    }

    public function name(): string
    {
        return 'Analytic (Schlyter, pure-PHP)';
    }

    public function positions(float $jdUt): array
    {
        $bodies = $this->computeAll($jdUt);

        // Numerical daily speed (and retrograde flag) via a small time step.
        $dt = 0.5; // days
        $next = $this->computeAll($jdUt + $dt);

        $out = [];
        foreach ($bodies as $name => $b) {
            $delta = $this->normalizeSigned($next[$name]['lon'] - $b['lon']);
            $speed = $delta / $dt;
            $out[$name] = [
                'lon'   => $this->normalize($b['lon']),
                'lat'   => $b['lat'],
                'speed' => $speed,
                'retro' => $speed < 0.0,
            ];
        }
        return $out;
    }

    /**
     * @return array<string, array{lon: float, lat: float}>
     */
    private function computeAll(float $jdUt): array
    {
        $d = JulianDay::dayNumber($jdUt);
        $ecl = 23.4393 - 3.563e-7 * $d; // obliquity (unused for ecliptic lon, kept for clarity)

        // --- Sun ----------------------------------------------------------
        $ws = 282.9404 + 4.70935e-5 * $d;
        $es = 0.016709 - 1.151e-9 * $d;
        $Ms = $this->normalize(356.0470 + 0.9856002585 * $d);
        $sun = $this->solveBody(0.0, 0.0, $ws, 1.0, $es, $Ms);
        $sunLon = $this->normalize($sun['v'] + $ws);
        $xs = $sun['r'] * $this->cos($sunLon);
        $ys = $sun['r'] * $this->sin($sunLon);
        $Ls = $this->normalize($Ms + $ws); // Sun's mean longitude

        $result = ['Sun' => ['lon' => $sunLon, 'lat' => 0.0]];

        // --- Moon (geocentric, with perturbations) ------------------------
        $moonPos = $this->moonLonLat($d);
        $result['Moon'] = ['lon' => $moonPos['lon'], 'lat' => $moonPos['lat']];

        // --- Planets (heliocentric -> geocentric) -------------------------
        $planets = $this->planetElements($d);
        // Mean anomalies needed for Jupiter/Saturn perturbations.
        $Mj = $this->normalize(19.8950 + 0.0830853001 * $d);
        $Msat = $this->normalize(316.9670 + 0.0334442282 * $d);

        foreach ($planets as $pname => $el) {
            $p = $this->solveBody($el['N'], $el['i'], $el['w'], $el['a'], $el['e'], $el['M']);
            $vw = $p['v'] + $el['w'];
            $xph = $p['r'] * ($this->cos($el['N']) * $this->cos($vw) - $this->sin($el['N']) * $this->sin($vw) * $this->cos($el['i']));
            $yph = $p['r'] * ($this->sin($el['N']) * $this->cos($vw) + $this->cos($el['N']) * $this->sin($vw) * $this->cos($el['i']));
            $zph = $p['r'] * ($this->sin($vw) * $this->sin($el['i']));

            $lonecl = $this->normalize($this->atan2($yph, $xph));
            $latecl = $this->atan2($zph, sqrt($xph * $xph + $yph * $yph));

            // Major perturbations for the slow giants.
            if ($pname === 'Jupiter') {
                $lonecl += -0.332 * $this->sin(2 * $Mj - 5 * $Msat - 67.6)
                    - 0.056 * $this->sin(2 * $Mj - 2 * $Msat + 21)
                    + 0.042 * $this->sin(3 * $Mj - 5 * $Msat + 21)
                    - 0.036 * $this->sin($Mj - 2 * $Msat)
                    + 0.022 * $this->cos($Mj - $Msat)
                    + 0.023 * $this->sin(2 * $Mj - 3 * $Msat + 52)
                    - 0.016 * $this->sin($Mj - 5 * $Msat - 69);
            } elseif ($pname === 'Saturn') {
                $lonecl += 0.812 * $this->sin(2 * $Mj - 5 * $Msat - 67.6)
                    - 0.229 * $this->cos(2 * $Mj - 4 * $Msat - 2)
                    + 0.119 * $this->sin($Mj - 2 * $Msat - 3)
                    + 0.046 * $this->sin(2 * $Mj - 6 * $Msat - 69)
                    + 0.014 * $this->sin($Mj - 3 * $Msat + 32);
                $latecl += -0.020 * $this->cos(2 * $Mj - 4 * $Msat - 2)
                    + 0.018 * $this->sin(2 * $Mj - 6 * $Msat - 49);
            }

            // Convert heliocentric ecliptic to geocentric by adding the Sun's
            // geocentric rectangular position (xs, ys, 0).
            $xg = $p['r'] * $this->cos($latecl) * $this->cos($lonecl) + $xs;
            $yg = $p['r'] * $this->cos($latecl) * $this->sin($lonecl) + $ys;
            $zg = $p['r'] * $this->sin($latecl);

            $result[$pname] = [
                'lon' => $this->normalize($this->atan2($yg, $xg)),
                'lat' => $this->atan2($zg, sqrt($xg * $xg + $yg * $yg)),
            ];
        }

        // --- Lunar nodes: Rahu (ascending node) & Ketu --------------------
        $rahu = $this->nodeType === 'mean'
            ? $this->normalize(125.1228 - 0.0529538083 * $d) // mean node
            : $this->trueNode($d);                           // true (osculating) node
        $result['Rahu'] = ['lon' => $rahu, 'lat' => 0.0];
        $result['Ketu'] = ['lon' => $this->normalize($rahu + 180.0), 'lat' => 0.0];

        return $result;
    }

    /**
     * Geocentric ecliptic longitude/latitude of the Moon (degrees), including
     * the principal Schlyter perturbations.
     *
     * @return array{lon: float, lat: float}
     */
    private function moonLonLat(float $d): array
    {
        $Nm = 125.1228 - 0.0529538083 * $d;
        $im = 5.1454;
        $wm = 318.0634 + 0.1643573223 * $d;
        $am = 60.2666;
        $em = 0.054900;
        $Mm = $this->normalize(115.3654 + 13.0649929509 * $d);
        $moon = $this->solveBody($Nm, $im, $wm, $am, $em, $Mm);

        $vw = $moon['v'] + $wm;
        $xh = $moon['r'] * ($this->cos($Nm) * $this->cos($vw) - $this->sin($Nm) * $this->sin($vw) * $this->cos($im));
        $yh = $moon['r'] * ($this->sin($Nm) * $this->cos($vw) + $this->cos($Nm) * $this->sin($vw) * $this->cos($im));
        $zh = $moon['r'] * ($this->sin($vw) * $this->sin($im));
        $moonLon = $this->normalize($this->atan2($yh, $xh));
        $moonLat = $this->atan2($zh, sqrt($xh * $xh + $yh * $yh));

        // Sun mean longitude for the perturbation arguments.
        $Ms = $this->normalize(356.0470 + 0.9856002585 * $d);
        $ws = 282.9404 + 4.70935e-5 * $d;
        $Ls = $this->normalize($Ms + $ws);

        $Lm = $this->normalize($Mm + $wm + $Nm);
        $Dm = $this->normalize($Lm - $Ls);
        $Fm = $this->normalize($Lm - $Nm);

        $moonLon += -1.274 * $this->sin($Mm - 2 * $Dm)
            + 0.658 * $this->sin(2 * $Dm)
            - 0.186 * $this->sin($Ms)
            - 0.059 * $this->sin(2 * $Mm - 2 * $Dm)
            - 0.057 * $this->sin($Mm - 2 * $Dm + $Ms)
            + 0.053 * $this->sin($Mm + 2 * $Dm)
            + 0.046 * $this->sin(2 * $Dm - $Ms)
            + 0.041 * $this->sin($Mm - $Ms)
            - 0.035 * $this->sin($Dm)
            - 0.031 * $this->sin($Mm + $Ms)
            - 0.015 * $this->sin(2 * $Fm - 2 * $Dm)
            + 0.011 * $this->sin($Mm - 4 * $Dm);

        $moonLat += -0.173 * $this->sin($Fm - 2 * $Dm)
            - 0.055 * $this->sin($Mm - $Fm - 2 * $Dm)
            - 0.046 * $this->sin($Mm + $Fm - 2 * $Dm)
            + 0.033 * $this->sin($Fm + 2 * $Dm)
            + 0.017 * $this->sin(2 * $Mm + $Fm);

        return ['lon' => $this->normalize($moonLon), 'lat' => $moonLat];
    }

    /**
     * True (osculating) lunar ascending node: the longitude where the Moon's
     * instantaneous orbital plane crosses the ecliptic. Computed from the Moon's
     * position and (finite-difference) velocity — the orbit normal h = r x v,
     * and the node line = z_hat x h. This naturally includes the perturbations
     * carried in moonLonLat(), matching true-node software (e.g. Parashara's
     * Light) to within ~1 arc-minute.
     */
    private function trueNode(float $d): float
    {
        // Central difference for a more accurate instantaneous velocity.
        $dt = 0.05; // days
        $r = $this->moonUnitVector($d);
        $back = $this->moonUnitVector($d - $dt);
        $fwd = $this->moonUnitVector($d + $dt);
        $v = [($fwd[0] - $back[0]) / 2.0, ($fwd[1] - $back[1]) / 2.0, ($fwd[2] - $back[2]) / 2.0];

        // Orbit normal h = r x v (use the central position r at d).
        $hx = $r[1] * $v[2] - $r[2] * $v[1];
        $hy = $r[2] * $v[0] - $r[0] * $v[2];

        // Ascending-node line = z_hat x h = (-hy, hx, 0); longitude = atan2(hx, -hy).
        return $this->normalize($this->atan2($hx, -$hy));
    }

    /** Unit vector of the Moon's geocentric ecliptic direction at day-number d. */
    private function moonUnitVector(float $d): array
    {
        $m = $this->moonLonLat($d);
        $cb = $this->cos($m['lat']);
        return [$cb * $this->cos($m['lon']), $cb * $this->sin($m['lon']), $this->sin($m['lat'])];
    }

    /**
     * Orbital elements (Schlyter) as linear functions of the day number.
     *
     * @return array<string, array{N:float,i:float,w:float,a:float,e:float,M:float}>
     */
    private function planetElements(float $d): array
    {
        return [
            'Mercury' => [
                'N' => 48.3313 + 3.24587e-5 * $d, 'i' => 7.0047 + 5.00e-8 * $d,
                'w' => 29.1241 + 1.01444e-5 * $d, 'a' => 0.387098,
                'e' => 0.205635 + 5.59e-10 * $d, 'M' => $this->normalize(168.6562 + 4.0923344368 * $d),
            ],
            'Venus' => [
                'N' => 76.6799 + 2.46590e-5 * $d, 'i' => 3.3946 + 2.75e-8 * $d,
                'w' => 54.8910 + 1.38374e-5 * $d, 'a' => 0.723330,
                'e' => 0.006773 - 1.302e-9 * $d, 'M' => $this->normalize(48.0052 + 1.6021302244 * $d),
            ],
            'Mars' => [
                'N' => 49.5574 + 2.11081e-5 * $d, 'i' => 1.8497 - 1.78e-8 * $d,
                'w' => 286.5016 + 2.92961e-5 * $d, 'a' => 1.523688,
                'e' => 0.093405 + 2.516e-9 * $d, 'M' => $this->normalize(18.6021 + 0.5240207766 * $d),
            ],
            'Jupiter' => [
                'N' => 100.4542 + 2.76854e-5 * $d, 'i' => 1.3030 - 1.557e-7 * $d,
                'w' => 273.8777 + 1.64505e-5 * $d, 'a' => 5.20256,
                'e' => 0.048498 + 4.469e-9 * $d, 'M' => $this->normalize(19.8950 + 0.0830853001 * $d),
            ],
            'Saturn' => [
                'N' => 113.6634 + 2.38980e-5 * $d, 'i' => 2.4886 - 1.081e-7 * $d,
                'w' => 339.3939 + 2.97661e-5 * $d, 'a' => 9.55475,
                'e' => 0.055546 - 9.499e-9 * $d, 'M' => $this->normalize(316.9670 + 0.0334442282 * $d),
            ],
        ];
    }

    /**
     * Solve Kepler's equation and return true anomaly v (deg) and radius r (AU).
     *
     * @return array{v: float, r: float}
     */
    private function solveBody(float $N, float $i, float $w, float $a, float $e, float $M): array
    {
        // Eccentric anomaly by Newton iteration (degrees).
        $E = $M + (180.0 / M_PI) * $e * $this->sin($M) * (1.0 + $e * $this->cos($M));
        for ($iter = 0; $iter < 12; $iter++) {
            $dE = ($E - (180.0 / M_PI) * $e * $this->sin($E) - $M) / (1.0 - $e * $this->cos($E));
            $E -= $dE;
            if (abs($dE) < 1e-9) {
                break;
            }
        }
        // Position in the orbital plane.
        $xv = $a * ($this->cos($E) - $e);
        $yv = $a * sqrt(1.0 - $e * $e) * $this->sin($E);

        return [
            'v' => $this->atan2($yv, $xv),
            'r' => sqrt($xv * $xv + $yv * $yv),
        ];
    }

    // --- degree-based trig + angle helpers ---------------------------------

    private function sin(float $deg): float
    {
        return sin(deg2rad($deg));
    }

    private function cos(float $deg): float
    {
        return cos(deg2rad($deg));
    }

    private function atan2(float $y, float $x): float
    {
        return rad2deg(atan2($y, $x));
    }

    private function normalize(float $deg): float
    {
        $deg = fmod($deg, 360.0);
        return $deg < 0 ? $deg + 360.0 : $deg;
    }

    /** Wrap a difference into [-180, 180] (for daily-motion sign). */
    private function normalizeSigned(float $deg): float
    {
        $deg = fmod($deg + 180.0, 360.0);
        if ($deg < 0) {
            $deg += 360.0;
        }
        return $deg - 180.0;
    }
}
