<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Ingestion;

use AutoBusiness\Astro\Llm\LlmClientInterface;
use AutoBusiness\Core\Database;
use PDO;

/**
 * One-time-per-book ingestion pipeline (Module 3, phase 1):
 *
 *   upload -> extract text -> clean structured Markdown -> chunk by heading
 *          -> store chunks in agent_knowledge -> compile digest -> agent_digest
 *
 * Idempotent: re-ingesting an agent replaces its chunks and creates a NEW
 * agent_digest version (prior versions are retained for rollback — Module 7).
 *
 * The heavy LLM-driven steps degrade gracefully to offline heuristics when no
 * API key is configured, so the pipeline always completes and produces a usable
 * (if simpler) knowledge base.
 */
final class BookIngestionService
{
    public function __construct(private readonly ?LlmClientInterface $llm = null)
    {
    }

    /**
     * Ingest a book for an agent.
     *
     * @param int    $agentId       astro_agents.id
     * @param string $source        a file path (when $isFile) or raw text
     * @param bool   $isFile        true if $source is a path to upload
     * @return array{chunks:int, digest_version:int, structured_by:string, compiled_by:string}
     */
    public function ingest(int $agentId, string $source, bool $isFile, string $bookLabel): array
    {
        $rawText = (new PdfTextExtractor())->getText($source, $isFile);
        if (trim($rawText) === '') {
            throw new \RuntimeException('No text could be extracted from the source.');
        }

        $structurer = new MarkdownStructurer($this->llm);
        $markdown = $structurer->structure($rawText);

        $chunks = (new MarkdownChunker())->chunk($markdown);
        if ($chunks === []) {
            throw new \RuntimeException('Structuring produced no chunks.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Replace prior chunks (re-compile path).
            $pdo->prepare('DELETE FROM agent_knowledge WHERE agent_id = :id')
                ->execute([':id' => $agentId]);

            $insert = $pdo->prepare(
                'INSERT INTO agent_knowledge (agent_id, chunk_index, heading_path, markdown_text, topic_tags)
                 VALUES (:id, :idx, :path, :text, :tags)'
            );
            foreach ($chunks as $c) {
                $insert->execute([
                    ':id' => $agentId,
                    ':idx' => $c['chunk_index'],
                    ':path' => $c['heading_path'] !== '' ? $c['heading_path'] : null,
                    ':text' => $c['markdown_text'],
                    ':tags' => $c['topic_tags'] !== '' ? $c['topic_tags'] : null,
                ]);
            }

            // Compile + store a new digest version.
            $compiled = (new DigestCompiler($this->llm))->compile($chunks, $bookLabel);
            $nextVersion = $this->nextDigestVersion($agentId);
            $pdo->prepare(
                'INSERT INTO agent_digest (agent_id, digest_json, version, compiled_at)
                 VALUES (:id, :digest, :ver, NOW())'
            )->execute([
                ':id' => $agentId,
                ':digest' => json_encode($compiled['digest'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ':ver' => $nextVersion,
            ]);

            $pdo->commit();

            return [
                'chunks' => count($chunks),
                'digest_version' => $nextVersion,
                'structured_by' => $this->llm !== null ? 'llm' : 'heuristic',
                'compiled_by' => $compiled['compiled_by'],
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function nextDigestVersion(int $agentId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(version), 0) + 1 FROM agent_digest WHERE agent_id = :id'
        );
        $stmt->execute([':id' => $agentId]);
        return (int) $stmt->fetchColumn();
    }
}
