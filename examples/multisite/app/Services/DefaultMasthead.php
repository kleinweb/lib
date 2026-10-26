<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Masthead;

final class DefaultMasthead implements Masthead
{
    public function title(): string
    {
        return get_bloginfo('name');
    }

    public function tagline(): string
    {
        return get_bloginfo('description');
    }
}
