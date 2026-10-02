FROM php:8.3-fpm-alpine

# System deps
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    sqlite-dev

# PHP extensions required by the scraper and Laravel
# (ctype, fileinfo, tokenizer are already compiled in; no need to install)
RUN docker-php-ext-install \
    bcmath \
    mbstring \
    opcache \
    pdo \
    pdo_sqlite \
    xml \
    zip \
    intl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# php.ini tweaks for development
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" && \
    echo "display_errors = On"          >> "$PHP_INI_DIR/conf.d/wsdc.ini" && \
    echo "display_startup_errors = On"  >> "$PHP_INI_DIR/conf.d/wsdc.ini" && \
    echo "error_reporting = E_ALL"      >> "$PHP_INI_DIR/conf.d/wsdc.ini" && \
    echo "memory_limit = 256M"          >> "$PHP_INI_DIR/conf.d/wsdc.ini" && \
    echo "max_execution_time = 300"     >> "$PHP_INI_DIR/conf.d/wsdc.ini"

WORKDIR /var/www

# php-fpm runs as www-data; tell it to run as root so Windows-mounted
# volumes (which ignore chmod) are always writable in the dev container.
RUN sed -i 's/^user = www-data/user = root/' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's/^group = www-data/group = root/' /usr/local/etc/php-fpm.d/www.conf

EXPOSE 9000

# Entrypoint: ensure data dirs are writable before starting FPM
RUN printf '#!/bin/sh\nmkdir -p /var/www/data/raw\nchmod -R 777 /var/www/data\nchmod 777 /tmp\nexec php-fpm -R "$@"\n' > /usr/local/bin/entrypoint.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD []
