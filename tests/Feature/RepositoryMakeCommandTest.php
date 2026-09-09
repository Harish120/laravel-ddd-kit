<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempRoot = sys_get_temp_dir().'/ddd-kit-tests/'.uniqid('', true);
    $this->domainsPath = $this->tempRoot.'/app/Domains';
    $this->providersFile = $this->tempRoot.'/bootstrap/providers.php';
    $this->namespace = 'App\\Domains'.uniqid('Ns', false);

    config()->set('ddd.base_path', $this->domainsPath);
    config()->set('ddd.base_namespace', $this->namespace);
    config()->set('ddd.providers_file', $this->providersFile);

    File::ensureDirectoryExists(dirname($this->providersFile));
    File::put($this->providersFile, "<?php\n\nreturn [\n    //\n];\n");

    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();
});

afterEach(function (): void {
    File::deleteDirectory($this->tempRoot);
});

it('fails when the aggregate does not exist yet', function (): void {
    $this->artisan('ddd:repository', ['name' => 'Contact/Lead'])
        ->assertFailed()
        ->expectsOutputToContain('does not exist');
});

it('warns but still generates when the entity is not an aggregate', function (): void {
    $this->artisan('ddd:entity', ['name' => 'Contact/Lead'])->assertSuccessful();

    $this->artisan('ddd:repository', ['name' => 'Contact/Lead'])
        ->assertSuccessful()
        ->expectsOutputToContain('does not extend AggregateRoot');
});

it('generates the repository interface, Eloquent model, and Eloquent implementation', function (): void {
    $this->artisan('ddd:entity', ['name' => 'Contact/Lead', '--aggregate' => true])->assertSuccessful();
    $this->artisan('ddd:repository', ['name' => 'Contact/Lead'])->assertSuccessful();

    $interface = "{$this->domainsPath}/Contact/Domain/Repositories/LeadRepository.php";
    $model = "{$this->domainsPath}/Contact/Infrastructure/Persistence/Eloquent/LeadModel.php";
    $implementation = "{$this->domainsPath}/Contact/Infrastructure/Persistence/Repositories/EloquentLeadRepository.php";

    expect(File::exists($interface))->toBeTrue()
        ->and(File::get($interface))->toContain("namespace {$this->namespace}\\Contact\\Domain\\Repositories;")
        ->and(File::get($interface))->toContain('interface LeadRepository')
        ->and(File::get($interface))->not->toContain('Illuminate');

    expect(File::exists($model))->toBeTrue()
        ->and(File::get($model))->toContain('extends Model');

    expect(File::exists($implementation))->toBeTrue()
        ->and(File::get($implementation))->toContain('final class EloquentLeadRepository implements LeadRepository');

    foreach ([$interface, $model, $implementation] as $file) {
        exec('php -l '.escapeshellarg($file), result_code: $exitCode, output: $output);
        expect($exitCode)->toBe(0, implode("\n", $output));
    }
});

it('binds the interface to the implementation in the domain service provider', function (): void {
    $this->artisan('ddd:entity', ['name' => 'Contact/Lead', '--aggregate' => true])->assertSuccessful();
    $this->artisan('ddd:repository', ['name' => 'Contact/Lead'])->assertSuccessful();

    $providerFile = "{$this->domainsPath}/Contact/Infrastructure/Providers/ContactServiceProvider.php";
    $contents = File::get($providerFile);

    expect($contents)->toContain(
        "\$this->app->bind(\\{$this->namespace}\\Contact\\Domain\\Repositories\\LeadRepository::class, ".
        "\\{$this->namespace}\\Contact\\Infrastructure\\Persistence\\Repositories\\EloquentLeadRepository::class);"
    );

    exec('php -l '.escapeshellarg($providerFile), result_code: $exitCode, output: $output);
    expect($exitCode)->toBe(0, implode("\n", $output));
});

it('binds multiple repositories in the same domain without clobbering earlier bindings', function (): void {
    $this->artisan('ddd:entity', ['name' => 'Contact/Lead', '--aggregate' => true])->assertSuccessful();
    $this->artisan('ddd:entity', ['name' => 'Contact/Company', '--aggregate' => true])->assertSuccessful();
    $this->artisan('ddd:repository', ['name' => 'Contact/Lead'])->assertSuccessful();
    $this->artisan('ddd:repository', ['name' => 'Contact/Company'])->assertSuccessful();

    $contents = File::get("{$this->domainsPath}/Contact/Infrastructure/Providers/ContactServiceProvider.php");

    expect($contents)
        ->toContain('LeadRepository::class, \\'."{$this->namespace}".'\\Contact\\Infrastructure\\Persistence\\Repositories\\EloquentLeadRepository::class')
        ->toContain('CompanyRepository::class, \\'."{$this->namespace}".'\\Contact\\Infrastructure\\Persistence\\Repositories\\EloquentCompanyRepository::class');
});

it('fails when the domain does not exist', function (): void {
    $this->artisan('ddd:repository', ['name' => 'Ghost/Lead'])
        ->assertFailed()
        ->expectsOutputToContain('does not exist');
});

it('fails when the repository already exists', function (): void {
    $this->artisan('ddd:entity', ['name' => 'Contact/Lead', '--aggregate' => true])->assertSuccessful();
    $this->artisan('ddd:repository', ['name' => 'Contact/Lead'])->assertSuccessful();

    $this->artisan('ddd:repository', ['name' => 'Contact/Lead'])
        ->assertFailed()
        ->expectsOutputToContain('Already exists');
});

it('fails when the name argument is missing a domain segment', function (): void {
    $this->artisan('ddd:repository', ['name' => 'Lead'])
        ->assertFailed()
        ->expectsOutputToContain('Expected {domain}/{name}');
});
