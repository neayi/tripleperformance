#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

docker cp $DIR/.mysql.cnf tripleperformance_prod-db-1:/etc/mysql/conf.d/mysqlpassword.cnf

for DB in $(docker exec tripleperformance_prod-db-1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -s -e "SHOW DATABASES" --skip-column-names); do
    docker exec tripleperformance_prod-db-1 /usr/bin/mysqldump --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf --single-transaction $DB -u root | gzip > $DIR/DBs/$DB-$(date +%Y%m%d).sql.gz
done

docker exec tripleperformance_prod-db-1 rm /etc/mysql/conf.d/mysqlpassword.cnf

rsync -va $DIR/DBs/*-$(date +%Y%m%d).sql.gz neayi.com:~/backup
rsync -va $DIR/../.data/images neayi.com:~/backup
rsync -va $DIR/../.data/insights_prod_storage neayi.com:~/backup
rsync -va /var/www/discourse/shared/standalone/backups neayi.com:~/discourse_backup/
rsync -va /var/www/discourse/containers neayi.com:~/discourse_backup/
rsync -va /var/www/tripleperformance_docker/piwigo/config/www/_data/i/upload neayi.com:~/piwigo_backup/
scp $DIR/.env $DIR/.env.preprod neayi.com:~/backup/
scp $DIR/insights/.env neayi.com:~/backup/.env.insights

find $DIR/DBs -name "*.gz" -type f -mtime +10 -exec rm -f {} \;
