<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Llm;

use AutoBusiness\Core\Env;

/**
 * Claude (Anthropic Messages API) client over native cURL.
 *
 * WHY RAW HTTP (not the PHP SDK): the Global Architecture Rules mandate native
 * cURL + curl_multi_exec for parallel work and forbid extra runtimes/daemons.
 * The 19-agent fan-out (AstrologyOrchestrator) needs many requests in flight at
 * once via curl_multi — so this client exposes endpoint()/headers()/body() for
 * the orchestrator to build its own multi-handles, plus a blocking complete()
 * for one-off calls (e.g. digest compilation).
 *
 * Model choice follows the spec's cost guidance: a cheaper model for routine
 * book agents, a stronger one for synthesis — both configurable via .env
 * (LLM_AGENT_MODEL, LLM_CONCLUSION_MODEL). Defaults target current Claude models.
 */
final class AnthropicClient implements LlmClientInterface
{
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.anthropic.com'
    ) {
    }

    /** Cheaper model for the routine book agents. */
    public static function agentModel(): string
    {
        return Env::get('LLM_AGENT_MODEL', 'claude-haiku-4-5') ?? 'claude-haiku-4-5';
    }

    /** Stronger model for the synthesis/conclusion step (Module 5). */
    public static function conclusionModel(): string
    {
        return Env::get('LLM_CONCLUSION_MODEL', 'claude-opus-4-8') ?? 'claude-opus-4-8';
    }

    public function endpoint(): string
    {
        return rtrim($this->baseUrl, '/') . '/v1/messages';
    }

    /** @return string[] */
    public function headers(): array
    {
        return [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: ' . self::ANTHROPIC_VERSION,
            'content-type: application/json',
        ];
    }

    /** @return array<string,mixed> */
    public function body(string $model, string $system, string $user, ?array $jsonSchema = null, int $maxTokens = 2000): array
    {
        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $user],
            ],
        ];
        // Strict JSON when a schema is supplied (supported on the default models).
        if ($jsonSchema !== null) {
            $body['output_config'] = ['format' => ['type' => 'json_schema', 'schema' => $jsonSchema]];
        }
        return $body;
    }

    public function complete(string $model, string $system, string $user, ?array $jsonSchema = null, int $maxTokens = 2000): string
    {
        $ch = curl_init();
        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->endpoint(),
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $this->headers(),
                CURLOPT_POSTFIELDS => (string) json_encode($this->body($model, $system, $user, $jsonSchema, $maxTokens)),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 120,
            ]);
            $raw = curl_exec($ch);
            if ($raw === false) {
                throw new \RuntimeException('LLM request failed: ' . curl_error($ch));
            }
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('LLM returned non-JSON response.');
            }
            if ($status < 200 || $status >= 300) {
                $msg = $decoded['error']['message'] ?? ('HTTP ' . $status);
                throw new \RuntimeException('LLM error: ' . $msg);
            }
            return $this->extractText($decoded);
        } finally {
            curl_close($ch);
        }
    }

    /** @param array<string,mixed> $decoded */
    public function extractText(array $decoded): string
    {
        if (($decoded['stop_reason'] ?? null) === 'refusal') {
            throw new \RuntimeException('LLM declined the request (refusal).');
        }
        $text = '';
        foreach (($decoded['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        return $text;
    }

    /** Construct from .env (LLM_API_KEY). */
    public static function fromEnv(): self
    {
        return new self(Env::require('LLM_API_KEY'), Env::get('LLM_BASE_URL', 'https://api.anthropic.com') ?? 'https://api.anthropic.com');
    }
}
