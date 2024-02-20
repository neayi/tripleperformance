![build](https://github.com/neayi/tripleperformance/workflows/build/badge.svg)

# tripleperformance

Cette brique permet de versionner :
* l'environnement docker, y compris les fichiers de conf apache, sql, les procédures de création de certificats, les backups, etc....
* le script de création de l'environnement de dev, preprod et prod
* les confs des différentes briques

## Utilisation
### Installation :

    git clone git@github.com:neayi/tripleperformance.git
    cd tripleperformance
    git clone git@github.com:neayi/insights.git insights

### Mise à jour :

    git pull

--> Attention, quand on fait un git pull, on met à jour en même temps les fichiers de conf et le script de mise à jour - potentiellement, les plateformes ne fonctionneront plus parce que leur configuration fait usage d'une extension qui n'est pas encore mise à jour tant qu'on n'aura pas lancé la commande `build_project.php`

### Configuration

Copier les fichiers `.env` et `docker-compose.override.yml`, et éventuellement en configurer les valeurs :

    cp .env.dev.dist .env
    cp insights/.env.example insights/.env
    cp docker-compose.override.yml.dist docker-compose.override.yml

    mkdir -p .cache/ssl .cache/composer

### Création des certificats de dev

    rm -rf .cache/ssl && mkdir .cache/ssl
    docker run --rm -v $(pwd):/src alpine:3.9 sh -c "/src/engine/traefik/dev/generate-certs.sh && chown -R $(id -u):$(id -g) /src/.cache/ssl"
    docker compose up -d --force-recreate traefik

Il est possible d'installer les certificats rapidement sous windows avec la commande PowerShell:

    & '.\engine\traefik\dev\install certificates.ps1'

Attention : après avoir créé les certificats, il faut absolument recréer le container de Traefik, qui monte ces certificats dans un volume :

    docker compose up -d --force-recreate traefik

### Installation des certificats dans les navigateurs

Sous Windows : installer d'abord le certificat `root` puis le certificat `server` ainsi créés dans `.cache/ssl` dans [l'autorité racine de confiance](https://docs.microsoft.com/en-us/dotnet/framework/wcf/feature-details/how-to-create-temporary-certificates-for-use-during-development) - en pratique il faut double cliquer sur les fichiers .crt, puis cliquer sur **Installer un certificat**, Ordinateur local, et choisir **Autorités de certification racines de confiance**

Pour installer le certificat dans Firefox, le plus simple est de faire en sorte que Firefox respecte les certificats racine de Windows en allant dans about:config, et en [changeant le réglage setting security.enterprise_roots.enabled à true](https://gist.github.com/cecilemuller/9492b848eb8fe46d462abeb26656c4f8).

Pour utiliser le certificat avec Chrome sous Linix, aller dans [chrome://settings/certificates](chrome://settings/certificates), cliquez sur "Authorities", et importez le certificat root.pem (NB : sous Windows, Chrome utilise les certificats de la machine).

Pour les autres OS ou navigateurs, [ajouter le certificat root au système](https://manuals.gfi.com/en/kerio/connect/content/server-configuration/ssl-certificates/adding-trusted-root-certificates-to-the-server-1605.html)
et redémarrez votre pc, au cas où.

Pour ubuntu, par exemple :
```bash
sudo cp .cache/ssl/root.crt /usr/local/share/ca-certificates
sudo update-ca-certificates
openssl verify .cache/ssl/server.pem
reboot
```

### Lancement de docker
* Dev, prod et preprod : `docker compose up --build -d`

### Création d'un mot de passe pour ElasticSearch
* Voir https://www.elastic.co/fr/blog/getting-started-with-elasticsearch-security
* En pratique :
1. Ouvrir `.env` et récupérer le mot de passe utilisé pour ELASTICSEARCH_SERVER
2. Se rendre dans le container : `docker compose exec elasticsearch bash`
3. Exécuter la commande : `bin/elasticsearch-setup-passwords interactive` et utiliser le mot de passe précédemment récupéré pour tous les users
4. Utiliser l'identifiant `elastic` pour ElasticVue (voir plus loin).

### Exécution d'un bash
* Web : `docker compose exec web bash`
* SQL : `docker compose exec db bash`
* Insights : `docker compose run --rm --user="$UID:$GID" insights_php bash` (ou `docker compose exec insights_php bash` en tant que root et si le service est up)

### Accès à phpMyAdmin
* Dev : http://phpmyadmin.dev.tripleperformance.fr
* Prod : http://phpmyadmin.tripleperformance.fr

### Accès à elasticVue
* Dev : https://elasticvue.dev.tripleperformance.fr/
* Prod : https://elasticvue.tripleperformance.fr/
* Utiliser comme url de connexion : https://elastic:xxxxxxxx@elasticsearch.tripleperformance.fr (mot de passe dans le fichier `.env` --> `ELASTICSEARCH_SERVER`)

### Restauration d'une base de données de backup

Utiliser wiki.php mysql :

    php wiki.php mysql backup/DBs/wiki_prod-20211116.sql

NB : il faudra créer un fichier `.mysql.cnf` dans le dossier backup avec le mot de passe root de la DB :

    cat > backup/.mysql.cnf
    [client]
    password=root

### Extraction du code

    php wiki.php build_project.php --create-env

En cas d'erreur, relancer la commande avec:

    php wiki.php build_project.php --update

Ces deux commandes font la création du dossier `/var/www/src`, dans lesquels on trouvera d'une part un dossier html (le web root de chacun des domaines) et un dossier html/extensions (les extensions et plugins utilisés dans notre setup).
Les fichiers de configuration sont aussi ajoutés via lien symbolique à partir du dossier /var/www/scripts/settings

### Création des bases de données
Une fois le code extrait, il faut ajouter la base de données.

Par défaut, la base créée sera `wiki`, mais il est possible d'importer aussi une base de prod en créant le fichier `bin/sql/wiki_prod.sql` avant de lancer le script.

    docker compose exec -w /var/sql db ./load_db.sh

### Import d'une base existante

    docker exec -i tripleperformance_db_1 mysql -u root -p<MYSQL_PASSWORD> wiki < $sql_file_path

### Installation de Insights

    docker compose run --rm --user="$UID:$GID" insights_php ./install.sh
    docker compose run --rm insights_php chmod -R o+w storage

### Ajout des images dans le wiki
Il faut aussi ajouter des images pour compléter la configuration. Ces images ne sont pas versionnées, voir avec un membre de l'équipe pour les récupérer d'une autre install.

### Indexation du wiki dans elasticSearch
Dans cette opération, on indexe les pages du wiki dans elasticSearch à partir de la DB :

    docker compose exec web php bin/build_project.php --initElasticSearch

On peut vérifier la bonne indexation en allant sur http://elasticvue.dev.tripleperformance.fr/ ou en pratiquant une recherche dans le wiki.

### Mise à jour du code
Quand on met à jour tripleperformance, il faut ensuite mettre à jour le code de chaque instance :

    docker compose exec web php bin/build_project.php --update

On ira ensuite mettre à jour spécifiquement le wiki :

    cd /var/www/html/maintenance
    php update.php
    php runJobs.php

### Configuration de VSCode et du débuggeur
* L'environnement de Dev a déjà XDebug en place. On peut vérifier avec php -i
* Installer l'extension Chrome XDebug Helper https://chrome.google.com/webstore/detail/xdebug-helper/eadndfjplgieldjbigjakmdgkmoaaaoc
* Configurer l'extension pour VSCode : Mettre comme clé d'IDE le mot clé `VSCODE`

### Création d'une image de production

    docker build -t wiki:latest -f engine/php_server/Dockerfile .
    docker build -t insights:latest -f insights/dockerfiles/php/Dockerfile insights

## Installer un serveur de production

    git clone git@github.com:neayi/tripleperformance.git
    cd tripleperformance
    git clone git@github.com:neayi/insights.git insights

    cp .env.prod.dist .env
    cp .env.preprod.dist .env.preprod
    cp insights/.env.example insights/.env
    cp insights/.env.example insights/.env.preprod

Modifier les fichiers .env, et en particulier vérifier la version des images à utiliser (INSIGHTS_VERSION_*, WIKI_VERSION_*)

    mkdir -p .data/elasticsearch && chmod o+w .data/elasticsearch
    mkdir -p .data/insights_prod_storage && chmod o+w .data/insights_prod_storage
    mkdir -p .data/insights_preprod_storage && chmod o+w .data/insights_preprod_storage
    touch .data/acme.json && chmod 600 .data/acme.json

Configurer les fichiers .env, puis :

    docker login -u bertrand.gorge@neayi.com -p $PAT docker.pkg.github.com
    docker compose -f docker-compose.prod.yml up -d

Avec `$PAT` un [Personal Access Token](https://github.com/settings/tokens) ayant les droits de lecture sur les packages github.


Lancer la migration d'Insights :

    docker compose -f docker-compose.prod.yml exec --user="www-data:www-data" insights php artisan migrate


# Tâches de maintenance diverses
## Mediawiki

Pour lancer runJobs.php

    docker compose -f docker-compose.prod.yml run --rm web sh -c "php /var/www/html/maintenance/runJobs.php"


Pour importer des images ou un fichier xml :

    docker compose -f docker-compose.prod.yml run --rm -v ~/wiki_builder/out/departements:/out web php /var/www/html/maintenance/importImages.php --user="ImportsTriplePerformance" /out/

    docker compose -f docker-compose.prod.yml run --rm -v ~/wiki_builder/out/departements:/out web php /var/www/html/maintenance/importDump.php --user="ImportsTriplePerformance" /out/wiki_departements.xml

    docker compose -f docker-compose.prod.yml run --rm web sh -c "php /var/www/html/maintenance/rebuildrecentchanges.php && php /var/www/html/maintenance/initSiteStats.php && php /var/www/html/maintenance/runJobs.php"


## Mysql
Pour importer une DB, utiliser la commande :

    docker compose -f docker-compose.prod.yml exec -T db mysql -u root --password=xxxxxx wiki < bin/sql/wiki.sql


# TODO
- Tous les logs doivent être envoyés sur STDOUT ou STDERR, pas dans un fichier
