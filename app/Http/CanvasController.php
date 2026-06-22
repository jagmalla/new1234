<?php
declare(strict_types=1);

namespace AutoBusiness\Http;

use AutoBusiness\Core\AdminGuard;
use AutoBusiness\Core\Csrf;
use AutoBusiness\Core\Database;
use AutoBusiness\Core\Uuid;

/**
 * Canvas save/load API. ADMIN/STAFF only (the canvas is a builder tool). All
 * mutating requests are CSRF-protected per the Global Rules.
 */
final class CanvasController
{
    /** POST /api/workflow/save — create or update a workflow's graph. */
    public function save(): void
    {
        AdminGuard::require();
        Csrf::requireValid();

        $body = self::jsonBody();
        $name = trim((string) ($body['name'] ?? ''));
        $graph = $body['graph'] ?? null;
        $id = isset($body['id']) ? (string) $body['id'] : null;

        if ($name === '' || !is_array($graph)) {
            self::json(['error' => 'name and graph are required'], 422);
            return;
        }
        // Reject a graph the engine could never run (cheap structural check).
        if (!isset($graph['nodes']) || !is_array($graph['nodes'])) {
            self::json(['error' => 'graph.nodes must be an array'], 422);
            return;
        }

        $graphJson = json_encode($graph, JSON_THROW_ON_ERROR);
        $pdo = Database::pdo();

        // The per-workflow webhook HMAC secret lives in its own column. If the
        // graph has a webhook trigger and no secret is set yet, generate one and
        // return it so the caller can configure the sender.
        $hasWebhook = self::graphHasWebhook($graph);
        $generatedSecret = null;

        if ($id === null || $id === '') {
            $id = Uuid::v4();
            // user_id is the staff member acting as owner until Module 8 formalises
            // ownership; staff_id 1 in dev. Stored as the workflow owner.
            $ownerId = (int) ($_SESSION['staff_id'] ?? 1);
            $generatedSecret = $hasWebhook ? bin2hex(random_bytes(24)) : null;
            $pdo->prepare(
                'INSERT INTO workflows (id, user_id, name, workflow_graph_json, webhook_secret)
                 VALUES (:id, :uid, :name, :graph, :secret)'
            )->execute([
                ':id' => $id, ':uid' => $ownerId, ':name' => $name,
                ':graph' => $graphJson, ':secret' => $generatedSecret,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE workflows SET name = :name, workflow_graph_json = :graph WHERE id = :id'
            );
            $stmt->execute([':name' => $name, ':graph' => $graphJson, ':id' => $id]);
            if ($stmt->rowCount() === 0 && !self::exists($id)) {
                self::json(['error' => 'workflow not found'], 404);
                return;
            }
            // Backfill a secret for a webhook added to an existing workflow.
            if ($hasWebhook) {
                $cur = $pdo->prepare('SELECT webhook_secret FROM workflows WHERE id = :id');
                $cur->execute([':id' => $id]);
                if (($cur->fetchColumn() ?: '') === '') {
                    $generatedSecret = bin2hex(random_bytes(24));
                    $pdo->prepare('UPDATE workflows SET webhook_secret = :s WHERE id = :id')
                        ->execute([':s' => $generatedSecret, ':id' => $id]);
                }
            }
        }

        $response = ['id' => $id, 'saved' => true];
        if ($generatedSecret !== null) {
            $response['webhook_secret'] = $generatedSecret;
        }
        self::json($response);
    }

    /** @param array<string,mixed> $graph */
    private static function graphHasWebhook(array $graph): bool
    {
        foreach (($graph['nodes'] ?? []) as $node) {
            if (($node['type'] ?? '') === 'webhook') {
                return true;
            }
        }
        return false;
    }

    /** GET /api/workflow/load?id=... — return a stored graph for editing. */
    public function load(): void
    {
        AdminGuard::require();
        $id = (string) ($_GET['id'] ?? '');
        if ($id === '') {
            self::json(['error' => 'id required'], 422);
            return;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id, name, workflow_graph_json FROM workflows WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            self::json(['error' => 'not found'], 404);
            return;
        }
        self::json([
            'id'    => $row['id'],
            'name'  => $row['name'],
            'graph' => json_decode((string) $row['workflow_graph_json'], true),
        ]);
    }

    private static function exists(string $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM workflows WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<string,mixed> */
    private static function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $data */
    private static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
