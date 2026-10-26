<?php

declare(strict_types=1);

namespace Kleinweb\Themes\Base;

/**
 * Layout choices a child theme may change by rebinding this class.
 */
class Layout
{
    public function bodyClass(): string
    {
        return 'layout-standard';
    }

    public function sidebar(): bool
    {
        return true;
    }
}
