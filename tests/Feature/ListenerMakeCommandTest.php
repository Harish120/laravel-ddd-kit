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
    File::ensureDirectoryExists($this->domainsPath.'/Billing/Domain');
});

afterEach(function (): void {
    File::deleteDirectory($this->tempRoot);
});

it('generates a generic listener stub when no --event is given', function (): void {
    $this->artisan('ddd:listener', ['name' => 'Contact/NotifySalesTeam'])
        ->assertSuccessful()
        ->expectsOutputToContain('No --event given');

    $file = "{$this->domainsPath}/Contact/Application/Listeners/NotifySalesTeam.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain("namespace {$this->namespace}\\Contact\\Application\\Listeners;")
        ->and(File::get($file))->toContain('public function handle(object $event): void');
});

it('generates a cross-domain listener wired to a specific event', function (): void {
    $this->artisan('ddd:event', ['name' => 'Contact/LeadWasCreated'])->assertSuccessful();

    $this->artisan('ddd:listener', [
        'name' => 'Billing/CreateInvoiceOnLeadWasCreated',
        '--event' => 'Contact/LeadWasCreated',
    ])->assertSuccessful();

    $file = "{$this->domainsPath}/Billing/Application/Listeners/CreateInvoiceOnLeadWasCreated.php";

    expect(File::exists($file))->toBeTrue()
        ->and(File::get($file))->toContain("namespace {$this->namespace}\\Billing\\Application\\Listeners;")
        ->and(File::get($file))->toContain("use {$this->namespace}\\Contact\\Domain\\Events\\LeadWasCreated;")
        ->and(File::get($file))->toContain('public function handle(LeadWasCreated $event): void');

    exec('php -l '.escapeshellarg($file), result_code: $exitCode, output: $output);
    expect($exitCode)->toBe(0, implode("\n", $output));
});

it('fails when the referenced event does not exist', function (): void {
    $this->artisan('ddd:listener', [
        'name' => 'Billing/CreateInvoiceOnLeadWasCreated',
        '--event' => 'Contact/LeadWasCreated',
    ])
        ->assertFailed()
        ->expectsOutputToContain('does not exist');
});

it('fails when the listener domain does not exist', function (): void {
    $this->artisan('ddd:listener', ['name' => 'Ghost/NotifySalesTeam'])
        ->assertFailed()
        ->expectsOutputToContain('does not exist');
});

it('fails when the listener already exists', function (): void {
    $this->artisan('ddd:listener', ['name' => 'Contact/NotifySalesTeam'])->assertSuccessful();

    $this->artisan('ddd:listener', ['name' => 'Contact/NotifySalesTeam'])
        ->assertFailed()
        ->expectsOutputToContain('already exists');
});

it('fails when the name argument is missing a domain segment', function (): void {
    $this->artisan('ddd:listener', ['name' => 'NotifySalesTeam'])
        ->assertFailed()
        ->expectsOutputToContain('Expected {domain}/{name}');
});

it('fails when the --event option is missing a domain segment', function (): void {
    $this->artisan('ddd:listener', [
        'name' => 'Billing/CreateInvoiceOnLeadWasCreated',
        '--event' => 'LeadWasCreated',
    ])
        ->assertFailed()
        ->expectsOutputToContain('Expected --event={domain}/{event}');
});
