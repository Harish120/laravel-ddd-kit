<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Support;

interface AutoloadDumper
{
    public function dump(string $workingDirectory): void;
}
