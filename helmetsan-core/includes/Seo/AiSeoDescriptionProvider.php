<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

use Helmetsan\Core\AI\AiService;

/**
 * Generates SEO meta descriptions via the AI module (plugin settings) or legacy env keys.
 * When AiService is provided and has configured providers, uses the module; otherwise Groq/Gemini from env.
 */
final class AiSeoDescriptionProvider
{
    private const META_DESC_MAX = 160;
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const GEMINI_URL_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const RATE_LIMIT_DELAY_SECONDS = 1;

    private ?string $groqKey = null;
    private ?string $geminiKey = null;

    public function __construct(
        private readonly ?AiService $aiService = null
    ) {
        $this->groqKey = $this->getEnvOrConstant('GROQ_API_KEY', 'HELMETSAN_GROQ_API_KEY');
        $this->geminiKey = $this->getEnvOrConstant('GEMINI_API_KEY', 'HELMETSAN_GEMINI_API_KEY');
    }

    public function hasAnyKey(): bool
    {
        if ($this->aiService !== null && $this->aiService->hasAnyConfiguredProvider()) {
            return true;
        }
        return ($this->groqKey !== null && $this->groqKey !== '')
            || ($this->geminiKey !== null && $this->geminiKey !== '');
    }

    /**
     * Generate meta description for a helmet page.
     */
    public function generateForHelmet(int $postId, array $context): ?string
    {
        if ($this->aiService !== null && $this->aiService->hasAnyConfiguredProvider()) {
            $text = $this->aiService->generateSeoDescription('helmet', $context, $postId);
            return $text !== null ? $this->normalizeMetaDesc($text) : null;
        }
        $text = $this->callLoadBalanced($postId, $this->buildHelmetPrompt($context));
        if ($text === null) {
            return null;
        }
        $this->rateLimit();
        return $this->normalizeMetaDesc($text);
    }

    /**
     * Generate meta description for a brand hub page.
     */
    public function generateForBrand(int $postId, array $context): ?string
    {
        if ($this->aiService !== null && $this->aiService->hasAnyConfiguredProvider()) {
            $text = $this->aiService->generateSeoDescription('brand', $context, $postId);
            return $text !== null ? $this->normalizeMetaDesc($text) : null;
        }
        $text = $this->callLoadBalanced($postId, $this->buildBrandPrompt($context));
        if ($text === null) {
            return null;
        }
        $this->rateLimit();
        return $this->normalizeMetaDesc($text);
    }

    /**
     * Generate meta description for an accessory page.
     */
    public function generateForAccessory(int $postId, array $context): ?string
    {
        if ($this->aiService !== null && $this->aiService->hasAnyConfiguredProvider()) {
            $text = $this->aiService->generateSeoDescription('accessory', $context, $postId);
            return $text !== null ? $this->normalizeMetaDesc($text) : null;
        }
        $text = $this->callLoadBalanced($postId, $this->buildAccessoryPrompt($context));
        if ($text === null) {
            return null;
        }
        $this->rateLimit();
        return $this->normalizeMetaDesc($text);
    }

    private function buildHelmetPrompt(array $context): string
    {
        $product = ($context['brand'] !== '' ? $context['brand'] . ' ' : '') . $context['title'];
        $type = $context['type'] ?? 'motorcycle helmet';
        $certs = $context['certifications'] ?? [];
        $price = $context['price'] ?? null;
        $prompt = "Write exactly one meta description for a motorcycle helmet product page. "
            . "Product: {$product}. Type: {$type}. ";
        if ($certs !== []) {
            $prompt .= "Certifications: " . implode(', ', array_slice($certs, 0, 3)) . ". ";
        }
        if ($price !== null && $price !== '') {
            $prompt .= "Price: {$price}. ";
        }
        $prompt .= "Requirements: 150-160 characters total. Include product name and type. "
            . "Professional, benefit-led. End with a CTA like 'Compare at Helmetsan' or 'Find the best deal at Helmetsan'. "
            . "Output ONLY the meta description, no quotes or explanation.";
        return $prompt;
    }

