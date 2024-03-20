import subprocess
import os
import sys
import signal
from jinja2 import Environment, FileSystemLoader
import time


def sites_default_conf(env, vars):
    config_name = "sites-default.conf"
    template = env.get_template(f"{config_name}.j2")

    config = template.render(
        TRUSTED_NETWORK=vars['TRUSTED_NETWORK'],
        SKIP_SOGO=vars['SKIP_SOGO'],
        SKIP_RSPAMD=vars['SKIP_RSPAMD'],
        SKIP_MAILCOW_UI=vars['SKIP_MAILCOW_UI'],
        NGINX_USE_PROXY_PROTOCOL=vars['NGINX_USE_PROXY_PROTOCOL'],
        sogo_proxy_auth=vars['sogo_proxy_auth'],
        sogo_proxy_pass=vars['sogo_proxy_pass'],
        sogo_eas_proxy_pass=vars['sogo_eas_proxy_pass']
    )

    with open(f"/etc/nginx/includes/{config_name}", "w") as f:
        f.write(config)

def nginx_conf(env, vars):
    config_name = "nginx.conf"
    template = env.get_template(f"{config_name}.j2")

    config = template.render(
        cert_dirs=vars['valid_cert_dirs'],
        MAILCOW_HOSTNAME=vars['MAILCOW_HOSTNAME'],
        ADDITIONAL_SERVER_NAMES=vars['ADDITIONAL_SERVER_NAMES'],
        HTTP_PORT=vars['HTTP_PORT'],
        HTTPS_PORT=vars['HTTPS_PORT'],
        NGINX_USE_PROXY_PROTOCOL=vars['NGINX_USE_PROXY_PROTOCOL']
    )

    with open(f"/etc/nginx/{config_name}", "w") as f:
        f.write(config)

def prepare_template_vars():
    vars = {}
    vars['IPV4_NETWORK'] = os.getenv("IPV4_NETWORK", "172.22.1")

    vars['TRUSTED_NETWORK'] = os.getenv("TRUSTED_NETWORK", False)
    vars['SKIP_RSPAMD'] = os.getenv("SKIP_RSPAMD", "n").lower() in ("y", "yes")
    vars['SKIP_MAILCOW_UI'] = os.getenv("SKIP_MAILCOW_UI", "n").lower() in ("y", "yes")
    vars['NGINX_USE_PROXY_PROTOCOL'] = os.getenv("NGINX_USE_PROXY_PROTOCOL", "n").lower() in ("y", "yes")
    vars['MAILCOW_HOSTNAME'] = os.getenv("MAILCOW_HOSTNAME", "")
    vars['ADDITIONAL_SERVER_NAMES'] = os.getenv("ADDITIONAL_SERVER_NAMES", "").replace(',', ' ')
    vars['HTTP_PORT'] = os.getenv("HTTP_PORT", "80")
    vars['HTTPS_PORT'] = os.getenv("HTTPS_PORT", "443")
    vars['sogo_proxy_pass'] = f"proxy_pass http://{vars['IPV4_NETWORK']}.248:20000;"
    if os.getenv("SKIP_SOGO", "n").lower() in ("y", "yes"):
        vars['SKIP_SOGO'] = True
        vars['sogo_eas_proxy_pass'] = "return 410;"
    else:
        vars['SKIP_SOGO'] = False
        vars['sogo_eas_proxy_pass'] = f"proxy_pass http://{vars['IPV4_NETWORK']}.248:20000/SOGo/Microsoft-Server-ActiveSync;"

    if not vars['SKIP_MAILCOW_UI'] and vars['SKIP_SOGO']:
        vars['sogo_proxy_auth'] = ""
    else:
        vars['sogo_proxy_auth'] = """
        auth_request /sogo-auth-verify;
        auth_request_set $user $upstream_http_x_user;
        auth_request_set $auth $upstream_http_x_auth;
        auth_request_set $auth_type $upstream_http_x_auth_type;
        proxy_set_header x-webobjects-remote-user "$user";
        proxy_set_header Authorization "$auth";
        proxy_set_header x-webobjects-auth-type "$auth_type";
        """

    ssl_dir = '/etc/ssl/mail/'
    vars['valid_cert_dirs'] = []
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
            if domains and domains != os.getenv("MAILCOW_HOSTNAME", ""):
                vars['valid_cert_dirs'].append({
                    'cert_path': full_path + '/',
                    'domains': domains.replace(',', ' ')
                })

    return vars

def wait_for_host(host, timeout=5):
    while True:
        try:
            response = subprocess.run(["ping", "-c", "1", "-W", str(timeout), host], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            
            if response.returncode == 0:
                break
            else:
                print(f"Waiting for {host}...")
        except Exception as e:
            print(f"An error occurred while trying to ping {host}: {e}")
        
        time.sleep(1)

def sigterm_quit():
    sys.exit()

def main():  
    signal.signal(signal.SIGTERM, sigterm_quit)
    env = Environment(loader=FileSystemLoader('./etc/nginx/conf.d'))

    # Generate config
    print("Generate config")
    vars = prepare_template_vars()
    sites_default_conf(env, vars)
    nginx_conf(env, vars)
    # Wait for services
    wait_for_host("phpfpm")
    wait_for_host("sogo")
    wait_for_host("redis")
    wait_for_host("rspamd")
    # Validate config
    print("Validate config")
    subprocess.run(["nginx", "-qt"])

    # Start NGINX
    print("Starting NGINX")
    subprocess.run(["nginx", "-g", "daemon off;"])


if __name__ == "__main__":
    main()
