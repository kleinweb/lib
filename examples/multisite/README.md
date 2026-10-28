# Example multisite project

A minimal project showing `kleinweb/lib` tenancy on a WordPress multisite
network, in the layout of `kleinweb/project-template`: Core at the project
root, `composer.json` and `wp-config.php` beside it, and the application in a
network-wide mu-plugin, `wp-content/mu-plugins/example/`, which is also
Acorn's base path (`ACORN_BASEPATH`). Themes and site plugins live in the
same repository.

It is example code, not a runnable install: Core, `vendor/` and most of
`wp-config.php` are left out.

```text
composer.json                    merge plugin includes themes and site plugins
wp-config.php                    excerpt: ACORN_BASEPATH and multisite
wp-content/
  mu-plugins/
    001-require-plugins.php      loads the application plugin
    example/                     the application: shared code, Acorn's home
      example.php                boots Acorn; nothing site-specific
      app/                       App\ (shared classes)
      config/                    shared config (post-types, mail, tenancy)
      resources/views/           shared views
      routes/web.php             shared routes
  plugins/
    kleinweb-site-news/          the "news" site's code and config
    kleinweb-site-events/        the "events" site's config only
  themes/
    kleinweb-base/               parent theme, Illuminate only
    kleinweb-news/               child theme for the news site
```

The network has three sites:

| Site key | Site plugin | Theme |
| --- | --- | --- |
| `main` | none | `kleinweb-base` |
| `news` | `kleinweb-site-news` | `kleinweb-news` (child of `kleinweb-base`) |
| `events` | `kleinweb-site-events` | `kleinweb-base` |

`main` adds no files at all and behaves like plain WordPress plus the shared
application.

## Setup

1. Install. The merge plugin adds each theme's and site plugin's `require`
   and `autoload` to the project, so one `vendor/` serves the monorepo:

   ```sh
   composer update
   ```

   Run `composer update`, not `install`, after adding a theme or site plugin
   or changing one's `composer.json`; the lock file records merged
   requirements.

2. Give each site its key (stored as the `site_key` blogmeta; keys are stable
   across environments, blog IDs are not):

   ```sh
   wp acorn site:assign-keys --dry-run
   wp acorn site:assign-keys
   wp acorn site:assign-keys --id=1 --key=main
   ```

   Keys are derived from each site's host (or its original host, if
   `site:demap-domains` recorded one) by the same rule as
   `site:demap-domains`. The main site, and any site whose derived key is
   taken or reserved, gets one with `--id`.

3. There is nothing to activate. A site plugin applies to the site whose key
   it is named for, as soon as it exists. `SitePluginLoader::register()`, in
   `wp-content/mu-plugins/001-require-plugins.php`, makes WordPress load
   `kleinweb-site-news` on the news site and nowhere else; it shows as active
   there and cannot be activated or deactivated anywhere. Activation, per
   site or network-wide, is refused, and one already stored fails
   `site:check`.

   Do not list a site plugin in `alleyinteractive/wp-plugin-loader`: that
   loader makes plugins active on every site.

4. Check the layout:

   ```sh
   wp acorn site:list
   wp acorn site:check
   ```

New site plugins come from `wp acorn make:site <key>`; add
`--url=<site url>` to give that site the key at the same time.

## What happens on a request

WordPress loads plugins, including the site's own site plugin, and the theme
as usual. The application mu-plugin boots Acorn on `after_setup_theme` with
the shared providers. After every shared provider has registered and before
any boots, `TenancyServiceProvider`:

1. registers the requested site's providers, least specific first, so later
   bindings win:
   - the site plugin's (`kleinweb-site-{key}/composer.json`), for this
     site's key only;
   - the parent theme's;
   - the child theme's;
2. overlays the site plugin's `config/*.php` on the shared config.

On a request for `news`:

| Thing | Result | Why |
| --- | --- | --- |
| `App\Contracts\Masthead` | `NewsMasthead` | site plugin `$bindings` beat the shared `#[Bind]` default |
| `Kleinweb\Themes\Base\Layout` | `WideLayout` | child theme beats parent theme |
| `partials.masthead` view | the site plugin's copy | `views()` prepends its directory |
| composer for `partials.masthead` | `NewsHeader` only | `composers()` binds it in place of `SiteHeader` |
| `config('mail.from.address')` | `news@klein.temple.edu` | `config/mail.php` overlay; `mail.default` unchanged |
| post types | `post`, `page`, `profile` (news icon), `story` | shared `config/post-types.php` plus overlay |
| `/desks.json`, `news:import-wire` | present | registered by `NewsServiceProvider` |
| `RebuildDeskIndex::dispatch()` | queued on `site-news` | `RunsOnSiteWorker` |

On a request for `events`, none of the news code is registered: `Masthead`
is `DefaultMasthead`, `Layout` is the parent's, the shared composer and view
run, and there is no `story` type, `/desks.json` route or `news:import-wire`
command. Post types are `post`, `page` and `event`, because the events overlay
sets `profile` to `null`. `config('services.ticketing.endpoint')` exists only
there.

On `main`, everything is shared.

Every class is still autoloadable on every site; that is harmless because
loading a class does nothing. Site plugins and themes must not use
`autoload.files`; `site:check` reports it.

## Console commands and `switch_to_blog()`

A console process is for one site, like a request: `wp --url=news.example.test
acorn …` has the news providers, and `wp acorn …` has the main site's.

- `content:report` (shared,
  `wp-content/mu-plugins/example/app/Console/Commands/ContentReport.php`) runs
  once and visits every site with `forEachSite()`. Inside its callback,
  WordPress data, the per-blog cache, the visited site's config overlay and
  its post types follow the switch. Container bindings do not: `Masthead` is
  the one for the site the command started on.
- `news:import-wire` exists only in news processes. To run it from
  elsewhere, start a process for that site:

  ```sh
  wp acorn site:run news:import-wire --sites=news
  ```

  `site:run` starts one process per site; `--parallel=4` runs four at a
  time.

Plain `switch_to_blog()` in any code works as it does in Core, plus the
config overlay and cache follow it. Post types do not follow a plain switch;
use `forEachSite()` or `ContentTypeRegistrar::runFor()` when writing another
site's content.

## Queues

One worker serves the whole network by default. Acorn records the
dispatching blog with each job and switches to it while the job runs, so
data, the per-blog cache and the site plugin's config follow. The worker
has only the providers of the site it was started for, so most jobs should
resolve nothing site-specific.

A job that needs its site's bindings implements `ShouldRunOnSiteWorker` and
uses `RunsOnSiteWorker`, like `kleinweb-site-news/src/Jobs/RebuildDeskIndex.php`.
It goes on its site's queue and runs on that site's worker:

```sh
wp --url=news.example.test acorn queue:work --queue=site-news
```

A worker for any other site fails such a job (`WrongSiteWorker`) instead of
running it with the wrong bindings. After every job, the worker is returned
to the blog it was on, even if the job left its own switches unrestored.

## Post types

The application's `config/post-types.php` takes the place of
`roots/acorn-post-types`, which registers one site's types for the whole
process; the format is the same. Do not install that package alongside.

In a site overlay:

- a new key adds a type (`story`, `event`);
- a partial entry changes only those arguments (`profile.menu_icon`);
- a list replaces the shared list (`topic.post_types`);
- `null` removes a shared type (`profile` on events).

## Caching

```sh
wp acorn site:cache   # provider declarations and site plugin config
wp acorn site:clear
```

`route:cache` is network-wide, so site plugin routes are skipped while routes
are cached; `site:check` reports it.
