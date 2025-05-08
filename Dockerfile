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

# Laravelni ishga tushirish uchun Apache konfiguratsiyasi
COPY ./src /var/www/html
COPY ./apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Apache'ni va Laravelni ishga tushirish
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
RUN a2enmod rewrite

# Laravel uchun permission berish
RUN chown -R www-data:www-data /var/www

# Apache portini ochish
EXPOSE 80

