<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Commands;

use Harryes\LaravelDddKit\Console\Concerns\ManagesStubs;
use Harryes\LaravelDddKit\Console\Concerns\ReadsTypedInput;
use Harryes\LaravelDddKit\Support\Config;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class EventMakeCommand extends Command
{
    use ManagesStubs;
    use ReadsTypedInput;

    protected $signature = 'ddd:event {name : Domain and event name, e.g. Contact/LeadWasCreated}';

    protected $description = "Generate a plain PHP domain event inside a domain's Domain/Events directory";

    public function handle(): int
    {
        $segments = explode('/', $this->stringArgument('name'));

        if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
            $this->components->error('Expected {domain}/{name}, e.g. Contact/LeadWasCreated.');

            return self::FAILURE;
        }

        [$domainInput, $nameInput] = $segments;

        $domain = Str::studly($domainInput);
        $event = Str::studly($nameInput);
        $domainPath = rtrim(Config::string('ddd.base_path'), '/').'/'.$domain;

        if (! File::isDirectory($domainPath.'/Domain')) {
            $this->components->error("Domain [{$domain}] does not exist. Run `ddd:domain {$domain}` first.");

            return self::FAILURE;
        }

        $file = $domainPath.'/Domain/Events/'.$event.'.php';

        if (File::exists($file)) {
            $this->components->error("Event [{$event}] already exists at {$file}.");

            return self::FAILURE;
        }

        $namespace = rtrim(Config::string('ddd.base_namespace'), '\\')."\\{$domain}\\Domain\\Events";

        File::ensureDirectoryExists(dirname($file));
        File::put($file, $this->populateStub($this->stub('event/event.stub'), [
            'namespace' => $namespace,
            'class' => $event,
        ]));

        $this->components->info("Generated {$event} event at {$file}.");

        return self::SUCCESS;
    }
}
