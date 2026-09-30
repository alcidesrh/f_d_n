# the different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target


# https://docs.docker.com/engine/reference/builder/#understand-how-arg-and-from-interact
ARG PHP_VERSION=8.1
ARG CADDY_VERSION=2.11

# Prod image
FROM php:${PHP_VERSION}-fpm-alpine3.16 AS app_php

# Allow to use development versions of Symfony
ARG STABILITY="stable"
ENV STABILITY=${STABILITY}

# Allow to select Symfony version
ARG SYMFONY_VERSION=""
ENV SYMFONY_VERSION=${SYMFONY_VERSION}

ENV APP_ENV=prod

WORKDIR /srv/app

# persistent / runtime deps
RUN apk add --no-cache \
	acl \
	fcgi \
	file \
	gettext \
	git \
	make \
	;

RUN set -eux; \
	apk add --no-cache --virtual .build-deps \
	$PHPIZE_DEPS \
	icu-data-full \
	icu-dev \
	libzip-dev \
	zlib-dev \
	; \
	\
	docker-php-ext-configure zip; \
	docker-php-ext-install -j$(nproc) \
	intl \
	zip \
	; \
	pecl install \
	apcu \
	; \
	pecl clear-cache; \
	docker-php-ext-enable \
	apcu \
	opcache \
	; \
	\
	runDeps="$( \
	scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
	| tr ',' '\n' \
	| sort -u \
	| awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
	)"; \
	apk add --no-cache --virtual .app-phpexts-rundeps $runDeps; \
	\
	apk del .build-deps

###> recipes ###
###> doctrine/doctrine-bundle ###
RUN apk add --no-cache --virtual .pgsql-deps postgresql-dev; \
	docker-php-ext-install -j$(nproc) pdo_pgsql; \
	apk add --no-cache --virtual .pgsql-rundeps so:libpq.so.5; \
	apk del .pgsql-deps
###< doctrine/doctrine-bundle ###
###< recipes ###

###>sql server drivers
RUN apk add gcc make g++ zlib-dev unixodbc-dev php8-dev gnupg

RUN  curl -SL https://download.microsoft.com/download/b/9/f/b9f3cce4-3925-46d4-9f46-da08869c6486/msodbcsql18_18.0.1.1-1_amd64.apk >> /srv/app/msodbcsql18_18.0.1.1-1_amd64.apk \
	&& curl -SL https://download.microsoft.com/download/b/9/f/b9f3cce4-3925-46d4-9f46-da08869c6486/mssql-tools18_18.0.1.1-1_amd64.apk >> /srv/app/mssql-tools18_18.0.1.1-1_amd64.apk \
	&& curl -SL https://download.microsoft.com/download/b/9/f/b9f3cce4-3925-46d4-9f46-da08869c6486/msodbcsql18_18.0.1.1-1_amd64.sig >> /srv/app/msodbcsql18_18.0.1.1-1_amd64.sig \
	&& curl -SL https://download.microsoft.com/download/b/9/f/b9f3cce4-3925-46d4-9f46-da08869c6486/mssql-tools18_18.0.1.1-1_amd64.sig >> /srv/app/mssql-tools18_18.0.1.1-1_amd64.sig


RUN curl https://packages.microsoft.com/keys/microsoft.asc  | gpg --import -; \
	gpg --verify msodbcsql18_18.0.1.1-1_amd64.sig msodbcsql18_18.0.1.1-1_amd64.apk; \
	gpg --verify mssql-tools18_18.0.1.1-1_amd64.sig mssql-tools18_18.0.1.1-1_amd64.apk;

RUN apk add --allow-untrusted msodbcsql18_18.0.1.1-1_amd64.apk
RUN apk add --allow-untrusted mssql-tools18_18.0.1.1-1_amd64.apk

#RUN pecl install sqlsrv; \
#	pecl install pdo_sqlsrv
RUN pecl install sqlsrv-5.11.1; \
    pecl install pdo_sqlsrv-5.11.1

