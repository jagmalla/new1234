<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Orchestration;

/**
 * AgentPromptFactory — builds each book agent's prompt from three inputs:
 *   1. the chart JSON (same immutable payload for every agent)
 *   2. that book's system_instruction_template + grounding_mode
 *   3. ONLY that book's knowledge slice (digest + retrieved chunks)
 *
 * It enforces the two halves of single-book isolation at the PROMPT layer
 * (the data layer is enforced by KnowledgeRepository):
 *   - grounded books are told to answer ONLY from the supplied passages and to
 *     say plainly when the book is silent — never inventing shlokas/remedies.
 *   - output is constrained to strict JSON {"prediction","remedies":[...]}.
 *
 * The chosen output language (Module 3b) is injected so the prediction and
 * remedies are written natively in that language.
 */
final class AgentPromptFactory
{
    /** Strict JSON contract every book agent must return. */
    public static function outputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['prediction', 'remedies', 'covered'],
            'properties' => [
                'prediction' => ['type' => 'string'],
                'remedies' => ['type' => 'array', 'items' => ['type' => 'string']],
                // Honest-gap flag: false when the book does not cover the topic.
                'covered' => ['type' => 'boolean'],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $agent  row from astro_agents
     */
    public static function buildSystem(array $agent, string $outputLanguage): string
    {
        $book = (string) ($agent['book_label'] ?? $agent['agent_name'] ?? 'this book');
        $template = trim((string) ($agent['system_instruction_template'] ?? ''));
        $mode = (string) ($agent['grounding_mode'] ?? 'grounded');

        $parts = [];
        $parts[] = "You are an expert Vedic astrology agent speaking strictly as the classical text \"{$book}\".";
        if ($template !== '') {
            $parts[] = $template;
        }

        if ($mode === 'grounded') {
            $parts[] = "GROUNDING (hard rule): Answer ONLY from the supplied passages and digest of \"{$book}\". "
                . "Do NOT use any other astrological tradition, any other book, or your own training knowledge. "
                . "If \"{$book}\" does not address the chart's question, set \"covered\" to false and say plainly "
                . "that this book does not cover it. Never invent shlokas, rules, or remedies to fill a gap — "
                . "an honest \"not covered\" is correct and expected.";
        } elseif ($mode === 'hybrid') {
            $parts[] = "Prefer the supplied passages of \"{$book}\"; you may add general knowledge only to clarify, "
                . "and must clearly attribute anything not from the book.";
        } else { // style
            $parts[] = "Answer in the interpretive style of \"{$book}\".";
        }

        $parts[] = "Keep technical terms (graha, dasha, yoga) in their original form and add the local-language "
            . "meaning in brackets on first use.";
        $parts[] = "Write the ENTIRE response — both the prediction and every remedy — in this language: {$outputLanguage}.";
        $parts[] = 'Return ONLY a JSON object of the form '
            . '{"prediction": "...", "remedies": ["..."], "covered": true|false}. No prose outside the JSON.';

        return implode("\n\n", $parts);
    }

    /**
     * @param array<string,mixed> $agent
     * @param array<string,mixed> $chart
     * @param array{digest: ?array<string,mixed>, chunks: list<array<string,mixed>>} $slice
     */
    public static function buildUser(array $agent, array $chart, array $slice): string
    {
        $book = (string) ($agent['book_label'] ?? $agent['agent_name'] ?? 'this book');

        $out = [];
        $out[] = "BIRTH CHART (computed by the calculation engine — authoritative, do not recompute):";
        $out[] = '```json';
        $out[] = (string) json_encode(self::trimChartForPrompt($chart), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $out[] = '```';

        $out[] = "\nYOUR BOOK'S KNOWLEDGE — \"{$book}\" ONLY (this is the entire body of text you may use):";

        if (!empty($slice['digest']['digest'])) {
            $out[] = "\n[Compiled digest]";
            $out[] = (string) json_encode($slice['digest']['digest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        if (!empty($slice['chunks'])) {
            $out[] = "\n[Relevant passages]";
            foreach ($slice['chunks'] as $chunk) {
                $heading = $chunk['heading_path'] ?? '';
                $out[] = ($heading !== '' ? "## {$heading}\n" : '') . (string) ($chunk['markdown_text'] ?? '');
            }
        }

        if (empty($slice['digest']['digest']) && empty($slice['chunks'])) {
            $out[] = "\n(No compiled knowledge is available for this book yet.)";
        }

        $out[] = "\nTASK: Interpret this chart strictly from \"{$book}\" as instructed. "
            . "Give the prediction and any remedies the book prescribes, then the JSON.";

        return implode("\n", $out);
    }

    /**
     * Compact the chart for prompting — keep placements, drop bulky numeric
     * internals the LLM doesn't need (full dasha tables, raw shadbala).
     *
     * @param array<string,mixed> $chart
     * @return array<string,mixed>
     */
    private static function trimChartForPrompt(array $chart): array
    {
        $planets = [];
        foreach (($chart['planets'] ?? []) as $name => $p) {
            $planets[$name] = [
                'position' => $p['formatted'] ?? null,
                'house' => $p['house'] ?? null,
                'nakshatra' => $p['nakshatra']['name'] ?? null,
                'retro' => $p['retro'] ?? false,
                'navamsa' => $p['navamsa_sign'] ?? null,
            ];
        }
        return [
            'ascendant' => $chart['ascendant']['formatted'] ?? null,
            'moon_sign' => $chart['planets']['Moon']['sign'] ?? null,
            'planets' => $planets,
            'running_dasha' => $chart['dasha']['running'] ?? null,
        ];
    }
}
