# syntax=docker/dockerfile:1
FROM ubuntu:22.04

RUN apt-get update && apt-get -y upgrade && DEBIAN_FRONTEND=noninteractive apt-get -y install \
    sudo \
    ssh \
    git \
    nano \
    less \
    net-tools \
    inetutils-ping \
    iproute2 \
    telnet \
    apache2 \
    curl \
    jq \
    ca-certificates \
    gnupg \
    postgresql-client \
    unzip \
    zip \
    libzip-dev \
    php \
    php-fpm \
    php-pgsql \
    php-sqlite3 \
    php-xml \
    php-xdebug \
    php-curl \
    php-redis \
    php-zip

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer

# Apache configuration
RUN a2enmod rewrite
RUN a2enmod actions

COPY ./docker/apache.conf /etc/apache2/sites-enabled/000-default.conf

# Manually set up the apache environment variables
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOG_DIR /var/log/apache2
ENV APACHE_LOCK_DIR /var/lock/apache2
ENV APACHE_PID_FILE /var/run/apache2.pid

COPY ./docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD apachectl -D FOREGROUND

# Used for debugging purposes only to keep the container up and running
# CMD tail -f /dev/null