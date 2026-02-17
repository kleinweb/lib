# SPDX-FileCopyrightText: 2022-2026 Temple University <kleinweb@temple.edu>
# SPDX-License-Identifier: GPL-3.0-or-later

###: <https://just.systems/man/en/>

import '.config/vars.just'

mod php '.config/php'
mod release '.config/release'
mod reuse '.config/reuse'

default:
  @just --choose

[group: "qa"]
[doc: "Check for any lint or formatting issues on project files"]
check:
  biome check {{prj-root}}
  php-lint-project
  composer php-cs-fixer -- check
  composer phpcs
  composer phpstan

[group: "qa"]
[doc: "Check for (non-stylistic) linting issues on project files"]
lint:
  biome lint {{prj-root}}
  php-lint-project
  composer lint

[group: "qa"]
[doc: "Write *all* formatter+fixer changes to project files"]
fix:
  treefmt
  composer fix

[group: "qa"]
[doc: "Write _safe_ formatter changes to project files"]
fmt:
  treefmt
