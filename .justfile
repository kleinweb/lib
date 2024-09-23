# SPDX-FileCopyrightText: 2022-2024 Temple University <kleinweb@temple.edu>
# SPDX-License-Identifier: GPL-3.0-or-later

###: <https://just.systems/man/en/>

set dotenv-filename := '.env'
set dotenv-required

import '.config/vars.just'

[group: "php"]
mod php '.config/php'

[group: "release"]
mod release '.config/release'

[group: "licensing"]
mod reuse '.config/reuse'

php-lint-project-cmd := "nix run 'github:kleinweb/beams#php-lint-project'"

# Display a list of available tasks as the default command
default:
  @just --choose

[group: "qa"]
[doc: "Check for any lint or formatting issues on project files"]
check:
  dotenv-linter check
  biome check {{prj-root}}
  {{php-lint-project-cmd}}
  composer php-cs-fixer -- check
  composer phpcs
  composer phpstan

[group: "qa"]
[doc: "Check for (non-stylistic) linting issues on project files"]
lint:
  biome lint {{prj-root}}
  {{php-lint-project-cmd}}
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
