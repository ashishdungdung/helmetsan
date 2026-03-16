<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

/**
 * Tracks silent background tasks (CLI/AI) in a WordPress option.
 * Tasks expire if heartbeats stop for 5 minutes.
 */
final class TaskTracker
{
    private const EXPIRY_SECONDS = 300;

    private function getTasksDir(): string
    {
        $dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
        $dir .= '/uploads/helmetsan-data/tasks';
        
        if (! is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }

    public function verify(): bool
    {
        $dir = $this->getTasksDir();
        return is_dir($dir) && is_writable($dir);
    }

    /**
     * @param string $id Unique ID (e.g. CLI process hash or 'fill-missing-helmet')
     * @param string $label Human readable label
     * @param string $type Task type (e.g. 'ai-enrichment', 'seo-seed')
     */
    public function start(string $id, string $label, string $type): void
    {
        $data = [
            'id'        => $id,
            'label'     => $label,
            'type'      => $type,
            'start'     => time(),
            'last_ping' => time(),
            'progress'  => 0,
        ];
        $this->saveTaskFile($id, $data);
    }

    public function heartbeat(string $id, int $progress = 0): void
    {
        $path = $this->getTaskFilePath($id);
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $data = $content ? json_decode($content, true) : null;
            if (is_array($data)) {
                $data['last_ping'] = time();
                $data['progress']  = $progress;
                $this->saveTaskFile($id, $data);
            }
        }
    }

    public function stop(string $id): void
    {
        $path = $this->getTaskFilePath($id);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * @return array<string, array{label: string, type: string, start: int, last_ping: int, progress: int}>
     */
    public function getActiveTasks(): array
    {
        $dir = $this->getTasksDir();
        $files = glob($dir . '/task_*.json');
        if (! $files) {
            return [];
        }

        $now = time();
        $active = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $data = $content ? json_decode($content, true) : null;
            if (! is_array($data) || ! isset($data['id'])) {
                @unlink($file);
                continue;
            }

            if ($now - ($data['last_ping'] ?? 0) > self::EXPIRY_SECONDS) {
                @unlink($file);
                continue;
            }

            $active[$data['id']] = $data;
        }

        return $active;
    }

    private function getTaskFilePath(string $id): string
    {
        return $this->getTasksDir() . '/task_' . sanitize_file_name($id) . '.json';
    }

    private function saveTaskFile(string $id, array $data): void
    {
        file_put_contents($this->getTaskFilePath($id), wp_json_encode($data));
    }
}
