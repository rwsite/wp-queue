<?php

declare(strict_types=1);

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$GLOBALS['wp_options_store'] ??= [];
$GLOBALS['wp_site_options_store'] ??= [];
$GLOBALS['wp_transients_store'] ??= [];
$GLOBALS['wp_site_transients_store'] ??= [];
$GLOBALS['wp_cron_store'] ??= [];
$GLOBALS['wp_hooks'] ??= [];
$GLOBALS['wp_users_store'] ??= [
    1 => ['ID' => 1, 'user_login' => 'admin', 'caps' => ['manage_options' => true]],
];
$GLOBALS['wp_current_user_id'] ??= 1;
$GLOBALS['wp_rest_routes'] ??= [];

// Keep site options synced with standard options for tests
$GLOBALS['wp_site_options_store'] = &$GLOBALS['wp_options_store'];

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            private string $code = '',
            private string $message = '',
            private array $data = [],
        ) {}

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_data(): array
        {
            return $this->data;
        }
    }
}

class FakeWpdb
{
    public string $prefix = 'wp_';

    public string $options = 'wp_options';

    public string $last_error = '';

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $tables = [];

    public function get_charset_collate(): string
    {
        return 'utf8mb4_unicode_ci';
    }

    public function insert(string $table, array $data, array $format = []): bool
    {
        $this->ensureTable($table);

        if (! isset($data['id'])) {
            $data['id'] = count($this->tables[$table]) + 1;
        }

        $this->tables[$table][] = $data;

        return true;
    }

    public function get_results(string $query, string $output = ARRAY_A): array
    {
        $table = $this->extractTable($query);
        if (! $table) {
            return [];
        }

        $this->ensureTable($table);
        $rows = $this->tables[$table];

        if (preg_match("/status\s*=\s*'([^']+)'/i", $query, $match)) {
            $status = $match[1];
            $rows = array_values(array_filter($rows, fn ($row) => ($row['status'] ?? null) === $status));
        }

        $lower = strtolower($query);
        if (str_contains($lower, 'order by created_at desc')) {
            usort($rows, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        } elseif (str_contains($lower, 'order by created_at asc')) {
            usort($rows, fn ($a, $b) => strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));
        }

        if (str_contains($lower, 'select queue') && str_contains($lower, 'job_class')) {
            return array_map(static fn ($row) => [
                'queue' => $row['queue'] ?? '',
                'job_class' => $row['job_class'] ?? '',
                'status' => $row['status'] ?? '',
            ], $rows);
        }

        return $rows;
    }

    public function get_col(string $query): array
    {
        if (str_contains($query, "FROM {$this->options}")) {
            if (preg_match("/LIKE\s+'([^']+)'/i", $query, $match)) {
                $pattern = $match[1];

                return array_values(array_filter(array_keys($GLOBALS['wp_options_store']), function (string $key) use ($pattern): bool {
                    return $this->matchesLike($key, $pattern);
                }));
            }
        }

        return [];
    }

    public function prepare(string $query, mixed ...$args): string
    {
        $query = preg_replace('/%s/', "'%s'", $query);
        $escaped = array_map(fn ($arg) => is_string($arg) ? $this->escape($arg) : $arg, $args);

        return vsprintf($query, $escaped);
    }

    public function query(string $sql): int
    {
        if (str_contains($sql, "DELETE FROM {$this->options}")) {
            if (preg_match("/LIKE\s+'([^']+)'/i", $sql, $match)) {
                $pattern = $match[1];
                $removed = 0;

                foreach (array_keys($GLOBALS['wp_options_store']) as $key) {
                    if ($this->matchesLike($key, $pattern)) {
                        unset($GLOBALS['wp_options_store'][$key]);
                        $removed++;
                    }
                }

                return $removed;
            }
        }

        if (preg_match("/DELETE FROM\s+(\w+)\s+WHERE\s+created_at\s+<\s+'([^']+)'/i", $sql, $match)) {
            $table = $match[1];
            $cutoff = $match[2];
            $this->ensureTable($table);
            $before = count($this->tables[$table]);
            $this->tables[$table] = array_values(array_filter(
                $this->tables[$table],
                static fn ($row) => ($row['created_at'] ?? '') >= $cutoff,
            ));

            return $before - count($this->tables[$table]);
        }

        if (preg_match("/TRUNCATE TABLE\s+(\w+)/i", $sql, $match)) {
            $table = $match[1];
            $count = count($this->tables[$table] ?? []);
            $this->tables[$table] = [];

            return $count;
        }

        if (preg_match("/DROP TABLE IF EXISTS\s+(\w+)/i", $sql, $match)) {
            $table = $match[1];
            unset($this->tables[$table]);

            return 0;
        }

        return 0;
    }

