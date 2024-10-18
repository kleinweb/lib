<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later


declare(strict_types=1);

// phpcs:disable Squiz.NamingConventions.ValidFunctionName.NotCamelCaps

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Routing\UrlGenerator as UrlGeneratorContract;
use Illuminate\Foundation\Application;

/**
 * Get the available container instance.
 *
 * @template TClass
 *
 * @param string|class-string<TClass>|null $abstract
 * @param array<string, mixed>             $parameters
 *
 * @return ($abstract is class-string<TClass> ? TClass : ($abstract is null ? Application : mixed))
 *
 * @throws BindingResolutionException
 */
function app(?string $abstract = null, array $parameters = [])
{
    if (is_null($abstract)) {
        return Container::getInstance();
    }

    return Container::getInstance()->make($abstract, $parameters);
}

/**
 * Get the path to the application folder.
 *
 * @throws BindingResolutionException
 */
function app_path(string $path = ''): string
{
    return app()->path($path);
}

/**
 * Get the path to the base of the install.
 *
 * @throws BindingResolutionException
 */
function base_path(string $path = ''): string
{
    return app()->basePath($path);
}

/**
 * Get the configuration path.
 *
 * @throws BindingResolutionException
 */
function config_path(string $path = ''): string
{
    return app()->configPath($path);
}

/**
 * Get the path to the public folder.
 *
 * @throws BindingResolutionException
 */
function public_path(string $path = ''): string
{
    return app()->publicPath($path);
}

/**
 * Get the path to the resources folder.
 *
 * @throws BindingResolutionException
 */
function resource_path(string $path = ''): string
{
    return app()->resourcePath($path);
}

/**
 * Generate a url for the application.
 *
 * @return ($path is null ? \Illuminate\Contracts\Routing\UrlGenerator : string)
 *
 * @throws BindingResolutionException
 */
function url(
    ?string $path = null,
    mixed $parameters = [],
    ?bool $secure = null,
): string|UrlGeneratorContract {
    if (is_null($path)) {
        return app(UrlGeneratorContract::class);
    }

    return app(UrlGeneratorContract::class)->to($path, $parameters, $secure);
}

/**
 * Get the database path.
 *
 * @throws BindingResolutionException
 */
function database_path(string $path = ''): string
{
    return app()->databasePath($path);
}
