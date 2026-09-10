<?php

declare(strict_types=1);

use Harryes\LaravelDddKit\Support\AutoloadDumper;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempRoot = sys_get_temp_dir().'/ddd-kit-tests/'.uniqid('', true);
    $this->domainsPath = $this->tempRoot.'/app/Domains';
    $this->providersFile = $this->tempRoot.'/bootstrap/providers.php';

    config()->set('ddd.base_path', $this->domainsPath);
    config()->set('ddd.base_namespace', 'App\\Domains');
    config()->set('ddd.providers_file', $this->providersFile);

    File::ensureDirectoryExists(dirname($this->providersFile));
    File::put($this->providersFile, "<?php\n\nreturn [\n    //\n];\n");
});

afterEach(function (): void {
    File::deleteDirectory($this->tempRoot);
});

it('scaffolds the full ddd folder shape for a new domain', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    $expectedDirectories = [
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

    foreach ($expectedDirectories as $directory) {
        expect(File::isDirectory("{$this->domainsPath}/Contact/{$directory}"))->toBeTrue();
    }

    // Infrastructure/Providers always gets a real ServiceProvider file in
    // the same run, so it should never carry a redundant .gitkeep.
    expect(File::exists("{$this->domainsPath}/Contact/Infrastructure/Providers/.gitkeep"))->toBeFalse();
});

it('generates a service provider with the correct namespace that lints clean', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Infrastructure/Providers/ContactServiceProvider.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain('namespace App\\Domains\\Contact\\Infrastructure\\Providers;')
        ->and(File::get($file))->toContain('final class ContactServiceProvider extends ServiceProvider');

    exec('php -l '.escapeshellarg($file), result_code: $exitCode, output: $output);

    expect($exitCode)->toBe(0, implode("\n", $output));
});

it('generates a routes file scoped to the domain', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/routes.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain("Route::prefix('contact')");
});

it('registers the domain service provider in bootstrap/providers.php', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    expect(File::get($this->providersFile))
        ->toContain('App\\Domains\\Contact\\Infrastructure\\Providers\\ContactServiceProvider::class');
});

it('does not duplicate the provider entry when it is already registered', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    File::deleteDirectory("{$this->domainsPath}/Contact");

    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    $occurrences = substr_count(
        File::get($this->providersFile),
        'App\\Domains\\Contact\\Infrastructure\\Providers\\ContactServiceProvider::class'
    );

    expect($occurrences)->toBe(1);
});

it('fails with a clear error when bootstrap/providers.php is missing', function (): void {
    File::delete($this->providersFile);

    $this->artisan('ddd:domain', ['name' => 'Contact'])
        ->assertSuccessful()
        ->expectsOutputToContain('bootstrap/providers.php not found');
});

it('refuses to overwrite an existing domain', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    $this->artisan('ddd:domain', ['name' => 'Contact'])
        ->assertFailed()
        ->expectsOutputToContain('already exists');
});

it('does nothing extra with --interactive when no blocks are selected', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact', '--interactive' => true])
        ->expectsChoice(
            'Which building blocks would you like to generate for Contact?',
            [],
            [
                'entity' => 'Aggregate root (ddd:entity --aggregate)',
                'value-object' => 'Value object (ddd:value-object)',
                'usecase' => 'Use case (ddd:usecase)',
                'repository' => 'Repository (ddd:repository)',
            ]
        )
        ->assertSuccessful();

    expect(File::isDirectory("{$this->domainsPath}/Contact/Domain/Entities"))->toBeTrue()
        ->and(File::exists("{$this->domainsPath}/Contact/Domain/Entities/Lead.php"))->toBeFalse();
});

it('generates an aggregate root interactively when selected', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact', '--interactive' => true])
        ->expectsChoice(
            'Which building blocks would you like to generate for Contact?',
            ['entity'],
            [
                'entity' => 'Aggregate root (ddd:entity --aggregate)',
                'value-object' => 'Value object (ddd:value-object)',
                'usecase' => 'Use case (ddd:usecase)',
                'repository' => 'Repository (ddd:repository)',
            ]
        )
        ->expectsQuestion('Aggregate root name for Contact', 'Lead')
        ->assertSuccessful();

    expect(File::exists("{$this->domainsPath}/Contact/Domain/Entities/Lead.php"))->toBeTrue();
});

it('dumps the composer autoloader after scaffolding when enabled', function (): void {
    $dumper = new class implements AutoloadDumper
    {
        /** @var list<string> */
        public array $calls = [];

        public function dump(string $workingDirectory): void
        {
            $this->calls[] = $workingDirectory;
        }
    };

    $this->app->instance(AutoloadDumper::class, $dumper);

    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    expect($dumper->calls)->toHaveCount(1);
});

it('does not dump the composer autoloader when disabled', function (): void {
    config()->set('ddd.auto_dump_autoload', false);

    $dumper = new class implements AutoloadDumper
    {
        /** @var list<string> */
        public array $calls = [];

        public function dump(string $workingDirectory): void
        {
            $this->calls[] = $workingDirectory;
        }
    };

    $this->app->instance(AutoloadDumper::class, $dumper);

    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    expect($dumper->calls)->toBe([]);
});
