<?php return array(
    'root' => array(
        'name' => 'payrexx/payment',
        'pretty_version' => '2.1.0',
        'version' => '2.1.0.0',
        'reference' => null,
        'type' => 'shopware-platform-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'payrexx/payment' => array(
            'pretty_version' => '2.1.0',
            'version' => '2.1.0.0',
            'reference' => null,
            'type' => 'shopware-platform-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'payrexx/payrexx' => array(
            'pretty_version' => 'v2.0.2',
            'version' => '2.0.2.0',
            'reference' => '88ebd2d02e1809fff7b5fa93b4a66e3ac0186e9b',
            'type' => 'library',
            'install_path' => __DIR__ . '/../payrexx/payrexx',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
