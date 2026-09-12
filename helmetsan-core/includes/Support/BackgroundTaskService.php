<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

/**
 * Handles asynchronous background tasks.
 * Abstracts Action Scheduler and WP Cron.
 *
 * This service allows offloading heavy tasks (like image enrichment,
 * rating calculations, or bulk syncs) to background workers.
 * It prioritizes WooCommerce's Action Scheduler for high-performance
 * parallel execution, with a fallback to native WP-Cron single events.
 */
final class BackgroundTaskService
{
    /**
     * Dispatch an asynchronous action to be executed as soon as possible.
     *
     * @param string $hook The action hook to trigger.
     * @param array<string, mixed> $args Arguments to pass to the hook.
     * @param string $group Optional group name for task organization.
     */
    public function dispatch(string $hook, array $args = [], string $group = 'helmetsan'): void
    {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action($hook, $args, $group);
            return;
        }

        // Fallback to WP Cron (single event) if Action Scheduler is not present.
        // We use a small delay (1s) to ensure the current process can finish.
        wp_schedule_single_event(time() + 1, $hook, $args);
    }

    /**
     * Schedule a single action for a specific time in the future.
     */
    public function schedule(int $timestamp, string $hook, array $args = [], string $group = 'helmetsan'): void
    {
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action($timestamp, $hook, $args, $group);
            return;
        }

        wp_schedule_single_event($timestamp, $hook, $args);
    }
}
