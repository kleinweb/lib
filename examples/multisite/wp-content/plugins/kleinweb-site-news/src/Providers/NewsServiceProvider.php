<?php

declare(strict_types=1);

namespace Kleinweb\Sites\News\Providers;

use App\Contracts\Masthead;
use Kleinweb\Lib\Hooks\Attributes\Filter;
use Kleinweb\Lib\Tenancy\SiteServiceProvider;
use Kleinweb\Sites\News\Console\Commands\ImportWire;
use Kleinweb\Sites\News\Services\NewsMasthead;
use Kleinweb\Sites\News\View\Composers\NewsHeader;

/**
 * Registered only on requests (and console processes) for the "news" site,
 * and only while this plugin is active there.
 *
 * On any other site none of this exists: Masthead resolves to the shared
 * default, the shared composer runs, these routes and the command are absent.
 */
final class NewsServiceProvider extends SiteServiceProvider
{
    /**
     * Replace shared bindings on this site.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        Masthead::class => NewsMasthead::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->views(__DIR__ . '/../../resources/views');
        $this->composers([NewsHeader::class]);
        $this->webRoutes(__DIR__ . '/../../routes/web.php');
        $this->commands([ImportWire::class]);
    }

    /**
     * Hooks declared here are added only on the news site.
     */
    #[Filter('excerpt_length')]
    public function excerptLength(): int
    {
        return 30;
    }
}
