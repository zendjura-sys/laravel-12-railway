FROM php:8.4-fpm

# Instala as ferramentas básicas do sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Instala o componente que permite adicionar extensões ao PHP
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# AQUI ESTÁ A SOLUÇÃO: Instalamos o SOAP que a SEFAZ exige
RUN install-php-extensions ctype curl dom fileinfo filter hash mbstring openssl pcre pdo session tokenizer xml pdo_mysql soap

# Instala o Composer (Gerenciador de bibliotecas)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Instala as bibliotecas ( sped-nfe etc )
RUN composer install --optimize-autoloader --no-scripts --no-interaction

# Ajusta as permissões para o servidor não dar erro de acesso
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

EXPOSE 8080
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
