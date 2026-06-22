<?php
declare(strict_types=1);

namespace AutoBusiness\Engine\Nodes;

/**
 * If/Else logic node. Compares two already-resolved operands with a whitelisted
 * operator and exposes the result on a "true" or "false" handle so the engine
 * prunes the branch that wasn't taken.
 *
 * Expected (token-resolved) input:
 *   left, operator (== != > >= < <= contains in empty notEmpty), right
 */
final class IfElseNode extends AbstractNode
{
    public function execute(array $input): array
    {
        $left  = $input['left']  ?? null;
        $right = $input['right'] ?? null;
        $op    = (string) ($input['operator'] ?? '==');

        $result = match ($op) {
            '=='        => $left == $right,
            '==='       => $left === $right,
            '!='        => $left != $right,
            '>'         => (float) $left >  (float) $right,
            '>='        => (float) $left >= (float) $right,
            '<'         => (float) $left <  (float) $right,
            '<='        => (float) $left <= (float) $right,
            'contains'  => is_string($left) && str_contains($left, (string) $right),
            'in'        => is_array($right) && in_array($left, $right, false),
            'empty'     => $left === null || $left === '' || $left === [],
            'notEmpty'  => !($left === null || $left === '' || $left === []),
            default     => throw new \InvalidArgumentException("Unknown operator: {$op}"),
        };

        return ['result' => $result];
    }

    /** Follow only the branch matching the boolean result. */
    public function activeHandles(array $output): array
    {
        return !empty($output['result']) ? ['true'] : ['false'];
    }
}
