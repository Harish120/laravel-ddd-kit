<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

final class DoctorCommand extends Command
{
    protected $signature = 'ddd:doctor';

    protected $description = 'Statically scan app/Domains for violations of the DDD non-negotiables';

    private string $basePath;

    public function handle(): int
    {
        $this->basePath = rtrim((string) config('ddd.base_path'), '/');

        if (! File::isDirectory($this->basePath)) {
            $this->components->info("No domains found at {$this->basePath}.");

            return self::SUCCESS;
        }

        $files = $this->phpFilesIn($this->basePath);

        $violations = [
            ...$this->scanForEloquentImportsInDomain($files),
            ...$this->scanForPublicEntityState($files),
            ...$this->scanForUseCasesMissingTransaction($files),
            ...$this->scanForRepositoriesReturningModels($files),
        ];

        if ($violations === []) {
            $this->components->info('No violations found.');

            return self::SUCCESS;
        }

        $this->table(['File:Line', 'Rule violated'], $violations);
        $this->components->error(count($violations).' violation(s) found.');

        return self::FAILURE;
    }

    /**
     * @param  list<SplFileInfo>  $files
     * @return list<array{0: string, 1: string}>
     */
    private function scanForEloquentImportsInDomain(array $files): array
    {
        $violations = [];

        foreach ($this->filterByPath($files, DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR) as $file) {
            foreach ($this->linesMatching($file, '/^use\s+Illuminate\\\\/') as $lineNumber => $line) {
                $violations[] = [
                    $this->location($file, $lineNumber),
                    'Domain layer must not import Illuminate/Eloquent classes.',
                ];
            }
        }

        return $violations;
    }

    /**
     * @param  list<SplFileInfo>  $files
     * @return list<array{0: string, 1: string}>
     */
    private function scanForPublicEntityState(array $files): array
    {
        $violations = [];

        foreach ($this->filterByPath($files, DIRECTORY_SEPARATOR.'Entities'.DIRECTORY_SEPARATOR) as $file) {
            foreach ($this->linesMatching($file, '/^\s*public function set[A-Z]\w*\s*\(/') as $lineNumber => $line) {
                $violations[] = [
                    $this->location($file, $lineNumber),
                    'Entities must not expose public setters.',
                ];
            }

            foreach ($this->linesMatching($file, '/^\s*public(?!\s+function)\s+(?:readonly\s+)?[\w?|\\\\]+\s+\$\w+/') as $lineNumber => $line) {
                $violations[] = [
                    $this->location($file, $lineNumber),
                    'Entities must not expose public properties.',
                ];
            }
        }

        return $violations;
    }

    /**
     * @param  list<SplFileInfo>  $files
     * @return list<array{0: string, 1: string}>
     */
    private function scanForUseCasesMissingTransaction(array $files): array
    {
        $violations = [];

        foreach ($this->filterByPath($files, DIRECTORY_SEPARATOR.'UseCases'.DIRECTORY_SEPARATOR) as $file) {
            if (! str_contains($file->getContents(), 'DB::transaction(')) {
                $violations[] = [
                    $this->location($file),
                    'Use cases must wrap writes in DB::transaction().',
                ];
            }
        }

        return $violations;
    }

    /**
     * @param  list<SplFileInfo>  $files
     * @return list<array{0: string, 1: string}>
     */
    private function scanForRepositoriesReturningModels(array $files): array
    {
        $violations = [];

        $repositories = array_filter(
            $files,
            static fn (SplFileInfo $file): bool => str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Infrastructure'.DIRECTORY_SEPARATOR)
                && str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Repositories'.DIRECTORY_SEPARATOR)
        );

        foreach ($repositories as $file) {
            foreach ($this->linesMatching($file, '/public function \w+\([^)]*\)\s*:\s*\??[\w\\\\]*Model\b/') as $lineNumber => $line) {
                $violations[] = [
                    $this->location($file, $lineNumber),
                    'Repositories must return domain entities, not Eloquent models.',
                ];
            }
        }

        return $violations;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function phpFilesIn(string $path): array
    {
        return array_values(array_filter(
            File::allFiles($path),
            static fn (SplFileInfo $file): bool => $file->getExtension() === 'php'
        ));
    }

    /**
     * @param  list<SplFileInfo>  $files
     * @return list<SplFileInfo>
     */
    private function filterByPath(array $files, string $needle): array
    {
        return array_values(array_filter(
            $files,
            static fn (SplFileInfo $file): bool => str_contains($file->getPathname(), $needle)
        ));
    }

    /**
     * @return array<int, string> Line number (1-indexed) => matching line.
     */
    private function linesMatching(SplFileInfo $file, string $pattern): array
    {
        $matches = [];

        foreach (explode("\n", $file->getContents()) as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                $matches[$index + 1] = $line;
            }
        }

        return $matches;
    }

    /**
     * Path relative to the domains base path — short and readable in the
     * report table, instead of a long absolute path.
     */
    private function location(SplFileInfo $file, ?int $lineNumber = null): string
    {
        $relative = ltrim(str_replace($this->basePath, '', $file->getPathname()), DIRECTORY_SEPARATOR);

        return $lineNumber === null ? $relative : "{$relative}:{$lineNumber}";
    }
}
