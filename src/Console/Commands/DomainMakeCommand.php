<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Commands;

use Harryes\LaravelDddKit\Console\Concerns\ManagesStubs;
use Harryes\LaravelDddKit\Support\AutoloadDumper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

final class DomainMakeCommand extends Command
{
    use ManagesStubs;

    protected $signature = 'ddd:domain
        {name : The domain name, e.g. Contact}
        {--interactive : Interactively choose building blocks to generate after scaffolding}';

    protected $description = 'Scaffold a full DDD module (Domain, Application, and Infrastructure layers) for a domain';

    /** @var list<string> */
    private const DIRECTORIES = [
        'Domain/Entities',
        'Domain/ValueObjects',
        'Domain/Events',
        'Domain/Repositories',
        'Domain/Exceptions',
        'Application/UseCases',
        'Application/DTOs',
        'Application/Queries',
        'Infrastructure/Persistence/Eloquent',
        'Infrastructure/Persistence/Repositories',
        'Infrastructure/Http/Controllers',
        'Infrastructure/Http/Requests',
        'Infrastructure/Providers',
        'database/migrations',
    ];

    public function handle(AutoloadDumper $autoloadDumper): int
    {
        $domain = Str::studly((string) $this->argument('name'));
        $path = rtrim((string) config('ddd.base_path'), '/').'/'.$domain;

        if (File::isDirectory($path)) {
            $this->components->error("Domain [{$domain}] already exists at {$path}.");

            return self::FAILURE;
        }

        foreach (self::DIRECTORIES as $directory) {
            File::ensureDirectoryExists($path.'/'.$directory);
            File::put($path.'/'.$directory.'/.gitkeep', '');
        }

        $namespace = rtrim((string) config('ddd.base_namespace'), '\\').'\\'.$domain;

        File::put(
            $path.'/Infrastructure/Providers/'.$domain.'ServiceProvider.php',
            $this->populateStub($this->stub('domain/service-provider.stub'), [
                'namespace' => $namespace,
                'domain' => $domain,
            ])
        );

        File::put(
            $path.'/routes.php',
            $this->populateStub($this->stub('domain/routes.stub'), [
                'route_prefix' => Str::kebab($domain),
            ])
        );

        $this->components->info("Domain [{$domain}] scaffolded at {$path}.");

        if ((bool) config('ddd.auto_register_providers')) {
            $this->registerServiceProvider($namespace, $domain);
        }

        if ((bool) $this->option('interactive')) {
            $this->runInteractiveMode($domain);
        }

        if ((bool) config('ddd.auto_dump_autoload')) {
            $autoloadDumper->dump(base_path());
        }

        return self::SUCCESS;
    }

    private function runInteractiveMode(string $domain): void
    {
        $blocks = multiselect(
            label: "Which building blocks would you like to generate for {$domain}?",
            options: [
                'entity' => 'Aggregate root (ddd:entity --aggregate)',
                'value-object' => 'Value object (ddd:value-object)',
                'usecase' => 'Use case (ddd:usecase)',
                'repository' => 'Repository (ddd:repository)',
            ],
            hint: 'Use the space bar to select options.',
        );

        if (in_array('entity', $blocks, true)) {
            $name = text(label: "Aggregate root name for {$domain}", placeholder: 'Lead', required: true);
            $this->call('ddd:entity', ['name' => "{$domain}/{$name}", '--aggregate' => true]);
        }

        if (in_array('value-object', $blocks, true)) {
            $name = text(label: "Value object name for {$domain}", placeholder: 'Email', required: true);
            $this->call('ddd:value-object', ['name' => "{$domain}/{$name}"]);
        }

        if (in_array('usecase', $blocks, true)) {
            $name = text(label: "Use case name for {$domain}", placeholder: 'CreateLead', required: true);
            $this->call('ddd:usecase', ['name' => "{$domain}/{$name}"]);
        }

        if (in_array('repository', $blocks, true)) {
            $name = text(label: 'Target aggregate name for the repository', placeholder: 'Lead', required: true);
            $this->call('ddd:repository', ['name' => "{$domain}/{$name}"]);
        }
    }

    private function registerServiceProvider(string $namespace, string $domain): void
    {
        $providersFile = config('ddd.providers_file') ?: base_path('bootstrap/providers.php');

        if (! File::exists($providersFile)) {
            $this->components->error(
                'bootstrap/providers.php not found — could not auto-register the service provider. '.
                "Register {$namespace}\\Infrastructure\\Providers\\{$domain}ServiceProvider manually, ".
                'or set ddd.auto_register_providers to false.'
            );

            return;
        }

        $providerClass = "{$namespace}\\Infrastructure\\Providers\\{$domain}ServiceProvider";
        $contents = File::get($providersFile);

        if (str_contains($contents, $providerClass.'::class')) {
            return;
        }

        $entry = "    {$providerClass}::class,\n";

        // Drop Laravel's default "// no providers yet" placeholder before inserting.
        $withoutPlaceholder = preg_replace('/^\s*\/\/\s*\n(?=\s*\];)/m', '', $contents, 1) ?? $contents;
        $updated = preg_replace('/\];(\s*)$/', $entry.'];$1', $withoutPlaceholder, 1) ?? $withoutPlaceholder;

        File::put($providersFile, $updated);

        $this->components->info("Registered {$providerClass} in bootstrap/providers.php.");
    }
}
