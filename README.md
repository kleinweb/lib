# Kleinweb Standard Library

A PHP library providing shared functionality for WordPress-based applications
at Klein College (Temple University). Built on
[Roots Acorn](https://roots.io/acorn/) to bring Laravel-style service
providers, dependency injection, and Blade templating to WordPress.

## Requirements

- PHP 8.2+
- WordPress with [Roots Acorn](https://roots.io/acorn/) 5.x

## Installation

```bash
composer require kleinweb/lib
```

## Features

### Attribute-Based WordPress Hooks

Register actions and filters declaratively using PHP 8 attributes:

```php
use Kleinweb\Lib\Hooks\Attributes\Action;
use Kleinweb\Lib\Hooks\Attributes\Filter;
use Kleinweb\Lib\Support\ServiceProvider;

class MyServiceProvider extends ServiceProvider
{
    #[Action('init')]
    public function onInit(): void
    {
        // Runs on WordPress 'init' action
    }

    #[Filter('the_content', priority: 20)]
    public function filterContent(string $content): string
    {
        return $content . '<p>Appended content</p>';
    }
}
```

### Package Configuration

Fluent API for defining package assets, adapted from
[spatie/laravel-package-tools](https://github.com/spatie/laravel-package-tools):

```php
use Kleinweb\Lib\Package\Package;
use Kleinweb\Lib\Package\PackageServiceProvider;

class MyPackageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('my-package')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations('create_items_table')
            ->hasCommands(MyCommand::class);
    }
}
```

### Multisite Utilities

Helpers for WordPress multisite environments:

```php
use Kleinweb\Lib\Tenancy\Site;

Site::name();                    // Current site name
Site::url();                     // Site URL as League\Uri instance
Site::host();                    // Current hostname
Site::isPrimaryHost();           // Check if on main site
Site::originalHost();            // Get original domain from site meta
```

### Logging Integration

Monolog handler for
[Simple History](https://wordpress.org/plugins/simple-history/) plugin:

```php
use Kleinweb\Lib\Log\SimpleHistoryHandler;
use Monolog\Logger;

$logger = new Logger('my-channel');
$logger->pushHandler(new SimpleHistoryHandler());
```

## Development

This project uses [Nix](https://nixos.org/) for reproducible development
environments and [just](https://just.systems/) for task automation.

```bash
# Enter development shell (if using Nix)
nix develop

# Or use direnv
direnv allow

# Run all checks
just check

# Lint only
just lint

# Auto-fix issues
just fix

# Individual tools
composer phpstan    # Static analysis (level 8)
composer phpcs      # Code sniffer
composer phpcbf     # Auto-fix code sniffer issues
```

## License

GPL-3.0-or-later
