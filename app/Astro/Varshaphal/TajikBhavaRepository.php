<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Varshaphal;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the Tajik-Neelakanthi Bhava-Phal rule catalogue (migration 023). DB
 * rows (edited by staff) override the baked {@see TajikBhavaData} by rule_id;
 * falls back to the full baked catalogue when the DB is unavailable.
 */
final class TajikBhavaRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   rules: list<array{id:string,h:int,sh:string,cat:string,cond:string,phal:string,note:string}>,
     *   config: array<string,string>
     * }
     */
    public static function load(string $language = 'hi'): array
    {
        self::$lastError = null;
        $rules = TajikBhavaData::RULES;
        $config = ['tajik_bhava_show' => '1', 'tajik_bhava_autofire' => '1'];

        try {
            $pdo = Database::pdo();
            $byId = [];
            foreach ($rules as $i => $r) { $byId[$r['id']] = $i; }

            $stmt = $pdo->prepare(
                'SELECT rule_id, house, shloka, category, cond_text, phal_text, note_text
                 FROM tajik_bhava_phal WHERE language = ?'
            );
            $stmt->execute([$language]);
            foreach ($stmt as $row) {
                $id = (string) $row['rule_id'];
                $rec = [
                    'id' => $id,
                    'h' => (int) $row['house'],
                    'sh' => (string) $row['shloka'],
                    'cat' => (string) $row['category'],
                    'cond' => (string) $row['cond_text'],
                    'phal' => (string) $row['phal_text'],
                    'note' => (string) $row['note_text'],
                ];
                if (isset($byId[$id])) { $rules[$byId[$id]] = $rec; }
                else { $rules[] = $rec; }
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
            error_log('Tajik Bhava phal load failed (using baked fallback): ' . $e->getMessage());
            return ['rules' => $rules, 'config' => $config];
        }
    }
}
