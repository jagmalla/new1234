<?php
declare(strict_types=1);

namespace AutoBusiness\Engine;

/**
 * TokenResolver — resolves {{ dynamic.variables }} against the workflow's global
 * state before a node runs.
 *
 * Syntax:  {{ Nodes.API_Fetch.output.title }}   (dot-notation path)
 * Also supports {{ Trigger.body.email }} and any other top-level state key.
 *
 * Two behaviours:
 *  - If a string is EXACTLY one token ("{{ Nodes.X.output.items }}"), the
 *    resolved value is returned with its real type preserved (array, int, …).
 *  - If a token is embedded in surrounding text, it is interpolated as a string.
 *
 * Arrays/objects in node configuration are walked recursively so tokens can
 * appear anywhere (e.g. inside HTTP headers or a JSON body template).
 */
final class TokenResolver
{
    private const TOKEN_RE      = '/\{\{\s*([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*)\s*\}\}/';
    private const EXACT_TOKEN_RE = '/^\s*\{\{\s*([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*)\s*\}\}\s*$/';

    /**
     * Recursively resolve tokens within any value (string/array) against $state.
     *
     * @param mixed                $value
     * @param array<string,mixed>  $state
     * @return mixed
     */
    public static function resolve(mixed $value, array $state): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::resolve($v, $state);
            }
            return $out;
        }

        if (!is_string($value)) {
            return $value;
        }

        // Whole-string single token: preserve the resolved value's native type.
        if (preg_match(self::EXACT_TOKEN_RE, $value, $m) === 1) {
            return self::lookup($m[1], $state);
        }

        // Embedded token(s): interpolate as string.
        return preg_replace_callback(
            self::TOKEN_RE,
            static function (array $m) use ($state): string {
                $resolved = self::lookup($m[1], $state);
                if (is_scalar($resolved) || $resolved === null) {
                    return (string) $resolved;
                }
                return (string) json_encode($resolved);
            },
            $value
        );
    }

    /**
     * Walk a dot-notation path against the state. Returns null if any segment
     * is missing (a missing token resolves to empty rather than erroring).
     *
     * @param array<string,mixed> $state
     */
    private static function lookup(string $path, array $state): mixed
    {
        $cursor = $state;
        foreach (explode('.', $path) as $segment) {
            if (is_array($cursor) && array_key_exists($segment, $cursor)) {
                $cursor = $cursor[$segment];
            } else {
                return null;
            }
        }
        return $cursor;
    }
}
