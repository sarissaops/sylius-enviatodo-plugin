<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractPostgreSQLMigration;

final class Version20260923000003 extends AbstractPostgreSQLMigration
{
    public function getDescription(): string
    {
        return 'Add guide and handover detail columns to sarissaops_enviatodo_shipment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE sarissaops_enviatodo_shipment ADD guide_id VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sarissaops_enviatodo_shipment ADD handover_detail VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE sarissaops_enviatodo_shipment DROP guide_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sarissaops_enviatodo_shipment DROP handover_detail
        SQL);
    }
}
