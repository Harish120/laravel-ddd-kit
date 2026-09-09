<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit;

use Harryes\LaravelDddKit\Console\Commands\DomainMakeCommand;
use Harryes\LaravelDddKit\Console\Commands\EntityMakeCommand;
use Illuminate\Support\ServiceProvider;

final class DddKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ddd.php', 'ddd');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/ddd.php' => config_path('ddd.php'),
        ], 'ddd-config');

        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs/ddd'),
        ], 'ddd-stubs');

        $this->commands([
            DomainMakeCommand::class,
            EntityMakeCommand::class,
        ]);
    }
}
