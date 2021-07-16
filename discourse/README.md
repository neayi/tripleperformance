# Tripleperformance Discourse

## Install

Start the tripleperformance stack, then update the `.env` file.

See [the bitnami variables doc](https://github.com/bitnami/bitnami-docker-discourse#environment-variables)
in order to get the list of available variables.

See [the bitnami smtp doc](https://github.com/bitnami/bitnami-docker-discourse#smtp-configuration)
in order to configure the smtp server.

```bash
cd discourse
docker-compose up -d

# Then wait for "INFO  discourse successfully initialized":
docker-compose logs -f discourse
```

## Adminer (PostgresSQL web ui)

Go to [http://forum-adminer.dev.tripleperformance.fr](http://forum-adminer.dev.tripleperformance.fr/?pgsql=postgresql&username=bn_discourse&db=bitnami_application&ns=public)
and use the values used into the `docker-compose.yml` file:
```text
Type: postgreSQL
Server: postgresql
User: bn_discourse
Password: bitnami1
DB: bitnami_application
```
