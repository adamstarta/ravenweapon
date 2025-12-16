<?php
/**
 * List all email templates with details
 */

$API_URL = 'http://localhost';

function getToken($baseUrl) {
    $ch = curl_init($baseUrl . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware'
        ])
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true)['access_token'] ?? null;
}

function apiRequest($baseUrl, $token, $method, $endpoint, $data = null) {
    $ch = curl_init($baseUrl . '/api/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    return json_decode($response, true);
}

$token = getToken($API_URL);
if (!$token) {
    die("Failed to get token\n");
}

// First get mail template types
echo "=== MAIL TEMPLATE TYPES ===\n\n";
$types = apiRequest($API_URL, $token, 'POST', 'search/mail-template-type', [
    'limit' => 100
]);

$typeMap = [];
foreach ($types['data'] ?? [] as $type) {
    $typeMap[$type['id']] = $type['technicalName'];
    echo "{$type['technicalName']} - {$type['name']}\n";
}

echo "\n=== MAIL TEMPLATES WITH TRANSLATIONS ===\n\n";

// Get templates with translations
$result = apiRequest($API_URL, $token, 'POST', 'search/mail-template', [
    'limit' => 100,
    'associations' => [
        'translations' => [],
        'mailTemplateType' => []
    ]
]);

foreach ($result['data'] ?? [] as $template) {
    $typeId = $template['mailTemplateTypeId'] ?? '';
    $typeName = $typeMap[$typeId] ?? 'unknown';

    echo "=== $typeName ===\n";
    echo "ID: {$template['id']}\n";

    // Get translations
    foreach ($template['translations'] ?? [] as $trans) {
        $subject = $trans['subject'] ?? '(no subject)';
        echo "Subject: $subject\n";
        echo "Content preview: " . substr(strip_tags($trans['contentHtml'] ?? ''), 0, 200) . "...\n";
        break; // Just show first translation
    }
    echo "---\n\n";
}
