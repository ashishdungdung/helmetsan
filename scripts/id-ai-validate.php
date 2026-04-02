<?php
/**
 * CLI Bridge for AI-powered Semantic Integrity Validation.
 * Allows IDE AI, Local AI, or Server AI to validate JSON data against Helmetsan's rules.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../helmetsan-core/includes/Validation/Validator.php';

use Helmetsan\Core\Validation\Validator;

$validator = new Validator();

// Get JSON from stdin
$input = file_get_contents('php://stdin');
if (empty($input)) {
    echo json_encode(['ok' => false, 'errors' => ['No input provided']]);
    exit(1);
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['ok' => false, 'errors' => ['Invalid JSON: ' . json_last_error_msg()]]);
    exit(1);
}

$entity = $data['entity'] ?? 'helmet';
$results = [];

if ($entity === 'helmet') {
    $schema = $validator->validateSchema($data);
    $logic  = $validator->validateLogic($data);
    
    $results = [
        'ok'       => $schema['ok'] && $logic['ok'],
        'errors'   => array_merge($schema['errors'], $logic['errors']),
        'warnings' => $logic['warnings'] ?? [],
    ];
} elseif ($entity === 'accessory') {
    $schema = $validator->validateAccessorySchema($data);
    $logic  = $validator->validateAccessoryLogic($data);
    
    $results = [
        'ok'       => $schema['ok'] && $logic['ok'],
        'errors'   => array_merge($schema['errors'], $logic['errors']),
        'warnings' => $logic['warnings'] ?? [],
    ];
} elseif ($entity === 'brand') {
    $schema = $validator->validateSchema($data);
    $logic  = $validator->validateBrandLogic($data);
    
    $results = [
        'ok'       => $schema['ok'] && $logic['ok'],
        'errors'   => array_merge($schema['errors'], $logic['errors']),
        'warnings' => $logic['warnings'] ?? [],
    ];
} elseif ($entity === 'offer') {
    $schema = $validator->validateSchema($data);
    $logic  = $validator->validateOfferLogic($data);
    
    $results = [
        'ok'       => $schema['ok'] && $logic['ok'],
        'errors'   => array_merge($schema['errors'], $logic['errors']),
        'warnings' => $logic['warnings'] ?? [],
    ];
} elseif ($entity === 'distributor') {
    $schema = $validator->validateSchema($data);
    $logic  = $validator->validateDistributorLogic($data);
    
    $results = [
        'ok'       => $schema['ok'] && $logic['ok'],
        'errors'   => array_merge($schema['errors'], $logic['errors']),
        'warnings' => $logic['warnings'] ?? [],
    ];
} else {
    $results = ['ok' => false, 'errors' => ['Unknown entity type: ' . $entity]];
}

echo json_encode($results, JSON_PRETTY_PRINT);
exit($results['ok'] ? 0 : 1);
