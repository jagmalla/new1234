<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Ingestion;

use AutoBusiness\Astro\Llm\AnthropicClient;
use AutoBusiness\Astro\Llm\LlmClientInterface;

/**
 * Converts raw book text into clean, heading-structured Markdown. Markdown
 * headings become the natural retrieval anchors (e.g. "## Mars in 7th House"),
 * which use far fewer tokens than raw PDF text.
 *
 * Two modes:
 *   - LLM-assisted (preferred when an API key is configured): a model rewrites a
 *     section into clean Markdown with ## headings and bulleted rules/remedies.
 *   - Heuristic fallback (always available, offline): detects chapter/section
 *     headings and bullet lines by pattern, so ingestion still works with no LLM.
 */
final class MarkdownStructurer
{
    public function __construct(private readonly ?LlmClientInterface $llm = null)
    {
    }

    public function structure(string $rawText): string
    {
        $rawText = $this->normalizeWhitespace($rawText);
        if ($this->llm !== null) {
            try {
                return $this->structureWithLlm($rawText);
            } catch (\Throwable $e) {
                error_log('LLM structuring failed, using heuristic: ' . $e->getMessage());
            }
        }
        return $this->structureHeuristically($rawText);
    }

    private function structureWithLlm(string $text): string
    {
        $llm = $this->llm;
        if ($llm === null) {
            return $this->structureHeuristically($text);
        }
        $system = 'You convert raw scanned astrology-book text into clean, '
            . 'heading-structured Markdown. Use "##" headings for each topic '
            . '(chapter, planet-in-house, planet-in-sign, etc.) so they act as '
            . 'retrieval anchors, and bullet lists for individual rules and '
            . 'remedies. Preserve the meaning and any shlokas exactly; do NOT '
            . 'invent or add content. Output Markdown only.';

        $pieces = [];
        foreach ($this->splitForLlm($text) as $section) {
            $pieces[] = $llm->complete(
                AnthropicClient::agentModel(),
                $system,
                $section,
                null,
                4000
            );
        }
        return $this->collapseBlankLines(implode("\n\n", $pieces));
    }

    private function structureHeuristically(string $text): string
    {
        $lines = explode("\n", $text);
        $out = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $out[] = '';
                continue;
            }
            if ($this->looksLikeChapter($trimmed)) {
                $out[] = '# ' . $trimmed;
            } elseif ($this->looksLikeHeading($trimmed)) {
                $out[] = '## ' . $trimmed;
            } elseif ($this->looksLikeBullet($trimmed)) {
                $out[] = '- ' . ltrim($trimmed, "-*•0123456789.) \t");
            } else {
                $out[] = $trimmed;
            }
        }
        return $this->collapseBlankLines(implode("\n", $out));
    }

    private function looksLikeChapter(string $line): bool
    {
        return (bool) preg_match('/^(chapter|adhyaya|canto)\b/i', $line)
            && mb_strlen($line) < 80;
    }

    private function looksLikeHeading(string $line): bool
    {
        if (mb_strlen($line) > 70) {
            return false;
        }
        // Planet-in-house / planet-in-sign style, or a short title-case/all-caps line.
        if (preg_match('/\b(sun|moon|mars|mercury|jupiter|venus|saturn|rahu|ketu)\b.*\b(house|sign|rasi|bhava)\b/i', $line)) {
            return true;
        }
        if (preg_match('/^\d+(st|nd|rd|th)\s+house/i', $line)) {
            return true;
        }
        // ALL CAPS short line, or trailing colon title.
        if (mb_strtoupper($line) === $line && preg_match('/[A-Z]/', $line)) {
            return true;
        }
        return (bool) preg_match('/^[A-Z][A-Za-z ]{2,40}:$/', $line);
    }

    private function looksLikeBullet(string $line): bool
    {
        return (bool) preg_match('/^([-*•]|\d+[.)])\s+/u', $line);
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        return trim($text);
    }

    private function collapseBlankLines(string $text): string
    {
        return preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    }

    /**
     * Split long text into LLM-sized sections (used by the LLM mode).
     * @return string[]
     */
    private function splitForLlm(string $text, int $approxChars = 8000): array
    {
        $paras = preg_split("/\n\n+/", $text) ?: [];
        $sections = [];
        $buf = '';
        foreach ($paras as $p) {
            if (mb_strlen($buf) + mb_strlen($p) > $approxChars && $buf !== '') {
                $sections[] = $buf;
                $buf = '';
            }
            $buf .= ($buf === '' ? '' : "\n\n") . $p;
        }
        if ($buf !== '') {
            $sections[] = $buf;
        }
        return $sections;
    }
}
