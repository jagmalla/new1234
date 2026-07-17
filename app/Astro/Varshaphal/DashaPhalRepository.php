<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Varshaphal;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the Tajik-Neelakanthi Dasha-Phal rule set (migration 024). DB rows
 * override the baked {@see DashaPhalData} by rule_id; the bala-tier thresholds
 * live in house_engine_config. Falls back to the full baked set when the DB is
 * unavailable.
 */
final class DashaPhalRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   rules: array<string,array{id:string,sec:string,sh:string,cat:string,cond:string,phal:string,note:string}>,
     *   config: array<string,string>
     * }
     */
    public static function load(string $language = 'hi'): array
    {
        self::$lastError = null;
        $rules = [];
        foreach (DashaPhalData::RULES as $r) { $rules[$r['id']] = $r; }
        // Bala-tier thresholds (Panchavargeeya bala, of 20) — configurable per
        // implementation note 2.
        $config = [
            'dasha_phal_show' => '1',
            'dasha_bala_purna' => '10',   // >= → पूर्णबल
            'dasha_bala_madhya' => '5',   // >= → मध्यबल
            'dasha_bala_hina' => '2.5',   // >= → स्वल्प/हीनबल; below → नष्टबल
        ];

        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                'SELECT rule_id, section, shloka, category, cond_text, phal_text, note_text
                 FROM dasha_phal WHERE language = ?'
            );
            $stmt->execute([$language]);
            foreach ($stmt as $row) {
                $id = (string) $row['rule_id'];
                $rules[$id] = [
                    'id' => $id, 'sec' => (string) $row['section'], 'sh' => (string) $row['shloka'],
                    'cat' => (string) $row['category'], 'cond' => (string) $row['cond_text'],
                    'phal' => (string) $row['phal_text'], 'note' => (string) $row['note_text'],
                ];
            }
            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    $k = (string) $r['cfg_key'];
                    if (array_key_exists($k, $config)) { $config[$k] = (string) $r['cfg_value']; }
                }
            } catch (Throwable $e) { /* config optional */ }

            return ['rules' => $rules, 'config' => $config];
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Dasha phal load failed (using baked fallback): ' . $e->getMessage());
            return ['rules' => $rules, 'config' => $config];
        }
    }
}
