# SPDX-FileCopyrightText: 2024 Temple University <kleinweb@temple.edu>
#
# SPDX-License-Identifier: GPL-3.0-or-later

# === Settings ===

# Load required environment variables from the local `.env` file.
set dotenv-load
set dotenv-required

# === Global ===

prj-root := env('PRJ_ROOT')
prj-config-home := env('PRJ_CONFIG_HOME')
prj-data-home := env('PRJ_DATA_HOME')
prj-bin-home := env('PRJ_PATH', join(prj-root, '.bin'))

time-format-zettel := '%Y%m%dT%H%M%S%z'
now := datetime('%+')
nowfs := datetime(time-format-zettel)
today := datetime('%F')

# === Project ===

prj-slug := env('KLEINWEB_PROJECT_SLUG')
prj-title := env('KLEINWEB_PROJECT_TITLE')
prj-namespace := env('KLEINWEB_PROJECT_NAMESPACE')
prj-domain := env('KLEINWEB_PROJECT_DOMAIN')

prj-fqdn-prod := prj-domain
prj-fqdn-migration := prj-slug + '.kinsta.cloud'
prj-fqdn-staging := 'stg-' + prj-slug + '-staging.kinsta.cloud'
prj-fqdn-local := prj-slug + '.ddev.site'

prj-site-uuid := `cat $PRJ_CONFIG_HOME/site_id`

# === Organization ===

owner-name := 'Temple University'
owner-handle := 'kleinweb'
owner-email := 'kleinweb@temple.edu'
owner-attr := owner-name + ' <' + owner-email + '>'

owner-uuid := `cat $PRJ_CONFIG_HOME/org_id`

# === WordPress ===

wp-env := env('WP_ENV')
wp-home := env('WP_HOME')
wp-fqdn := trim_start_match(wp-home, 'https://')

wp-theme-slug := env('WP_DEFAULT_THEME', prj-slug)

# WordPress well-known paths
wp-content-dir := "wp-content"
wp-themes-dir := wp-content-dir / "themes"
wp-site-theme-dir := wp-themes-dir / wp-theme-slug
wp-mu-plugins-dir := wp-content-dir / "mu-plugins"
wp-plugins-dir := wp-content-dir / "plugins"
wp-cache-dir := wp-content-dir / 'cache'

# === Miscellaneous ===

open := if os_family() == "linux" { "xdg-open" } else { "open" }

# <https://datatracker.ietf.org/doc/html/rfc9562#name-nil-uuid>
uuid-nil := '00000000-0000-0000-0000-000000000000'