    private function ensureTable(string $table): void
    {
        $this->tables[$table] ??= [];
    }

    private function extractTable(string $query): ?string
    {
        if (preg_match('/FROM\s+(\w+)/i', $query, $match)) {
            return $match[1];
        }

        return null;
    }

    private function matchesLike(string $subject, string $pattern): bool
    {
        $regex = '/^'.str_replace(['%', '_'], ['.*', '.'], preg_quote($pattern, '/')).'$/i';

        return (bool) preg_match($regex, $subject);
    }

    private function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}

global $wpdb;
$wpdb = new FakeWpdb();

if (! function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        $GLOBALS['wp_hooks'][$hook][$priority][] = ['callback' => $callback, 'args' => $acceptedArgs];
        ksort($GLOBALS['wp_hooks'][$hook]);
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void
    {
        if (! isset($GLOBALS['wp_hooks'][$hook])) {
            return;
        }

        foreach ($GLOBALS['wp_hooks'][$hook] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback['callback'], array_slice($args, 0, $callback['args']));
            }
        }
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (! isset($GLOBALS['wp_hooks'][$hook])) {
            return $value;
        }

        foreach ($GLOBALS['wp_hooks'][$hook] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func_array($callback['callback'], array_merge([$value], array_slice($args, 0, $callback['args'] - 1)));
            }
        }

        return $value;
    }
}

if (! function_exists('__')) {
    function __(string $text): string
    {
        return $text;
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text): string
    {
        return $text;
    }
}

if (! function_exists('size_format')) {
    function size_format(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 2).' MB';
    }
}

if (! function_exists('home_url')) {
    function home_url(): string
    {
        return 'https://example.com';
    }
}

if (! function_exists('site_url')) {
    function site_url(): string
    {
        return 'https://example.com/wp-cron.php';
    }
}

if (! function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.com/wp-admin/'.ltrim($path, '/');
    }
}

if (! function_exists('current_time')) {
    function current_time(string $type = 'mysql'): string
    {
        return date($type === 'mysql' ? 'Y-m-d H:i:s' : $type);
    }
}

if (! function_exists('wp_date')) {
    function wp_date(string $format): string
    {
        return date($format);
    }
}

if (! function_exists('wp_timezone_string')) {
    function wp_timezone_string(): string
    {
        return 'UTC';
    }
}

if (! function_exists('human_time_diff')) {
    function human_time_diff(int $from, int $to): string
    {
        $diff = abs($to - $from);

        return $diff.' seconds';
    }
}

