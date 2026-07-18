<?php
declare(strict_types=1);

namespace AutoBusiness\Http;

use AutoBusiness\Core\AdminGuard;
use AutoBusiness\Core\Database;
use AutoBusiness\Core\MigrationRunner;
use Throwable;

/**
 * Admin "DB Sync" page — ?r=admin/migrate
 *
 * Shows the connection status and every migrations/*.sql with its applied
 * state, and runs the pending ones on demand (POST). Guarded exactly like the
 * calculator page. Works on shared hosting where CLI/mysql access is awkward —
 * open the page, press "Run pending", predictions start showing.
 */
final class MigrateController
{
    public function show(?array $result = null): void
    {
        AdminGuard::require();
        \AutoBusiness\Core\Asset::noCacheHtml();

        $dbError = null;
        $status = [];
        $dbName = \AutoBusiness\Core\Env::get('DB_NAME', '');
        try {
            $pdo = Database::pdo();
            $status = MigrationRunner::status($pdo);
        } catch (Throwable $e) {
            $dbError = $e->getMessage();
        }

        $view = [
            'db_error' => $dbError,
            'db_name' => $dbName,
            'status' => $status,
            'result' => $result,
        ];
        require dirname(__DIR__) . '/Http/views/migrate.php';
    }

    public function run(): void
    {
        AdminGuard::require();
        $force = (($_POST['force'] ?? '') === '1');
        $result = null;
        try {
            $pdo = Database::pdo();
            // Seeds are big; give slow shared hosts room.
            @set_time_limit(600);
            @ini_set('memory_limit', '512M');
            $result = MigrationRunner::run($pdo, $force);
        } catch (Throwable $e) {
            $result = ['ran' => [], 'skipped' => 0,
                'error' => ['file' => '(connection)', 'message' => $e->getMessage(), 'statement' => '']];
        }
        $this->show($result);
    }
}
