<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Contract for an AI completion provider (Groq, Gemini, Mistral, OpenAI, etc.).
 */
interface ProviderInterface
{
    public function getId(): string;

    public function getLabel(): string;

    /** 'free' | 'premium' */
    public function getTier(): string;

    /** Whether the provider is configured (has API key). */
    public function isConfigured(): bool;

    /**
     * Generate completion for the given prompt.
     * @param array{max_tokens?: int, temperature?: float} $options
     */
    public function generate(string $prompt, array $options = []): ?string;
}
