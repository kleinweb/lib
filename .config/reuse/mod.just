# SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
# SPDX-FileCopyrightText: 2024 Temple University <kleinweb@temple.edu>
# SPDX-License-Identifier: GPL-3.0-or-later

import '../vars.just'

alias l-default := l-gpl
alias l-cc0 := l-public-domain

_annotate license +ARGS:
  reuse annotate -l '{{license}}' -c '{{owner-attr}}' \
    --merge-copyrights \
    {{ARGS}}

[group: "licensing"]
[doc: "Annotate all plaintext note files with the documentation license"]
l-docs:
  fd --glob '**/*.{md,mdx,markdown,org}' -X \
    just l-cc {}

[group: "licensing"]
[doc: "License the specified files as GPL-3.0-or-later"]
l-gpl +FILES: (_annotate 'GPL-3.0-or-later' FILES)

[group: "licensing"]
[doc: "Dual-license the specified files as GPL+MIT"]
l-dual colicensor +FILES: (_annotate 'GPL-3.0-or-later AND MIT' ('-c ' + quote(colicensor) + ' ' + FILES))

[group: "licensing"]
[doc: "License the specified files as non-commercial docs (CC-BY-NC-SA-4.0)"]
l-cc +FILES: (_annotate  'CC-BY-NC-SA-4.0' FILES)

[group: "licensing"]
[doc: "Release the specified files into the public domain (CC0-1.0)"]
l-public-domain +FILES: (_annotate  'CC0-1.0' FILES)
