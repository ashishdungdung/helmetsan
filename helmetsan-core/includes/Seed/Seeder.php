<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seed;

use Helmetsan\Core\Support\Logger;

final class Seeder
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function seed(string $set, bool $force = false): array
    {
        $this->logger->info('Seeding set: ' . $set . ' force=' . ($force ? '1' : '0'));

        return [
            'ok'      => true,
            'set'     => $set,
            'force'   => $force,
            'message' => 'Seeder scaffold complete. Implement dataset inserts in module.',
        ];
    }
}
