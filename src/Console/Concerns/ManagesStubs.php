<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Concerns;

use Harryes\LaravelDddKit\Support\Config;
use Illuminate\Support\Facades\File;

trait ManagesStubs
{
    /**
     * Resolve stub contents, preferring a consumer's published override.
     */
    private function stub(string $name): string
    {
        $published = Config::nullableString('ddd.stubs_path');

        $path = $published !== null && File::exists($published.'/'.$name)
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

    private function testsBasePath(): string
    {
        return rtrim(Config::nullableString('ddd.tests_path') ?? base_path('tests'), '/');
    }

    /**
     * Generate a companion Pest test alongside a stub, unless disabled.
     *
     * @param  array<string, string>  $replacements
     */
    private function writeCompanionTest(string $testFile, string $stubName, array $replacements): void
    {
        if (! (bool) config('ddd.generate_tests')) {
            return;
        }

        File::ensureDirectoryExists(dirname($testFile));
        File::put($testFile, $this->populateStub($this->stub($stubName), $replacements));

        $this->components->info("Generated test at {$testFile}.");
    }
}
