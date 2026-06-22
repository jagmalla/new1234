<?php
declare(strict_types=1);

/**
 * Master cron runner — the single entry point invoked every minute:
 *
 *   * * * * * php /home/USER/auto_business/runner.php
 *
 * It claims pending jobs safely, resumes paused ones, executes due schedules,
 * and returns within the per-tick time budget. CLI-only; never web-reachable
 * (it lives in the project root, outside public_html).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("runner.php is CLI-only.\n");
}

require __DIR__ . '/bootstrap.php';

use AutoBusiness\Queue\WorkflowRunner;

try {
    (new WorkflowRunner())->tick();
} catch (\Throwable $e) {
    // Never fatal in a way that breaks the cron; log and exit non-zero.
    error_log('runner.php tick failed: ' . $e->getMessage());
    fwrite(STDERR, 'runner.php tick failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

exit(0);
