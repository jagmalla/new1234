<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Gochar;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the Gochar Ashtakavarga phal (migration 021): bindu-count phal
 * (Part 1), Kaksha phal (Part 2) and SAV hints (Part 3); baked
 * {@see GocharAvData} as the DB-down fallback.
 */
final class GocharAvRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   bindu: array<string,array<int,string>>,
     *   kaksha: array<string,array<string,array{shubh:string,rekha:string}>>,
     *   sav: array<string,string>,
     *   config: array<string,string>
     * }
     */
    public static function load(string $language = 'hi'): array
    {
        self::$lastError = null;
        $out = [
            'bindu' => self::bakedBindu(),
            'kaksha' => self::bakedKaksha(),
            'sav' => GocharAvData::SAV,
            'config' => ['gochar_av_bindu_phal' => '1', 'gochar_kaksha_phal' => '1', 'gochar_sav_hint' => '1'],
        ];
        try {
            $pdo = Database::pdo();

            $bp = [];
            $stmt = $pdo->prepare('SELECT planet, bindu, phal_text FROM gochar_bindu_phal WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) { $bp[(string) $r['planet']][(int) $r['bindu']] = (string) $r['phal_text']; }
            if ($bp !== []) { foreach ($bp as $p => $rows) { foreach ($rows as $b => $t) { $out['bindu'][$p][$b] = $t; } } }

            $kk = [];
            $stmt = $pdo->prepare('SELECT planet, kaksha_lord, kind, phal_text FROM gochar_kaksha_phal WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) { $kk[(string) $r['planet']][(string) $r['kaksha_lord']][(string) $r['kind']] = (string) $r['phal_text']; }
            if ($kk !== []) {
                foreach ($kk as $p => $lords) {
                    foreach ($lords as $l => $kinds) {
                        foreach ($kinds as $k => $t) { $out['kaksha'][$p][$l][$k] = $t; }
                    }
                }
            }

            $sv = [];
            foreach ($pdo->query('SELECT band, hint_text FROM gochar_sav_hint') as $r) { $sv[(string) $r['band']] = (string) $r['hint_text']; }
            if ($sv !== []) { $out['sav'] = $sv + $out['sav']; }

            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    $k = (string) $r['cfg_key'];
                    if ($k === 'gochar_av_bindu_phal' || $k === 'gochar_kaksha_phal' || $k === 'gochar_sav_hint') {
                        $out['config'][$k] = (string) $r['cfg_value'];
                    }
                }
            } catch (Throwable $e) { /* config optional */ }

            return $out;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Gochar AV phal load failed (using baked fallback): ' . $e->getMessage());
            return $out;
        }
    }

    /** @return array<string,array<int,string>> */
    private static function bakedBindu(): array
    {
        $out = [];
        foreach (GocharAvData::BINDU as $p => $rows) { foreach ($rows as $b => $t) { $out[$p][$b] = (string) $t; } }
        return $out;
    }

    /** @return array<string,array<string,array{shubh:string,rekha:string}>> */
    private static function bakedKaksha(): array
    {
        $out = [];
        foreach (GocharAvData::KAKSHA as $p => $lords) {
            foreach ($lords as $l => $kv) { $out[$p][$l] = ['shubh' => (string) $kv[0], 'rekha' => (string) $kv[1]]; }
        }
        return $out;
    }
}
