set working-directory := "../"

import "common.vars.just"

[doc("Check for code quality issues on project files")]
check:
    biome check {{ prj-root }}
    composer check

[doc("Check for non-stylistic linting issues on project files")]
lint:
    biome lint {{ prj-root }}
    composer lint

[group("php")]
[doc("Validate syntax of project PHP files")]
check-php-syntax:
    fd --type file --extension php --hidden --exec-batch \
        composer check-syntax

[confirm]
[doc("Write unsafe code quality changes to project files")]
fix:
    treefmt
    composer fix

[doc("Write safe formatting changes to project files")]
fmt:
    treefmt
