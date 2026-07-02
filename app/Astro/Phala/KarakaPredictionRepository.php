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
     *   sent: array<string,string>
     * }|null
     */
    public static function load(string $language = 'hi'): ?array
    {
        self::$lastError = null;

        try {
            $pdo = Database::pdo();
            $rules = ['map' => [], 'meaning' => [], 'sent' => []];

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
