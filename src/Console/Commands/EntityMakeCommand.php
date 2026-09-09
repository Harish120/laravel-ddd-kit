<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Commands;

use Harryes\LaravelDddKit\Console\Concerns\ManagesStubs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class EntityMakeCommand extends Command
{
    use ManagesStubs;

    protected $signature = 'ddd:entity
        {name : Domain and entity name, e.g. Contact/Lead}
        {--aggregate : Generate an aggregate root instead of a plain entity}';

    protected $description = "Generate an entity or aggregate root inside a domain's Domain/Entities directory";

    public function handle(): int
    {
        $segments = explode('/', (string) $this->argument('name'));

        if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
            $this->components->error('Expected {domain}/{name}, e.g. Contact/Lead.');

            return self::FAILURE;
        }

        [$domainInput, $nameInput] = $segments;

        $domain = Str::studly($domainInput);
        $entity = Str::studly($nameInput);
        $domainPath = rtrim((string) config('ddd.base_path'), '/').'/'.$domain;

        if (! File::isDirectory($domainPath.'/Domain')) {
            $this->components->error("Domain [{$domain}] does not exist. Run `ddd:domain {$domain}` first.");

            return self::FAILURE;
        }

        $file = $domainPath.'/Domain/Entities/'.$entity.'.php';

        if (File::exists($file)) {
            $this->components->error("Entity [{$entity}] already exists at {$file}.");

            return self::FAILURE;
        }

        $isAggregate = (bool) $this->option('aggregate');
        $namespace = rtrim((string) config('ddd.base_namespace'), '\\')."\\{$domain}\\Domain\\Entities";

        File::ensureDirectoryExists(dirname($file));
        File::put($file, $this->populateStub(
            $this->stub($isAggregate ? 'entity/aggregate.stub' : 'entity/entity.stub'),
            ['namespace' => $namespace, 'class' => $entity]
        ));

        $kind = $isAggregate ? 'aggregate root' : 'entity';
        $this->components->info("Generated {$entity} {$kind} at {$file}.");

        return self::SUCCESS;
    }
}
