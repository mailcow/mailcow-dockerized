if printf "%s\n" "${ALLOW_ADMIN_EMAIL_LOGIN}" | grep -E '^([yY][eE][sS]|[yY])+$' >/dev/null; then
    echo 'auth_request /sogo-auth-verify;
auth_request_set $user $upstream_http_x_user;
auth_request_set $auth $upstream_http_x_auth;
auth_request_set $auth_type $upstream_http_x_auth_type;
proxy_set_header x-webobjects-remote-user "$user";
proxy_set_header Authorization "$auth";
proxy_set_header x-webobjects-auth-type "$auth_type";
'
fi
