#!/bin/bash

trap "postfix stop" EXIT

[[ ! -d /opt/postfix/conf/sql/ ]] && mkdir -p /opt/postfix/conf/sql/

# Wait for MySQL to warm-up
while ! mysqladmin status --socket=/var/run/mysqld/mysqld.sock -u${DBUSER} -p${DBPASS} --silent; do
  echo "Waiting for database to come up..."
  sleep 2
done

until dig +short mailcow.email > /dev/null; do
  echo "Waiting for DNS..."
  sleep 1
done

cat <<EOF > /etc/aliases
# Autogenerated by mailcow
null: /dev/null
watchdog: /dev/null
ham: "|/usr/local/bin/rspamd-pipe-ham"
spam: "|/usr/local/bin/rspamd-pipe-spam"
EOF
newaliases;

# create sni configuration
if [[ "${SKIP_LETS_ENCRYPT}" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
  echo -n "" > /opt/postfix/conf/sni.map
else
  echo -n "" > /opt/postfix/conf/sni.map;
  for cert_dir in /etc/ssl/mail/*/ ; do
    if [[ ! -f ${cert_dir}domains ]] || [[ ! -f ${cert_dir}cert.pem ]] || [[ ! -f ${cert_dir}key.pem ]]; then
      continue;
    fi
    IFS=" " read -r -a domains <<< "$(cat "${cert_dir}domains")"
    for domain in "${domains[@]}"; do
      echo -n "${domain} ${cert_dir}key.pem ${cert_dir}cert.pem" >> /opt/postfix/conf/sni.map;
      if [[ -f ${cert_dir}ecdsa-cert.pem && -f ${cert_dir}ecdsa-key.pem ]]; then
        echo -n " ${cert_dir}ecdsa-key.pem ${cert_dir}ecdsa-cert.pem" >> /opt/postfix/conf/sni.map;
      fi
      echo "" >> /opt/postfix/conf/sni.map;
    done
  done
fi
postmap -F hash:/opt/postfix/conf/sni.map;

cat <<EOF > /opt/postfix/conf/sql/mysql_relay_ne.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT IF(EXISTS(SELECT address, domain FROM alias
      WHERE address = '%s'
        AND domain IN (
          SELECT domain FROM domain
            WHERE backupmx = '1'
              AND relay_all_recipients = '1'
              AND relay_unknown_only = '1')

      ), 'lmtp:inet:dovecot:24', NULL) AS 'transport'
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_relay_recipient_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT DISTINCT
  CASE WHEN '%d' IN (
    SELECT domain FROM domain
      WHERE relay_all_recipients=1
        AND domain='%d'
        AND backupmx=1
  )
  THEN '%s' ELSE (
    SELECT goto FROM alias WHERE address='%s' AND active='1'
  )
  END AS result;
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_tls_policy_override_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT CONCAT(policy, ' ', parameters) AS tls_policy FROM tls_policy_override WHERE active = '1' AND dest = '%s'
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_tls_enforce_in_policy.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT IF(EXISTS(
  SELECT 'TLS_ACTIVE' FROM alias
    LEFT OUTER JOIN mailbox ON mailbox.username = alias.goto
      WHERE (address='%s'
        OR address IN (
          SELECT CONCAT('%u', '@', target_domain) FROM alias_domain
            WHERE alias_domain='%d'
        )
      ) AND JSON_UNQUOTE(JSON_VALUE(attributes, '$.tls_enforce_in')) = '1' AND mailbox.active = '1'
  ), 'reject_plaintext_session', NULL) AS 'tls_enforce_in';
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_sender_dependent_default_transport_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT GROUP_CONCAT(transport SEPARATOR '') AS transport_maps
  FROM (
    SELECT IF(EXISTS(SELECT 'smtp_type' FROM alias
      LEFT OUTER JOIN mailbox ON mailbox.username = alias.goto
        WHERE (address = '%s'
          OR address IN (
            SELECT CONCAT('%u', '@', target_domain) FROM alias_domain
              WHERE alias_domain = '%d'
          )
        )
        AND JSON_UNQUOTE(JSON_VALUE(attributes, '$.tls_enforce_out')) = '1'
        AND mailbox.active = '1'
    ), 'smtp_enforced_tls:', 'smtp:') AS 'transport'
    UNION ALL
    SELECT COALESCE(
      (SELECT hostname FROM relayhosts
      LEFT OUTER JOIN mailbox ON JSON_UNQUOTE(JSON_VALUE(mailbox.attributes, '$.relayhost')) = relayhosts.id
        WHERE relayhosts.active = '1'
          AND (
            mailbox.username IN (SELECT alias.goto from alias
              JOIN mailbox ON mailbox.username = alias.goto
                WHERE alias.active = '1'
                  AND alias.address = '%s'
                  AND alias.address NOT LIKE '@%%'
            )
          )
      ),
      (SELECT hostname FROM relayhosts
      LEFT OUTER JOIN domain ON domain.relayhost = relayhosts.id
        WHERE relayhosts.active = '1'
          AND (domain.domain = '%d'
            OR domain.domain IN (
              SELECT target_domain FROM alias_domain
                WHERE alias_domain = '%d'
            )
          )
      )
    )
  ) AS transport_view;
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_transport_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT CONCAT('smtp_via_transport_maps:', nexthop) AS transport FROM transports
  WHERE active = '1'
  AND destination = '%s';
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_virtual_resource_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT 'null@localhost' FROM mailbox
  WHERE kind REGEXP 'location|thing|group' AND username = '%s';
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_sasl_passwd_maps_sender_dependent.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT CONCAT_WS(':', username, password) AS auth_data FROM relayhosts
  WHERE id IN (
    SELECT COALESCE(
      (SELECT id FROM relayhosts
      LEFT OUTER JOIN domain ON domain.relayhost = relayhosts.id
      WHERE relayhosts.active = '1'
        AND (domain.domain = '%d'
          OR domain.domain IN (
            SELECT target_domain FROM alias_domain
            WHERE alias_domain = '%d'
          )
        )
      ),
      (SELECT id FROM relayhosts
      LEFT OUTER JOIN mailbox ON JSON_UNQUOTE(JSON_VALUE(mailbox.attributes, '$.relayhost')) = relayhosts.id
      WHERE relayhosts.active = '1'
        AND (
          mailbox.username IN (
            SELECT alias.goto from alias
              JOIN mailbox ON mailbox.username = alias.goto
                WHERE alias.active = '1'
                  AND alias.address = '%s'
                  AND alias.address NOT LIKE '@%%'
          )
        )
      )
    )
  )
  AND active = '1'
  AND username != '';
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_sasl_passwd_maps_transport_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT CONCAT_WS(':', username, password) AS auth_data FROM transports
  WHERE nexthop = '%s'
  AND active = '1'
  AND username != ''
  LIMIT 1;
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_virtual_alias_domain_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT username FROM mailbox, alias_domain
  WHERE alias_domain.alias_domain = '%d'
    AND mailbox.username = CONCAT('%u', '@', alias_domain.target_domain)
    AND (mailbox.active = '1' OR mailbox.active = '2')
    AND alias_domain.active='1'
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_virtual_alias_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT goto FROM alias
  WHERE address='%s'
    AND (active='1' OR active='2');
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_recipient_bcc_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT bcc_dest FROM bcc_maps
  WHERE local_dest='%s'
    AND type='rcpt'
    AND active='1';
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_sender_bcc_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT bcc_dest FROM bcc_maps
  WHERE local_dest='%s'
    AND type='sender'
    AND active='1';
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_recipient_canonical_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT new_dest FROM recipient_maps
  WHERE old_dest='%s'
    AND active='1';
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_virtual_domains_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT alias_domain from alias_domain WHERE alias_domain='%s' AND active='1'
  UNION
  SELECT domain FROM domain
    WHERE domain='%s'
      AND active = '1'
      AND backupmx = '0'
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_virtual_mailbox_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT CONCAT(JSON_UNQUOTE(JSON_VALUE(attributes, '$.mailbox_format')), mailbox_path_prefix, '%d/%u/') FROM mailbox WHERE username='%s' AND (active = '1' OR active = '2')
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_virtual_relay_domain_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT domain FROM domain WHERE domain='%s' AND backupmx = '1' AND active = '1'
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_virtual_sender_acl.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
# First select queries domain and alias_domain to determine if domains are active.
query = SELECT goto FROM alias
  WHERE id IN (
      SELECT COALESCE (
        (
          SELECT id FROM alias
            WHERE address='%s'
            AND (active='1' OR active='2')
        ), (
          SELECT id FROM alias
            WHERE address='@%d'
            AND (active='1' OR active='2')
        )
      )
    )
    AND active='1'
    AND (domain IN
      (SELECT domain FROM domain
        WHERE domain='%d'
          AND active='1')
      OR domain in (
        SELECT alias_domain FROM alias_domain
          WHERE alias_domain='%d'
            AND active='1'
      )
    )
  UNION
  SELECT logged_in_as FROM sender_acl
    WHERE send_as='@%d'
      OR send_as='%s'
      OR send_as='*'
      OR send_as IN (
        SELECT CONCAT('@',target_domain) FROM alias_domain
          WHERE alias_domain = '%d')
      OR send_as IN (
        SELECT CONCAT('%u','@',target_domain) FROM alias_domain
          WHERE alias_domain = '%d')
      AND logged_in_as NOT IN (
        SELECT goto FROM alias
          WHERE address='%s')
  UNION
  SELECT username FROM mailbox, alias_domain
    WHERE alias_domain.alias_domain = '%d'
      AND mailbox.username = CONCAT('%u','@',alias_domain.target_domain)
      AND (mailbox.active = '1' OR mailbox.active ='2')
      AND alias_domain.active='1';
EOF

# MX based routing
cat <<EOF > /opt/postfix/conf/sql/mysql_mbr_access_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT CONCAT('FILTER smtp_via_transport_maps:', nexthop) as transport FROM transports
  WHERE '%s' REGEXP destination
    AND active='1'
    AND is_mx_based='1';
EOF

cat <<EOF > /opt/postfix/conf/sql/mysql_virtual_spamalias_maps.cf
# Autogenerated by mailcow
user = ${DBUSER}
password = ${DBPASS}
hosts = unix:/var/run/mysqld/mysqld.sock
dbname = ${DBNAME}
query = SELECT goto FROM spamalias
  WHERE address='%s'
    AND validity >= UNIX_TIMESTAMP()
EOF

if [ ! -f /opt/postfix/conf/dns_blocklists.cf ]; then
  cat <<EOF > /opt/postfix/conf/dns_blocklists.cf
# This file can be edited. 
# Delete this file and restart postfix container to revert any changes.
postscreen_dnsbl_sites = wl.mailspike.net=127.0.0.[18;19;20]*-2
  hostkarma.junkemailfilter.com=127.0.0.1*-2
  list.dnswl.org=127.0.[0..255].0*-2
  list.dnswl.org=127.0.[0..255].1*-4
  list.dnswl.org=127.0.[0..255].2*-6
  list.dnswl.org=127.0.[0..255].3*-8
  ix.dnsbl.manitu.net*2
  bl.spamcop.net*2
  bl.suomispam.net*2
  hostkarma.junkemailfilter.com=127.0.0.2*3
  hostkarma.junkemailfilter.com=127.0.0.4*2
  hostkarma.junkemailfilter.com=127.0.1.2*1
  backscatter.spameatingmonkey.net*2
  bl.ipv6.spameatingmonkey.net*2
  bl.spameatingmonkey.net*2
  b.barracudacentral.org=127.0.0.2*7
  bl.mailspike.net=127.0.0.2*5
  bl.mailspike.net=127.0.0.[10;11;12]*4
  dnsbl.sorbs.net=127.0.0.10*8
  dnsbl.sorbs.net=127.0.0.5*6
  dnsbl.sorbs.net=127.0.0.7*3
  dnsbl.sorbs.net=127.0.0.8*2
  dnsbl.sorbs.net=127.0.0.6*2
  dnsbl.sorbs.net=127.0.0.9*2
EOF
fi
DNSBL_CONFIG=$(grep -v '^#' /opt/postfix/conf/dns_blocklists.cf | grep '\S')

if [ ! -z "$DNSBL_CONFIG" ]; then
  echo -e "\e[33mChecking if ASN for your IP is listed for Spamhaus Bad ASN List...\e[0m"
  if [ -n "$SPAMHAUS_DQS_KEY" ]; then
    echo -e "\e[32mDetected SPAMHAUS_DQS_KEY variable from mailcow.conf...\e[0m"
    echo -e "\e[33mUsing DQS Blocklists from Spamhaus!\e[0m"
    SPAMHAUS_DNSBL_CONFIG=$(cat <<EOF
  ${SPAMHAUS_DQS_KEY}.zen.dq.spamhaus.net=127.0.0.[4..7]*6
  ${SPAMHAUS_DQS_KEY}.zen.dq.spamhaus.net=127.0.0.[10;11]*8
  ${SPAMHAUS_DQS_KEY}.zen.dq.spamhaus.net=127.0.0.3*4
  ${SPAMHAUS_DQS_KEY}.zen.dq.spamhaus.net=127.0.0.2*3
postscreen_dnsbl_reply_map = texthash:/opt/postfix/conf/dnsbl_reply.map
EOF

  cat <<EOF > /opt/postfix/conf/dnsbl_reply.map
# Autogenerated by mailcow, using Spamhaus DQS reply domains
${SPAMHAUS_DQS_KEY}.sbl.dq.spamhaus.net     sbl.spamhaus.org
${SPAMHAUS_DQS_KEY}.xbl.dq.spamhaus.net     xbl.spamhaus.org
${SPAMHAUS_DQS_KEY}.pbl.dq.spamhaus.net     pbl.spamhaus.org
${SPAMHAUS_DQS_KEY}.zen.dq.spamhaus.net     zen.spamhaus.org
${SPAMHAUS_DQS_KEY}.dbl.dq.spamhaus.net     dbl.spamhaus.org
${SPAMHAUS_DQS_KEY}.zrd.dq.spamhaus.net     zrd.spamhaus.org
EOF
    )
  else
    if [ -f "/opt/postfix/conf/dnsbl_reply.map" ]; then
      rm /opt/postfix/conf/dnsbl_reply.map
    fi
    response=$(curl --connect-timeout 15 --max-time 30 -s -o /dev/null -w "%{http_code}" "https://asn-check.mailcow.email")
    if [ "$response" -eq 503 ]; then
      echo -e "\e[31mThe AS of your IP is listed as a banned AS from Spamhaus!\e[0m"
      echo -e "\e[33mNo SPAMHAUS_DQS_KEY found... Skipping Spamhaus blocklists entirely!\e[0m"
      SPAMHAUS_DNSBL_CONFIG=""
    elif [ "$response" -eq 200 ]; then
      echo -e "\e[32mThe AS of your IP is NOT listed as a banned AS from Spamhaus!\e[0m"
      echo -e "\e[33mUsing the open Spamhaus blocklists.\e[0m"
      SPAMHAUS_DNSBL_CONFIG=$(cat <<EOF
  zen.spamhaus.org=127.0.0.[10;11]*8
  zen.spamhaus.org=127.0.0.[4..7]*6
  zen.spamhaus.org=127.0.0.3*4
  zen.spamhaus.org=127.0.0.2*3
EOF
      )

    else
      echo -e "\e[31mWe couldn't determine your AS... (maybe DNS/Network issue?) Response Code: $response\e[0m"
      echo -e "\e[33mDeactivating Spamhaus DNS Blocklists to be on the safe site!\e[0m"
      SPAMHAUS_DNSBL_CONFIG=""
    fi
  fi
fi

# Reset main.cf
sed -i '/Overrides/q' /opt/postfix/conf/main.cf
echo >> /opt/postfix/conf/main.cf
# Append postscreen dnsbl sites to main.cf
if [ ! -z "$DNSBL_CONFIG" ]; then
  echo -e "${DNSBL_CONFIG}\n${SPAMHAUS_DNSBL_CONFIG}" >> /opt/postfix/conf/main.cf
fi
# Append user overrides
echo -e "\n# User Overrides" >> /opt/postfix/conf/main.cf
touch /opt/postfix/conf/extra.cf
sed -i '/\$myhostname/! { /myhostname/d }' /opt/postfix/conf/extra.cf
echo -e "myhostname = ${MAILCOW_HOSTNAME}\n$(cat /opt/postfix/conf/extra.cf)" > /opt/postfix/conf/extra.cf
cat /opt/postfix/conf/extra.cf >> /opt/postfix/conf/main.cf

if [ ! -f /opt/postfix/conf/custom_transport.pcre ]; then
  echo "Creating dummy custom_transport.pcre"
  touch /opt/postfix/conf/custom_transport.pcre
fi

if [[ ! -f /opt/postfix/conf/custom_postscreen_whitelist.cidr ]]; then
  echo "Creating dummy custom_postscreen_whitelist.cidr"
  cat <<EOF > /opt/postfix/conf/custom_postscreen_whitelist.cidr
# Autogenerated by mailcow
# Rules are evaluated in the order as specified.
# Blacklist 192.168.* except 192.168.0.1.
# 192.168.0.1          permit
# 192.168.0.0/16       reject
EOF
fi

# Fix Postfix permissions
chown -R root:postfix /opt/postfix/conf/sql/ /opt/postfix/conf/custom_transport.pcre
chmod 640 /opt/postfix/conf/sql/*.cf /opt/postfix/conf/custom_transport.pcre
chgrp -R postdrop /var/spool/postfix/public
chgrp -R postdrop /var/spool/postfix/maildrop
postfix set-permissions

# Check Postfix configuration
postconf -c /opt/postfix/conf > /dev/null

if [[ $? != 0 ]]; then
  echo "Postfix configuration error, refusing to start."
  exit 1
else
  postfix -c /opt/postfix/conf start
  sleep 126144000
fi
