<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Support;

use RuntimeException;

/**
 * Narrows the package's config() reads to their expected types, since
 * config() itself returns mixed and every ddd.* value is meant to be a
 * string (or, for the overridable paths, a nullable string).
 */
final class Config
{
    public static function string(string $key): string
    {
        $value = config($key);

        if (! is_string($value)) {
            throw new RuntimeException("Expected config [{$key}] to be a string, got ".get_debug_type($value).'.');
        }

        return $value;
    }

    public static function nullableString(string $key): ?string
    {
        $value = config($key);

        if ($value !== null && ! is_string($value)) {
            throw new RuntimeException("Expected config [{$key}] to be a string or null, got ".get_debug_type($value).'.');
        }

        return $value;
    }
}
