#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

docker cp $DIR/.mysql.cnf tripleperformance_db_1:/etc/mysql/conf.d/mysqlpassword.cnf

for DB in $(docker exec tripleperformance_db_1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -s -e "SHOW DATABASES" --skip-column-names); do
    docker exec tripleperformance_db_1 /usr/bin/mysqldump --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf --single-transaction $DB -u root | gzip > $DIR/DBs/$DB-$(date +%Y%m%d).sql.gz
done

docker exec tripleperformance_db_1 rm /etc/mysql/conf.d/mysqlpassword.cnf

scp -i $DIR/ssh.cluster026.hosting.ovh.net.key $DIR/DBs/*-$(date +%Y%m%d).sql.gz neayicomwl@ssh.cluster026.hosting.ovh.net:~/backup
scp -ri $DIR/ssh.cluster026.hosting.ovh.net.key $DIR/../.data/images neayicomwl@ssh.cluster026.hosting.ovh.net:~/backup

find $DIR/DBs -name "*.gz" -type f -mtime +10 -exec rm -f {} \;
