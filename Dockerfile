# =========================
# PHP / Composer build stage
# =========================
FROM php:8.4-fpm-bookworm AS vendor
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

# Install build deps for Composer and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor git zip unzip curl gnupg tzdata wget \
 && rm -rf /var/lib/apt/lists/*

#RUN wget -O PaloAlto_SSLInspection_ForwardTrust.crt https://tetrapi.pt/gp/PaloAlto_SSLInspection_ForwardTrust.crt \
#    && cp PaloAlto_SSLInspection_ForwardTrust.crt /usr/local/share/ca-certificates/ \
#    && update-ca-certificates

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer \
 && composer self-update --2

# PHP extensions, needed here for composer install and the Symfony cache warmup.
# install-php-extensions resolves the right system libraries and extension versions
# for the base image's Debian release. The previous hand-pinned pecl builds were
# tied to bullseye: memcached-3.2.0 cannot detect bookworm's libmemcached-awesome.
COPY --from=mlocati/php-extension-installer:2.11.12 /usr/bin/install-php-extensions /usr/local/bin/
RUN apt-get update && apt-get install -y --no-install-recommends \
    xmlsec1 libxmlsec1-openssl bash \
 && rm -rf /var/lib/apt/lists/* \
 && install-php-extensions \
      intl zip bcmath mbstring pdo pdo_mysql pdo_sqlite soap gd dom exif opcache ldap \
      gnupg memcached

# Copy Symfony app
COPY . .

# Install PHP dependencies
COPY ./.env.sample /app/.env
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini \
 && composer install --optimize-autoloader --no-interaction

# Warm Symfony cache
RUN php bin/console cache:warmup --env=prod

# =========================
# Final runtime image
# =========================
FROM php:8.4-fpm-bookworm AS runtime
ENV TZ=UTC
WORKDIR /var/www/openroaming

# Set CA bundle for Python / requests (Certbot)
ENV REQUESTS_CA_BUNDLE=/etc/ssl/certs/ca-certificates.crt

# Install runtime OS packages, then the PHP extensions.
#
# The extensions are installed here rather than copied as .so files from the
# vendor stage: install-php-extensions pulls each extension's own runtime
# libraries, so the two cannot drift apart. Copying the .so files left gd
# unloadable, because it is built against libavif, which was not installed here.
COPY --from=mlocati/php-extension-installer:2.11.12 /usr/bin/install-php-extensions /usr/local/bin/
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor tzdata xmlsec1 libxmlsec1-openssl ca-certificates \
    curl gnupg bash \
    certbot python3-certbot-nginx python3-certbot-dns-cloudflare python3-certbot-dns-google \
 && update-ca-certificates \
 && rm -rf /var/lib/apt/lists/* \
 && install-php-extensions \
      intl zip bcmath mbstring pdo pdo_mysql pdo_sqlite soap gd dom exif opcache ldap \
      gnupg memcached

# Fail the build here rather than at runtime if an extension is not loadable:
# the container would still start, so CI's smoke test would not catch it
RUN for ext in intl zip bcmath mbstring pdo_mysql pdo_sqlite soap gd exif ldap gnupg memcached; do \
      php -m | grep -qx "$ext" || { echo "missing PHP extension: $ext"; php -m; exit 1; }; \
    done

# Set PHP memory limit for runtime (overrides the 512M set in vendor stage)
RUN echo "memory_limit=1024M" > /usr/local/etc/php/conf.d/memory.ini

# Copy Symfony app from vendor stage
COPY . /var/www/openroaming
COPY --from=vendor /app /var/www/openroaming
RUN php bin/console cache:clear --env=prod --no-debug
RUN php bin/console tailwind:build --minify --env=prod
RUN php bin/console asset-map:compile --env=prod

# Copy configs
COPY service-config/supervisor/supervisord.conf /etc/supervisor/conf.d/
COPY service-config/nginx/nginx.conf /etc/nginx/nginx.conf
COPY service-config/nginx/mime.types /etc/nginx/mime.types
COPY service-config/nginx/fastcgi_params /etc/nginx/fastcgi_params
COPY service-config/nginx/sites /etc/nginx/conf.d/

# Prepare runtime environment
RUN mkdir -p /run/nginx /run/php /var/log/supervisor /var/www/openroaming/var \
 && chown -R www-data:www-data /var/www/openroaming

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
