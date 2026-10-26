<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Contracts\Masthead;
use Roots\Acorn\View\Composer;

/**
 * Shared composer, attached by Acorn from app/View/Composers.
 *
 * A site replaces it by extending it and passing the subclass to
 * `SiteServiceProvider::composers()`; only one of the two runs.
 */
class SiteHeader extends Composer
{
    /** @var list<string> */
    protected static $views = ['partials.masthead'];

    public function __construct(protected readonly Masthead $masthead) {}

    /**
     * @return array<string, mixed>
     */
    protected function with(): array
    {
        return [
            'title' => $this->masthead->title(),
            'tagline' => $this->masthead->tagline(),
            'links' => $this->links(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function links(): array
    {
        return ['Home' => home_url('/')];
    }
}
