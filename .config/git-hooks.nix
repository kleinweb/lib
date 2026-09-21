# SPDX-FileCopyrightText: 2024-2026 Temple University <kleinweb@temple.edu>
# SPDX-License-Identifier: GPL-3.0-or-later
{
  perSystem =
    { inputs', ... }:
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
            "CLAUDE.md"
          ];
          treefmt.enable = true;
          yamllint.enable = true;
          yamllint.excludes = [
            "^\.copier-answers\.yml$"
            "^\.ddev/.+$"
          ];
        };
        default_stages = [
          "pre-commit"
          "pre-push"
        ];
        excludes = [ ];
      };
    };
}
