import "./common.vars.just"

init: update-composer-auth-json-file

update-composer-auth-json-file:
    op document get ocq4xdqicp6htzkynjt2f77a5y --out-file {{ prj-root }}/auth.json
