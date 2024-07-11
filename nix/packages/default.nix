{
  imports = [ ./php-lint.nix ];

  perSystem =
    { pkgs, ... }:
    {
      packages = {
        php-stubs-generator = pkgs.callPackage ./php-stubs-generator/package.nix { };
      };
    };
}
