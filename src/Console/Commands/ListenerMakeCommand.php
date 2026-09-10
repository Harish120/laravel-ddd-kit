<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Commands;

use Harryes\LaravelDddKit\Console\Concerns\ManagesStubs;
use Harryes\LaravelDddKit\Console\Concerns\ReadsTypedInput;
use Harryes\LaravelDddKit\Support\Config;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class ListenerMakeCommand extends Command
{
    use ManagesStubs;
    use ReadsTypedInput;

    protected $signature = 'ddd:listener
        {name : Domain and listener name, e.g. Billing/CreateInvoiceOnLeadWasCreated}
        {--event= : The domain event to listen for, formatted domain/event, e.g. Contact/LeadWasCreated}';

    protected $description = "Generate a listener inside a domain's Application/Listeners directory, optionally wired to a specific (possibly cross-domain) event";

    public function handle(): int
    {
        $segments = explode('/', $this->stringArgument('name'));

        if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
            $this->components->error('Expected {domain}/{name}, e.g. Billing/CreateInvoiceOnLeadWasCreated.');

            return self::FAILURE;
        }

        [$domainInput, $nameInput] = $segments;

        $domain = Str::studly($domainInput);
        $listener = Str::studly($nameInput);
        $domainPath = rtrim(Config::string('ddd.base_path'), '/').'/'.$domain;

        if (! File::isDirectory($domainPath.'/Domain')) {
            $this->components->error("Domain [{$domain}] does not exist. Run `ddd:domain {$domain}` first.");

            return self::FAILURE;
        }

        $file = $domainPath.'/Application/Listeners/'.$listener.'.php';

        if (File::exists($file)) {
            $this->components->error("Listener [{$listener}] already exists at {$file}.");

            return self::FAILURE;
        }

        $namespace = rtrim(Config::string('ddd.base_namespace'), '\\')."\\{$domain}\\Application\\Listeners";
        $eventOption = $this->stringOption('event');

        if ($eventOption === null) {
            File::ensureDirectoryExists(dirname($file));
            File::put($file, $this->populateStub($this->stub('listener/listener-generic.stub'), [
                'namespace' => $namespace,
                'class' => $listener,
            ]));

            $this->components->info("Generated {$listener} listener at {$file}.");
            $this->components->warn('No --event given — register this listener manually once you type-hint an event.');

            return self::SUCCESS;
        }

        $event = $this->resolveEvent($eventOption);

        if ($event === null) {
            return self::FAILURE;
        }

        [$eventNamespace, $eventClass] = $event;

        File::ensureDirectoryExists(dirname($file));
        File::put($file, $this->populateStub($this->stub('listener/listener-with-event.stub'), [
            'namespace' => $namespace,
            'class' => $listener,
            'event_namespace' => $eventNamespace,
            'event_class' => $eventClass,
        ]));

        $this->components->info("Generated {$listener} listener at {$file}.");
        $this->components->info(
            "Register it manually, e.g. in {$domain}ServiceProvider::boot(): ".
            "Event::listen({$eventClass}::class, {$listener}::class);"
        );

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}|null Tuple of [event namespace, event class name].
     */
    private function resolveEvent(string $eventOption): ?array
    {
        $segments = explode('/', $eventOption);

        if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
            $this->components->error('Expected --event={domain}/{event}, e.g. --event=Contact/LeadWasCreated.');

            return null;
        }

        [$eventDomainInput, $eventNameInput] = $segments;

        $eventDomain = Str::studly($eventDomainInput);
        $eventName = Str::studly($eventNameInput);
        $eventDomainPath = rtrim(Config::string('ddd.base_path'), '/').'/'.$eventDomain;
        $eventFile = $eventDomainPath.'/Domain/Events/'.$eventName.'.php';

        if (! File::exists($eventFile)) {
            $this->components->error(
                "Event [{$eventName}] does not exist in domain [{$eventDomain}]. ".
                "Run `ddd:event {$eventDomain}/{$eventName}` first."
            );

            return null;
        }

        $eventNamespace = rtrim(Config::string('ddd.base_namespace'), '\\')."\\{$eventDomain}\\Domain\\Events";

        return [$eventNamespace, $eventName];
    }
}
