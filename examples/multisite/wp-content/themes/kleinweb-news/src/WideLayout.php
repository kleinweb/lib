<?php

declare(strict_types=1);

namespace Kleinweb\Themes\News;

use Kleinweb\Themes\Base\Layout;

final class WideLayout extends Layout
{
    public function bodyClass(): string
    {
        return 'layout-wide';
    }

    public function sidebar(): bool
    {
        return false;
    }
}
