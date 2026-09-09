<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Tests;

use Harryes\LaravelDddKit\DddKitServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DddKitServiceProvider::class,
        ];
    }
}
