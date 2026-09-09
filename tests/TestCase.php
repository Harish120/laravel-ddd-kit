<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Tests;

use Harryes\LaravelDddKit\DddKitServiceProvider;
use Harryes\LaravelDddKit\Support\AutoloadDumper;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DddKitServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // base_path() resolves into vendor/orchestra/testbench-core during
        // tests, which does have a composer.json — never let the default
        // auto_dump_autoload behavior shell out to a real `composer` there.
        // Tests exercising that behavior rebind this themselves.
        $this->app->instance(AutoloadDumper::class, new class implements AutoloadDumper
        {
            public function dump(string $workingDirectory): void {}
        });

        // base_path('tests') resolves into vendor/orchestra/testbench-core
        // during tests too — never let the default generate_tests behavior
        // write companion tests there. Tests exercising that behavior
        // re-enable this and point ddd.tests_path at a temp directory.
        config()->set('ddd.generate_tests', false);
    }
}
