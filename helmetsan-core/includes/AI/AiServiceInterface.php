<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Interface for the high-level AI service. Use this (or the class outline) when writing
 * code that calls the AI module; read the concrete AiService implementation only when modifying it.
 */
interface AiServiceInterface
{
    /**
     * Generate completion for a prompt. Uses enabled free providers with load balance by seed; optional premium override.
     */
    public function generate(string $prompt, int $seed = 0, ?string $useProviderId = null, array $options = []): ?string;

    /**
     * Phase 1: Generate SEO meta description. Truncates to 160 chars.
     */
    public function generateSeoDescription(string $entityType, array $context, int $postId): ?string;

    /**
     * Phase 2: Generate value for a missing field (context-aware).
     */
    public function generateFillField(
        string $entityType,
        string $fieldName,
        array $existingData,
        ?string $fieldLabel = null,
        ?int $maxLength = null,
        ?array $allowedValues = null
    ): ?string;

    /**
     * Phase 2 retry: generate with "previous reply invalid" feedback when allowed_values validation failed.
     */
    public function generateFillFieldWithFeedback(
        string $entityType,
        string $fieldName,
        array $existingData,
        string $fieldLabel,
        array $allowedValues,
        string $previousInvalidValue
    ): ?string;

    /**
     * Phase 3: Check entity integrity; returns assessment string or null.
     */
    public function checkIntegrity(string $entityType, array $entityData): ?string;

    /**
     * Resolve a helmet's product image source: EAN-13 and/or direct image URL.
     * @return array{ean: string, image_url: string}|null
     */
    public function resolveHelmetImageSource(int $helmetId): ?array;

    /**
     * Use AI to find the RevZilla product page URL for a helmet (title + brand).
     * @return string|null RevZilla product URL or null
     */
    public function resolveRevZillaUrlForHelmet(int $helmetId): ?string;

    /**
     * Use AI to find official manufacturer product images for a helmet.
     * @return array<string> List of image URLs
     */
    public function resolveManufacturerImageUrls(int $helmetId): array;

    /** Whether at least one provider is enabled and configured. */
    public function hasAnyConfiguredProvider(): bool;

    /** IDs of all enabled providers (any tier). For health/api-check display. @return list<string> */
    public function getConfiguredProviderIds(): array;

    /**
     * Test a single provider with a minimal prompt. For admin "Test connection".
     * @return array{ok: bool, message?: string}
     */
    public function testProvider(string $providerId): array;

    /**
     * Generate completions for multiple prompts in parallel, distributing them across providers (field-level parallelism).
     * @param array<string, string> $prompts Map of key -> prompt.
     * @param list<string>|null $providerIds Optional list of provider IDs to use.
     * @return array<string, string|null> Map of key -> result.
     */
    public function generateMultiplexed(array $prompts, ?array $providerIds = null, array $options = []): array;

    /**
     * Generate and return which provider responded. For --live API check.
     * @return array{text: string, provider_id: string}|null
     */
    public function generateWithProviderId(string $prompt, int $seed = 0, array $options = []): ?array;
}
