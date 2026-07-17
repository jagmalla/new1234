<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Milan;

use AutoBusiness\Core\Database;
use PDO;
use Throwable;

/**
 * Loads the editable Guna-Milan text tables (migration 014) — koota phal,
 * parihara, summary sentences, the milan_* config keys, and (optionally) the
 * lookup-matrix overrides an owner may tweak. Resilient: on a DB error load()
 * returns null and {@see GunaMilan} falls back to its baked classical data, so
 * the page still scores. lastError() lets a staff page surface the reason.
 */
final class MilanRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   koota_phal: array<string,string>, parihara: array<string,string>,
     *   summary: array<string,string>, config: array<string,string>,
     *   yoni: array<string,array<string,float>>, vashya: array<string,array<string,float>>,
     *   gana: array<string,array<string,float>>
     * }|null
     */
    public static function load(string $language = 'hi'): ?array
    {
        self::$lastError = null;
        try {
            $pdo = Database::pdo();
            $rules = [
                'koota_phal' => [], 'parihara' => [], 'summary' => [],
                'config' => [], 'yoni' => [], 'vashya' => [], 'gana' => [],
            ];

            $stmt = $pdo->prepare('SELECT koota, outcome_key, phal_text FROM milan_koota_phal WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $rules['koota_phal'][$r['koota'] . '|' . $r['outcome_key']] = (string) $r['phal_text'];
            }
            $stmt = $pdo->prepare('SELECT rule_key, phal_text FROM milan_parihara WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $rules['parihara'][(string) $r['rule_key']] = (string) $r['phal_text'];
            }
            $stmt = $pdo->prepare('SELECT rule_key, phal_text FROM milan_summary WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $rules['summary'][(string) $r['rule_key']] = (string) $r['phal_text'];
            }
            foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                if (str_starts_with((string) $r['cfg_key'], 'milan_')) {
                    $rules['config'][(string) $r['cfg_key']] = (string) $r['cfg_value'];
                }
            }
            // Optional matrix overrides — only used when present (else baked).
            foreach ($pdo->query('SELECT yoni_a, yoni_b, points FROM milan_yoni_matrix') as $r) {
                $rules['yoni'][(string) $r['yoni_a']][(string) $r['yoni_b']] = (float) $r['points'];
            }
            foreach ($pdo->query('SELECT grp_boy, grp_girl, points FROM milan_vashya_matrix') as $r) {
                $rules['vashya'][(string) $r['grp_boy']][(string) $r['grp_girl']] = (float) $r['points'];
            }
            foreach ($pdo->query('SELECT gana_boy, gana_girl, points FROM milan_gana_matrix') as $r) {
                $rules['gana'][(string) $r['gana_boy']][(string) $r['gana_girl']] = (float) $r['points'];
            }

            return $rules;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Guna Milan rules load failed: ' . $e->getMessage());
            return null;
        }
    }
}
