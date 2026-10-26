# Example multisite project

A minimal project showing `kleinweb/lib` tenancy on a WordPress multisite
network. It has the layout the Klein projects use: Core at the project root,
`wp-content/` beside it, a project mu-plugin that boots Acorn, and themes and
site plugins in the same repository.

It is example code, not a runnable install: Core, `wp-config.php` and
`vendor/` are left out.

```text
composer.json                    merge plugin includes themes and site plugins
app/                             shared application code (App\)
config/                          shared config (post-types, mail, tenancy)
resources/views/                 shared views
routes/web.php                   shared routes
wp-content/
  mu-plugins/app.php             boots Acorn; nothing site-specific
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
   wp acorn site:assign-keys --id=3 --key=events
   ```

3. Activate each site plugin on its own site, never network-wide:

   ```sh
   wp --url=news.example.test plugin activate kleinweb-site-news
   wp --url=events.example.test plugin activate kleinweb-site-events
   ```

4. Check the layout:

   ```sh
   wp acorn site:list
   wp acorn site:check
   ```

New site plugins come from `wp acorn make:site <key>`.

## What happens on a request

WordPress loads plugins and the theme as usual. The mu-plugin boots Acorn on
`after_setup_theme` with the shared providers. After every shared provider
has registered and before any boots, `TenancyServiceProvider`:

1. registers the requested site's providers, least specific first, so later
   bindings win:
   - the site plugin's (`kleinweb-site-{key}/composer.json`), only if it is
     active on this site;
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

- `content:report` (shared, `app/Console/Commands/ContentReport.php`) runs
  once and visits every site with `forEachSite()`. Inside its callback,
  WordPress data, the per-blog cache, the visited site's config overlay and
  its post types follow the switch. Container bindings do not: `Masthead` is
  the one for the site the command started on.
- `news:import-wire` exists only in news processes. To run it from
  elsewhere, start a process for that site:

  ```sh
  wp acorn site:run news:import-wire --sites=news
  ```

Plain `switch_to_blog()` in any code works as it does in Core, plus the
config overlay and cache follow it. Post types do not follow a plain switch;
use `forEachSite()` or `ContentTypeRegistrar::runFor()` when writing another
site's content.

## Post types

`config/post-types.php` replaces `roots/acorn-post-types`, which registers
one site's types for the whole process. The format is the same; remove that
package if a project has it (`site:check` flags it).

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
are cached; `site:check` warns about it.
