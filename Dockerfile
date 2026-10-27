FROM php:8.3-apache

RUN docker-php-ext-install mysqli \
    && a2enmod headers

COPY . /var/www/html/
COPY railway-entrypoint.sh /usr/local/bin/railway-entrypoint

RUN chmod +x /usr/local/bin/railway-entrypoint \
    && chown -R www-data:www-data /var/www/html

ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["railway-entrypoint"]
CMD ["apache2-foreground"]
