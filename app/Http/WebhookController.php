<?php
declare(strict_types=1);

namespace AutoBusiness\Http;

use AutoBusiness\Core\Database;
use AutoBusiness\Queue\JobQueue;

/**
 * Inbound webhook trigger. Global Rules:
 *   - Triggers NEVER run inline: this enqueues a job and returns HTTP 202.
 *   - Webhooks authenticate with a per-workflow HMAC secret (not a CSRF token).
 *
 * The secret lives in workflows.webhook_secret (a dedicated column). Signature =
 * hex HMAC-SHA256 of the raw request body, sent in X-Signature (an optional
 * "sha256=" prefix is tolerated).
 */
final class WebhookController
{
    public function handle(string $workflowId): void
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, webhook_secret, is_active FROM workflows WHERE id = :id'
        );
        $stmt->execute([':id' => $workflowId]);
        $row = $stmt->fetch();

        if ($row === false || (int) $row['is_active'] !== 1) {
            self::respond(404, ['error' => 'workflow not found or inactive']);
            return;
        }

        $secret = $row['webhook_secret'] ?? null;
        if (!is_string($secret) || $secret === '') {
            self::respond(400, ['error' => 'workflow has no webhook secret configured']);
            return;
        }

        $rawBody = file_get_contents('php://input') ?: '';
        if (!self::verifySignature($rawBody, $secret)) {
            self::respond(401, ['error' => 'invalid signature']);
            return;
        }

        $parsed = json_decode($rawBody, true);
        $payload = [
            'trigger' => 'webhook',
            'headers' => self::safeHeaders(),
            'body'    => is_array($parsed) ? $parsed : $rawBody,
        ];

        $jobId = JobQueue::enqueue($workflowId, $payload);

        // Enqueue-and-return: the runner executes it on the next cron tick.
        self::respond(202, ['accepted' => true, 'job_id' => $jobId]);
    }

    private static function verifySignature(string $body, string $secret): bool
    {
        $provided = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        if ($provided === '') {
            return false;
        }
        $provided = str_starts_with($provided, 'sha256=') ? substr($provided, 7) : $provided;
        $expected = hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $provided);
    }

    /** @return array<string,string> */
    private static function safeHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') && is_string($value)) {
                $name = str_replace('_', '-', substr($key, 5));
                // Never echo the signature back into workflow state.
                if (strcasecmp($name, 'X-Signature') !== 0) {
                    $headers[$name] = $value;
                }
            }
        }
        return $headers;
    }

    /** @param array<string,mixed> $data */
    private static function respond(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