if (! function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed
    {
        return $GLOBALS['wp_options_store'][$key] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $key, mixed $value): bool
    {
        $GLOBALS['wp_options_store'][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $key): bool
    {
        unset($GLOBALS['wp_options_store'][$key]);

        return true;
    }
}

if (! function_exists('get_site_option')) {
    function get_site_option(string $key, mixed $default = false): mixed
    {
        return $GLOBALS['wp_site_options_store'][$key] ?? $default;
    }
}

if (! function_exists('update_site_option')) {
    function update_site_option(string $key, mixed $value): bool
    {
        $GLOBALS['wp_site_options_store'][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_site_option')) {
    function delete_site_option(string $key): bool
    {
        unset($GLOBALS['wp_site_options_store'][$key]);

        return true;
    }
}

if (! function_exists('get_site_transient')) {
    function get_site_transient(string $key): mixed
    {
        if (! isset($GLOBALS['wp_site_transients_store'][$key])) {
            return false;
        }

        $entry = $GLOBALS['wp_site_transients_store'][$key];
        if ($entry['expires'] !== 0 && $entry['expires'] < time()) {
            unset($GLOBALS['wp_site_transients_store'][$key]);

            return false;
        }

        return $entry['value'];
    }
}

if (! function_exists('set_site_transient')) {
    function set_site_transient(string $key, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['wp_site_transients_store'][$key] = [
            'value' => $value,
            'expires' => $expiration > 0 ? time() + $expiration : 0,
        ];

        return true;
    }
}

if (! function_exists('delete_site_transient')) {
    function delete_site_transient(string $key): bool
    {
        unset($GLOBALS['wp_site_transients_store'][$key]);

        return true;
    }
}

if (! function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []): array
    {
        return ['response' => ['code' => 200], 'body' => ''];
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int
    {
        return $response['response']['code'] ?? 0;
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (! function_exists('wp_set_current_user')) {
    function wp_set_current_user(int $userId): ?object
    {
        $GLOBALS['wp_current_user_id'] = $userId;

        return isset($GLOBALS['wp_users_store'][$userId]) ? (object) $GLOBALS['wp_users_store'][$userId] : null;
    }
}

if (! function_exists('wp_create_user')) {
    function wp_create_user(string $login, string $password, string $email): int
    {
        $nextId = empty($GLOBALS['wp_users_store']) ? 1 : (max(array_keys($GLOBALS['wp_users_store'])) + 1);
        $GLOBALS['wp_users_store'][$nextId] = ['ID' => $nextId, 'user_login' => $login, 'caps' => []];

        return $nextId;
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        $userId = $GLOBALS['wp_current_user_id'] ?? 0;
        if ($userId === 0) {
            return false;
        }

        $caps = $GLOBALS['wp_users_store'][$userId]['caps'] ?? [];

        return ! empty($caps[$capability]);
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return $GLOBALS['wp_current_user_id'] ?? 0;
    }
}

if (! function_exists('is_admin')) {
    function is_admin(): bool
    {
        return false;
    }
}

if (! function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string
    {
        return dirname($file).'/';
    }
}

if (! function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string
    {
        return 'https://example.com/'.basename(dirname($file)).'/';
    }
}

if (! function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, callable $callback): void {}
}

if (! function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, callable $callback): void {}
}

if (! function_exists('_doing_it_wrong')) {
    function _doing_it_wrong(string $function, string $message, string $version): void
    {
        error_log("$function was called incorrectly: $message (since $version)");
    }
}

if (! function_exists('dbDelta')) {
    function dbDelta(string $sql): void {}
}

if (! function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook): int|false
    {
        foreach ($GLOBALS['wp_cron_store'] as $timestamp => $events) {
            if (isset($events[$hook])) {
                return (int) $timestamp;
            }
        }

        return false;
    }
}

if (! function_exists('_get_cron_array')) {
    function _get_cron_array(): array
    {
        return $GLOBALS['wp_cron_store'];
    }
}

if (! function_exists('wp_schedule_event')) {
    function wp_schedule_event(int $timestamp, string $schedule, string $hook, array $args = []): bool
    {
        $sig = md5(serialize($args));
        $GLOBALS['wp_cron_store'][$timestamp][$hook][$sig] = [
            'schedule' => $schedule,
            'interval' => wp_get_schedules()[$schedule]['interval'] ?? 0,
            'args' => $args,
        ];

        return true;
    }
}

if (! function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event(int $timestamp, string $hook, array $args = []): bool
    {
        $sig = md5(serialize($args));
        $GLOBALS['wp_cron_store'][$timestamp][$hook][$sig] = [
            'schedule' => false,
            'interval' => null,
            'args' => $args,
        ];

        return true;
    }
}

if (! function_exists('wp_unschedule_event')) {
    function wp_unschedule_event(int $timestamp, string $hook, array $args = []): bool
    {
        $sig = md5(serialize($args));
        if (isset($GLOBALS['wp_cron_store'][$timestamp][$hook][$sig])) {
            unset($GLOBALS['wp_cron_store'][$timestamp][$hook][$sig]);
            if (empty($GLOBALS['wp_cron_store'][$timestamp][$hook])) {
                unset($GLOBALS['wp_cron_store'][$timestamp][$hook]);
            }
            if (empty($GLOBALS['wp_cron_store'][$timestamp])) {
                unset($GLOBALS['wp_cron_store'][$timestamp]);
            }

            return true;
        }

        return false;
    }
}

if (! function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook(string $hook, array $args = []): int
    {
        $removed = 0;
        foreach (array_keys($GLOBALS['wp_cron_store']) as $timestamp) {
            $removed += (int) wp_unschedule_event((int) $timestamp, $hook, $args);
        }

        return $removed;
    }
}

if (! function_exists('wp_get_schedules')) {
    function wp_get_schedules(): array
    {
        return [
            'hourly' => ['interval' => HOUR_IN_SECONDS, 'display' => 'Hourly'],
            'twicedaily' => ['interval' => 12 * HOUR_IN_SECONDS, 'display' => 'Twice Daily'],
            'daily' => ['interval' => DAY_IN_SECONDS, 'display' => 'Daily'],
            'min' => ['interval' => MINUTE_IN_SECONDS, 'display' => 'Every Minute'],
        ];
    }
}

if (! function_exists('wp_get_scheduled_event')) {
    function wp_get_scheduled_event(string $hook, array $args = []): array|false
    {
        foreach ($GLOBALS['wp_cron_store'] as $timestamp => $hooks) {
            if (isset($hooks[$hook])) {
                foreach ($hooks[$hook] as $event) {
                    return [
                        'hook' => $hook,
                        'timestamp' => (int) $timestamp,
                        'schedule' => $event['schedule'],
                        'args' => $event['args'],
                    ];
                }
            }
        }

        return false;
    }
}

if (! function_exists('get_userdata')) {
    function get_userdata(int $userId): ?object
    {
        return isset($GLOBALS['wp_users_store'][$userId]) ? (object) $GLOBALS['wp_users_store'][$userId] : null;
    }
}

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private array $params = [];

        public function __construct(private string $method, private string $route) {}

        public function get_method(): string
        {
            return strtoupper($this->method);
        }

        public function get_route(): string
        {
            return $this->route;
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }

        public function add_url_params(array $params): void
        {
            $this->params = array_merge($params, $this->params);
        }
    }
}

