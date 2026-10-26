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
and does nothing on single-site installs (beyond enabling Laravel's
`#[Bind]` attribute, which Acorn's config loader leaves off).

#### Where site code lives

The application (`app/`, `config/`, the project mu-plugin that boots Acorn)
holds only code shared by every site. Everything specific to one site lives
in its theme or in its site plugin, `wp-content/plugins/kleinweb-site-{key}/`
(`Tenancy\SitePlugin`):

- providers, declared in the plugin's or theme's `composer.json`
  (`extra.acorn.providers`); site plugin providers usually extend
  `Tenancy\SiteServiceProvider`, which adds `views()`, `composers()` and
  `webRoutes()` helpers;
- config overlays, in the site plugin's `config/*.php`: `config/mail.php`
  overlays `mail`, leaf by leaf (`Tenancy\Config\ConfigOverlay`: maps merge,
  lists replace, `null` removes).

WordPress loads plugins and themes as usual. From an application `booting`
callback (after all configured providers register, before any boot),
`Tenancy\SiteLoader` registers, least specific first so later bindings win:
the site plugin's providers (only if it is active on this site and named for
this site's key), then the active parent theme's, then the child theme's.
`Tenancy\Bootstrappers\ConfigBootstrapper` applies the site plugin's config
while it is active. One site therefore never loads another site's services or
config, even if a site plugin is activated on the wrong site.

`examples/multisite/` is a complete example project.

#### Post types and taxonomies

`ContentTypes\ContentTypesServiceProvider` replaces `roots/acorn-post-types`
with the same `config/post-types.php` format (Extended CPTs arguments). Site
plugins add, change or remove (`null`) types in their own
`config/post-types.php`. `ContentTypes\ContentTypeRegistrar::runFor()`, used
by `forEachSite()`, temporarily switches registrations to the visited site's
types (taxonomy links included) so its content can be written from the
console.

#### Class loading

All theme and site-plugin classes are autoloaded by Composer on every site,
through `wikimedia/composer-merge-plugin` in the project's `composer.json`
including `wp-content/themes/*/composer.json` and
`wp-content/plugins/kleinweb-site-*/composer.json`. The library adds no
autoloaders. Loading a class has no side effects; site-specific behaviour
comes only from which providers are registered. Theme and site-plugin
`composer.json` files must not use `autoload.files`, which would run on every
site; `site:check` reports it, along with PSR-4 prefixes Composer does not
know.

#### `switch_to_blog()` contract

Multisite support mirrors Core: code is per process, data follows the switch.

| Follows every `switch_to_blog()` / `restore_current_blog()` | Fixed to the site WordPress loaded |
| --- | --- |
| WordPress tables, options, object cache (Core) | Providers and container bindings |
| Laravel cache, per blog (`Tenancy\Bootstrappers\CacheBootstrapper`) | Routes, view paths, view composers, commands |
| Site plugin config overlay (`ConfigBootstrapper`) | Objects that copied config when built (e.g. acorn-mail) |
| `cache.prefix` / `session.cookie` (Acorn) | Post types and taxonomies, except inside `ContentTypeRegistrar::runFor()` |

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
