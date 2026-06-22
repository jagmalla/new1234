<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Ingestion;

/**
 * Splits structured Markdown into retrievable chunks at heading boundaries.
 * Each chunk carries a heading_path (e.g. "Chapter 4 > Mars > 7th House") and
 * auto-derived topic_tags that match the tag format the orchestrator uses for
 * retrieval (planet names, "<n>-house", sign names, "remedies", "yoga", ...).
 */
final class MarkdownChunker
{
    private const PLANETS = ['sun', 'moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn', 'rahu', 'ketu'];
    private const SIGNS = ['aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo', 'libra',
        'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces'];
    private const TOPIC_WORDS = ['remedies', 'remedy', 'yoga', 'dasha', 'gemstone', 'mantra', 'transit', 'gochar'];

    /**
     * @return list<array{chunk_index:int, heading_path:string, markdown_text:string, topic_tags:string}>
     */
    public function chunk(string $markdown): array
    {
        $lines = explode("\n", $markdown);
        $chunks = [];
        $h1 = '';
        $currentHeading = '';
        $buffer = [];

        $flush = function () use (&$chunks, &$currentHeading, &$buffer, &$h1): void {
            $text = trim(implode("\n", $buffer));
            if ($text === '' && $currentHeading === '') {
                return;
            }
            $path = trim(($h1 !== '' ? $h1 . ' > ' : '') . $currentHeading, ' >');
            $body = ($currentHeading !== '' ? '## ' . $currentHeading . "\n" : '') . $text;
            $chunks[] = [
                'chunk_index' => count($chunks),
                'heading_path' => $path,
                'markdown_text' => $body,
                'topic_tags' => self::tagsFor($path . ' ' . $text),
            ];
            $buffer = [];
        };

        foreach ($lines as $line) {
            if (preg_match('/^#\s+(.+)$/', $line, $m)) {
                $flush();
                $h1 = trim($m[1]);
                $currentHeading = '';
            } elseif (preg_match('/^##\s+(.+)$/', $line, $m)) {
                $flush();
                $currentHeading = trim($m[1]);
            } else {
                $buffer[] = $line;
            }
        }
        $flush();

        // Guarantee at least one chunk for very unstructured input.
        if ($chunks === [] && trim($markdown) !== '') {
            $chunks[] = [
                'chunk_index' => 0,
                'heading_path' => '',
                'markdown_text' => trim($markdown),
                'topic_tags' => self::tagsFor($markdown),
            ];
        }
        return $chunks;
    }

    /** Derive a comma-joined tag string from text. */
    private static function tagsFor(string $text): string
    {
        $lc = mb_strtolower($text);
        $tags = [];
        foreach (self::PLANETS as $p) {
            if (str_contains($lc, $p)) {
                $tags[] = $p;
            }
        }
        foreach (self::SIGNS as $s) {
            if (str_contains($lc, $s)) {
                $tags[] = $s;
            }
        }
        foreach (self::TOPIC_WORDS as $w) {
            if (str_contains($lc, $w)) {
                $tags[] = $w === 'remedy' ? 'remedies' : $w;
            }
        }
        if (preg_match_all('/(\d+)(st|nd|rd|th)\s+house/i', $text, $mm)) {
            foreach ($mm[1] as $n) {
                $tags[] = $n . '-house';
            }
        }
        return implode(',', array_values(array_unique($tags)));
    }
}
