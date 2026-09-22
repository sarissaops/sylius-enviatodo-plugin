<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractPostgreSQLMigration;

final class Version20260923000001 extends AbstractPostgreSQLMigration
{
    public function getDescription(): string
    {
        return 'Create sarissaops_enviatodo_shipment table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sarissaops_enviatodo_shipment (id SERIAL NOT NULL, shipment_id INT NOT NULL, quote_uuid VARCHAR(255) NOT NULL, provider_id VARCHAR(255) NOT NULL, provider_service_id VARCHAR(255) NOT NULL, tracking_id VARCHAR(255) DEFAULT NULL, amount_minor INT NOT NULL, handover VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_ENVSHIP_SHIPMENT ON sarissaops_enviatodo_shipment (shipment_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_ENVSHIP_TRACKING ON sarissaops_enviatodo_shipment (tracking_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sarissaops_enviatodo_shipment ADD CONSTRAINT FK_ENVSHIP_SHIPMENT FOREIGN KEY (shipment_id) REFERENCES sylius_shipment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE sarissaops_enviatodo_shipment DROP CONSTRAINT FK_ENVSHIP_SHIPMENT
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE sarissaops_enviatodo_shipment
        SQL);
    }
}
