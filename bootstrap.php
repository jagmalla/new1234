<?php
declare(strict_types=1);

/**
 * Bootstrap — shared startup for both the web front controller and runner.php.
 *
 * Lives in the project ROOT (one level above public_html) so the .env it loads
 * is never web-accessible. Registers a tiny PSR-4 autoloader for the
 * AutoBusiness\ namespace, loads the environment, and starts the session for
 * CSRF on dashboard/admin/canvas requests.
 */

define('AB_ROOT', __DIR__);

// --- PSR-4 autoloader: AutoBusiness\Foo\Bar  ->  app/Foo/Bar.php ------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'AutoBusiness\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = AB_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use AutoBusiness\Core\Env;

// --- Load environment (.env in project root) --------------------------------
Env::load(AB_ROOT . '/.env');

// --- Session (only meaningful for web requests; harmless under CLI) ---------
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}
