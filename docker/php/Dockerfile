# syntax=docker/dockerfile:1.7
# =============================================================================
# Dockerfile — ERP Fanorte
# Runtime: FrankenPHP custom build sobre php:8.5.4-alpine3.22
# Extensões: pdo_pgsql, redis, opcache, intl, bcmath
#
# Estágios:
#   base    → PHP + libs + extensões (compartilhado entre builder e runtime)
#   builder → base + Composer + dependências instaladas
#   runtime → base + binário FrankenPHP + artefatos (sem toolchain)
#
# Princípios aplicados:
#   - Multi-stage com extração de artefatos (mínimo possível em runtime)
#   - Layer ordering: estável → volátil (cache eficiente)
#   - Cache mounts (BuildKit) para Composer e APK
#   - Usuário não-root no runtime
#   - Read-only friendly (suporta security_opt do compose)
#   - tini como init process (PID 1 correto)
# =============================================================================

ARG PHP_VERSION=8.5.4
ARG PHP_IMAGE=php:${PHP_VERSION}-alpine3.22
ARG FRANKENPHP_VERSION=1.4.0
ARG APP_UID=1000
ARG APP_GID=1000

# =============================================================================
# Estágio: base
# Imagem comum entre builder e runtime — PHP + libs + extensões PHP
# =============================================================================
FROM ${PHP_IMAGE} AS base

# Argumentos repassados ao estágio
ARG APP_UID
ARG APP_GID

# Variáveis de ambiente — config de runtime PHP
ENV PHP_INI_DIR=/usr/local/etc/php \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1

# -----------------------------------------------------------------------------
# Pacotes de runtime (libs dinâmicas necessárias em produção)
# Não inclui ferramentas de build — essas vão apenas no builder
# -----------------------------------------------------------------------------
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    apk add --no-cache \
    ca-certificates \
    tini \
    tzdata \
    curl \
    bash \
    libpq \
    icu-libs \
    libstdc++

# -----------------------------------------------------------------------------
# Instalador de extensões PHP (mlocati/docker-php-extension-installer)
# Padrão de fato — resolve dependências automaticamente, mais rápido e seguro
# que docker-php-ext-install manual
# -----------------------------------------------------------------------------
ADD --chmod=0755 \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    /usr/local/bin/install-php-extensions

# -----------------------------------------------------------------------------
# Extensões PHP — perfil "Mínimo ERP" conforme system instructions
#   - pdo_pgsql → PostgreSQL via Doctrine DBAL
#   - redis     → cliente phpredis (NÃO usar predis em produção; phpredis é nativo)
#   - opcache   → bytecode cache + JIT em produção
#   - intl      → I18n, formatação de moeda, datas (crítico para ERP brasileiro)
#   - bcmath    → aritmética arbitrária (cálculos financeiros sem float drift)
# -----------------------------------------------------------------------------
RUN install-php-extensions \
    pdo_pgsql \
    redis \
    opcache \
    intl \
    bcmath

# -----------------------------------------------------------------------------
# Usuário não-root para runtime
# UID/GID fixos garantem permissão consistente em volumes mount entre dev/prod
# -----------------------------------------------------------------------------
RUN addgroup -g ${APP_GID} -S app \
    && adduser  -u ${APP_UID} -S -G app -h /var/www -s /bin/bash app

WORKDIR /var/www

# =============================================================================
# Estágio: builder
# Composer + dependências PHP do projeto (vendor)
# =============================================================================
FROM base AS builder

# Ferramentas de build necessárias apenas para `composer install`
# Git é necessário para Composer instalar pacotes de repositórios VCS
# unzip é mais rápido que tar.gz para downloads do Composer
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    apk add --no-cache \
    git \
    unzip \
    patch

# Composer via imagem oficial (extração apenas do binário)
COPY --from=composer/composer:2.8-bin /composer /usr/local/bin/composer

# -----------------------------------------------------------------------------
# Instalação do vendor — layer cacheável
# Copiar APENAS composer.json + composer.lock primeiro maximiza cache hit:
# se nenhum dos dois mudou, o vendor não é reinstalado em builds seguintes
# -----------------------------------------------------------------------------
COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/root/.composer/cache,sharing=locked \
    composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-progress

