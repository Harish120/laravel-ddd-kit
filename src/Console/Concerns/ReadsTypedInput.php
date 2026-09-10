<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Concerns;

use RuntimeException;

/**
 * Narrows Command::argument()/option() reads to their expected types,
 * since both return array|string|float|int|bool|null.
 */
trait ReadsTypedInput
{
    private function stringArgument(string $key): string
    {
        $value = $this->argument($key);

        if (! is_string($value)) {
            throw new RuntimeException("Expected argument [{$key}] to be a string, got ".get_debug_type($value).'.');
        }

        return $value;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        if ($value !== null && ! is_string($value)) {
            throw new RuntimeException("Expected option [{$key}] to be a string, got ".get_debug_type($value).'.');
        }

        return $value;
    }
}
