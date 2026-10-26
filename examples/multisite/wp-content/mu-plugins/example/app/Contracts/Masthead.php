<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\DefaultMasthead;
use Illuminate\Container\Attributes\Bind;

/**
 * What a site shows at the top of every page.
 *
 * `#[Bind]` names the shared default; a site plugin or theme replaces it by
 * binding this interface (see kleinweb-site-news).  Explicit bindings always
 * win over the attribute.
 */
#[Bind(DefaultMasthead::class)]
interface Masthead
{
    public function title(): string;

    public function tagline(): string;
}
