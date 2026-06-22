<?php
declare(strict_types=1);

namespace AutoBusiness\Engine;

/**
 * Every executable node implements this contract. Per the Global Architecture
 * Rules state is passed strictly as JSON-compatible arrays between nodes.
 */
interface NodeInterface
{
    /**
     * Execute the node with its already-token-resolved configuration/input.
     *
     * @param array<string,mixed> $input resolved node config + upstream data
     * @return array<string,mixed> JSON-serializable output for downstream nodes
     */
    public function execute(array $input): array;

    /**
     * Which output handles/ports are "active" given this node's output. The
     * engine follows only connections leaving an active handle, which is how
     * If/Else branch pruning works. Most nodes return their single default port.
     *
     * @param array<string,mixed> $output the value returned by execute()
     * @return string[] active handle names
     */
    public function activeHandles(array $output): array;
}
