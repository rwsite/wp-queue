<?php

declare(strict_types=1);

/**
 * Bootstrap для E2E тестов с реальным WordPress окружением.
 */

// Путь к корню WordPress
$wp_root = dirname(__DIR__, 4);

// Проверка наличия WordPress
if (! file_exists($wp_root.'/wp-load.php')) {
    echo "WordPress не найден в: {$wp_root}\n";
    exit(1);
}

// Загрузка WordPress
require_once $wp_root.'/wp-load.php';

// Загрузка WordPress тестовой библиотеки
if (file_exists($wp_root.'/wp-content/plugins/wp-queue/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php')) {
    require_once $wp_root.'/wp-content/plugins/wp-queue/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
}

// Загрузка плагина
require_once dirname(__DIR__).'/wp-queue.php';

// Активация плагина
if (! function_exists('activate_plugin')) {
    require_once $wp_root.'/wp-admin/includes/plugin.php';
}

// Инициализация плагина
\WPQueue\WPQueue::boot();

// Установка тестового пользователя с правами администратора
if (! function_exists('wp_set_current_user')) {
    function wp_set_current_user($user_id)
    {
        global $current_user;
        $current_user = new \stdClass();
        $current_user->ID = $user_id;

        return $current_user;
    }
}

// Создание тестового администратора
$admin_id = 1;
wp_set_current_user($admin_id);

echo "E2E тесты инициализированы с WordPress окружением\n";