# -----------------------------------------------------------------------------
# Copiar código-fonte e gerar autoload otimizado
# Esta layer invalida a cada mudança no código — esperado e correto
# -----------------------------------------------------------------------------
COPY --chown=app:app . .

RUN --mount=type=cache,target=/root/.composer/cache,sharing=locked \
    composer dump-autoload \
    --classmap-authoritative \
    --no-dev \
    --optimize \
    && composer run-script post-install-cmd --no-dev || true

# Remover artefatos que não devem ir para runtime
RUN rm -rf \
    .git \
    .github \
    .vscode \
    .idea \
    tests \
    docs \
    docker \
    node_modules \
    .env \
    .env.local \
    .env.*.local \
    .gitattributes \
    .gitignore \
    composer.lock \
    phpstan.neon* \
    rector.php \
    .php-cs-fixer.* \
    pest.config.php

# =============================================================================
# Estágio: runtime
# Imagem final de produção — sem Composer, sem build tools, sem código-fonte de dev
# =============================================================================
FROM base AS runtime

ARG FRANKENPHP_VERSION
ARG TARGETARCH

# -----------------------------------------------------------------------------
# Binário do FrankenPHP — estaticamente linkado, standalone
# Detecção de arquitetura (amd64/arm64) para builds multi-plataforma
# -----------------------------------------------------------------------------
RUN set -eux; \
    case "${TARGETARCH:-amd64}" in \
    amd64) FRANKEN_ARCH='x86_64' ;; \
    arm64) FRANKEN_ARCH='aarch64' ;; \
    *) echo "Arquitetura não suportada: ${TARGETARCH}"; exit 1 ;; \
    esac; \
    curl -fsSL \
    "https://github.com/php/frankenphp/releases/download/v${FRANKENPHP_VERSION}/frankenphp-linux-${FRANKEN_ARCH}" \
    -o /usr/local/bin/frankenphp; \
    chmod +x /usr/local/bin/frankenphp; \
    /usr/local/bin/frankenphp version

# -----------------------------------------------------------------------------
# Capabilities para FrankenPHP escutar em portas privilegiadas (<1024)
# Sem isso, FrankenPHP rodando como usuário 'app' não conseguiria bind em 80/443
# Em nosso compose ele escuta na 80 interna (Nginx/Traefik fica na frente),
# mas mantemos por flexibilidade arquitetural
# -----------------------------------------------------------------------------
RUN apk add --no-cache libcap \
    && setcap cap_net_bind_service=+ep /usr/local/bin/frankenphp \
    && apk del libcap

# -----------------------------------------------------------------------------
# Diretórios de runtime com permissão correta
# var/log → logs da aplicação (montado como volume nomeado no compose)
# var/cache → cache do Twig, container DI compilado
# -----------------------------------------------------------------------------
RUN mkdir -p \
    /var/www/var/log \
    /var/www/var/cache \
    /var/www/public \
    && chown -R app:app /var/www

# Copiar artefatos do builder Apenas o que é necessário em produção — vendor + código + public
COPY --from=builder --chown=app:app /var/www/vendor   /var/www/vendor
COPY --from=builder --chown=app:app /var/www/public   /var/www/public
COPY --from=builder --chown=app:app /var/www/app      /var/www/app
COPY --from=builder --chown=app:app /var/www/composer.json /var/www/composer.json

# Copiar php.ini de produção se o arquivo não existir ainda, copia o default da imagem
#COPY --chown=root:root /docker/php/php.ini /usr/local/etc/php/php.ini

# Mudança para usuário não-root A partir daqui, nada roda como root
USER app:app

WORKDIR /var/www

# Healthcheck — endpoint /health deve responder 200 em <5s efine o contrato: app DEVE expor /health verificando banco + redis
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl --fail --silent --show-error http://localhost/health || exit 1

# Portas expostas (documentação — Compose ignora EXPOSE, mas é boa prática)
# 80 → HTTP interno (Nginx/Traefik termina TLS na frente)
EXPOSE 80

# -----------------------------------------------------------------------------
# Init process (tini) → PID 1 correto, propagação de sinais, reap de zombies
# FrankenPHP em worker mode → bootstrap único, processos persistentes
# -----------------------------------------------------------------------------
ENTRYPOINT ["/sbin/tini", "--"]

CMD ["frankenphp", "run", \
    "--config", "/etc/caddy/Caddyfile"]