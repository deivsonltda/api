FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
  nginx \
  supervisor \
  bash \
  curl \
  ca-certificates \
  icu-dev \
  libzip-dev \
  oniguruma-dev \
  zlib-dev \
  && docker-php-ext-install \
    pdo \
    pdo_mysql \
    intl \
    zip \
  && docker-php-ext-install opcache \
  && rm -rf /var/cache/apk/*

WORKDIR /var/www/html
COPY . /var/www/html

# pastas úteis (logs, tmp)
RUN mkdir -p /var/www/html/log /run/nginx \
  && chown -R www-data:www-data /var/www/html

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]