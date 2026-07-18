<?php
declare(strict_types=1);

/**
 * CLI migration runner — for hosts with SSH access:
 *   php bin/migrate.php          # run pending
 *   php bin/migrate.php --force  # re-run everything (seeds are idempotent)
 */
require dirname(__DIR__) . '/bootstrap.php';

use AutoBusiness\Core\Database;
use AutoBusiness\Core\MigrationRunner;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$force = in_array('--force', $argv, true);
try {
    $pdo = Database::pdo();
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: {$e->getMessage()}\nCheck .env DB_* settings.\n");
    exit(1);
}

$res = MigrationRunner::run($pdo, $force);
foreach ($res['ran'] as $r) {
    printf("APPLIED  %-40s %3d statements  %5d ms\n", $r['file'], $r['statements'], $r['ms']);
}
printf("skipped (already applied): %d\n", $res['skipped']);
if ($res['error'] !== null) {
    fwrite(STDERR, "FAILED at {$res['error']['file']}: {$res['error']['message']}\n");
    exit(1);
}
echo "OK — database is in sync.\n";
