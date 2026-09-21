now := datetime('%+')
nowfs := datetime(time-format-denote)
time-format-denote := '%Y%m%dT%H%M%S%z'
today := datetime('%F')
open := if os_family() == "linux" { "xdg-open" } else { "open" }

# <https://datatracker.ietf.org/doc/html/rfc9562#name-nil-uuid>
uuid-nil := '00000000-0000-0000-0000-000000000000'

# === Project ===

prj-root := env('PRJ_ROOT')
prj-config-home := env('PRJ_CONFIG_HOME', prj-root / ".config")
prj-cache-home := env('PRJ_CACHE_HOME', prj-root / ".cache")
prj-data-home := env('PRJ_DATA_HOME', prj-root / ".data")
prj-bin-home := env('PRJ_PATH', prj-root / ".bin")

prj-slug := env('KLEINWEB_PROJECT_SLUG')
prj-title := env('KLEINWEB_PROJECT_TITLE')
prj-namespace := env('KLEINWEB_PROJECT_NAMESPACE')

# === Organization ===

owner-name := 'Temple University'
owner-handle := 'kleinweb'
owner-email := 'kleinweb@temple.edu'
owner-domain := 'klein.temple.edu'
owner-attr := owner-name + ' <' + owner-email + '>'
owner-uuid := '9c9855c1-fa2f-4096-9a00-11b498dffc15'
