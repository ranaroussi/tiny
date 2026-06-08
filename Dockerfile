FROM alpine:3.23

# Setup document root
WORKDIR /var/www/html

# Install packages and remove default server definition
RUN apk add --no-cache \
  libmemcached-dev \
  memcached \
  curl \
  bash \
  nano \
  nginx \
  dcron \
  php85 \
  php85-bcmath \
  php85-bz2 \
  php85-cgi \
  php85-ctype \
  php85-curl \
  php85-dba \
  php85-dev \
  php85-dom \
  php85-enchant \
  php85-fpm \
  php85-fileinfo \
  php85-gd \
  php85-gmp \
  php85-pecl-imap \
  php85-intl \
  php85-ldap \
  php85-mbstring \
  php85-mysqli \
  php85-odbc \
  php85-openssl \
  php85-pdo \
  php85-pdo_mysql \
  php85-pdo_pgsql \
  php85-pdo_sqlite \
  php85-pgsql \
  php85-phar \
  php85-phpdbg \
  php85-session \
  php85-snmp \
  php85-soap \
  php85-sqlite3 \
  php85-tidy \
  php85-xml \
  php85-simplexml \
  php85-xmlreader \
  php85-xsl \
  php85-zip \
  php85-pecl-memcached \
  php85-pecl-imagick \
  php85-pecl-apcu \
  php85-pecl-excimer \
  supervisor \
  imagemagick \
  imagemagick-dev

# Configure nginx - http
COPY server-config/nginx.conf /etc/nginx/nginx.conf

# Configure default server
COPY server-config/conf.d /etc/nginx/conf.d/

# Configure PHP-FPM
COPY server-config/fpm-www.conf /etc/php85/php-fpm.d/www.conf
COPY server-config/php.ini /etc/php85/conf.d/custom.ini
RUN ln -s /usr/bin/php85 /usr/bin/php

# Configure supervisord
COPY server-config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
RUN chmod +x /etc/supervisor/conf.d/supervisord.conf

# Configure timezone
RUN apk add --no-cache tzdata && \
    cp /usr/share/zoneinfo/UTC /etc/localtime && \
    echo "UTC" > /etc/timezone && \
    apk del tzdata

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add application
COPY html /var/www/html
COPY tiny /var/www/tiny
COPY app /var/www/app
COPY .version /var/www
COPY composer.json /var/www
RUN find /var/www -name ".DS_Store" -type f -delete
RUN chmod +x /var/www/app/scheduler.sh

# Create log directories and set up cron
RUN mkdir -p /var/log/cron && \
    touch /var/log/cron/cron.log /var/log/cron/cron.error.log && \
    chown -R nobody:nobody /var/log/cron

# Create error pages directory with proper permissions
RUN mkdir -p /var/lib/nginx/html && \
    echo "<html><body><h1>Error 50x</h1><p>An internal server error occurred.</p></body></html>" > /var/lib/nginx/html/50x.html && \
    echo "<html><body><h1>Error 404</h1><p>Page not found.</p></body></html>" > /var/lib/nginx/html/404.html && \
    chown -R nginx:nginx /var/lib/nginx/html && \
    chmod -R 755 /var/lib/nginx/html

# Setup crontab
COPY server-config/crontab.txt /etc/crontabs/root
RUN chmod 600 /etc/crontabs/root

# Set all permissions
RUN chown -R nobody:nginx /var/www /run /var/lib/nginx /var/log/nginx /etc/nginx /etc/php85 && \
    chmod 755 /run && \
    chmod -R 755 /var/www/html && \
    chmod -R 755 /var/log/nginx && \
    chmod -R 755 /var/lib/nginx && \
    chmod 755 /etc/php85/php-fpm.d/www.conf && \
    mkdir -p /var/log/php && \
    touch /var/log/php/php-errors.log /var/log/php/php-slow.log && \
    chmod 777 /var/log/php/php-errors.log /var/log/php/php-slow.log

# Install dependencies
RUN composer install --working-dir=/var/www --no-dev --ignore-platform-reqs --optimize-autoloader --no-cache

# Expose the port nginx is reachable on
EXPOSE 8080

# Let supervisord start nginx & php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping
