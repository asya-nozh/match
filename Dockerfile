FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev && docker-php-ext-install mysqli mbstring && rm -rf /var/lib/apt/lists/*
COPY . /var/www/html/
RUN mkdir -p logs images/avatars && chown -R www-data:www-data logs images/avatars assets/images/places
