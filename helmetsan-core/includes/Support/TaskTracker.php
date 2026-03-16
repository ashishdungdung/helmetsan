<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

/**
 * Tracks silent background tasks (CLI/AI) in a WordPress option.
 * Tasks expire if heartbeats stop for 5 minutes.
 */
final class TaskTracker
{
    private const OPTION_NAME = 'helmetsan_active_tasks';
    private const EXPIRY_SECONDS = 300;

    /**
     * @param string $id Unique ID (e.g. CLI process hash or 'fill-missing-helmet')
     * @param string $label Human readable label
     * @param string $type Task type (e.g. 'ai-enrichment', 'seo-seed')
     */
    public function start(string $id, string $label, string $type): void
    {
        $tasks = $this->getActiveTasks();
        $tasks[$id] = [
            'label'     => $label,
            'type'      => $type,
            'start'     => time(),
            'last_ping' => time(),
            'progress'  => 0,
        ];
        $this->saveTasks($tasks);
    }

    public function heartbeat(string $id, int $progress = 0): void
    {
        $tasks = $this->getActiveTasks();
        if (isset($tasks[$id])) {
            $tasks[$id]['last_ping'] = time();
            $tasks[$id]['progress']  = $progress;
            $this->saveTasks($tasks);
        }
    }

    public function stop(string $id): void
    {
        $tasks = $this->getActiveTasks();
        if (isset($tasks[$id])) {
            unset($tasks[$id]);
            $this->saveTasks($tasks);
        }
    }

    /**
     * @return array<string, array{label: string, type: string, start: int, last_ping: int, progress: int}>
     */
    public function getActiveTasks(): array
    {
        $tasks = get_option(self::OPTION_NAME, []);
        if (! is_array($tasks)) {
            return [];
        }

        $now = time();
        $filtered = [];
        foreach ($tasks as $id => $data) {
            if ($now - ($data['last_ping'] ?? 0) < self::EXPIRY_SECONDS) {
                $filtered[$id] = $data;
            }
        }

        if (count($filtered) !== count($tasks)) {
            $this->saveTasks($filtered);
        }

        return $filtered;
    }

    private function saveTasks(array $tasks): void
    {
        update_option(self::OPTION_NAME, $tasks, false);
    }
}
