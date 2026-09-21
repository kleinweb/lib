# SPDX-FileCopyrightText: 2024-2026 Temple University <kleinweb@temple.edu>
# SPDX-License-Identifier: GPL-3.0-or-later
{
  description = "Kleinweb Standard Library";

  inputs = {
    flake-parts.url = "github:hercules-ci/flake-parts";
    beams.url = "github:kleinweb/beams";
    git-hooks.url = "github:cachix/git-hooks.nix";
    nixos-unstable.url = "github:NixOS/nixpkgs/nixos-unstable";
    nixpkgs-trunk.url = "github:NixOS/nixpkgs/master";
    nixpkgs.follows = "nixos-unstable";
  };

  outputs =
    inputs@{ flake-parts, ... }:
    flake-parts.lib.mkFlake { inherit inputs; } {
      systems = [
        "x86_64-linux"
        "aarch64-darwin"
        "aarch64-linux"
      ];

      imports = [
        inputs.git-hooks.flakeModule

        ./.config/devshells.nix
        ./.config/git-hooks.nix
      ];

      perSystem =
        { inputs', system, ... }:
        {
          _module.args.pkgs = import inputs.nixpkgs {
            inherit system;
            overlays = [
              (_final: prev: {
                php = prev.php83;
              })
            ];
          };
        };
    };
}
