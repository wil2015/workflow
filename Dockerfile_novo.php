FROM php:7.1-apache

# --- 1. CORREÇÃO DOS REPOSITÓRIOS DEBIAN (FIX 404) ---
RUN echo "deb http://archive.debian.org/debian/ buster main" > /etc/apt/sources.list && \
    echo "deb http://archive.debian.org/debian/ buster-updates main" >> /etc/apt/sources.list && \
    echo "deb http://archive.debian.org/debian-security buster/updates main" >> /etc/apt/sources.list || true

RUN sed -i 's/deb.debian.org/archive.debian.org/g' /etc/apt/sources.list || true && \
    sed -i 's/security.debian.org/archive.debian.org/g' /etc/apt/sources.list || true && \
    sed -i '/stretch-updates/d' /etc/apt/sources.list || true

# --- 2. INSTALA DEPENDÊNCIAS BÁSICAS E GNUMPG (Para chaves da Microsoft) ---
RUN apt-get -o Acquire::Check-Valid-Until=false update && \
    apt-get -o Acquire::Check-Valid-Until=false install -y \
    libxml2-dev \
    zlib1g-dev \
    libzip-dev \
    unzip \
    gnupg2 \
    apt-transport-https \
    $PHPIZE_DEPS

# --- 3. ADICIONA REPOSITÓRIO DA MICROSOFT (SQL SERVER) ---
# Adiciona a chave e o repositório do Debian 10 (Buster)
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add -
RUN curl https://packages.microsoft.com/config/debian/10/prod.list > /etc/apt/sources.list.d/mssql-release.list

# --- 4. INSTALA O DRIVER ODBC DA MICROSOFT ---
# ACCEPT_EULA=Y aceita a licença automaticamente
RUN apt-get -o Acquire::Check-Valid-Until=false update && \
    ACCEPT_EULA=Y apt-get -o Acquire::Check-Valid-Until=false install -y \
    msodbcsql17 \
    unixodbc-dev

# --- 5. INSTALA EXTENSÕES PHP ---
# Nativas
RUN docker-php-ext-install pdo_mysql xml zip

# SQL Server (Versão 5.6.1 é a última compatível com PHP 7.1)
RUN pecl install sqlsrv-5.6.1 pdo_sqlsrv-5.6.1 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Xdebug (Versão 2.9.8 para PHP 7.1)
RUN pecl install xdebug-2.9.8 \
    && docker-php-ext-enable xdebug

# --- 6. CONFIGURAÇÕES FINAIS ---
RUN echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_autostart=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_port=9000" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN a2enmod rewrite
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html