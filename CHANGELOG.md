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
