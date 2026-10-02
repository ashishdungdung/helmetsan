<?php

declare(strict_types=1);

namespace Helmetsan\Core\Media;

use Helmetsan\Core\AI\ProviderRegistry;
use Helmetsan\Core\AI\Providers\NvidiaNimProvider;

/**
 * Image Quality Audit Service
 *
 * Utilizes Moonshot AI Kimi-K3 and Meta Llama 3.2 90B Vision
 * to perform visual quality inspections, defect audits, and OCR verification
 * on generated helmet images before publication.
 */
final class ImageAuditService
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly HelmetImageManager $imageManager
    ) {}

    /**
     * Audit a helmet image using Kimi-K3 multimodal vision.
     *
     * @param string $imageId ID of image in wp_helmetsan_images
     * @param string $imageUrl Accessible image URL or absolute file path
     * @param string $shotType Expected shot type (front_hero, side_profile, etc.)
     * @param array<string, mixed> $helmetSpecs Helmet brand, model, certifications
     * @return array{
     *     decision: 'pass'|'fail'|'human_review',
     *     confidence: float,
     *     shot_type: string,
     *     checks: array<string, bool>,
     *     warnings: list<string>,
     *     rejection_reasons: list<string>,
     *     raw_output?: string
     * }
     */
    public function auditImage(string $imageId, string $imageUrl, string $shotType, array $helmetSpecs = []): array
    {
        $provider = $this->registry->get('nvidia_nim');
        if (! $provider instanceof NvidiaNimProvider || ! $provider->isConfigured()) {
            return [
                'decision' => 'human_review',
                'confidence' => 0.5,
                'shot_type' => $shotType,
                'checks' => ['provider_available' => false],
                'warnings' => ['NVIDIA NIM provider not configured. Manual review required.'],
                'rejection_reasons' => [],
            ];
        }

        $brand = $helmetSpecs['brand'] ?? 'Motorcycle Helmet';
        $model = $helmetSpecs['model'] ?? '';
        $shell = $helmetSpecs['shell_material'] ?? 'composite';

        $prompt = <<<PROMPT
You are Helmetsan's Senior Visual Quality Auditor and Motorcycle Safety Inspector.
Audit this generated product photograph for the helmet: "{$brand} {$model}" ({$shell} shell).
Expected Shot Type: {$shotType}

Verify the following rigorous criteria based on high-end motorcycle studio standards:
1. Specular Highlights & Glare: Are reflections controlled without blown-out clipped highlights on the visor or curved gloss shell?
2. Framing & Angle: Does the camera angle, scale, and horizon strictly match canonical shot type ({$shotType})?
3. Edge Segmentation: Are vents, spoilers, wings, and chin-straps crisp with zero halo artifacts or blurred cutout edges?
4. Color & Finish: Is the shell material (carbon fiber weave, matte, or gloss) rendered photorealistically without plastic artificiality?
5. Visor & Aperture: Is the eyeport, visor curve, and hinge/pivot baseplate mechanically plausible?
6. Retention System: If visible, is the chin strap (titanium D-ring or micrometric buckle) physically accurate?

Respond STRICTLY with a valid JSON object matching this schema:
{
  "decision": "pass" | "fail" | "human_review",
  "quality_score": 0 to 100,
  "confidence": 0.0 to 1.0,
  "shot_type": "{$shotType}",
  "checks": {
    "specular_blowout_controlled": true,
    "framing_and_angle_accurate": true,
    "edge_segmentation_clean": true,
    "color_consistency_valid": true,
    "visor_aperture_plausible": true,
    "retention_strap_valid": true
  },
  "defects": [],
  "warnings": [],
  "recommendations": "string"
}
PROMPT;

        // Use NVIDIA NIM Llama 3.2 Vision for rapid (~4s) inspection to prevent gateway timeouts
        $response = $provider->generate($prompt, [
            'model' => 'meta/llama-3.2-11b-vision-instruct',
            'image' => $imageUrl,
            'max_tokens' => 1024,
            'temperature' => 0.2,
            'timeout' => 25,
            'json_schema' => ['type' => 'json_object'],
        ]);

        // Fallback to Kimi-K3 if primary vision endpoint fails
        if (empty($response)) {
            $response = $provider->generate($prompt, [
                'model' => 'moonshotai/kimi-k3',
                'image' => $imageUrl,
                'max_tokens' => 1024,
                'temperature' => 0.2,
                'timeout' => 30,
                'json_schema' => ['type' => 'json_object'],
            ]);
        }

        if (empty($response)) {
            $err = $provider->getLastError();
            $result = [
                'decision' => 'human_review',
                'confidence' => 0.4,
                'shot_type' => $shotType,
                'checks' => ['api_success' => false],
                'warnings' => ['Audit API call failed: ' . ($err['message'] ?? 'Unknown error')],
                'rejection_reasons' => [],
            ];
            $this->imageManager->updateValidationStatus($imageId, 'human_review', $result);
            return $result;
        }

        $cleanJson = $this->extractJson($response);
        $data = json_decode($cleanJson, true);

        if (!is_array($data) || empty($data['decision'])) {
            $result = [
                'decision' => 'human_review',
                'confidence' => 0.5,
                'shot_type' => $shotType,
                'checks' => ['parse_success' => false],
                'warnings' => ['Malformed audit response: ' . substr($response, 0, 100)],
                'rejection_reasons' => [],
                'raw_output' => $response,
            ];
            $this->imageManager->updateValidationStatus($imageId, 'human_review', $result);
            return $result;
        }

        $status = match ($data['decision']) {
            'pass' => 'audit_passed',
            'fail' => 'audit_failed',
            default => 'human_review',
        };

        $this->imageManager->updateValidationStatus($imageId, $status, $data);
        return $data;
    }

    private function extractJson(string $text): string
    {
        $text = trim($text);
        if (preg_match('/```json\s*(\{.*\})\s*```/s', $text, $matches)) {
            return $matches[1];
        }
        if (preg_match('/(\{.*\})/s', $text, $matches)) {
            return $matches[1];
        }
        return $text;
    }
}
