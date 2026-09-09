<?php

declare(strict_types=1);

use Harryes\LaravelDddKit\Domain\AggregateRoot;
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

it('generates a plain entity with no public setters', function (): void {
    $this->artisan('ddd:entity', ['name' => 'Contact/Lead'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Domain/Entities/Lead.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain("namespace {$this->namespace}\\Contact\\Domain\\Entities;")
        ->and(File::get($file))->toContain('final class Lead')
        ->and(File::get($file))->not->toContain('public function set');

    require $file;

    $class = "{$this->namespace}\\Contact\\Domain\\Entities\\Lead";
    $entity = $class::create('lead-1');

    expect($entity)->toBeInstanceOf($class)
        ->and($entity->id())->toBe('lead-1')
        ->and($class::reconstitute('lead-2')->id())->toBe('lead-2');
});

it('generates an aggregate root extending AggregateRoot when --aggregate is passed', function (): void {
    $this->artisan('ddd:entity', ['name' => 'Contact/Lead', '--aggregate' => true])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Domain/Entities/Lead.php";

    expect(File::get($file))->toContain('extends AggregateRoot');

    require $file;

    $class = "{$this->namespace}\\Contact\\Domain\\Entities\\Lead";
    $aggregate = $class::create('lead-1');

    expect($aggregate)->toBeInstanceOf(AggregateRoot::class)
        ->and($aggregate->pullDomainEvents())->toBe([]);
});

it('generates a file that passes php -l', function (): void {
    $this->artisan('ddd:entity', ['name' => 'Contact/Lead'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Domain/Entities/Lead.php";

    exec('php -l '.escapeshellarg($file), result_code: $exitCode, output: $output);

    expect($exitCode)->toBe(0, implode("\n", $output));
});

it('fails when the domain does not exist', function (): void {
    $this->artisan('ddd:entity', ['name' => 'Ghost/Lead'])
        ->assertFailed()
        ->expectsOutputToContain('does not exist');
});

it('fails when the entity already exists', function (): void {
    $this->artisan('ddd:entity', ['name' => 'Contact/Lead'])->assertSuccessful();

    $this->artisan('ddd:entity', ['name' => 'Contact/Lead'])
        ->assertFailed()
        ->expectsOutputToContain('already exists');
});

it('fails when the name argument is missing a domain segment', function (): void {
    $this->artisan('ddd:entity', ['name' => 'Lead'])
        ->assertFailed()
        ->expectsOutputToContain('Expected {domain}/{name}');
});
