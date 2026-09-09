# Project brief: harryes/laravel-ddd-kit (DDD scaffolding package for Laravel)

You are helping me build a Composer package that scaffolds a proper Domain-Driven
Design (DDD) architecture inside Laravel applications via artisan commands. Treat
this file as the persistent spec for the whole project — re-read it before each
work session and keep all generated code consistent with it. Ask before deviating
from anything below.

## 1. Who I am / what I already know

I'm a senior Laravel developer. I don't need MVC or Laravel basics explained. I
know the textbook DDD layering (Domain / Application / Infrastructure, entities,
repositories, use cases). What I need from you is **rigor on the parts most
Laravel DDD tutorials skip**, and a well-engineered package, not a toy.

## 2. Non-negotiable DDD principles the generated code must enforce

These are the things that separate "folders named Domain/Application/
Infrastructure with regular logic inside them" from actual DDD. Every stub and
generator you write must respect these:

1. **Entities have no public setters.** State changes happen through named
   methods that express business intent (`changeStatus()`, not `setStatus()`).
   Invalid states must be unrepresentable — validate in constructors/factories,
   throw domain-specific exceptions.
2. **Value Objects are mandatory for any concept with validation rules**
   (email, money, status enums, phone numbers, date ranges). Immutable,
   equality by value, no framework dependency.
3. **Aggregates have one root.** Child entities are never fetched or persisted
   independently of their aggregate root. Repositories only expose aggregate
   roots.
4. **Domain layer has zero Laravel/Eloquent imports.** No `Illuminate\*` in
   `Domain/`. Repositories in `Domain/` are interfaces only; Eloquent lives
   exclusively in `Infrastructure/`.
5. **Domain events are plain PHP objects**, not Laravel events, recorded on
   the aggregate via an `AggregateRoot` base class (`record()` /
   `pullDomainEvents()`), and only dispatched through Laravel's event
   dispatcher from the Application layer, after the use case's transaction —
   never from inside the Domain layer.
6. **Distinguish "new fact" from "rehydration."** Aggregates must have a
   `create()` factory (records an event) separate from a `reconstitute()`
   factory used by repositories when loading from persistence (records
   nothing).
7. **Use cases are the transaction boundary.** One use case = one
   `DB::transaction()` (when it writes) = one clear application-level
   operation. Use cases depend on domain interfaces and DTOs, never on
   Eloquent models or `Request` objects directly.
8. **Bounded contexts don't share domain models.** Each domain module must be
   generatable as a self-contained unit (its own `Domain/`, `Application/`,
   `Infrastructure/`, `ServiceProvider`, routes, migrations). Cross-context
   communication is via domain events only — never direct imports of another
   context's entities.
9. **CQRS-lite is supported, not forced.** Provide a `Queries/` slot in the
   Application layer for read-only queries that are allowed to bypass the
   domain layer and hit Eloquent/query builder directly for performance —
   but writes always go through aggregates.

If a generated stub violates any of these, that's a bug — fix the stub, don't
rationalize the violation.

## 3. Target folder shape (per domain module)

```
app/Domains/{Domain}/
├── Domain/
│   ├── Entities/          # aggregate roots + child entities
│   ├── ValueObjects/
│   ├── Events/
│   ├── Repositories/      # interfaces only
│   └── Exceptions/
├── Application/
│   ├── UseCases/
│   ├── DTOs/
│   └── Queries/
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Eloquent/      # Eloquent models
│   │   └── Repositories/  # interface implementations
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Requests/
│   └── Providers/         # {Domain}ServiceProvider.php
├── routes.php
└── database/migrations/
```

Confirm this shape with me before generating anything — I may want it
configurable (see §5, `config/ddd.php`).

## 4. Package identity

- Vendor: **harryes**
- Package name: **harryes/laravel-ddd-kit**
- Artisan command namespace: **`ddd:*`** (e.g. `ddd:domain`, `ddd:entity`,
  `ddd:doctor`) — kept short regardless of the package name
