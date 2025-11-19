FROM php:7.1-apache

# --- CORREÇÃO DOS REPOSITÓRIOS (FIX 404) ---
# Como o Debian dessa imagem é antigo, precisamos apontar para o archive.debian.org
# e desativar a verificação de validade (Check-Valid-Until=false)
RUN echo "deb http://archive.debian.org/debian/ buster main" > /etc/apt/sources.list && \
    echo "deb http://archive.debian.org/debian/ buster-updates main" >> /etc/apt/sources.list && \
    echo "deb http://archive.debian.org/debian-security buster/updates main" >> /etc/apt/sources.list || true

# Se a imagem for Stretch (versão 9) em vez de Buster, o comando acima pode falhar.
# O comando abaixo é uma "rede de segurança" genérica que substitui os URLs:
RUN sed -i 's/deb.debian.org/archive.debian.org/g' /etc/apt/sources.list || true && \
    sed -i 's/security.debian.org/archive.debian.org/g' /etc/apt/sources.list || true && \
    sed -i '/stretch-updates/d' /etc/apt/sources.list || true

# 1. Atualiza e instala as dependências com a flag de ignorar validade
RUN apt-get -o Acquire::Check-Valid-Until=false update && \
    apt-get -o Acquire::Check-Valid-Until=false install -y \
    libxml2-dev \
    zlib1g-dev \
    libzip-dev \
    unzip \
    $PHPIZE_DEPS

# 2. Instala extensões PHP
RUN docker-php-ext-install pdo_mysql xml zip

# 3. --- INSTALAÇÃO DO XDEBUG (Versão 2.9.8 para PHP 7.1) ---
RUN pecl install xdebug-2.9.8 \
    && docker-php-ext-enable xdebug

# 4. --- CONFIGURAÇÃO DO XDEBUG 2 ---
# Configuramos para conectar no 'host.docker.internal' (sua máquina Windows)
RUN echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_autostart=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_port=9000" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.remote_handler=dbgp" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
# 5. Habilita mod_rewrite do Apache
RUN a2enmod rewrite

# 6. Instala o Composer
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html