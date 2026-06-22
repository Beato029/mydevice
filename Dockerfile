FROM php:8.3-cli

# Installa estensioni necessarie (curl per chiamate Supabase)
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

# Render imposta la variabile PORT — la usiamo nel router
EXPOSE 10000

# Avvia il server PHP built-in con router
CMD php -S 0.0.0.0:${PORT:-10000} router.php
