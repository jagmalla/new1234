<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Llm;

/**
 * Abstraction over the LLM provider so the orchestrator, prompt factory, and
 * ingestion pipeline don't hard-depend on one vendor. The default
 * implementation (AnthropicClient) talks to Claude via native cURL — including
 * the request-building hooks the orchestrator needs for curl_multi fan-out.
 */
interface LlmClientInterface
{
    /**
     * Single blocking completion. Returns the assistant's text.
     *
     * @param ?array<string,mixed> $jsonSchema if given, request strict JSON
     */
    public function complete(string $model, string $system, string $user, ?array $jsonSchema = null, int $maxTokens = 2000): string;

    /** Request endpoint URL (for curl_multi handle construction). */
    public function endpoint(): string;

    /** Request headers as ["Header: value", ...] (for curl_multi). */
    public function headers(): array;

    /**
     * JSON request body for one message (for curl_multi handle construction).
     *
     * @param ?array<string,mixed> $jsonSchema
     * @return array<string,mixed>
     */
    public function body(string $model, string $system, string $user, ?array $jsonSchema = null, int $maxTokens = 2000): array;

    /**
     * Extract the assistant text from a decoded Messages API response, handling
     * the refusal stop reason gracefully.
     *
     * @param array<string,mixed> $decoded
     */
    public function extractText(array $decoded): string;
}
