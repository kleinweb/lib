<?php

// SPDX-FileCopyrightText: (C) 2026 Temple University <kleinweb@temple.edu>
// SPDX-License-Identifier: GPL-3.0-or-later

// Stand-ins for the WordPress functions the library calls, backed by
// Kleinweb\Lib\Tests\Support\WordPress.  Behaviour follows Core where the
// library depends on it; see the notes beside each group.

declare(strict_types=1);

use Kleinweb\Lib\Tests\Support\WordPress;
use Kleinweb\Lib\Tests\Support\WpDie;

// phpcs:disable

class WP_Site
{
    public string $blog_id;
    public string $domain;
    public string $path;
    public string $site_id;

    public function __construct(int $id)
    {
        $site = WordPress::$sites[$id];
        $this->blog_id = (string) $id;
        $this->domain = $site['domain'];
        $this->path = $site['path'];
        $this->site_id = (string) $site['network'];
    }
}

// Hooks ------------------------------------------------------------------

function add_filter(string $hook, callable $callback, int $priority = 10, int $args = 1): bool
{
    WordPress::$hooks[$hook][$priority][] = $callback;
    ksort(WordPress::$hooks[$hook]);

    return true;
}

function add_action(string $hook, callable $callback, int $priority = 10, int $args = 1): bool
{
    return add_filter($hook, $callback, $priority, $args);
}

function has_filter(string $hook, mixed $callback = false): bool|int
{
    foreach (WordPress::$hooks[$hook] ?? [] as $priority => $callbacks) {
        if ($callback === false || in_array($callback, $callbacks, true)) {
            return ($callback === false) ? true : $priority;
        }
    }

    return false;
}

function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
    foreach (WordPress::$hooks[$hook] ?? [] as $callbacks) {
        foreach ($callbacks as $callback) {
            $value = $callback($value, ...$args);
        }
    }

    return $value;
}

function do_action(string $hook, mixed ...$args): void
{
    WordPress::$actionCounts[$hook] = (WordPress::$actionCounts[$hook] ?? 0) + 1;
    foreach (WordPress::$hooks[$hook] ?? [] as $callbacks) {
        foreach ($callbacks as $callback) {
            $callback(...$args);
        }
    }
}

function did_action(string $hook): int
{
    return WordPress::$actionCounts[$hook] ?? 0;
}

// Multisite: as in ms-blogs.php, `switch_blog` fires after the globals
// change, also when switching to the current blog.

function is_multisite(): bool
{
    return true;
}

function get_current_blog_id(): int
{
    return WordPress::$blogId;
}

function get_current_network_id(): int
{
    return 1;
}

function get_main_site_id(): int
{
    return 1;
}

function is_main_site(?int $id = null): bool
{
    return ($id ?? WordPress::$blogId) === 1;
}

function ms_is_switched(): bool
{
    return !empty($GLOBALS['_wp_switched_stack']);
}

function switch_to_blog(int $new): bool
{
    $previous = WordPress::$blogId;
    $GLOBALS['_wp_switched_stack'][] = $previous;
    WordPress::$blogId = $new;
    $GLOBALS['switched'] = true;
    do_action('switch_blog', $new, $previous, 'switch');

    return true;
}

function restore_current_blog(): bool
{
    if (empty($GLOBALS['_wp_switched_stack'])) {
        return false;
    }

    $previous = WordPress::$blogId;
    WordPress::$blogId = array_pop($GLOBALS['_wp_switched_stack']);
    $GLOBALS['switched'] = !empty($GLOBALS['_wp_switched_stack']);
    do_action('switch_blog', WordPress::$blogId, $previous, 'restore');

    return true;
}

function get_site(int|WP_Site|null $site = null): ?WP_Site
{
    $id = ($site instanceof WP_Site) ? (int) $site->blog_id : ($site ?? WordPress::$blogId);

    return isset(WordPress::$sites[$id]) ? new WP_Site($id) : null;
}

/**
 * @param array<string, mixed> $args
 *
 * @return list<WP_Site>
 */
function get_sites(array $args = []): array
{
    $ids = array_keys(WordPress::$sites);
    sort($ids);

    return array_map(static fn (int $id): WP_Site => new WP_Site($id), $ids);
}

function is_site_meta_supported(): bool
{
    return true;
}

function get_site_meta(int $id, string $key = '', bool $single = false): mixed
{
    return WordPress::$meta[$id][$key] ?? '';
}

function update_site_meta(int $id, string $key, mixed $value): bool
{
    WordPress::$meta[$id][$key] = $value;

    return true;
}

function get_home_url(?int $id = null, string $path = ''): string
{
    $site = WordPress::$sites[$id ?? WordPress::$blogId];

    return "https://{$site['domain']}{$site['path']}" . ltrim($path, '/');
}

