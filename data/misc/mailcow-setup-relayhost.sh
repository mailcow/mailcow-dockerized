#!/bin/bash

# Postfix smtp_tls_security_level should be set to "may" to try an
# encrypted connection.

if [ "$EUID" -ne 0 ]
	then echo "Please run as root"
	exit 1
fi

# move into mailcow-dockerized base directory
cd ../../

if [[ ${1} == "reset" ]]; then
	# Reset modified values to their defaults
	sed -i "s/^relayhost\ \=.*/relayhost\ \=/" data/conf/postfix/main.cf
	sed -i "s/^smtp\_sasl\_password\_maps.*/smtp\_sasl\_password\_maps\ \=/" data/conf/postfix/main.cf
	sed -i "s/^smtp\_sasl\_security\_options.*/smtp\_sasl\_security\_options\ \=\ noplaintext\,\ noanonymous/" data/conf/postfix/main.cf
	sed -i "s/^smtp\_sasl\_auth\_enable.*/smtp\_sasl\_auth\_enable\ \=\ no/" data/conf/postfix/main.cf
	# Also delete the plaintext password file
	rm -f data/conf/postfix/smarthost_passwd*
	docker-compose exec postfix-mailcow postfix reload
	# Exit last exit code
	exit $?
elif [[ ${1} == "restore-string" ]]; then
	# Set parameter value of smtp_sasl_password_maps
	SMTPSASLPWDMAP="data/conf/postfix/smarthost_passwd"
	# Get parameter value of relayhost
	RELAYHOSTCFG=$(grep "relayhost\ =" data/conf/postfix/main.cf | awk '{print $3}')
	# Exit if empty/unset
	[[ -z ${RELAYHOSTCFG} ]] && exit 0
	# Replace ':' by ' ' (white space)
	RELAYHOSTCFG=${RELAYHOSTCFG//\:/ }
	# Replace '[' by '' (empty)
	RELAYHOSTCFG=${RELAYHOSTCFG//\[/}
	# Replace ']' by '' (empty) and create array of result
	RELAYHOSTCFGARR=(${RELAYHOSTCFG//\]/})
	# Get 'username:password' from SASL password maps
	# Grep relayhost without port and '[', ']' or ':' from SASL password map file without map type (e.g. 'hash:')
	USRPWD=$(grep ${RELAYHOSTCFGARR[0]} $SMTPSASLPWDMAP | awk {'print $2'})
	# Replace ':' by ' ' and create array of result
	USRPWDARR=(${USRPWD//:/ })
	# Echo script name, all values in RELAYHOSTCFGARR, first and second value in USRPWDARR
	# Why?
	# Host and port are required, so we can print the whole array RELAYHOSTCFGARR.
	# Password might be empty, so we print them separately.
	echo ${0} ${RELAYHOSTCFGARR[@]} \'${USRPWDARR[0]}\' \'${USRPWDARR[1]}\'
	exit 0
elif [[ -z ${1} ]] || [[ -z ${2} ]]; then
	# Exit with code 1 if host and port are missing
	echo "Usage: ${0} relayhost port (username) (password)"
	echo "Username and password are optional parameters."
	exit 1
else
	# Try a simple connection to host:port but don't recieve any data
	# Abort after 3 seconds
	if ! nc -z -v -w3 ${1} ${2} 2>/dev/null; then
		echo "Connection to relayhost ${1} failed, aborting..."
		exit 1
	fi
	# Use exact hostname as relayhost, don't lookup the MX record of relayhost
	sed -i "s/relayhost\ \=.*/relayhost\ \=\ \[${1}\]\:${2}/" data/conf/postfix/main.cf
	if grep -q "smtp_sasl_password_maps" data/conf/postfix/main.cf
	then
		sed -i "s/^smtp\_sasl\_password\_maps.*/smtp_sasl\_password\_maps\ \=\ hash\:\/opt\/postfix\/conf\/smarthost\_passwd/" data/conf/postfix/main.cf
	else
		echo "smtp_sasl_password_maps = hash:/opt/postfix/conf/smarthost_passwd" >>  data/conf/postfix/main.cf
	fi
	if grep -q "smtp_sasl_auth_enable" data/conf/postfix/main.cf
	then
		sed -i "s/^smtp\_sasl\_auth\_enable.*/smtp\_sasl\_auth\_enable\ \=\ yes/" data/conf/postfix/main.cf
	else
		echo "smtp_sasl_auth_enable = yes" >>  data/conf/postfix/main.cf
	fi
	docker-compose exec postfix-mailcow postconf -e "smtp_sasl_password_maps = hash:/opt/postfix/conf/smarthost_passwd"
	# We can use anonymous and plain-text authentication, too (be warned)
	docker-compose exec postfix-mailcow postconf -e "smtp_sasl_security_options = "
	docker-compose exec postfix-mailcow postconf -e "smtp_sasl_auth_enable = yes"
	if [[ ! -z ${3} ]]; then
		echo ${1} ${3}:${4} > data/conf/postfix/smarthost_passwd
		docker-compose exec postfix-mailcow postmap /opt/postfix/conf/smarthost_passwd
	fi
	docker-compose exec postfix-mailcow chown root:postfix /opt/postfix/conf/smarthost_passwd /opt/postfix/conf/smarthost_passwd.db
	docker-compose exec postfix-mailcow chmod 660 /opt/postfix/conf/smarthost_passwd /opt/postfix/conf/smarthost_passwd.db
	docker-compose exec postfix-mailcow postfix reload
	exit $?
fi
