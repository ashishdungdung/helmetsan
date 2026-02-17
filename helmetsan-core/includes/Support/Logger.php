<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

final class Logger
{
    public function info(string $message): void
    {
        error_log('[Helmetsan] ' . $message);
    }
}
