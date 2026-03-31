FROM php:8.2-apache

# 1. Instala dependências do sistema
RUN apt-get update && apt-get install -y \
    gnupg2 curl apt-transport-https unixodbc-dev libpng-dev libzip-dev unzip git \
    && docker-php-ext-install zip gd

# 2. Instalação dos Drivers Microsoft para SQL Server (ODBC 18)
# Baseado no Debian 12 (Bookworm)
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl https://packages.microsoft.com/config/debian/12/prod.list > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18 \
    && echo 'export PATH="$PATH:/opt/mssql-tools18/bin"' >> ~/.bashrc

# 3. Instala extensões PHP (ATENÇÃO AQUI: Adicionado 'sqlsrv' junto com 'pdo_sqlsrv')
# sqlsrv = Para o legado (sqlsrv_query)
# pdo_sqlsrv = Para o novo (PDO)
RUN pecl install sqlsrv pdo_sqlsrv xdebug \
    && docker-php-ext-install pdo pdo_mysql \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv xdebug

# 4. Configuração do Xdebug
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# 5. Habilita reescrita de URL
RUN a2enmod rewrite

# 6. Permissões
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html