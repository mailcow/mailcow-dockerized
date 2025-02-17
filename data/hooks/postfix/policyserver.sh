#!/bin/bash
apt-get update && apt-get install -y --no-install-recommends \
ruby3.1 \
libmariadb-dev \
ruby-mysql2 \
vim \
net-tools \
procps \
&& rm -rf /var/lib/apt/lists/* \

cp /assets/postfix/policyserver.rb /usr/local/bin/policyserver.rb
chmod +x /usr/local/bin/policyserver.rb

# Create policyserver_config.yml with the necessary database credentials.
cat <<EOF > /opt/postfix/conf/policyserver_config.yml
# config.yml
dbhost: mysql
dbname: ${DBNAME}
dbuser: ${DBUSER}
dbpass: ${DBPASS}
EOF

cat <<EOF >> /opt/postfix/conf/master.cf
# start policyserver
127.0.0.1:10777 inet n n n - 0 spawn user=nobody argv=/usr/local/bin/policyserver.rb
# end policyserver