RUN echo extension=pdo_sqlsrv.so >> `php --ini | grep "Scan for additional .ini files" | sed -e "s|.*:\s*||"`/10_pdo_sqlsrv.ini; \
	echo extension=sqlsrv.so >> `php --ini | grep "Scan for additional .ini files" | sed -e "s|.*:\s*||"`/20_sqlsrv.ini

RUN rm msodbcsql18_18.0.1.1-1_amd64.apk msodbcsql18_18.0.1.1-1_amd64.sig mssql-tools18_18.0.1.1-1_amd64.apk mssql-tools18_18.0.1.1-1_amd64.sig
###>end sql server drivers

## gd
RUN apk add --no-cache --virtual build-essentials \
	libpng-dev libwebp-dev libjpeg-turbo-dev freetype-dev && \
	docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp && \
	docker-php-ext-install gd exif
##>end gd

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/conf.d/app.ini $PHP_INI_DIR/conf.d/
COPY docker/php/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/

COPY docker/php/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
RUN mkdir -p /var/run/php

COPY docker/php/docker-healthcheck.sh /usr/local/bin/docker-healthcheck
RUN chmod +x /usr/local/bin/docker-healthcheck

HEALTHCHECK --interval=10s --timeout=3s --retries=3 CMD ["docker-healthcheck"]

COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

RUN mkdir -p /etc/periodic/20min
RUN mkdir -p /etc/periodic/5hour
RUN touch /etc/periodic/log
COPY docker/cron/anular-reservaciones.sh /etc/periodic/20min/anular-reservaciones
RUN chmod +x /etc/periodic/20min/anular-reservaciones
RUN echo "*/20       *       *       *       *       run-parts /etc/periodic/20min" >> /etc/crontabs/root
COPY docker/cron/reenviar-email.sh /etc/periodic/1hour/reenviar-email
RUN chmod +x /etc/periodic/1hour/reenviar-email
RUN echo "*       */1       *       *       *       run-parts /etc/periodic/1hour" >> /etc/crontabs/root

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="${PATH}:/root/.composer/vendor/bin"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# prevent the reinstallation of vendors at every changes in the source code
COPY composer.* symfony.* ./
RUN set -eux; \
	if [ -f composer.json ]; then \
	composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress; \
	composer clear-cache; \
	fi

# copy sources
COPY . .
RUN rm -Rf docker/

RUN set -eux; \
	mkdir -p var/cache var/log public/images public/media public/facturas; \
	if [ -f composer.json ]; then \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer dump-env prod; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; sync; \
	fi

RUN apk add alpine-conf
RUN setup-timezone America/Guatemala

# Dev image
FROM app_php AS app_php_dev

ENV APP_ENV=dev XDEBUG_MODE=off
VOLUME /srv/app/var/

RUN rm $PHP_INI_DIR/conf.d/app.prod.ini; \
	mv "$PHP_INI_DIR/php.ini" "$PHP_INI_DIR/php.ini-production"; \
	mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY docker/php/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/

RUN apk add --update linux-headers;
RUN set -eux; \
	apk add --no-cache --virtual .build-deps $PHPIZE_DEPS; \
	pecl install xdebug; \
	docker-php-ext-enable xdebug; \
	apk del .build-deps

RUN rm -f .env.local.php

# Build Caddy with the Mercure and Vulcain modules
FROM caddy:${CADDY_VERSION}-builder-alpine AS app_caddy_builder

RUN xcaddy build \
	--with github.com/dunglas/mercure \
	--with github.com/dunglas/mercure/caddy \
	--with github.com/dunglas/vulcain \
	--with github.com/dunglas/vulcain/caddy

# Caddy image
FROM caddy:${CADDY_VERSION} AS app_caddy

WORKDIR /srv/app

COPY --from=app_caddy_builder /usr/bin/caddy /usr/bin/caddy
COPY --from=app_php /srv/app/public public/
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
