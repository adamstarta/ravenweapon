<?php
/**
 * Check .22 LR RAVEN product variants and their media
 */

$config = [
    'shopware_url' => 'http://localhost',
    'api_user' => 'admin',
    'api_password' => 'shopware',
];

// Get token
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/oauth/token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'grant_type' => 'password',
        'client_id' => 'administration',
        'username' => $config['api_user'],
        'password' => $config['api_password'],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$token = $data['access_token'] ?? null;

if (!$token) die("Auth failed\n");

$productId = '019ac3a702a2730b94c384fc75eab389';

echo "Checking .22 LR RAVEN product and variants...\n";
echo str_repeat("=", 80) . "\n\n";

// Get product with all associations
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/product/' . $productId,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

$product = $result['data'] ?? $result;
$attrs = $product['attributes'] ?? $product;

echo "Main Product:\n";
echo "  ID: " . ($product['id'] ?? 'N/A') . "\n";
echo "  Name: " . ($attrs['name'] ?? $attrs['translated']['name'] ?? 'N/A') . "\n";
echo "  Cover ID: " . ($attrs['coverId'] ?? 'NONE') . "\n";
echo "  Parent ID: " . ($attrs['parentId'] ?? 'NONE (this is parent)') . "\n";
echo "\n";

// Get all children/variants
echo "Getting variants...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/product',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [['type' => 'equals', 'field' => 'parentId', 'value' => $productId]],
        'associations' => [
            'media' => [],
            'options' => ['associations' => ['group' => []]],
        ],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

$variants = $result['data'] ?? [];
echo "Found " . count($variants) . " variants\n\n";

foreach ($variants as $variant) {
    $vAttrs = $variant['attributes'] ?? $variant;
    $vId = $variant['id'] ?? 'N/A';
    $vName = $vAttrs['name'] ?? $vAttrs['translated']['name'] ?? 'N/A';
    $vCoverId = $vAttrs['coverId'] ?? 'NONE';

    echo "Variant: $vName\n";
    echo "  ID: $vId\n";
    echo "  Cover ID: $vCoverId\n";

    // Get options (color)
    $options = $vAttrs['options'] ?? [];
    if (!empty($options)) {
        foreach ($options as $opt) {
            $optAttrs = $opt['attributes'] ?? $opt;
            $optName = $optAttrs['name'] ?? $optAttrs['translated']['name'] ?? 'N/A';
            $groupName = $optAttrs['group']['name'] ?? $optAttrs['group']['translated']['name'] ?? 'N/A';
            echo "  Option: $groupName = $optName\n";
        }
    }

    // Get media
    $media = $vAttrs['media'] ?? [];
    echo "  Media count: " . count($media) . "\n";
    foreach ($media as $m) {
        $mAttrs = $m['attributes'] ?? $m;
        echo "    - Media ID: " . ($mAttrs['mediaId'] ?? $m['mediaId'] ?? 'N/A') . "\n";
    }
    echo "\n";
}

// Also get property options to understand the color structure
echo "\nGetting color property options...\n";
echo str_repeat("=", 80) . "\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['shopware_url'] . '/api/search/property-group',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'filter' => [['type' => 'contains', 'field' => 'name', 'value' => 'Farbe']],
        'associations' => ['options' => ['associations' => ['media' => []]]],
    ]),
]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

$groups = $result['data'] ?? [];
foreach ($groups as $group) {
    $gAttrs = $group['attributes'] ?? $group;
    $gName = $gAttrs['name'] ?? $gAttrs['translated']['name'] ?? 'N/A';
    echo "\nProperty Group: $gName (ID: {$group['id']})\n";

    $options = $gAttrs['options'] ?? [];
    foreach ($options as $opt) {
        $oAttrs = $opt['attributes'] ?? $opt;
        $oName = $oAttrs['name'] ?? $oAttrs['translated']['name'] ?? 'N/A';
        $oMediaId = $oAttrs['mediaId'] ?? 'NONE';
        echo "  - $oName (ID: {$opt['id']}) mediaId: $oMediaId\n";
    }
}
