# Install mailcow

1. You need Docker.

    - Most systems can install Docker by running `wget -qO- https://get.docker.com/ | sh`

2. You need Docker Compose.

    - Learn [how to install Docker Compose](https://docs.docker.com/compose/install/)

3. Clone the master branch of the repository and run `./generate_config.sh` to generate a file "mailcow.conf". You will be asked for a hostname and a timezone:

    - `git clone https://github.com/andryyy/mailcow-dockerized && cd mailcow-dockerized`
	- `./generate_config.sh`
	- Open and check "mailcow.conf" if you need or want to make changes to ports (for example changing the default HTTPS port)

4. Run the composer file.
    - `docker-compose up -d`

Done.

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

## Use dev branch (not recommended)

When you checkout the dev branch, you will most likely end up using the "master" images with code base of "dev".
If there were critical changes to the images in dev, mailcow will not work.

But you can still build the images by yourself:

```
docker-compose up -d --build
```
