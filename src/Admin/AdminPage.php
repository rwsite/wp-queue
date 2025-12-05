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
            '<p>'.__('WP Queue helps your site run heavy or regular tasks in the background (emails, synchronizations, cleanups) so that pages load faster for visitors.', 'wp-queue').'</p>'.
            '<p>'.__('The plugin itself does not add new features to the frontend. It provides a reliable queue that other plugins or your custom code can use for background work.', 'wp-queue').'</p>'.
            '<h3>'.__('What you can use it for', 'wp-queue').'</h3>'.
            '<ul>'.
            '<li>'.__('Monitor which background jobs are queued and running right now.', 'wp-queue').'</li>'.
            '<li>'.__('Quickly see if any jobs are failing and view recent log entries.', 'wp-queue').'</li>'.
            '<li>'.__('Inspect and manage WP-Cron events for WP Queue and other plugins.', 'wp-queue').'</li>'.
            '<li>'.__('Check basic system requirements that affect queue stability.', 'wp-queue').'</li>'.
            '</ul>'.
            '<h3>'.__('For developers', 'wp-queue').'</h3>'.
            '<p>'.__('Developers can dispatch their own jobs to the queue, choose drivers (Database, Sync, Redis), configure retries and schedules. See the documentation and examples on GitHub for integration details.', 'wp-queue').'</p>';
    }

    protected function getHelpTabUsage(): string
    {
        return '<h2>'.__('Basic Usage', 'wp-queue').'</h2>'.
            '<h3>'.__('For site administrators', 'wp-queue').'</h3>'.
            '<ul>'.
            '<li>'.__('Dashboard – quick overview of queues, number of jobs and recent activity.', 'wp-queue').'</li>'.
            '<li>'.__('Scheduled Jobs – list of recurring background jobs added by your theme or plugins.', 'wp-queue').'</li>'.
            '<li>'.__('Logs – history of completed and failed jobs that helps to diagnose problems.', 'wp-queue').'</li>'.
            '<li>'.__('WP-Cron – list of cron events with the ability to run, pause or delete them.', 'wp-queue').'</li>'.
            '<li>'.__('System – checks PHP version, WP-Cron status and other important settings.', 'wp-queue').'</li>'.
            '</ul>'.
            '<p>'.__('If you installed WP Queue because another plugin requires it, you usually do not need to configure anything here. Use these tabs mainly for monitoring and troubleshooting.', 'wp-queue').'</p>'.
            '<h3>'.__('For developers', 'wp-queue').'</h3>'.
            '<p>'.sprintf(
                /* translators: %s: URL to GitHub repository */
                __('To add your own background jobs, use the WP Queue PHP API from your plugin or theme. You can dispatch jobs to different queues, set delays and retries. Full code examples are available in the <a href="%s" target="_blank">README on GitHub</a>.', 'wp-queue'),
                esc_url('https://github.com/rwsite/wp-queue'),
            ).'</p>';
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
                <a href="?page=wp-queue&tab=help" class="nav-tab <?php echo $tab === 'help' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Help', 'wp-queue'); ?>
                </a>
            </nav>

            <div class="wp-queue-content">
                <?php
                        match ($tab) {
                            'jobs' => $this->renderJobsTab(),
                            'logs' => $this->renderLogsTab(),
                            'cron' => $this->renderCronTab(),
                            'system' => $this->renderSystemTab(),
                            'help' => $this->renderHelpTab(),
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
            <p class="description">
                <?php echo esc_html__(
                    'Показывает общую статистику по очередям (ожидающие, выполняющиеся, завершенные и провалившиеся задачи), таблицу всех очередей с их статусом и кнопками управления (пауза/возобновление, очистка), а также последние 10 записей логов активности.',
                    'wp-queue',
                ); ?>
            </p>
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
            <p class="description">
                <?php echo esc_html__(
                    'Отображает таблицу запланированных повторяющихся задач (PHP-классов), зарегистрированных в WP Queue: класс задачи, интервал выполнения, очередь назначения и время следующего запуска. Позволяет запускать любую задачу вручную.',
                    'wp-queue',
                ); ?>
            </p>
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

    protected function renderHelpTab(): void
    {
        ?>
        <div class="wp-queue-help">
            <h2><?php echo esc_html__('About WP Queue', 'wp-queue'); ?></h2>
            <p class="description">
                <?php echo esc_html__(
                    'WP Queue is a robust background processing library for WordPress. It allows you to offload time-consuming tasks (like sending emails, syncing data, or generating reports) to be processed asynchronously, ensuring your site remains fast and responsive for visitors.',
                    'wp-queue',
                ); ?>
            </p>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">

            <h3><?php echo esc_html__('Why is it needed?', 'wp-queue'); ?></h3>
            <p>
                <?php echo esc_html__(
                    'By default, WordPress executes all actions during the page load request. If a plugin needs to send 100 emails or sync 500 products with an external API, the user has to wait until it finishes. This leads to slow page loads or timeouts.',
                    'wp-queue',
                ); ?>
            </p>
            <p>
                <?php echo esc_html__(
                    'WP Queue solves this by pushing these tasks to a "Queue" and processing them later in the background. The user gets an instant response, and the heavy work happens behind the scenes.',
                    'wp-queue',
                ); ?>
            </p>

            <h3><?php echo esc_html__('How Queues Work', 'wp-queue'); ?></h3>
            <ul class="wp-queue-help-list" style="list-style: disc; padding-left: 20px;">
                <li><strong><?php echo esc_html__('Dispatch', 'wp-queue'); ?>:</strong> <?php echo esc_html__('A plugin creates a "Job" (a task) and pushes it to the queue.', 'wp-queue'); ?></li>
                <li><strong><?php echo esc_html__('Storage', 'wp-queue'); ?>:</strong> <?php echo esc_html__('The job is saved in the database (or Redis) waiting to be picked up.', 'wp-queue'); ?></li>
                <li><strong><?php echo esc_html__('Worker', 'wp-queue'); ?>:</strong> <?php echo esc_html__('A background process (Worker) constantly checks the queue for new jobs.', 'wp-queue'); ?></li>
                <li><strong><?php echo esc_html__('Process', 'wp-queue'); ?>:</strong> <?php echo esc_html__('The worker picks up the job and executes it. If it succeeds, it is marked as "Completed". If it fails, it can be retried.', 'wp-queue'); ?></li>
            </ul>

            <h3><?php echo esc_html__('WP-Cron vs System Cron', 'wp-queue'); ?></h3>
            <p>
                <?php echo esc_html__(
                    'To process the queue, WordPress needs a trigger. There are two ways to do this:',
                    'wp-queue',
                ); ?>
            </p>
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 15px;">
                <p><strong>1. <?php echo esc_html__('WP-Cron (Default)', 'wp-queue'); ?></strong></p>
                <p style="margin-top: 5px;">
                    <?php echo esc_html__(
                        'WordPress checks for scheduled tasks on every page load. This works out of the box but has downsides: on low-traffic sites, tasks might not run on time; on high-traffic sites, it can cause performance issues. It is "fake" cron.',
                        'wp-queue',
                    ); ?>
                </p>

                <p style="margin-top: 15px;"><strong>2. <?php echo esc_html__('System Cron (Recommended)', 'wp-queue'); ?></strong></p>
                <p style="margin-top: 5px;">
                    <?php echo esc_html__(
                        'A real cron job configured on your server (Linux) that calls WordPress every minute. This is reliable and precise. Ideally, you should disable default WP-Cron and use System Cron.',
                        'wp-queue',
                    ); ?>
                </p>
                <code style="display: block; background: #f0f0f1; padding: 10px; margin-top: 5px;">*/1 * * * * php /path/to/wordpress/wp-cron.php</code>
            </div>

            <h3><?php echo esc_html__('Redis Support', 'wp-queue'); ?></h3>
            <p>
                <?php echo esc_html__(
                    'By default, WP Queue stores jobs in the WordPress database (wp_options or custom tables). For high-performance sites, you can use Redis. Redis is an in-memory data store that is much faster than a SQL database for queue operations. It reduces load on your database and speeds up job processing.',
                    'wp-queue',
                ); ?>
            </p>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">

            <h3><?php echo esc_html__('Подробное описание вкладок панели', 'wp-queue'); ?></h3>
            <div class="wp-queue-accordion" style="margin-top: 20px;">
                <details>
                    <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; margin-bottom: 5px;">
                        <?php echo esc_html__('Dashboard', 'wp-queue'); ?>
                    </summary>
                    <div style="padding: 15px; border: 1px solid #ddd; border-top: none; margin-bottom: 10px;">
                        <p><?php echo esc_html__('Главная вкладка для мониторинга состояния очередей в реальном времени.', 'wp-queue'); ?></p>
                        <ul style="list-style: disc; padding-left: 20px;">
                            <li><strong><?php echo esc_html__('Статистика очередей', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Показывает общее количество задач в разных статусах (ожидающие, выполняющиеся, завершенные, провалившиеся).', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Таблица очередей', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Список всех активных очередей с их размером, статусом (idle/pending/running/paused) и кнопками управления (пауза/возобновление, очистка всех задач).', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Недавняя активность', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Последние 10 записей из логов выполнения задач для быстрого отслеживания работы.', 'wp-queue'); ?></li>
                        </ul>
                        <p><em><?php echo esc_html__('Используйте эту вкладку для быстрой проверки работоспособности очередей и выявления зависших процессов.', 'wp-queue'); ?></em></p>
                    </div>
                </details>

                <details>
                    <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; margin-bottom: 5px;">
                        <?php echo esc_html__('Scheduled Jobs', 'wp-queue'); ?>
                    </summary>
                    <div style="padding: 15px; border: 1px solid #ddd; border-top: none; margin-bottom: 10px;">
                        <p><?php echo esc_html__('Управление запланированными повторяющимися задачами.', 'wp-queue'); ?></p>
                        <ul style="list-style: disc; padding-left: 20px;">
                            <li><strong><?php echo esc_html__('Список задач', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Показывает все зарегистрированные PHP-классы задач с их конфигурацией.', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Информация по каждой задаче', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Класс задачи, интервал выполнения (например, "hourly", "daily"), очередь назначения, время следующего запуска.', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Ручной запуск', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Кнопка "Run Now" позволяет запустить любую задачу немедленно, не дожидаясь расписания.', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Статус следующего запуска', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Показывает, когда задача должна выполниться в следующий раз, или отмечает просроченные задачи.', 'wp-queue'); ?></li>
                        </ul>
                        <p><em><?php echo esc_html__('Полезно для тестирования задач и принудительного запуска синхронизаций.', 'wp-queue'); ?></em></p>
                    </div>
                </details>

                <details>
                    <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; margin-bottom: 5px;">
                        <?php echo esc_html__('Logs', 'wp-queue'); ?>
                    </summary>
                    <div style="padding: 15px; border: 1px solid #ddd; border-top: none; margin-bottom: 10px;">
                        <p><?php echo esc_html__('История выполнения всех задач для диагностики и отладки.', 'wp-queue'); ?></p>
                        <ul style="list-style: disc; padding-left: 20px;">
                            <li><strong><?php echo esc_html__('Фильтры по статусу', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Кнопки для показа всех записей, только завершенных или только провалившихся задач.', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Таблица логов', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Время выполнения, статус (completed/failed/retrying), класс задачи, очередь, сообщение об ошибке.', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Очистка логов', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Кнопка для удаления старых записей логов, чтобы не засорять базу данных.', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Количество записей', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Показывает последние 200 записей по умолчанию, или все завершенные/провалившиеся.', 'wp-queue'); ?></li>
                        </ul>
                        <p><em><?php echo esc_html__('Обязательно проверяйте логи при возникновении ошибок в фоновых процессах.', 'wp-queue'); ?></em></p>
                    </div>
                </details>

                <details>
                    <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; margin-bottom: 5px;">
                        <?php echo esc_html__('WP-Cron', 'wp-queue'); ?>
                    </summary>
                    <div style="padding: 15px; border: 1px solid #ddd; border-top: none; margin-bottom: 10px;">
                        <p><?php echo esc_html__('Управление системными таймерами WordPress, которые запускают фоновые процессы.', 'wp-queue'); ?></p>
                        <ul style="list-style: disc; padding-left: 20px;">
                            <li><strong><?php echo esc_html__('Статистика событий', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Общее количество событий, просроченных, группировка по источнику (WordPress, WooCommerce, WP Queue, plugins).', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Таблица событий', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Хук события, время следующего запуска, расписание (single/hourly/daily), источник, аргументы.', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Действия с событиями', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Запуск сейчас, редактирование расписания, пауза, удаление. Модальное окно для изменения интервала.', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Приостановленные события', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Отдельная таблица для событий, которые временно отключены.', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Зарегистрированные расписания', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Таблица всех доступных интервалов (hourly, daily, etc.) с их длительностью.', 'wp-queue'); ?></li>
                        </ul>
                        <p><em><?php echo esc_html__('Критично для понимания, почему задачи не запускаются вовремя.', 'wp-queue'); ?></em></p>
                    </div>
                </details>

                <details>
                    <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; margin-bottom: 5px;">
                        <?php echo esc_html__('System', 'wp-queue'); ?>
                    </summary>
                    <div style="padding: 15px; border: 1px solid #ddd; border-top: none; margin-bottom: 10px;">
                        <p><?php echo esc_html__('Проверка системных требований и окружения для стабильной работы очередей.', 'wp-queue'); ?></p>
                        <ul style="list-style: disc; padding-left: 20px;">
                            <li><strong><?php echo esc_html__('Здоровье системы', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Уведомления о критических проблемах (низкая память, отключенный WP-Cron).', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Окружение', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Версии PHP и WordPress, лимиты памяти (текущий usage), максимальное время выполнения, таймзона.', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('WP-Cron статус', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Проверка отключения (DISABLE_WP_CRON), альтернативного крона, loopback запросов.', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Action Scheduler', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Если установлен, показывает версию и статистику (pending/running/failed actions).', 'wp-queue'); ?></li>
                            <li><strong><?php echo esc_html__('Время сервера', 'wp-queue'); ?>:</strong> <?php echo esc_html__('Сравнение времени сервера, WordPress и GMT offset для диагностики проблем с расписаниями.', 'wp-queue'); ?></li>
                        </ul>
                        <p><em><?php echo esc_html__('Проверяйте эту вкладку при настройке сервера или отладке проблем с очередями.', 'wp-queue'); ?></em></p>
                    </div>
                </details>
            </div>

            <h3><?php echo esc_html__('More documentation', 'wp-queue'); ?></h3>
            <p>
                <?php
                printf(
                    /* translators: %s: URL to GitHub repository */
                    esc_html__(
                        'Full documentation and code examples are available on GitHub: %s.',
                        'wp-queue',
                    ),
                    esc_url('https://github.com/rwsite/wp-queue'),
                );
        ?>
            </p>
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
            <p class="description">
                <?php echo esc_html__(
                    'Показывает историю выполнения задач в виде таблицы: время запуска, статус (завершено/провалено), класс задачи, очередь и сообщение об ошибке. Фильтры по статусу (все, только завершенные, только провалившиеся). Кнопка для очистки старых логов.',
                    'wp-queue',
                ); ?>
            </p>
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
                    <button class="button wp-queue-clear-logs" title="<?php echo esc_attr__('Clears logs older than 7 days', 'wp-queue'); ?>">
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
            <p class="description">
                <?php echo esc_html__(
                    'Отображает все зарегистрированные WP-Cron события (таймеры): хук, следующее время выполнения, расписание, источник. Действия для каждого: запуск, редактирование расписания, пауза, удаление. Отдельные таблицы для приостановленных событий и зарегистрированных расписаний.',
                    'wp-queue',
                ); ?>
            </p>
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
            <p class="description">
                <?php echo esc_html__(
                    'Показывает информацию об окружении (версии PHP и WordPress, лимиты памяти, время выполнения, таймзона), статус WP-Cron (отключен ли, альтернативный крон, loopback проверки). Если установлен Action Scheduler — его статистику. Время сервера и WordPress.',
                    'wp-queue',
                ); ?>
            </p>
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
