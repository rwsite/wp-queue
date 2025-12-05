<?php

/**
 * Конфигурация WordPress для Docker тестирования
 */

define('DB_NAME', getenv('WORDPRESS_DB_NAME') ?: 'wordpress_test');
define('DB_USER', getenv('WORDPRESS_DB_USER') ?: 'wordpress');
define('DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: 'wordpress');
define('DB_HOST', getenv('WORDPRESS_DB_HOST') ?: 'mysql');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

define('AUTH_KEY',         'test-key');
define('SECURE_AUTH_KEY',  'test-key');
define('LOGGED_IN_KEY',    'test-key');
define('NONCE_KEY',        'test-key');
define('AUTH_SALT',        'test-key');
define('SECURE_AUTH_SALT', 'test-key');
define('LOGGED_IN_SALT',   'test-key');
define('NONCE_SALT',       'test-key');

$table_prefix = getenv('WORDPRESS_TABLE_PREFIX') ?: 'wp_';

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Для тестирования
define('WP_ENVIRONMENT_TYPE', 'testing');
define('WP_TESTS_DIR', '/tmp/wordpress-tests-lib');
define('WP_CORE_DIR', '/var/www/html');

/* That's all, stop editing! Happy publishing. */

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';
