import "./common.vars.just"

update-composer-auth-json-file:
    op document get ocq4xdqicp6htzkynjt2f77a5y --out-file {{ prj-root }}/auth.json
