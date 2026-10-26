<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Bootstrappers;

use Illuminate\Contracts\Config\Repository;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Config\ConfigOverlay;
use Kleinweb\Lib\Tenancy\Contracts\SiteBootstrapper;
use Kleinweb\Lib\Tenancy\Discovery\SiteManifest;
use Kleinweb\Lib\Tenancy\SitePlugin;

use function is_array;
use function is_string;

/**
 * Applies a site's config overlay.
 *
 * Sources, later ones winning:
 *
 * 1. The site plugin's `config/*.php`, only while the plugin is active on
 *    the site.  `wp-content/plugins/news-hub/config/mail.php` overlays
 *    `mail`.
 * 2. Blogmeta mapped to config keys by `tenancy.meta_config`, for values
 *    editors change without a deploy, e.g.
 *    `['analytics_id' => 'services.analytics.id']`.
 *
 * `app.providers` is never overlaid: site providers come from the site
 * plugin's `composer.json`.
 */
final class ConfigBootstrapper implements SiteBootstrapper
{
    /** Overlay keys handled elsewhere. */
    private const array IGNORED = ['app.providers'];

    private readonly ConfigOverlay $overlay;

    public function __construct(
        private readonly Repository $config,
        private readonly SiteManifest $manifest,
    ) {
        $this->overlay = new ConfigOverlay($config);
    }

    public function bootstrap(Site $site): void
    {
        $this->overlay->revert();

        $key = $site->key();
        if ($key === null) {
            return;
        }

        // Runs after the switch, so this reads the target site's plugins.
        $files = SitePlugin::isActive($key) ? $this->manifest->config($key) : [];
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
            if ($value !== '' && $value !== false && $value !== null) {
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
