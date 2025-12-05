<?php

declare(strict_types=1);

namespace WPQueue\Admin;

use WPQueue\WPQueue;

/**
 * Admin Page with Rank Math style UI
 * 3 main tabs: Queues, Scheduler, System
 */
class AdminPage
{
    /** @var array<string, array<string, string>> */
    private array $tabs = [];

    /** @var array<string, array<string, array{title: string, icon: string}>> */
    private array $sections = [];

    private const JOBS_PER_PAGE = 20;

    private const LOGS_PER_PAGE = 50;

    public function __construct()
    {
        add_action('init', [$this, 'initTabs'], 5);
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function initTabs(): void
    {
        $this->tabs = [
            'queues' => ['title' => __('Очереди', 'wp-queue'), 'icon' => 'dashicons-database'],
            'scheduler' => ['title' => __('Планировщик', 'wp-queue'), 'icon' => 'dashicons-clock'],
            'system' => ['title' => __('Система', 'wp-queue'), 'icon' => 'dashicons-admin-tools'],
        ];

        $this->sections = [
            'queues' => [
                'overview' => ['title' => __('Обзор', 'wp-queue'), 'icon' => 'dashicons-chart-bar'],
                'jobs' => ['title' => __('Задачи в очереди', 'wp-queue'), 'icon' => 'dashicons-list-view'],
                'history' => ['title' => __('История', 'wp-queue'), 'icon' => 'dashicons-backup'],
                'failed' => ['title' => __('Ошибки', 'wp-queue'), 'icon' => 'dashicons-dismiss'],
                'drivers' => ['title' => __('Драйверы', 'wp-queue'), 'icon' => 'dashicons-admin-generic'],
            ],
            'scheduler' => [
                'overview' => ['title' => __('Обзор', 'wp-queue'), 'icon' => 'dashicons-chart-bar'],
                'events' => ['title' => __('Cron события', 'wp-queue'), 'icon' => 'dashicons-calendar-alt'],
                'scheduled' => ['title' => __('Запланированные', 'wp-queue'), 'icon' => 'dashicons-clock'],
                'paused' => ['title' => __('Приостановленные', 'wp-queue'), 'icon' => 'dashicons-controls-pause'],
            ],
            'system' => [
                'status' => ['title' => __('Статус системы', 'wp-queue'), 'icon' => 'dashicons-heart'],
                'tools' => ['title' => __('Инструменты', 'wp-queue'), 'icon' => 'dashicons-admin-tools'],
                'help' => ['title' => __('Справка', 'wp-queue'), 'icon' => 'dashicons-editor-help'],
            ],
        ];
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('WP Queue', 'wp-queue'),
            __('WP Queue', 'wp-queue'),
            'manage_options',
            'wp-queue',
            [$this, 'renderPage'],
            'dashicons-database',
            80,
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
        if ($hook !== 'toplevel_page_wp-queue') {
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
    }

    public function renderPage(): void
    {
        $tab = sanitize_key($_GET['tab'] ?? 'queues');
        $section = sanitize_key($_GET['section'] ?? '');

        if (! isset($this->tabs[$tab])) {
            $tab = 'queues';
        }

        // Дефолтная секция для каждой вкладки
        if (empty($section) || ! isset($this->sections[$tab][$section])) {
            $section = array_key_first($this->sections[$tab]);
        }

        // Проверка на детальный просмотр очереди
        $queueView = sanitize_key($_GET['queue'] ?? '');
        $jobView = sanitize_key($_GET['job'] ?? '');

        ?>
        <div class="wrap wp-queue-wrap">
            <?php $this->renderHeader($tab, $section, $queueView); ?>
            <?php $this->renderTabs($tab); ?>

            <div class="wp-queue-container">
                <?php $this->renderSidebar($tab, $section); ?>
                <div class="wp-queue-main">
                    <?php
                            if ($queueView && $tab === 'queues') {
                                $this->renderQueueDetail($queueView, $jobView);
                            } else {
                                $this->renderSectionContent($tab, $section);
                            }
        ?>
                </div>
            </div>
        </div>
    <?php
    }

    private function renderHeader(string $tab, string $section, string $queueView = ''): void
    {
        $tabTitle = $this->tabs[$tab]['title'] ?? '';
        $sectionTitle = $this->sections[$tab][$section]['title'] ?? '';

        $breadcrumb = '/ '.esc_html($tabTitle).' / '.esc_html($sectionTitle);
        if ($queueView) {
            $breadcrumb = '/ '.esc_html($tabTitle).' / <a href="'.esc_url(admin_url('admin.php?page=wp-queue&tab=queues&section=overview')).'">'.esc_html__('Обзор', 'wp-queue').'</a> / '.esc_html($queueView);
        }
        ?>
        <div class="wp-queue-header">
            <div class="wp-queue-header-left">
                <span class="dashicons dashicons-database wp-queue-logo-icon"></span>
                <span class="wp-queue-title">WP Queue</span>
                <span class="wp-queue-breadcrumb">
                    <?php echo wp_kses($breadcrumb, ['a' => ['href' => []]]); ?>
                </span>
            </div>
            <div class="wp-queue-header-right">
                <span class="wp-queue-version">v<?php echo esc_html(WP_QUEUE_VERSION); ?></span>
            </div>
        </div>
    <?php
    }

    private function renderTabs(string $currentTab): void
    {
        ?>
        <nav class="wp-queue-tabs">
            <?php foreach ($this->tabs as $tabKey => $tabData) { ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab='.$tabKey)); ?>"
                    class="wp-queue-tab <?php echo $currentTab === $tabKey ? 'active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($tabData['icon']); ?>"></span>
                    <?php echo esc_html($tabData['title']); ?>
                </a>
            <?php } ?>
        </nav>
    <?php
    }

    private function renderSidebar(string $tab, string $currentSection): void
    {
        ?>
        <div class="wp-queue-sidebar">
            <?php foreach ($this->sections[$tab] as $sectionKey => $sectionData) { ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab='.$tab.'&section='.$sectionKey)); ?>"
                    class="wp-queue-sidebar-item <?php echo $currentSection === $sectionKey ? 'active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($sectionData['icon']); ?>"></span>
                    <?php echo esc_html($sectionData['title']); ?>
                </a>
            <?php } ?>
        </div>
    <?php
    }

    private function renderSectionContent(string $tab, string $section): void
    {
        $method = 'render'.ucfirst($tab).ucfirst($section);
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $this->renderPlaceholder($tab, $section);
        }
    }

    private function renderPlaceholder(string $tab, string $section): void
    {
        ?>
        <div class="wp-queue-section-header">
            <h1><?php echo esc_html($this->sections[$tab][$section]['title']); ?></h1>
            <p class="description"><?php echo esc_html__('Этот раздел находится в разработке.', 'wp-queue'); ?></p>
        </div>
    <?php
    }

    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'idle' => __('Простаивает', 'wp-queue'),
            'pending' => __('Ожидает', 'wp-queue'),
            'running' => __('Работает', 'wp-queue'),
            'paused' => __('Пауза', 'wp-queue'),
            'completed' => __('Завершено', 'wp-queue'),
            'failed' => __('Ошибка', 'wp-queue'),
            default => ucfirst($status),
        };
    }

    protected function renderQueuesOverview(): void
    {
        $metrics = WPQueue::logs()->metrics();
        $queues = $this->getQueuesStatus();
        $driver = WPQueue::manager()->getDefaultDriver();
        $filter = sanitize_key($_GET['status'] ?? '');
        ?>
        <div class="wp-queue-content-wrapper">
            <!-- Статистика - кликабельные карточки -->
            <div class="wp-queue-stats">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab=queues&section=jobs')); ?>" class="stat-card stat-pending <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    <span class="stat-number"><?php echo esc_html((string) $this->getTotalPending()); ?></span>
                    <span class="stat-label"><?php echo esc_html__('В очереди', 'wp-queue'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab=queues&section=overview&status=running')); ?>" class="stat-card stat-running <?php echo $filter === 'running' ? 'active' : ''; ?>">
                    <span class="stat-number"><?php echo esc_html((string) $this->getRunningCount()); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Выполняется', 'wp-queue'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab=queues&section=history&filter=completed')); ?>" class="stat-card stat-completed <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                    <span class="stat-number"><?php echo esc_html((string) $metrics['completed']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Завершено', 'wp-queue'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab=queues&section=failed')); ?>" class="stat-card stat-failed <?php echo $filter === 'failed' ? 'active' : ''; ?>">
                    <span class="stat-number"><?php echo esc_html((string) $metrics['failed']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Ошибок', 'wp-queue'); ?></span>
                </a>
            </div>

            <!-- Информация о драйвере -->
            <div class="wp-queue-driver-info">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php echo esc_html__('Драйвер:', 'wp-queue'); ?>
                <strong><?php echo esc_html(ucfirst($driver)); ?></strong>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab=queues&section=drivers')); ?>" class="driver-link">
                    <?php echo esc_html__('Настроить', 'wp-queue'); ?>
                </a>
            </div>

            <!-- Очереди как полностью кликабельные карточки -->
            <h2><?php echo esc_html__('Очереди', 'wp-queue'); ?></h2>
            <div class="wp-queue-cards">
                <?php foreach ($queues as $name => $data) { ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab=queues&queue='.urlencode($name))); ?>" class="queue-card queue-card-<?php echo esc_attr($data['status']); ?>">
                        <div class="queue-card-header">
                            <span class="queue-card-title"><?php echo esc_html($name); ?></span>
                            <span class="status-badge status-<?php echo esc_attr($data['status']); ?>">
                                <?php echo esc_html($this->getStatusLabel($data['status'])); ?>
                            </span>
                        </div>
                        <div class="queue-card-body">
                            <div class="queue-card-stat">
                                <span class="queue-stat-number"><?php echo esc_html((string) $data['size']); ?></span>
                                <span class="queue-stat-label"><?php echo esc_html__('задач', 'wp-queue'); ?></span>
                            </div>
                        </div>
                        <div class="queue-card-footer">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>
                    </a>
                <?php } ?>
            </div>

            <!-- Последняя активность -->
            <h2><?php echo esc_html__('Последняя активность', 'wp-queue'); ?></h2>
            <?php $this->renderRecentLogs(10); ?>
        </div>
    <?php
    }

    /**
     * Детальный просмотр очереди с джобами
     */
    protected function renderQueueDetail(string $queueName, string $jobId = ''): void
    {
        $jobs = $this->getQueueJobs($queueName);
        $page = max(1, (int) ($_GET['paged'] ?? 1));
        $totalJobs = count($jobs);
        $totalPages = max(1, (int) ceil($totalJobs / self::JOBS_PER_PAGE));
        $offset = ($page - 1) * self::JOBS_PER_PAGE;
        $pagedJobs = array_slice($jobs, $offset, self::JOBS_PER_PAGE, true);

        $isPaused = WPQueue::isPaused($queueName);
        $isProcessing = WPQueue::isProcessing($queueName);
        ?>
        <div class="wp-queue-detail">
            <!-- Заголовок с действиями -->
            <div class="queue-detail-header">
                <div class="queue-detail-title">
                    <h1><?php echo esc_html(sprintf(__('Очередь: %s', 'wp-queue'), $queueName)); ?></h1>
                    <span class="status-badge status-<?php echo $isPaused ? 'paused' : ($isProcessing ? 'running' : 'idle'); ?>">
                        <?php
                            if ($isPaused) {
                                echo esc_html__('Приостановлена', 'wp-queue');
                            } elseif ($isProcessing) {
                                echo esc_html__('Обрабатывается', 'wp-queue');
                            } else {
                                echo esc_html__('Активна', 'wp-queue');
                            }
        ?>
                    </span>
                </div>
                <div class="queue-detail-actions">
                    <?php if ($isPaused) { ?>
                        <button class="button button-primary wp-queue-action" data-action="resume" data-queue="<?php echo esc_attr($queueName); ?>">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php echo esc_html__('Возобновить', 'wp-queue'); ?>
                        </button>
                    <?php } else { ?>
                        <button class="button wp-queue-action" data-action="pause" data-queue="<?php echo esc_attr($queueName); ?>">
                            <span class="dashicons dashicons-controls-pause"></span>
                            <?php echo esc_html__('Пауза', 'wp-queue'); ?>
                        </button>
                    <?php } ?>
                    <button class="button wp-queue-action" data-action="process" data-queue="<?php echo esc_attr($queueName); ?>">
                        <span class="dashicons dashicons-update"></span>
                        <?php echo esc_html__('Обработать сейчас', 'wp-queue'); ?>
                    </button>
                    <button class="button wp-queue-action" data-action="clear" data-queue="<?php echo esc_attr($queueName); ?>">
                        <span class="dashicons dashicons-trash"></span>
                        <?php echo esc_html__('Очистить', 'wp-queue'); ?>
                    </button>
                </div>
            </div>

            <!-- Статистика очереди -->
            <div class="wp-queue-stats" style="margin-bottom: 20px;">
                <div class="stat-card">
                    <span class="stat-number"><?php echo esc_html((string) $totalJobs); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Всего задач', 'wp-queue'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo esc_html((string) $this->countPendingJobs($jobs)); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Ожидают', 'wp-queue'); ?></span>
                </div>
                <div class="stat-card stat-running">
                    <span class="stat-number"><?php echo esc_html((string) $this->countReservedJobs($jobs)); ?></span>
                    <span class="stat-label"><?php echo esc_html__('В обработке', 'wp-queue'); ?></span>
                </div>
            </div>

            <!-- Таблица джобов -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 80px;"><?php echo esc_html__('ID', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Класс задачи', 'wp-queue'); ?></th>
                        <th style="width: 80px;"><?php echo esc_html__('Попытки', 'wp-queue'); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__('Доступна с', 'wp-queue'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Статус', 'wp-queue'); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__('Действия', 'wp-queue'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pagedJobs)) { ?>
                        <tr>
                            <td colspan="6" class="no-items"><?php echo esc_html__('Очередь пуста', 'wp-queue'); ?></td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($pagedJobs as $id => $job) { ?>
                            <tr>
                                <td><code title="<?php echo esc_attr($id); ?>"><?php echo esc_html(substr($id, 0, 8)); ?>...</code></td>
                                <td>
                                    <code><?php echo esc_html($job['class'] ?? __('Неизвестно', 'wp-queue')); ?></code>
                                    <?php if (! empty($job['payload_preview'])) { ?>
                                        <br><small class="description"><?php echo esc_html($job['payload_preview']); ?></small>
                                    <?php } ?>
                                </td>
                                <td><?php echo esc_html((string) ($job['attempts'] ?? 0)); ?></td>
                                <td>
                                    <?php
                    $availableAt = $job['available_at'] ?? 0;
                            if ($availableAt > time()) {
                                echo esc_html(human_time_diff($availableAt, time())).' '.esc_html__('позже', 'wp-queue');
                            } else {
                                echo esc_html__('Сейчас', 'wp-queue');
                            }
                            ?>
                                </td>
                                <td>
                                    <?php
                            $status = 'pending';
                            if (! empty($job['reserved_at'])) {
                                $status = 'running';
                            } elseif ($availableAt > time()) {
                                $status = 'delayed';
                            }
                            ?>
                                    <span class="status-badge status-<?php echo esc_attr($status); ?>">
                                        <?php echo esc_html($this->getStatusLabel($status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="button button-small wp-queue-job-action" data-action="view" data-job="<?php echo esc_attr($id); ?>" data-queue="<?php echo esc_attr($queueName); ?>" title="<?php echo esc_attr__('Подробности', 'wp-queue'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    <button class="button button-small wp-queue-job-action" data-action="delete" data-job="<?php echo esc_attr($id); ?>" data-queue="<?php echo esc_attr($queueName); ?>" title="<?php echo esc_attr__('Удалить', 'wp-queue'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>

            <!-- Пагинация -->
            <?php if ($totalPages > 1) { ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php echo esc_html(sprintf(__('%d задач', 'wp-queue'), $totalJobs)); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                            $baseUrl = admin_url('admin.php?page=wp-queue&tab=queues&queue='.urlencode($queueName));
                if ($page > 1) {
                    echo '<a class="prev-page button" href="'.esc_url($baseUrl.'&paged='.($page - 1)).'">‹</a>';
                }
                echo '<span class="paging-input">'.esc_html($page).' / '.esc_html((string) $totalPages).'</span>';
                if ($page < $totalPages) {
                    echo '<a class="next-page button" href="'.esc_url($baseUrl.'&paged='.($page + 1)).'">›</a>';
                }
                ?>
                        </span>
                    </div>
                </div>
            <?php } ?>
        </div>

        <!-- Модальное окно для просмотра деталей джоба -->
        <div id="wp-queue-job-modal" class="wp-queue-modal" style="display:none;">
            <div class="wp-queue-modal-content" style="max-width: 700px;">
                <h2><?php echo esc_html__('Детали задачи', 'wp-queue'); ?></h2>
                <div id="wp-queue-job-details"></div>
                <p class="submit">
                    <button type="button" class="button wp-queue-modal-close"><?php echo esc_html__('Закрыть', 'wp-queue'); ?></button>
                </p>
            </div>
        </div>
    <?php
    }

    protected function renderQueuesJobs(): void
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

    protected function renderDocsIntro(): void
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

    protected function renderQueuesHistory(): void
    {
        $filter = sanitize_key($_GET['filter'] ?? 'all');
        $queueFilter = sanitize_key($_GET['queue_filter'] ?? '');
        $page = max(1, (int) ($_GET['paged'] ?? 1));

        // Получаем все логи
        $allLogs = match ($filter) {
            'failed' => WPQueue::logs()->failed(),
            'completed' => WPQueue::logs()->completed(),
            default => WPQueue::logs()->recent(500),
        };

        // Фильтрация по очереди
        if ($queueFilter) {
            $allLogs = array_filter($allLogs, fn ($log) => ($log['queue'] ?? '') === $queueFilter);
        }

        // Получаем список уникальных очередей
        $queuesInLogs = array_unique(array_column(WPQueue::logs()->recent(500), 'queue'));

        $totalLogs = count($allLogs);
        $totalPages = max(1, (int) ceil($totalLogs / self::LOGS_PER_PAGE));
        $offset = ($page - 1) * self::LOGS_PER_PAGE;
        $logs = array_slice($allLogs, $offset, self::LOGS_PER_PAGE);
        ?>
        <div class="wp-queue-content-wrapper">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="?page=wp-queue&tab=queues&section=history&filter=all" class="button <?php echo $filter === 'all' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('Все', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=queues&section=history&filter=completed" class="button <?php echo $filter === 'completed' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('Завершённые', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=queues&section=history&filter=failed" class="button <?php echo $filter === 'failed' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('Ошибки', 'wp-queue'); ?>
                    </a>

                    <?php if (! empty($queuesInLogs)) { ?>
                        <select id="queue-filter" class="wp-queue-filter-select">
                            <option value=""><?php echo esc_html__('Все очереди', 'wp-queue'); ?></option>
                            <?php foreach ($queuesInLogs as $q) { ?>
                                <option value="<?php echo esc_attr($q); ?>" <?php selected($queueFilter, $q); ?>>
                                    <?php echo esc_html($q); ?>
                                </option>
                            <?php } ?>
                        </select>
                    <?php } ?>
                </div>
                <div class="alignright actions">
                    <button class="button wp-queue-clear-logs" title="<?php echo esc_attr__('Удаляет логи старше 7 дней', 'wp-queue'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                        <?php echo esc_html__('Очистить старые', 'wp-queue'); ?>
                    </button>
                    <button class="button button-link-delete wp-queue-clear-all-logs">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php echo esc_html__('Удалить все', 'wp-queue'); ?>
                    </button>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 150px;"><?php echo esc_html__('Время', 'wp-queue'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Статус', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Задача', 'wp-queue'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Очередь', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Сообщение', 'wp-queue'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) { ?>
                        <tr>
                            <td colspan="5" class="no-items"><?php echo esc_html__('Логов не найдено', 'wp-queue'); ?></td>
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
                                <td>
                                    <a href="?page=wp-queue&tab=queues&section=history&queue_filter=<?php echo esc_attr($log['queue']); ?>">
                                        <?php echo esc_html($log['queue']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($log['message'] ?? '-'); ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1) { ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php echo esc_html(sprintf(__('%d записей', 'wp-queue'), $totalLogs)); ?>
                        </span>
                        <span class="pagination-links">
                            <?php
                                $baseUrl = admin_url('admin.php?page=wp-queue&tab=queues&section=history&filter='.$filter);
                if ($queueFilter) {
                    $baseUrl .= '&queue_filter='.$queueFilter;
                }
                if ($page > 1) {
                    echo '<a class="prev-page button" href="'.esc_url($baseUrl.'&paged='.($page - 1)).'">‹</a>';
                }
                echo '<span class="paging-input">'.esc_html($page).' / '.esc_html((string) $totalPages).'</span>';
                if ($page < $totalPages) {
                    echo '<a class="next-page button" href="'.esc_url($baseUrl.'&paged='.($page + 1)).'">›</a>';
                }
                ?>
                        </span>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php
    }

    /**
     * Секция с ошибками (failed jobs)
     */
    protected function renderQueuesFailed(): void
    {
        $failedLogs = WPQueue::logs()->failed();
        $page = max(1, (int) ($_GET['paged'] ?? 1));
        $totalLogs = count($failedLogs);
        $totalPages = max(1, (int) ceil($totalLogs / self::LOGS_PER_PAGE));
        $offset = ($page - 1) * self::LOGS_PER_PAGE;
        $logs = array_slice($failedLogs, $offset, self::LOGS_PER_PAGE);
        ?>
        <div class="wp-queue-failed">
            <div class="wp-queue-stats" style="margin-bottom: 20px;">
                <div class="stat-card stat-failed">
                    <span class="stat-number"><?php echo esc_html((string) $totalLogs); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Всего ошибок', 'wp-queue'); ?></span>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 150px;"><?php echo esc_html__('Время', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Задача', 'wp-queue'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Очередь', 'wp-queue'); ?></th>
                        <th style="width: 80px;"><?php echo esc_html__('Попытки', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Сообщение об ошибке', 'wp-queue'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) { ?>
                        <tr>
                            <td colspan="5" class="no-items"><?php echo esc_html__('Ошибок нет', 'wp-queue'); ?></td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($logs as $log) { ?>
                            <tr>
                                <td><?php echo esc_html(wp_date('Y-m-d H:i:s', $log['timestamp'])); ?></td>
                                <td><code><?php echo esc_html($log['job_class']); ?></code></td>
                                <td><?php echo esc_html($log['queue']); ?></td>
                                <td><?php echo esc_html((string) ($log['attempts'] ?? 0)); ?></td>
                                <td>
                                    <span class="error-message" title="<?php echo esc_attr($log['message'] ?? ''); ?>">
                                        <?php echo esc_html(mb_substr($log['message'] ?? '-', 0, 100)); ?>
                                        <?php if (strlen($log['message'] ?? '') > 100) {
                                            echo '...';
                                        } ?>
                                    </span>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1) { ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="pagination-links">
                            <?php
                            $baseUrl = admin_url('admin.php?page=wp-queue&tab=queues&section=failed');
                if ($page > 1) {
                    echo '<a class="prev-page button" href="'.esc_url($baseUrl.'&paged='.($page - 1)).'">‹</a>';
                }
                echo '<span class="paging-input">'.esc_html($page).' / '.esc_html((string) $totalPages).'</span>';
                if ($page < $totalPages) {
                    echo '<a class="next-page button" href="'.esc_url($baseUrl.'&paged='.($page + 1)).'">›</a>';
                }
                ?>
                        </span>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php
    }

    /**
     * Секция с информацией о драйверах
     */
    protected function renderQueuesDrivers(): void
    {
        $manager = WPQueue::manager();
        $currentDriver = $manager->getDefaultDriver();
        $configuredDriver = $manager->getConfiguredDriver();
        $drivers = $manager->getAvailableDrivers();

        // Check if there's a fallback situation
        $hasFallback = $configuredDriver !== $currentDriver;
        ?>
        <div class="wp-queue-drivers">
            <?php if ($hasFallback) { ?>
                <div class="notice notice-warning" style="margin: 0 0 20px;">
                    <p>
                        <strong><?php echo esc_html__('⚠️ Внимание:', 'wp-queue'); ?></strong>
                        <?php echo esc_html(sprintf(
                            __('Драйвер "%s" настроен в wp-config.php, но недоступен. Используется fallback на "%s".', 'wp-queue'),
                            $configuredDriver,
                            $currentDriver,
                        )); ?>
                    </p>
                    <p>
                        <?php echo esc_html($drivers[$configuredDriver]['message'] ?? __('Проверьте настройки драйвера.', 'wp-queue')); ?>
                    </p>
                </div>
            <?php } else { ?>
                <div class="notice notice-info" style="margin: 0 0 20px;">
                    <p>
                        <strong><?php echo esc_html__('Текущий драйвер:', 'wp-queue'); ?></strong>
                        <?php echo esc_html(ucfirst($currentDriver)); ?>
                    </p>
                </div>
            <?php } ?>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 150px;"><?php echo esc_html__('Драйвер', 'wp-queue'); ?></th>
                        <th style="width: 120px;"><?php echo esc_html__('Статус', 'wp-queue'); ?></th>
                        <th><?php echo esc_html__('Описание', 'wp-queue'); ?></th>
                        <th style="width: 100px;"><?php echo esc_html__('Активен', 'wp-queue'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($drivers as $name => $info) {
                        $isActive = ($name === $currentDriver);
                        $isConfigured = ($name === $configuredDriver);
                        $status = $info['status'] ?? 'unknown';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html(ucfirst($name)); ?></strong>
                                <?php if ($isConfigured && ! $isActive) { ?>
                                    <br><small style="color: #d63638;"><?php echo esc_html__('(настроен)', 'wp-queue'); ?></small>
                                <?php } ?>
                            </td>
                            <td>
                                <?php echo $this->renderDriverStatusBadge($status, $info); ?>
                            </td>
                            <td>
                                <?php echo esc_html($info['message'] ?? $info['info'] ?? ''); ?>
                                <?php if ($status === \WPQueue\QueueManager::STATUS_NO_EXTENSION) { ?>
                                    <br><small class="description">
                                        <?php if ($name === 'redis') { ?>
                                            <?php echo esc_html__('Установите: pecl install redis', 'wp-queue'); ?>
                                        <?php } elseif ($name === 'memcached') { ?>
                                            <?php echo esc_html__('Установите: pecl install memcached', 'wp-queue'); ?>
                                        <?php } ?>
                                    </small>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($isActive) { ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="wp-queue-help" style="margin-top: 20px;">
                <h3><?php echo esc_html__('Как изменить драйвер?', 'wp-queue'); ?></h3>
                <p><?php echo esc_html__('Добавьте в wp-config.php:', 'wp-queue'); ?></p>
                <pre><code>define('WP_QUEUE_DRIVER', 'database'); // или 'redis', 'memcached', 'sync', 'auto'</code></pre>

                <h4><?php echo esc_html__('Настройка Redis', 'wp-queue'); ?></h4>
                <pre><code>define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_PASSWORD', ''); // если требуется
define('WP_QUEUE_DRIVER', 'redis');</code></pre>

                <h4><?php echo esc_html__('Настройка Memcached', 'wp-queue'); ?></h4>
                <pre><code>define('WP_MEMCACHED_HOST', '127.0.0.1');
define('WP_MEMCACHED_PORT', 11211);
define('WP_QUEUE_DRIVER', 'memcached');</code></pre>

                <div class="notice notice-info inline" style="margin-top: 15px;">
                    <p>
                        <strong><?php echo esc_html__('Важно:', 'wp-queue'); ?></strong>
                        <?php echo esc_html__('Для работы Redis/Memcached требуется установленное PHP-расширение И доступный сервер. Если условия не выполнены, система автоматически использует драйвер "database".', 'wp-queue'); ?>
                    </p>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Render driver status badge with appropriate styling.
     *
     * @param  string  $status  Driver status constant
     * @param  array<string, mixed>  $info  Driver info
     */
    protected function renderDriverStatusBadge(string $status, array $info): string
    {
        return match ($status) {
            \WPQueue\QueueManager::STATUS_READY => sprintf(
                '<span class="status-badge status-completed">%s</span>',
                esc_html__('Готов', 'wp-queue'),
            ),
            \WPQueue\QueueManager::STATUS_NO_EXTENSION => sprintf(
                '<span class="status-badge status-failed">%s</span>',
                esc_html__('Нет расширения', 'wp-queue'),
            ),
            \WPQueue\QueueManager::STATUS_NO_SERVER => sprintf(
                '<span class="status-badge status-pending">%s</span>',
                esc_html__('Нет сервера', 'wp-queue'),
            ),
            default => sprintf(
                '<span class="status-badge status-failed">%s</span>',
                esc_html__('Недоступен', 'wp-queue'),
            ),
        };
    }

    protected function renderRecentLogs(int $limit): void
    {
        $logs = WPQueue::logs()->recent($limit);
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Время', 'wp-queue'); ?></th>
                    <th><?php echo esc_html__('Статус', 'wp-queue'); ?></th>
                    <th><?php echo esc_html__('Задача', 'wp-queue'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)) { ?>
                    <tr>
                        <td colspan="3" class="no-items"><?php echo esc_html__('Нет активности', 'wp-queue'); ?></td>
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

    /**
     * Получить джобы из очереди с детальной информацией
     *
     * @return array<string, array{class: string, attempts: int, available_at: int, reserved_at: int|null, payload_preview: string}>
     */
    protected function getQueueJobs(string $queueName): array
    {
        $rawJobs = get_site_option('wp_queue_jobs_'.$queueName, []);
        $jobs = [];

        foreach ($rawJobs as $id => $data) {
            $class = __('Неизвестно', 'wp-queue');
            $payloadPreview = '';

            // Попробуем десериализовать payload для получения класса
            if (isset($data['payload'])) {
                try {
                    $job = @unserialize($data['payload']);
                    if (is_object($job)) {
                        $class = get_class($job);
                        // Получаем превью данных джоба
                        if (method_exists($job, 'toArray')) {
                            $arr = $job->toArray();
                            unset($arr['id'], $arr['queue'], $arr['attempts']);
                            $payloadPreview = substr(wp_json_encode($arr), 0, 100);
                        }
                    }
                } catch (\Throwable $e) {
                    // Игнорируем ошибки десериализации
                }
            }

            $jobs[$id] = [
                'class' => $class,
                'attempts' => $data['attempts'] ?? 0,
                'available_at' => $data['available_at'] ?? 0,
                'reserved_at' => $data['reserved_at'] ?? null,
                'payload_preview' => $payloadPreview,
            ];
        }

        return $jobs;
    }

    /**
     * @param  array<string, array{reserved_at: int|null, available_at: int}>  $jobs
     */
    protected function countPendingJobs(array $jobs): int
    {
        $count = 0;
        $now = time();
        foreach ($jobs as $job) {
            if (empty($job['reserved_at']) && ($job['available_at'] ?? 0) <= $now) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, array{reserved_at: int|null}>  $jobs
     */
    protected function countReservedJobs(array $jobs): int
    {
        $count = 0;
        foreach ($jobs as $job) {
            if (! empty($job['reserved_at'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Обзор планировщика
     */
    protected function renderSchedulerOverview(): void
    {
        $monitor = new CronMonitor();
        $stats = $monitor->getStats();
        $scheduler = WPQueue::scheduler();
        $jobs = $scheduler->getJobs();
        ?>
        <div class="wp-queue-content-wrapper">
            <!-- Статистика планировщика -->
            <div class="wp-queue-stats">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab=scheduler&section=events')); ?>" class="stat-card stat-pending">
                    <span class="stat-number"><?php echo esc_html((string) $stats['total']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Всего событий', 'wp-queue'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab=scheduler&section=scheduled')); ?>" class="stat-card stat-running">
                    <span class="stat-number"><?php echo esc_html((string) count($jobs)); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Запланировано', 'wp-queue'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab=scheduler&section=events&filter=overdue')); ?>" class="stat-card stat-failed">
                    <span class="stat-number"><?php echo esc_html((string) $stats['overdue']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Просрочено', 'wp-queue'); ?></span>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab=scheduler&section=paused')); ?>" class="stat-card">
                    <span class="stat-number"><?php echo esc_html((string) count($monitor->getPaused())); ?></span>
                    <span class="stat-label"><?php echo esc_html__('На паузе', 'wp-queue'); ?></span>
                </a>
            </div>

            <!-- Информация о cron -->
            <div class="wp-queue-driver-info">
                <span class="dashicons dashicons-clock"></span>
                <?php echo esc_html__('WP-Cron:', 'wp-queue'); ?>
                <?php if (defined('DISABLE_WP_CRON') && constant('DISABLE_WP_CRON')) { ?>
                    <span class="status-badge status-failed"><?php echo esc_html__('Отключён', 'wp-queue'); ?></span>
                    <span class="description"><?php echo esc_html__('Используйте системный cron', 'wp-queue'); ?></span>
                <?php } else { ?>
                    <span class="status-badge status-completed"><?php echo esc_html__('Активен', 'wp-queue'); ?></span>
                <?php } ?>
            </div>

            <!-- События по источникам -->
            <h2><?php echo esc_html__('События по источникам', 'wp-queue'); ?></h2>
            <div class="wp-queue-cards">
                <?php foreach ($stats['by_source'] as $source => $count) { ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp-queue&tab=scheduler&section=events&filter='.urlencode($source))); ?>" class="queue-card">
                        <div class="queue-card-header">
                            <span class="queue-card-title"><?php echo esc_html(ucfirst($source)); ?></span>
                        </div>
                        <div class="queue-card-body">
                            <div class="queue-card-stat">
                                <span class="queue-stat-number"><?php echo esc_html((string) $count); ?></span>
                                <span class="queue-stat-label"><?php echo esc_html__('событий', 'wp-queue'); ?></span>
                            </div>
                        </div>
                        <div class="queue-card-footer">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>
                    </a>
                <?php } ?>
            </div>

            <!-- Ближайшие события -->
            <h2><?php echo esc_html__('Ближайшие события', 'wp-queue'); ?></h2>
            <?php
                $upcomingEvents = array_slice($monitor->getAllEvents(), 0, 5);
        if (empty($upcomingEvents)) { ?>
                <p class="description"><?php echo esc_html__('Нет запланированных событий', 'wp-queue'); ?></p>
            <?php } else { ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Хук', 'wp-queue'); ?></th>
                            <th style="width: 150px;"><?php echo esc_html__('Следующий запуск', 'wp-queue'); ?></th>
                            <th style="width: 100px;"><?php echo esc_html__('Источник', 'wp-queue'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingEvents as $event) { ?>
                            <tr>
                                <td><code><?php echo esc_html($event['hook']); ?></code></td>
                                <td><?php echo esc_html($event['next_run']); ?></td>
                                <td><span class="status-badge status-<?php echo esc_attr($event['source']); ?>"><?php echo esc_html(ucfirst($event['source'])); ?></span></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    <?php
    }

    protected function renderSchedulerEvents(): void
    {
        $monitor = new CronMonitor();
        $filter = sanitize_key($_GET['filter'] ?? 'all');

        $events = match ($filter) {
            'wordpress' => array_filter($monitor->getAllEvents(), fn ($e) => $e['source'] === 'wordpress'),
            'woocommerce' => array_filter($monitor->getAllEvents(), fn ($e) => $e['source'] === 'woocommerce'),
            'wp-queue' => array_filter($monitor->getAllEvents(), fn ($e) => $e['source'] === 'wp-queue'),
            'plugin' => array_filter($monitor->getAllEvents(), fn ($e) => $e['source'] === 'plugin'),
            'overdue' => array_filter($monitor->getAllEvents(), fn ($e) => $e['is_overdue']),
            default => $monitor->getAllEvents(),
        };

        $stats = $monitor->getStats();
        ?>
        <div class="wp-queue-content-wrapper">
            <div class="wp-queue-stats">
                <a href="?page=wp-queue&tab=scheduler&section=events&filter=all" class="stat-card <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <span class="stat-number"><?php echo esc_html((string) $stats['total']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Всего', 'wp-queue'); ?></span>
                </a>
                <a href="?page=wp-queue&tab=scheduler&section=events&filter=overdue" class="stat-card stat-failed <?php echo $filter === 'overdue' ? 'active' : ''; ?>">
                    <span class="stat-number"><?php echo esc_html((string) $stats['overdue']); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Просрочено', 'wp-queue'); ?></span>
                </a>
                <?php foreach ($stats['by_source'] as $source => $count) { ?>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo esc_html((string) $count); ?></span>
                        <span class="stat-label"><?php echo esc_html(ucfirst($source)); ?></span>
                    </div>
                <?php } ?>
            </div>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="?page=wp-queue&tab=scheduler&section=events&filter=all" class="button <?php echo $filter === 'all' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('All', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=scheduler&section=events&filter=wordpress" class="button <?php echo $filter === 'wordpress' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('WordPress', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=scheduler&section=events&filter=woocommerce" class="button <?php echo $filter === 'woocommerce' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('WooCommerce', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=scheduler&section=events&filter=wp-queue" class="button <?php echo $filter === 'wp-queue' ? 'button-primary' : ''; ?>">
                        <?php echo esc_html__('WP Queue', 'wp-queue'); ?>
                    </a>
                    <a href="?page=wp-queue&tab=scheduler&section=events&filter=plugin" class="button <?php echo $filter === 'plugin' ? 'button-primary' : ''; ?>">
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

    protected function renderDiagnosticsEnvironment(): void
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

    /**
     * Статус системы - объединённый с информацией
     */
    protected function renderSystemStatus(): void
    {
        $status = new SystemStatus();
        $report = $status->getFullReport();
        $metrics = WPQueue::logs()->metrics();
        $driver = WPQueue::manager()->getDefaultDriver();

        // Безопасное получение данных
        $phpInfo = $report['php'] ?? [];
        $cronInfo = $report['cron'] ?? [];
        $loopbackInfo = $report['loopback'] ?? [];
        $timeInfo = $report['time_info'] ?? [];
        $wpInfo = $report['wordpress'] ?? [];
        $asInfo = $report['action_scheduler'] ?? [];
        ?>
        <div class="wp-queue-content-wrapper">
            <!-- Статистика -->
            <div class="wp-queue-stats">
                <div class="stat-card stat-completed">
                    <span class="stat-number"><?php echo esc_html((string) ($metrics['completed'] ?? 0)); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Выполнено', 'wp-queue'); ?></span>
                </div>
                <div class="stat-card stat-failed">
                    <span class="stat-number"><?php echo esc_html((string) ($metrics['failed'] ?? 0)); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Ошибок', 'wp-queue'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo esc_html($report['memory_limit_formatted'] ?? 'N/A'); ?></span>
                    <span class="stat-label"><?php echo esc_html__('Memory Limit', 'wp-queue'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo esc_html((string) ($report['max_execution_time'] ?? 0)); ?>s</span>
                    <span class="stat-label"><?php echo esc_html__('Timeout', 'wp-queue'); ?></span>
                </div>
            </div>

            <!-- Версии -->
            <h2><?php echo esc_html__('Версии', 'wp-queue'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <th scope="row" style="width:200px;"><?php echo esc_html__('WP Queue', 'wp-queue'); ?></th>
                        <td><code><?php echo esc_html(defined('WP_QUEUE_VERSION') ? WP_QUEUE_VERSION : '1.0.0'); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('WordPress', 'wp-queue'); ?></th>
                        <td><code><?php echo esc_html($wpInfo['version'] ?? get_bloginfo('version')); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('PHP', 'wp-queue'); ?></th>
                        <td><code><?php echo esc_html($phpInfo['version'] ?? PHP_VERSION); ?></code></td>
                    </tr>
                </tbody>
            </table>

            <!-- Статус компонентов -->
            <h2><?php echo esc_html__('Статус компонентов', 'wp-queue'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <th scope="row" style="width:200px;"><?php echo esc_html__('Драйвер очередей', 'wp-queue'); ?></th>
                        <td>
                            <span class="status-badge status-completed"><?php echo esc_html(ucfirst($driver)); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('WP-Cron', 'wp-queue'); ?></th>
                        <td>
                            <?php if ($cronInfo['disabled'] ?? false) { ?>
                                <span class="status-badge status-failed"><?php echo esc_html__('Отключён', 'wp-queue'); ?></span>
                                <span class="description"><?php echo esc_html__('Настройте системный cron', 'wp-queue'); ?></span>
                            <?php } else { ?>
                                <span class="status-badge status-completed"><?php echo esc_html__('Активен', 'wp-queue'); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Loopback', 'wp-queue'); ?></th>
                        <td>
                            <?php
                                $loopbackStatus = $loopbackInfo['status'] ?? 'unknown';
        $statusClass = match ($loopbackStatus) {
            'ok' => 'completed',
            'warning' => 'pending',
            default => 'failed',
        };
        ?>
                            <span class="status-badge status-<?php echo esc_attr($statusClass); ?>">
                                <?php echo esc_html(ucfirst($loopbackStatus)); ?>
                            </span>
                            <?php if ($loopbackStatus !== 'ok' && isset($loopbackInfo['message'])) { ?>
                                <span class="description"><?php echo esc_html($loopbackInfo['message']); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- PHP -->
            <h2><?php echo esc_html__('PHP', 'wp-queue'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <th scope="row" style="width:200px;"><?php echo esc_html__('Memory Limit', 'wp-queue'); ?></th>
                        <td><?php echo esc_html($phpInfo['memory_limit'] ?? ini_get('memory_limit')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Использовано', 'wp-queue'); ?></th>
                        <td><?php echo esc_html($phpInfo['memory_usage'] ?? size_format(memory_get_usage(true))); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Max Execution Time', 'wp-queue'); ?></th>
                        <td><?php echo esc_html((string) ($phpInfo['max_execution_time'] ?? ini_get('max_execution_time'))); ?> сек</td>
                    </tr>
                </tbody>
            </table>

            <!-- Время -->
            <h2><?php echo esc_html__('Время', 'wp-queue'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <th scope="row" style="width:200px;"><?php echo esc_html__('Время сервера', 'wp-queue'); ?></th>
                        <td><?php echo esc_html($timeInfo['server_time'] ?? gmdate('Y-m-d H:i:s')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Время WordPress', 'wp-queue'); ?></th>
                        <td><?php echo esc_html($timeInfo['wp_time'] ?? wp_date('Y-m-d H:i:s')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Часовой пояс', 'wp-queue'); ?></th>
                        <td>
                            <?php
        $offset = $timeInfo['gmt_offset'] ?? get_option('gmt_offset', 0);
        echo 'GMT '.($offset >= 0 ? '+' : '').esc_html((string) $offset);
        ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if ($asInfo['installed'] ?? false) { ?>
                <h2><?php echo esc_html__('Action Scheduler', 'wp-queue'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <th scope="row" style="width:200px;"><?php echo esc_html__('Версия', 'wp-queue'); ?></th>
                            <td><code><?php echo esc_html($asInfo['version'] ?? 'Unknown'); ?></code></td>
                        </tr>
                        <?php if (isset($asInfo['stats'])) { ?>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Ожидающих', 'wp-queue'); ?></th>
                                <td><?php echo esc_html((string) ($asInfo['stats']['pending'] ?? 0)); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    <?php
    }

    /**
     * Информация о системе - редирект на статус
     */
    protected function renderSystemInfo(): void
    {
        $this->renderSystemStatus();
    }

    /**
     * Инструменты
     */
    protected function renderSystemTools(): void
    {
        ?>
        <div class="wp-queue-content-wrapper">
            <h2><?php echo esc_html__('Инструменты', 'wp-queue'); ?></h2>

            <div class="wp-queue-tools-grid">
                <div class="tool-card">
                    <h3><span class="dashicons dashicons-trash"></span> <?php echo esc_html__('Очистить логи', 'wp-queue'); ?></h3>
                    <p class="description"><?php echo esc_html__('Удаляет все логи выполнения задач', 'wp-queue'); ?></p>
                    <button class="button wp-queue-clear-all-logs"><?php echo esc_html__('Очистить все логи', 'wp-queue'); ?></button>
                </div>

                <div class="tool-card">
                    <h3><span class="dashicons dashicons-update"></span> <?php echo esc_html__('Обработать очереди', 'wp-queue'); ?></h3>
                    <p class="description"><?php echo esc_html__('Принудительно запускает обработку всех очередей', 'wp-queue'); ?></p>
                    <button class="button wp-queue-process-all"><?php echo esc_html__('Обработать сейчас', 'wp-queue'); ?></button>
                </div>

                <div class="tool-card">
                    <h3><span class="dashicons dashicons-backup"></span> <?php echo esc_html__('Очистить все очереди', 'wp-queue'); ?></h3>
                    <p class="description"><?php echo esc_html__('Удаляет все задачи из всех очередей', 'wp-queue'); ?></p>
                    <button class="button button-link-delete wp-queue-clear-all-queues"><?php echo esc_html__('Очистить все', 'wp-queue'); ?></button>
                </div>
            </div>

            <h2><?php echo esc_html__('WP-CLI команды', 'wp-queue'); ?></h2>
            <div class="wp-queue-help">
                <pre><code># Обработать очередь
wp queue work --queue=default

# Статус очередей
wp queue status

# Очистить очередь
wp queue clear default

# Список cron событий
wp queue cron list</code></pre>
            </div>
        </div>
    <?php
    }

    /**
     * Справка - полностью переработанная
     */
    protected function renderSystemHelp(): void
    {
        ?>
        <div class="wp-queue-content-wrapper wp-queue-help-page">
            <div class="help-intro">
                <h2><?php echo esc_html__('WP Queue — система очередей для WordPress', 'wp-queue'); ?></h2>
                <p class="description">
                    <?php echo esc_html__('WP Queue позволяет выполнять задачи асинхронно в фоновом режиме, не блокируя основной поток выполнения WordPress.', 'wp-queue'); ?>
                </p>
            </div>

            <div class="help-grid">
                <!-- Быстрый старт -->
                <div class="help-card">
                    <div class="help-card-icon">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                    </div>
                    <h3><?php echo esc_html__('Быстрый старт', 'wp-queue'); ?></h3>
                    <p><?php echo esc_html__('Создайте класс задачи и отправьте её в очередь:', 'wp-queue'); ?></p>
                    <pre><code>use WPQueue\Jobs\Job;

class MyJob extends Job {
    public function handle(): void {
        // Ваш код
    }
}

// Отправка в очередь
WPQueue::dispatch(new MyJob());</code></pre>
                </div>

                <!-- Отложенные задачи -->
                <div class="help-card">
                    <div class="help-card-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <h3><?php echo esc_html__('Отложенные задачи', 'wp-queue'); ?></h3>
                    <p><?php echo esc_html__('Запланируйте выполнение задачи через определённое время:', 'wp-queue'); ?></p>
                    <pre><code>// Через 5 минут
WPQueue::dispatch(
    (new MyJob())->delay(300)
);

// В определённое время
WPQueue::dispatch(
    (new MyJob())->delay(
        strtotime('tomorrow 9:00')
    )
);</code></pre>
                </div>

                <!-- Именованные очереди -->
                <div class="help-card">
                    <div class="help-card-icon">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                    <h3><?php echo esc_html__('Именованные очереди', 'wp-queue'); ?></h3>
                    <p><?php echo esc_html__('Распределяйте задачи по разным очередям:', 'wp-queue'); ?></p>
                    <pre><code>// Отправить в очередь emails
WPQueue::dispatch(
    (new SendEmailJob())->onQueue('emails')
);

// Отправить в очередь sync
WPQueue::dispatch(
    (new SyncDataJob())->onQueue('sync')
);</code></pre>
                </div>

                <!-- Повторные попытки -->
                <div class="help-card">
                    <div class="help-card-icon">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <h3><?php echo esc_html__('Повторные попытки', 'wp-queue'); ?></h3>
                    <p><?php echo esc_html__('Настройте автоматические повторы при ошибках:', 'wp-queue'); ?></p>
                    <pre><code>class MyJob extends Job {
    protected int $maxAttempts = 3;
    protected int $timeout = 60;

    public function handle(): void {
        // Код задачи
    }

    public function failed(\Throwable $e): void {
        // Обработка ошибки
    }
}</code></pre>
                </div>

                <!-- Планировщик -->
                <div class="help-card">
                    <div class="help-card-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <h3><?php echo esc_html__('Планировщик', 'wp-queue'); ?></h3>
                    <p><?php echo esc_html__('Запускайте задачи по расписанию:', 'wp-queue'); ?></p>
                    <pre><code>// Каждый час
WPQueue::scheduler()->hourly(
    new CleanupJob()
);

// Ежедневно в 3:00
WPQueue::scheduler()->dailyAt(
    new ReportJob(), '03:00'
);

// Каждые 5 минут
WPQueue::scheduler()->everyMinutes(
    new CheckJob(), 5
);</code></pre>
                </div>

                <!-- WP-CLI -->
                <div class="help-card">
                    <div class="help-card-icon">
                        <span class="dashicons dashicons-editor-code"></span>
                    </div>
                    <h3><?php echo esc_html__('WP-CLI команды', 'wp-queue'); ?></h3>
                    <p><?php echo esc_html__('Управляйте очередями из командной строки:', 'wp-queue'); ?></p>
                    <pre><code># Обработать очередь
wp queue work --queue=default

# Статус очередей
wp queue status

# Очистить очередь
wp queue clear default

# Список cron событий
wp queue cron list

# Запустить cron событие
wp queue cron run hook_name</code></pre>
                </div>
            </div>

            <!-- Полезные ссылки -->
            <div class="help-links">
                <h3><?php echo esc_html__('Полезные ссылки', 'wp-queue'); ?></h3>
                <ul>
                    <li>
                        <span class="dashicons dashicons-book"></span>
                        <a href="https://github.com/developer/wp-queue" target="_blank">
                            <?php echo esc_html__('Документация на GitHub', 'wp-queue'); ?>
                        </a>
                    </li>
                    <li>
                        <span class="dashicons dashicons-sos"></span>
                        <a href="https://github.com/developer/wp-queue/issues" target="_blank">
                            <?php echo esc_html__('Сообщить о проблеме', 'wp-queue'); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    <?php
    }

    /**
     * Запланированные задачи (scheduled jobs)
     */
    protected function renderSchedulerScheduled(): void
    {
        $this->renderQueuesJobs();
    }

    /**
     * Приостановленные cron события
     */
    protected function renderSchedulerPaused(): void
    {
        $monitor = new CronMonitor();
        $paused = $monitor->getPaused();
        ?>
        <div class="wp-queue-paused">
            <?php if (empty($paused)) { ?>
                <div class="notice notice-info" style="margin: 0;">
                    <p><?php echo esc_html__('Нет приостановленных событий', 'wp-queue'); ?></p>
                </div>
            <?php } else { ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Хук', 'wp-queue'); ?></th>
                            <th style="width: 120px;"><?php echo esc_html__('Расписание', 'wp-queue'); ?></th>
                            <th style="width: 150px;"><?php echo esc_html__('Приостановлено', 'wp-queue'); ?></th>
                            <th style="width: 120px;"><?php echo esc_html__('Действия', 'wp-queue'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paused as $event) { ?>
                            <tr>
                                <td><code><?php echo esc_html($event['hook']); ?></code></td>
                                <td><?php echo esc_html($event['schedule'] ?: __('Одноразовое', 'wp-queue')); ?></td>
                                <td><?php echo esc_html(wp_date('Y-m-d H:i:s', $event['paused_at'])); ?></td>
                                <td>
                                    <button class="button button-small wp-queue-cron-resume"
                                        data-hook="<?php echo esc_attr($event['hook']); ?>"
                                        data-args="<?php echo esc_attr(wp_json_encode($event['args'])); ?>">
                                        <?php echo esc_html__('Возобновить', 'wp-queue'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
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
