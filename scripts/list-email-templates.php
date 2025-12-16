<?php
/**
 * List all email templates in Shopware
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
    curl_close($ch);
    return json_decode($response, true);
}

$token = getToken($API_URL);
if (!$token) {
    die("Failed to get token\n");
}

echo "=== EMAIL TEMPLATES ===\n\n";

// Get all mail templates
$result = apiRequest($API_URL, $token, 'POST', 'search/mail-template', [
    'limit' => 100,
    'associations' => [
        'mailTemplateType' => []
    ]
]);

foreach ($result['data'] ?? [] as $template) {
    $type = $template['mailTemplateType']['technicalName'] ?? 'unknown';
    $subject = $template['subject'] ?? '';
    echo "Type: $type\n";
    echo "Subject: $subject\n";
    echo "ID: {$template['id']}\n";
    echo "---\n";
}
