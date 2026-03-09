<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Handles AI-powered analysis of images.
 * Determines the type of photo and generates semantic filenames.
 */
class ImageAnalysisService
{
    public function __construct(
        private readonly ProviderRegistry $registry
    ) {}

    /**
     * Analyzes an image URL to determine the photo type and model name.
     *
     * @param string $imageUrl The URL of the image to analyze.
     * @param string $knownContext Optional context (e.g., known brand/model) to help the AI.
     * @return array{photo_type: string, model_name: string, suggested_filename: string}|null Returns array on success, null on failure.
     */
    public function analyzeImage(string $imageUrl, string $knownContext = ''): ?array
    {
        // For simplicity in this implementation, we attempt to use OpenAI or Gemini specifically
        // because they support Vision APIs and we can craft a bespoke request for them here.

        $openAi = $this->registry->get('openai');
        if ($openAi && $openAi->isConfigured()) {
            return $this->callOpenAiVision($openAi, $imageUrl, $knownContext);
        }

        $gemini = $this->registry->get('gemini');
        if ($gemini && $gemini->isConfigured()) {
            // Note: Helmetsan's GeminiProvider text implementation doesn't expose the raw request or API key via a public getter,
            // but we can extract it from the config if we need to, or rely on a fallback generic logic if we can't access it.
            // Since this is an MVP for the ImageAnalysisService, let's write the OpenAI integration first.
        }

        // If no vision provider is configured, return a default deterministic analysis
        return $this->getFallbackAnalysis($imageUrl, $knownContext);
    }

    private function callOpenAiVision(ProviderInterface $provider, string $imageUrl, string $knownContext): ?array
    {
        // We know it's OpenAIProvider, but we might not have public access to the apiKey property.
        // Let's re-fetch it from the plugin settings to be safe.
        $settings = get_option(\Helmetsan\Core\Support\Config::OPTION_AI, []);
        $apiKey = $settings['providers']['openai']['api_key'] ?? '';

        if (empty($apiKey)) {
            return null;
        }

        $prompt = "Analyze this image.";
        if ($knownContext !== '') {
            $prompt .= " Context: this should be a $knownContext.";
        }
        $prompt .= " Determine if this image is actually a relevant picture of the product (helmet or riding accessory). If it is a size chart, promotional banner, random logo, or completely unrelated item, it is NOT relevant. Set the 'is_relevant' boolean accordingly. If relevant, determine the type of photo (e.g., 'front-view', 'side-view', 'angled', 'interior', 'visor-close-up', 'lifestyle'). Also output a semantic filename slug (lowercase, hyphenated, no extension). Return ONLY valid JSON in the exact format: { \"is_relevant\": true, \"photo_type\": \"front-view\", \"model_name\": \"identified-model\", \"suggested_filename\": \"model-front-view\" }";

        $body = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageUrl,
                            ],
                        ],
                    ],
                ]
            ],
            'max_tokens' => 300
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Strip markdown backticks if present
        $content = trim(str_replace(['```json', '```'], '', $content));

        $json = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json['photo_type'], $json['suggested_filename'])) {
            return [
                'is_relevant'        => (bool) ($json['is_relevant'] ?? true),
                'photo_type'         => $json['photo_type'],
                'model_name'         => $json['model_name'] ?? 'unknown',
                'suggested_filename' => $json['suggested_filename'],
            ];
        }

        return null;
    }

    private function getFallbackAnalysis(string $imageUrl, string $knownContext): array
    {
        $hash = md5($imageUrl);
        $slug = sanitize_title($knownContext !== '' ? $knownContext : 'helmet');
        return [
            'is_relevant'        => true,
            'photo_type'         => 'standard-view',
            'model_name'         => $knownContext ?: 'Unknown Model',
            'suggested_filename' => $slug . '-' . substr($hash, 0, 6) . '-standard',
        ];
    }
}
