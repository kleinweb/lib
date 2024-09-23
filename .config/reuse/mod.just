# SPDX-FileCopyrightText: (C) 2024 Temple University <kleinweb@temple.edu>
# SPDX-FileCopyrightText: 2024 Temple University <kleinweb@temple.edu>
# SPDX-License-Identifier: GPL-3.0-or-later

import '../vars.just'

gpl30Plus := 'GPL-3.0-or-later'
mit := 'MIT'
and := ' AND '

alias public-domain := cc0

default:
  reuse lint

[private]
annotate license +ARGS:
  reuse annotate \
    --template compact \
    --copyright-prefix spdx-c \
    --license '{{license}}' \
    --copyright '{{owner-attr}}' \
    --merge-copyrights \
    {{ARGS}}

[doc: "Annotate all plaintext note files with the documentation license"]
docs:
  fd --glob '**/*.{md,mdx,markdown,org}' -X \
    just commons {}

[doc: "License the specified files as GPL-3.0-or-later"]
gpl +FILES: (annotate gpl30Plus FILES)

[doc: "Dual-license the specified files as GPL+MIT"]
dual colicensor +FILES: (annotate (gpl30Plus + and + mit) ('-c ' + quote(colicensor) + ' ' + FILES))

[doc: "License the specified files as non-commercial docs (CC-BY-NC-SA-4.0)"]
commons +FILES: (annotate 'CC-BY-NC-SA-4.0' FILES)

[doc: "Release the specified files into the public domain (CC0-1.0)"]
cc0 +FILES: (annotate 'CC0-1.0' FILES)
