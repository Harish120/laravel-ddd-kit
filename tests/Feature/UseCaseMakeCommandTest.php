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

it('generates a use case wired for DB::transaction and event dispatch', function (): void {
    $this->artisan('ddd:usecase', ['name' => 'Contact/CreateLead'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Application/UseCases/CreateLead.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain("namespace {$this->namespace}\\Contact\\Application\\UseCases;")
        ->and(File::get($file))->toContain("use {$this->namespace}\\Contact\\Application\\DTOs\\CreateLeadData;")
        ->and(File::get($file))->toContain('final class CreateLead')
        ->and(File::get($file))->toContain('DB::transaction(function ()')
        ->and(File::get($file))->toContain('Event::dispatch($event);')
        ->and(File::get($file))->toContain('public function handle(CreateLeadData $data): void');
});

it('generates a matching DTO for the use case', function (): void {
    $this->artisan('ddd:usecase', ['name' => 'Contact/CreateLead'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Application/DTOs/CreateLeadData.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain("namespace {$this->namespace}\\Contact\\Application\\DTOs;")
        ->and(File::get($file))->toContain('final readonly class CreateLeadData');
});

it('generates files that pass php -l', function (): void {
    $this->artisan('ddd:usecase', ['name' => 'Contact/CreateLead'])->assertSuccessful();

    foreach ([
        "{$this->domainsPath}/Contact/Application/UseCases/CreateLead.php",
        "{$this->domainsPath}/Contact/Application/DTOs/CreateLeadData.php",
    ] as $file) {
        exec('php -l '.escapeshellarg($file), result_code: $exitCode, output: $output);
        expect($exitCode)->toBe(0, implode("\n", $output));
    }
});

it('fails when the domain does not exist', function (): void {
    $this->artisan('ddd:usecase', ['name' => 'Ghost/CreateLead'])
        ->assertFailed()
        ->expectsOutputToContain('does not exist');
});

it('fails when the use case already exists', function (): void {
    $this->artisan('ddd:usecase', ['name' => 'Contact/CreateLead'])->assertSuccessful();

    $this->artisan('ddd:usecase', ['name' => 'Contact/CreateLead'])
        ->assertFailed()
        ->expectsOutputToContain('Already exists');
});

it('fails when the name argument is missing a domain segment', function (): void {
    $this->artisan('ddd:usecase', ['name' => 'CreateLead'])
        ->assertFailed()
        ->expectsOutputToContain('Expected {domain}/{name}');
});

it('generates a companion Pest test alongside the use case', function (): void {
    config()->set('ddd.generate_tests', true);
    config()->set('ddd.tests_path', $this->tempRoot.'/tests');

    $this->artisan('ddd:usecase', ['name' => 'Contact/CreateLead'])->assertSuccessful();

    $file = "{$this->tempRoot}/tests/Unit/Domains/Contact/Application/UseCases/CreateLeadTest.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain("use {$this->namespace}\\Contact\\Application\\UseCases\\CreateLead;")
        ->and(File::get($file))->toContain("use {$this->namespace}\\Contact\\Application\\DTOs\\CreateLeadData;")
        ->and(File::get($file))->toContain('->todo();');

    exec('php -l '.escapeshellarg($file), result_code: $exitCode, output: $output);
    expect($exitCode)->toBe(0, implode("\n", $output));
});

it('does not generate a companion test when disabled', function (): void {
    config()->set('ddd.generate_tests', false);
    config()->set('ddd.tests_path', $this->tempRoot.'/tests');

    $this->artisan('ddd:usecase', ['name' => 'Contact/CreateLead'])->assertSuccessful();

    expect(File::exists("{$this->tempRoot}/tests/Unit/Domains/Contact/Application/UseCases/CreateLeadTest.php"))->toBeFalse();
});
