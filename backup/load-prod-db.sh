docker cp .mysql.cnf tripleperformance_prod-db-1:/etc/mysql/conf.d/mysqlpassword.cnf

docker exec -i tripleperformance_prod-db-1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -D insights_prod < insights.sql
docker exec -i tripleperformance_prod-db-1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -D insights_preprod < insights.sql

docker exec -i tripleperformance_prod-db-1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -D wiki_prod < wiki.sql
docker exec -i tripleperformance_prod-db-1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -D wiki_preprod < wiki.sql

docker exec -i tripleperformance_prod-db-1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -D matomo < matomo.sql
docker exec -i tripleperformance_prod-db-1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -D piwigo < piwigo.sql

docker exec  tripleperformance_prod-db-1 rm /etc/mysql/conf.d/mysqlpassword.cnf