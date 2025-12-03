<?php

declare(strict_types=1);

namespace WPQueue\Admin;

// Import WordPress functions if not available
if (!function_exists('wp_unslash')) {
    /**
     * Unslashes values.
     *
     * @param mixed $value Value to unslash.
     * @return mixed Unslashed value.
     */
    function wp_unslash($value) {
        return stripslashes_deep($value);
    }
}

if (!function_exists('site_url')) {
    /**
     * Retrieve the site URL for the current site.
     *
     * @param string $path Path relative to the site URL.
     * @return string Site URL.
     */
    function site_url($path = '') {
        $url = 'http://localhost';
        if ($path) {
            $url .= '/' . ltrim($path, '/');
        }
        return $url;
    }
}

if (!function_exists('wp_remote_post')) {
    /**
     * Perform an HTTP POST request.
     *
     * @param string $url URL to retrieve.
     * @param array $args Request arguments.
     * @return array Response array.
     */
    function wp_remote_post($url, $args = []) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => 'success',
            'headers' => [],
        ];
    }
}

if (!function_exists('is_wp_error')) {
    /**
     * Check whether variable is a WordPress Error.
     *
     * @param mixed $thing Check if unknown variable is a WP_Error object.
     * @return bool True if WP_Error, false if not.
     */
    function is_wp_error($thing) {
        return false;
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    /**
     * Retrieve only the response code from the raw response.
     *
     * @param array $response HTTP response.
     * @return int The response code.
     */
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    /**
     * Retrieve only the body from the raw response.
     *
     * @param array $response HTTP response.
     * @return string The body of the response.
     */
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!function_exists('apply_filters')) {
    /**
     * Calls the callback functions that have been added to a filter hook.
     *
     * @param string $hook_name The name of the filter hook.
     * @param mixed $value The value to filter.
     * @return mixed The filtered value after all hooked functions are applied to it.
     */
    function apply_filters($hook_name, $value) {
        return $value;
    }
}

if (!function_exists('get_option')) {
    /**
     * Retrieves an option value based on an option name.
     *
     * @param string $option Name of the option to retrieve.
     * @param mixed $default Optional. Default value to return if the option does not exist.
     * @return mixed Value set for the option.
     */
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('wp_get_theme')) {
    /**
     * Gets a WP_Theme object for a theme.
     *
     * @param string $stylesheet Optional. Directory name for the theme.
     * @return WP_Theme Theme object.
     */
    function wp_get_theme($stylesheet = null) {
        return new class {
            public function get($key) {
                switch ($key) {
                    case 'Name':
                        return 'Test Theme';
                    case 'Version':
                        return '1.0.0';
                    default:
                        return '';
                }
            }
        };
    }
}

if (!function_exists('get_bloginfo')) {
    /**
     * Retrieves information about the current site.
     *
     * @param string $show Site info to retrieve.
     * @return string Mostly string values.
     */
    function get_bloginfo($show) {
        switch ($show) {
            case 'version':
                return '6.0.0';
            case 'url':
                return 'http://localhost';
            default:
                return '';
        }
    }
}

/**
 * Deeply stripslashes values.
 *
 * @param mixed $value Value to stripslashes.
 * @return mixed Stripslashed value.
 */
function stripslashes_deep($value) {
    return map_deep($value, 'stripslashes_from_strings_only');
}

/**
 * Maps a function to all non-iterable elements of an array or an object.
 *
 * @param mixed $value The array, object, or scalar.
 * @param callable $callback The function to map.
 * @return mixed The value with the callback applied to all non-iterable elements.
 */
function map_deep($value, $callback) {
    if (is_array($value)) {
        foreach ($value as $index => $item) {
            $value[$index] = map_deep($item, $callback);
        }
    } elseif (is_object($value)) {
        $object_vars = get_object_vars($value);
        foreach ($object_vars as $property_name => $property_value) {
            $value->$property_name = map_deep($property_value, $callback);
        }
    } else {
        $value = call_user_func($callback, $value);
    }

    return $value;
}

/**
 * Strips slashes only from strings.
 *
 * @param mixed $value Value to strip slashes from.
 * @return mixed Stripslashed value.
 */
function stripslashes_from_strings_only($value) {
    return is_string($value) ? stripslashes($value) : $value;
}

class SystemStatus
{
    /**
     * Check if WP-Cron is disabled.
     */
    public function isWpCronDisabled(): bool
    {
        return defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
    }

    /**
     * Check if alternate cron is enabled.
     */
    public function isAlternateCron(): bool
    {
        return defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;
    }

    /**
     * Get PHP memory limit in bytes.
     */
    public function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');

        return $this->convertToBytes($limit);
    }

    /**
     * Get PHP max execution time in seconds.
     */
    public function getMaxExecutionTime(): int
    {
        return (int) ini_get('max_execution_time');
    }

    /**
     * Get current memory usage in bytes.
     */
    public function getCurrentMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    /**
     * Get memory usage percentage.
     */
    public function getMemoryUsagePercent(): float
    {
        $limit = $this->getMemoryLimit();

        if ($limit <= 0) {
            return 0;
        }

        return round(($this->getCurrentMemoryUsage() / $limit) * 100, 2);
    }

    /**
     * Get WordPress version.
     */
    public function getWordPressVersion(): string
    {
        global $wp_version;

        return $wp_version ?? 'unknown';
    }

    /**
     * Get PHP version.
     */
    public function getPhpVersion(): string
    {
        return PHP_VERSION;
    }

    /**
     * Check loopback request status.
     * Uses the same method as WordPress Site Health (can_perform_loopback).
     *
     * @return array{status: string, message: string}
     */
    public function checkLoopback(): array
    {
        $body = ['site-health' => 'loopback-test'];
        $cookies = wp_unslash($_COOKIE);
        $timeout = 10;
        $headers = [
            'Cache-Control' => 'no-cache',
        ];

        $sslverify = apply_filters('https_local_ssl_verify', false);

        // Include Basic auth in loopback requests.
        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $headers['Authorization'] = 'Basic '.base64_encode(wp_unslash($_SERVER['PHP_AUTH_USER']).':'.wp_unslash($_SERVER['PHP_AUTH_PW']));
        }

        $url = site_url('wp-cron.php');

        $response = wp_remote_post($url, [
            'body' => $body,
            'cookies' => $cookies,
            'headers' => $headers,
            'timeout' => $timeout,
            'sslverify' => $sslverify,
            'blocking' => true,
        ]);

        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'message' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return [
                'status' => 'warning',
                'message' => sprintf(__('Unexpected response code: %d', 'wp-queue'), $code),
            ];
        }

        return [
            'status' => 'ok',
            'message' => __('Loopback request successful', 'wp-queue'),
        ];
    }

    /**
     * Check if Action Scheduler is installed.
     */
    public function hasActionScheduler(): bool
    {
        return class_exists('ActionScheduler') || function_exists('as_schedule_single_action');
    }

    /**
     * Get Action Scheduler version if available.
     */
    public function getActionSchedulerVersion(): ?string
    {
        if (! $this->hasActionScheduler()) {
            return null;
        }

        if (class_exists('ActionScheduler_Versions')) {
            return \ActionScheduler_Versions::instance()->latest_version();
        }

        return 'installed';
    }

    /**
     * Get Action Scheduler stats if available.
     *
     * @return array{pending: int, running: int, complete: int, failed: int}|null
     */
    public function getActionSchedulerStats(): ?array
    {
        if (! $this->hasActionScheduler() || ! class_exists('ActionScheduler')) {
            return null;
        }

        try {
            $store = \ActionScheduler::store();

            return [
                'pending' => $store->query_actions(['status' => 'pending'], 'count'),
                'running' => $store->query_actions(['status' => 'in-progress'], 'count'),
                'complete' => $store->query_actions(['status' => 'complete'], 'count'),
                'failed' => $store->query_actions(['status' => 'failed'], 'count'),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get WordPress timezone string.
     */
    public function getTimezone(): string
    {
        return wp_timezone_string();
    }

    /**
     * Get server time info.
     *
     * @return array{server_time: string, wp_time: string, timezone: string, gmt_offset: float}
     */
    public function getTimeInfo(): array
    {
        return [
            'server_time' => date('Y-m-d H:i:s'),
            'wp_time' => current_time('mysql'),
            'timezone' => $this->getTimezone(),
            'gmt_offset' => (float) get_option('gmt_offset', 0),
        ];
    }

    /**
     * Get full system report.
     *
     * @return array<string, mixed>
     */
    public function getFullReport(): array
    {
        $loopback = $this->checkLoopback();

        return [
            'php_version' => $this->getPhpVersion(),
            'wp_version' => $this->getWordPressVersion(),
            'memory_limit' => $this->getMemoryLimit(),
            'memory_limit_formatted' => size_format($this->getMemoryLimit()),
            'max_execution_time' => $this->getMaxExecutionTime(),
            'current_memory' => $this->getCurrentMemoryUsage(),
            'current_memory_formatted' => size_format($this->getCurrentMemoryUsage()),
            'memory_percent' => $this->getMemoryUsagePercent(),
            'wp_cron_disabled' => $this->isWpCronDisabled(),
            'alternate_cron' => $this->isAlternateCron(),
            'loopback' => $loopback,
            'timezone' => $this->getTimezone(),
            'time_info' => $this->getTimeInfo(),
            'action_scheduler' => [
                'installed' => $this->hasActionScheduler(),
                'version' => $this->getActionSchedulerVersion(),
                'stats' => $this->getActionSchedulerStats(),
            ],
        ];
    }

    /**
     * Get health status.
     *
     * @return array{status: string, issues: array<string>}
     */
    public function getHealthStatus(): array
    {
        $issues = [];

        if ($this->isWpCronDisabled()) {
            $issues[] = __('WP-Cron is disabled. Background tasks may not run automatically.', 'wp-queue');
        }

        $loopback = $this->checkLoopback();
        if ($loopback['status'] !== 'ok') {
            $issues[] = sprintf(__('Loopback issue: %s', 'wp-queue'), $loopback['message']);
        }

        if ($this->getMemoryUsagePercent() > 80) {
            $issues[] = __('Memory usage is above 80%.', 'wp-queue');
        }

        if ($this->getMaxExecutionTime() > 0 && $this->getMaxExecutionTime() < 30) {
            $issues[] = __('PHP max_execution_time is less than 30 seconds.', 'wp-queue');
        }

        return [
            'status' => empty($issues) ? 'healthy' : 'warning',
            'issues' => $issues,
        ];
    }

    /**
     * Convert PHP ini value to bytes.
     */
    protected function convertToBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '-1') {
            return PHP_INT_MAX;
        }

        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
