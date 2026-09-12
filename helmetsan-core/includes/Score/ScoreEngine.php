<?php
/**
 * Helmetsan Transparent Intelligence Score Calculation Engine
 * 
 * Aggregates 7 structured dimensions into a 0-100 Helmetsan Score:
 * - Safety (30%)
 * - Comfort (20%)
 * - Ventilation (15%)
 * - Noise Isolation (10%)
 * - Weight (10%)
 * - Features (10%)
 * - Value Ratio (5%)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Helmetsan_ScoreEngine {

    /**
     * Calculate comprehensive Helmetsan Score object for a given helmet data array.
     * 
     * @param array $helmetData
     * @return array
     */
    public static function calculate_score($helmetData) {
        $specs  = $helmetData['specs'] ?? [];
        $safety = $helmetData['safety_intelligence'] ?? [];
        $price  = $helmetData['price']['current'] ?? 350;
        $weight = $specs['weight_g'] ?? ($helmetData['spec_weight_g'] ?? 1450);

        // 1. Safety Score (0-100)
        $safetyScore = 75; // Baseline DOT
        $homo = strtoupper($safety['homologation_standard'] ?? '');
        if (strpos($homo, 'FIM') !== false) $safetyScore = 98;
        elseif (strpos($homo, 'ECE 22.06') !== false) $safetyScore = 94;
        elseif (strpos($homo, 'SNELL') !== false) $safetyScore = 90;
        elseif (strpos($homo, 'ECE 22.05') !== false) $safetyScore = 85;

        if (!empty($safety['rotational_mitigation']) && $safety['rotational_mitigation'] !== 'None') {
            $safetyScore = min(99, $safetyScore + 4); // MIPS/ODS bonus
        }
        if (!empty($safety['sharp_rating'])) {
            $stars = (int)$safety['sharp_rating'];
            if ($stars >= 5) $safetyScore = min(99, $safetyScore + 3);
        }

        // 2. Comfort Score (0-100)
        $comfortScore = 88;
        if ($weight < 1300) $comfortScore += 6;
        elseif ($weight > 1600) $comfortScore -= 6;
        if (!empty($helmetData['sizing_fit']['head_shape'])) $comfortScore += 2;

        // 3. Ventilation Score (0-100)
        $ventScore = 85;
        if (!empty($helmetData['aero_acoustic_profile']['ventilation_efficiency_score'])) {
            $ventScore = min(98, $helmetData['aero_acoustic_profile']['ventilation_efficiency_score'] * 10);
        }

        // 4. Noise Isolation Score (0-100)
        $noiseScore = 82;
        if (!empty($helmetData['aero_acoustic_profile']['noise_db_at_100kph'])) {
            $db = (int)$helmetData['aero_acoustic_profile']['noise_db_at_100kph'];
            if ($db <= 82) $noiseScore = 96;
            elseif ($db <= 85) $noiseScore = 90;
            elseif ($db <= 88) $noiseScore = 82;
            else $noiseScore = 74;
        }

        // 5. Weight Score (0-100)
        $weightScore = 80;
        if ($weight <= 1250) $weightScore = 98;
        elseif ($weight <= 1380) $weightScore = 92;
        elseif ($weight <= 1500) $weightScore = 84;
        else $weightScore = 72;

        // 6. Features Score (0-100)
        $featureScore = 78;
        if (!empty($helmetData['features_data']['visor'])) $featureScore += 8;
        if (!empty($helmetData['tech_integration']['comms_cutout_type'])) $featureScore += 8;
        if (!empty($helmetData['key_highlights'])) $featureScore += 4;
        $featureScore = min(98, $featureScore);

        // 7. Value Ratio Score (0-100)
        $valueScore = 80;
        if ($price > 0) {
            $ratio = ($safetyScore + $comfortScore) / max(1, log($price, 10) * 35);
            $valueScore = max(60, min(98, round($ratio * 75)));
        }

        // Overall Weighted Score
        $overallScore = round(
            ($safetyScore * 0.30) +
            ($comfortScore * 0.20) +
            ($ventScore * 0.15) +
            ($noiseScore * 0.10) +
            ($weightScore * 0.10) +
            ($featureScore * 0.10) +
            ($valueScore * 0.05)
        );

        return [
            'overall'     => (int)$overallScore,
            'dimensions'  => [
                'safety'      => (int)$safetyScore,
                'comfort'     => (int)$comfortScore,
                'ventilation' => (int)$ventScore,
                'noise'       => (int)$noiseScore,
                'weight'      => (int)$weightScore,
                'features'    => (int)$featureScore,
                'value'       => (int)$valueScore,
            ],
            'disclaimer'  => 'Helmetsan Score is an aggregated comparison score based on structured product data, certifications and available specifications. Not an independent crash-test rating.'
        ];
    }
}

function helmetsan_calculate_score($helmetData) {
    return Helmetsan_ScoreEngine::calculate_score($helmetData);
}
