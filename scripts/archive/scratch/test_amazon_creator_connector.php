<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../helmetsan-core/includes/Marketplace/MarketplaceConnectorInterface.php';
require_once __DIR__ . '/../../../helmetsan-core/includes/Marketplace/PriceResult.php';
require_once __DIR__ . '/../../../helmetsan-core/includes/Marketplace/Connectors/AmazonCreatorConnector.php';

use Helmetsan\Core\Marketplace\Connectors\AmazonCreatorConnector;

echo "=======================================================\n";
echo "🧪 Testing Amazon Creator API Connector (v3.1)\n";
echo "=======================================================\n\n";

$connector = new AmazonCreatorConnector();

echo "1. Connector ID:   " . $connector->id() . "\n";
echo "2. Connector Name: " . $connector->name() . "\n";
echo "3. Supported Countries (" . count($connector->supportedCountries()) . "): " . implode(', ', $connector->supportedCountries()) . "\n";

echo "\n4. Testing OAuth 2.0 Health Check against api.amazon.com...\n";
$health = $connector->healthCheck();
echo "   Result: " . ($health ? "✅ SUCCESS (OAuth 2.0 Bearer token acquired & verified)" : "❌ FAILED") . "\n";

if ($health) {
    echo "   Token snippet: " . substr($connector->getAccessToken(), 0, 25) . "...\n";
}

echo "\n5. Testing PriceResult Generation (US fallback search & OneLink):\n";
$resultUS = $connector->fetchPriceForCountry('shoei-rf-1400', 'US');
if ($resultUS !== null) {
    echo "   Marketplace:   " . $resultUS->marketplaceId . "\n";
    echo "   Country:       " . $resultUS->countryCode . "\n";
    echo "   Currency:      " . $resultUS->currency . "\n";
    echo "   Affiliate URL: " . $resultUS->affiliateUrl . "\n";
}

echo "\n6. Testing India Store Routing (virginiatete-21):\n";
$resultIN = $connector->fetchPriceForCountry('vega-bolt', 'IN');
if ($resultIN !== null) {
    echo "   Marketplace:   " . $resultIN->marketplaceId . "\n";
    echo "   Country:       " . $resultIN->countryCode . "\n";
    echo "   Currency:      " . $resultIN->currency . "\n";
    echo "   Affiliate URL: " . $resultIN->affiliateUrl . "\n";
}

echo "\n7. Testing Japan Store Routing (vtete-22):\n";
$resultJP = $connector->fetchPriceForCountry('shoei-x-fifteen', 'JP');
if ($resultJP !== null) {
    echo "   Marketplace:   " . $resultJP->marketplaceId . "\n";
    echo "   Country:       " . $resultJP->countryCode . "\n";
    echo "   Currency:      " . $resultJP->currency . "\n";
    echo "   Affiliate URL: " . $resultJP->affiliateUrl . "\n";
}

echo "\n8. Testing UAE Store Routing (vtete08-21):\n";
$resultAE = $connector->fetchPriceForCountry('hjc-rpha-1', 'AE');
if ($resultAE !== null) {
    echo "   Marketplace:   " . $resultAE->marketplaceId . "\n";
    echo "   Country:       " . $resultAE->countryCode . "\n";
    echo "   Currency:      " . $resultAE->currency . "\n";
    echo "   Affiliate URL: " . $resultAE->affiliateUrl . "\n";
}

echo "\n=======================================================\n";
echo "🎉 All Amazon Creator API Connector tests completed!\n";
echo "=======================================================\n";
