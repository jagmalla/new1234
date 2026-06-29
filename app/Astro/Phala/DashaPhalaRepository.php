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

    /** Last DB error from find() (null if the query ran without error). */
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * One combination's phala text, or null if absent / DB unreachable.
     * On a database error, the message is recorded in lastError() so a staff
     * page can show the real reason instead of a silent placeholder.
     *
     * @return array{positive_text:?string, negative_text:?string, remedy_text:?string}|null
     */
    public static function find(string $maha, string $antar, string $language = 'hi'): ?array
    {
        self::$lastError = null;

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
            self::$lastError = $e->getMessage();
            error_log('DashaPhala lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Connection / data diagnostics for the staff "why is it empty" check.
     *
     * @return array<string,mixed>
     */
    public static function diagnostics(string $maha, string $antar, string $language = 'hi'): array
    {
        $d = [
            'db_connected' => false,
            'db_name'      => (string) (\AutoBusiness\Core\Env::get('DB_NAME', '') ?? ''),
            'db_host'      => (string) (\AutoBusiness\Core\Env::get('DB_HOST', '127.0.0.1') ?? '127.0.0.1'),
            'table_exists' => false,
            'row_count'    => 0,
            'row_found'    => false,
            'error'        => null,
        ];

        try {
            $pdo = Database::pdo();
            $d['db_connected'] = true;
            $d['table_exists'] = $pdo->query("SHOW TABLES LIKE 'dasha_phala'")->fetchColumn() !== false;
            if ($d['table_exists']) {
                $d['row_count'] = (int) $pdo->query('SELECT COUNT(*) FROM dasha_phala')->fetchColumn();
                $d['row_found'] = self::find($maha, $antar, $language) !== null;
            }
        } catch (Throwable $e) {
            $d['error'] = $e->getMessage();
        }

        return $d;
    }
}
