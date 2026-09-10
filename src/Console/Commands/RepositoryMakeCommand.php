<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Commands;

use Harryes\LaravelDddKit\Console\Concerns\ManagesStubs;
use Harryes\LaravelDddKit\Console\Concerns\ReadsTypedInput;
use Harryes\LaravelDddKit\Support\Config;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class RepositoryMakeCommand extends Command
{
    use ManagesStubs;
    use ReadsTypedInput;

    protected $signature = 'ddd:repository {name : Domain and aggregate name, e.g. Contact/Lead}';

    protected $description = "Generate a repository interface and its Eloquent implementation for an aggregate, and bind them in the domain's service provider";

    public function handle(): int
    {
        $segments = explode('/', $this->stringArgument('name'));

        if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
            $this->components->error('Expected {domain}/{name}, e.g. Contact/Lead.');

            return self::FAILURE;
        }

        [$domainInput, $nameInput] = $segments;

        $domain = Str::studly($domainInput);
        $aggregate = Str::studly($nameInput);
        $domainPath = rtrim(Config::string('ddd.base_path'), '/').'/'.$domain;

        if (! File::isDirectory($domainPath.'/Domain')) {
            $this->components->error("Domain [{$domain}] does not exist. Run `ddd:domain {$domain}` first.");

            return self::FAILURE;
        }

        $entityFile = $domainPath.'/Domain/Entities/'.$aggregate.'.php';

        if (! File::exists($entityFile)) {
            $this->components->error("Aggregate [{$aggregate}] does not exist. Run `ddd:entity {$domain}/{$aggregate} --aggregate` first.");

            return self::FAILURE;
        }

        if (! str_contains(File::get($entityFile), 'extends AggregateRoot')) {
            $this->components->warn("[{$aggregate}] does not extend AggregateRoot — repositories should only expose aggregate roots.");
        }

        $interfaceFile = $domainPath.'/Domain/Repositories/'.$aggregate.'Repository.php';
        $modelFile = $domainPath.'/Infrastructure/Persistence/Eloquent/'.$aggregate.'Model.php';
        $implementationFile = $domainPath.'/Infrastructure/Persistence/Repositories/Eloquent'.$aggregate.'Repository.php';

        $existing = array_filter(
            [$interfaceFile, $modelFile, $implementationFile],
            static fn (string $path): bool => File::exists($path)
        );

        if ($existing !== []) {
            $this->components->error('Already exists: '.implode(', ', $existing));

            return self::FAILURE;
        }

        $baseNamespace = rtrim(Config::string('ddd.base_namespace'), '\\')."\\{$domain}";
        $entityNamespace = "{$baseNamespace}\\Domain\\Entities";
        $repositoryNamespace = "{$baseNamespace}\\Domain\\Repositories";
        $modelNamespace = "{$baseNamespace}\\Infrastructure\\Persistence\\Eloquent";
        $implementationNamespace = "{$baseNamespace}\\Infrastructure\\Persistence\\Repositories";
        $variable = Str::camel($aggregate);

        File::ensureDirectoryExists(dirname($interfaceFile));
        File::put($interfaceFile, $this->populateStub($this->stub('repository/interface.stub'), [
            'namespace' => $repositoryNamespace,
            'entity_namespace' => $entityNamespace,
            'class' => $aggregate,
            'variable' => $variable,
        ]));

        File::ensureDirectoryExists(dirname($modelFile));
        File::put($modelFile, $this->populateStub($this->stub('repository/model.stub'), [
            'namespace' => $modelNamespace,
            'class' => $aggregate,
        ]));

        File::ensureDirectoryExists(dirname($implementationFile));
        File::put($implementationFile, $this->populateStub($this->stub('repository/eloquent-repository.stub'), [
            'namespace' => $implementationNamespace,
            'entity_namespace' => $entityNamespace,
            'domain_repository_namespace' => $repositoryNamespace,
            'model_namespace' => $modelNamespace,
            'class' => $aggregate,
            'variable' => $variable,
        ]));

        $this->components->info("Generated {$aggregate}Repository interface at {$interfaceFile}.");
        $this->components->info("Generated {$aggregate}Model at {$modelFile}.");
        $this->components->info("Generated Eloquent{$aggregate}Repository at {$implementationFile}.");

        $this->bindInServiceProvider(
            $domainPath,
            $domain,
            "{$repositoryNamespace}\\{$aggregate}Repository",
            "{$implementationNamespace}\\Eloquent{$aggregate}Repository"
        );

        return self::SUCCESS;
    }

    private function bindInServiceProvider(string $domainPath, string $domain, string $interfaceFqcn, string $implementationFqcn): void
    {
        $providerFile = $domainPath.'/Infrastructure/Providers/'.$domain.'ServiceProvider.php';

        if (! File::exists($providerFile)) {
            $this->components->warn("Could not find {$providerFile} — bind {$interfaceFqcn}::class to {$implementationFqcn}::class manually.");

            return;
        }

        $contents = File::get($providerFile);

        if (str_contains($contents, $implementationFqcn.'::class')) {
            return;
        }

        $anchor = "// Added automatically by `ddd:repository`.\n";

        if (! str_contains($contents, $anchor)) {
            $this->components->warn("Could not find the auto-bind anchor in {$providerFile} — bind {$interfaceFqcn}::class to {$implementationFqcn}::class manually.");

            return;
        }

        $bindLine = "        \$this->app->bind(\\{$interfaceFqcn}::class, \\{$implementationFqcn}::class);\n";

        File::put($providerFile, str_replace($anchor, $anchor.$bindLine, $contents));

        $this->components->info("Bound {$interfaceFqcn} to {$implementationFqcn} in {$domain}ServiceProvider.");
    }
}
