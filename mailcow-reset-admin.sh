#/bin/bash
if [[ ! -f mailcow.conf ]]; then
        echo "Cannot find mailcow.conf, make sure this script is run from within the mailcow folder."
        exit 1
fi

echo -n "Checking MySQL service... "
docker-compose ps -q mysql-mailcow > /dev/null 2>&1

if [[ $? -ne 0 ]]; then
        echo "failed"
        echo "MySQL (mysql-mailcow) is not up and running, exiting..."
        exit 1
fi

echo "OK"
read -r -p "Are you sure you want to reset the mailcow administrator account? [y/N] " response
response=${response,,}    # tolower
if [[ "$response" =~ ^(yes|y)$ ]]; then
        echo -e "\nWorking, please wait..."
        source mailcow.conf
        docker-compose exec -T mysql-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME} -e "DELETE FROM admin;"
        docker-compose exec -T mysql-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME} -e "INSERT INTO admin (username, password, superadmin, created, modified, active) VALUES ('admin', '{SSHA256}K8eVJ6YsZbQCfuJvSUbaQRLr0HPLz5rC9IAp0PAFl0tmNDBkMDc0NDAyOTAxN2Rk', 1, NOW(), NOW(), 1);"
        docker-compose exec -T mysql-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME} -e "DELETE FROM domain_admins WHERE username='admin';"
        docker-compose exec -T mysql-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME} -e "INSERT INTO domain_admins (username, domain, created, active) VALUES ('admin', 'ALL', NOW(), 1);"
        docker-compose exec -T mysql-mailcow mysql -u${DBUSER} -p${DBPASS} ${DBNAME} -e "DELETE FROM tfa WHERE username='admin';"
        echo "
Reset credentials:
---
Username: admin
Password: moohoo
TFA: none
"
else
        echo "Operation canceled."
fi
