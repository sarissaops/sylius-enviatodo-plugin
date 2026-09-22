<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractMigration;

final class Version20260923000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sarissaops_enviatodo_shipment table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sarissaops_enviatodo_shipment (id INT AUTO_INCREMENT NOT NULL, shipment_id INT NOT NULL, quote_uuid VARCHAR(255) NOT NULL, provider_id VARCHAR(255) NOT NULL, provider_service_id VARCHAR(255) NOT NULL, tracking_id VARCHAR(255) DEFAULT NULL, amount_minor INT NOT NULL, handover VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_ENVSHIP_SHIPMENT (shipment_id), INDEX IDX_ENVSHIP_TRACKING (tracking_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sarissaops_enviatodo_shipment ADD CONSTRAINT FK_ENVSHIP_SHIPMENT FOREIGN KEY (shipment_id) REFERENCES sylius_shipment (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE sarissaops_enviatodo_shipment DROP FOREIGN KEY FK_ENVSHIP_SHIPMENT
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE sarissaops_enviatodo_shipment
        SQL);
    }
}
