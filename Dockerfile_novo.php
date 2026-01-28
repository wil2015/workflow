FROM php:8.3-apache

# --- 1. DEPENDÊNCIAS BÁSICAS ---
# [ALTERADO] Adicionei libpng-dev, libjpeg-dev e libfreetype6-dev
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    zlib1g-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    gnupg2 \
    apt-transport-https \
    curl \
    $PHPIZE_DEPS

# --- 2. ADICIONA REPOSITÓRIO DA MICROSOFT (SQL SERVER) ---
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg
RUN curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list > /etc/apt/sources.list.d/mssql-release.list

# --- 3. INSTALA O DRIVER ODBC 18 E PHP EXTENSIONS ---
RUN apt-get update && \
    ACCEPT_EULA=Y apt-get install -y msodbcsql18 unixodbc-dev

# [ALTERADO] Configura e instala o GD junto com as outras extensões
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql xml zip

# Instala versões modernas do sqlsrv e pdo_sqlsrv
RUN pecl install sqlsrv pdo_sqlsrv && docker-php-ext-enable sqlsrv pdo_sqlsrv

# --- 4. XDEBUG 3 ---
RUN pecl install xdebug && docker-php-ext-enable xdebug
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN a2enmod rewrite
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html