<?php

declare(strict_types=1);

/*
 * One way a site can render templates; each site (theme) chooses its own.
 * This theme asks the existing container for the view factory, so it works
 * with any Illuminate container that has one.
 */

use Illuminate\Container\Container;

echo Container::getInstance()->make('view')->make('base::index')->render();
