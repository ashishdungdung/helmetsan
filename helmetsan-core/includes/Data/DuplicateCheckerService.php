<?php

declare(strict_types=1);

namespace Helmetsan\Core\Data;

use Helmetsan\Core\Repository\JsonRepository;

/**
 * Scans JSON under the data root for duplicate unique keys (id, EAN, etc.)
 * to maintain repository sanity. Used by `wp helmetsan data check-duplicates`.
 */
final class DuplicateCheckerService
{
    public function __construct(
        private readonly JsonRepository $repository
    ) {
    }

    /**
     * Run duplicate check for the given types.
     *
     * @param list<string> $types One or more of: helmet, accessory, brand
     * @param bool $includeEan For helmets, also report duplicate EANs
     * @return array{duplicates: list<array{type: string, key: string, key_type: string, count: int, locations: list<string>}>, total_checked: array<string, int>}
     */
    public function check(array $types = ['helmet', 'accessory', 'brand'], bool $includeEan = true): array
    {
        $duplicates = [];
        $totalChecked = [];

        if (in_array('helmet', $types, true)) {
            $byId = [];
            $byEan = [];
            $this->collectHelmets($byId, $byEan);
            $totalChecked['helmet_id'] = count($byId);
            foreach ($byId as $id => $locs) {
                if (count($locs) > 1) {
                    $duplicates[] = [
                        'type'     => 'helmet',
                        'key'      => $id,
                        'key_type' => 'id',
                        'count'    => count($locs),
                        'locations' => $locs,
                    ];
                }
            }
            if ($includeEan) {
                $totalChecked['helmet_ean'] = count($byEan);
                foreach ($byEan as $ean => $locs) {
                    if ($ean === '' || count($locs) <= 1) {
                        continue;
                    }
                    $duplicates[] = [
                        'type'      => 'helmet',
                        'key'       => $ean,
                        'key_type'  => 'ean',
                        'count'     => count($locs),
                        'locations' => $locs,
                    ];
                }
            }
        }

        if (in_array('accessory', $types, true)) {
            $byId = [];
            $this->collectAccessories($byId);
            $totalChecked['accessory_id'] = count($byId);
            foreach ($byId as $id => $locs) {
                if (count($locs) > 1) {
                    $duplicates[] = [
                        'type'      => 'accessory',
                        'key'       => $id,
                        'key_type'  => 'id',
                        'count'     => count($locs),
                        'locations' => $locs,
                    ];
                }
            }
        }

        if (in_array('brand', $types, true)) {
            $byId = [];
            $this->collectBrands($byId);
            $totalChecked['brand_id'] = count($byId);
            foreach ($byId as $id => $locs) {
                if (count($locs) > 1) {
                    $duplicates[] = [
                        'type'      => 'brand',
                        'key'       => $id,
                        'key_type'  => 'id',
                        'count'     => count($locs),
                        'locations' => $locs,
                    ];
                }
            }
        }

        return ['duplicates' => $duplicates, 'total_checked' => $totalChecked];
    }

