FROM php:8.2-apache

# PostgreSQL uchun PHP kengaytmasini o'rnatish
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    libpq-dev \
    nano \
    && docker-php-ext-install pdo pdo_pgsql gd

# Apache uchun mod_rewrite yoqish
RUN a2enmod rewrite

# Apache serveriga ServerName qo'shish
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Apache serverini qayta ishga tushurish
RUN service apache2 restart

# Composer o'rnatish
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Ishlash katalogi
WORKDIR /var/www

# Loyihani konteynerga yuklash
COPY . .

# Laravel uchun permission berish
RUN chown -R www-data:www-data /var/www

# Apache portini ochish
EXPOSE 80

