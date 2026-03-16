<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

/**
 * Monitors server resources (RAM, CPU) to recommend optimal concurrency.
 */
final class ResourceMonitor
{
    /**
     * Get available system memory in MB.
     */
    public function getAvailableMemoryMB(): int
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return 1024; // Generic fallback for Windows
        }

        if (function_exists('shell_exec')) {
            $free = shell_exec('free -m');
            if ($free) {
                $lines = explode("\n", trim($free));
                $mem = preg_split('/\s+/', $lines[1]);
                return (int) ($mem[6] ?? $mem[3] ?? 512); // Use 'available' if present, else 'free'
            }
        }

        // Mac fallback
        if (strtoupper(PHP_OS) === 'DARWIN' && function_exists('shell_exec')) {
            $vmstat = shell_exec('vm_stat');
            if ($vmstat) {
                preg_match('/Pages free:\s+(\d+)/', $vmstat, $m);
                $freePages = (int) ($m[1] ?? 0);
                return (int) (($freePages * 4096) / 1024 / 1024);
            }
        }

        return 512;
    }

    /**
     * Get current CPU load (1 min average).
     */
    public function getCpuLoad(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return (float) ($load[0] ?? 0.0);
        }
        return 0.0;
    }

    /**
     * Get number of CPU cores.
     */
    public function getCpuCores(): int
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return 2;
        }

        if (function_exists('shell_exec')) {
            $numCores = shell_exec('nproc');
            if ($numCores) {
                return (int) trim($numCores);
            }
        }

        if (strtoupper(PHP_OS) === 'DARWIN' && function_exists('shell_exec')) {
            $numCores = shell_exec('sysctl -n hw.ncpu');
            if ($numCores) {
                return (int) trim($numCores);
            }
        }

        return 2;
    }

    /**
     * Recommend concurrency level based on available resources.
     * Assuming each child process takes ~150MB RAM.
     */
    public function getRecommendedConcurrency(int $max = 16): int
    {
        $mem = $this->getAvailableMemoryMB();
        $cores = $this->getCpuCores();
        $load = $this->getCpuLoad();

        // Reserve 256MB for system, then 150MB per process
        $memConcurrency = (int) floor(($mem - 256) / 150);
        
        // CPU consideration: don't exceed cores * 2 if load is low, or cores * 1 if load is high
        $cpuFactor = $load > ($cores * 0.8) ? 1 : 2;
        $cpuConcurrency = $cores * $cpuFactor;

        $recommended = min($memConcurrency, $cpuConcurrency, $max);
        
        return max(1, $recommended);
    }
}