    private function buildBrandPrompt(array $context): string
    {
        $brand = $context['brand'] ?? 'Brand';
        $country = $context['country'] ?? '';
        return "Write exactly one meta description for a motorcycle helmet brand hub page. "
            . "Brand: {$brand}. " . ($country !== '' ? "Origin: {$country}. " : '')
            . "Requirements: 150-160 characters. Mention full face, modular, adventure helmets; compare prices and certifications; "
            . "end with CTA like 'Official reviews at Helmetsan'. Output ONLY the meta description, no quotes or explanation.";
    }

    private function buildAccessoryPrompt(array $context): string
    {
        $title = $context['title'] ?? 'Product';
        $category = $context['category'] ?? 'Motorcycle Accessory';
        return "Write exactly one meta description for a motorcycle helmet accessory product page. "
            . "Product: {$title}. Category: {$category}. "
            . "Requirements: 150-160 characters. Mention compatibility and buying guide. "
            . "End with CTA like 'at Helmetsan'. Output ONLY the meta description, no quotes or explanation.";
    }

    private function getEnvOrConstant(string $envKey, string $constKey): ?string
    {
        $v = getenv($envKey);
        if ($v !== false && $v !== '') {
            return (string) $v;
        }
        if (defined($constKey)) {
            $c = constant($constKey);
            return is_string($c) ? $c : null;
        }
        return null;
    }

    /**
     * Load balance by post_id: even → Groq first, odd → Gemini first. On failure or 429, try the other.
     */
    private function callLoadBalanced(int $postId, string $prompt): ?string
    {
        $useGroqFirst = ($postId % 2) === 0;
        $first = $useGroqFirst ? $this->callGroq($prompt) : $this->callGemini($prompt);
        if ($first !== null) {
            return $first;
        }
        $second = $useGroqFirst ? $this->callGemini($prompt) : $this->callGroq($prompt);
        return $second;
    }

    private function callGroq(string $prompt): ?string
    {
        if ($this->groqKey === null || $this->groqKey === '') {
            return null;
        }
        $body = [
            'model' => 'llama-3.1-8b-instant',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 120,
            'temperature' => 0.4,
        ];
        $response = wp_remote_post(self::GROQ_URL, [
            'timeout' => 25,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->groqKey,
            ],
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($response)) {
            return null;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($data) || empty($data['choices'][0]['message']['content'])) {
            return null;
        }
        return trim((string) $data['choices'][0]['message']['content']);
    }

    private function callGemini(string $prompt): ?string
    {
        if ($this->geminiKey === null || $this->geminiKey === '') {
            return null;
        }
        $url = sprintf(self::GEMINI_URL_TEMPLATE, 'gemini-1.5-flash') . '?key=' . rawurlencode($this->geminiKey);
        $body = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['maxOutputTokens' => 120, 'temperature' => 0.4],
        ];
        $response = wp_remote_post($url, [
            'timeout' => 25,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($response)) {
            return null;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($data) || empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            return null;
        }
        return trim((string) $data['candidates'][0]['content']['parts'][0]['text']);
    }

    private function normalizeMetaDesc(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/^["\']|["\']$/u', '', $s);
        $s = trim($s);
        if (strlen($s) > self::META_DESC_MAX) {
            $s = substr($s, 0, self::META_DESC_MAX - 3);
            $last = strrpos($s, ' ');
            if ($last !== false && $last > (int) (self::META_DESC_MAX * 0.5)) {
                $s = substr($s, 0, $last);
            }
        }
        return $s;
    }

    private function rateLimit(): void
    {
        $skip = \defined('HELMETSAN_SEO_AI_SKIP_RATE_LIMIT') ? constant('HELMETSAN_SEO_AI_SKIP_RATE_LIMIT') : false;
        if ($skip) {
            return;
        }
        sleep(self::RATE_LIMIT_DELAY_SECONDS);
    }
}
