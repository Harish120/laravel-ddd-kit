<?php

declare(strict_types=1);

use Harryes\LaravelDddKit\Support\Config;

it('reads a string config value', function (): void {
    config()->set('ddd.base_namespace', 'App\\Domains');

    expect(Config::string('ddd.base_namespace'))->toBe('App\\Domains');
});

it('throws when a required string config value is not a string', function (): void {
    config()->set('ddd.base_namespace', ['not', 'a', 'string']);

    Config::string('ddd.base_namespace');
})->throws(RuntimeException::class, 'Expected config [ddd.base_namespace] to be a string, got array.');

it('reads a nullable string config value that is set', function (): void {
    config()->set('ddd.stubs_path', '/tmp/stubs');

    expect(Config::nullableString('ddd.stubs_path'))->toBe('/tmp/stubs');
});

it('reads a nullable string config value that is null', function (): void {
    config()->set('ddd.stubs_path', null);

    expect(Config::nullableString('ddd.stubs_path'))->toBeNull();
});

it('throws when a nullable string config value is neither string nor null', function (): void {
    config()->set('ddd.stubs_path', 42);

    Config::nullableString('ddd.stubs_path');
})->throws(RuntimeException::class, 'Expected config [ddd.stubs_path] to be a string or null, got int.');
