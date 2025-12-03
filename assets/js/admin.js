/**
 * WP Queue Admin JavaScript
 */
(function($) {
    'use strict';

    const WPQueueAdmin = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Queue actions (pause, resume, clear)
            $(document).on('click', '.wp-queue-action', this.handleQueueAction.bind(this));
            
            // Run job manually
            $(document).on('click', '.wp-queue-run-job', this.handleRunJob.bind(this));
            
            // Clear logs
            $(document).on('click', '.wp-queue-clear-logs', this.handleClearLogs.bind(this));
            
            // Cron actions
            $(document).on('click', '.wp-queue-cron-run', this.handleCronRun.bind(this));
            $(document).on('click', '.wp-queue-cron-delete', this.handleCronDelete.bind(this));
            $(document).on('click', '.wp-queue-cron-pause', this.handleCronPause.bind(this));
            $(document).on('click', '.wp-queue-cron-resume', this.handleCronResume.bind(this));
            $(document).on('click', '.wp-queue-cron-edit', this.handleCronEdit.bind(this));
            $(document).on('click', '.wp-queue-modal-close', this.closeModal.bind(this));
            $(document).on('submit', '#wp-queue-edit-form', this.handleCronEditSubmit.bind(this));
        },

        handleQueueAction: function(e) {
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
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function(response) {
                    this.showToast(wpQueue.i18n.success, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function(xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function() {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleRunJob: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const jobClass = $btn.data('job');

            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/jobs/' + encodeURIComponent(jobClass) + '/run',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function(response) {
                    this.showToast(response.message || wpQueue.i18n.success, 'success');
                }.bind(this),
                error: function(xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function() {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleClearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('Clear logs older than 7 days?')) {
                return;
            }

            const $btn = $(e.currentTarget);
            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/logs/clear',
                method: 'POST',
                data: { days: 7 },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function(response) {
                    this.showToast('Cleared ' + response.cleared + ' old logs', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function(xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function() {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        setLoading: function($element, loading) {
            if (loading) {
                $element.addClass('wp-queue-loading').prop('disabled', true);
            } else {
                $element.removeClass('wp-queue-loading').prop('disabled', false);
            }
        },

        handleCronRun: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const hook = $btn.data('hook');
            const args = $btn.data('args');

            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/cron/run',
                method: 'POST',
                data: { hook: hook, args: JSON.stringify(args) },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function(response) {
                    this.showToast(response.message || wpQueue.i18n.success, 'success');
                }.bind(this),
                error: function(xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function() {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleCronDelete: function(e) {
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
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function(response) {
                    this.showToast(wpQueue.i18n.success, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function(xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function() {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleCronPause: function(e) {
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
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function(response) {
                    this.showToast(response.message || wpQueue.i18n.success, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function(xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function() {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleCronResume: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const hook = $btn.data('hook');
            const args = $btn.data('args');

            this.setLoading($btn, true);

            $.ajax({
                url: wpQueue.restUrl + '/cron/resume',
                method: 'POST',
                data: { hook: hook, args: JSON.stringify(args) },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function(response) {
                    this.showToast(response.message || wpQueue.i18n.success, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function(xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function() {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        handleCronEdit: function(e) {
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

        handleCronEditSubmit: function(e) {
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
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpQueue.nonce);
                },
                success: function(response) {
                    this.showToast(response.message || wpQueue.i18n.success, 'success');
                    this.closeModal();
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                }.bind(this),
                error: function(xhr) {
                    this.showToast(xhr.responseJSON?.message || wpQueue.i18n.error, 'error');
                }.bind(this),
                complete: function() {
                    this.setLoading($btn, false);
                }.bind(this)
            });
        },

        closeModal: function() {
            $('#wp-queue-edit-modal').hide();
        },

        showToast: function(message, type) {
            const $toast = $('<div class="wp-queue-toast ' + type + '">' + message + '</div>');
            $('body').append($toast);
            
            setTimeout(function() {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(document).ready(function() {
        WPQueueAdmin.init();
    });

})(jQuery);
