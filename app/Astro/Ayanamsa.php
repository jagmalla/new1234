<?php
declare(strict_types=1);

namespace AutoBusiness\Astro;

use AutoBusiness\Astro\Time\JulianDay;

/**
 * Ayanamsa = the angular gap between the tropical and sidereal zodiacs.
 *
 * Vedic (sidereal) longitude = tropical longitude - ayanamsa. The ayanamsa is
 * admin-selectable (app_settings.ayanamsa, default 'lahiri'); the engine passes
 * the chosen name here for every calculation.
 *
 * Values use a linear precession model anchored at J2000.0 (accurate to roughly
 * an arc-minute over the modern era — good enough to match mainstream Jyotish
 * software to the degree/nakshatra). Swiss Ephemeris, when wired in, supplies a
 * higher-precision ayanamsa directly.
 */
final class Ayanamsa
{
    /** Ayanamsa value (degrees) at J2000.0 and precession rate (°/century). */
    private const MODELS = [
        // name        => [value at J2000.0, precession °/century]
        'lahiri'        => [23.85294, 1.396042], // Chitrapaksha (official Indian)
        'chitrapaksha'  => [23.85294, 1.396042], // alias of Lahiri
        'raman'         => [22.50538, 1.396042], // B.V. Raman
        'kp'            => [23.71667, 1.396042], // Krishnamurti Paddhati
        'fagan_bradley' => [24.74222, 1.396042], // Western sidereal
    ];

    public static function degrees(string $name, float $jdUt): float
    {
        $key = strtolower($name);
        [$base, $rate] = self::MODELS[$key] ?? self::MODELS['lahiri'];
        $t = JulianDay::centuriesSinceJ2000($jdUt);
        return $base + $rate * $t;
    }

    /** @return string[] the ayanamsa names the engine understands */
    public static function supported(): array
    {
        return array_keys(self::MODELS);
    }
}
