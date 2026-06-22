<?php
declare(strict_types=1);

namespace AutoBusiness\Core;

/**
 * Minimal .env loader. Parses KEY=VALUE lines into an internal store (and into
 * getenv/$_ENV) without any third-party dependency. Lines starting with # and
 * blank lines are ignored; inline "# comment" tails are stripped on unquoted
 * values; surrounding single/double quotes are removed.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_readable($path)) {
            // No .env on disk (e.g. CI) — fall back to the real process env.
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));

            // Strip an inline comment only on unquoted values.
            if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
                $hash = strpos($value, ' #');
                if ($hash !== false) {
                    $value = rtrim(substr($value, 0, $hash));
                }
            }
            // Remove surrounding quotes if present.
            if (strlen($value) >= 2
                && (($value[0] === '"' && $value[-1] === '"')
                 || ($value[0] === "'" && $value[-1] === "'"))) {
                $value = substr($value, 1, -1);
            }

            self::$vars[$key] = $value;
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }
        $fromEnv = getenv($key);
        return $fromEnv === false ? $default : $fromEnv;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);
        return ($value === null || $value === '') ? $default : (int) $value;
    }

    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing required environment variable: {$key}");
        }
        return $value;
    }
}
