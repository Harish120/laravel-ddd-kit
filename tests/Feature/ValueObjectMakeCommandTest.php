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

it('generates an immutable value object with a validation TODO block', function (): void {
    $this->artisan('ddd:value-object', ['name' => 'Contact/Email'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Domain/ValueObjects/Email.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain("namespace {$this->namespace}\\Contact\\Domain\\ValueObjects;")
        ->and(File::get($file))->toContain('final readonly class Email')
        ->and(File::get($file))->toContain('// TODO: validate $value here')
        ->and(File::get($file))->not->toContain('public function set');
});

it('generates a value object that is equal by value and passes php -l', function (): void {
    $this->artisan('ddd:value-object', ['name' => 'Contact/Email'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Domain/ValueObjects/Email.php";

    exec('php -l '.escapeshellarg($file), result_code: $exitCode, output: $output);
    expect($exitCode)->toBe(0, implode("\n", $output));

    require $file;

    $class = "{$this->namespace}\\Contact\\Domain\\ValueObjects\\Email";
    $a = new $class('a@example.com');
    $b = new $class('a@example.com');
    $c = new $class('b@example.com');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse()
        ->and((string) $a)->toBe('a@example.com');
});

it('fails when the domain does not exist', function (): void {
    $this->artisan('ddd:value-object', ['name' => 'Ghost/Email'])
        ->assertFailed()
        ->expectsOutputToContain('does not exist');
});

it('fails when the value object already exists', function (): void {
    $this->artisan('ddd:value-object', ['name' => 'Contact/Email'])->assertSuccessful();

    $this->artisan('ddd:value-object', ['name' => 'Contact/Email'])
        ->assertFailed()
        ->expectsOutputToContain('already exists');
});

it('fails when the name argument is missing a domain segment', function (): void {
    $this->artisan('ddd:value-object', ['name' => 'Email'])
        ->assertFailed()
        ->expectsOutputToContain('Expected {domain}/{name}');
});

it('generates a companion Pest test alongside the value object', function (): void {
    config()->set('ddd.generate_tests', true);
    config()->set('ddd.tests_path', $this->tempRoot.'/tests');

    $this->artisan('ddd:value-object', ['name' => 'Contact/Email'])->assertSuccessful();

    $file = "{$this->tempRoot}/tests/Unit/Domains/Contact/Domain/ValueObjects/EmailTest.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain("use {$this->namespace}\\Contact\\Domain\\ValueObjects\\Email;")
        ->and(File::get($file))->toContain('equals');

    exec('php -l '.escapeshellarg($file), result_code: $exitCode, output: $output);
    expect($exitCode)->toBe(0, implode("\n", $output));
});

it('does not generate a companion test when disabled', function (): void {
    config()->set('ddd.generate_tests', false);
    config()->set('ddd.tests_path', $this->tempRoot.'/tests');

    $this->artisan('ddd:value-object', ['name' => 'Contact/Email'])->assertSuccessful();

    expect(File::exists("{$this->tempRoot}/tests/Unit/Domains/Contact/Domain/ValueObjects/EmailTest.php"))->toBeFalse();
});
