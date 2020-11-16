#!/bin/bash

docker cp /var/www/tripleperformance/backup/.mysql.cnf tripleperformance_db_1:/etc/mysql/conf.d/mysqlpassword.cnf

for DB in $(docker exec tripleperformance_db_1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -s -e "SHOW DATABASES" --skip-column-names); do
    docker exec tripleperformance_db_1 /usr/bin/mysqldump --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf --single-transaction $DB -u root | gzip > /var/www/tripleperformance/backup/DBs/$DB-$(date +%Y%m%d).sql.gz
done

docker exec tripleperformance_db_1 rm /etc/mysql/conf.d/mysqlpassword.cnf

scp -i /var/www/tripleperformance/backup/ssh.cluster026.hosting.ovh.net.key /var/www/tripleperformance/backup/DBs/*-$(date +%Y%m%d).sql.gz neayicomwl@ssh.cluster026.hosting.ovh.net:~/backup
scp -ri /var/www/tripleperformance/backup/ssh.cluster026.hosting.ovh.net.key /var/www/tripleperformance/html/images neayicomwl@ssh.cluster026.hosting.ovh.net:~/backup

find /var/www/tripleperformance/backup/DBs -name "*.gz" -type f -mtime +10 -exec rm -f {} \;
