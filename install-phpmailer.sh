#!/bin/bash

# Install PHPMailer using Composer
echo "Installing PHPMailer..."

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "Composer is not installed. Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

# Install PHPMailer
composer require phpmailer/phpmailer

echo "PHPMailer installation complete!"