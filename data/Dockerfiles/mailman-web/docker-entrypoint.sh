#! /bin/bash
set -e

while ! mysqladmin ping --host mysql -u${DBUSER} -p${DBPASS} --silent; do
  echo "Waiting for database to come up..."
  sleep 2
done

mysql --host mysql -uroot -p${DBROOT} << EOF
CREATE DATABASE IF NOT EXISTS ${DBNAME}_mm;
GRANT ALL ON ${DBNAME}_mm.* TO ${DBUSER}@"%";
EOF

export DBROOT=

mkdir -p /opt/mm_web
touch /opt/mm_web/uwsgi.log
touch /opt/mm_web/django.log
mkdir -p /opt/mm_web-data/
chown -R mailman:mailman /opt/mm_web
chown -R mailman:mailman /opt/mm_web-data
chmod +x /opt/mm_web/manage.py

su-exec mailman python /opt/mm_web/manage.py makemigrations --merge
su-exec mailman python /opt/mm_web/manage.py migrate
su-exec mailman python /opt/mm_web/manage.py shell -c "from django.contrib.sites.models import Site; Site.objects.update(domain='${MAILCOW_HOSTNAME}', name='${MAILCOW_HOSTNAME}')"
su-exec mailman python /opt/mm_web/manage.py collectstatic --noinput
su-exec mailman python /opt/mm_web/manage.py clearsessions

exec su-exec mailman "$@"
