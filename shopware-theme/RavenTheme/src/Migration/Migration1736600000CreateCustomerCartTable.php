<?php declare(strict_types=1);

namespace RavenTheme\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1736600000CreateCustomerCartTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1736600000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `raven_customer_cart` (
                `customer_id` BINARY(16) NOT NULL,
                `cart_data` LONGTEXT NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`customer_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive changes
    }
}
