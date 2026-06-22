<?php
declare(strict_types=1);

namespace AutoBusiness\Queue;

use AutoBusiness\Core\Database;
use PDO;

/**
 * JobQueue — enqueue + SAFE claim semantics for the cron runner.
 *
 * Two cron ticks must never double-run the same job. We avoid that with a
 * status-flip guarded by an affected-rows check: the UPDATE only succeeds for
 * exactly one tick because the WHERE clause requires the prior status. The first
 * tick to flip pending->running wins; any other sees 0 affected rows and skips.
 */
final class JobQueue
{
    /** Enqueue a job and return immediately (triggers never run inline). */
    public static function enqueue(?string $workflowId, array $payload): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO job_queue (workflow_id, status, payload_json)
             VALUES (:wf, :status, :payload)'
        );
        $stmt->execute([
            ':wf'      => $workflowId,
            ':status'  => 'pending',
            ':payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * Atomically claim a single pending job by flipping its status to 'running'.
     * Returns the claimed row, or null if another tick already took it.
     *
     * @return array<string,mixed>|null
     */
    public static function claim(int $jobId): ?array
    {
        $pdo = Database::pdo();
        $update = $pdo->prepare(
            "UPDATE job_queue
                SET status = 'running', claimed_at = NOW(), attempts = attempts + 1
              WHERE id = :id AND status = 'pending'"
        );
        $update->execute([':id' => $jobId]);

        // Affected-rows guard: only the winning tick flipped this row.
        if ($update->rowCount() !== 1) {
            return null;
        }

        return self::find($jobId);
    }

    /** @return array<int,int> ids of jobs awaiting a first run */
    public static function pendingIds(int $limit): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT id FROM job_queue WHERE status = 'pending' ORDER BY id ASC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Jobs left in 'running' from a previous tick that paused on the time budget.
     * They are resumed before new pending jobs are claimed.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function resumable(int $limit): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM job_queue
              WHERE status = 'running' AND state_json IS NOT NULL
              ORDER BY claimed_at ASC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Persist resume state mid-run (called from the engine checkpoint). */
    public static function saveState(int $jobId, array $state): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE job_queue SET state_json = :state WHERE id = :id'
        );
        $stmt->execute([
            ':state' => json_encode($state, JSON_THROW_ON_ERROR),
            ':id'    => $jobId,
        ]);
    }

    public static function markDone(int $jobId): void
    {
        Database::pdo()
            ->prepare("UPDATE job_queue SET status = 'done' WHERE id = :id")
            ->execute([':id' => $jobId]);
    }

    public static function markFailed(int $jobId): void
    {
        Database::pdo()
            ->prepare("UPDATE job_queue SET status = 'failed' WHERE id = :id")
            ->execute([':id' => $jobId]);
    }

    /** @return array<string,mixed>|null */
    public static function find(int $jobId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM job_queue WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
