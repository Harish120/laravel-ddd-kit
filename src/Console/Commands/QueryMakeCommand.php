<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Commands;

use Harryes\LaravelDddKit\Console\Concerns\ManagesStubs;
use Harryes\LaravelDddKit\Console\Concerns\ReadsTypedInput;
use Harryes\LaravelDddKit\Support\Config;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class QueryMakeCommand extends Command
{
    use ManagesStubs;
    use ReadsTypedInput;

    protected $signature = 'ddd:query {name : Domain and query name, e.g. Contact/ListActiveLeads}';

    protected $description = "Generate a CQRS-lite read-model query inside a domain's Application/Queries directory";

    public function handle(): int
    {
        $segments = explode('/', $this->stringArgument('name'));

        if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
            $this->components->error('Expected {domain}/{name}, e.g. Contact/ListActiveLeads.');

            return self::FAILURE;
        }

        [$domainInput, $nameInput] = $segments;

        $domain = Str::studly($domainInput);
        $query = Str::studly($nameInput);
        $domainPath = rtrim(Config::string('ddd.base_path'), '/').'/'.$domain;

        if (! File::isDirectory($domainPath.'/Domain')) {
            $this->components->error("Domain [{$domain}] does not exist. Run `ddd:domain {$domain}` first.");

            return self::FAILURE;
        }

        $file = $domainPath.'/Application/Queries/'.$query.'.php';

        if (File::exists($file)) {
            $this->components->error("Query [{$query}] already exists at {$file}.");

            return self::FAILURE;
        }

        $namespace = rtrim(Config::string('ddd.base_namespace'), '\\')."\\{$domain}\\Application\\Queries";

        File::ensureDirectoryExists(dirname($file));
        File::put($file, $this->populateStub($this->stub('query/query.stub'), [
            'namespace' => $namespace,
            'class' => $query,
        ]));

        $this->components->info("Generated {$query} query at {$file}.");

        return self::SUCCESS;
    }
}
