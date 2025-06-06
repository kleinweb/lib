<?php

// SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

// phpcs:disable Squiz.NamingConventions.ValidFunctionName.NotCamelCaps

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Routing\UrlGenerator as UrlGeneratorContract;
use Kleinweb\Lib\Application;

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
    if ($abstract === null) {
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
 * Get / set the specified configuration value.
 *
 * If an array is passed as the key, we will assume you want to set an
 * array of values.
 *
 * @param array<string, mixed>|string|null $key
 *
 * @return ($key is null ? Illuminate\Config\Repository : ($key is string ? mixed : null))
 */
function config($key = null, mixed $default = null)
{
    if ($key === null) {
        return app('config');
    }

    if (is_array($key)) {
        return app('config')->set($key);
    }

    return app('config')->get($key, $default);
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
 * Get the database path.
 *
 * @throws BindingResolutionException
 */
function database_path(string $path = ''): string
{
    return app()->databasePath($path);
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
 * Get the path to the storage folder.
 *
 * @throws BindingResolutionException
 */
function storage_path(string $path = ''): string
{
    return app()->storagePath($path);
}

/**
 * Generate a url for the application.
 *
 * @return ($path is null ? UrlGeneratorContract : string)
 *
 * @throws BindingResolutionException
 */
function url(
    ?string $path = null,
    mixed $parameters = [],
    ?bool $secure = null,
): string|UrlGeneratorContract {
    if ($path === null) {
        return app(UrlGeneratorContract::class);
    }

    return app(UrlGeneratorContract::class)->to($path, $parameters, $secure);
}
