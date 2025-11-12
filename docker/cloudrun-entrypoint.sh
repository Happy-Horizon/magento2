#!/bin/bash
set -e

# Cloud Run entrypoint script for Magento 2
# This script handles initialization and starts supervisord

echo "Starting Cloud Run entrypoint script..."

# Set MAGE_ROOT environment variable
export MAGE_ROOT=/var/www/html

# Ensure required directories exist with proper permissions
mkdir -p /var/www/html/var
mkdir -p /var/www/html/generated
mkdir -p /var/www/html/pub/static
mkdir -p /var/www/html/pub/media

# Set permissions
chown -R www-data:www-data /var/www/html/var /var/www/html/generated
chmod -R 775 /var/www/html/var /var/www/html/generated

# Cloud Run provides PORT environment variable, but nginx is configured for 8080
# If PORT is set differently, we could adjust nginx config here
if [ -n "$PORT" ] && [ "$PORT" != "8080" ]; then
    echo "Warning: Cloud Run PORT ($PORT) differs from nginx default (8080)"
    echo "Consider updating nginx configuration if needed"
fi

# Run Magento setup commands if needed (only in production mode)
# These should typically be run during build, but can be run here if necessary
if [ "$RUN_MAGENTO_SETUP" = "true" ]; then
    echo "Running Magento setup commands..."
    cd /var/www/html
    
    # Set permissions
    php bin/magento setup:store-config:set --base-url="${MAGENTO_BASE_URL:-http://localhost}/"
    
    # Deploy static content (if not already done)
    if [ "$DEPLOY_STATIC_CONTENT" = "true" ]; then
        php bin/magento setup:static-content:deploy -f
    fi
    
    # Compile DI (if not already done)
    if [ "$COMPILE_DI" = "true" ]; then
        php bin/magento setup:di:compile
    fi
fi

# Start supervisord which will manage nginx and PHP-FPM
echo "Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
