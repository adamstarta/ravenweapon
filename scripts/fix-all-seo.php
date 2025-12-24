<?php
$pdo = new PDO('mysql:host=localhost;dbname=shopware;charset=utf8mb4', 'root', 'root');
$langId = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
$scId = '0191c12dd4b970949e9aeec40433be3e';

// Products to fix: product_number => [category_path, slug]
$products = [
    'MGP-ZBH-GRP-000-FD-00053' => ['zubehoer/griffe-handschutz', 'afg-angled-fore-grip-fd'],
    'MGP-ZBH-GRP-000-OD-00055' => ['zubehoer/griffe-handschutz', 'afg-angled-fore-grip-od'],
    'MGP-ZBH-GRP-000-SG-00054' => ['zubehoer/griffe-handschutz', 'afg-angled-fore-grip-sg'],
    'DEX-PIS-CRC-9mm-BLO-00141' => ['waffen/caracal-lynx/lynx-compact', 'caracal-lynx-compact-black-oxyde'],
    'DEX-PIS-RPC-9mm-BLO-00135' => ['waffen/rapax/rx-compact', 'rapax-ii-compact-black-oxide'],
    'MGP-ZBH-STK-000-SG-00042' => ['zubehoer/schienen-zubehoer', 'moe-sl-carbine-stock-mil-spec-sg'],
    'MGP-ZBH-STK-000-OD-00043' => ['zubehoer/schienen-zubehoer', 'moe-sl-carbine-stock-mil-spec-od'],
    'MGP-ZBH-STK-000-SG-00038' => ['zubehoer/schienen-zubehoer', 'moe-carbine-stock-mil-spec-sg'],
    'MGP-ZBH-STK-000-OD-00039' => ['zubehoer/schienen-zubehoer', 'moe-carbine-stock-mil-spec-od'],
    'MGP-ZBH-STK-000-FD-00037' => ['zubehoer/schienen-zubehoer', 'moe-carbine-stock-mil-spec-fd'],
    'MGP-ZBH-STK-000-SG-00034' => ['zubehoer/schienen-zubehoer', 'moe-fixed-carbine-stock-mil-spec-sg'],
    'MGP-ZBH-STK-000-OD-00035' => ['zubehoer/schienen-zubehoer', 'moe-fixed-carbine-stock-mil-spec-od'],
    'MGP-ZBH-STK-000-SG-00046' => ['zubehoer/schienen-zubehoer', 'moe-sl-s-carbine-stock-mil-spec-sg'],
    'MGP-ZBH-STK-000-FD-00045' => ['zubehoer/schienen-zubehoer', 'moe-sl-s-carbine-stock-mil-spec-fd'],
    'MGP-ZBH-STK-000-OD-00047' => ['zubehoer/schienen-zubehoer', 'moe-sl-s-carbine-stock-mil-spec-od'],
    'MGP-MG-AR30W-223-CT-00089' => ['zubehoer/magazine', 'pmag-30-ar-m4-gen-m3-window-556x45'],
    'MGP-MG-AR30-223-CT-00087' => ['zubehoer/magazine', 'pmag-30-ar-m4-gen-m3-556x45'],
    'MGP-ZBH-STK-000-FD-00049' => ['zubehoer/schienen-zubehoer', 'prs-gen3-precision-adjustable-stock-fd'],
    'MGP-ZBH-STK-000-OD-00051' => ['zubehoer/schienen-zubehoer', 'prs-gen3-precision-adjustable-stock-od'],
    'MGP-ZBH-STK-000-SG-00050' => ['zubehoer/schienen-zubehoer', 'prs-gen3-precision-adjustable-stock-sg'],
];

$inserted = 0;
foreach ($products as $pn => $data) {
    $stmt = $pdo->prepare("SELECT LOWER(HEX(id)) FROM product WHERE product_number = ?");
    $stmt->execute([$pn]);
    $productId = $stmt->fetchColumn();
    if (!$productId) { echo "NOT FOUND: $pn\n"; continue; }

    $seoPath = $data[0] . '/' . $data[1];

    $pdo->prepare("INSERT INTO seo_url (id, language_id, sales_channel_id, foreign_key, route_name, path_info, seo_path_info, is_canonical, is_modified, is_deleted, created_at)
        VALUES (UNHEX(REPLACE(UUID(),'-','')), UNHEX(?), UNHEX(?), UNHEX(?), 'frontend.detail.page', ?, ?, 1, 0, 0, NOW())")
        ->execute([$langId, $scId, $productId, "/detail/$productId", $seoPath]);

    echo "OK: $pn -> /$seoPath\n";
    $inserted++;
}
echo "\nInserted: $inserted SEO URLs\n";
