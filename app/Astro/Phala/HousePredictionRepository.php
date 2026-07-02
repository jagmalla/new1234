<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Core\Database;
use PDO;
use Throwable;

/**
 * Loads the six editable House-Prediction rule tables
 * (see migrations/006_house_prediction.sql) into plain arrays for the generator
 * ({@see HousePrediction}). Resilient: if the database is unreachable, load()
 * returns null and the last error is recorded so a staff page can show it.
 */
final class HousePredictionRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   rashi: array<int,array{rashi:string,quality:string,element:string,lord:string}>,
     *   planet: array<string,array{element:string,nature:string}>,
     *   react: array<string,array<string,array{result:string,gb:string}>>,
     *   friend: array<string,array<string,string>>,
     *   nature: array<string,array{good:list<int>,bad:list<int>,notes:string}>,
     *   tpl: array<string,string>
     * }|null
     */
    public static function load(string $language = 'hi'): ?array
    {
        self::$lastError = null;

        try {
            $pdo = Database::pdo();

            $rules = ['rashi' => [], 'planet' => [], 'react' => [], 'friend' => [], 'nature' => [], 'tpl' => []];

            foreach ($pdo->query('SELECT rashi_num, rashi, quality, element, lord FROM rashi_elements') as $r) {
                $rules['rashi'][(int) $r['rashi_num']] = [
                    'rashi' => (string) $r['rashi'], 'quality' => (string) $r['quality'],
                    'element' => (string) $r['element'], 'lord' => (string) $r['lord'],
                ];
            }
            foreach ($pdo->query('SELECT planet, element, nature FROM planet_elements') as $r) {
                $rules['planet'][(string) $r['planet']] = ['element' => (string) $r['element'], 'nature' => (string) $r['nature']];
            }
            foreach ($pdo->query('SELECT planet_element, rashi_element, reaction_result, good_bad FROM element_reactions') as $r) {
                $rules['react'][(string) $r['planet_element']][(string) $r['rashi_element']] = [
                    'result' => (string) $r['reaction_result'], 'gb' => (string) $r['good_bad'],
                ];
            }
            foreach ($pdo->query('SELECT planet, toward_planet, relation FROM planet_friendship') as $r) {
                $rules['friend'][(string) $r['planet']][(string) $r['toward_planet']] = (string) $r['relation'];
            }
            foreach ($pdo->query('SELECT planet, good_houses, bad_houses, notes FROM planet_house_nature') as $r) {
                $rules['nature'][(string) $r['planet']] = [
                    'good' => self::houseList((string) $r['good_houses']),
                    'bad' => self::houseList((string) $r['bad_houses']),
                    'notes' => (string) $r['notes'],
                ];
            }
            $stmt = $pdo->prepare('SELECT rule_key, sentence_template FROM house_pred_templates WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $rules['tpl'][(string) $r['rule_key']] = (string) $r['sentence_template'];
            }

            return $rules;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('HousePrediction rules load failed: ' . $e->getMessage());
            return null;
        }
    }

    /** "3, 6, 11" -> [3,6,11] */
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
