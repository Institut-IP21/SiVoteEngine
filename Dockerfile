FROM php:7.4-fpm

ARG DOCKER_USER_ID

# Copy composer.lock and composer.json

# Set working directory
WORKDIR /var/www

# Install dependencies
RUN apt-get update && apt-get install -y \
	build-essential \
	libpng-dev \
	libonig-dev \
	libjpeg62-turbo-dev \
	libfreetype6-dev \
	locales \
	libzip-dev \
	zip \
	jpegoptim optipng pngquant gifsicle \
	vim \
	unzip \
	git \
	curl \
	npm

RUN npm install npm@latest -g

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install extensions
RUN docker-php-ext-install pdo_mysql zip exif pcntl

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add user for laravel application
RUN groupadd -g $DOCKER_USER_ID www
RUN useradd -u $DOCKER_USER_ID -ms /bin/bash -g www www

# Copy existing application directory contents
COPY . /var/www

# Copy existing application directory permissions
COPY --chown=www:www . /var/www

# Change current user to www
USER www

# Start php-fpm server
CMD ["php-fpm"]
