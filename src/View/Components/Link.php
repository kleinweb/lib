<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace Kleinweb\Lib\View\Components;

use Illuminate\Support\Arr;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\View;
use Roots\Acorn\View\Component;
use Kleinweb\Lib\Support\Url;

final class Link extends Component
{
    /**
     * Whether the provided URL points to a different RFC3986 host.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.2.2
     */
    public bool $isUrlExternal;

    /**
     * Value for the anchor element's `rel` attribute.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/a#security_and_privacy
     */
    public string $defaultRel = '';

    /**
     * Value for the anchor element's `target` attribute.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/a#target
     * @see https://html.spec.whatwg.org/multipage/document-sequences.html#valid-navigable-target-name-or-keyword
     */
    public string $defaultTarget = '';

    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $href,
        public bool $newTab = false,
    ) {
        $this->isUrlExternal = ! Url::isCurrentHost($this->href);
        $this->newTab = $this->isUrlExternal;

        $defaultRel = [];

        if ($this->isUrlExternal) {
            $defaultRel[] = 'external';
        }

        if ($this->newTab) {
            $defaultRel[] = 'noopener';
            $defaultRel[] = 'noreferrer';
            $this->defaultTarget = '_blank';
        }

        $this->defaultRel = Arr::join($defaultRel, ' ');
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): ViewFactory|View
    {
        return \view('kleinlib::components.link');
    }
}
