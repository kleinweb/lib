# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with
code in this repository.

## Project Overview

This is the **Kleinweb Standard Library** (`kleinweb/lib`) — a PHP library
providing shared functionality for WordPress-based applications at Klein
College (Temple University). It builds on top of
[Roots Acorn](https://roots.io/acorn/) to integrate Laravel-style service
providers, dependency injection, and Blade templating into WordPress.

## Development Commands

The project uses [just](https://just.systems/) for task automation. Run
`just --choose` to interactively select a task.

```bash
# Full lint + formatting check
just check

# Lint only (non-stylistic issues)
just lint

# Auto-fix all issues (treefmt + composer fix)
just fix

# Format only (safe changes)
just fmt

# Individual PHP tools via composer
composer phpstan          # Static analysis (level 8)
composer phpcs            # Code sniffer
composer phpcbf           # Code sniffer auto-fix
composer php-cs-fixer fix # Code style fixer
```

PHPStan output is cached in `.cache/phpstan/`. The baseline is in
`phpstan-baseline.neon`.

## Architecture

### Application & Service Providers

- `Application` extends Roots Acorn's Application with custom package manifest
  discovery that scans active WordPress plugins, themes, and the project root
  for composer packages.

- `Support\ServiceProvider` extends Laravel's ServiceProvider with `Hookable`
  trait, which auto-registers WordPress hooks via PHP 8 attributes when the
  service provider boots.

### Hook System (Attribute-Based)

WordPress actions and filters can be registered declaratively using PHP
attributes:

```php
use Kleinweb\Lib\Hooks\Attributes\Action;
use Kleinweb\Lib\Hooks\Attributes\Filter;

class MyServiceProvider extends ServiceProvider
{
    #[Action('init')]
    public function onInit(): void { ... }

    #[Filter('the_content', priority: 20)]
    public function filterContent(string $content): string { ... }
}
```

The `Hookable` trait in `Hooks\Traits\Hookable` uses reflection to collect
`#[Action]` and `#[Filter]` attributes and registers them automatically.

### Package System

`Package\Package` is a fluent configuration class (adapted from Spatie's
laravel-package-tools) for defining package assets, views, config files,
migrations, and commands. `PackageServiceProvider` provides the base class
for package registration.

### Database Models

`Database\Models\Model` is an abstract base for WordPress entity wrappers,
requiring `find()` and `newFromExisting()` methods. `User` is the concrete
implementation for WordPress users.

### Tenancy/Multisite

`Tenancy\Site` provides helpers for WordPress multisite: site URLs, hostnames,
original host detection (via `orig_host` site meta), and domain checks.

### Key Support Classes

- `Support\Plugin` — readonly DTO for plugin metadata (basename, path, URL)
- `Support\Environment` — environment detection helpers
- `Support\Url` — URL manipulation utilities
- `Log\SimpleHistoryHandler` — Monolog handler for Simple History plugin
