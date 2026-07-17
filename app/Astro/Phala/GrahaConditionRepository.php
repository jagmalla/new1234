<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Phala;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the editable rule tables for the Planet-Prediction "ग्रह स्थिति" block
 * (see migrations/008_graha_prediction_fix.sql): status/combustion templates,
 * the nature×maitri companion matrix, the named pair-yogas, the combustion
 * signification-loss text and the engine config switches. Resilient — returns
 * null with lastError() set when the database is unreachable.
 */
final class GrahaConditionRepository
{
    private static ?string $lastError = null;

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   tpl: array<string,string>,
     *   matrix: array<string,array{tpl:string,score:float}>,
     *   yogas: array<string,array{name:string,tpl:string,good_bad:string,score:float,key:string}>,
     *   loss: array<string,string>,
     *   config: array<string,string>
     * }|null
     */
    public static function load(string $language = 'hi'): ?array
    {
        self::$lastError = null;
        try {
            $pdo = Database::pdo();
            $out = ['tpl' => [], 'matrix' => [], 'yogas' => [], 'loss' => [], 'config' => []];

            $stmt = $pdo->prepare(
                "SELECT rule_key, sentence_template FROM house_pred_templates
                  WHERE language = ? AND rule_key IN
                  ('graha_status','graha_combust_full','graha_combust_moderate',
                   'graha_combust_partial','graha_alone','budhaditya_combust_note')"
            );
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $out['tpl'][(string) $r['rule_key']] = (string) $r['sentence_template'];
            }

            $stmt = $pdo->prepare('SELECT rule_key, sentence_template, score FROM graha_yuti_sentences WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $out['matrix'][(string) $r['rule_key']] = [
                    'tpl' => (string) $r['sentence_template'], 'score' => (float) $r['score'],
                ];
            }

            $stmt = $pdo->prepare('SELECT rule_key, planet_a, planet_b, yoga_name, sentence_template, good_bad, score FROM graha_pair_yogas WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $out['yogas'][self::pairKey((string) $r['planet_a'], (string) $r['planet_b'])] = [
                    'name' => (string) $r['yoga_name'], 'tpl' => (string) $r['sentence_template'],
                    'good_bad' => (string) $r['good_bad'], 'score' => (float) $r['score'],
                    'key' => (string) $r['rule_key'],
                ];
            }

            $stmt = $pdo->prepare('SELECT planet, loss_text FROM karaka_combust_loss WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $out['loss'][(string) $r['planet']] = (string) $r['loss_text'];
            }

            foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                $out['config'][(string) $r['cfg_key']] = (string) $r['cfg_value'];
            }

            return $out;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Graha condition rules load failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Unordered pair key, e.g. ("Mercury","Sun") -> "Mercury|Sun". */
    public static function pairKey(string $a, string $b): string
    {
        $pair = [$a, $b];
        sort($pair);
        return $pair[0] . '|' . $pair[1];
    }
}
