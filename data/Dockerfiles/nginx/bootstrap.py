import os
import subprocess
from jinja2 import Environment, FileSystemLoader

def includes_conf(env, template_vars):
  server_name = "server_name.active"
  listen_plain = "listen_plain.active"
  listen_ssl = "listen_ssl.active"

  server_name_config = f"server_name {template_vars['MAILCOW_HOSTNAME']} autodiscover.* autoconfig.* {' '.join(template_vars['ADDITIONAL_SERVER_NAMES'])};"
  listen_plain_config = f"listen {template_vars['HTTP_PORT']};"
  listen_ssl_config = f"listen {template_vars['HTTPS_PORT']};"
  if not template_vars['DISABLE_IPv6']:
    listen_plain_config += f"\nlisten [::]:{template_vars['HTTP_PORT']};"
    listen_ssl_config += f"\nlisten [::]:{template_vars['HTTPS_PORT']} ssl;"
  listen_ssl_config += "\nhttp2 on;"

  with open(f"/etc/nginx/conf.d/{server_name}", "w") as f:
    f.write(server_name_config)

  with open(f"/etc/nginx/conf.d/{listen_plain}", "w") as f:
    f.write(listen_plain_config)

  with open(f"/etc/nginx/conf.d/{listen_ssl}", "w") as f:
    f.write(listen_ssl_config)

def sites_default_conf(env, template_vars):
  config_name = "sites-default.conf"
  template = env.get_template(f"{config_name}.j2")
  config = template.render(template_vars)

  with open(f"/etc/nginx/includes/{config_name}", "w") as f:
    f.write(config)

def nginx_conf(env, template_vars):
  config_name = "nginx.conf"
  template = env.get_template(f"{config_name}.j2")
  config = template.render(template_vars)

  with open(f"/etc/nginx/{config_name}", "w") as f:
    f.write(config)

def prepare_template_vars():
  ipv4_network = os.getenv("IPV4_NETWORK", "172.22.1")
  additional_server_names = os.getenv("ADDITIONAL_SERVER_NAMES", "")

  template_vars = {
    'IPV4_NETWORK': ipv4_network,
    'TRUSTED_NETWORK': os.getenv("TRUSTED_NETWORK", False),
    'SKIP_RSPAMD': os.getenv("SKIP_RSPAMD", "n").lower() in ("y", "yes"),
    'SKIP_SOGO': os.getenv("SKIP_SOGO", "n").lower() in ("y", "yes"),
    'NGINX_USE_PROXY_PROTOCOL': os.getenv("NGINX_USE_PROXY_PROTOCOL", "n").lower() in ("y", "yes"),
    'MAILCOW_HOSTNAME': os.getenv("MAILCOW_HOSTNAME", ""),
    'ADDITIONAL_SERVER_NAMES': [item.strip() for item in additional_server_names.split(",") if item.strip()],
    'HTTP_PORT': os.getenv("HTTP_PORT", "80"),
    'HTTPS_PORT': os.getenv("HTTPS_PORT", "443"),
    'SOGOHOST': os.getenv("SOGOHOST", ipv4_network + ".248"),
    'RSPAMDHOST': os.getenv("RSPAMDHOST", "rspamd-mailcow"),
    'PHPFPMHOST': os.getenv("PHPFPMHOST", "php-fpm-mailcow"),
    'DISABLE_IPv6': os.getenv("DISABLE_IPv6", "n").lower() in ("y", "yes"),
    'HTTP_REDIRECT': os.getenv("HTTP_REDIRECT", "n").lower() in ("y", "yes"),
  }

  ssl_dir = '/etc/ssl/mail/'
  template_vars['valid_cert_dirs'] = []
  for d in os.listdir(ssl_dir):
    full_path = os.path.join(ssl_dir, d)
    if not os.path.isdir(full_path):
      continue

    cert_path = os.path.join(full_path, 'cert.pem')
    key_path = os.path.join(full_path, 'key.pem')
    domains_path = os.path.join(full_path, 'domains')

    if os.path.isfile(cert_path) and os.path.isfile(key_path) and os.path.isfile(domains_path):
      with open(domains_path, 'r') as file:
        domains = file.read().strip()
      domains_list = domains.split()
      if domains_list and template_vars["MAILCOW_HOSTNAME"] not in domains_list:
        template_vars['valid_cert_dirs'].append({
          'cert_path': full_path + '/',
          'domains': domains
        })

  return template_vars

def main():
  env = Environment(loader=FileSystemLoader('./etc/nginx/conf.d/templates'))

  # Render config
  print("Render config")
  template_vars = prepare_template_vars()
  sites_default_conf(env, template_vars)
  nginx_conf(env, template_vars)
  includes_conf(env, template_vars)


if __name__ == "__main__":
  main()
