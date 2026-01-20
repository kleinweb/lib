# Changelog
All notable changes to this project will be documented in this file. See [conventional commits](https://www.conventionalcommits.org/) for commit guidelines.

- - -
## [1.1.2](https://github.com/kleinweb/lib/compare/f397a75c26684b2e7b9920af5afbbe1a2cbb2f13..1.1.2) - 2026-01-20
#### Bug Fixes
- check `isProduction()` via `WP_ENV` comparison - ([f397a75](https://github.com/kleinweb/lib/commit/f397a75c26684b2e7b9920af5afbbe1a2cbb2f13)) - chris montgomery

- - -

## [1.1.1](https://github.com/kleinweb/lib/compare/6a2e5266d18c86c49581ada4025af5887ac9a35d..1.1.1) - 2026-01-14
#### Bug Fixes
- add `preprod` to environment enum - ([050565f](https://github.com/kleinweb/lib/commit/050565fdb8054f93a15f032943fcbe980bd20d9b)) - chris montgomery
#### Miscellaneous Chores
- (**deps**) update dependency kleinweb/php-coding-standards to ^0.8.0 - ([6a2e526](https://github.com/kleinweb/lib/commit/6a2e5266d18c86c49581ada4025af5887ac9a35d)) - renovate[bot]

- - -

## [1.1.0](https://github.com/kleinweb/lib/compare/75bc8644fcb2b1e81afcf9b670fd4cc1b75509e4..1.1.0) - 2026-01-13
#### Features
- prevent upload of known audio or video filetypes - ([75bc864](https://github.com/kleinweb/lib/commit/75bc8644fcb2b1e81afcf9b670fd4cc1b75509e4)) - chris montgomery

- - -

## [1.0.0](https://github.com/kleinweb/lib/compare/90055a953e5c74b7b06e241c9589bf3f03fe1274..1.0.0) - 2026-01-13
#### Bug Fixes
- ![BREAKING](https://img.shields.io/badge/BREAKING-red) drop support for php 8.2 - ([4f03ade](https://github.com/kleinweb/lib/commit/4f03adeafe63d4d17eb0ebdc3ff06eade914a310)) - chris montgomery
#### Miscellaneous Chores
- (**license**) update copyright year to include 2026 - ([5e8336b](https://github.com/kleinweb/lib/commit/5e8336b435d18e6da18d129fd96a5ffde7010c34)) - chris montgomery
- (**reuse**) annotate missing - ([90055a9](https://github.com/kleinweb/lib/commit/90055a953e5c74b7b06e241c9589bf3f03fe1274)) - chris montgomery
- update deps - ([59fe5c5](https://github.com/kleinweb/lib/commit/59fe5c50f17c9b2e15c9f782fb9d601275953edb)) - chris montgomery

- - -

## [0.7.0](https://github.com/kleinweb/lib/compare/0.7.0-rc.1..0.7.0) - 2026-01-13
#### Features
- add Plugin class - ([4f715b6](https://github.com/kleinweb/lib/commit/4f715b6e61303695e15da21f01cf134910c3165e)) - [@montchr](https://github.com/montchr)
#### Bug Fixes
- (**dev**) use min supported php version in devshell - ([b17df3e](https://github.com/kleinweb/lib/commit/b17df3e0b15fef304e859182a308f49691c899f4)) - chris montgomery
- skip `dotenv-linter` check - ([b672aa0](https://github.com/kleinweb/lib/commit/b672aa03d592c7ce2975787cbf3f80650573e442)) - chris montgomery
#### Documentation
- add CLAUDE.md and expand README with usage examples - ([c1d9f31](https://github.com/kleinweb/lib/commit/c1d9f311cd90b5f65706439b25b6b8830372e25f)) - chris montgomery
#### Miscellaneous Chores
- (**reuse**) add missing - ([7540136](https://github.com/kleinweb/lib/commit/75401368af02fad22ba42d38bc2e606ef6415e32)) - [@montchr](https://github.com/montchr)
- flake update - ([fa075bb](https://github.com/kleinweb/lib/commit/fa075bb529b6162609e7bb2445ceb85e86cdf7b2)) - [@montchr](https://github.com/montchr)
- remove nixConfig - ([45bc75b](https://github.com/kleinweb/lib/commit/45bc75b9369c7b4630b1fc9ce22d65fd6350472a)) - [@montchr](https://github.com/montchr)
- fmt - ([aac8b94](https://github.com/kleinweb/lib/commit/aac8b9410acdc3b29e924672a8cefe0745e84bd3)) - [@montchr](https://github.com/montchr)

- - -

## [0.7.0-rc.1](https://github.com/kleinweb/lib/compare/22717f5569ba247554b3822dc7b89d3437992008..0.7.0-rc.1) - 2026-01-13
#### Features
- (**log**) add simple-history plugin monolog handler - ([c3431c2](https://github.com/kleinweb/lib/commit/c3431c2c7d2b47d7b15840bf9d65ab87e4121f2d)) - [@montchr](https://github.com/montchr)
- add `MultisiteOnly` exception - ([105559c](https://github.com/kleinweb/lib/commit/105559c068e8e6f12b10c2d7c3518c5779fd2fbf)) - [@montchr](https://github.com/montchr)
#### Bug Fixes
- ![BREAKING](https://img.shields.io/badge/BREAKING-red) remove boot method from abstract service provider - ([7a24ccd](https://github.com/kleinweb/lib/commit/7a24ccd48b467ad98a1556e77ea41c0f307814d6)) - [@montchr](https://github.com/montchr)
- remove redundant parent method override - ([b7964fa](https://github.com/kleinweb/lib/commit/b7964fafc10f57fdebe275b7bb0da45ada5c9bd5)) - [@montchr](https://github.com/montchr)
- replace usages of forbidden functions - ([4e0872f](https://github.com/kleinweb/lib/commit/4e0872ff37f2ce6ffed9f65f6d9e442f8506fa68)) - [@montchr](https://github.com/montchr)
- remove override of `Roots\Acorn\Application::skipProvider()` - ([a6fab83](https://github.com/kleinweb/lib/commit/a6fab8338bae016093e594645d42eb583c659d28)) - [@montchr](https://github.com/montchr)
#### Miscellaneous Chores
- (**deps**) update dependency kleinweb/php-coding-standards to ^0.7.0 - ([22717f5](https://github.com/kleinweb/lib/commit/22717f5569ba247554b3822dc7b89d3437992008)) - renovate[bot]
- (**dev**) increase phpactor phpstan memory limit - ([1d2e06c](https://github.com/kleinweb/lib/commit/1d2e06c9e1a0fc8068c325265de8a294302e106f)) - [@montchr](https://github.com/montchr)
- (**reuse**) update copyright year - ([f83b26c](https://github.com/kleinweb/lib/commit/f83b26c3544c9ebc84970a1ecae4fe61f8ebceef)) - [@montchr](https://github.com/montchr)
- (**version**) 0.7.0-rc.1 - ([eb5b8bc](https://github.com/kleinweb/lib/commit/eb5b8bc5f86920e2f04a8fc9112e0a8d9107fcf7)) - [@montchr](https://github.com/montchr)
- phpstan 2.0 support - ([79f2f3e](https://github.com/kleinweb/lib/commit/79f2f3eb3db828df415a4f4aad6494d81707e1e5)) - [@montchr](https://github.com/montchr)

- - -

## [0.7.0-rc.1](https://github.com/kleinweb/lib/compare/22717f5569ba247554b3822dc7b89d3437992008..0.7.0-rc.1) - 2025-06-11
#### Bug Fixes
- remove boot method from abstract service provider - ([7a24ccd](https://github.com/kleinweb/lib/commit/7a24ccd48b467ad98a1556e77ea41c0f307814d6)) - [@montchr](https://github.com/montchr)
- remove redundant parent method override - ([b7964fa](https://github.com/kleinweb/lib/commit/b7964fafc10f57fdebe275b7bb0da45ada5c9bd5)) - [@montchr](https://github.com/montchr)
- replace usages of forbidden functions - ([4e0872f](https://github.com/kleinweb/lib/commit/4e0872ff37f2ce6ffed9f65f6d9e442f8506fa68)) - [@montchr](https://github.com/montchr)
- remove override of `Roots\Acorn\Application::skipProvider()` - ([a6fab83](https://github.com/kleinweb/lib/commit/a6fab8338bae016093e594645d42eb583c659d28)) - [@montchr](https://github.com/montchr)
#### Features
- **(log)** add simple-history plugin monolog handler - ([c3431c2](https://github.com/kleinweb/lib/commit/c3431c2c7d2b47d7b15840bf9d65ab87e4121f2d)) - [@montchr](https://github.com/montchr)
- add `MultisiteOnly` exception - ([105559c](https://github.com/kleinweb/lib/commit/105559c068e8e6f12b10c2d7c3518c5779fd2fbf)) - [@montchr](https://github.com/montchr)
#### Miscellaneous Chores
- **(deps)** update dependency kleinweb/php-coding-standards to ^0.7.0 - ([22717f5](https://github.com/kleinweb/lib/commit/22717f5569ba247554b3822dc7b89d3437992008)) - renovate[bot]
- **(dev)** increase phpactor phpstan memory limit - ([1d2e06c](https://github.com/kleinweb/lib/commit/1d2e06c9e1a0fc8068c325265de8a294302e106f)) - [@montchr](https://github.com/montchr)
- **(reuse)** update copyright year - ([f83b26c](https://github.com/kleinweb/lib/commit/f83b26c3544c9ebc84970a1ecae4fe61f8ebceef)) - [@montchr](https://github.com/montchr)
- phpstan 2.0 support - ([79f2f3e](https://github.com/kleinweb/lib/commit/79f2f3eb3db828df415a4f4aad6494d81707e1e5)) - [@montchr](https://github.com/montchr)

- - -

## [0.6.1](https://github.com/kleinweb/lib/compare/53dcb9b726b1af9f6a409e77c676cb2f2742fadd..0.6.1) - 2025-04-12
#### Bug Fixes
- `AppServiceProvider` must be abstract - ([53dcb9b](https://github.com/kleinweb/lib/commit/53dcb9b726b1af9f6a409e77c676cb2f2742fadd)) - [@montchr](https://github.com/montchr)

- - -

## [0.6.0](https://github.com/kleinweb/lib/compare/83c739e1154d11e42b896fb7dabe7fc5f8966c70..0.6.0) - 2025-04-12
#### Bug Fixes
- **(site)** `isPrimaryHost()` must support subdomains - ([6bb8dfd](https://github.com/kleinweb/lib/commit/6bb8dfda508275d0936f5b83f80f20cde88415a1)) - [@montchr](https://github.com/montchr)
#### Features
- add base `AppServiceProvider` class - ([42d3ffa](https://github.com/kleinweb/lib/commit/42d3ffacc124a474a6df93b85b756edb4df463a9)) - [@montchr](https://github.com/montchr)
- add command to delete dead attachments - ([6f04be5](https://github.com/kleinweb/lib/commit/6f04be58c06a688944979c81d14f471a1f83d437)) - [@montchr](https://github.com/montchr)
- add command to demap multisite domains - ([83c739e](https://github.com/kleinweb/lib/commit/83c739e1154d11e42b896fb7dabe7fc5f8966c70)) - [@montchr](https://github.com/montchr)
#### Miscellaneous Chores
- reuse - ([5633a9b](https://github.com/kleinweb/lib/commit/5633a9bcbf76cb1a9acf69bdbad02e86f21a577a)) - [@montchr](https://github.com/montchr)
- phpactor: don't look for unused stubs - ([d07c5b2](https://github.com/kleinweb/lib/commit/d07c5b29896691d2ab1e057d29c74f443b2d18a5)) - [@montchr](https://github.com/montchr)

- - -

## [0.5.0](https://github.com/kleinweb/lib/compare/91706fcbb601883d49b09ede10288b8d847a3212..0.5.0) - 2025-04-11
#### Bug Fixes
- redundant instanceof check - ([5fe273c](https://github.com/kleinweb/lib/commit/5fe273cfe2bc1b9bdb7958fe3e547c18045f3ce4)) - [@montchr](https://github.com/montchr)
#### Features
- **(site)** add function to get `orig_host` site metadata - ([0c2d8d0](https://github.com/kleinweb/lib/commit/0c2d8d0215222ad375d1295b93378a65c908d716)) - [@montchr](https://github.com/montchr)
- add support for 'migration' environment type - ([91706fc](https://github.com/kleinweb/lib/commit/91706fcbb601883d49b09ede10288b8d847a3212)) - [@montchr](https://github.com/montchr)
#### Miscellaneous Chores
- fmt - ([fa9eed7](https://github.com/kleinweb/lib/commit/fa9eed719d1a84fa8fefbb199b627d17a59c8a3d)) - [@montchr](https://github.com/montchr)

- - -

## [0.4.1](https://github.com/kleinweb/lib/compare/6993a1381afdb979cacc939379c2902648d3ff60..0.4.1) - 2025-02-17
#### Bug Fixes
- **(helpers)** add missing `config()` helper - ([6993a13](https://github.com/kleinweb/lib/commit/6993a1381afdb979cacc939379c2902648d3ff60)) - [@montchr](https://github.com/montchr)

- - -

## [0.4.0](https://github.com/kleinweb/lib/compare/5d98930220ece9029f246de1727afb733ee40853..0.4.0) - 2025-02-13
#### Features
- extend `SageServiceProvider` with `Hookable` trait - ([5d98930](https://github.com/kleinweb/lib/commit/5d98930220ece9029f246de1727afb733ee40853)) - [@montchr](https://github.com/montchr)

- - -

## [0.3.0](https://github.com/kleinweb/lib/compare/0555e26ca29d5f6b30700ca7bbdc0f24a716a9d7..0.3.0) - 2024-12-06
#### Bug Fixes
- restore `CoreObjects` class for autoloading - ([a702066](https://github.com/kleinweb/lib/commit/a702066d4935679fc5afb5647f77576e03cea336)) - [@montchr](https://github.com/montchr)
#### Features
- add function to get currently-authenticated user - ([0474562](https://github.com/kleinweb/lib/commit/047456200614224133dc1ef69f986a684f2ec787)) - [@montchr](https://github.com/montchr)
#### Miscellaneous Chores
- fmt - ([a94b4c1](https://github.com/kleinweb/lib/commit/a94b4c199a32c5757ec27981fb90fd1e952bc3ed)) - [@montchr](https://github.com/montchr)
#### Refactoring
- **(helpers)** rename to `helpers.php` for framework consistency - ([4e2c9e8](https://github.com/kleinweb/lib/commit/4e2c9e810b1b89e8e70f8492e09c90682874aca2)) - [@montchr](https://github.com/montchr)
- **(helpers)** alphabetize - ([0555e26](https://github.com/kleinweb/lib/commit/0555e26ca29d5f6b30700ca7bbdc0f24a716a9d7)) - [@montchr](https://github.com/montchr)

- - -

## [0.2.3](https://github.com/kleinweb/lib/compare/4622db2a646e308185f7b32f7b2353c8f81fbe5c..0.2.3) - 2024-12-03
#### Bug Fixes
- simplify `Url::isKinstaDomain()` - ([056b1c2](https://github.com/kleinweb/lib/commit/056b1c2d511da689d4ddba20bbb3c99e2359e3a3)) - [@montchr](https://github.com/montchr)
#### Features
- functions to determine whether site is kinsta domain - ([4622db2](https://github.com/kleinweb/lib/commit/4622db2a646e308185f7b32f7b2353c8f81fbe5c)) - [@montchr](https://github.com/montchr)
#### Miscellaneous Chores
- **(version)** 0.2.3-rc.1 - ([55336cd](https://github.com/kleinweb/lib/commit/55336cdfa623b40de4279ecbc5185bd33e891ccd)) - [@montchr](https://github.com/montchr)

- - -

## [0.2.3-rc.1](https://github.com/kleinweb/lib/compare/4622db2a646e308185f7b32f7b2353c8f81fbe5c..0.2.3-rc.1) - 2024-12-03
#### Features
- functions to determine whether site is kinsta domain - ([4622db2](https://github.com/kleinweb/lib/commit/4622db2a646e308185f7b32f7b2353c8f81fbe5c)) - [@montchr](https://github.com/montchr)

- - -

## [0.2.2](https://github.com/kleinweb/lib/compare/ae44a2cfbf3ee823126877de071ec7fa404d32ad..0.2.2) - 2024-11-25
#### Bug Fixes
- **(just|release)** accept args override - ([ae44a2c](https://github.com/kleinweb/lib/commit/ae44a2cfbf3ee823126877de071ec7fa404d32ad)) - [@montchr](https://github.com/montchr)

- - -

## [0.2.0](https://github.com/kleinweb/lib/compare/050afda6cb37efe78c9d9cd1a7a24fd297caa4d1..0.2.0) - 2024-11-20
#### Bug Fixes
- **(prj)** use correct just task to bump version - ([f58090e](https://github.com/kleinweb/lib/commit/f58090ea3823b1d3a5dfa1538197838562812de4)) - [@montchr](https://github.com/montchr)
- **(prj)** composer.json should not set version - ([050afda](https://github.com/kleinweb/lib/commit/050afda6cb37efe78c9d9cd1a7a24fd297caa4d1)) - [@montchr](https://github.com/montchr)

- - -

Changelog generated by [cocogitto](https://github.com/cocogitto/cocogitto).