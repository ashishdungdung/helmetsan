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

    public function requestCancellation(string $id): void
    {
        $path = $this->getTaskFilePath($id);
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $data = $content ? json_decode($content, true) : null;
            if (is_array($data)) {
                $data['cancelled'] = true;
                $this->saveTaskFile($id, $data);
            }
        }
    }

    public function isCancelled(string $id): bool
    {
        $path = $this->getTaskFilePath($id);
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $data = $content ? json_decode($content, true) : null;
            return !empty($data['cancelled']);
        }
        return false;
    }

    /**
     * @return array<string, array{label: string, type: string, start: int, last_ping: int, progress: int, cancelled: bool}>
     */
    public function getActiveTasks(): array
    {
        $dir = $this->getTasksDir(); // Kept getTasksDir()
        $files = glob($dir . '/*.json'); // Changed glob pattern
        if (! is_array($files)) { // Changed condition
            return [];
        }

        $now = time();
        $tasks = []; // Renamed from $active
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) { // Added check for false
                continue;
            }

            $data = json_decode($content, true);
            if (! is_array($data) || empty($data['id'])) { // Changed condition
                continue;
            }

            $lastPing = (int) ($data['last_ping'] ?? 0); // Changed key back
            if ($now - $lastPing > self::EXPIRY_SECONDS) { // Changed constant name
                @unlink($file);
                continue;
            }

            $id = $data['id'];
            $tasks[$id] = [
                'id' => $id,
                'label' => $data['label'] ?? 'Unknown Task',
                'type' => $data['type'] ?? 'unknown',
                'start' => $data['start'] ?? time(), // Changed key back
                'last_ping' => $lastPing,
                'progress' => $data['progress'] ?? 0,
                'cancelled' => !empty($data['cancelled']),
            ];
        }

        return $tasks;
    }

    public function queueLaunch(string $actionType, string $id): void
    {
        $dir = $this->getTasksDir() . '/queue';
        if (! is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        $file = $dir . '/' . sanitize_file_name($id) . '.json';
        file_put_contents($file, wp_json_encode([
            'id' => $id,
            'action' => $actionType,
            'queued_at' => time()
        ]));
    }

    private function getTaskFilePath(string $id): string
    {
        return $this->getTasksDir() . '/task_' . sanitize_file_name($id) . '.json';
    }

    private function saveTaskFile(string $id, array $data): void
    {
        file_put_contents($this->getTaskFilePath($id), wp_json_encode($data), LOCK_EX);
    }
}
