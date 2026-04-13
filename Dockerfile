FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    git unzip curl bash zsh sudo \
    icu-dev oniguruma-dev libzip-dev \
    nodejs npm

RUN docker-php-ext-install \
    intl pdo pdo_mysql zip opcache mbstring

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

RUN deluser www-data 2>/dev/null || true \
    && addgroup -g 1000 appuser \
    && adduser -u 1000 -G appuser -s /bin/zsh -D appuser \
    && echo "appuser ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers

RUN sed -i 's/user = www-data/user = appuser/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/group = www-data/group = appuser/' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/portal

COPY --chown=appuser:appuser composer.json composer.lock* ./
RUN composer install --no-scripts --prefer-dist 2>/dev/null || true

COPY --chown=appuser:appuser package.json package-lock.json* ./
RUN npm install 2>/dev/null || true

COPY --chown=appuser:appuser . .

# Fix ownership of everything after copy
# RUN chown -R appuser:appuser /var/www/portal  # with userns_mode: keep-id from docker-compose.yml it is no longer needed and is what caused the ownership shift.

USER appuser

EXPOSE 9000
CMD ["php-fpm"]