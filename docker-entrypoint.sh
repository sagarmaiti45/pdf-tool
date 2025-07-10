#!/bin/bash
set -e

# Use PORT env variable from Railway, default to 80
PORT=${PORT:-80}

# Update Apache configuration with the PORT
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

echo "Starting Apache on port ${PORT}..."

# Execute Apache
exec apache2-foreground