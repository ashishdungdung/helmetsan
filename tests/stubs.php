<?php

declare(strict_types=1);

// Stubs so we can load FillMissingService without WordPress or full AI provider chain.
namespace {
    if (! class_exists('WP_Post', false)) {
        class WP_Post
        {
        }
    }
}

namespace Helmetsan\Core\AI {
    if (! class_exists('Helmetsan\Core\AI\AiService', false)) {
        class AiService
        {
        }
    }
}
