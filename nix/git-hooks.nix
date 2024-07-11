# SPDX-FileCopyrightText: 2024 Temple University <kleinweb@temple.edu>
#
# SPDX-License-Identifier: GPL-3.0-or-later

{
  perSystem =
    { config, pkgs, ... }:
    {
      pre-commit.settings = {
        hooks = {
          check-xml.enable = true;
          composer-lint = {
            enable = true;
            entry = "composer lint --";
            types = [
              "file"
              "php"
            ];
            stages = [ "pre-commit" ];
          };
          markdownlint.enable = true;
          markdownlint.excludes = [
            # Auto-generated
            "CHANGELOG.md"
          ];
          php-lint = {
            enable = true;
            description = "Check PHP files for syntax errors";
            package = config.packages.php-lint;
            entry = "php-lint";
            types = [
              "file"
              "php"
            ];
            # Other PHP linters will likely fail when there are syntax errors.
            fail_fast = true;
          };
          treefmt.enable = true;
          yamllint.enable = true;
        };
        default_stages = [
          "pre-commit"
          "pre-push"
        ];

        excludes = [ ];
      };
    };
}
