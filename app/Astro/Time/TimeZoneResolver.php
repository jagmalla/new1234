<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Time;

/**
 * Date-aware UTC offset resolution for a named IANA timezone.
 *
 * The astronomy engine works in Universal Time and only needs a single number:
 * the birth place's offset east of Greenwich (see JulianDay). That offset is NOT
 * constant for a place — regions that observe Daylight Saving Time are (say)
 * UTC-8 in winter and UTC-7 in summer, and some regions have CHANGED their rules
 * over the years. So the correct offset depends on the *exact date* in question.
 *
 * Given an IANA zone id (e.g. "America/Vancouver") and a wall-clock date this
 * returns the offset that actually applied on that date, using PHP's bundled
 * IANA timezone database — which already encodes the historical rules for e.g.:
 *   - Saskatchewan  (America/Regina):    permanent Standard Time (UTC-6) since 1966
 *   - Yukon         (America/Whitehorse) permanent DST (UTC-7) since 2020
 *
 * For rules that are enacted but not yet shipped in the bundled tz database, the
 * OVERRIDES table below takes precedence (see British Columbia, below).
 *
 * When the zone is unknown/empty the caller should fall back to the manually
 * entered numeric offset, so this class never guesses.
 */
final class TimeZoneResolver
{
    /**
     * Fixed-offset windows that OVERRIDE the bundled IANA database, for rules
     * that are law but not yet reflected in the shipped tz data.
     *
     * Each entry: [fromYmd (inclusive), toYmd|null (exclusive), offsetHours, note].
     *
     * British Columbia adopts permanent Daylight Saving Time: from the 2026
     * spring-forward date it stays on UTC-7 year-round and no longer falls back
     * to UTC-8 in winter. Dates before that keep the normal switching rules from
     * the IANA database.
     */
    private const OVERRIDES = [
        'America/Vancouver' => [
            ['2026-03-08', null, -7.0, 'BC permanent DST (no fall-back after 2026-03-08)'],
        ],
    ];

    /**
     * UTC offset in hours (east positive) for a zone on a given wall-clock date,
     * or null when the zone id is empty/unknown.
     */
    public static function offsetHours(
        string $zoneId,
        int $year,
        int $month,
        int $day,
        int $hour = 12,
        int $minute = 0
    ): ?float {
        $zoneId = trim($zoneId);
        if ($zoneId === '') {
            return null;
        }

        $ymd = sprintf('%04d-%02d-%02d', $year, $month, $day);

        $override = self::override($zoneId, $ymd);
        if ($override !== null) {
            return $override;
        }

        try {
            $tz = new \DateTimeZone($zoneId);
        } catch (\Exception $e) {
            return null; // unknown zone -> caller falls back to the manual offset
        }

        $dt = \DateTime::createFromFormat(
            'Y-m-d H:i',
            sprintf('%s %02d:%02d', $ymd, $hour, $minute),
            $tz
        );
        if ($dt === false) {
            return null;
        }

        return $tz->getOffset($dt) / 3600.0;
    }

    /** Enacted-but-unshipped rule for a zone/date, or null when none applies. */
    private static function override(string $zoneId, string $ymd): ?float
    {
        foreach (self::OVERRIDES[$zoneId] ?? [] as [$from, $to, $offset]) {
            if ($ymd >= $from && ($to === null || $ymd < $to)) {
                return $offset;
            }
        }
        return null;
    }
}
