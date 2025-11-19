# -----------------------------------------------------------------
# ESTÁGIO 1: "Builder" do Front-end (Onde o Vite vai rodar)
# -----------------------------------------------------------------
FROM node:24-alpine AS builder

# Define o diretório de trabalho
WORKDIR /app-builder

# Copia os arquivos de dependência do front-end
COPY package.json package-lock.json ./

# Instala as dependências (npm install)
RUN npm install

# Copia o resto dos arquivos do front-end
COPY ./src ./src
COPY index.html ./
# (Copie outros arquivos de config do Vite se houver)

# Roda o "build" (npm run build)
RUN npm run build
# Agora temos uma pasta /app-builder/dist/ com o front-end pronto

# -----------------------------------------------------------------
# ESTÁGIO 2: Servidor Final (Apache + PHP 7.1)
# -----------------------------------------------------------------
# (Usamos a imagem oficial do PHP 7.1 com Apache)
FROM php:8.2-apache

# Habilita o mod_rewrite do Apache (para URLs amigáveis)
RUN a2enmod rewrite

# Instala as extensões PHP necessárias:
# 1. pdo_mysql (para conectar ao MySQL)
# 2. zip (essencial para o PHPWord 0.18.x)
# 3. xml (também essencial para o PHPWord)
RUN docker-php-ext-install pdo_mysql zip xml

# (OPCIONAL: Se for usar a alternativa pdftk)
# RUN apt-get update && apt-get install -y pdftk

# Copia o back-end (seu código PHP, o "motor")
# (Estou assumindo que seu PHP fica numa pasta /backend)
COPY ./backend/ /var/www/html/

# COPIA MÁGICA: Copia o front-end "empacotado" do ESTÁGIO 1
# para dentro da pasta do Apache
COPY --from=builder /app-builder/dist/ /var/www/html/

# (Certifique-se que o Apache possa ler os arquivos)
RUN chown -R www-data:www-data /var/www/html