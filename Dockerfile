# =========================
# PHP / Composer build stage
# =========================
FROM php:8.4-fpm-bullseye AS vendor
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

# Compile PHP extensions once — copied into the runtime stage to avoid recompiling
RUN apt-get update && apt-get install -y --no-install-recommends \
    xmlsec1 libxmlsec1-openssl \
    libpng-dev libjpeg-dev libfreetype6-dev libsqlite3-dev libicu-dev libzip-dev \
    libonig-dev libxml2-dev libgpgme-dev libgpg-error-dev libmemcached-dev \
    libldap2-dev build-essential pkg-config autoconf bash \
 && docker-php-ext-configure gd --with-jpeg --with-freetype \
 && docker-php-ext-install intl zip bcmath mbstring pdo pdo_mysql pdo_sqlite soap gd dom exif opcache ldap \
 && pecl channel-update pecl.php.net \
 && pecl install gnupg-1.5.0 memcached-3.2.0 \
 && docker-php-ext-enable gnupg memcached \
 && rm -rf /var/lib/apt/lists/*

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
FROM php:8.4-fpm-bullseye AS runtime
ENV TZ=UTC
WORKDIR /var/www/openroaming

# Install runtime OS libraries — no PHP extension compilation (extensions copied from vendor stage below)
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor tzdata xmlsec1 libxmlsec1-openssl \
    libpng-dev libjpeg-dev libfreetype6-dev libsqlite3-dev libicu-dev libzip-dev \
    libonig-dev libxml2-dev libgpgme-dev libgpg-error-dev libmemcached-dev \
    libldap2-dev curl gnupg bash \
 && rm -rf /var/lib/apt/lists/*

# Reuse compiled extensions from vendor stage — avoids recompiling lexbor/dom and all other extensions
COPY --from=vendor /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=vendor /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

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
