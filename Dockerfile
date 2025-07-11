FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    ghostscript \
    imagemagick \
    libmagickwand-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql

# Install ImageMagick PHP extension
RUN pecl install imagick \
    && docker-php-ext-enable imagick

# Fix ImageMagick security policy to allow PDF operations
RUN sed -i '/<policy domain="coder" rights="none" pattern="PDF" \/>/d' /etc/ImageMagick-6/policy.xml || true \
    && sed -i '/<policy domain="coder" rights="none" pattern="PS" \/>/d' /etc/ImageMagick-6/policy.xml || true \
    && sed -i '/<policy domain="coder" rights="none" pattern="EPS" \/>/d' /etc/ImageMagick-6/policy.xml || true \
    && sed -i '/<policy domain="coder" rights="none" pattern="XPS" \/>/d' /etc/ImageMagick-6/policy.xml || true

# Also check for policy.xml in other possible locations
RUN if [ -f /etc/ImageMagick/policy.xml ]; then \
        sed -i '/<policy domain="coder" rights="none" pattern="PDF" \/>/d' /etc/ImageMagick/policy.xml; \
    fi

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure PHP
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy application files
COPY . /var/www/html/

# Install PHP dependencies
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

# Create necessary directories
RUN mkdir -p /var/www/html/uploads \
    && mkdir -p /var/www/html/temp \
    && mkdir -p /var/www/html/logs

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/temp \
    && chmod -R 777 /var/www/html/logs

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy and setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port (Railway will override this)
EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]