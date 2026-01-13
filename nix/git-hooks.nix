# SPDX-FileCopyrightText: 2024-2026 Temple University <kleinweb@temple.edu>
# SPDX-License-Identifier: GPL-3.0-or-later
{
  perSystem =
    {
      config,
      inputs',
      pkgs,
      ...
    }:
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
            package = inputs'.beams.packages.php-lint;
            entry = "php-lint";
            types = [
              "file"
              "php"
            ];
            # Other PHP linters will likely fail when there are syntax errors.
            fail_fast = true;
          };
          reuse = {
            enable = true;
            stages = [ "pre-push" ];
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
