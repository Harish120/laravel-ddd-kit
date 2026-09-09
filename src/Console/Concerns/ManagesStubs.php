<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Concerns;

use Illuminate\Support\Facades\File;

trait ManagesStubs
{
    /**
     * Resolve stub contents, preferring a consumer's published override.
     */
    private function stub(string $name): string
    {
        $published = config('ddd.stubs_path');

        $path = $published && File::exists($published.'/'.$name)
            ? $published.'/'.$name
            : __DIR__.'/../../../stubs/'.$name;

        return File::get($path);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function populateStub(string $stub, array $replacements): string
    {
        $search = array_map(
            static fn (string $key): string => '{{ '.$key.' }}',
            array_keys($replacements)
        );

        return str_replace($search, array_values($replacements), $stub);
    }
}