    /**
     * @param array<string, list<string>> $byId id => [ location, ... ]
     * @param array<string, list<string>> $byEan ean => [ location, ... ]
     */
    private function collectHelmets(array &$byId, array &$byEan): void
    {
        $base = $this->repository->rootPath();
        $relPaths = ['helmets', 'seed-data'];
        foreach ($relPaths as $rel) {
            $dir = rtrim($base . '/' . ltrim($rel, '/'), '/');
            if (! is_dir($dir)) {
                continue;
            }
            $files = $this->repository->listJsonFiles($rel);
            foreach ($files as $absPath) {
                $data = $this->repository->read($absPath);
                $relPath = ltrim(str_replace($base, '', $absPath), '/');
                if (isset($data[0]) && is_array($data[0])) {
                    foreach ($data as $i => $item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        $id = isset($item['id']) ? trim((string) $item['id']) : null;
                        if ($id !== null && $id !== '') {
                            $loc = $relPath . '#' . $i;
                            $byId[$id] = $byId[$id] ?? [];
                            $byId[$id][] = $loc;
                        }
                        $ean = $this->extractEan($item);
                        if ($ean !== '') {
                            $loc = $relPath . '#' . $i;
                            $byEan[$ean] = $byEan[$ean] ?? [];
                            $byEan[$ean][] = $loc;
                        }
                    }
                } elseif (isset($data['entity']) && ($data['entity'] === 'helmet' || ! isset($data['entity']))) {
                    $id = isset($data['id']) ? trim((string) $data['id']) : null;
                    if ($id !== null && $id !== '') {
                        $byId[$id] = $byId[$id] ?? [];
                        $byId[$id][] = $relPath;
                    }
                    $ean = $this->extractEan($data);
                    if ($ean !== '') {
                        $byEan[$ean] = $byEan[$ean] ?? [];
                        $byEan[$ean][] = $relPath;
                    }
                } elseif ($this->isMasterFormat($data)) {
                    foreach ($data as $brand => $models) {
                        if ($brand === '_comment' || ! is_array($models)) {
                            continue;
                        }
                        foreach (array_keys($models) as $model) {
                            $key = $brand . '/' . $model;
                            $byId[$key] = $byId[$key] ?? [];
                            $byId[$key][] = $relPath . ' (master)';
                        }
                    }
                }
            }
        }
    }

    /**
     * @param array<string, list<string>> $byId
     */
    private function collectAccessories(array &$byId): void
    {
        $base = $this->repository->rootPath();
        $files = $this->repository->listJsonFiles('accessories');
        foreach ($files as $absPath) {
            $data = $this->repository->read($absPath);
            $relPath = ltrim(str_replace($base, '', $absPath), '/');
            if (isset($data[0]) && is_array($data[0])) {
                foreach ($data as $i => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $id = isset($item['id']) ? trim((string) $item['id']) : null;
                    if ($id !== null && $id !== '') {
                        $byId[$id] = $byId[$id] ?? [];
                        $byId[$id][] = $relPath . '#' . $i;
                    }
                }
            } else {
                $id = isset($data['id']) ? trim((string) $data['id']) : null;
                if ($id !== null && $id !== '') {
                    $byId[$id] = $byId[$id] ?? [];
                    $byId[$id][] = $relPath;
                }
            }
        }
    }

    /**
     * @param array<string, list<string>> $byId
     */
    private function collectBrands(array &$byId): void
    {
        $base = $this->repository->rootPath();
        $files = $this->repository->listJsonFiles('brands');
        if ($files === []) {
            $single = $base . '/brands.json';
            if (is_file($single)) {
                $files = [$single];
            }
        }
        foreach ($files as $absPath) {
            $data = $this->repository->read($absPath);
            $relPath = ltrim(str_replace($base, '', $absPath), '/');
            if (isset($data[0]) && is_array($data[0])) {
                foreach ($data as $i => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $id = $this->brandIdFromPayload($item);
                    if ($id !== '') {
                        $byId[$id] = $byId[$id] ?? [];
                        $byId[$id][] = $relPath . '#' . $i;
                    }
                }
            } else {
                $id = $this->brandIdFromPayload($data);
                if ($id !== '') {
                    $byId[$id] = $byId[$id] ?? [];
                    $byId[$id][] = $relPath;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extractEan(array $item): string
    {
        $identifiers = $item['identifiers'] ?? null;
        if (! is_array($identifiers)) {
            return '';
        }
        $ean = $identifiers['ean'] ?? $identifiers['gtin'] ?? $identifiers['upc'] ?? '';
        return trim((string) $ean);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isMasterFormat(array $data): bool
    {
        if (isset($data['entity']) || isset($data[0])) {
            return false;
        }
        foreach (array_keys($data) as $k) {
            if ($k === '_comment') {
                continue;
            }
            if (is_array($data[$k] ?? null)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function brandIdFromPayload(array $item): string
    {
        if (isset($item['id'])) {
            return trim((string) $item['id']);
        }
        if (isset($item['profile']['slug'])) {
            return trim((string) $item['profile']['slug']);
        }
        if (isset($item['title'])) {
            return sanitize_title((string) $item['title']);
        }
        return '';
    }
}
