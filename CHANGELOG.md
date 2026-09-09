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
