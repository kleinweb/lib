# SPDX-FileCopyrightText: (C) 2024-2025 Temple University <kleinweb@temple.edu>
# SPDX-License-Identifier: GPL-3.0-or-later

import '../vars.just'

[doc: "Rebuild the Phpactor project index"]
reindex:
  phpactor index:build --reset
