FROM php:8.3-apache

# --- 1. DEPENDÊNCIAS BÁSICAS E GNUMPG ---
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    zlib1g-dev \
    libzip-dev \
    unzip \
    gnupg2 \
    apt-transport-https \
    curl \
    $PHPIZE_DEPS

# --- 2. ADICIONA REPOSITÓRIO DA MICROSOFT (GPG KEY) ---
# Correção: Remove apt-key e usa o repositório do Debian 12 (Bookworm)
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg
RUN curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list > /etc/apt/sources.list.d/mssql-release.list

# --- 3. INSTALA O DRIVER ODBC 18 DA MICROSOFT ---
RUN apt-get update && \
    ACCEPT_EULA=Y apt-get install -y msodbcsql18 unixodbc-dev

# --- 4. INSTALA EXTENSÕES PHP ---
RUN docker-php-ext-install pdo_mysql xml zip

# Instala versões modernas do sqlsrv para PHP 8.3
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Xdebug 3 (Configuração para PHP 8.3 na porta 9003)
RUN pecl install xdebug && docker-php-ext-enable xdebug
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN a2enmod rewrite
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html