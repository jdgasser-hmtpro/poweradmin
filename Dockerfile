FROM php:8.3-fpm-alpine

RUN apk add --no-cache caddy \
    gettext-dev icu-dev libxml2-dev openldap-dev postgresql-dev \
    && apk add --no-cache icu-data-full icu-libs gettext libintl sqlite bash \
    mariadb-client mariadb-connector-c libpq libxml2 \
    && docker-php-ext-install gettext intl ldap mysqli pdo_mysql pdo_pgsql

WORKDIR /app
COPY . .
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY Caddyfile /etc/caddy/Caddyfile
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /db /app/config /data/caddy /config/caddy \
    && chown -R www-data:www-data /app /db /data/caddy /config/caddy


USER www-data
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["caddy", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
