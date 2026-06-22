<?php
declare(strict_types=1);

namespace AutoBusiness\Engine;

use AutoBusiness\Engine\Nodes\HttpRequestNode;
use AutoBusiness\Engine\Nodes\IfElseNode;
use AutoBusiness\Engine\Nodes\TransformNode;
use AutoBusiness\Engine\Nodes\TriggerNode;
use AutoBusiness\Security\CredentialVault;

/**
 * Maps a node "type" string (as stored in workflow_graph_json) to a concrete
 * NodeInterface instance, wiring in dependencies (e.g. the CredentialVault for
 * HTTP nodes). New node types from later modules register here.
 */
final class NodeFactory
{
    public function __construct(private readonly ?CredentialVault $vault = null)
    {
    }

    public function make(string $type): NodeInterface
    {
        return match ($type) {
            'trigger', 'webhook', 'cron' => new TriggerNode(),
            'if', 'ifelse'               => new IfElseNode(),
            'transform'                  => new TransformNode(),
            'http', 'http_request'       => new HttpRequestNode($this->vault),
            default => throw new \InvalidArgumentException("Unknown node type: {$type}"),
        };
    }

    public function isTrigger(string $type): bool
    {
        return in_array($type, ['trigger', 'webhook', 'cron'], true);
    }
}
