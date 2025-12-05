#!/bin/bash

# Скрипт для установки WordPress и запуска E2E тестов в Docker

set -e

PLUGIN_DIR="/var/www/wp-content/plugins/wp-queue"
WP_DIR="/var/www"

echo "=== Установка WordPress в Docker ==="

# Запуск команд в PHP контейнере
docker exec wp_site-php bash -c "
    cd $WP_DIR
    
    # Проверка, установлен ли WordPress
    if [ ! -f wp-settings.php ]; then
        echo 'Загрузка WordPress...'
        wp core download --allow-root --force
        
        echo 'Создание wp-config.php...'
        wp config create \
            --dbname=wp_site \
            --dbuser=mysql \
            --dbpass=mysql \
            --dbhost=mysql \
            --allow-root \
            --force
        
        echo 'Установка WordPress...'
        wp core install \
            --url=http://localhost \
            --title='WP Queue Test' \
            --admin_user=admin \
            --admin_password=admin \
            --admin_email=admin@example.com \
            --allow-root
    else
        echo 'WordPress уже установлен'
    fi
    
    # Активация плагина
    echo 'Активация плагина wp-queue...'
    wp plugin activate wp-queue --allow-root || true
"

echo ""
echo "=== Запуск E2E тестов ==="

docker exec wp_site-php bash -c "
    cd $PLUGIN_DIR
    WP_CORE_DIR=$WP_DIR ./vendor/bin/pest tests/Feature --configuration=phpunit-e2e.xml
"

echo ""
echo "=== E2E тесты завершены ==="
