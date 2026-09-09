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
