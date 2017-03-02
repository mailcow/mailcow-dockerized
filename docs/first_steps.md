# SSL (and: How to use Let's Encrypt)

mailcow dockerized comes with a snakeoil CA "mailcow" and a server certificate in `data/assets/ssl`. Please use your own trusted certificates.

mailcow uses 3 domain names that should be covered by your new certificate:

- ${MAILCOW_HOSTNAME}
- autodiscover.**example.org**
- autoconfig.**example.org**

**Obtain multi-SAN certificate by Let's Encrypt** 

This is just an example of how to obtain certificates with certbot. There are several methods!

1. Get the certbot client:

    ```
    wget https://dl.eff.org/certbot-auto -O /usr/local/sbin/certbot && chmod +x /usr/local/sbin/certbot
    ```

2. Make sure you set `HTTP_BIND=0.0.0.0` in `mailcow.conf` or setup a reverse proxy to enable connections to port 80. If you changed HTTP_BIND, then restart Nginx: `docker-compose restart nginx-mailcow`.

3. Request the certificate with the webroot method:

    ```
    cd /path/to/git/clone/mailcow-dockerized
    source mailcow.conf
    certbot certonly \
            --webroot \
            -w ${PWD}/data/web \
            -d ${MAILCOW_HOSTNAME} \
            -d autodiscover.example.org \
            -d autoconfig.example.org \
            --email you@example.org \
            --agree-tos
    ```

4. Create hard links to the full path of the new certificates. Assuming you are still in the mailcow root folder:

    ```
    mv data/assets/ssl/cert.{pem,pem.backup}
    mv data/assets/ssl/key.{pem,pem.backup}
    ln $(readlink -f /etc/letsencrypt/live/${MAILCOW_HOSTNAME}/fullchain.pem) data/assets/ssl/cert.pem
    ln $(readlink -f /etc/letsencrypt/live/${MAILCOW_HOSTNAME}/privkey.pem) data/assets/ssl/key.pem
    ```

5. Restart affected containers:

    ```
    docker-compose restart postfix-mailcow dovecot-mailcow nginx-mailcow
    ```

When renewing certificates, run the last two steps (link + restart) as post-hook in a script.

# Rspamd Web UI
At first you may want to setup Rspamds web interface which provides some useful features and information.

1. Generate a Rspamd controller password hash:

    ```
    docker-compose exec rspamd-mailcow rspamadm pw
    ```

2. Replace the default hash in `data/conf/rspamd/override.d/worker-controller.inc` by your newly generated:

    ```
    enable_password = "myhash";
    ```

3. Restart rspamd:

    ```
    docker-compose restart rspamd-mailcow
    ```

Open https://${MAILCOW_HOSTNAME}/rspamd in a browser and login!

# Optional: Reverse proxy

You don't need to change the Nginx site that comes with mailcow: dockerized.
mailcow: dockerized trusts the default gateway IP 172.22.1.1 as proxy. This is very important to control access to Rspamd's web UI.

1. Make sure you change HTTP_BIND and HTTPS_BIND in `mailcow.conf` to a local address and set the ports accordingly, for example:

    ```
    HTTP_BIND=127.0.0.1
    HTTP_PORT=8080
    HTTPS_PORT=127.0.0.1
    HTTPS_PORT=8443
    ```

    Recreate affected containers by running `docker-compose up -d`.

2. Configure your local webserver as reverse proxy:

    **Apache 2.4**
    ```
    <VirtualHost *:443>
        ServerName mail.example.org
        ServerAlias autodiscover.example.org
        ServerAlias autoconfig.example.org

        [...]
        # You should proxy to a plain HTTP session to offload SSL processing
        ProxyPass / http://127.0.0.1:8080
        ProxyPassReverse / http://127.0.0.1:8080
        ProxyPreserveHost On
        your-ssl-configuration-here
        [...]

        # If you plan to proxy to a HTTPS host:
        #SSLProxyEngine On
        
        # If you plan to proxy to an untrusted HTTPS host:
        #SSLProxyVerify none
        #SSLProxyCheckPeerCN off
        #SSLProxyCheckPeerName off
        #SSLProxyCheckPeerExpire off
    </VirtualHost>
    ```

    **Nginx**
    ```
    server {
        listen 443;
        server_name mail.example.org autodiscover.example.org autoconfig.example.org;

        [...]
        your-ssl-configuration-here
        location / {
            proxy_pass http://127.0.0.1:8080;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }
        [...]
    }
    ```

