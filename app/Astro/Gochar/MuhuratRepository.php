<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Gochar;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the Muhurat rule set (migration 022): tithi names/meanings, disha-shul
 * table, janma-nakshatra weekday phal and combustion warnings. DB rows override
 * the baked {@see MuhuratData}; falls back to baked data when the DB is down.
 */
final class MuhuratRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   tithi: array<int,array{name:string,lord:string,meaning:string}>,
     *   disha: array<int,array{dir:string,lord:string}>,
     *   nak_vaar: array<int,array{phal:string,bonus:array<int>,bonus_note:string,cond:string}>,
     *   combust: array<string,string>,
     *   config: array<string,string>
     * }
     */
    public static function load(string $language = 'hi'): array
    {
        self::$lastError = null;
        $out = [
            'tithi' => MuhuratData::TITHI,
            'disha' => MuhuratData::DISHA_SHUL,
            'nak_vaar' => MuhuratData::NAK_VAAR_PHAL,
            'combust' => MuhuratData::COMBUST_WARN,
            'config' => [
                'muhurat_show' => '1',
                'muhurat_rahu_kaal' => '1',
                'muhurat_disha_shul' => '1',
                'muhurat_tithi' => '1',
                'muhurat_janma_nak' => '1',
                'muhurat_combust' => '1',
                'muhurat_kashta' => '1',
                'muhurat_sunrise' => '6.0',
                'muhurat_sunset' => '18.0',
            ],
        ];

        try {
            $pdo = Database::pdo();

            $stmt = $pdo->prepare('SELECT tithi_no, name, lord, meaning FROM muhurat_tithi WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $out['tithi'][(int) $r['tithi_no']] = [
                    'name' => (string) $r['name'], 'lord' => (string) $r['lord'], 'meaning' => (string) $r['meaning'],
                ];
            }

            $stmt = $pdo->prepare('SELECT weekday, forbidden_dir, dir_lord FROM muhurat_disha_shul WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $out['disha'][(int) $r['weekday']] = ['dir' => (string) $r['forbidden_dir'], 'lord' => (string) $r['dir_lord']];
            }

            $stmt = $pdo->prepare('SELECT weekday, phal_text, bonus_tithis, bonus_note, cond_text FROM muhurat_nak_vaar_phal WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $bonus = array_values(array_filter(array_map('intval', explode(',', (string) $r['bonus_tithis'])), static fn($n) => $n > 0));
                $out['nak_vaar'][(int) $r['weekday']] = [
                    'phal' => (string) $r['phal_text'], 'bonus' => $bonus,
                    'bonus_note' => (string) $r['bonus_note'], 'cond' => (string) $r['cond_text'],
                ];
            }

            $stmt = $pdo->prepare('SELECT planet, warn_text FROM muhurat_combust WHERE language = ?');
            $stmt->execute([$language]);
            $cw = [];
            foreach ($stmt as $r) { $cw[(string) $r['planet']] = (string) $r['warn_text']; }
            if ($cw !== []) { $out['combust'] = $cw + $out['combust']; }

            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    $k = (string) $r['cfg_key'];
                    if (array_key_exists($k, $out['config'])) { $out['config'][$k] = (string) $r['cfg_value']; }
                }
            } catch (Throwable $e) { /* config optional */ }

            return $out;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Muhurat rules load failed (using baked fallback): ' . $e->getMessage());
            return $out;
        }
    }
}
