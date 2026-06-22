<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Ingestion;

/**
 * Extracts raw text from an uploaded book. Plain text/markdown is read directly.
 * PDFs are handled by the first available method:
 *   1. the `pdftotext` binary (poppler-utils) via shell, when exec is enabled;
 *   2. the smalot/pdfparser library, if installed via Composer.
 * If neither is available the caller is told to supply text or install one — we
 * never silently return empty content.
 */
final class PdfTextExtractor
{
    /** @param bool $isFile true => $source is a path; false => already raw text */
    public function getText(string $source, bool $isFile): string
    {
        if (!$isFile) {
            return $source;
        }
        if (!is_readable($source)) {
            throw new \RuntimeException("Source file not readable: {$source}");
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (in_array($ext, ['txt', 'md', 'markdown'], true)) {
            return (string) file_get_contents($source);
        }
        if ($ext === 'pdf') {
            return $this->fromPdf($source);
        }
        // Unknown extension — best effort as text.
        return (string) file_get_contents($source);
    }

    private function fromPdf(string $path): string
    {
        // 1. pdftotext binary.
        if ($this->execEnabled()) {
            $out = @shell_exec('pdftotext -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null');
            if (is_string($out) && trim($out) !== '') {
                return $out;
            }
        }
        // 2. smalot/pdfparser (pure PHP), if present.
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            $parser = new \Smalot\PdfParser\Parser();
            return $parser->parseFile($path)->getText();
        }

        throw new \RuntimeException(
            'No PDF extractor available. Install poppler-utils (pdftotext) or '
            . 'run "composer require smalot/pdfparser", or upload the book as text.'
        );
    }

    private function execEnabled(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return function_exists('shell_exec') && !in_array('shell_exec', $disabled, true);
    }
}
