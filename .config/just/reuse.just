# SPDX-FileCopyrightText: 2024 Temple University <kleinweb@temple.edu>
#
# SPDX-License-Identifier: GPL-3.0-or-later

import '_common.vars.just'

alias l-default := l-gpl

_annotate license +FILES:
  reuse annotate -l '{{license}}' -c '{{owner-attr}}' \
    --template=compact --skip-existing {{FILES}}

[group: "licensing"]
[doc: "Annotate all plaintext note files with the documentation license"]
l-docs:
  fd --glob '**/*.{md,mdx,markdown,org}' -X \
    just l-cc {}

[group: "licensing"]
[doc: "License the specified files as GPL-3.0-or-later"]
l-gpl +FILES: (_annotate 'GPL-3.0-or-later' FILES)

[group: "licensing"]
[doc: "Dual-license the specified files as GPL/MIT"]
l-dual colicensor +FILES: (_annotate 'GPL-3.0-or-later AND MIT' ('-c ' + quote(colicensor) + ' ' + FILES))

[group: "licensing"]
[doc: "License the specified files as non-commercial docs"]
l-cc +FILES: (_annotate  'CC-BY-NC-SA-4.0' FILES)

[group: "licensing"]
[doc: "Release the specified files into the public domain"]
l-public-domain +FILES: (_annotate  'CC0-1.0' FILES)
