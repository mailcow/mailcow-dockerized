#!/bin/bash

set -e

MAILHOST=$1

cd $(dirname $0)

wget -O integrator.tar.gz https://github.com/inverse-inc/sogo-integrator.tb31/archive/master.tar.gz
wget -O connector.tar.gz https://github.com/inverse-inc/sogo-connector.tb31/archive/master.tar.gz

mkdir -p integrator connector
tar --strip-components=1 -C integrator -xf integrator.tar.gz
tar --strip-components=1 -C connector -xf connector.tar.gz

# build custom integrator
while read DOMAIN; do
	echo "Building SOGo Integrator for $DOMAIN hosted on $MAILHOST"
	cd integrator
	echo > defaults/preferences/site.js
	mkdir -p custom/${DOMAIN}
	cp -r custom/sogo-demo/* custom/${DOMAIN}/
	sed -i "s/http:\/\/sogo-demo\.inverse\.ca/https:\/\/${MAILHOST}/g" custom/${DOMAIN}/chrome/content/extensions.rdf
	sed -i "s/plugins\/updates\.php[?]/thunderbird-plugins.php?domain=${DOMAIN}\&amp;/g" custom/${DOMAIN}/chrome/content/extensions.rdf
	echo 'pref("sogo-integrator.autocomplete.server.urlid", "'${DOMAIN}'");' > custom/${DOMAIN}/defaults/preferences/site.js
	echo 'pref("mail.collect_email_address_outgoing", false);' >> custom/${DOMAIN}/defaults/preferences/site.js
	sed -i 's/<\/Seq>/<li><Description em:id="sieve@mozdev.org" em:name="Sieve"\/><\/li><li><Description em:id="imap-acl@sirphreak.com" em:name="Imap-ACL-Extension"\/><\/li><\/Seq>/g' custom/${DOMAIN}/chrome/content/extensions.rdf
	make build=${DOMAIN}
	INTEGRATOR_VER=$(grep em:version install.rdf | awk -F '"' '{print $2}')
	cp sogo-integrator-*-${DOMAIN}.xpi ../sogo-integrator-${INTEGRATOR_VER}-${DOMAIN}.xpi
	cd ..
done

# build connector
cd connector
make
CONNECTOR_VER=$(grep em:version install.rdf | awk -F '"' '{print $2}')
cp sogo-connector-*.xpi ../sogo-connector-${CONNECTOR_VER}.xpi
cd ..

# download Sieve plugin
SIEVE_RELEASES=$(wget --header="Accept: application/vnd.github.v3+json" -qO - https://api.github.com/repos/thsmi/sieve/releases)
SIEVE_VER=$(echo "$SIEVE_RELEASES" | grep -o '"tag_name": *"[^"]*"' | head -n 1 | awk -F '"' '{print $4}')
SIEVE_URL=$(echo "$SIEVE_RELEASES" | grep -o '"browser_download_url": *"[^"]*"' | head -n 1 | awk -F '"' '{print $4}')
wget -O sieve-${SIEVE_VER}.xpi ${SIEVE_URL}
unset SIEVE_RELEASES

# download ACL plugin
IMAP_ACL_RELEASES=$(wget -qO - 'https://addons.mozilla.org/api/v3/addons/addon/176736')
IMAP_ACL_VER=$(echo "$IMAP_ACL_RELEASES" | grep -o '"version": *"[^"]*"' | head -n 1 | awk -F '"' '{print $4}')
IMAP_ACL_URL=$(echo "$IMAP_ACL_RELEASES" | grep -o '"url": *"[^"]*\.xpi' | head -n 1 | awk -F '"' '{print $4}')
wget -O imap_acl_extension-${IMAP_ACL_VER}-tb.xpi ${IMAP_ACL_URL}
unset IMAP_ACL_RELEASES

# update version file
echo "sogo-connector@inverse.ca;${CONNECTOR_VER};sogo-connector-${CONNECTOR_VER}.xpi" > version.csv
echo "sogo-integrator@inverse.ca;${INTEGRATOR_VER};sogo-integrator-${INTEGRATOR_VER}-__DOMAIN__.xpi" >> version.csv
echo "sieve@mozdev.org;${SIEVE_VER};sieve-${SIEVE_VER}.xpi" >> version.csv
echo "imap-acl@sirphreak.com;${IMAP_ACL_VER};imap_acl_extension-${IMAP_ACL_VER}-tb.xpi" >> version.csv

rm -rf connector integrator *.tar.gz
