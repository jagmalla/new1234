<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the Phaladeepika yoga catalogue (migration 025). DB rows override the
 * baked {@see PhaladeepikaYogaData} by yoga id; falls back to the full baked
 * catalogue when the DB is unavailable.
 */
final class PhaladeepikaYogaRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   yogas: list<array{id:string,hi:string,en:string,cat:string,ref:string,type:string,rule:string,result:string}>,
     *   config: array<string,string>
     * }
     */
    public static function load(string $language = 'hi'): array
    {
        self::$lastError = null;
        $yogas = PhaladeepikaYogaData::YOGAS;
        $config = ['phala_yoga_show' => '1', 'phala_yoga_autodetect' => '1'];

        try {
            $pdo = Database::pdo();
            $byId = [];
            foreach ($yogas as $i => $y) { $byId[$y['id']] = $i; }

            $stmt = $pdo->prepare(
                'SELECT yoga_id, yoga_hi, yoga_en, category, ref, yoga_type, rule_text, result_text
                 FROM phaladeepika_yoga WHERE language = ?'
            );
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $id = (string) $r['yoga_id'];
                $rec = [
                    'id' => $id, 'hi' => (string) $r['yoga_hi'], 'en' => (string) $r['yoga_en'],
                    'cat' => (string) $r['category'], 'ref' => (string) $r['ref'],
                    'type' => (string) $r['yoga_type'], 'rule' => (string) $r['rule_text'],
                    'result' => (string) $r['result_text'],
                ];
                if (isset($byId[$id])) { $yogas[$byId[$id]] = $rec; }
                else { $yogas[] = $rec; }
            }

            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    $k = (string) $r['cfg_key'];
                    if (array_key_exists($k, $config)) { $config[$k] = (string) $r['cfg_value']; }
                }
            } catch (Throwable $e) { /* config optional */ }

            return ['yogas' => $yogas, 'config' => $config];
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Phaladeepika yoga load failed (using baked fallback): ' . $e->getMessage());
            return ['yogas' => $yogas, 'config' => $config];
        }
    }
}
