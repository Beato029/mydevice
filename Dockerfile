FROM php:8.3-cli

# Estensione curl per le chiamate a Supabase
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

# Render fornisce $PORT a runtime
ENV PORT=10000
EXPOSE 10000

# Usa lo start script così $PORT viene espanso correttamente a runtime
RUN chmod +x /app/start.sh
CMD ["/app/start.sh"]
