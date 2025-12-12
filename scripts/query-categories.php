<?php
// Use mysql CLI via shell_exec
$result = shell_exec("mysql -u root -proot shopware -e \"
    SELECT
        HEX(c.id) as id,
        ct.name,
        c.active,
        c.level,
        HEX(c.cms_page_id) as cms_page_id
    FROM category c
    LEFT JOIN category_translation ct ON c.id = ct.category_id
    WHERE c.active = 1
    ORDER BY c.level, ct.name
    LIMIT 50
\" 2>/dev/null");

echo "Active categories:\n";
echo str_repeat("=", 100) . "\n";
echo $result;
