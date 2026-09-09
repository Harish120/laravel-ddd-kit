<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Commands;

use Harryes\LaravelDddKit\Console\Concerns\ManagesStubs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class UseCaseMakeCommand extends Command
{
    use ManagesStubs;

    protected $signature = 'ddd:usecase {name : Domain and use case name, e.g. Contact/CreateLead}';

    protected $description = 'Generate a use case and its matching DTO, wired for DB::transaction and event dispatch';

    public function handle(): int
    {
        $segments = explode('/', (string) $this->argument('name'));

        if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
            $this->components->error('Expected {domain}/{name}, e.g. Contact/CreateLead.');

            return self::FAILURE;
        }

        [$domainInput, $nameInput] = $segments;

        $domain = Str::studly($domainInput);
        $useCase = Str::studly($nameInput);
        $domainPath = rtrim((string) config('ddd.base_path'), '/').'/'.$domain;

        if (! File::isDirectory($domainPath.'/Domain')) {
            $this->components->error("Domain [{$domain}] does not exist. Run `ddd:domain {$domain}` first.");

            return self::FAILURE;
        }

        $useCaseFile = $domainPath.'/Application/UseCases/'.$useCase.'.php';
        $dtoFile = $domainPath.'/Application/DTOs/'.$useCase.'Data.php';

        $existing = array_filter(
            [$useCaseFile, $dtoFile],
            static fn (string $path): bool => File::exists($path)
        );

        if ($existing !== []) {
            $this->components->error('Already exists: '.implode(', ', $existing));

            return self::FAILURE;
        }

        $appNamespace = rtrim((string) config('ddd.base_namespace'), '\\')."\\{$domain}\\Application";
        $useCaseNamespace = "{$appNamespace}\\UseCases";
        $dtoNamespace = "{$appNamespace}\\DTOs";

        File::ensureDirectoryExists(dirname($dtoFile));
        File::put($dtoFile, $this->populateStub($this->stub('usecase/data.stub'), [
            'dto_namespace' => $dtoNamespace,
            'class' => $useCase,
        ]));

        File::ensureDirectoryExists(dirname($useCaseFile));
        File::put($useCaseFile, $this->populateStub($this->stub('usecase/usecase.stub'), [
            'namespace' => $useCaseNamespace,
            'dto_namespace' => $dtoNamespace,
            'class' => $useCase,
        ]));

        $this->components->info("Generated {$useCase} use case at {$useCaseFile}.");
        $this->components->info("Generated {$useCase}Data DTO at {$dtoFile}.");

        $this->writeCompanionTest(
            $this->testsBasePath()."/Unit/Domains/{$domain}/Application/UseCases/{$useCase}Test.php",
            'usecase/usecase-test.stub',
            ['namespace' => $useCaseNamespace, 'dto_namespace' => $dtoNamespace, 'class' => $useCase]
        );

        return self::SUCCESS;
    }
}
