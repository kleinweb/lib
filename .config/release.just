# SPDX-FileCopyrightText: (C) 2024-2026 Temple University <kleinweb@temple.edu>
# SPDX-License-Identifier: GPL-3.0-or-later

import "common.vars.just"

semver-regexp := '^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$'
wp-header-version-prefix-regexp := '(\s*)(\*\s)?Version:(\s+)'

[no-cd]
[confirm: "Prepare and tag release?"]
[doc: "Prepare and tag a release based on semantic commits"]
[arg("dry-run", long, value="--dry-run")]
release dry-run='' *ARGS='--auto':
    cog bump {{ dry-run }} {{ ARGS }}

[doc: "Bump version numbers across manifest files"]
[no-cd]
bump version:
    @for file in `rg '^{{ wp-header-version-prefix-regexp }}({{ semver-regexp }})$' --files-with-matches {{ prj-root }}`; do \
        just release::_bump-wp-header {{ version }} $file; \
    done

@_bump-json version file:
    jq --arg ver '{{version}}' '.version = $ver' {{ file }} \
    | sponge {{ file }}
    echo 'Bumped {{ file }} version to {{ version }}'
    biome format --diagnostic-level=warn --write {{ file }} >/dev/null

@_bump-wp-header version file:
    sed -E -i 's/^({{ wp-header-version-prefix-regexp }}){{ semver-regexp }}/\1{{ version }}/' {{ file }}
    echo 'Bumped {{ file }} version to {{ version }}'
