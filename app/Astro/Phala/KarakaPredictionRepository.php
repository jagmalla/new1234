<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Core\Database;
use PDO;
use Throwable;

/**
 * Loads the three editable Karaka-Prediction rule tables
 * (see migrations/007_karaka_prediction.sql) into arrays for the generator
 * ({@see KarakaPrediction}). Resilient: returns null with lastError() set when
 * the database is unreachable.
 */
final class KarakaPredictionRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   map: array<string,array{title:string,houses:list<int>,signifies:string}>,
     *   meaning: array<string,array<int,array{meaning:string,lagna:string}>>,
     *   sent: array<string,string>,
     *   yuti_generic: array<string,array{key:string,tpl:string,gb:string,score:float}>,
     *   yuti_special: array<string,array{key:string,tpl:string,gb:string,score:float}>,
     *   loss: array<string,string>,
     *   config: array<string,string>
     * }|null
     */
    public static function load(string $language = 'hi'): ?array
    {
        self::$lastError = null;

        try {
            $pdo = Database::pdo();
            $rules = ['map' => [], 'meaning' => [], 'sent' => [],
                'yuti_generic' => [], 'yuti_special' => [], 'loss' => [], 'config' => []];

            foreach ($pdo->query('SELECT planet, title_heading, houses_judged, signifies FROM karaka_map') as $r) {
                $rules['map'][(string) $r['planet']] = [
                    'title' => (string) $r['title_heading'],
                    'houses' => self::houseList((string) $r['houses_judged']),
                    'signifies' => (string) $r['signifies'],
                ];
            }
            foreach ($pdo->query('SELECT karaka, house, karaka_meaning, lagna_view FROM karaka_house_meaning') as $r) {
                $rules['meaning'][(string) $r['karaka']][(int) $r['house']] = [
                    'meaning' => (string) $r['karaka_meaning'],
                    'lagna' => (string) $r['lagna_view'],
                ];
            }
            $stmt = $pdo->prepare('SELECT rule_key, sentence_template FROM karaka_sentences WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $rules['sent'][(string) $r['rule_key']] = (string) $r['sentence_template'];
            }

            // --- Fix v2 (migration 010): yuti rules, combustion loss, config ---
            try {
                $stmt = $pdo->prepare('SELECT rule_key, karaka, with_planet, sentence_template, good_bad, score FROM karaka_yuti_rules WHERE language = ?');
                $stmt->execute([$language]);
                foreach ($stmt as $r) {
                    $row = [
                        'key' => (string) $r['rule_key'], 'tpl' => (string) $r['sentence_template'],
                        'gb' => (string) $r['good_bad'], 'score' => (float) $r['score'],
                    ];
                    if ($r['karaka'] !== null && $r['with_planet'] !== null) {
                        // Special: keyed by "Karaka|Companion".
                        $rules['yuti_special'][(string) $r['karaka'] . '|' . (string) $r['with_planet']] = $row;
                    } elseif ($r['with_planet'] !== null) {
                        // Generic class: keyed by rule_key (k_with_jupiter, …).
                        $rules['yuti_generic'][(string) $r['rule_key']] = $row;
                    }
                }
            } catch (Throwable $e) { /* karaka_yuti_rules optional until migration 010 */ }

            try {
                $stmt = $pdo->prepare('SELECT planet, loss_text FROM karaka_combust_loss WHERE language = ?');
                $stmt->execute([$language]);
                foreach ($stmt as $r) {
                    $rules['loss'][(string) $r['planet']] = (string) $r['loss_text'];
                }
            } catch (Throwable $e) { /* karaka_combust_loss optional */ }

            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    $rules['config'][(string) $r['cfg_key']] = (string) $r['cfg_value'];
                }
            } catch (Throwable $e) { /* house_engine_config optional */ }

            return $rules;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Karaka rules load failed: ' . $e->getMessage());
            return null;
        }
    }

    private static function houseList(string $csv): array
    {
        $out = [];
        foreach (explode(',', $csv) as $x) {
            $x = trim($x);
            if ($x !== '' && ctype_digit($x)) {
                $out[] = (int) $x;
            }
        }
        return $out;
    }
}
