<?php
declare(strict_types=1);

namespace AutoBusiness\Engine;

/**
 * ExecutionEngine — the DAG parser/walker.
 *
 * It topologically sorts the workflow nodes, starts at the Trigger, and walks
 * Logic/Action/AI nodes, passing state strictly as JSON-compatible arrays. Every
 * node implements execute(array $input): array.
 *
 * Resumability (critical for A2): execution is checkpointed after every node and
 * bounded by a wall-clock deadline. When the deadline is hit the engine returns
 * status "paused" with the full state; the runner persists that to
 * job_queue.state_json and calls run() again on the next cron tick to resume
 * exactly where it left off.
 *
 * Expected graph shape (stored in workflows.workflow_graph_json):
 *   {
 *     "nodes": [ {"id","name","type","data":{...}}, ... ],
 *     "connections": [ {"from","fromHandle","to","toHandle"}, ... ]
 *   }
 *
 * State shape (also the resume token):
 *   {
 *     "_trigger": {...payload...},
 *     "Nodes": { "<nodeName>": {"output": {...}} },
 *     "_engine": { "order":[ids], "completed":[ids], "active":[ids] }
 *   }
 */
final class ExecutionEngine
{
    public function __construct(private readonly NodeFactory $factory)
    {
    }

    /**
     * @param array<string,mixed> $graph   nodes + connections
     * @param array<string,mixed> $state   prior state for resume, or fresh state
     * @param float               $deadline microtime(true) after which to pause
     * @param callable|null       $checkpoint fn(array $state): void after each node
     * @return array{status:string, state:array<string,mixed>}
     */
    public function run(array $graph, array $state, float $deadline, ?callable $checkpoint = null): array
    {
        /** @var array<string,array<string,mixed>> $nodes id => node */
        $nodes = [];
        foreach (($graph['nodes'] ?? []) as $node) {
            if (!isset($node['id'])) {
                throw new \InvalidArgumentException('Every node needs an "id".');
            }
            $nodes[(string) $node['id']] = $node;
        }
        $connections = $graph['connections'] ?? [];

        // Build the outgoing-connection index on EVERY call (including resume
        // ticks), since the topological plan is only computed on the first call.
        $this->buildConnectionIndex($nodes, $connections);

        // First call: compute the execution order and seed the active set with
        // the trigger node(s). Subsequent calls reuse the stored plan.
        if (!isset($state['_engine'])) {
            $order = $this->topologicalSort($nodes, $connections);
            $active = [];
            foreach ($order as $id) {
                if ($this->factory->isTrigger((string) ($nodes[$id]['type'] ?? ''))) {
                    $active[$id] = true;
                }
            }
            // If no explicit trigger, the first node in topo order starts it.
            if ($active === [] && $order !== []) {
                $active[$order[0]] = true;
            }
            $state['_engine'] = [
                'order'     => $order,
                'completed' => [],
                'active'    => array_keys($active),
            ];
            $state['Nodes'] ??= [];
        }

        $order     = $state['_engine']['order'];
        $completed = array_fill_keys($state['_engine']['completed'], true);
        $active    = array_fill_keys($state['_engine']['active'], true);

        foreach ($order as $id) {
            if (isset($completed[$id])) {
                continue;
            }

            $node = $nodes[$id] ?? null;
            if ($node === null) {
                $completed[$id] = true;
                continue;
            }

            // A node only runs if it is active (reachable via a taken branch).
            if (isset($active[$id])) {
                $this->executeNode($id, $node, $state, $active);
            }

            $completed[$id] = true;
            $state['_engine']['completed'] = array_keys($completed);
            $state['_engine']['active']    = array_keys($active);

            if ($checkpoint !== null) {
                $checkpoint($state);
            }

            // Time budget exhausted — pause and resume next tick.
            if (microtime(true) >= $deadline) {
                return ['status' => 'paused', 'state' => $state];
            }
        }

        return ['status' => 'done', 'state' => $state];
    }

    /**
     * Execute one node: resolve its tokens against the global state, run it,
     * store the output, then activate downstream targets via active handles.
     *
     * @param array<string,mixed>  $node
     * @param array<string,mixed>  $state  (by reference)
     * @param array<string,bool>   $active (by reference)
     */
    private function executeNode(string $id, array $node, array &$state, array &$active): void
    {
        $type = (string) ($node['type'] ?? '');
        $name = (string) ($node['name'] ?? $id);
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        // Trigger nodes receive the original job payload.
        if ($this->factory->isTrigger($type)) {
            $data['payload'] = $state['_trigger'] ?? [];
        }

        $resolved = TokenResolver::resolve($data, $state);

        try {
            $instance = $this->factory->make($type);
            /** @var array<string,mixed> $output */
            $output = $instance->execute(is_array($resolved) ? $resolved : []);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Node '{$name}' ({$type}) failed: " . $e->getMessage(),
                0,
                $e
            );
        }

        $state['Nodes'][$name] = ['output' => $output];

        // Activate downstream targets reachable from this node's active handles.
        $activeHandles = array_fill_keys($instance->activeHandles($output), true);
        foreach ($this->outgoing($id) as $conn) {
            $handle = (string) ($conn['fromHandle'] ?? 'output');
            if (isset($activeHandles[$handle])) {
                $active[(string) $conn['to']] = true;
            }
        }
    }

    /** Outgoing-connection index, keyed by source node id (built during sort). */
    private array $connIndex = [];

    /** @return array<int,array<string,mixed>> */
    private function outgoing(string $fromId): array
    {
        return $this->connIndex[$fromId] ?? [];
    }

    /**
     * Build the outgoing-connection index (source id => connections) used to
     * propagate branch activation during the walk. Rebuilt on every run() so
     * resume ticks (which skip the topological sort) still have it.
     *
     * @param array<string,array<string,mixed>> $nodes
     * @param array<int,array<string,mixed>>    $connections
     */
    private function buildConnectionIndex(array $nodes, array $connections): void
    {
        $this->connIndex = [];
        foreach ($connections as $conn) {
            $from = (string) ($conn['from'] ?? '');
            $to   = (string) ($conn['to'] ?? '');
            if (!isset($nodes[$from], $nodes[$to])) {
                continue;
            }
            $this->connIndex[$from][] = $conn;
        }
    }

    /**
     * Kahn's algorithm — produces a topological order and detects cycles.
     *
     * @param array<string,array<string,mixed>> $nodes
     * @param array<int,array<string,mixed>>    $connections
     * @return string[] node ids in execution order
     */
    private function topologicalSort(array $nodes, array $connections): array
    {
        $inDegree = [];
        $adjacency = [];

        foreach (array_keys($nodes) as $id) {
            $inDegree[$id] = 0;
            $adjacency[$id] = [];
        }

        foreach ($connections as $conn) {
            $from = (string) ($conn['from'] ?? '');
            $to   = (string) ($conn['to'] ?? '');
            if (!isset($nodes[$from], $nodes[$to])) {
                continue;
            }
            $adjacency[$from][] = $to;
            $inDegree[$to]++;
        }

        // Seed the queue with all zero-in-degree nodes.
        $queue = [];
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) {
                $queue[] = $id;
            }
        }

        $order = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            $order[] = $id;
            foreach ($adjacency[$id] as $next) {
                if (--$inDegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        if (count($order) !== count($nodes)) {
            throw new \RuntimeException('Workflow graph has a cycle; cannot execute.');
        }
        return $order;
    }
}
