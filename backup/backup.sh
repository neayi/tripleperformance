#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

docker cp $DIR/.mysql.cnf tripleperformance_prod_db_1:/etc/mysql/conf.d/mysqlpassword.cnf

for DB in $(docker exec tripleperformance_prod_db_1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -s -e "SHOW DATABASES" --skip-column-names); do
    docker exec tripleperformance_prod_db_1 /usr/bin/mysqldump --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf --single-transaction $DB -u root | gzip > $DIR/DBs/$DB-$(date +%Y%m%d).sql.gz
done

docker exec tripleperformance_prod_db_1 rm /etc/mysql/conf.d/mysqlpassword.cnf

scp -qi $DIR/ssh.cluster026.hosting.ovh.net.key $DIR/DBs/*-$(date +%Y%m%d).sql.gz wkgv4271@neayi.com:~/backup
scp -rqi $DIR/ssh.cluster026.hosting.ovh.net.key $DIR/../.data/images wkgv4271@neayi.com:~/backup

find $DIR/DBs -name "*.gz" -type f -mtime +10 -exec rm -f {} \;
