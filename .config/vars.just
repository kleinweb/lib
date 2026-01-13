# SPDX-FileCopyrightText: (C) 2024-2026 Temple University <kleinweb@temple.edu>
# SPDX-License-Identifier: GPL-3.0-or-later

now := datetime('%+')
nowfs := datetime(time-format-zettel)
time-format-zettel := '%Y%m%dT%H%M%S%z'
today := datetime('%F')
open := if os_family() == "linux" { "xdg-open" } else { "open" }

# <https://datatracker.ietf.org/doc/html/rfc9562#name-nil-uuid>
uuid-nil := '00000000-0000-0000-0000-000000000000'

# === Project ===

prj-root := env('PRJ_ROOT')
prj-config-home := env('PRJ_CONFIG_HOME')
prj-data-home := env('PRJ_DATA_HOME')
prj-bin-home := env('PRJ_PATH', join(prj-root, '.bin'))

prj-slug := env('KLEINWEB_PROJECT_SLUG', 'lib')
prj-title := env('KLEINWEB_PROJECT_TITLE', 'Kleinweb Standard Library')

# === Organization ===

owner-name := 'Temple University'
owner-handle := 'kleinweb'
owner-email := 'kleinweb@temple.edu'
owner-attr := owner-name + ' <' + owner-email + '>'
owner-uuid := '9c9855c1-fa2f-4096-9a00-11b498dffc15'
