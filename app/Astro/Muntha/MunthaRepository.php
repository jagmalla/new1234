<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muntha;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the Muntha phal rules (migration 019): the 12 bhava texts, the 9
 * graha/rashi/Rahu-zone rules and the shloka 3/17–23/33–36 special-modifier
 * texts. Resilient like the Saham/Tajik/Gochar/Varshesh repositories — on a DB
 * error it returns the baked {@see MunthaData} defaults; lastError() surfaces
 * the reason.
 */
final class MunthaRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   bhava: array<int,array{band:string,text:string}>,
     *   graha: array<string,array{situation:string,text:string,nature:string}>,
     *   special: array<string,array{situation:string,text:string}>,
     *   config: array<string,string>
     * }
     */
    public static function load(string $language = 'hi'): array
    {
        self::$lastError = null;
        $out = [
            'bhava' => self::bakedBhava(),
            'graha' => self::bakedGraha(),
            'special' => self::bakedSpecial(),
            'config' => self::defaultConfig(),
        ];
        try {
            $pdo = Database::pdo();

            $bh = [];
            $stmt = $pdo->prepare('SELECT house, band, phal_text FROM muntha_bhava_phal WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $bh[(int) $r['house']] = ['band' => (string) $r['band'], 'text' => (string) $r['phal_text']];
            }
            if ($bh !== []) { foreach ($bh as $h => $e) { $out['bhava'][$h] = $e; } }

            $gr = [];
            $stmt = $pdo->prepare('SELECT rule_key, situation, phal_text, nature FROM muntha_graha_phal WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $gr[(string) $r['rule_key']] = [
                    'situation' => (string) $r['situation'], 'text' => (string) $r['phal_text'], 'nature' => (string) $r['nature'],
                ];
            }
            if ($gr !== []) { $out['graha'] = $gr + $out['graha']; }

            $sp = [];
            $stmt = $pdo->prepare('SELECT rule_key, situation, phal_text FROM muntha_special_rules WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $sp[(string) $r['rule_key']] = ['situation' => (string) $r['situation'], 'text' => (string) $r['phal_text']];
            }
            if ($sp !== []) { $out['special'] = $sp + $out['special']; }

            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    if (str_starts_with((string) $r['cfg_key'], 'muntha_')) {
                        $out['config'][(string) $r['cfg_key']] = (string) $r['cfg_value'];
                    }
                }
            } catch (Throwable $e) { /* config optional */ }

            return $out;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Muntha rules load failed (using baked fallback): ' . $e->getMessage());
            return $out;
        }
    }

    /** @return array<int,array{band:string,text:string}> */
    private static function bakedBhava(): array
    {
        $out = [];
        foreach (MunthaData::BHAVA as $h => $e) { $out[$h] = ['band' => (string) $e[0], 'text' => (string) $e[1]]; }
        return $out;
    }

    /** @return array<string,array{situation:string,text:string,nature:string}> */
    private static function bakedGraha(): array
    {
        $out = [];
        foreach (MunthaData::GRAHA as $k => $e) {
            $out[$k] = ['situation' => (string) $e[0], 'text' => (string) $e[1], 'nature' => (string) $e[2]];
        }
        return $out;
    }

    /** @return array<string,array{situation:string,text:string}> */
    private static function bakedSpecial(): array
    {
        $out = [];
        foreach (MunthaData::SPECIAL as $k => $e) { $out[$k] = ['situation' => (string) $e[0], 'text' => (string) $e[1]]; }
        return $out;
    }

    /** @return array<string,string> */
    private static function defaultConfig(): array
    {
        return [
            'muntha_daily_motion' => '0',
            'muntha_rahu_mode' => 'bhogya',
            'muntha_rahu_prishtha' => 'ashubh',
            'muntha_use_tajik_drishti' => '1',
            'muntha_janma_bridge' => '1',
        ];
    }
}
