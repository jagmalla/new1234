<?php
declare(strict_types=1);

namespace AutoBusiness\Core;

/**
 * Staff-only access guard for the visual canvas and its save/load API.
 *
 * The canvas is an ADMIN/STAFF-only builder (Global Rule / Module 2): clients
 * and astrologers never see it. Full staff authentication + role enforcement
 * arrives in Module 8 (the `staff` table). Until then this guard checks for a
 * staff session and, ONLY when APP_ENV=local, allows a dev shortcut so the
 * canvas is testable during development.
 */
final class AdminGuard
{
    public static function require(): void
    {
        if (!empty($_SESSION['staff_id'])) {
            return;
        }

        // Dev-only convenience: in a local environment, log in as staff #1 so
        // the canvas can be built/tested before Module 8 ships real auth.
        // This branch is impossible in production (APP_ENV=production).
        if (Env::get('APP_ENV', 'production') === 'local') {
            $_SESSION['staff_id'] = 1;
            return;
        }

        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Staff authentication required']);
        exit;
    }
}
