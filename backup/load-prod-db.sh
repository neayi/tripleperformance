docker cp .mysql.cnf tripleperformance_db_1:/etc/mysql/conf.d/mysqlpassword.cnf

docker exec -i tripleperformance_db_1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -D q2a_prod < q2a_prod.sql
docker exec -i tripleperformance_db_1 /usr/bin/mysql --defaults-extra-file=/etc/mysql/conf.d/mysqlpassword.cnf -u root -D wiki_prod < wiki_prod.sql

docker exec tripleperformance_db_1 rm /etc/mysql/conf.d/mysqlpassword.cnf
