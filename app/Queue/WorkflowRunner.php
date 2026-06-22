<?php
declare(strict_types=1);

namespace AutoBusiness\Queue;

use AutoBusiness\Core\Database;
use AutoBusiness\Core\Env;
use AutoBusiness\Engine\ExecutionEngine;
use AutoBusiness\Engine\NodeFactory;
use AutoBusiness\Security\CredentialVault;

/**
 * WorkflowRunner — the body of one cron tick.
 *
 *   * * * * * php /home/USER/auto_business/runner.php
 *
 * Per tick it:
 *   1. promotes due scheduled workflows into job_queue (and advances next_run_at)
 *   2. resumes jobs that paused on the time budget last tick
 *   3. claims and runs new pending jobs
 *
 * Everything is bounded by a wall-clock budget (well under max_execution_time);
 * long work checkpoints to job_queue.state_json and resumes next tick.
 */
final class WorkflowRunner
{
    private ExecutionEngine $engine;
    private float $deadline;

    public function __construct()
    {
        // The vault is optional at runtime: workflows without credentials still
        // run if no master key is configured (e.g. CI). Construction only fails
        // loudly when a node actually needs to decrypt a secret.
        $vault = null;
        try {
            $vault = new CredentialVault();
        } catch (\Throwable) {
            $vault = null;
        }

        $this->engine = new ExecutionEngine(new NodeFactory($vault));
    }

    public function tick(): void
    {
        $budget = Env::int('RUNNER_TICK_BUDGET_SECONDS', 25);
        $this->deadline = microtime(true) + $budget;

        $this->promoteDueSchedules();

        // Resume paused jobs first so long-running work makes progress.
        foreach (JobQueue::resumable(10) as $job) {
            if (microtime(true) >= $this->deadline) {
                return;
            }
            $this->runJob($job, resume: true);
        }

        // Then claim new pending jobs (small batch per tick).
        foreach (JobQueue::pendingIds(10) as $id) {
            if (microtime(true) >= $this->deadline) {
                return;
            }
            $job = JobQueue::claim($id);
            if ($job === null) {
                continue; // another tick already claimed it
            }
            $this->runJob($job, resume: false);
        }
    }

    /**
     * Enqueue any active scheduled workflow whose next_run_at is due, then push
     * next_run_at forward from its cron expression.
     */
    private function promoteDueSchedules(): void
    {
        $pdo = Database::pdo();
        $rows = $pdo->query(
            "SELECT id, schedule_cron FROM workflows
              WHERE is_active = 1
                AND schedule_cron IS NOT NULL
                AND next_run_at IS NOT NULL
                AND next_run_at <= NOW()"
        )->fetchAll();

        $now = new \DateTimeImmutable('now');
        foreach ($rows as $row) {
            try {
                $cron = new CronSchedule((string) $row['schedule_cron']);
            } catch (\Throwable $e) {
                error_log("Invalid cron for workflow {$row['id']}: " . $e->getMessage());
                continue;
            }
            JobQueue::enqueue((string) $row['id'], ['trigger' => 'cron', 'fired_at' => $now->format('c')]);

            $next = $cron->nextRun($now)->format('Y-m-d H:i:s');
            $pdo->prepare('UPDATE workflows SET next_run_at = :next WHERE id = :id')
                ->execute([':next' => $next, ':id' => $row['id']]);
        }
    }

    /**
     * @param array<string,mixed> $job
     */
    private function runJob(array $job, bool $resume): void
    {
        $jobId = (int) $job['id'];
        $workflowId = $job['workflow_id'] !== null ? (string) $job['workflow_id'] : null;

        try {
            $graph = $this->loadGraph($workflowId);

            if ($resume) {
                $state = json_decode((string) $job['state_json'], true, 512, JSON_THROW_ON_ERROR);
            } else {
                $payload = $job['payload_json'] !== null
                    ? json_decode((string) $job['payload_json'], true, 512, JSON_THROW_ON_ERROR)
                    : [];
                $state = ['_trigger' => $payload, 'Nodes' => []];
            }

            $result = $this->engine->run(
                $graph,
                $state,
                $this->deadline,
                static fn(array $s) => JobQueue::saveState($jobId, $s)
            );

            if ($result['status'] === 'done') {
                JobQueue::markDone($jobId);
                $this->log($workflowId, 'success', $job, $result['state']);
            }
            // 'paused' -> leave status 'running' with state saved; next tick resumes.
        } catch (\Throwable $e) {
            JobQueue::markFailed($jobId);
            $this->log($workflowId, 'failed', $job, ['error' => $e->getMessage()], $e->getMessage());
            error_log("Job {$jobId} failed: " . $e->getMessage());
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function loadGraph(?string $workflowId): array
    {
        if ($workflowId === null) {
            throw new \RuntimeException('Job has no workflow_id.');
        }
        $stmt = Database::pdo()->prepare(
            'SELECT workflow_graph_json FROM workflows WHERE id = :id'
        );
        $stmt->execute([':id' => $workflowId]);
        $json = $stmt->fetchColumn();
        if ($json === false || $json === null) {
            throw new \RuntimeException("Workflow {$workflowId} not found or empty.");
        }
        return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string,mixed> $job
     * @param array<string,mixed> $state
     */
    private function log(?string $workflowId, string $status, array $job, array $state, ?string $error = null): void
    {
        // Truncate large blobs per the Module 1 schema note.
        $output = json_encode($state['Nodes'] ?? $state, JSON_PARTIAL_OUTPUT_ON_ERROR);
        $output = is_string($output) ? mb_substr($output, 0, 60000) : null;
        $input  = $job['payload_json'] !== null ? mb_substr((string) $job['payload_json'], 0, 60000) : null;

        Database::pdo()->prepare(
            'INSERT INTO execution_logs (workflow_id, status, input_data, output_data, error_message)
             VALUES (:wf, :status, :input, :output, :error)'
        )->execute([
            ':wf'     => $workflowId,
            ':status' => $status,
            ':input'  => $input,
            ':output' => $output,
            ':error'  => $error,
        ]);
    }
}
