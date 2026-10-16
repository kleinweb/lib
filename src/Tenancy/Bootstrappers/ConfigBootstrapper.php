<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\Tenancy\Bootstrappers;

use Illuminate\Contracts\Config\Repository;
use Kleinweb\Lib\Database\Models\Site;
use Kleinweb\Lib\Tenancy\Config\ConfigOverlay;
use Kleinweb\Lib\Tenancy\Contracts\SiteBootstrapper;

use function is_array;
use function is_string;

/**
 * Applies a site's config overlay.
 *
 * Sources, later ones winning:
 *
 * 1. `config/sites/{key}/*.php`.  Laravel loads config subdirectories as
 *    nested keys, so `config/sites/news-hub/mail.php` is the config value
 *    `sites.news-hub.mail` and overlays `mail`.  These files are included in
 *    `config:cache`.
 * 2. Blogmeta mapped to config keys by `tenancy.meta_config`, for values
 *    editors change without a deploy, e.g.
 *    `['analytics_id' => 'services.analytics.id']`.
 *
 * `app.providers` is never overlaid; site providers are registered by
 * {@see \Kleinweb\Lib\Tenancy\SiteLoader}.
 */
final class ConfigBootstrapper implements SiteBootstrapper
{
    /** Overlay keys handled elsewhere. */
    private const array IGNORED = ['app.providers'];

    private readonly ConfigOverlay $overlay;

    public function __construct(private readonly Repository $config)
    {
        $this->overlay = new ConfigOverlay($config);
    }

    public function bootstrap(Site $site): void
    {
        $this->overlay->revert();

        $key = $site->key();
        if ($key === null) {
            return;
        }

        $files = $this->config->get("sites.{$key->value}", []);
        $leaves = is_array($files) ? ConfigOverlay::leaves($files) : [];

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
