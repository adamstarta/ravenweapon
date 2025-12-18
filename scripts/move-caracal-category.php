<?php
/**
 * Move Caracal Lynx category from under RAPAX to be a sibling under Waffen
 *
 * Current structure:
 *   Waffen > RAPAX > Caracal Lynx
 *
 * Desired structure:
 *   Waffen > RAPAX
 *   Waffen > Caracal Lynx
 *
 * This will change the URL from:
 *   /Waffen/RAPAX/Caracal-Lynx/
 * To:
 *   /Waffen/Caracal-Lynx/
 */

// Shopware API credentials
$baseUrl = 'https://ortak.ch/api';
$clientId = 'SWIAC3HJVHFJMHQYRWRUM1E1SG';
$clientSecret = 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg';

// Get access token
function getAccessToken($baseUrl, $clientId, $clientSecret) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/oauth/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to get access token: " . $response);
    }

    $data = json_decode($response, true);
    return $data['access_token'];
}

// Make API request
function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

// Search for category by name
function findCategoryByName($baseUrl, $token, $name) {
    $response = apiRequest($baseUrl, $token, 'POST', '/search/category', [
        'filter' => [
            [
                'type' => 'contains',
                'field' => 'name',
                'value' => $name
            ]
        ],
        'includes' => [
            'category' => ['id', 'name', 'parentId', 'path', 'level']
        ]
    ]);

    if ($response['code'] === 200 && !empty($response['body']['data'])) {
        return $response['body']['data'];
    }

    return [];
}

echo "=== Caracal Lynx Category Mover ===\n\n";

try {
    // Step 1: Get access token
    echo "1. Getting access token...\n";
    $token = getAccessToken($baseUrl, $clientId, $clientSecret);
    echo "   ✓ Got access token\n\n";

    // Step 2: Find Waffen category (this will be the new parent)
    echo "2. Finding 'Waffen' category...\n";
    $waffenCategories = findCategoryByName($baseUrl, $token, 'Waffen');

    $waffenCategory = null;
    foreach ($waffenCategories as $cat) {
        // Find the main Waffen category (level 2, direct child of root)
        if ($cat['name'] === 'Waffen' && $cat['level'] == 2) {
            $waffenCategory = $cat;
            break;
        }
    }

    if (!$waffenCategory) {
        throw new Exception("Could not find 'Waffen' category");
    }

    echo "   ✓ Found Waffen category\n";
    echo "     ID: " . $waffenCategory['id'] . "\n";
    echo "     Level: " . $waffenCategory['level'] . "\n\n";

    // Step 3: Find Caracal Lynx category
    echo "3. Finding 'Caracal Lynx' category...\n";
    $caracalCategories = findCategoryByName($baseUrl, $token, 'Caracal');

    $caracalCategory = null;
    foreach ($caracalCategories as $cat) {
        if (stripos($cat['name'], 'Caracal') !== false && stripos($cat['name'], 'Lynx') !== false) {
            $caracalCategory = $cat;
            break;
        }
        // Also check for just "Caracal Lynx"
        if ($cat['name'] === 'Caracal Lynx' || $cat['name'] === 'Caracal-Lynx') {
            $caracalCategory = $cat;
            break;
        }
    }

    if (!$caracalCategory) {
        // Try searching more broadly
        $allCategories = apiRequest($baseUrl, $token, 'POST', '/search/category', [
            'limit' => 500,
            'includes' => [
                'category' => ['id', 'name', 'parentId', 'path', 'level']
            ]
        ]);

        if ($allCategories['code'] === 200 && !empty($allCategories['body']['data'])) {
            foreach ($allCategories['body']['data'] as $cat) {
                if (stripos($cat['name'], 'Caracal') !== false) {
                    echo "   Found category with 'Caracal': " . $cat['name'] . " (ID: " . $cat['id'] . ")\n";
                    if (stripos($cat['name'], 'Lynx') !== false) {
                        $caracalCategory = $cat;
                    }
                }
            }
        }
    }

    if (!$caracalCategory) {
        throw new Exception("Could not find 'Caracal Lynx' category");
    }

    echo "   ✓ Found Caracal Lynx category\n";
    echo "     ID: " . $caracalCategory['id'] . "\n";
    echo "     Name: " . $caracalCategory['name'] . "\n";
    echo "     Current Parent ID: " . $caracalCategory['parentId'] . "\n";
    echo "     Current Level: " . $caracalCategory['level'] . "\n\n";

    // Check if already correct
    if ($caracalCategory['parentId'] === $waffenCategory['id']) {
        echo "   ℹ Caracal Lynx is already a direct child of Waffen. No changes needed.\n";
        exit(0);
    }

    // Step 4: Update Caracal Lynx's parent to Waffen
    echo "4. Moving Caracal Lynx to be direct child of Waffen...\n";
    echo "   Old parent: " . $caracalCategory['parentId'] . "\n";
    echo "   New parent: " . $waffenCategory['id'] . "\n";

    $updateResponse = apiRequest($baseUrl, $token, 'PATCH', '/category/' . $caracalCategory['id'], [
        'parentId' => $waffenCategory['id']
    ]);

    if ($updateResponse['code'] === 204 || $updateResponse['code'] === 200) {
        echo "   ✓ Successfully moved Caracal Lynx category!\n\n";
    } else {
        echo "   ✗ Failed to move category\n";
        echo "   HTTP Code: " . $updateResponse['code'] . "\n";
        echo "   Response: " . $updateResponse['raw'] . "\n";
        exit(1);
    }

    // Step 5: Trigger SEO URL regeneration (optional, Shopware usually does this automatically)
    echo "5. Category structure updated!\n\n";
    echo "=== IMPORTANT ===\n";
    echo "The category has been moved. The new structure is:\n";
    echo "  Waffen/\n";
    echo "  ├── RAPAX/\n";
    echo "  └── Caracal Lynx/\n\n";
    echo "New URL will be: /Waffen/Caracal-Lynx/\n";
    echo "New breadcrumb: Home / Waffen / Caracal Lynx\n\n";
    echo "You may need to:\n";
    echo "1. Clear cache: docker exec shopware-chf bin/console cache:clear\n";
    echo "2. Regenerate SEO URLs in admin: Settings > SEO > Regenerate URLs\n";
    echo "3. Update the navigation menu if needed\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
