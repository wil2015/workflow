FROM php:8.3-apache

# --- 1. LIMPEZA E DEPENDÊNCIAS BÁSICAS ---
# Removidos os redirecionamentos para archive.debian.org pois o PHP 8.3 usa o Debian 12 atualizado
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    zlib1g-dev \
    libzip-dev \
    unzip \
    gnupg2 \
    apt-transport-https \
    curl \
    $PHPIZE_DEPS

# --- 2. ADICIONA REPOSITÓRIO DA MICROSOFT (SQL SERVER) ---
# CORREÇÃO: Usa GPG em vez de apt-key e aponta para o repositório do Debian 12
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg
RUN curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list > /etc/apt/sources.list.d/mssql-release.list

# --- 3. INSTALA O DRIVER ODBC DA MICROSOFT ---
RUN apt-get update && \
    ACCEPT_EULA=Y apt-get install -y \
    msodbcsql18 \
    unixodbc-dev

# --- 4. INSTALA EXTENSÕES PHP ---
RUN docker-php-ext-install pdo_mysql xml zip

# SQL Server (Versões modernas compatíveis com PHP 8.3)
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Xdebug 3 (A versão 2.9.8 não funciona no PHP 8.3)
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# --- 5. CONFIGURAÇÕES DO XDEBUG 3 ---
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN a2enmod rewrite
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html