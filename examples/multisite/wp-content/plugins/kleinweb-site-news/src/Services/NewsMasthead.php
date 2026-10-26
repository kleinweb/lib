<?php

declare(strict_types=1);

namespace Kleinweb\Sites\News\Services;

use App\Contracts\Masthead;

final class NewsMasthead implements Masthead
{
    public function title(): string
    {
        return get_bloginfo('name');
    }

    public function tagline(): string
    {
        return (string) wp_date('l, F j, Y');
    }
}
