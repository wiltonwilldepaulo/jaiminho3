# Define a imagem base do container
# PHP 8.5.2 com PHP-FPM, baseada em Alpine Linux (leve e eficiente)
FROM php:8.5.4-fpm-alpine3.23

# Define variáveis de ambiente globais do container
# TZ ajusta o fuso horário
# COMPOSER_ALLOW_SUPERUSER permite executar o Composer como root dentro do container
ENV TZ=${TZ} \
    COMPOSER_ALLOW_SUPERUSER=1

# Instala dependências do sistema operacional usando o gerenciador apk (Alpine)
# --no-cache evita armazenar cache local, reduzindo o tamanho da imagem final
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    icu-dev \
    postgresql-dev \
    oniguruma-dev \
    tzdata

# Compila e instala extensões PHP nativas necessárias para a aplicação
# PCOV instalado via pecl para cobertura de código nos testes (sem ext-uopz)
RUN apk add --no-cache $PHPIZE_DEPS \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    postgresql-dev \
    icu-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_pgsql \
    intl \
    mbstring \
    gd \
    && pecl install pcov \
    && docker-php-ext-enable pcov

# Copia o arquivo de configuração customizado do PHP para o container
# Ele será carregado automaticamente pelo PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Copia o binário do Composer a partir da imagem oficial
# Evita instalar o Composer manualmente e reduz complexidade
COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

# Define o diretório de trabalho padrão dentro do container
# Todos os comandos seguintes serão executados a partir desse diretório
WORKDIR /var/www

# Copia todo o código da aplicação para dentro do container
COPY . .

# Expõe a porta 9000, utilizada pelo PHP-FPM
# Essa porta será acessada pelo Nginx via rede Docker
EXPOSE 9000

# Comando padrão executado ao iniciar o container
# Inicia o PHP-FPM
CMD ["php-fpm"]