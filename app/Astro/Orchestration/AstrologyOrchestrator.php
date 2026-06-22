<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Orchestration;

use AutoBusiness\Astro\Llm\AnthropicClient;
use AutoBusiness\Astro\Llm\LlmClientInterface;
use AutoBusiness\Core\Env;

/**
 * AstrologyOrchestrator — fan-out to the selected book agents.
 *
 * Takes the Calculation JSON and broadcasts it to each agent via curl_multi_exec
 * — but in BATCHES (waves of 4-5, per the Global Rules) so a 19-agent fan-out
 * never exhausts memory/time on shared hosting. Each agent receives ONLY its own
 * isolated knowledge slice (KnowledgeRepository) plus the chart.
 *
 * Resumable: results are accumulated in $state['results'] (keyed by agent_id)
 * and a checkpoint callback is invoked after each wave so the cron runner can
 * persist progress to job_queue.state_json. When the wall-clock deadline is hit
 * the orchestrator returns status "paused" with the state; calling run() again
 * with that state continues from the next unfinished agent.
 */
final class AstrologyOrchestrator
{
    public function __construct(
        private readonly LlmClientInterface $llm,
        private readonly KnowledgeRepository $repo
    ) {
    }

    /**
     * @param array<string,mixed>              $chart   immutable calculation payload
     * @param list<array<string,mixed>>        $agents  selected astro_agents rows
     * @param array<string,mixed>              $state   prior state for resume, or []
     * @param float                            $deadline microtime(true) budget
     * @param callable|null                    $checkpoint fn(array $state): void
     * @return array{status:string, state:array<string,mixed>}
     */
    public function run(
        array $chart,
        array $agents,
        string $outputLanguage,
        array $state,
        float $deadline,
        ?callable $checkpoint = null
    ): array {
        $state['results'] ??= [];
        $model = AnthropicClient::agentModel();
        $waveSize = max(1, Env::int('FANOUT_WAVE_SIZE', 4));

        // Agents not yet completed (resume-safe).
        $pending = [];
        foreach ($agents as $agent) {
            $id = (int) $agent['id'];
            if (!isset($state['results'][$id])) {
                $pending[] = $agent;
            }
        }

        foreach (array_chunk($pending, $waveSize) as $wave) {
            $waveResults = $this->runWave($wave, $chart, $outputLanguage, $model);
            foreach ($waveResults as $id => $res) {
                $state['results'][$id] = $res;
            }
            if ($checkpoint !== null) {
                $checkpoint($state);
            }
            if (microtime(true) >= $deadline) {
                return ['status' => 'paused', 'state' => $state];
            }
        }

        return ['status' => 'done', 'state' => $state];
    }

    /**
     * Issue one wave of agent calls concurrently via curl_multi.
     *
     * @param list<array<string,mixed>> $wave
     * @param array<string,mixed>       $chart
     * @return array<int, array<string,mixed>> agent_id => result
     */
    private function runWave(array $wave, array $chart, string $outputLanguage, string $model): array
    {
        $mh = curl_multi_init();
        $handles = [];
        $schema = AgentPromptFactory::outputSchema();

        foreach ($wave as $agent) {
            $id = (int) $agent['id'];
            $slice = $this->repo->sliceForAgent($id, $chart);
            $system = AgentPromptFactory::buildSystem($agent, $outputLanguage);
            $user = AgentPromptFactory::buildUser($agent, $chart, $slice);
            $body = $this->llm->body($model, $system, $user, $schema, 2000);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->llm->endpoint(),
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $this->llm->headers(),
                CURLOPT_POSTFIELDS => (string) json_encode($body),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 120,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$id] = ['ch' => $ch, 'agent' => $agent];
        }

        // Drive all handles to completion.
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        // Collect + parse each response.
        $results = [];
        foreach ($handles as $id => $h) {
            $agent = $h['agent'];
            $book = (string) ($agent['book_label'] ?? $agent['agent_name'] ?? '');
            $raw = curl_multi_getcontent($h['ch']);
            $httpCode = (int) curl_getinfo($h['ch'], CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $h['ch']);
            curl_close($h['ch']);

            $results[$id] = $this->parseAgentResponse($id, $book, $model, is_string($raw) ? $raw : '', $httpCode);
        }
        curl_multi_close($mh);

        return $results;
    }

    /**
     * @return array<string,mixed>
     */
    private function parseAgentResponse(int $agentId, string $book, string $model, string $raw, int $httpCode): array
    {
        $base = ['agent_id' => $agentId, 'book_label' => $book, 'model' => $model];

        try {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('non-JSON HTTP response');
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new \RuntimeException($decoded['error']['message'] ?? ('HTTP ' . $httpCode));
            }
            $text = $this->llm->extractText($decoded);
            $answer = self::extractJsonObject($text);

            return $base + [
                'prediction' => (string) ($answer['prediction'] ?? ''),
                'remedies' => array_values(array_map('strval', (array) ($answer['remedies'] ?? []))),
                'covered' => (bool) ($answer['covered'] ?? true),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return $base + [
                'prediction' => '',
                'remedies' => [],
                'covered' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Defensive JSON extraction from an LLM text answer — handles both clean
     * JSON and JSON wrapped in stray prose/code fences.
     *
     * @return array<string,mixed>
     */
    private static function extractJsonObject(string $text): array
    {
        $trimmed = trim($text);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        // Grab the first {...} block.
        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidate = substr($trimmed, $start, $end - $start + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        throw new \RuntimeException('could not parse agent JSON answer');
    }
}
