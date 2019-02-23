if printf "%s\n" "${ALLOW_ADMIN_EMAIL_LOGIN}" | grep -E '^([yY][eE][sS]|[yY])+$' >/dev/null; then
    echo '
auth_request /sogo-auth-verify;
auth_request_set $user $upstream_http_x_username;
proxy_set_header x-webobjects-remote-user $user;
'
fi
