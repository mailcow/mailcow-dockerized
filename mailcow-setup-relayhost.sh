#/bin/bash
if [[ ! -f mailcow.conf ]]; then
        echo "Cannot find mailcow.conf, make sure this script is run from within the mailcow folder."
        exit 1
fi

echo -n "Checking Postfix service... "
docker-compose ps -q postfix-mailcow > /dev/null 2>&1

if [[ $? -ne 0 ]]; then
        echo "failed"
        echo "Postfix (postifx-mailcow) is not up and running, exiting..."
        exit 1
fi

echo "OK"

if [[ -z ${1} ]]; then
    echo "Usage:"
	echo
	echo "Setup a relayhost:"
	echo "${0} relayhost port (username) (password)"
    echo "Username and password are optional parameters."
	echo
	echo "Reset to defaults:"
	echo "${0} reset"
    exit 1
fi

if [[ ${1} == "reset" ]]; then
	# Reset modified values to their defaults
	sed -i "s/^relayhost\ \=.*/relayhost\ \=/" data/conf/postfix/main.cf
	sed -i "s/^smtp\_sasl\_password\_maps.*/smtp\_sasl\_password\_maps\ \=/" data/conf/postfix/main.cf
	sed -i "s/^smtp\_sasl\_security\_options.*/smtp\_sasl\_security\_options\ \=\ noplaintext\,\ noanonymous/" data/conf/postfix/main.cf
	sed -i "s/^smtp\_sasl\_auth\_enable.*/smtp\_sasl\_auth\_enable\ \=\ no/" data/conf/postfix/main.cf
	# Also delete the plaintext password file
	rm -f data/conf/postfix/smarthost_passwd*
	docker-compose exec postfix-mailcow postfix reload
	# Exit with dc exit code
	exit $?
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
		sed -i "s/^smtp\_sasl\_password\_maps.*/smtp\_sasl\_password\_maps\ \=\ hash\:\/opt\/postfix\/conf\/smarthost\_passwd/" data/conf/postfix/main.cf
	else
		echo "smtp_sasl_password_maps = hash:/opt/postfix/conf/smarthost_passwd" >>  data/conf/postfix/main.cf
	fi
	if grep -q "smtp_sasl_auth_enable" data/conf/postfix/main.cf
	then
		sed -i "s/^smtp\_sasl\_auth\_enable.*/smtp\_sasl\_auth\_enable\ \=\ yes/" data/conf/postfix/main.cf
	else
		echo "smtp_sasl_auth_enable = yes" >>  data/conf/postfix/main.cf
	fi
	if grep -q "smtp_sasl_security_options" data/conf/postfix/main.cf
	then
		sed -i "s/^smtp\_sasl\_security\_options.*/smtp\_sasl\_security\_options\ \=/" data/conf/postfix/main.cf
	else
		echo "smtp_sasl_security_options =" >>  data/conf/postfix/main.cf
	fi
	if [[ ! -z ${3} ]]; then
		echo ${1} ${3}:${4} > data/conf/postfix/smarthost_passwd
		docker-compose exec postfix-mailcow postmap /opt/postfix/conf/smarthost_passwd
	fi
	docker-compose exec postfix-mailcow chown root:postfix /opt/postfix/conf/smarthost_passwd /opt/postfix/conf/smarthost_passwd.db
	docker-compose exec postfix-mailcow chmod 660 /opt/postfix/conf/smarthost_passwd /opt/postfix/conf/smarthost_passwd.db
	docker-compose exec postfix-mailcow postfix reload
	exit $?
fi
