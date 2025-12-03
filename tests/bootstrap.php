<?php

declare(strict_types=1);

// WordPress stubs for testing without WordPress
if (! defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load Brain Monkey
\Brain\Monkey\setUp();

// Define common WordPress constants if not defined
if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (! defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}
