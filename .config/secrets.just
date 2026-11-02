# SPDX-FileCopyrightText: 2022-2026 Temple University <kleinweb@temple.edu>
#
# SPDX-License-Identifier: GPL-3.0-or-later

import "./common.vars.just"

init: update-composer-auth-json-file

update-composer-auth-json-file:
    op document get ocq4xdqicp6htzkynjt2f77a5y --out-file {{ prj-root }}/auth.json
