=== WP Queue - Background Job Manager ===
Contributors: rwsite
Donate link: https://rwsite.ru/donate
Tags: queue, cron, background-processing, jobs, scheduler
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Background job processing and WP-Cron management for WordPress. Schedule tasks, manage queues, and monitor cron events.

== Description ==

WP Queue is a modern, scalable solution for WordPress background processing. It provides a clean API for dispatching jobs, scheduling tasks, and monitoring WP-Cron events with a beautiful admin dashboard.

= Features =

* **Laravel-style Job Dispatching** - Clean, fluent API with PHP 8 attributes
* **Multiple Queue Drivers** - Database, Sync, and extensible for Redis
* **Automatic Retries** - Exponential backoff for failed jobs
* **Job Scheduling** - Cron-like scheduling with fluent methods
* **WP-Cron Monitor** - View, run, pause, resume, and edit cron events
* **System Status** - Health checks for PHP, WordPress, and loopback
* **Admin Dashboard** - Beautiful UI with real-time statistics
* **REST API** - Full API for external integrations
* **WP-CLI Support** - Complete command-line interface

= Requirements =

* PHP 8.3 or higher
* WordPress 6.0 or higher

= Usage =

**Creating a Job:**

`
use WPQueue\Jobs\Job;
use WPQueue\Attributes\Queue;
use WPQueue\Attributes\Retries;

#[Queue('emails')]
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
        wp_mail($this->email, 'Subject', 'Body');
    }
}
`

**Dispatching:**

`
use WPQueue\WPQueue;

// Dispatch to queue
WPQueue::dispatch(new SendEmailJob('user@example.com'));

// Dispatch with delay
WPQueue::dispatch(new SendEmailJob('user@example.com'))
    ->delay(300); // 5 minutes
`

= WP-CLI Commands =

`
# Queue commands
wp queue status              # Show queue status
wp queue work [queue]        # Process jobs from queue
wp queue clear [queue]       # Clear all jobs from queue
wp queue failed              # List failed jobs

# Cron commands
wp queue cron list           # List all cron events
wp queue cron run <hook>     # Run a cron event now
wp queue cron delete <hook>  # Delete a cron event
wp queue cron pause <hook>   # Pause a cron event
wp queue cron resume <hook>  # Resume a paused event

# System commands
wp queue system              # Show system status
`

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-queue`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Access the dashboard via WP Admin → WP Queue

**Via Composer:**

`
composer require rwsite/wp-queue
`

== Frequently Asked Questions ==

= What PHP version is required? =

PHP 8.3 or higher is required for PHP 8 attributes support.

= How does it compare to Action Scheduler? =

WP Queue is lighter and uses modern PHP features. Action Scheduler is more mature and has WooCommerce integration. Choose based on your needs.

= Can I use Redis as a queue driver? =

Yes, you can extend the QueueManager with custom drivers including Redis.

= Is it compatible with multisite? =

Yes, WP Queue works with WordPress multisite installations.

== Screenshots ==

1. Dashboard with queue statistics
2. Scheduled jobs management
3. Logs viewer with filtering
4. WP-Cron events monitor
5. System status and health check

== Changelog ==

= 1.0.0 =
* Initial release
* Job dispatching with PHP 8 attributes
* Database and Sync queue drivers
* Job scheduling with fluent API
* WP-Cron monitoring and management
* Admin dashboard with statistics
* REST API endpoints
* WP-CLI commands
* Russian localization

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Queue.
