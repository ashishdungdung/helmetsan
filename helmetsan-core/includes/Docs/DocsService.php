<?php

declare(strict_types=1);

namespace Helmetsan\Core\Docs;

final class DocsService
{
    public function docsRoot(): string
    {
        return HELMETSAN_CORE_DIR . 'docs';
    }

    /**
     * @return array<int, string>
     */
    public function listDocs(): array
    {
        if (! is_dir($this->docsRoot())) {
            return [];
        }

        $files = glob($this->docsRoot() . '/*.md') ?: [];

        sort($files);

        return array_values($files);
    }
}
