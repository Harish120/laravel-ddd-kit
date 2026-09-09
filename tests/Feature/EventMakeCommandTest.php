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

it('generates a plain PHP domain event with no Illuminate dependency', function (): void {
    $this->artisan('ddd:event', ['name' => 'Contact/LeadWasCreated'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Domain/Events/LeadWasCreated.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain("namespace {$this->namespace}\\Contact\\Domain\\Events;")
        ->and(File::get($file))->toContain('final readonly class LeadWasCreated')
        ->and(File::get($file))->not->toContain('Illuminate');
});

it('generates an event that is instantiable and passes php -l', function (): void {
    $this->artisan('ddd:event', ['name' => 'Contact/LeadWasCreated'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Domain/Events/LeadWasCreated.php";

    exec('php -l '.escapeshellarg($file), result_code: $exitCode, output: $output);
    expect($exitCode)->toBe(0, implode("\n", $output));

    require $file;

    $class = "{$this->namespace}\\Contact\\Domain\\Events\\LeadWasCreated";
    $event = new $class('lead-1');

    expect($event->aggregateId)->toBe('lead-1')
        ->and($event->occurredAt)->toBeInstanceOf(DateTimeImmutable::class);
});

it('fails when the domain does not exist', function (): void {
    $this->artisan('ddd:event', ['name' => 'Ghost/LeadWasCreated'])
        ->assertFailed()
        ->expectsOutputToContain('does not exist');
});

it('fails when the event already exists', function (): void {
    $this->artisan('ddd:event', ['name' => 'Contact/LeadWasCreated'])->assertSuccessful();

    $this->artisan('ddd:event', ['name' => 'Contact/LeadWasCreated'])
        ->assertFailed()
        ->expectsOutputToContain('already exists');
});

it('fails when the name argument is missing a domain segment', function (): void {
    $this->artisan('ddd:event', ['name' => 'LeadWasCreated'])
        ->assertFailed()
        ->expectsOutputToContain('Expected {domain}/{name}');
});
