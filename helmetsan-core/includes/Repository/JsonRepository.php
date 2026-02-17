<?php

declare(strict_types=1);

namespace Helmetsan\Core\Repository;

use Helmetsan\Core\Support\Config;

final class JsonRepository
{
    public function __construct(private readonly Config $config)
    {
    }

    public function rootPath(): string
    {
        return $this->config->dataRoot();
    }

    public function exists(): bool
    {
        return is_dir($this->rootPath());
    }

    /**
     * @return array<int, string>
     */
    public function listJsonFiles(string $relativePath = ''): array
    {
        $base = rtrim($this->rootPath() . '/' . ltrim($relativePath, '/'), '/');

        if (! is_dir($base)) {
            return [];
        }

        $files = [];
        $iter  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

        foreach ($iter as $item) {
            if (! $item->isFile()) {
                continue;
            }
            if (strtolower((string) $item->getExtension()) !== 'json') {
                continue;
            }

            $files[] = $item->getPathname();
        }

        sort($files);

        return $files;
    }

    public function read(string $absolutePath): array
    {
        $raw = file_get_contents($absolutePath);

        if ($raw === false) {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }
}
