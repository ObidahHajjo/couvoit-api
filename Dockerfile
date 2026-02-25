FROM php:8.5-apache

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev \
 && docker-php-ext-install pdo pdo_pgsql zip \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && a2enmod rewrite headers \
 && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername \
 && sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
 && printf '%s\n' \
    '<Directory /var/www/html/public>' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    > /etc/apache2/conf-available/laravel-public.conf \
 && a2enconf laravel-public \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
