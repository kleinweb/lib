<?php

declare(strict_types=1);

namespace Kleinweb\Sites\News\View\Composers;

use App\View\Composers\SiteHeader;

/**
 * Replaces the shared SiteHeader on the news site (bound in its place by
 * SiteServiceProvider::composers()); the shared one does not also run.
 */
final class NewsHeader extends SiteHeader
{
    /**
     * @return array<string, string>
     */
    protected function links(): array
    {
        return parent::links() + [
            'Stories' => (string) get_post_type_archive_link('story'),
            'Desks' => home_url('/desks.json'),
        ];
    }
}
