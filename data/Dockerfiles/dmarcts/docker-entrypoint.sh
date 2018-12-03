if [[ "${USE_DMARCTS}" =~ ^([nN][oO]|[nN])+$ ]]; then
  echo -e "$(date) - USE_DMARCTS=n, skipping dmarcts..."
  sleep 365d
  exec $(readlink -f "$0")
fi

sed -ri "/^dbuser_placeholder/c\\\$dbuser='${DBUSER}';" /dmarcts-report-parser/dmarcts-report-parser.conf
sed -ri "/^dbpass_placeholder/c\\\$dbpass='${DBPASS}';" /dmarcts-report-parser/dmarcts-report-parser.conf
sed -ri "/^dbname_placeholder/c\\\$dbname='${DBNAME}';" /dmarcts-report-parser/dmarcts-report-parser.conf
sed -ri "/^imapuser_placeholder/c\\\$imapuser='${DMARCTS_IMAP_USER}';" /dmarcts-report-parser/dmarcts-report-parser.conf
sed -ri "/^imappassword_placeholder/c\\\$imappass='${DMARCTS_IMAP_PASSWORD}';" /dmarcts-report-parser/dmarcts-report-parser.conf

# TODO: This doesn't work very well. Maybe we can merge without these changes and add them later.
# Rename tables in perl script to make them more easily identifiable in the Mailcow database
# sed -ri "s/rptrecord/dmarcts_rptrecord/" /dmarcts-report-parser/dmarcts-report-parser.pl
# sed -ri "s/([FROM|INTO]) report/\1 dmarcts_report/i" /dmarcts-report-parser/dmarcts-report-parser.pl
# sed -ri "s/\"report\"/\"dmarcts_report\"/" /dmarcts-report-parser/dmarcts-report-parser.pl

# Fix for dmarcts-report-parser issue #59
# https://github.com/techsneeze/dmarcts-report-parser/pull/60/files
sed -ri "s/(\s+additional_definitions\s+=> \")PRIMARY KEY \(id\), /\1/g" /dmarcts-report-parser/dmarcts-report-parser.pl

sleep 15

while true
do
  ./dmarcts-report-parser/dmarcts-report-parser.pl -i
  sleep 1800
done; 