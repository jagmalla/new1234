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
use AutoBusiness\Http\MilanController;

// The astrology calculator is the site's home page. A bare domain visit (no ?r=)
// opens it directly, exactly as /calc does.
$route  = (string) ($_GET['r'] ?? 'calc');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ("{$method} {$route}") {
        case 'GET calc':
        case 'GET ':
            (new CalcController())->show();
            break;

        case 'GET calc/gochar':
            (new CalcController())->gocharJson();
            break;

        case 'GET calc/ping':
        case 'POST calc/ping':
            (new CalcController())->ping();
            break;

        case 'POST calc/translate':
            (new CalcController())->translateJson();
            break;

        case 'GET calc/varshaphal':
            (new CalcController())->varshaphalJson();
            break;

        case 'GET calc/dashaPhala':
            (new CalcController())->dashaPhalaJson();
            break;

        case 'GET calc/dashaEngine':
            (new CalcController())->dashaEngineJson();
            break;

        case 'GET milan':
            (new MilanController())->show();
            break;

        default:
            // No dead ends: any unknown path (including the retired /canvas
            // builder) sends the visitor to the calculator home page.
            header('Location: /calc', true, 302);
    }
} catch (\Throwable $e) {
    // Strict: errors logged, never fatal-leaked to the client.
    error_log('Request failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal error']);
}
