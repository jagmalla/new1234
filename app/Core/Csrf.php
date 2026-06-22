<?php
declare(strict_types=1);

namespace AutoBusiness\Core;

/**
 * CSRF token helper. Global Rule: CSRF tokens on all dashboard/admin/canvas
 * forms. (Inbound webhooks use a per-workflow HMAC instead — see
 * WebhookController — not this token.)
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    /** Constant-time validation of a submitted token. */
    public static function validate(?string $submitted): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? '';
        return is_string($submitted)
            && $expected !== ''
            && hash_equals($expected, $submitted);
    }

    /** Reads the token from header or POST field and aborts the request on mismatch. */
    public static function requireValid(): void
    {
        $submitted = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? ($_POST['csrf_token'] ?? null);

        if (!self::validate(is_string($submitted) ? $submitted : null)) {
            http_response_code(419); // "Authentication Timeout" — conventional for CSRF failures
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid or missing CSRF token']);
            exit;
        }
    }
}
