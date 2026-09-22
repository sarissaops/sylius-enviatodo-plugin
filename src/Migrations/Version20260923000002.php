<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractMigration;

final class Version20260923000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add guide and handover detail columns to sarissaops_enviatodo_shipment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE sarissaops_enviatodo_shipment ADD guide_id VARCHAR(255) DEFAULT NULL, ADD handover_detail VARCHAR(255) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE sarissaops_enviatodo_shipment DROP guide_id, DROP handover_detail
        SQL);
    }
}
