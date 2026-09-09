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
