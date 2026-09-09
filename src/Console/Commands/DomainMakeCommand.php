<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Commands;

use Harryes\LaravelDddKit\Console\Concerns\ManagesStubs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class DomainMakeCommand extends Command
{
    use ManagesStubs;

    protected $signature = 'ddd:domain {name : The domain name, e.g. Contact}';

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

    public function handle(): int
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

        return self::SUCCESS;
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
