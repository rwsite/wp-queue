<?php

declare(strict_types=1);

namespace WPQueue\Admin;

use WPQueue\WPQueue;

class AdminPage
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('load-tools_page_wp-queue', [$this, 'addHelpTabs']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'tools.php',
            __('WP Queue', 'wp-queue'),
            __('WP Queue', 'wp-queue'),
            'manage_options',
            'wp-queue',
            [$this, 'renderPage'],
        );
    }

    public function addHelpTabs(): void
    {
        $screen = get_current_screen();

        if (! $screen) {
            return;
        }

        $screen->add_help_tab([
            'id' => 'wp-queue-about',
            'title' => __('About WP Queue', 'wp-queue'),
            'content' => $this->getHelpTabAbout(),
        ]);

        $screen->add_help_tab([
            'id' => 'wp-queue-usage',
            'title' => __('Usage', 'wp-queue'),
            'content' => $this->getHelpTabUsage(),
        ]);

        $screen->add_help_tab([
            'id' => 'wp-queue-cli',
            'title' => __('WP-CLI', 'wp-queue'),
            'content' => $this->getHelpTabCli(),
        ]);

        $screen->set_help_sidebar($this->getHelpSidebar());
    }

    protected function getHelpTabAbout(): string
    {
        return '<h2>'.__('About WP Queue', 'wp-queue').' '.WP_QUEUE_VERSION.'</h2>'.
            '<p>'.__('WP Queue is a scalable queue manager for WordPress background processing. It works by triggering hook events for execution at a specific time or in the future. Scheduled actions can also be scheduled for regular execution.', 'wp-queue').'</p>'.
            '<h3>'.__('Features', 'wp-queue').'</h3>'.
            '<ul>'.
            '<li>'.__('Laravel-style job dispatching with PHP 8 attributes', 'wp-queue').'</li>'.
            '<li>'.__('Multiple queue drivers (Database, Sync, Redis)', 'wp-queue').'</li>'.
            '<li>'.__('Automatic retries with exponential backoff', 'wp-queue').'</li>'.
            '<li>'.__('Job scheduling with cron expressions', 'wp-queue').'</li>'.
            '<li>'.__('WP-Cron monitoring and management', 'wp-queue').'</li>'.
            '<li>'.__('REST API for external integrations', 'wp-queue').'</li>'.
            '</ul>';
    }

    protected function getHelpTabUsage(): string
    {
        return '<h2>'.__('Basic Usage', 'wp-queue').'</h2>'.
            '<h3>'.__('Creating a Job', 'wp-queue').'</h3>'.
            '<pre><code>use WPQueue\Jobs\Job;
use WPQueue\Attributes\Queue;
use WPQueue\Attributes\Retries;

#[Queue(\'emails\')]
#[Retries(3)]
class SendEmailJob extends Job
{
    public function __construct(
        private readonly string $email
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        wp_mail($this->email, \'Subject\', \'Body\');
    }
}</code></pre>'.
            '<h3>'.__('Dispatching', 'wp-queue').'</h3>'.
            '<pre><code>use WPQueue\WPQueue;

// Dispatch immediately
WPQueue::dispatch(new SendEmailJob(\'user@example.com\'));

// Dispatch with delay
WPQueue::dispatch(new SendEmailJob(\'user@example.com\'))
    ->delay(300); // 5 minutes</code></pre>';
    }

    protected function getHelpTabCli(): string
    {
        return '<h2>'.__('WP-CLI Commands', 'wp-queue').'</h2>'.
            '<p>'.__('Run', 'wp-queue').' <code>wp help queue</code> '.__('to get a list of available commands.', 'wp-queue').'</p>'.
            '<pre><code># Queue commands
wp queue status              # Show queue status
wp queue work [queue]        # Process jobs from queue
wp queue clear [queue]       # Clear all jobs from queue
wp queue failed              # List failed jobs
wp queue retry [job_id]      # Retry a failed job

# Cron commands
wp queue cron list           # List all cron events
wp queue cron run &lt;hook&gt;     # Run a cron event now
wp queue cron delete &lt;hook&gt;  # Delete a cron event
wp queue cron pause &lt;hook&gt;   # Pause a cron event
wp queue cron resume &lt;hook&gt;  # Resume a paused event

# System commands
wp queue system              # Show system status</code></pre>';
    }

    protected function getHelpSidebar(): string
    {
        return '<p><strong>'.__('For more information:', 'wp-queue').'</strong></p>'.
            '<p><a href="https://github.com/rwsite/wp-queue" target="_blank">'.__('GitHub Repository', 'wp-queue').'</a></p>'.
            '<p><a href="https://github.com/rwsite/wp-queue/issues" target="_blank">'.__('Report Issues', 'wp-queue').'</a></p>'.
            '<p><a href="https://rwsite.ru" target="_blank">'.__('Author Website', 'wp-queue').'</a></p>';
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'tools_page_wp-queue') {
            return;
        }

        wp_enqueue_style(
            'wp-queue-admin',
            WP_QUEUE_URL.'assets/css/admin.css',
            [],
            WP_QUEUE_VERSION,
        );

        wp_enqueue_script(
            'wp-queue-admin',
            WP_QUEUE_URL.'assets/js/admin.js',
            ['jquery'],
            WP_QUEUE_VERSION,
            true,
        );

        wp_localize_script('wp-queue-admin', 'wpQueue', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('wp-queue/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'confirm_clear' => __('Are you sure you want to clear this queue?', 'wp-queue'),
                'confirm_pause' => __('Pause this queue?', 'wp-queue'),
                'success' => __('Action completed successfully', 'wp-queue'),
                'error' => __('An error occurred', 'wp-queue'),
            ],
        ]);

        // Добавляем inline script для подсветки активного пункта меню
        wp_add_inline_script('wp-queue-admin', "
            jQuery(document).ready(function($) {
                // Подсвечиваем пункт меню WP Queue как активный
                var currentUrl = window.location.href;
                var wpQueueMenuItem = $('a[href*=\"page=wp-queue\"]');
                
                if (currentUrl.includes('page=wp-queue')) {
                    // Удаляем класс current у всех пунктов подменю Инструменты
                    $('#menu-tools ul.wp-submenu li').removeClass('current');
                    
                    // Добавляем класс current к пункту WP Queue
                    wpQueueMenuItem.parent().addClass('current');
                    
                    // Также подсвечиваем родительский пункт меню
                    $('#menu-tools').addClass('wp-has-current-submenu wp-menu-open');
                    $('#menu-tools > a').addClass('wp-has-current-submenu');
                }
            });
        ");
    }

    public function renderPage(): void
    {
        $tab = sanitize_key($_GET['tab'] ?? 'dashboard');

        ?>
        <div class="wrap wp-queue-admin">
            <h1><?php echo esc_html__('WP Queue Dashboard', 'wp-queue'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=wp-queue&tab=dashboard" class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Dashboard', 'wp-queue'); ?>
                </a>
                <a href="?page=wp-queue&tab=jobs" class="nav-tab <?php echo $tab === 'jobs' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Scheduled Jobs', 'wp-queue'); ?>
                </a>
                <a href="?page=wp-queue&tab=logs" class="nav-tab <?php echo $tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Logs', 'wp-queue'); ?>
                </a>
                <a href="?page=wp-queue&tab=cron" class="nav-tab <?php echo $tab === 'cron' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('WP-Cron', 'wp-queue'); ?>
                </a>
                <a href="?page=wp-queue&tab=system" class="nav-tab <?php echo $tab === 'system' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('System', 'wp-queue'); ?>
                </a>
            </nav>

            <div class="wp-queue-content">
                <?php
                match ($tab) {
                    'jobs' => $this->renderJobsTab(),
                    'logs' => $this->renderLogsTab(),
                    'cron' => $this->renderCronTab(),
                    'system' => $this->renderSystemTab(),
                    default => $this->renderDashboardTab(),
                };
        ?>
            </div>
        </div>
        <?php
    }

    protected function renderDashboardTab(): void
    {
        $metrics = WPQueue::logs()->metrics();
        $queues = $this->getQueuesStatus();

        ?>
        <div class="wp-queue-dashboard">
            <div class="wp-queue-stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo esc_html((string) $this->getTotalPending()); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Pending', 'wp-queue'); ?></span>
                </div>
                <div class="stat-card stat-running">
                    <span class="stat-number"><?php echo esc_html((string) $this->getRunningCount()); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Running', 'wp-queue'); ?></span>
                </div>
                <div class="stat-card stat-completed">
                    <span class="stat-number"><?php echo esc_html((string) $metrics['completed']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Completed', 'wp-queue'); ?></span>
                </div>
                <div class="stat-card stat-failed">
                    <span class="stat-number"><?php echo esc_html((string) $metrics['failed']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Failed', 'wp-queue'); ?></span>
                </div>
            </div>

            <h2><?php echo esc_html__('Queues', 'wp-queue'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Queue', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Jobs', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Status', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Actions', 'wp-queue'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queues as $name => $data) { ?>
                        <tr>
                            <td><strong><?php echo esc_html($name); ?></strong></td>
                            <td><?php echo esc_html((string) $data['size']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($data['status']); ?>">
                                    <?php echo esc_html(ucfirst($data['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($data['status'] === 'paused') { ?>
                                    <button class="button button-small wp-queue-action" data-action="resume" data-queue="<?php echo esc_attr($name); ?>">
                                        <?php echo esc_html__('Resume', 'wp-queue'); ?>
                                    </button>
                                <?php } else { ?>
                                    <button class="button button-small wp-queue-action" data-action="pause" data-queue="<?php echo esc_attr($name); ?>">
                                        <?php echo esc_html__('Pause', 'wp-queue'); ?>
                                    </button>
                                <?php } ?>
                                <button class="button button-small wp-queue-action" data-action="clear" data-queue="<?php echo esc_attr($name); ?>">
                                    <?php echo esc_html__('Clear', 'wp-queue'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Recent Activity', 'wp-queue'); ?></h2>
            <?php $this->renderRecentLogs(10); ?>
        </div>
        <?php
    }

    protected function renderJobsTab(): void
    {
        $scheduler = WPQueue::scheduler();
        $jobs = $scheduler->getJobs();

        ?>
        <div class="wp-queue-jobs">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Job', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Schedule', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Queue', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Next Run', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Actions', 'wp-queue'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)) { ?>
                        <tr>
                            <td colspan="5"><?php echo esc_html__('No scheduled jobs found.', 'wp-queue'); ?></td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($jobs as $jobClass => $scheduled) { ?>
                            <?php
                            $hook = 'wp_queue_'.strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (new \ReflectionClass($jobClass))->getShortName()));
                            $nextRun = wp_next_scheduled($hook);
                            ?>
                            <tr>
                                <td><code><?php echo esc_html($jobClass); ?></code></td>
                                <td><?php echo esc_html($scheduled->getInterval() ?: '-'); ?></td>
                                <td><?php echo esc_html($scheduled->getQueue() ?? 'default'); ?></td>
                                <td>
                                    <?php if ($nextRun) { ?>
                                        <?php echo esc_html(human_time_diff($nextRun, time())); ?>
                                        <?php echo $nextRun > time() ? esc_html__('from now', 'wp-queue') : esc_html__('ago (overdue)', 'wp-queue'); ?>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                                <td>
                                    <button class="button button-small wp-queue-run-job" data-job="<?php echo esc_attr($jobClass); ?>">
                                        <?php echo esc_html__('Run Now', 'wp-queue'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    protected function renderLogsTab(): void
    {
        $filter = sanitize_key($_GET['filter'] ?? 'all');
        $logs = match ($filter) {
            'failed' => WPQueue::logs()->failed(),
            'completed' => WPQueue::logs()->completed(),
            default => WPQueue::logs()->recent(200),
        };

        ?>
        <div class="wp-queue-logs">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="?page=wp-queue&tab=logs&filter=all" class="button <?php echo $filter === 'all' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('All', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=logs&filter=completed" class="button <?php echo $filter === 'completed' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('Completed', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=logs&filter=failed" class="button <?php echo $filter === 'failed' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('Failed', 'wp-queue'); ?>
                    </a>
                </div>
                <div class="alignright">
                    <button class="button wp-queue-clear-logs">
                        <?php echo esc_html__('Clear Old Logs', 'wp-queue'); ?>
                    </button>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 150px;"><?php echo esc_html__('Time', 'wp-queue'); ?></th>
                        <th style="width: 80px;"><?php echo esc_html__('Status', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Job', 'wp-queue'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Queue', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Message', 'wp-queue'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) { ?>
                        <tr>
                            <td colspan="5"><?php echo esc_html__('No logs found.', 'wp-queue'); ?></td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($logs as $log) { ?>
                            <tr>
                                <td><?php echo esc_html(wp_date('Y-m-d H:i:s', $log['timestamp'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($log['status']); ?>">
                                        <?php echo esc_html($log['status']); ?>
                                    </span>
                                </td>
                                <td><code><?php echo esc_html($log['job_class']); ?></code></td>
                                <td><?php echo esc_html($log['queue']); ?></td>
                                <td><?php echo esc_html($log['message'] ?? '-'); ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    protected function renderRecentLogs(int $limit): void
    {
        $logs = WPQueue::logs()->recent($limit);

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Time', 'wp-queue'); ?></th>
                    <th><?php echo esc_html__('Status', 'wp-queue'); ?></th>
                    <th><?php echo esc_html__('Job', 'wp-queue'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)) { ?>
                    <tr>
                        <td colspan="3"><?php echo esc_html__('No recent activity.', 'wp-queue'); ?></td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($logs as $log) { ?>
                        <tr>
                            <td><?php echo esc_html(wp_date('H:i:s', $log['timestamp'])); ?></td>
                            <td>
                                <?php
                                $icon = match ($log['status']) {
                                    'completed' => '✓',
                                    'failed' => '✗',
                                    'retrying' => '↻',
                                    default => '⟳',
                                };
                        ?>
                                <span class="log-icon log-<?php echo esc_attr($log['status']); ?>"><?php echo esc_html($icon); ?></span>
                                <?php echo esc_html($log['status']); ?>
                            </td>
                            <td><code><?php echo esc_html(class_basename($log['job_class'])); ?></code></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * @return array<string, array{size: int, status: string}>
     */
    protected function getQueuesStatus(): array
    {
        global $wpdb;

        $queues = ['default' => ['size' => 0, 'status' => 'idle']];

        // Find all queue options
        $results = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp_queue_jobs_%'",
        );

        foreach ($results as $optionName) {
            $queueName = str_replace('wp_queue_jobs_', '', $optionName);
            $jobs = get_site_option($optionName, []);

            $status = 'idle';
            if (WPQueue::isPaused($queueName)) {
                $status = 'paused';
            } elseif (WPQueue::isProcessing($queueName)) {
                $status = 'running';
            } elseif (! empty($jobs)) {
                $status = 'pending';
            }

            $queues[$queueName] = [
                'size' => count($jobs),
                'status' => $status,
            ];
        }

        return $queues;
    }

    protected function getTotalPending(): int
    {
        $total = 0;
        foreach ($this->getQueuesStatus() as $data) {
            $total += $data['size'];
        }

        return $total;
    }

    protected function getRunningCount(): int
    {
        $count = 0;
        foreach ($this->getQueuesStatus() as $data) {
            if ($data['status'] === 'running') {
                $count++;
            }
        }

        return $count;
    }

    protected function renderCronTab(): void
    {
        $monitor = new CronMonitor();
        $filter = sanitize_key($_GET['filter'] ?? 'all');

        $events = match ($filter) {
            'wordpress' => array_filter($monitor->getAllEvents(), fn ($e) => $e['source'] === 'wordpress'),
            'woocommerce' => array_filter($monitor->getAllEvents(), fn ($e) => $e['source'] === 'woocommerce'),
            'wp-queue' => array_filter($monitor->getAllEvents(), fn ($e) => $e['source'] === 'wp-queue'),
            'plugin' => array_filter($monitor->getAllEvents(), fn ($e) => $e['source'] === 'plugin'),
            default => $monitor->getAllEvents(),
        };

        $stats = $monitor->getStats();

        ?>
        <div class="wp-queue-cron">
            <div class="wp-queue-stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo esc_html((string) $stats['total']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Total Events', 'wp-queue'); ?></span>
                </div>
                <div class="stat-card stat-failed">
                    <span class="stat-number"><?php echo esc_html((string) $stats['overdue']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Overdue', 'wp-queue'); ?></span>
                </div>
                <?php foreach ($stats['by_source'] as $source => $count) { ?>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo esc_html((string) $count); ?></span>
                        <span class="stat-label"><?php echo esc_html(ucfirst($source)); ?></span>
                    </div>
                <?php } ?>
            </div>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="?page=wp-queue&tab=cron&filter=all" class="button <?php echo $filter === 'all' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('All', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=cron&filter=wordpress" class="button <?php echo $filter === 'wordpress' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('WordPress', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=cron&filter=woocommerce" class="button <?php echo $filter === 'woocommerce' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('WooCommerce', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=cron&filter=wp-queue" class="button <?php echo $filter === 'wp-queue' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('WP Queue', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=cron&filter=plugin" class="button <?php echo $filter === 'plugin' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('Plugins', 'wp-queue'); ?>
                    </a>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Hook', 'wp-queue'); ?></th>
                        <th style="width: 120px;"><?php echo esc_html__('Next Run', 'wp-queue'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Schedule', 'wp-queue'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Source', 'wp-queue'); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__('Actions', 'wp-queue'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)) { ?>
                        <tr>
                            <td colspan="5"><?php echo esc_html__('No cron events found.', 'wp-queue'); ?></td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($events as $event) { ?>
                            <tr class="<?php echo $event['is_overdue'] ? 'wp-queue-overdue' : ''; ?>">
                                <td>
                                    <code><?php echo esc_html($event['hook']); ?></code>
                                    <?php if (! empty($event['args'])) { ?>
                                        <br><small><?php echo esc_html(wp_json_encode($event['args'])); ?></small>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($event['is_overdue']) { ?>
                                        <span class="status-badge status-failed"><?php echo esc_html__('Overdue', 'wp-queue'); ?></span>
                                    <?php } else { ?>
                                        <?php echo esc_html($event['next_run']); ?>
                                    <?php } ?>
                                </td>
                                <td><?php echo esc_html($event['schedule'] ?: __('Single', 'wp-queue')); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($event['source']); ?>">
                                        <?php echo esc_html(ucfirst($event['source'])); ?>
                                    </span>
                                </td>
                                <td class="wp-queue-cron-actions">
                                    <button class="button button-small wp-queue-cron-run" 
                                            data-hook="<?php echo esc_attr($event['hook']); ?>"
                                            data-args="<?php echo esc_attr(wp_json_encode($event['args'])); ?>"
                                            title="<?php echo esc_attr__('Run Now', 'wp-queue'); ?>">
                                        <?php echo esc_html__('Run', 'wp-queue'); ?>
                                    </button>
                                    <button class="button button-small wp-queue-cron-edit" 
                                            data-hook="<?php echo esc_attr($event['hook']); ?>"
                                            data-timestamp="<?php echo esc_attr((string) $event['timestamp']); ?>"
                                            data-args="<?php echo esc_attr(wp_json_encode($event['args'])); ?>"
                                            data-schedule="<?php echo esc_attr($event['schedule'] ?: 'single'); ?>"
                                            title="<?php echo esc_attr__('Edit Schedule', 'wp-queue'); ?>">
                                        <?php echo esc_html__('Edit', 'wp-queue'); ?>
                                    </button>
                                    <button class="button button-small wp-queue-cron-pause" 
                                            data-hook="<?php echo esc_attr($event['hook']); ?>"
                                            data-timestamp="<?php echo esc_attr((string) $event['timestamp']); ?>"
                                            data-args="<?php echo esc_attr(wp_json_encode($event['args'])); ?>"
                                            title="<?php echo esc_attr__('Pause', 'wp-queue'); ?>">
                                        <?php echo esc_html__('Pause', 'wp-queue'); ?>
                                    </button>
                                    <button class="button button-small wp-queue-cron-delete" 
                                            data-hook="<?php echo esc_attr($event['hook']); ?>"
                                            data-timestamp="<?php echo esc_attr((string) $event['timestamp']); ?>"
                                            data-args="<?php echo esc_attr(wp_json_encode($event['args'])); ?>"
                                            title="<?php echo esc_attr__('Delete', 'wp-queue'); ?>">
                                        <?php echo esc_html__('Delete', 'wp-queue'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>

            <?php $paused = $monitor->getPaused(); ?>
            <?php if (! empty($paused)) { ?>
                <h2><?php echo esc_html__('Paused Events', 'wp-queue'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Hook', 'wp-queue'); ?></th>
                            <th><?php echo esc_html__('Schedule', 'wp-queue'); ?></th>
                            <th><?php echo esc_html__('Paused At', 'wp-queue'); ?></th>
                            <th style="width: 100px;"><?php echo esc_html__('Actions', 'wp-queue'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paused as $event) { ?>
                            <tr>
                                <td><code><?php echo esc_html($event['hook']); ?></code></td>
                                <td><?php echo esc_html($event['schedule'] ?: __('Single', 'wp-queue')); ?></td>
                                <td><?php echo esc_html(wp_date('Y-m-d H:i:s', $event['paused_at'])); ?></td>
                                <td>
                                    <button class="button button-small wp-queue-cron-resume" 
                                            data-hook="<?php echo esc_attr($event['hook']); ?>"
                                            data-args="<?php echo esc_attr(wp_json_encode($event['args'])); ?>">
                                        <?php echo esc_html__('Resume', 'wp-queue'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>

            <h2><?php echo esc_html__('Registered Schedules', 'wp-queue'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Name', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Interval', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Display', 'wp-queue'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monitor->getSchedules() as $name => $schedule) { ?>
                        <tr>
                            <td><code><?php echo esc_html($name); ?></code></td>
                            <td><?php echo esc_html(human_time_diff(0, $schedule['interval'])); ?></td>
                            <td><?php echo esc_html($schedule['display']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <!-- Edit Modal -->
            <div id="wp-queue-edit-modal" class="wp-queue-modal" style="display:none;">
                <div class="wp-queue-modal-content">
                    <h2><?php echo esc_html__('Edit Cron Event', 'wp-queue'); ?></h2>
                    <form id="wp-queue-edit-form">
                        <input type="hidden" name="hook" id="edit-hook">
                        <input type="hidden" name="timestamp" id="edit-timestamp">
                        <input type="hidden" name="args" id="edit-args">
                        
                        <p>
                            <label for="edit-schedule"><strong><?php echo esc_html__('Schedule', 'wp-queue'); ?></strong></label><br>
                            <select name="schedule" id="edit-schedule" class="regular-text">
                                <option value="single"><?php echo esc_html__('Single (run once)', 'wp-queue'); ?></option>
                                <?php foreach ($monitor->getSchedules() as $name => $schedule) { ?>
                                    <option value="<?php echo esc_attr($name); ?>">
                                        <?php echo esc_html($schedule['display']); ?> (<?php echo esc_html($name); ?>)
                                    </option>
                                <?php } ?>
                            </select>
                        </p>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php echo esc_html__('Save Changes', 'wp-queue'); ?></button>
                            <button type="button" class="button wp-queue-modal-close"><?php echo esc_html__('Cancel', 'wp-queue'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    protected function renderSystemTab(): void
    {
        $status = new SystemStatus();
        $report = $status->getFullReport();
        $health = $status->getHealthStatus();

        ?>
        <div class="wp-queue-system">
            <?php if ($health['status'] !== 'healthy') { ?>
                <div class="notice notice-warning">
                    <p><strong><?php echo esc_html__('System Issues Detected:', 'wp-queue'); ?></strong></p>
                    <ul>
                        <?php foreach ($health['issues'] as $issue) { ?>
                            <li><?php echo esc_html($issue); ?></li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } else { ?>
                <div class="notice notice-success">
                    <p><?php echo esc_html__('All systems are healthy.', 'wp-queue'); ?></p>
                </div>
            <?php } ?>

            <h2><?php echo esc_html__('Environment', 'wp-queue'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('PHP Version', 'wp-queue'); ?></th>
                        <td><?php echo esc_html($report['php_version']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('WordPress Version', 'wp-queue'); ?></th>
                        <td><?php echo esc_html($report['wp_version']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Memory Limit', 'wp-queue'); ?></th>
                        <td><?php echo esc_html($report['memory_limit_formatted']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Current Memory Usage', 'wp-queue'); ?></th>
                        <td>
                            <?php echo esc_html($report['current_memory_formatted']); ?>
                            (<?php echo esc_html((string) $report['memory_percent']); ?>%)
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Max Execution Time', 'wp-queue'); ?></th>
                        <td><?php echo esc_html((string) $report['max_execution_time']); ?>s</td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Timezone', 'wp-queue'); ?></th>
                        <td><?php echo esc_html($report['timezone']); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html__('WP-Cron Status', 'wp-queue'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('WP-Cron Disabled', 'wp-queue'); ?></th>
                        <td>
                            <?php if ($report['wp_cron_disabled']) { ?>
                                <span class="status-badge status-failed"><?php echo esc_html__('Disabled', 'wp-queue'); ?></span>
                                <p class="description"><?php echo esc_html__('DISABLE_WP_CRON is set to true. Background tasks will not run automatically.', 'wp-queue'); ?></p>
                            <?php } else { ?>
                                <span class="status-badge status-completed"><?php echo esc_html__('Enabled', 'wp-queue'); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Alternate Cron', 'wp-queue'); ?></th>
                        <td>
                            <?php if ($report['alternate_cron']) { ?>
                                <span class="status-badge status-completed"><?php echo esc_html__('Enabled', 'wp-queue'); ?></span>
                            <?php } else { ?>
                                <span class="status-badge status-idle"><?php echo esc_html__('Disabled', 'wp-queue'); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Loopback Request', 'wp-queue'); ?></th>
                        <td>
                            <span class="status-badge status-<?php echo $report['loopback']['status'] === 'ok' ? 'completed' : ($report['loopback']['status'] === 'warning' ? 'pending' : 'failed'); ?>">
                                <?php
                                    $status_text = $report['loopback']['status'];
        if ($status_text === 'ok') {
            $status_text = __('OK', 'wp-queue');
        } elseif ($status_text === 'warning') {
            $status_text = __('Warning', 'wp-queue');
        } else {
            $status_text = __('Error', 'wp-queue');
        }
        echo esc_html($status_text);
        ?>
                            </span>
                            <?php if ($report['loopback']['status'] !== 'ok') { ?>
                                <p class="description"><?php echo esc_html($report['loopback']['message']); ?></p>
                            <?php } ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if ($report['action_scheduler']['installed']) { ?>
                <h2><?php echo esc_html__('Action Scheduler', 'wp-queue'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Version', 'wp-queue'); ?></th>
                            <td><?php echo esc_html($report['action_scheduler']['version'] ?? 'Unknown'); ?></td>
                        </tr>
                        <?php if ($report['action_scheduler']['stats']) { ?>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Pending Actions', 'wp-queue'); ?></th>
                                <td><?php echo esc_html((string) $report['action_scheduler']['stats']['pending']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Running Actions', 'wp-queue'); ?></th>
                                <td><?php echo esc_html((string) $report['action_scheduler']['stats']['running']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Failed Actions', 'wp-queue'); ?></th>
                                <td>
                                    <?php if ($report['action_scheduler']['stats']['failed'] > 0) { ?>
                                        <span class="status-badge status-failed">
                                            <?php echo esc_html((string) $report['action_scheduler']['stats']['failed']); ?>
                                        </span>
                                    <?php } else { ?>
                                        0
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>

            <h2><?php echo esc_html__('Server Time', 'wp-queue'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Server Time', 'wp-queue'); ?></th>
                        <td><?php echo esc_html($report['time_info']['server_time']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('WordPress Time', 'wp-queue'); ?></th>
                        <td><?php echo esc_html($report['time_info']['wp_time']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('GMT Offset', 'wp-queue'); ?></th>
                        <td><?php echo esc_html((string) $report['time_info']['gmt_offset']); ?> hours</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}

if (! function_exists('class_basename')) {
    function class_basename(string $class): string
    {
        return basename(str_replace('\\', '/', $class));
    }
}
