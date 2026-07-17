<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Gochar;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the Shani Sade Sati / Paya / Lagna-functional data (migration 020),
 * baked {@see SadeSatiData} as the DB-down fallback — same resilient pattern as
 * the other Gochar repositories.
 */
final class SadeSatiRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   phal: array<string,string>,
     *   paya: array<int,array{metal:string,shubh:bool,effect:string}>,
     *   config: array<string,string>
     * }
     */
    public static function load(string $language = 'hi'): array
    {
        self::$lastError = null;
        $out = [
            'phal' => SadeSatiData::PHAL,
            'paya' => self::bakedPaya(),
            'config' => ['sadesati_paya_mode' => 'ingress', 'sadesati_show' => '1', 'sadesati_saturn_cycle' => '29.46'],
        ];
        try {
            $pdo = Database::pdo();

            $ph = [];
            $stmt = $pdo->prepare('SELECT phase_key, phal_text FROM sadesati_phal WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) { $ph[(string) $r['phase_key']] = (string) $r['phal_text']; }
            if ($ph !== []) { $out['phal'] = $ph + $out['phal']; }

            $py = [];
            foreach ($pdo->query('SELECT house_from_moon, metal_hi, shubh, effect_hi FROM shani_paya') as $r) {
                $py[(int) $r['house_from_moon']] = [
                    'metal' => (string) $r['metal_hi'], 'shubh' => (int) $r['shubh'] === 1, 'effect' => (string) $r['effect_hi'],
                ];
            }
            if ($py !== []) { $out['paya'] = $py + $out['paya']; }

            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    if (str_starts_with((string) $r['cfg_key'], 'sadesati_')) {
                        $out['config'][(string) $r['cfg_key']] = (string) $r['cfg_value'];
                    }
                }
            } catch (Throwable $e) { /* config optional */ }

            return $out;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Sade Sati rules load failed (using baked fallback): ' . $e->getMessage());
            return $out;
        }
    }

    /** @return array<int,array{metal:string,shubh:bool,effect:string}> */
    private static function bakedPaya(): array
    {
        $out = [];
        foreach (SadeSatiData::PAYA as $h => $e) {
            $out[$h] = ['metal' => (string) $e[0], 'shubh' => (bool) $e[1], 'effect' => (string) $e[2]];
        }
        return $out;
    }
}
