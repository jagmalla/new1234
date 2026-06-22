<?php
declare(strict_types=1);

namespace AutoBusiness\Engine\Nodes;

use AutoBusiness\Engine\NodeInterface;

/**
 * Convenience base: a single default "output" handle. Branching nodes (If/Else)
 * override activeHandles().
 */
abstract class AbstractNode implements NodeInterface
{
    /** @inheritDoc */
    public function activeHandles(array $output): array
    {
        return ['output'];
    }
}
