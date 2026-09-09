# Laravel DDD Kit

Scaffold a real Domain-Driven Design architecture into your Laravel application —
Domain / Application / Infrastructure layers, aggregate roots, value objects, and
domain events — via `ddd:*` artisan commands.

## Requirements

- PHP ^8.3
- Laravel ^13.0

## Installation

```bash
composer require harryes/laravel-ddd-kit
```

The service provider is auto-discovered. Publish the config if you want to
customize the base namespace, base path, or stub overrides:

```bash
php artisan vendor:publish --tag=ddd-config
```

## Commands

### `ddd:domain`

Scaffolds a complete, self-contained DDD module: `Domain/`, `Application/`, and
`Infrastructure/` layers, a `routes.php`, and a `database/migrations/` directory.
See [`config/ddd.php`](config/ddd.php) for the exact folder shape.

```bash
php artisan ddd:domain Contact
```

```
   INFO  Domain [Contact] scaffolded at app/Domains/Contact.

   INFO  Registered App\Domains\Contact\Infrastructure\Providers\ContactServiceProvider in bootstrap/providers.php.
```

This creates:

```
app/Domains/Contact/
├── Domain/{Entities,ValueObjects,Events,Repositories,Exceptions}/
├── Application/{UseCases,DTOs,Queries}/
├── Infrastructure/
│   ├── Persistence/{Eloquent,Repositories}/
│   ├── Http/{Controllers,Requests}/
│   └── Providers/ContactServiceProvider.php
├── routes.php
└── database/migrations/
```

When `ddd.auto_register_providers` is enabled (the default), the generated
`ContactServiceProvider` is appended to `bootstrap/providers.php` automatically.
If that file doesn't exist, the command fails with a clear error instead of
registering nothing silently.

### `ddd:entity`

Generates an entity — or, with `--aggregate`, an aggregate root extending the
package's framework-agnostic `AggregateRoot` base class — inside an existing
domain's `Domain/Entities` directory. Requires the domain to already exist
(run `ddd:domain` first).

```bash
php artisan ddd:entity Contact/Lead --aggregate
```

```
   INFO  Generated Lead aggregate root at app/Domains/Contact/Domain/Entities/Lead.php.
```

```php
final class Lead extends AggregateRoot
{
    private function __construct(
        private readonly string $id,
        // TODO: add constructor-promoted properties for this aggregate's state.
    ) {
        // TODO: validate invariants here and throw a domain exception on violation.
    }

    public static function create(string $id /* , ...state */): self
    {
        $aggregate = new self($id);

        // TODO: record a domain event once you've generated one, e.g.:
        // $aggregate->record(new LeadWasCreated($id));

        return $aggregate;
    }

    public static function reconstitute(string $id /* , ...state */): self
    {
        return new self($id);
    }

    // ...
}
```

The constructor stays private — state is only ever set through `create()`,
`reconstitute()`, and named intent methods you add yourself. There are no
generated setters, and none should be added by hand.

### `ddd:value-object`

Generates an immutable, equality-by-value object inside a domain's
`Domain/ValueObjects` directory. Requires the domain to already exist.

```bash
php artisan ddd:value-object Contact/Email
```

```
   INFO  Generated Email value object at app/Domains/Contact/Domain/ValueObjects/Email.php.
```

```php
final readonly class Email
{
    public function __construct(
        private string $value,
    ) {
        // TODO: validate $value here and throw a domain exception on violation.
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

Fill in the constructor's `TODO` with the concept's validation rules (format,
range, allowed values, ...) and throw a domain exception on violation.

### `ddd:usecase`

Generates a use case and its matching DTO. The use case is the transaction
boundary — its body is always wrapped in `DB::transaction()`, and domain
events are dispatched only after that transaction commits, never from the
Domain layer itself.

```bash
php artisan ddd:usecase Contact/CreateLead
```

```
   INFO  Generated CreateLead use case at app/Domains/Contact/Application/UseCases/CreateLead.php.

   INFO  Generated CreateLeadData DTO at app/Domains/Contact/Application/DTOs/CreateLeadData.php.
```

```php
final class CreateLead
{
    public function __construct(
        // TODO: inject the domain repository interface(s) this use case needs.
    ) {}

    public function handle(CreateLeadData $data): void
    {
        DB::transaction(function () use ($data): void {
            // TODO: load or create the aggregate and invoke its intent method(s).
            // TODO: persist the aggregate via its repository interface.

            // Dispatch events recorded on the aggregate — never from the Domain layer.
            // foreach ($aggregate->pullDomainEvents() as $event) {
            //     Event::dispatch($event);
            // }
        });
    }
}
```

```php
final readonly class CreateLeadData
{
    public function __construct(
        // TODO: add the primitive/value-object properties this use case needs.
    ) {}
}
```

### `ddd:repository`

Generates a repository interface (`Domain/Repositories/`), a minimal Eloquent
model (`Infrastructure/Persistence/Eloquent/`), and the Eloquent
implementation (`Infrastructure/Persistence/Repositories/`) for an existing
aggregate — then binds the interface to the implementation in the domain's
`ServiceProvider` automatically. Requires the aggregate to already exist
(`ddd:entity {domain}/{name} --aggregate`); warns (without failing) if the
target entity doesn't extend `AggregateRoot`, since repositories should only
expose aggregate roots.

```bash
php artisan ddd:repository Contact/Lead
```

```
   INFO  Generated LeadRepository interface at app/Domains/Contact/Domain/Repositories/LeadRepository.php.

   INFO  Generated LeadModel at app/Domains/Contact/Infrastructure/Persistence/Eloquent/LeadModel.php.

   INFO  Generated EloquentLeadRepository at app/Domains/Contact/Infrastructure/Persistence/Repositories/EloquentLeadRepository.php.

   INFO  Bound App\Domains\Contact\Domain\Repositories\LeadRepository to App\Domains\Contact\Infrastructure\Persistence\Repositories\EloquentLeadRepository in ContactServiceProvider.
```

```php
interface LeadRepository
{
    public function find(string $id): ?Lead;

    public function save(Lead $lead): void;
}
```

```php
final class EloquentLeadRepository implements LeadRepository
{
    public function find(string $id): ?Lead
    {
        $model = LeadModel::find($id);

        if ($model === null) {
            return null;
        }

        // TODO: map $model's attributes onto Lead::reconstitute(...).
        return Lead::reconstitute($model->getKey());
    }

    public function save(Lead $lead): void
    {
        // TODO: map $lead's state onto a LeadModel and persist it.
    }
}
```

## Configuration

| Key | Default | Description |
| --- | --- | --- |
| `base_namespace` | `App\Domains` | Root namespace for generated domain code. |
| `base_path` | `app_path('Domains')` | Root directory for generated domain code. |
| `stubs_path` | `null` | Override with a published stub path to customize generated file templates. |
| `auto_register_providers` | `true` | Append generated `{Domain}ServiceProvider` classes to `bootstrap/providers.php`. |
| `providers_file` | `null` | Override the target `bootstrap/providers.php` path (non-standard app structures). |

## Testing

```bash
composer test
composer lint
composer analyse
```

## License

MIT.
