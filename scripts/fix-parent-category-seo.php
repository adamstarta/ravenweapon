<?php
/**
 * Fix SEO URLs for parent categories (Zubehör, Waffenzubehör, Ausrüstung)
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'admin',
    'api_password' => 'shopware',
];

$GLOBALS['token_data'] = ['token' => null, 'expires_at' => 0];

function getAccessToken($config, $forceRefresh = false) {
    if (!$forceRefresh && $GLOBALS['token_data']['token'] && $GLOBALS['token_data']['expires_at'] > time() + 60) {
        return $GLOBALS['token_data']['token'];
    }

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
    if (isset($data['access_token'])) {
        $GLOBALS['token_data']['token'] = $data['access_token'];
        $GLOBALS['token_data']['expires_at'] = time() + ($data['expires_in'] ?? 600);
        return $data['access_token'];
    }
    return null;
}

function apiRequest($method, $endpoint, $data = null, $token = null, $config) {
    $url = $config['shopware_url'] . '/api' . $endpoint;

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

echo "=== FIX PARENT CATEGORY SEO URLS ===\n\n";

$token = getAccessToken($config);
if (!$token) {
    die("Failed to get token\n");
}
echo "Got API token\n\n";

// Get sales channel ID
$result = apiRequest('GET', '/sales-channel?limit=1', null, $token, $config);
$salesChannelId = $result['body']['data'][0]['id'] ?? null;
echo "Sales Channel ID: $salesChannelId\n\n";

// Get language ID for German
$result = apiRequest('GET', '/language?filter[name]=Deutsch', null, $token, $config);
$languageId = $result['body']['data'][0]['id'] ?? null;
echo "Language ID: $languageId\n\n";

// Categories to fix with their desired SEO paths
$categoriesToFix = [
    'Zubehoer' => [
        'searchName' => 'Zubehör',
        'seoPath' => 'Zubehoer'
    ],
    'Waffenzubehoer' => [
        'searchName' => 'Zielhilfen',
        'seoPath' => 'Waffenzubehoer'
    ],
    'Ausruestung' => [
        'searchName' => 'Ausrüstung',
        'seoPath' => 'Ausruestung'
    ]
];

// Get all categories
echo "Fetching categories...\n";
$result = apiRequest('GET', '/category?limit=500&associations[translations]=[]', null, $token, $config);

if (!isset($result['body']['data'])) {
    die("Failed to get categories\n");
}

$categories = $result['body']['data'];
echo "Found " . count($categories) . " categories\n\n";

// Find categories by name and fix SEO URLs
foreach ($categories as $cat) {
    $catName = $cat['name'] ?? ($cat['translated']['name'] ?? '');
    $catId = $cat['id'];

    foreach ($categoriesToFix as $key => $fixData) {
        if (stripos($catName, $fixData['searchName']) !== false) {
            echo "Found: $catName (ID: $catId)\n";

            // Check existing SEO URL
            $seoResult = apiRequest('GET', "/seo-url?filter[foreignKey]=$catId&filter[routeName]=frontend.navigation.page&filter[salesChannelId]=$salesChannelId&filter[isCanonical]=1", null, $token, $config);

            $existingSeoUrl = null;
            if (isset($seoResult['body']['data']) && count($seoResult['body']['data']) > 0) {
                $existingSeoUrl = $seoResult['body']['data'][0];
                echo "  Existing SEO: " . $existingSeoUrl['seoPathInfo'] . "\n";
            } else {
                echo "  No existing SEO URL\n";
            }

            // Create or update SEO URL
            $desiredPath = $fixData['seoPath'] . '/';

            if ($existingSeoUrl && $existingSeoUrl['seoPathInfo'] === $desiredPath) {
                echo "  SEO URL already correct\n";
            } else {
                // Create new SEO URL
                $seoData = [
                    'foreignKey' => $catId,
                    'routeName' => 'frontend.navigation.page',
                    'pathInfo' => '/navigation/' . $catId,
                    'seoPathInfo' => $desiredPath,
                    'isCanonical' => true,
                    'salesChannelId' => $salesChannelId,
                    'languageId' => $languageId
                ];

                // If existing, update it
                if ($existingSeoUrl) {
                    $updateResult = apiRequest('PATCH', '/seo-url/' . $existingSeoUrl['id'], [
                        'seoPathInfo' => $desiredPath,
                        'isCanonical' => true
                    ], $token, $config);

                    if ($updateResult['code'] >= 200 && $updateResult['code'] < 300) {
                        echo "  Updated SEO URL to: $desiredPath\n";
                    } else {
                        echo "  Failed to update: " . json_encode($updateResult['body']) . "\n";
                    }
                } else {
                    $createResult = apiRequest('POST', '/seo-url', $seoData, $token, $config);

                    if ($createResult['code'] >= 200 && $createResult['code'] < 300) {
                        echo "  Created SEO URL: $desiredPath\n";
                    } else {
                        echo "  Failed to create: " . json_encode($createResult['body']) . "\n";
                    }
                }
            }
            echo "\n";
        }
    }
}

echo "\n=== DONE ===\n";
echo "Clear cache with: ssh root@77.42.19.154 \"docker exec shopware-chf bin/console cache:clear\"\n";
