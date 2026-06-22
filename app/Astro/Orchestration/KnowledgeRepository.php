<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Orchestration;

use AutoBusiness\Core\Database;
use PDO;

/**
 * Retrieval layer that ENFORCES strict single-book isolation at the DATA layer.
 *
 * Every query is bound to a single agent_id, so an agent is physically never
 * given another book's text. Cross-book retrieval is impossible through this
 * repository — there is no method that reads across agents. This is the
 * non-negotiable half of isolation that prompting alone cannot guarantee.
 */
final class KnowledgeRepository
{
    /**
     * The current compiled digest for one agent (its "own version").
     *
     * @return array<string,mixed>|null
     */
    public function digestFor(int $agentId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT digest_json, version, compiled_at
               FROM agent_digest
              WHERE agent_id = :id
              ORDER BY version DESC
              LIMIT 1'
        );
        $stmt->execute([':id' => $agentId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'version' => (int) $row['version'],
            'compiled_at' => $row['compiled_at'],
            'digest' => json_decode((string) $row['digest_json'], true),
        ];
    }

    /**
     * Retrieve the most relevant Markdown chunks for an agent, matched to the
     * chart's placements via topic tags. STRICTLY scoped to this agent_id.
     *
     * @param string[] $topicTags e.g. ['mars','7th-house','remedies']
     * @return list<array{heading_path:?string, markdown_text:string, topic_tags:?string}>
     */
    public function chunksFor(int $agentId, array $topicTags, int $limit = 12): array
    {
        $pdo = Database::pdo();

        if ($topicTags === []) {
            $stmt = $pdo->prepare(
                'SELECT heading_path, markdown_text, topic_tags
                   FROM agent_knowledge
                  WHERE agent_id = :id
                  ORDER BY chunk_index ASC
                  LIMIT :lim'
            );
            $stmt->bindValue(':id', $agentId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        // Score chunks by how many requested tags appear in their topic_tags.
        $likeClauses = [];
        $params = [':id' => $agentId];
        foreach (array_values($topicTags) as $i => $tag) {
            $key = ':t' . $i;
            $likeClauses[] = "topic_tags LIKE {$key}";
            $params[$key] = '%' . $tag . '%';
        }
        $where = implode(' OR ', $likeClauses);

        $sql = "SELECT heading_path, markdown_text, topic_tags
                  FROM agent_knowledge
                 WHERE agent_id = :id AND ({$where})
                 ORDER BY chunk_index ASC
                 LIMIT " . (int) $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Fall back to the digest-only path (handled by caller) when nothing matched.
        return $rows;
    }

    /**
     * Build the isolated knowledge slice for an agent: its digest plus the
     * chunks matched to the chart. This is the ONLY text the agent will see.
     *
     * @param array<string,mixed> $chart
     * @return array{digest: ?array<string,mixed>, chunks: list<array<string,mixed>>}
     */
    public function sliceForAgent(int $agentId, array $chart): array
    {
        $tags = self::topicTagsFromChart($chart);
        return [
            'digest' => $this->digestFor($agentId),
            'chunks' => $this->chunksFor($agentId, $tags),
        ];
    }

    /**
     * Derive retrieval tags from the chart: each planet, its sign, and house.
     *
     * @param array<string,mixed> $chart
     * @return string[]
     */
    public static function topicTagsFromChart(array $chart): array
    {
        $tags = [];
        foreach (($chart['planets'] ?? []) as $planet => $p) {
            $tags[] = strtolower((string) $planet);
            if (isset($p['sign'])) {
                $tags[] = strtolower((string) $p['sign']);
            }
            if (isset($p['house'])) {
                $tags[] = self::ordinal((int) $p['house']) . '-house';
            }
        }
        if (isset($chart['ascendant']['sign'])) {
            $tags[] = strtolower((string) $chart['ascendant']['sign']);
            $tags[] = 'lagna';
        }
        $tags[] = 'remedies';
        return array_values(array_unique($tags));
    }

    private static function ordinal(int $n): string
    {
        return (string) $n; // tags use '7-house'; ingestion tags chunks the same way
    }
}
