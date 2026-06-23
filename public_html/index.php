<?php
declare(strict_types=1);

/**
 * Front controller (web entry point). The ONLY PHP directly reachable by the
 * browser lives under public_html; everything else (app/, runner.php, .env)
 * sits in the project root above the webroot.
 *
 * Routing is a small explicit map keyed by ?r=. On A2 this pairs with a simple
 * rewrite (see public_html/.htaccess) so clean paths map onto ?r=.
 */

require dirname(__DIR__) . '/bootstrap.php';

use AutoBusiness\Http\CalcController;
use AutoBusiness\Http\CanvasController;
use AutoBusiness\Http\WebhookController;

$route  = (string) ($_GET['r'] ?? 'canvas');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ("{$method} {$route}") {
        case 'GET canvas':
            require dirname(__DIR__) . '/app/Http/views/canvas.php';
            break;

        case 'GET calc':
            (new CalcController())->show();
            break;

        case 'GET calc/gochar':
            (new CalcController())->gocharJson();
            break;

        case 'GET calc/varshaphal':
            (new CalcController())->varshaphalJson();
            break;

        case 'POST api/workflow/save':
            (new CanvasController())->save();
            break;

        case 'GET api/workflow/load':
            (new CanvasController())->load();
            break;

        case 'POST webhook':
            (new WebhookController())->handle((string) ($_GET['wf'] ?? ''));
            break;

        default:
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not found', 'route' => $route]);
    }
} catch (\Throwable $e) {
    // Strict: errors logged, never fatal-leaked to the client.
    error_log('Request failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal error']);
}
