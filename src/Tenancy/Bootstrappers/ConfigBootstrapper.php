<?php

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Bootstrappers;

use Illuminate\Contracts\Config\Repository;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\ConfigOverlay;
use Kleinweb\Lib\Tenancy\Contracts\TenantBootstrapper;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SitePlugin;

/**
 * Apply a per-site configuration overlay.
 *
 * Configuration overlays are applied in the following order,
 * with later overlays taking precedence:
 *
 * 1. Configuration files in a site-specific plugin (`.../config/*.php`).
 *
 * 2. `blogmeta` mapped to config keys by `tenancy.meta_config`.
 */
final class ConfigBootstrapper implements TenantBootstrapper
{
    private const array IGNORED = ['app.providers'];

    private readonly ConfigOverlay $overlay;

    public function __construct(
        private readonly Repository $config,
        private readonly SiteManifest $manifest,
    ) {
        $this->overlay = new ConfigOverlay($this->config);
    }

    public function bootstrap(Site $site): void
    {
        $this->overlay->revert();

        $key = $site->key();
        if ($key === null) {
            return;
        }

        $files = SitePlugin::exists($key) ? $this->manifest->config($key) : [];
        $leaves = ConfigOverlay::leaves($files);

        foreach (self::IGNORED as $ignored) {
            unset($leaves[$ignored]);
        }

        $map = $this->config->get('tenancy.meta_config', []);

        foreach (is_array($map) ? $map : [] as $metaKey => $configKey) {
            if (!is_string($metaKey) || !is_string($configKey)) {
                continue;
            }

            $value = $site->meta($metaKey);
            if (($value !== '') && ($value !== false) && ($value !== null)) {
                $leaves[$configKey] = $value;
            }
        }

        $this->overlay->apply($leaves);
    }

    public function revert(): void
    {
        $this->overlay->revert();
    }
}
