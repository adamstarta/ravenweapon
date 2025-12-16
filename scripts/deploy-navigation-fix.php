<?php
/**
 * Deploy navigation fix - upload updated header template
 */

$config = [
    'shopware_url' => 'https://ortak.ch',
    'api_user' => 'Micro the CEO',
    'api_password' => '100%Ravenweapon...',
];

// SSH/SFTP config for deployment
$sshConfig = [
    'host' => 'ortak.ch',
    'user' => 'root',
    'key_file' => 'C:/Users/alama/.ssh/id_ed25519',
];

echo "=== Deploying Navigation Fix ===\n\n";

// Read the updated header file
$headerFile = __DIR__ . '/../shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig';
$headerContent = file_get_contents($headerFile);

if (!$headerContent) {
    die("Error: Could not read header file\n");
}

echo "1. Header file read successfully (" . strlen($headerContent) . " bytes)\n";

// Create temp file for upload
$tempFile = sys_get_temp_dir() . '/header.html.twig';
file_put_contents($tempFile, $headerContent);

// Build SCP command
$remotePath = '/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig';
$scpCmd = sprintf(
    'scp -i "%s" "%s" %s@%s:"%s"',
    $sshConfig['key_file'],
    $tempFile,
    $sshConfig['user'],
    $sshConfig['host'],
    $remotePath
);

echo "2. Uploading header file via SCP...\n";
echo "   Command: $scpCmd\n";

exec($scpCmd . ' 2>&1', $output, $returnCode);

if ($returnCode !== 0) {
    echo "   SCP Error: " . implode("\n", $output) . "\n";
    die("Upload failed\n");
}

echo "   Upload successful!\n";

// Clear cache via SSH
echo "\n3. Clearing Shopware cache via SSH...\n";
$sshCmd = sprintf(
    'ssh -i "%s" %s@%s "cd /var/www/html && php bin/console cache:clear"',
    $sshConfig['key_file'],
    $sshConfig['user'],
    $sshConfig['host']
);

exec($sshCmd . ' 2>&1', $cacheOutput, $cacheReturn);
echo implode("\n", $cacheOutput) . "\n";

// Also clear via API
echo "\n4. Clearing cache via API...\n";

function getAccessToken($config) {
    $ch = curl_init($config['shopware_url'] . '/api/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $config['api_user'],
            'password' => $config['api_password'],
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

$token = getAccessToken($config);
if ($token) {
    $ch = curl_init($config['shopware_url'] . '/api/_action/cache');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    curl_exec($ch);
    curl_close($ch);
    echo "   API cache cleared\n";
}

// Clean up
unlink($tempFile);

echo "\n=== Deployment Complete! ===\n";
echo "Refresh the page and hover over 'Raven Weapons' to see the updated navigation.\n";
