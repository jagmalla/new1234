<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the Poorva-Shaap catalogue + remedies (migration 026). DB rows override
 * the baked {@see ShaapData} by rule id; falls back to the full baked catalogue
 * when the DB is unavailable.
 */
final class ShaapRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   rules: list<array{id:string,cat:string,ref:string,type:string,rule:string,result:string}>,
     *   remedies: array<string,array{ref:string,upaay:string}>,
     *   config: array<string,string>
     * }
     */
    public static function load(string $language = 'hi'): array
    {
        self::$lastError = null;
        $rules = ShaapData::RULES;
        $remedies = [];
        foreach (ShaapData::REMEDIES as $m) { $remedies[$m['cat']] = ['ref' => $m['ref'], 'upaay' => $m['upaay']]; }
        $config = ['shaap_show' => '1', 'shaap_autodetect' => '1'];

        try {
            $pdo = Database::pdo();
            $byId = [];
            foreach ($rules as $i => $r) { $byId[$r['id']] = $i; }

            $stmt = $pdo->prepare('SELECT rule_id, category, ref, shaap_type, rule_text, result_text FROM shaap_rule WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $id = (string) $r['rule_id'];
                $rec = [
                    'id' => $id, 'cat' => (string) $r['category'], 'ref' => (string) $r['ref'],
                    'type' => (string) $r['shaap_type'], 'rule' => (string) $r['rule_text'], 'result' => (string) $r['result_text'],
                ];
                if (isset($byId[$id])) { $rules[$byId[$id]] = $rec; }
                else { $rules[] = $rec; }
            }

            $stmt = $pdo->prepare('SELECT category, ref, upaay_text FROM shaap_remedy WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) { $remedies[(string) $r['category']] = ['ref' => (string) $r['ref'], 'upaay' => (string) $r['upaay_text']]; }

            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    $k = (string) $r['cfg_key'];
                    if (array_key_exists($k, $config)) { $config[$k] = (string) $r['cfg_value']; }
                }
            } catch (Throwable $e) { /* config optional */ }

            return ['rules' => $rules, 'remedies' => $remedies, 'config' => $config];
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Shaap rules load failed (using baked fallback): ' . $e->getMessage());
            return ['rules' => $rules, 'remedies' => $remedies, 'config' => $config];
        }
    }
}
