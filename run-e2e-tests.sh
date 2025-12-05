#!/bin/bash

# Скрипт для запуска E2E тестов в Docker окружении

set -e

echo "🚀 Запуск E2E тестов WP Queue..."

# Проверка наличия Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Docker не установлен. Установите Docker и попробуйте снова."
    exit 1
fi

# Проверка наличия docker-compose
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "❌ docker-compose не найден. Установите docker-compose и попробуйте снова."
    exit 1
fi

# Определение команды docker-compose
if docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    DOCKER_COMPOSE="docker-compose"
fi

# Запуск Docker окружения
echo "🐳 Запуск WordPress и MySQL..."
$DOCKER_COMPOSE up -d

# Ожидание запуска сервисов
echo "⏳ Ожидание запуска WordPress и MySQL..."
sleep 10

# Проверка доступности WordPress
echo "🔍 Проверка доступности WordPress..."
max_attempts=30
attempt=1

while [ $attempt -le $max_attempts ]; do
    if curl -f http://localhost:8080/wp-admin/install.php &> /dev/null; then
        echo "✅ WordPress доступен!"
        break
    fi

    echo "⏳ Попытка $attempt/$max_attempts - WordPress еще не готов..."
    sleep 10
    attempt=$((attempt + 1))
done

if [ $attempt -gt $max_attempts ]; then
    echo "❌ WordPress не запустился в течение $(($max_attempts * 10)) секунд"
    $DOCKER_COMPOSE logs wordpress
    exit 1
fi

# Активация плагина
echo "🔌 Активация плагина WP Queue..."
$DOCKER_COMPOSE exec -T wordpress wp plugin activate wp-queue --allow-root

# Запуск E2E тестов
echo "🧪 Запуск E2E тестов..."
$DOCKER_COMPOSE exec -T wordpress bash -c "
cd /var/www/html/wp-content/plugins/wp-queue && \
WP_CORE_DIR=/var/www/html \
WP_TESTS_DIR=/tmp/wordpress-tests-lib \
./vendor/bin/pest tests/Feature --configuration=phpunit-e2e.xml
"

test_exit_code=$?

# Остановка Docker окружения
echo "🛑 Остановка Docker окружения..."
$DOCKER_COMPOSE down

if [ $test_exit_code -eq 0 ]; then
    echo "✅ Все E2E тесты прошли успешно!"
else
    echo "❌ Некоторые E2E тесты провалились"
    exit $test_exit_code
fi
