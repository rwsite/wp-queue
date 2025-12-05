/**
 * WP Queue Admin JavaScript
 */
(function ($) {
    'use strict';

    const WPQueueAdmin = {
        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Queue actions (pause, resume, clear, process)
            $(document).on('click', '.wp-queue-action', this.handleQueueAction.bind(this));

            // Job actions (view, delete)
            $(document).on('click', '.wp-queue-job-action', this.handleJobAction.bind(this));

            // Run job manually
            $(document).on('click', '.wp-queue-run-job', this.handleRunJob.bind(this));

            // Clear logs
            $(document).on('click', '.wp-queue-clear-logs', this.handleClearLogs.bind(this));
            $(document).on('click', '.wp-queue-clear-all-logs', this.handleClearAllLogs.bind(this));

            // Tools actions
            $(document).on('click', '.wp-queue-process-all', this.handleProcessAll.bind(this));
            $(document).on('click', '.wp-queue-clear-all-queues', this.handleClearAllQueues.bind(this));

            // Queue filter
            $(document).on('change', '#queue-filter', this.handleQueueFilter.bind(this));

            // Cron actions
            $(document).on('click', '.wp-queue-cron-run', this.handleCronRun.bind(this));
            $(document).on('click', '.wp-queue-cron-delete', this.handleCronDelete.bind(this));
            $(document).on('click', '.wp-queue-cron-pause', this.handleCronPause.bind(this));
            $(document).on('click', '.wp-queue-cron-resume', this.handleCronResume.bind(this));
            $(document).on('click', '.wp-queue-cron-edit', this.handleCronEdit.bind(this));
            $(document).on('click', '.wp-queue-modal-close', this.closeModal.bind(this));
            $(document).on('submit', '#wp-queue-edit-form', this.handleCronEditSubmit.bind(this));

            // Close modal on escape
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    this.closeModal();
                }
            }.bind(this));
        },

        handleQueueAction: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const action = $btn.data('action');
            const queue = $btn.data('queue');

            if (action === 'clear' && !confirm(wpQueue.i18n.confirm_clear)) {
                return;
            }

            if (action === 'pause' && !confirm(wpQueue.i18n.confirm_pause)) {
                return;
            }

            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/queues/' + queue + '/' + action,
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    if (action === 'process') {
                        this.showToast('Обработано задач: ' + (response.processed || 0), 'success');
                    } else {
                        this.showToast(wpQueue.i18n.success, 'success');
                    }
                    setTimeout(function () {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function (xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function () {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleJobAction: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const action = $btn.data('action');
            const jobId = $btn.data('job');
            const queue = $btn.data('queue');

            if (action === 'delete') {
                if (!confirm('Удалить эту задачу из очереди?')) {
                    return;
                }

                this.setLoading($btn, true);

                $.ajax({
                    url: wpQueue.restUrl + '/jobs/' + encodeURIComponent(jobId) + '/delete',
                    method: 'POST',
                    data: { queue: queue },
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                    },
                    success: function (response) {
                        this.showToast('Задача удалена', 'success');
                        setTimeout(function () {
                            location.reload();
                        }, 500);
                    }.bind(this),
                    error: function (xhr) {
                        this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                    }.bind(this),
                    complete: function () {
                        this.setLoading($btn, false);
                    }.bind(this)
                });
            } else if (action === 'view') {
                this.viewJobDetails(jobId, queue);
            }
        },

        viewJobDetails: function (jobId, queue) {
            const $modal = $('#wp-queue-job-modal');
            const $details = $('#wp-queue-job-details');

            $details.html('<p>Загрузка...</p>');
            $modal.show();

            $.ajax({
                url: wpQueue.restUrl + '/jobs/' + encodeURIComponent(jobId),
                method: 'GET',
                data: { queue: queue },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    let html = '<table class="wp-list-table widefat fixed striped">';
                    html += '<tbody>';
                    html += '<tr><th>ID</th><td><code>' + (response.id || jobId) + '</code></td></tr>';
                    html += '<tr><th>Класс</th><td><code>' + (response.class || 'Неизвестно') + '</code></td></tr>';
                    html += '<tr><th>Очередь</th><td>' + (response.queue || queue) + '</td></tr>';
                    html += '<tr><th>Попытки</th><td>' + (response.attempts || 0) + '</td></tr>';
                    html += '<tr><th>Доступна с</th><td>' + (response.available_at ? new Date(response.available_at * 1000).toLocaleString() : '-') + '</td></tr>';
                    html += '<tr><th>Зарезервирована</th><td>' + (response.reserved_at ? new Date(response.reserved_at * 1000).toLocaleString() : 'Нет') + '</td></tr>';
                    if (response.payload) {
                        html += '<tr><th>Данные</th><td><pre style="max-height:200px;overflow:auto;font-size:11px;">' + JSON.stringify(response.payload, null, 2) + '</pre></td></tr>';
                    }
                    html += '</tbody></table>';
                    $details.html(html);
                }.bind(this),
                error: function (xhr) {
                    $details.html('<p class="error">Не удалось загрузить детали задачи</p>');
                }.bind(this)
            });
        },

        handleRunJob: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const jobClass = $btn.data('job');

            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/jobs/' + encodeURIComponent(jobClass) + '/run',
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    this.showToast(response.message || wpQueue.i18n.success, 'success');
                }.bind(this),
                error: function (xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function () {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleClearLogs: function (e) {
            e.preventDefault();

            if (!confirm('Очистить логи старше 7 дней?')) {
                return;
            }

            const $btn = $(e.currentTarget);
            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/logs/clear',
                method: 'POST',
                data: { days: 7 },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    this.showToast('Очищено логов: ' + response.cleared, 'success');
                    setTimeout(function () {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function (xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function () {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleClearAllLogs: function (e) {
            e.preventDefault();

            if (!confirm('Удалить ВСЕ логи? Это действие нельзя отменить.')) {
                return;
            }

            const $btn = $(e.currentTarget);
            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/logs/clear',
                method: 'POST',
                data: { all: true },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    this.showToast('Все логи удалены', 'success');
                    setTimeout(function () {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function (xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function () {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleProcessAll: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/process',
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    this.showToast('Обработано задач: ' + (response.processed || 0), 'success');
                    setTimeout(function () {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function (xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function () {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleClearAllQueues: function (e) {
            e.preventDefault();

            if (!confirm('Очистить ВСЕ очереди? Это удалит все задачи!')) {
                return;
            }

            const $btn = $(e.currentTarget);
            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/queues/clear-all',
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    this.showToast('Все очереди очищены', 'success');
                    setTimeout(function () {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function (xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function () {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleQueueFilter: function (e) {
            const queueFilter = $(e.currentTarget).val();
            const url = new URL(window.location.href);

            if (queueFilter) {
                url.searchParams.set('queue_filter', queueFilter);
            } else {
                url.searchParams.delete('queue_filter');
            }
            url.searchParams.delete('paged');

            window.location.href = url.toString();
        },

        setLoading: function ($element, loading) {
            if (loading) {
                $element.addClass('wp-queue-loading').prop('disabled', true);
            } else {
                $element.removeClass('wp-queue-loading').prop('disabled', false);
            }
        },

        handleCronRun: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const hook = $btn.data('hook');
            const args = $btn.data('args');

            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/cron/run',
                method: 'POST',
                data: { hook: hook, args: JSON.stringify(args) },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    this.showToast(response.message || wpQueue.i18n.success, 'success');
                }.bind(this),
                error: function (xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function () {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleCronDelete: function (e) {
            e.preventDefault();

            if (!confirm('Delete this cron event?')) {
                return;
            }

            const $btn = $(e.currentTarget);
            const hook = $btn.data('hook');
            const timestamp = $btn.data('timestamp');
            const args = $btn.data('args');

            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/cron/delete',
                method: 'POST',
                data: { hook: hook, timestamp: timestamp, args: JSON.stringify(args) },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    this.showToast(wpQueue.i18n.success, 'success');
                    setTimeout(function () {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function (xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function () {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleCronPause: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const hook = $btn.data('hook');
            const timestamp = $btn.data('timestamp');
            const args = $btn.data('args');

            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/cron/pause',
                method: 'POST',
                data: { hook: hook, timestamp: timestamp, args: JSON.stringify(args) },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    this.showToast(response.message || wpQueue.i18n.success, 'success');
                    setTimeout(function () {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function (xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function () {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleCronResume: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const hook = $btn.data('hook');
            const args = $btn.data('args');

            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/cron/resume',
                method: 'POST',
                data: { hook: hook, args: JSON.stringify(args) },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    this.showToast(response.message || wpQueue.i18n.success, 'success');
                    setTimeout(function () {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function (xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function () {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleCronEdit: function (e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const hook = $btn.data('hook');
            const timestamp = $btn.data('timestamp');
            const args = $btn.data('args');
            const schedule = $btn.data('schedule');

            $('#edit-hook').val(hook);
            $('#edit-timestamp').val(timestamp);
            $('#edit-args').val(JSON.stringify(args));
            $('#edit-schedule').val(schedule);

            $('#wp-queue-edit-modal').show();
        },

        handleCronEditSubmit: function (e) {
            e.preventDefault();

            const hook = $('#edit-hook').val();
            const timestamp = $('#edit-timestamp').val();
            const args = $('#edit-args').val();
            const schedule = $('#edit-schedule').val();

            const $btn = $('#wp-queue-edit-form button[type="submit"]');
            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/cron/edit',
                method: 'POST',
                data: { hook: hook, timestamp: timestamp, args: args, schedule: schedule },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function (response) {
                    this.showToast(response.message || wpQueue.i18n.success, 'success');
                    this.closeModal();
                    setTimeout(function () {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function (xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function () {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        closeModal: function () {
            $('#wp-queue-edit-modal').hide();
        },

        showToast: function (message, type) {
            const $toast = $('<div class="wp-queue-toast ' + type + '">' + message + '</div>');
            $('body').append($toast);

            setTimeout(function () {
                $toast.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(document).ready(function () {
        WPQueueAdmin.init();
    });

})(jQuery);
