# Install mailcow

You need Docker and Docker Compose.

1\. Learn how to install [Docker](https://docs.docker.com/engine/installation/linux/) and [Docker Compose](https://docs.docker.com/compose/install/).

2\. Clone the master branch of the repository
```
git clone https://github.com/andryyy/mailcow-dockerized && cd mailcow-dockerized
```

3\. Generate a configuration file. Use a FQDN (`host.domain.tld`) as hostname when asked.
```
./generate_config.sh
```

4\. Change configuration if you want or need to.
```
nano mailcow.conf
```
If you plan to use a reverse proxy, you can, for example, bind HTTPS to 127.0.0.1 on port 8443 and HTTP to 127.0.0.1 on port 8080.

5\. Run the composer file.
```
docker-compose up -d
```

Done!

You can now access **https://${MAILCOW_HOSTNAME}** with the default credentials `admin` + password `moohoo`.

It may take a while for MySQL to warm up, so please wait half a minute.

The database will be initialized right after a connection to MySQL can be established.

# Update mailcow

There is no update routine.

You need to refresh your pulled repository clone by running `git pull` - this will likely fail due to changes to your local configuration. But that's why we use git! :-)

Whatever file has local changes, add and commit it to your repository clone. For example:

```
git add data/conf/postfix/main.cf data/conf/dovecot/dovecot.conf
git commit -m "My changes to main.cf and dovecot.conf
```

Try running `git pull` again and resolve conflicts, if any.

Now update all images, apply changes to containers and restart all services:

```
docker-compose pull
docker-compose up -d --remove-orphans
docker-compose restart
```

## Development branch (not recommended)

When you checkout the "dev" git branch, you will most likely end up using the "master" images with code base of "dev".
If there were critical changes to the images in dev, mailcow will not work.

But you can still build the images by yourself:

```
docker-compose up -d --build
```
