<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ComposerAutoloadDumper implements AutoloadDumper
{
    /**
     * Refresh Composer's autoloader after scaffolding new classes. A
     * convenience for apps using an optimized/classmap autoloader — plain
     * PSR-4 autoloading already finds new files without this, so this
     * silently does nothing if there's no composer.json or no composer
     * binary on the PATH.
     */
    public function dump(string $workingDirectory): void
    {
        if (! File::exists($workingDirectory.'/composer.json')) {
            return;
        }

        $binary = (new ExecutableFinder)->find('composer');

        if ($binary === null) {
            return;
        }

        (new Process([$binary, 'dump-autoload', '-q'], $workingDirectory))->run();
    }
}
