FROM php:7.4-apache AS base

ARG DEBUG_TOOLS

RUN apt-get update  \
  && apt-get remove -y exim \
  && apt-get install -y --no-install-recommends \
	libpng-dev \
	aptitude \
	git \
	dnsutils \
	unzip \
	graphicsmagick-imagemagick-compat \
	libfreetype6-dev \
	libjpeg62-turbo-dev \
	libmcrypt-dev \
	libbz2-dev \
	libgmp-dev \
	libzip-dev \
	libc-client-dev \
	libkrb5-dev \
	libpspell-dev \
	libmagickwand-dev \
	libonig-dev \
	libxslt-dev --no-install-recommends \
	ghostscript \
	poppler-utils \
    cron \
    supervisor \
    vim \
    msmtp \
	ssh \
	&& rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install mysqli \
    && docker-php-ext-install zip \
    && docker-php-ext-install exif \
    && docker-php-ext-install bz2 \
    && docker-php-ext-install calendar \
    && docker-php-ext-install bcmath \
    && docker-php-ext-install gettext \
    && docker-php-ext-install intl \
    && docker-php-ext-install pdo \
    && docker-php-ext-install iconv \
    && docker-php-ext-install xmlrpc \
    && docker-php-ext-install xsl \
    && docker-php-source delete

# Image magick
RUN pecl install imagick \
	&& docker-php-ext-enable imagick

RUN pecl install mcrypt-1.0.3 \
	&& docker-php-ext-enable mcrypt

RUN pecl install -o -f redis \
	&& rm -rf /tmp/pear \
	&& docker-php-ext-enable redis

RUN pecl install apcu \
  && docker-php-ext-enable apcu

RUN a2enmod rewrite headers deflate

RUN test -z "$DEBUG_TOOLS" || ( \
        pecl install xdebug \
            && docker-php-ext-enable xdebug \
            && echo "xdebug.discover_client_host=on" >> /usr/local/etc/php/conf.d/xdebug.ini \
            && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/xdebug.ini \
            && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/xdebug.ini \
            && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/xdebug.ini \
            && echo "xdebug.idekey=VSCODE" >> /usr/local/etc/php/conf.d/xdebug.ini \
            && echo "log_errors = On" >> /usr/local/etc/php/conf.d/error_log.ini \
            && echo "error_log = /dev/stderr" >> /usr/local/etc/php/conf.d/error_log.ini \
    )

RUN curl -sS https://getcomposer.org/installer | php -- --1 --install-dir=/usr/local/bin --filename=composer

COPY engine/php_server/apache/000-default.conf /etc/apache2/sites-enabled/000-default.conf
COPY engine/php_server/apache/apache.prod.conf /etc/apache2/conf-available/z-app.conf
RUN a2enconf z-app

COPY engine/php_server/php/sendmail.ini /usr/local/etc/php/conf.d/sendmail.ini
COPY engine/php_server/php/errorlog.ini /usr/local/etc/php/conf.d/errorlog.ini

# Setup msmtp with mailtrap
COPY engine/php_server/msmtp/msmtprc.mailtrap /etc/msmtprc
RUN chmod 600 /etc/msmtprc && chown www-data /etc/msmtprc
RUN ln -s /dev/stdout /var/log/msmtp.log
WORKDIR /var/www

# ================================================

FROM base AS prod

# Setup msmtp with sendgrid
COPY engine/php_server/msmtp/msmtprc /etc/msmtprc
RUN chmod 600 /etc/msmtprc && chown www-data /etc/msmtprc

# Add the cron jobs:
COPY engine/php_server/cron/jobs-cron /etc/cron.d/jobs-cron
RUN chmod 0644 /etc/cron.d/jobs-cron
RUN crontab /etc/cron.d/jobs-cron

# supervisord config file
RUN mkdir -p /etc/supervisor/conf.d/
COPY engine/php_server/supervisord/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint & cmd script
COPY engine/php_server/bin/start.sh /bin/start.sh

CMD /bin/start.sh

# ================================================

FROM prod AS prod_sources

RUN rm -r /var/www/html

COPY --chown=www-data:www-data bin bin/
COPY --chown=www-data:www-data config config/

RUN php bin/build_project.php --create-env
