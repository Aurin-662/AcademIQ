FROM php:8.3-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip curl libgmp-dev libzip-dev libicu-dev libssl-dev libpng-dev libfreetype6-dev libjpeg-dev ca-certificates \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install gmp zip intl bcmath gd pcntl \
 && pecl install mongodb && docker-php-ext-enable mongodb \
 && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt-get install -y nodejs \
 && curl -fsSL -o /usr/local/bin/circom https://github.com/iden3/circom/releases/download/v2.2.2/circom-linux-amd64 && chmod +x /usr/local/bin/circom \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
