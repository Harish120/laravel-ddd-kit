<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempRoot = sys_get_temp_dir().'/ddd-kit-tests/'.uniqid('', true);
    $this->domainsPath = $this->tempRoot.'/app/Domains';
    $this->providersFile = $this->tempRoot.'/bootstrap/providers.php';
    $this->namespace = 'App\\Domains'.uniqid('Ns', false);

    config()->set('ddd.base_path', $this->domainsPath);
    config()->set('ddd.base_namespace', $this->namespace);
    config()->set('ddd.providers_file', $this->providersFile);

    File::ensureDirectoryExists(dirname($this->providersFile));
    File::put($this->providersFile, "<?php\n\nreturn [\n    //\n];\n");
});

afterEach(function (): void {
    File::deleteDirectory($this->tempRoot);
});

it('passes with no violations when there are no domains yet', function (): void {
    $this->artisan('ddd:doctor')
        ->assertSuccessful()
        ->expectsOutputToContain('No domains found');
});

it('passes with no violations for a fully compliant, generated module', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();
    $this->artisan('ddd:entity', ['name' => 'Contact/Lead', '--aggregate' => true])->assertSuccessful();
    $this->artisan('ddd:usecase', ['name' => 'Contact/CreateLead'])->assertSuccessful();
    $this->artisan('ddd:repository', ['name' => 'Contact/Lead'])->assertSuccessful();

    $this->artisan('ddd:doctor')
        ->assertSuccessful()
        ->expectsOutputToContain('No violations found');
});

it('flags Illuminate imports inside the Domain layer', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Domain/Entities/BadEntity.php";
    File::ensureDirectoryExists(dirname($file));
    File::put($file, <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Domains\Contact\Domain\Entities;

        use Illuminate\Database\Eloquent\Model;

        final class BadEntity
        {
        }

        PHP);

    $this->artisan('ddd:doctor')
        ->assertFailed()
        ->expectsTable(
            ['File:Line', 'Rule violated'],
            [['Contact/Domain/Entities/BadEntity.php:7', 'Domain layer must not import Illuminate/Eloquent classes.']]
        );
});

it('flags entities with public setters and public properties', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Domain/Entities/BadEntity.php";
    File::ensureDirectoryExists(dirname($file));
    File::put($file, <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Domains\Contact\Domain\Entities;

        final class BadEntity
        {
            public string $status;

            public function setStatus(string $status): void
            {
                $this->status = $status;
            }
        }

        PHP);

    $this->artisan('ddd:doctor')
        ->assertFailed()
        ->expectsTable(
            ['File:Line', 'Rule violated'],
            [
                ['Contact/Domain/Entities/BadEntity.php:11', 'Entities must not expose public setters.'],
                ['Contact/Domain/Entities/BadEntity.php:9', 'Entities must not expose public properties.'],
            ]
        );
});

it('flags use cases that do not wrap writes in DB::transaction', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Application/UseCases/BadUseCase.php";
    File::ensureDirectoryExists(dirname($file));
    File::put($file, <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Domains\Contact\Application\UseCases;

        final class BadUseCase
        {
            public function handle(): void
            {
                // Writes directly with no transaction boundary.
            }
        }

        PHP);

    $this->artisan('ddd:doctor')
        ->assertFailed()
        ->expectsTable(
            ['File:Line', 'Rule violated'],
            [['Contact/Application/UseCases/BadUseCase.php', 'Use cases must wrap writes in DB::transaction().']]
        );
});

it('flags repositories that return Eloquent models instead of domain entities', function (): void {
    $this->artisan('ddd:domain', ['name' => 'Contact'])->assertSuccessful();

    $file = "{$this->domainsPath}/Contact/Infrastructure/Persistence/Repositories/BadRepository.php";
    File::ensureDirectoryExists(dirname($file));
    File::put($file, <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Domains\Contact\Infrastructure\Persistence\Repositories;

        use App\Domains\Contact\Infrastructure\Persistence\Eloquent\LeadModel;

        final class BadRepository
        {
            public function find(string $id): ?LeadModel
            {
                return LeadModel::find($id);
            }
        }

        PHP);

    $this->artisan('ddd:doctor')
        ->assertFailed()
        ->expectsTable(
            ['File:Line', 'Rule violated'],
            [[
                'Contact/Infrastructure/Persistence/Repositories/BadRepository.php:11',
                'Repositories must return domain entities, not Eloquent models.',
            ]]
        );
});
