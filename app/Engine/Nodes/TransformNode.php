<?php
declare(strict_types=1);

namespace AutoBusiness\Engine\Nodes;

/**
 * Safe Transform node — the "Custom Code" node implemented as a WHITELISTED
 * transform DSL, never eval() and never user-supplied callables (Global
 * Security Rule).
 *
 * Configuration is a list of steps; each step assigns the result of one
 * whitelisted operation to a named field. Operands are already token-resolved
 * by the engine before this node runs, so steps reference plain values.
 *
 * Example (token-resolved) input:
 *   {
 *     "steps": [
 *       {"set": "full",  "op": "concat", "args": ["Hi ", "Ada"]},
 *       {"set": "shout",  "op": "upper",  "args": ["{{ Nodes.X.output.name }}"]},
 *       {"set": "total",  "op": "add",    "args": [2, 3]}
 *     ]
 *   }
 * Output: { "full": "Hi Ada", "shout": "ADA", "total": 5 }
 *
 * Only the operations in self::OPS are permitted; an unknown op throws.
 */
final class TransformNode extends AbstractNode
{
    private const OPS = [
        // string ops
        'concat', 'upper', 'lower', 'trim', 'length', 'replace', 'split', 'join',
        // math ops
        'add', 'sub', 'mul', 'div', 'mod', 'round', 'min', 'max', 'abs',
        // array ops
        'get', 'count', 'first', 'last',
    ];

    public function execute(array $input): array
    {
        $steps = $input['steps'] ?? [];
        if (!is_array($steps)) {
            throw new \InvalidArgumentException('Transform "steps" must be a list.');
        }

        $out = [];
        foreach ($steps as $i => $step) {
            if (!is_array($step) || !isset($step['set'], $step['op'])) {
                throw new \InvalidArgumentException("Transform step #{$i} missing 'set'/'op'.");
            }
            $op = (string) $step['op'];
            if (!in_array($op, self::OPS, true)) {
                throw new \InvalidArgumentException("Transform op not whitelisted: {$op}");
            }
            $args = isset($step['args']) && is_array($step['args']) ? array_values($step['args']) : [];
            $out[(string) $step['set']] = $this->apply($op, $args);
        }
        return $out;
    }

    /**
     * @param list<mixed> $a
     */
    private function apply(string $op, array $a): mixed
    {
        return match ($op) {
            'concat'  => implode('', array_map(static fn($v) => (string) $v, $a)),
            'upper'   => mb_strtoupper((string) ($a[0] ?? '')),
            'lower'   => mb_strtolower((string) ($a[0] ?? '')),
            'trim'    => trim((string) ($a[0] ?? '')),
            'length'  => mb_strlen((string) ($a[0] ?? '')),
            'replace' => str_replace((string) ($a[0] ?? ''), (string) ($a[1] ?? ''), (string) ($a[2] ?? '')),
            'split'   => explode((string) ($a[1] ?? ','), (string) ($a[0] ?? '')),
            'join'    => implode((string) ($a[1] ?? ','), is_array($a[0] ?? null) ? $a[0] : []),

            'add'     => (float) ($a[0] ?? 0) + (float) ($a[1] ?? 0),
            'sub'     => (float) ($a[0] ?? 0) - (float) ($a[1] ?? 0),
            'mul'     => (float) ($a[0] ?? 0) * (float) ($a[1] ?? 0),
            'div'     => (float) ($a[1] ?? 0) == 0.0
                            ? throw new \InvalidArgumentException('Transform div by zero')
                            : (float) ($a[0] ?? 0) / (float) ($a[1]),
            'mod'     => (int) ($a[1] ?? 0) === 0
                            ? throw new \InvalidArgumentException('Transform mod by zero')
                            : (int) ($a[0] ?? 0) % (int) ($a[1]),
            'round'   => round((float) ($a[0] ?? 0), (int) ($a[1] ?? 0)),
            'min'     => min(array_map(static fn($v) => (float) $v, $a)),
            'max'     => max(array_map(static fn($v) => (float) $v, $a)),
            'abs'     => abs((float) ($a[0] ?? 0)),

            'get'     => is_array($a[0] ?? null) ? ($a[0][$a[1] ?? 0] ?? null) : null,
            'count'   => is_array($a[0] ?? null) ? count($a[0]) : 0,
            'first'   => is_array($a[0] ?? null) ? ($a[0][array_key_first($a[0]) ?? 0] ?? null) : null,
            'last'    => is_array($a[0] ?? null) ? ($a[0][array_key_last($a[0]) ?? 0] ?? null) : null,

            default   => throw new \InvalidArgumentException("Unhandled op: {$op}"),
        };
    }
}
