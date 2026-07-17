<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Ephemeris;

use AutoBusiness\Core\Env;

/**
 * Selects the best available ephemeris provider:
 *   1. Swiss Ephemeris (swetest binary) when SWETEST_PATH is set, the binary
 *      exists, and shell-exec is enabled — gold-standard accuracy.
 *   2. Pure-PHP AnalyticEphemeris otherwise — always works, ~1-2' accuracy.
 *
 * Set SWETEST_PATH (and optionally SWEPH_PATH for the .se1 data files) in .env
 * to enable Swiss Ephemeris. The engine behaves identically either way; only the
 * underlying precision differs.
 */
final class EphemerisFactory
{
    public static function create(): EphemerisProviderInterface
    {
        // Node type: 'true' (osculating) by default — matches Parashara's Light,
        // whose Rahu/Ketu sit on the true node (e.g. the reference D9 places Rahu
        // with the Lagna and Ketu with Mercury). The retrograde "(R)" marker stays
        // suppressed for the nodes either way. Admin-overridable via .env
        // NODE_TYPE=mean (classical mean node, reverse year-round).
        $nodeType = strtolower(Env::get('NODE_TYPE', 'true') ?? 'true') === 'mean' ? 'mean' : 'true';

        $binary = Env::get('SWETEST_PATH');
        if (SwissEphemeris::isAvailable($binary)) {
            return new SwissEphemeris((string) $binary, Env::get('SWEPH_PATH'), $nodeType);
        }
        return new AnalyticEphemeris($nodeType);
    }
}
