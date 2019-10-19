echo '
server {
  listen 127.0.0.1:65510;
  listen '${HTTP_PORT}' default_server;
  listen [::]:'${HTTP_PORT}' default_server;
  listen '${HTTPS_PORT}' ssl http2 default_server;
  listen [::]:'${HTTPS_PORT}' ssl http2 default_server;

  ssl_certificate /etc/ssl/mail/cert.pem;
  ssl_certificate_key /etc/ssl/mail/key.pem;

  server_name '${MAILCOW_HOSTNAME}' autodiscover.* autoconfig.*;

  include /etc/nginx/conf.d/includes/site-defaults.conf;
}
';
for cert_dir in /etc/ssl/mail/*/ ; do
  if [[ ! -f ${cert_dir}domains ]] || [[ ! -f ${cert_dir}cert.pem ]] || [[ ! -f ${cert_dir}key.pem ]]; then
    continue
  fi
  # remove hostname to not cause nginx warnings (hostname is covered in default server listen)
  domains="$(cat ${cert_dir}domains | sed -e "s/\(^\| \)\($(echo ${MAILCOW_HOSTNAME} | sed 's/\./\\./g')\)\( \|$\)/ /g" | sed -e 's/^[[:space:]]*//')"
  if [[ "${domains}" == "" ]]; then
    continue
  fi
  echo -n '
server {
  listen '${HTTPS_PORT}' ssl http2;
  listen [::]:'${HTTPS_PORT}' ssl http2;

  ssl_certificate '${cert_dir}'cert.pem;
  ssl_certificate_key '${cert_dir}'key.pem;
';
  echo -n '
  server_name '${domains}';

  include /etc/nginx/conf.d/includes/site-defaults.conf;
}
';
done
