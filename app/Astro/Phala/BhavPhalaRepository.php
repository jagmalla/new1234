<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Core\Database;
use PDO;
use Throwable;

/**
 * Reads the two Planet-Prediction tables (see migrations/004_planet_phala.sql):
 *
 *   bhavesh_phal      — text for the LORD of house L when placed in house P
 *                       (12x12 grid). "Bhavesh Phal".
 *   graha_bhava_phal  — text for a PLANET placed in a house (Graha-in-Bhava).
 *                       Populated later; lookups return null until then.
 *
 * Lookups are resilient: a missing row or an unreachable database returns null
 * and the caller shows a friendly placeholder. The last DB error is recorded so
 * a staff page can surface the real reason instead of a silent blank.
 */
final class BhavPhalaRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /** Bhavesh Phal: prediction for the lord of $lordHouse placed in $placedHouse. */
    public static function bhavesh(int $lordHouse, int $placedHouse, string $language = 'hi'): ?string
    {
        if ($lordHouse < 1 || $lordHouse > 12 || $placedHouse < 1 || $placedHouse > 12) {
            return null;
        }

        try {
            $stmt = Database::pdo()->prepare(
                'SELECT prediction_text FROM bhavesh_phal
                  WHERE lord_house = ? AND placed_house = ? AND language = ? LIMIT 1'
            );
            $stmt->execute([$lordHouse, $placedHouse, $language]);
            $val = $stmt->fetchColumn();

            return $val === false ? null : (string) $val;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('BhavPhala bhavesh lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Graha-in-Bhava: prediction for $planet placed in $placedHouse (empty for now). */
    public static function grahaBhava(string $planet, int $placedHouse, string $language = 'hi'): ?string
    {
        if ($placedHouse < 1 || $placedHouse > 12) {
            return null;
        }

        try {
            $stmt = Database::pdo()->prepare(
                'SELECT prediction_text FROM graha_bhava_phal
                  WHERE planet = ? AND placed_house = ? AND language = ? LIMIT 1'
            );
            $stmt->execute([$planet, $placedHouse, $language]);
            $val = $stmt->fetchColumn();

            return $val === false ? null : (string) $val;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('BhavPhala grahaBhava lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}
