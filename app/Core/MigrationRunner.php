<?php
declare(strict_types=1);

namespace AutoBusiness\Core;

use PDO;
use Throwable;

/**
 * Database migration runner — imports every migrations/*.sql into the
 * connected database, in filename order, exactly once.
 *
 * - Applied files are recorded in `schema_migrations` (created on first run),
 *   so re-running is safe and only pending files execute.
 * - The seed files are themselves idempotent (CREATE TABLE IF NOT EXISTS +
 *   INSERT IGNORE / ON DUPLICATE KEY), so "force re-run" is also safe.
 * - Statements are split with a quote/comment-aware scanner — the Hindi seed
 *   text is full of semicolons inside quoted strings, so a naive explode(';')
 *   would corrupt them. Handles '…', "…", `…`, backslash escapes, '' doubling,
 *   -- and # line comments and /* … *​/ block comments. (None of the files use
 *   DELIMITER/stored routines, so that syntax is not needed.)
 *
 * Used by the admin "DB Sync" page (?r=admin/migrate) and runnable from CLI:
 *   php bin/migrate.php
 */
final class MigrationRunner
{
    /** @return string absolute migrations directory. */
    public static function dir(): string
    {
        return \AB_ROOT . '/migrations';
    }

    /** @return list<string> migration filenames (sorted, basename only). */
    public static function files(): array
    {
        $out = glob(self::dir() . '/*.sql') ?: [];
        $out = array_map('basename', $out);
        sort($out, SORT_STRING);
        return $out;
    }

    /** Create the tracking table if missing. */
    private static function ensureTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                filename    VARCHAR(191) NOT NULL,
                applied_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                statements  INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (filename)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string,string> filename => applied_at for recorded files. */
    public static function applied(PDO $pdo): array
    {
        self::ensureTable($pdo);
        $out = [];
        foreach ($pdo->query('SELECT filename, applied_at FROM schema_migrations') as $r) {
            $out[(string) $r['filename']] = (string) $r['applied_at'];
        }
        return $out;
    }

    /**
     * Status of every migration file.
     *
     * @return list<array{file:string,applied:bool,applied_at:?string,size:int}>
     */
    public static function status(PDO $pdo): array
    {
        $applied = self::applied($pdo);
        $out = [];
        foreach (self::files() as $f) {
            $out[] = [
                'file' => $f,
                'applied' => isset($applied[$f]),
                'applied_at' => $applied[$f] ?? null,
                'size' => (int) (@filesize(self::dir() . '/' . $f) ?: 0),
            ];
        }
        return $out;
    }

    /**
     * Run all pending migrations (or every file when $force). Stops at the
     * first failing file so problems are visible and nothing later runs on a
     * half-built schema.
     *
     * @return array{ran:list<array{file:string,statements:int,ms:int}>,
     *               skipped:int, error:?array{file:string,message:string,statement:string}}
     */
    public static function run(PDO $pdo, bool $force = false): array
    {
        self::ensureTable($pdo);
        $applied = self::applied($pdo);
        $ran = [];
        $skipped = 0;

        foreach (self::files() as $f) {
            if (!$force && isset($applied[$f])) {
                $skipped++;
                continue;
            }
            $sql = (string) @file_get_contents(self::dir() . '/' . $f);
            if ($sql === '') {
                return ['ran' => $ran, 'skipped' => $skipped,
                    'error' => ['file' => $f, 'message' => 'file unreadable/empty', 'statement' => '']];
            }
            $t0 = microtime(true);
            $count = 0;
            $current = '';
            try {
                foreach (self::split($sql) as $stmt) {
                    $current = $stmt;
                    $pdo->exec($stmt);
                    $count++;
                }
            } catch (Throwable $e) {
                return ['ran' => $ran, 'skipped' => $skipped, 'error' => [
                    'file' => $f,
                    'message' => $e->getMessage(),
                    'statement' => mb_substr($current, 0, 300),
                ]];
            }
            $ins = $pdo->prepare(
                'INSERT INTO schema_migrations (filename, statements) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP, statements = VALUES(statements)'
            );
            $ins->execute([$f, $count]);
            $ran[] = ['file' => $f, 'statements' => $count, 'ms' => (int) round((microtime(true) - $t0) * 1000)];
        }
        return ['ran' => $ran, 'skipped' => $skipped, 'error' => null];
    }

    /**
     * Quote/comment-aware SQL statement splitter.
     *
     * @return list<string> non-empty statements without the trailing ';'
     */
    public static function split(string $sql): array
    {
        $stmts = [];
        $buf = '';
        $len = strlen($sql);
        $i = 0;
        $state = 'code';   // code | squote | dquote | btick | line_comment | block_comment
        while ($i < $len) {
            $c = $sql[$i];
            $n = $i + 1 < $len ? $sql[$i + 1] : '';

            switch ($state) {
                case 'code':
                    if ($c === '-' && $n === '-') { $state = 'line_comment'; $i += 2; continue 2; }
                    if ($c === '#') { $state = 'line_comment'; $i++; continue 2; }
                    if ($c === '/' && $n === '*') { $state = 'block_comment'; $i += 2; continue 2; }
                    if ($c === "'") { $state = 'squote'; $buf .= $c; $i++; continue 2; }
                    if ($c === '"') { $state = 'dquote'; $buf .= $c; $i++; continue 2; }
                    if ($c === '`') { $state = 'btick'; $buf .= $c; $i++; continue 2; }
                    if ($c === ';') {
                        $s = trim($buf);
                        if ($s !== '') { $stmts[] = $s; }
                        $buf = '';
                        $i++;
                        continue 2;
                    }
                    $buf .= $c;
                    $i++;
                    continue 2;

                case 'squote':
                case 'dquote':
                    $q = $state === 'squote' ? "'" : '"';
                    if ($c === '\\') { $buf .= $c . $n; $i += 2; continue 2; }   // backslash escape
                    if ($c === $q && $n === $q) { $buf .= $c . $n; $i += 2; continue 2; }   // '' doubling
                    if ($c === $q) { $state = 'code'; }
                    $buf .= $c;
                    $i++;
                    continue 2;

                case 'btick':
                    if ($c === '`') { $state = 'code'; }
                    $buf .= $c;
                    $i++;
                    continue 2;

                case 'line_comment':
                    if ($c === "\n") { $state = 'code'; $buf .= $c; }
                    $i++;
                    continue 2;

                case 'block_comment':
                    if ($c === '*' && $n === '/') { $state = 'code'; $i += 2; continue 2; }
                    $i++;
                    continue 2;
            }
        }
        $s = trim($buf);
        if ($s !== '') { $stmts[] = $s; }
        return $stmts;
    }
}
