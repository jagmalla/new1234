<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Ingestion;

use AutoBusiness\Astro\Llm\AnthropicClient;
use AutoBusiness\Astro\Llm\LlmClientInterface;

/**
 * Compiles the structured chunks of one book into a compact digest — the
 * agent's "own version" (significations, yogas, dasha effects, remedies). Speed
 * comes from the digest; ground-truth precision still comes from the retained
 * chunks (the orchestrator can pull exact passages when needed).
 *
 * LLM mode produces a real structured digest; the heuristic fallback assembles a
 * table-of-contents-style digest from the headings so ingestion completes even
 * with no API key (clearly flagged via compiled_by).
 */
final class DigestCompiler
{
    public function __construct(private readonly ?LlmClientInterface $llm = null)
    {
    }

    /**
     * @param list<array<string,mixed>> $chunks
     * @return array{compiled_by:string, digest:array<string,mixed>}
     */
    public function compile(array $chunks, string $bookLabel): array
    {
        if ($this->llm !== null) {
            try {
                return ['compiled_by' => 'llm', 'digest' => $this->compileWithLlm($chunks, $bookLabel)];
            } catch (\Throwable $e) {
                error_log('Digest LLM compile failed, using heuristic: ' . $e->getMessage());
            }
        }
        return ['compiled_by' => 'heuristic', 'digest' => $this->compileHeuristically($chunks, $bookLabel)];
    }

    /**
     * @param list<array<string,mixed>> $chunks
     * @return array<string,mixed>
     */
    private function compileWithLlm(array $chunks, string $bookLabel): array
    {
        $llm = $this->llm;
        // Concatenate (cap to keep the single compile call bounded).
        $body = '';
        foreach ($chunks as $c) {
            $body .= "\n\n" . ($c['markdown_text'] ?? '');
            if (mb_strlen($body) > 60000) {
                break;
            }
        }

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['significations', 'yogas', 'dasha_effects', 'remedies'],
            'properties' => [
                'significations' => ['type' => 'array', 'items' => ['type' => 'string']],
                'yogas' => ['type' => 'array', 'items' => ['type' => 'string']],
                'dasha_effects' => ['type' => 'array', 'items' => ['type' => 'string']],
                'remedies' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
        $system = "Compile a compact structured digest of the classical text \"{$bookLabel}\" "
            . 'STRICTLY from the supplied passages — significations, yogas, dasha effects, and '
            . 'remedies the book itself gives. Do not add anything not in the text. Return JSON only.';

        $text = $llm->complete(AnthropicClient::agentModel(), $system, $body, $schema, 4000);
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            // Defensive: extract first object.
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            $decoded = ($start !== false && $end !== false)
                ? json_decode(substr($text, $start, $end - $start + 1), true)
                : null;
        }
        if (!is_array($decoded)) {
            throw new \RuntimeException('digest compile returned unparseable JSON');
        }
        return $decoded;
    }

    /**
     * @param list<array<string,mixed>> $chunks
     * @return array<string,mixed>
     */
    private function compileHeuristically(array $chunks, string $bookLabel): array
    {
        $headings = [];
        $remedies = [];
        foreach ($chunks as $c) {
            $path = trim((string) ($c['heading_path'] ?? ''));
            if ($path !== '') {
                $headings[] = $path;
            }
            if (str_contains((string) ($c['topic_tags'] ?? ''), 'remedies')) {
                // Pull bullet lines as candidate remedies.
                foreach (explode("\n", (string) ($c['markdown_text'] ?? '')) as $ln) {
                    if (preg_match('/^- (.+)/', trim($ln), $m)) {
                        $remedies[] = $m[1];
                    }
                }
            }
        }
        return [
            'book' => $bookLabel,
            'topics' => array_values(array_unique($headings)),
            'remedies' => array_values(array_slice(array_unique($remedies), 0, 50)),
            'note' => 'Heuristic digest (no LLM): topic index built from chunk headings. '
                . 'Recompile with an API key configured for a full structured digest.',
        ];
    }
}