- Service provider class: `Harryes\LaravelDddKit\DddKitServiceProvider`
- Root PHP namespace for the package itself: `Harryes\LaravelDddKit\`
  (this is the package's own namespace, distinct from `base_namespace` in
  §6, which is the namespace used for *generated* domain code inside the
  consuming app — default `App\Domains`)
- Supported Laravel versions: **^13.0** (Laravel 13, released March 2026, is
  the current version — Laravel 12's active support already ended
  13 Aug 2026, so targeting it now would mean shipping against a
  no-longer-actively-supported major from day one)
- Supported PHP versions: **^8.3** (Laravel 13's minimum — up from 8.2 in
  Laravel 12)
- Testing framework: Pest (preferred) — every command needs feature tests
  that assert the generated files exist, have correct namespaces, and pass
  `php -l` (lint) at minimum. Where feasible, assert generated PHP is
  autoloadable and instantiable.
- Code style: Laravel Pint, default preset.
- Static analysis: PHPStan level 6+ on `src/`.

## 5. Required artisan commands (build in this order)

1. `ddd:domain {name}` — full module scaffold (highest priority, build and
   fully test before anything else).
2. `ddd:entity {domain}/{name} [--aggregate]` — entity or aggregate root.
3. `ddd:value-object {domain}/{name}` — VO stub with a validation TODO block.
4. `ddd:usecase {domain}/{name}` — use case + matching DTO, wired for
   `DB::transaction` + event dispatch.
5. `ddd:repository {domain}/{name}` — interface + Eloquent implementation,
   and auto-append the bind() call into the domain's ServiceProvider.
6. `ddd:event {domain}/{name}` + `ddd:listener {domain}/{name}` — domain
   event + cross-domain listener stub.
7. `ddd:query {domain}/{name}` — CQRS read-model query class.
8. `ddd:doctor` — static scan of `app/Domains/**` that flags: Eloquent
   imports inside `Domain/`, entities with public properties or `set*()`
   methods, use cases not wrapped in a transaction, repositories returning
   Eloquent models instead of domain entities. Output as a table with
   file:line and rule violated.

For each command, before writing code:
- Show me the exact stub content and the exact target file paths first.
- Only after I confirm, wire it into the `DddServiceProvider` and add tests.

## 6. Configuration (`config/ddd.php`)

Must support at minimum:
- `base_namespace` (default `App\Domains`)
- `base_path` (default `app_path('Domains')`)
- `stubs_path` override (so consumers can `vendor:publish` and customize
  stubs — mirror how Laravel's own `make:*` stub publishing works)
- `auto_register_providers` (bool) — when true, `ddd:domain` should append
  the new `{Domain}ServiceProvider::class` into `bootstrap/providers.php`.
  Since we only support Laravel 13, this is the only registration path —
  no `config/app.php` fallback needed. Still guard with a clear error if
  `bootstrap/providers.php` is missing (e.g. someone stripped it) rather
  than silently failing.

## 7. Differentiators to build after the core commands work

Don't start these until §5's commands are solid and tested:

- Interactive mode using `laravel/prompts`: `ddd:domain Contact
  --interactive` asks which building blocks to generate.
- Auto `composer dump-autoload` via `Symfony\Component\Process\Process`
  after scaffolding.
- Matching Pest test file generation alongside every stub (unit test for
  entities/VOs with no framework bootstrap; use-case tests using an
  in-memory fake repository).
- `ddd:doctor` as a genuinely useful lint tool, not just a demo — this is
  the feature most competing packages (laravel-modules, lucid architecture)
  don't have, and it's the ongoing-value piece worth investing in.

## 8. How I want you to work with me in this repo

- Work in small, reviewable increments — one command or one concern per
  round, not the whole package at once.
- Every time you generate a stub file, show it to me in full before wiring
  it into a command.
- When you're not sure whether something violates §2's principles, flag it
  and ask rather than guessing.
- Write PHPDoc on public methods in `src/`, but skip PHPDoc noise in
  generated *stub* templates — stubs should look like clean example code a
  developer would actually write by hand.
- Keep `composer.json` minimal — this package should have zero runtime
  dependencies beyond `illuminate/support` and `illuminate/console`
  (`laravel/prompts` only if/when we build interactive mode).
- After each command is working, update a `CHANGELOG.md` and the package
  `README.md` section for that command with a real usage example and
  sample output.

## 9. First task

Start with `composer.json`, the package skeleton (`src/DddServiceProvider.php`,
`config/ddd.php`), and the `ddd:domain` command end-to-end, including its
stubs and its Pest tests. Stop and show me the plan (file list + what each
file will contain) before writing code.
