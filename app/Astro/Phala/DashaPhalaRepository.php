<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Core\Database;
use PDO;
use Throwable;

/**
 * Reads the editable Mahadasha/Antardasha summaries from the dasha_phala table
 * (see migrations/002_dasha_phala.sql). One row per (maha_lord, antar_lord,
 * language). Lookups are resilient: if the database is unavailable or the
 * combination has no text yet, find() returns null and callers show a friendly
 * "not available yet" placeholder instead of erroring.
 */
final class DashaPhalaRepository
{
    /** The nine Vimshottari dasha lords, in display order (Sun … Ketu). */
    public const LORDS = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];

    /** Hindi labels for the dropdowns (English value kept as the storage key). */
    public const LORDS_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];

    /**
     * One combination's phala text, or null if absent / DB unreachable.
     *
     * @return array{positive_text:?string, negative_text:?string, remedy_text:?string}|null
     */
    public static function find(string $maha, string $antar, string $language = 'hi'): ?array
    {
        if (!in_array($maha, self::LORDS, true) || !in_array($antar, self::LORDS, true)) {
            return null;
        }

        try {
            $stmt = Database::pdo()->prepare(
                'SELECT positive_text, negative_text, remedy_text
                   FROM dasha_phala
                  WHERE maha_lord = ? AND antar_lord = ? AND language = ?
                  LIMIT 1'
            );
            $stmt->execute([$maha, $antar, $language]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            error_log('DashaPhala lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}
