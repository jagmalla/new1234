<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Varshesh;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the Varshesh phal rules (migration 018): the 21 band-graded phal
 * (7 planets x पूर्ण/मध्यम/हीन) and the shloka 37–44 / 13 / 18 special-modifier
 * texts. Resilient like the Saham/Tajik/Gochar repositories — on a DB error it
 * returns the baked {@see VarsheshData} defaults so the panel still works;
 * lastError() surfaces the reason. Band thresholds and the tie/fallback rules
 * come from the house_engine_config gochar-style keys.
 */
final class VarsheshRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   phal: array<string,array<string,string>>,
     *   special: array<string,array{situation:string,text:string}>,
     *   config: array<string,string>
     * }
     */
    public static function load(string $language = 'hi'): array
    {
        self::$lastError = null;
        $out = [
            'phal' => VarsheshData::PHAL,
            'special' => self::bakedSpecial(),
            'config' => self::defaultConfig(),
        ];
        try {
            $pdo = Database::pdo();

            $phal = [];
            $stmt = $pdo->prepare('SELECT planet, bala_band, phal_text FROM varshesh_phal WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $phal[(string) $r['planet']][(string) $r['bala_band']] = (string) $r['phal_text'];
            }
            if ($phal !== []) {
                foreach ($phal as $p => $bands) {
                    foreach ($bands as $b => $t) { $out['phal'][$p][$b] = $t; }
                }
            }

            $sp = [];
            $stmt = $pdo->prepare('SELECT rule_key, situation, phal_text FROM varshesh_special_rules WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $sp[(string) $r['rule_key']] = [
                    'situation' => (string) $r['situation'], 'text' => (string) $r['phal_text'],
                ];
            }
            if ($sp !== []) { $out['special'] = $sp + $out['special']; }

            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    if (str_starts_with((string) $r['cfg_key'], 'varshesh_')) {
                        $out['config'][(string) $r['cfg_key']] = (string) $r['cfg_value'];
                    }
                }
            } catch (Throwable $e) { /* config optional */ }

            return $out;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Varshesh rules load failed (using baked fallback): ' . $e->getMessage());
            return $out;
        }
    }

    /** @return array<string,array{situation:string,text:string}> */
    private static function bakedSpecial(): array
    {
        $out = [];
        foreach (VarsheshData::SPECIAL as $key => $r) {
            $out[$key] = ['situation' => (string) $r[0], 'text' => (string) $r[1]];
        }
        return $out;
    }

    /** @return array<string,string> */
    private static function defaultConfig(): array
    {
        return [
            'varshesh_full_min' => '45',
            'varshesh_madhya_min' => '30',
            'varshesh_tie_rule' => 'varsha_lagnesh',
            'varshesh_fallback' => 'munthesh',
            'varshesh_lagna_drishti_min' => '1',
            'varshesh_check_natal' => '1',
            'varshesh_display' => 'both',
        ];
    }
}
