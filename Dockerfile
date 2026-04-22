# Сайт МакроТранс: PHP 8.2 + Apache (для Render, Railway, Fly.io, своего VPS)
FROM php:8.2-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libonig-dev \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_sqlite mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Закрыть прямой доступ к папке с базой из браузера
RUN printf '%s\n' \
  '<Directory /var/www/html/data>' \
  '  Require all denied' \
  '</Directory>' \
  > /etc/apache2/conf-available/deny-data.conf \
  && a2enconf deny-data

WORKDIR /var/www/html
COPY . /var/www/html/

RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data

EXPOSE 80
