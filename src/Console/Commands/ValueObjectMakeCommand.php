<?php

declare(strict_types=1);

namespace Harryes\LaravelDddKit\Console\Commands;

use Harryes\LaravelDddKit\Console\Concerns\ManagesStubs;
use Harryes\LaravelDddKit\Console\Concerns\ReadsTypedInput;
use Harryes\LaravelDddKit\Support\Config;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class ValueObjectMakeCommand extends Command
{
    use ManagesStubs;
    use ReadsTypedInput;

    protected $signature = 'ddd:value-object {name : Domain and value object name, e.g. Contact/Email}';

    protected $description = "Generate an immutable value object inside a domain's Domain/ValueObjects directory";

    public function handle(): int
    {
        $segments = explode('/', $this->stringArgument('name'));

        if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
            $this->components->error('Expected {domain}/{name}, e.g. Contact/Email.');

            return self::FAILURE;
        }

        [$domainInput, $nameInput] = $segments;

        $domain = Str::studly($domainInput);
        $valueObject = Str::studly($nameInput);
        $domainPath = rtrim(Config::string('ddd.base_path'), '/').'/'.$domain;

        if (! File::isDirectory($domainPath.'/Domain')) {
            $this->components->error("Domain [{$domain}] does not exist. Run `ddd:domain {$domain}` first.");

            return self::FAILURE;
        }

        $file = $domainPath.'/Domain/ValueObjects/'.$valueObject.'.php';

        if (File::exists($file)) {
            $this->components->error("Value object [{$valueObject}] already exists at {$file}.");

            return self::FAILURE;
        }

        $namespace = rtrim(Config::string('ddd.base_namespace'), '\\')."\\{$domain}\\Domain\\ValueObjects";

        File::ensureDirectoryExists(dirname($file));
        File::put($file, $this->populateStub(
            $this->stub('value-object/value-object.stub'),
            ['namespace' => $namespace, 'class' => $valueObject]
        ));

        $this->components->info("Generated {$valueObject} value object at {$file}.");

        $this->writeCompanionTest(
            $this->testsBasePath()."/Unit/Domains/{$domain}/Domain/ValueObjects/{$valueObject}Test.php",
            'value-object/value-object-test.stub',
            ['namespace' => $namespace, 'class' => $valueObject]
        );

        return self::SUCCESS;
    }
}
