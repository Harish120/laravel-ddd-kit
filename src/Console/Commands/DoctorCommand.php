<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Commands;

use Harryes\LaravelDddKit\Support\Config;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

final class DoctorCommand extends Command
{
    protected $signature = 'ddd:doctor';

    protected $description = 'Statically scan app/Domains for violations of the DDD non-negotiables';

    private string $basePath;

    public function handle(): int
    {
        $this->basePath = rtrim(Config::string('ddd.base_path'), '/');

        if (! File::isDirectory($this->basePath)) {
            $this->components->info("No domains found at {$this->basePath}.");

            return self::SUCCESS;
        }

        $files = $this->phpFilesIn($this->basePath);

        $violations = [
            ...$this->scanForFrameworkLeaksInDomain($files),
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
     * Global helpers that reach into the container or framework state. Pure
     * utilities like value(), tap(), or blank() are deliberately left out.
     */
    private const array FRAMEWORK_HELPERS = [
        'app', 'resolve', 'config', 'env', 'event', 'dispatch', 'broadcast',
        'now', 'today', 'cache', 'session', 'request', 'auth', 'logger', 'info',
        'report', 'abort', 'abort_if', 'abort_unless', 'collect', 'validator',
        'trans', '__', 'response', 'redirect', 'route', 'url', 'view',
        'storage_path', 'base_path',
    ];

    /**
     * Every way a Domain class commonly couples itself to the framework, not
     * just a plain `use Illuminate\...` line: Eloquent models living outside
     * the Illuminate namespace, fully-qualified references that never need an
     * import, root-namespace facade aliases, and container-backed helpers.
     *
     * @param  list<SplFileInfo>  $files
     * @return list<array{0: string, 1: string}>
     */
    private function scanForFrameworkLeaksInDomain(array $files): array
    {
        $domainFiles = $this->filterByPath($files, DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR);
        $violations = [];

        foreach ($this->frameworkLeakRules() as [$pattern, $rule]) {
            foreach ($domainFiles as $file) {
                foreach ($this->codeLinesMatching($file, $pattern) as $lineNumber => $line) {
                    $violations[] = [$this->location($file, $lineNumber), $rule];
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<array{0: string, 1: string}> Pattern => rule message, in report order.
     */
    private function frameworkLeakRules(): array
    {
        $aliases = implode('|', array_map(
            static fn (string $alias): string => preg_quote($alias, '/'),
            $this->facadeAliases()
        ));

        $helpers = implode('|', array_map(
            static fn (string $helper): string => preg_quote($helper, '/'),
            self::FRAMEWORK_HELPERS
        ));

        return [
            [
                '/^\s*use\s+(?:function\s+|const\s+)?\\\\?Illuminate\\\\/',
                'Domain layer must not import Illuminate/Eloquent classes.',
            ],
            [
                '/^\s*use\s+\\\\?(?:App\\\\Models\\\\|[\w\\\\]*\\\\Infrastructure\\\\)/',
                'Domain layer must not import App\Models or Infrastructure classes.',
            ],
            [
                '/^(?!\s*use\s).*(?<![\w\\\\])\\\\Illuminate\\\\/',
                'Domain layer must not reference Illuminate classes by fully-qualified name.',
            ],
            [
                '/(?<![\w\\\\])\\\\(?:'.$aliases.')\b(?!\\\\)/',
                'Domain layer must not use Laravel facade aliases.',
            ],
            [
                '/(?<![\w$>:\\\\])(?<!function\s)\\\\?(?:'.$helpers.')\s*\(/',
                'Domain layer must not call Laravel global helpers.',
            ],
        ];
    }

    /**
     * Laravel's default aliases plus any the app registers itself.
     *
     * @return list<string>
     */
    private function facadeAliases(): array
    {
        $aliases = array_keys(Facade::defaultAliases()->all());
        $configured = config('app.aliases');

        if (is_array($configured)) {
            $aliases = [...$aliases, ...array_filter(array_keys($configured), is_string(...))];
        }

        return array_values(array_unique($aliases));
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
     * Like linesMatching(), but ignores comment lines so a docblock that
     * merely mentions now() or \Illuminate\... is not reported. Attributes
     * (#[...]) are code, not comments, so they are still scanned.
     *
     * @return array<int, string> Line number (1-indexed) => matching line.
     */
    private function codeLinesMatching(SplFileInfo $file, string $pattern): array
    {
        return array_filter(
            $this->linesMatching($file, $pattern),
            static fn (string $line): bool => preg_match('/^\s*(?:\/\/|\/\*|\*|#(?!\[))/', $line) !== 1
        );
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
