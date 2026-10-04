FROM php:8.3-fpm-alpine
RUN apk add freetype-dev libjpeg-turbo-dev libpng-dev  libxml2-dev curl-dev icu-dev oniguruma-dev
RUN docker-php-ext-configure gd --with-freetype --with-jpeg 
RUN docker-php-ext-install mysqli pdo pdo_mysql mbstring curl gd  bcmath xml dom simplexml intl
RUN echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf
RUN mkdir -p /var/lib/php/sessions && chown -R www-data:www-data /var/lib/php/sessions
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader
RUN chown -R www-data:www-data /var/www/html
EXPOSE 9000
CMD ["php-fpm"]