if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        private int $status;

        public function __construct(private mixed $data = null, int $status = 200)
        {
            $this->status = $status;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function set_status(int $status): void
        {
            $this->status = $status;
        }

        public function get_data(): mixed
        {
            return $this->data;
        }
    }
}

if (! class_exists('WP_REST_Server')) {
    class WP_REST_Server {}
}

if (! function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args): void
    {
        $methods = $args['methods'] ?? 'GET';
        $methods = is_array($methods) ? $methods : explode('|', $methods);
        $pattern = '#^/'.trim($namespace, '/').$route.'$#';

        $GLOBALS['wp_rest_routes'][] = [
            'pattern' => $pattern,
            'methods' => array_map('strtoupper', $methods),
            'callback' => $args['callback'],
            'permission_callback' => $args['permission_callback'] ?? null,
        ];
    }
}

if (! function_exists('rest_do_request')) {
    function rest_do_request(WP_REST_Request $request): WP_REST_Response
    {
        foreach ($GLOBALS['wp_rest_routes'] as $route) {
            if (! in_array($request->get_method(), $route['methods'], true)) {
                continue;
            }

            if (! preg_match($route['pattern'], $request->get_route(), $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (! is_int($key)) {
                    $params[$key] = $value;
                }
            }

            if (! empty($params)) {
                foreach ($params as $key => $value) {
                    $request->set_param($key, $value);
                }
            }

            $permission = $route['permission_callback'];
            if ($permission) {
                $allowed = call_user_func($permission, $request);
                if ($allowed instanceof WP_Error) {
                    return new WP_REST_Response([
                        'code' => $allowed->get_error_code(),
                        'message' => $allowed->get_error_message(),
                    ], $allowed->get_error_data()['status'] ?? 403);
                }

                if ($allowed === false) {
                    $status = get_current_user_id() === 0 ? 401 : 403;

                    return new WP_REST_Response(['message' => 'rest_forbidden'], $status);
                }
            }

            $response = call_user_func($route['callback'], $request);

            if ($response instanceof WP_REST_Response) {
                return $response;
            }

            if ($response instanceof WP_Error) {
                return new WP_REST_Response([
                    'code' => $response->get_error_code(),
                    'message' => $response->get_error_message(),
                ], $response->get_error_data()['status'] ?? 500);
            }

            return new WP_REST_Response($response ?? [], 200);
        }

        return new WP_REST_Response(['message' => 'not_found'], 404);
    }
}