function get_bloginfo(string $show = ''): string
{
    return 'Blog ' . WordPress::$blogId;
}

// Options: `option_{name}` filters on read, `pre_update_option_{name}` on write.

function get_option(string $name, mixed $default = false): mixed
{
    $value = WordPress::$options[WordPress::$blogId][$name] ?? $default;

    return apply_filters("option_{$name}", $value, $name);
}

function update_option(string $name, mixed $value): bool
{
    $old = WordPress::$options[WordPress::$blogId][$name] ?? false;
    WordPress::$options[WordPress::$blogId][$name] = apply_filters("pre_update_option_{$name}", $value, $old, $name);

    return true;
}

function get_blog_option(int $id, string $name, mixed $default = false): mixed
{
    switch_to_blog($id);
    $value = get_option($name, $default);
    restore_current_blog();

    return $value;
}

function get_site_option(string $name, mixed $default = false): mixed
{
    return WordPress::$networkOptions[$name] ?? $default;
}

function update_site_option(string $name, mixed $value): bool
{
    $old = WordPress::$networkOptions[$name] ?? false;
    WordPress::$networkOptions[$name] = apply_filters("pre_update_site_option_{$name}", $value, $old, $name, 1);

    return true;
}

// Themes: set WordPress::$template / WordPress::$stylesheet.

function get_template_directory(): string
{
    return WordPress::$template;
}

function get_stylesheet_directory(): string
{
    return WordPress::$stylesheet;
}

function get_theme_root(): string
{
    return \constant('WP_CONTENT_DIR') . '/themes';
}

// Misc.

function wp_die(string $message = '', string $title = '', array $args = []): never
{
    throw new WpDie($message);
}

function esc_html(string $text): string
{
    return $text;
}

function esc_html__(string $text, string $domain = 'default'): string
{
    return $text;
}

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function is_admin(): bool
{
    return false;
}

function is_wp_error(mixed $thing): bool
{
    return false;
}

// Post types and taxonomies, after post.php / taxonomy.php: unregistering a
// post type unlinks every taxonomy from it; built-ins cannot be unregistered.

function post_type_exists(string $name): bool
{
    return isset(WordPress::$postTypes[$name]);
}

function get_post_type_object(string $name): ?object
{
    return isset(WordPress::$postTypes[$name]) ? (object) WordPress::$postTypes[$name] : null;
}

/**
 * @param array<array-key, mixed> $args
 */
function register_post_type(string $name, array $args = []): object
{
    WordPress::$postTypes[$name] = ['builtin' => false, 'args' => $args];
    foreach ($args['taxonomies'] ?? [] as $taxonomy) {
        register_taxonomy_for_object_type($taxonomy, $name);
    }

    return (object) ['name' => $name];
}

function unregister_post_type(string $name): bool
{
    if (!isset(WordPress::$postTypes[$name]) || WordPress::$postTypes[$name]['builtin']) {
        return false;
    }

    foreach (get_object_taxonomies($name) as $taxonomy) {
        unregister_taxonomy_for_object_type($taxonomy, $name);
    }

    unset(WordPress::$postTypes[$name]);

    return true;
}

/**
 * @return list<string>
 */
function get_post_types(array $args = [], string $output = 'names'): array
{
    return [];
}

function taxonomy_exists(string $name): bool
{
    return isset(WordPress::$taxonomies[$name]);
}

function get_taxonomy(string $name): object|false
{
    return isset(WordPress::$taxonomies[$name]) ? (object) WordPress::$taxonomies[$name] : false;
}

/**
 * @return list<string>
 */
function get_taxonomies(array $args = [], string $output = 'names'): array
{
    return [];
}

/**
 * @param array<array-key, mixed> $args
 */
function register_taxonomy(string $name, array|string $types, array $args = []): void
{
    WordPress::$taxonomies[$name] = ['object_type' => array_values((array) $types), 'args' => $args];
}

function unregister_taxonomy(string $name): bool
{
    unset(WordPress::$taxonomies[$name]);

    return true;
}

/**
 * @return list<string>
 */
function get_object_taxonomies(string $type): array
{
    $names = [];
    foreach (WordPress::$taxonomies as $name => $taxonomy) {
        if (in_array($type, $taxonomy['object_type'], true)) {
            $names[] = $name;
        }
    }

    return $names;
}

function register_taxonomy_for_object_type(string $taxonomy, string $type): bool
{
    if (!isset(WordPress::$taxonomies[$taxonomy])) {
        return false;
    }

    WordPress::link($taxonomy, $type);

    return true;
}

function unregister_taxonomy_for_object_type(string $taxonomy, string $type): bool
{
    WordPress::$taxonomies[$taxonomy]['object_type'] = array_values(
        array_diff(WordPress::$taxonomies[$taxonomy]['object_type'], [$type]),
    );

    return true;
}
