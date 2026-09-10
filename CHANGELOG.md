# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

- Package skeleton: `composer.json`, `DddKitServiceProvider`, `config/ddd.php`.
- `ddd:domain {name}` command — scaffolds the full Domain/Application/Infrastructure
  module shape, generates a domain `ServiceProvider` and `routes.php`, and
  auto-registers the provider in `bootstrap/providers.php`.
- `ddd:entity {domain}/{name} [--aggregate]` command — generates a plain entity
  or, with `--aggregate`, an aggregate root extending the new framework-agnostic
  `Harryes\LaravelDddKit\Domain\AggregateRoot` base class.
- `ddd:value-object {domain}/{name}` command — generates an immutable,
  equality-by-value object with a validation TODO block.
- `ddd:usecase {domain}/{name}` command — generates a use case and matching
  DTO, wired for `DB::transaction` and post-transaction event dispatch.
- `ddd:repository {domain}/{name}` command — generates a repository interface,
  a minimal Eloquent model, and an Eloquent repository implementation for an
  existing aggregate, and auto-binds them in the domain's service provider.
- `ddd:event {domain}/{name}` command — generates a plain PHP domain event.
- `ddd:listener {domain}/{name} [--event=domain/event]` command — generates a
  listener in `Application/Listeners/`, optionally wired to a specific
  (possibly cross-domain) event.
- `ddd:query {domain}/{name}` command — generates a CQRS-lite read query in
  `Application/Queries/` that may bypass the domain layer for reads.
- `ddd:doctor` command — static, regex-based scan of `app/Domains/**` for
  Illuminate imports in `Domain/`, public entity state, use cases missing a
  `DB::transaction()`, and repositories returning Eloquent models. Exits
  non-zero on violations, suitable as a CI gate.

This completes the full command set from the original build order (§5).

- `ddd:domain --interactive` — after scaffolding, interactively choose which
  building blocks (aggregate root, value object, use case, repository) to
  generate next, powered by `laravel/prompts`.
- `ddd:domain` now refreshes the Composer autoloader after scaffolding
  (`ddd.auto_dump_autoload`, default `true`), for apps using an
  optimized/classmap autoloader.
- `ddd:entity`, `ddd:value-object`, and `ddd:usecase` now each generate a
  matching Pest test under `tests/Unit/Domains/...` (`ddd.generate_tests`,
  default `true`; path configurable via `ddd.tests_path`). Entity/VO tests
  are plain unit tests with no framework bootstrap; use case tests are
  generated as a `->todo()` pending an in-memory fake repository, since the
  use case's real dependencies aren't known at generation time.

This completes all of §7's differentiators.

### Fixed

- `ddd:domain` no longer writes a stray `.gitkeep` into `Infrastructure/Providers/`
  — that directory always receives a real `{Domain}ServiceProvider.php` in the
  same run, so the placeholder was always immediately redundant. Found by
  dogfooding the package end-to-end in a real Laravel 13 app.

### Changed

- PHPStan raised from level 6 to `max` (level 9) on `src/`, enforced in CI.
  Every `config()`/`Command::argument()`/`Command::option()` read — all
  natively `mixed` — is now narrowed through two small helpers,
  `Harryes\LaravelDddKit\Support\Config` and the
  `Console\Concerns\ReadsTypedInput` trait, instead of unchecked casts.
