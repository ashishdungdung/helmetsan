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
     * List immediate subdirectory names under the data root (e.g. helmets, brands, accessories).
     * Useful for CLI or admin to discover entity paths without hardcoding.
     *
     * @return list<string>
     */
    public function listSubdirs(): array
    {
        $base = $this->rootPath();
        if (! is_dir($base)) {
            return [];
        }
        $out = [];
        $iter = new \DirectoryIterator($base);
        foreach ($iter as $item) {
            if (! $item->isDir() || $item->isDot()) {
                continue;
            }
            $name = $item->getFilename();
            if ($name !== '' && $name[0] !== '.') {
                $out[] = $name;
            }
        }
        sort($out);
        return $out;
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
