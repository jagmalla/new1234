<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the calculated Dasha-engine tables (migration 011): per-planet text by
 * house-from-Lagna (maha & antar), antar-from-mahalord text by position, and
 * optional bhavesh overrides. Resilient — returns null with lastError() set
 * when the database is unreachable. {@see DashaPhalEngine} consumes this.
 */
final class DashaEngineRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   lagna: array<string,array<string,array<int,array{pos:string,neg:string,rem:string}>>>,
     *   antarMaha: array<int,array{verdict:string,pos:string,neg:string,rem:string}>,
     *   bhavesh: array<int,array<int,array{pos:string,neg:string,rem:string}>>,
     *   config: array<string,string>
     * }|null
     */
    public static function load(string $language = 'hi'): ?array
    {
        self::$lastError = null;
        try {
            $pdo = Database::pdo();
            $out = ['lagna' => ['maha' => [], 'antar' => []], 'antarMaha' => [], 'bhavesh' => [], 'config' => []];

            $stmt = $pdo->prepare('SELECT context, planet, house_from_lagna, positive_text, negative_text, remedy_text FROM dasha_lord_from_lagna WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $out['lagna'][(string) $r['context']][(string) $r['planet']][(int) $r['house_from_lagna']] = [
                    'pos' => (string) ($r['positive_text'] ?? ''), 'neg' => (string) ($r['negative_text'] ?? ''),
                    'rem' => (string) ($r['remedy_text'] ?? ''),
                ];
            }

            $stmt = $pdo->prepare('SELECT antar_planet, house_from_maha_lord, verdict, positive_text, negative_text, remedy_text FROM dasha_antar_from_maha WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                // ANY row is the base; a specific antar_planet row (if present) overrides.
                $h = (int) $r['house_from_maha_lord'];
                $row = [
                    'verdict' => (string) ($r['verdict'] ?? ''), 'pos' => (string) ($r['positive_text'] ?? ''),
                    'neg' => (string) ($r['negative_text'] ?? ''), 'rem' => (string) ($r['remedy_text'] ?? ''),
                ];
                if ((string) $r['antar_planet'] === 'ANY') {
                    $out['antarMaha'][$h] = $out['antarMaha'][$h] ?? $row;
                } else {
                    $out['antarMaha'][$h] = $row; // specific wins
                }
            }

            try {
                $stmt = $pdo->prepare('SELECT lord_of_house, placed_in_house, positive_text, negative_text, remedy_text FROM dasha_bhavesh_phala WHERE language = ?');
                $stmt->execute([$language]);
                foreach ($stmt as $r) {
                    $out['bhavesh'][(int) $r['lord_of_house']][(int) $r['placed_in_house']] = [
                        'pos' => (string) ($r['positive_text'] ?? ''), 'neg' => (string) ($r['negative_text'] ?? ''),
                        'rem' => (string) ($r['remedy_text'] ?? ''),
                    ];
                }
            } catch (Throwable $e) { /* optional overrides */ }

            try {
                foreach ($pdo->query("SELECT cfg_key, cfg_value FROM house_engine_config WHERE cfg_key LIKE 'dasha_engine_%'") as $r) {
                    $out['config'][(string) $r['cfg_key']] = (string) $r['cfg_value'];
                }
            } catch (Throwable $e) { /* optional config */ }

            return $out;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Dasha engine rules load failed: ' . $e->getMessage());
            return null;
        }
    }
}
