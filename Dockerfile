FROM php:8.2-cli

# extensões necessárias: PostgreSQL e Composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip git \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && php artisan storage:link || true

# migra o banco, cria os agentes de exemplo e sobe o servidor na porta que o host informar
CMD sh -c "php artisan migrate --force && php artisan db:seed --class=AgentSeeder --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"
