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

When `ddd.auto_dump_autoload` is enabled (the default), the command also
refreshes Composer's autoloader afterward. This only matters if your app uses
an optimized/classmap autoloader — plain PSR-4 autoloading already finds new
files without it — and it silently does nothing if there's no `composer.json`
or no `composer` binary on the `PATH`.

#### Interactive mode

Pass `--interactive` to immediately generate a first building block after the
module scaffold, without leaving the terminal:

```bash
php artisan ddd:domain Contact --interactive
```

You'll be asked which building blocks to add (aggregate root, value object,
use case, repository — multiple selection allowed) and then for each one's
name, exactly as if you'd run `ddd:entity --aggregate`, `ddd:value-object`,
`ddd:usecase`, and `ddd:repository` yourself afterward. Selecting nothing
just leaves you with the plain module scaffold.

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

### `ddd:event` and `ddd:listener`

`ddd:event` generates a plain PHP domain event (`Domain/Events/`) — no
Laravel dependency, no framework event base class.

```bash
php artisan ddd:event Contact/LeadWasCreated
```

```php
final readonly class LeadWasCreated
{
    public function __construct(
        public string $aggregateId,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable(),
        // TODO: add any other data this event's listeners need.
    ) {}
}
```

`ddd:listener` generates a listener in the target domain's
`Application/Listeners/` directory. Pass `--event=domain/event` to wire it to
a specific event — the domain can differ from the listener's own domain,
since reacting to another context's event (never importing its entities
directly) is exactly how bounded contexts are meant to communicate:

```bash
php artisan ddd:listener Billing/CreateInvoiceOnLeadWasCreated --event=Contact/LeadWasCreated
```

```php
final class CreateInvoiceOnLeadWasCreated
{
    public function handle(LeadWasCreated $event): void
    {
        // TODO: react to the event. Keep this a thin orchestration step —
        // delegate real work to a use case if it needs a transaction.
    }
}
```

Omit `--event` to generate a generic stub with a `handle(object $event)`
placeholder instead. Either way, the command does not auto-register the
listener — wire it up yourself, e.g. in the owning domain's
`ServiceProvider::boot()`:

```php
Event::listen(LeadWasCreated::class, CreateInvoiceOnLeadWasCreated::class);
```

> **Note:** `Application/Listeners/` is not created by `ddd:domain` — it's
> added on demand the first time a domain gets a listener.

### `ddd:query`

Generates a CQRS-lite read query in a domain's `Application/Queries/`
directory. Queries are the one sanctioned shortcut around the domain layer —
they may hit Eloquent or the query builder directly for performance. Writes
never go through a query; they always go through a use case and its
aggregate.

```bash
php artisan ddd:query Contact/ListActiveLeads
```

```
   INFO  Generated ListActiveLeads query at app/Domains/Contact/Application/Queries/ListActiveLeads.php.
```

```php
final readonly class ListActiveLeads
{
    public function handle(): mixed
    {
        // TODO: query read models directly here — bypassing the domain layer
        // is fine for reads, but never write through this class. Writes
        // still go through a use case and its aggregate.
        return DB::table('table_name')->get();
    }
}
```

### `ddd:doctor`

Statically scans `app/Domains/**` for violations of the non-negotiables that
generated code alone can't guarantee — useful after hand-editing generated
stubs, or as a CI gate. It exits non-zero when it finds violations, so it's
safe to run in a pipeline.

```bash
php artisan ddd:doctor
```

```
+---------------------------------------------+-----------------------------------------------------------+
| File:Line                                   | Rule violated                                              |
+---------------------------------------------+-----------------------------------------------------------+
| Contact/Domain/Entities/BadEntity.php:7     | Domain layer must not import Illuminate/Eloquent classes. |
| Contact/Domain/Entities/BadEntity.php:13    | Entities must not expose public setters.                   |
| Contact/Domain/Entities/BadEntity.php:11    | Entities must not expose public properties.                |
| Contact/Application/UseCases/BadUseCase.php | Use cases must wrap writes in DB::transaction().           |
+---------------------------------------------+-----------------------------------------------------------+
```

It checks:

- No `use Illuminate\...` imports anywhere under a domain's `Domain/` layer.
- No public setters (`setX()`) or public properties on classes in
  `Domain/Entities/`.
- Every class in `Application/UseCases/` contains a `DB::transaction(` call.
- No repository under `Infrastructure/.../Repositories/` returns a type
  ending in `Model` from a public method.

> **Heuristic, not an AST parser.** These checks are line-based regex scans,
> not a full PHP parser — kept deliberately dependency-free (per the
> zero-runtime-dependency goal) at the cost of being fooled by unusual
> formatting. Treat findings as a strong signal, not a guarantee.

## Configuration

| Key | Default | Description |
| --- | --- | --- |
| `base_namespace` | `App\Domains` | Root namespace for generated domain code. |
| `base_path` | `app_path('Domains')` | Root directory for generated domain code. |
| `stubs_path` | `null` | Override with a published stub path to customize generated file templates. |
| `auto_register_providers` | `true` | Append generated `{Domain}ServiceProvider` classes to `bootstrap/providers.php`. |
| `providers_file` | `null` | Override the target `bootstrap/providers.php` path (non-standard app structures). |
| `auto_dump_autoload` | `true` | Refresh the Composer autoloader after `ddd:domain` scaffolds a module. |

## Testing

```bash
composer test
composer lint
composer analyse
```

## License

MIT.
