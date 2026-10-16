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

`Database\Models\Site` wraps `WP_Site` and its blogmeta. Each site is
identified by a `Tenancy\SiteKey` stored as the `site_key` blogmeta
(see `Tenancy\SiteMeta`); blog IDs differ between environments, keys do not.
`Tenancy\SiteRepository` looks sites up by key or ID.

`Tenancy\TenancyServiceProvider` is registered through `extra.acorn.providers`
and does nothing on single-site installs. On multisite, from an application
`booting` callback (after all configured providers register, before any
boot), it:

- registers the request site's providers via `Tenancy\SiteLoader`, least
  specific first: `app/Sites/{Key}/Providers`, the active site plugin
  `plugins/{key}/`, `providers` in `config/sites/{key}/app.php`, then the
  parent theme's and child theme's `composer.json` providers. Later bindings
  win. Also attaches `app/Sites/{Key}/View/Composers`, registers
  `app/Sites/{Key}/Console/Commands`, prepends `resources/views/sites/{key}`,
  and loads `routes/sites/{key}.php`.
- applies the site's config overlay (`Tenancy\Bootstrappers\ConfigBootstrapper`
  over `Tenancy\Config\ConfigOverlay`): `config/sites/{key}/*.php` loads as
  `sites.{key}.*` and overlays the config file of the same name, leaf by leaf.
  Bootstrappers are reverted and re-applied on every `switch_blog`
  (`Tenancy\SiteContext`); providers are not.

#### Class loading

All site, theme and site-plugin classes are autoloaded by Composer on every
site: `app/Sites/*` through the project's `App\` mapping, themes and site
plugins through `wikimedia/composer-merge-plugin` including their
`composer.json`. The library adds no autoloaders. Loading a class has no side
effects; site-specific behaviour comes only from which providers
`SiteLoader` registers. Theme and site-plugin `composer.json` files must not
use `autoload.files`, which would run on every site; `site:check` reports
it, along with PSR-4 prefixes Composer does not know.

#### `switch_to_blog()` contract

Multisite support mirrors Core: code is per process, data follows the switch.

| Follows every `switch_to_blog()` / `restore_current_blog()` | Fixed to the site WordPress loaded |
| --- | --- |
| WordPress tables, options, object cache (Core) | Providers and container bindings |
| Laravel cache, per blog (`Tenancy\Bootstrappers\CacheBootstrapper`) | Routes, view paths, view composers, commands |
| Config overlay for keyed sites (`ConfigBootstrapper`) | Objects that copied config when built (e.g. acorn-mail) |
| `cache.prefix` / `session.cookie` (Acorn) | Post types and taxonomies (Core never re-registers them) |

- `Tenancy\SiteContext::sync()` runs on `switch_blog` at priority 20, after
  Acorn's handler. Nothing it runs may throw: Core has already switched its
  globals, so failures (including in `SiteSwitched` listeners) are reported
  through the exception handler and skipped.
- It also runs for Core's internal switches (`get_home_url($id)`,
  `get_blog_option()`, `wp_initialize_site()`), so blog bootstrappers and
  `SiteSwitched` listeners must be cheap and idempotent.
- `Database\Models\Site::run()` restores the starting blog even if the
  callback throws or leaves extra switches unrestored. Console commands that
  loop over sites use `Console\Concerns\InteractsWithSites::forEachSite()`;
  work that needs another site's bindings uses `site:run`.
- Code that sets a config key a site overlay also sets, while switched, loses
  that change on the next switch.

Discovery results can be cached with `site:cache` (`Tenancy\Discovery\SiteManifest`).
Commands: `site:list`, `site:assign-keys`, `site:run`, `site:check`,
`site:cache`, `site:clear`, `site:demap-domains`, `make:site`.

### Key Support Classes

- `Support\Plugin` — readonly DTO for plugin metadata (basename, path, URL)
- `Support\Environment` — environment detection helpers
- `Support\Url` — URL manipulation utilities
- `Log\SimpleHistoryHandler` — Monolog handler for Simple History plugin
