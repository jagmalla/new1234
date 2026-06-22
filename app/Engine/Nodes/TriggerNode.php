<?php
declare(strict_types=1);

namespace AutoBusiness\Engine\Nodes;

/**
 * Trigger node (Webhook / Cron). Triggers never run work inline — by the time
 * the engine runs, the trigger has already fired and its payload is in the job.
 * This node simply surfaces that payload as its output so downstream nodes can
 * read {{ Nodes.Trigger.output.* }}.
 */
final class TriggerNode extends AbstractNode
{
    /** @inheritDoc */
    public function execute(array $input): array
    {
        // The runner injects the trigger payload as "payload" in the config.
        $payload = $input['payload'] ?? [];
        return is_array($payload) ? $payload : ['value' => $payload];
    }
}
