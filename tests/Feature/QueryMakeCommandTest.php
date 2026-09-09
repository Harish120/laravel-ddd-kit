<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempRoot = sys_get_temp_dir().'/ddd-kit-tests/'.uniqid('', true);
    $this->domainsPath = $this->tempRoot.'/app/Domains';
    $this->namespace = 'App\\Domains'.uniqid('Ns', false);

    config()->set('ddd.base_path', $this->domainsPath);
    config()->set('ddd.base_namespace', $this->namespace);

    File::ensureDirectoryExists($this->domainsPath.'/Contact/Domain');
});

afterEach(function (): void {
    File::deleteDirectory($this->tempRoot);
});

it('generates a CQRS-lite query that bypasses the domain layer', function (): void {
    $this->artisan('ddd:query', ['name' => 'Contact/ListActiveLeads'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Application/Queries/ListActiveLeads.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain("namespace {$this->namespace}\\Contact\\Application\\Queries;")
        ->and(File::get($file))->toContain('final readonly class ListActiveLeads')
        ->and(File::get($file))->toContain('use Illuminate\Support\Facades\DB;')
        ->and(File::get($file))->toContain('DB::table(');
});

it('generates a query that is instantiable and passes php -l', function (): void {
    $this->artisan('ddd:query', ['name' => 'Contact/ListActiveLeads'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Application/Queries/ListActiveLeads.php";

    exec('php -l '.escapeshellarg($file), result_code: $exitCode, output: $output);
    expect($exitCode)->toBe(0, implode("\n", $output));

    require $file;

    $class = "{$this->namespace}\\Contact\\Application\\Queries\\ListActiveLeads";

    expect(new $class)->toBeInstanceOf($class);
});

it('fails when the domain does not exist', function (): void {
    $this->artisan('ddd:query', ['name' => 'Ghost/ListActiveLeads'])
        ->assertFailed()
        ->expectsOutputToContain('does not exist');
});

it('fails when the query already exists', function (): void {
    $this->artisan('ddd:query', ['name' => 'Contact/ListActiveLeads'])->assertSuccessful();

    $this->artisan('ddd:query', ['name' => 'Contact/ListActiveLeads'])
        ->assertFailed()
        ->expectsOutputToContain('already exists');
});

it('fails when the name argument is missing a domain segment', function (): void {
    $this->artisan('ddd:query', ['name' => 'ListActiveLeads'])
        ->assertFailed()
        ->expectsOutputToContain('Expected {domain}/{name}');
});
