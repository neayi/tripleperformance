FROM php:8.2-apache AS base

ARG DEBUG_TOOLS

RUN <<EOF
apt-get update
apt-get remove -y exim
apt-get install -y --no-install-recommends libpng-dev
apt-get install -y --no-install-recommends aptitude
apt-get install -y --no-install-recommends git
apt-get install -y --no-install-recommends dnsutils
apt-get install -y --no-install-recommends unzip
apt-get install -y --no-install-recommends graphicsmagick-imagemagick-compat
apt-get install -y --no-install-recommends gsfonts
apt-get install -y --no-install-recommends libfreetype6-dev
apt-get install -y --no-install-recommends libjpeg62-turbo-dev
apt-get install -y --no-install-recommends libmcrypt-dev
apt-get install -y --no-install-recommends libbz2-dev
apt-get install -y --no-install-recommends libgmp-dev
apt-get install -y --no-install-recommends libicu-dev
apt-get install -y --no-install-recommends libzip-dev
apt-get install -y --no-install-recommends libc-client-dev
apt-get install -y --no-install-recommends libkrb5-dev
apt-get install -y --no-install-recommends libpspell-dev
apt-get install -y --no-install-recommends libmagickwand-dev
apt-get install -y --no-install-recommends libonig-dev
apt-get install -y --no-install-recommends libxslt-dev
apt-get install -y --no-install-recommends ghostscript
apt-get install -y --no-install-recommends poppler-utils
apt-get install -y --no-install-recommends cron
apt-get install -y --no-install-recommends supervisor
apt-get install -y --no-install-recommends vim
apt-get install -y --no-install-recommends msmtp
apt-get install -y --no-install-recommends ssh
rm -rf /var/lib/apt/lists/*
docker-php-ext-configure gd --with-freetype --with-jpeg
docker-php-ext-install -j$(nproc) gd
docker-php-ext-install pdo_mysql
docker-php-ext-install mysqli
docker-php-ext-install zip
docker-php-ext-install exif
docker-php-ext-install bz2
docker-php-ext-install calendar
docker-php-ext-install bcmath
docker-php-ext-install gettext
docker-php-ext-install intl
docker-php-ext-install pdo
docker-php-ext-install iconv
docker-php-ext-install xsl
docker-php-source delete
pecl install imagick apcu igbinary
docker-php-ext-enable imagick apcu igbinary
pecl install --configureoptions 'enable-redis-igbinary="yes" enable-redis-lzf="no" enable-redis-zstd="no" enable-redis-msgpack="no" enable-redis-lz4="no" with-liblz4="no"' -o -f redis
rm -rf /tmp/pear
docker-php-ext-enable redis
EOF

RUN a2enmod rewrite headers deflate remoteip

RUN test -z "$DEBUG_TOOLS" || ( \
        pecl install xdebug-3.2.2 \
            && docker-php-ext-enable xdebug \
            && echo "xdebug.discover_client_host=on" >> /usr/local/etc/php/conf.d/xdebug.ini \
            && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/xdebug.ini \
            && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/xdebug.ini \
            && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/xdebug.ini \
            && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/xdebug.ini \
            && echo "xdebug.idekey=VSCODE" >> /usr/local/etc/php/conf.d/xdebug.ini \
            && echo "log_errors = On" >> /usr/local/etc/php/conf.d/error_log.ini \
            && echo "error_log = /dev/stderr" >> /usr/local/etc/php/conf.d/error_log.ini \
    )

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY engine/php_server/apache/000-default.conf /etc/apache2/sites-enabled/000-default.conf
COPY engine/php_server/apache/apache.prod.conf /etc/apache2/conf-available/z-app.conf
RUN a2enconf z-app

COPY <<EOF /usr/local/etc/php/conf.d/sendmail.ini
sendmail_path = /usr/bin/msmtp -t
EOF

COPY <<EOF /usr/local/etc/php/conf.d/errorlog.ini
log_errors=1
error_log=/dev/stderr
display_errors=0
EOF

# Setup msmtp with mailtrap
COPY engine/php_server/msmtp/msmtprc.mailtrap /etc/msmtprc
RUN <<EOF
chmod 600 /etc/msmtprc
chown www-data /etc/msmtprc
ln -s /dev/stdout /var/log/msmtp.log
EOF

WORKDIR /var/www

# ================================================

FROM base AS prod

# Setup msmtp with sendgrid
COPY engine/php_server/msmtp/msmtprc /etc/msmtprc
RUN <<EOF
chmod 600 /etc/msmtprc
chown www-data /etc/msmtprc
EOF

# ================================================

FROM prod AS prod_sources

RUN rm -r /var/www/html

COPY --chown=www-data:www-data bin bin/
COPY --chown=www-data:www-data config config/
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN php bin/build_project.php --create-env
