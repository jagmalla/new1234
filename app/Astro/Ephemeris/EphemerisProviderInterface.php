<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Ephemeris;

/**
 * Source of geocentric planetary positions. Implementations return TROPICAL
 * ecliptic longitudes; the calculation engine subtracts the ayanamsa to obtain
 * sidereal (Vedic) positions, so a provider never needs to know the ayanamsa.
 *
 * The platform PREFERS Swiss Ephemeris (gold-standard accuracy). Where Swiss
 * Ephemeris is not usable, the pure-PHP AnalyticEphemeris is the fallback — see
 * EphemerisFactory and the notes in AnalyticEphemeris.
 */
interface EphemerisProviderInterface
{
    /**
     * Geocentric tropical positions for the nine grahas at the given instant.
     *
     * @param float $jdUt Julian Day in Universal Time
     * @return array<string, array{lon: float, lat: float, speed: float, retro: bool}>
     *         keyed by 'Sun','Moon','Mercury','Venus','Mars','Jupiter',
     *         'Saturn','Rahu','Ketu'. lon/lat in degrees; speed in °/day
     *         (longitude); retro true when moving backwards through the zodiac.
     */
    public function positions(float $jdUt): array;

    /** Human-readable identifier for logs / output (e.g. "Swiss Ephemeris"). */
    public function name(): string;
}
