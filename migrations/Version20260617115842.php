<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617115842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE AccessPoint (
        id INT AUTO_INCREMENT NOT NULL,
        network_id INT NOT NULL,
        name VARCHAR(255) DEFAULT NULL,
        ssid VARCHAR(255) NOT NULL,
        mac_address VARCHAR(255) DEFAULT NULL,
        vendor VARCHAR(255) DEFAULT NULL,
        model VARCHAR(255) DEFAULT NULL,
        standard VARCHAR(255) DEFAULT NULL,
        serial_number VARCHAR(255) DEFAULT NULL,
        location JSON DEFAULT NULL,
        altitude_msl DOUBLE PRECISION DEFAULT NULL,
        altitude_agl DOUBLE PRECISION DEFAULT NULL,
        createdAt DATETIME NOT NULL,
        updatedAt DATETIME NOT NULL,
        INDEX IDX_5B0445EA34128B91 (network_id),
        PRIMARY KEY (id)
    ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE Network (
        id INT AUTO_INCREMENT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        geometry JSON DEFAULT NULL,
        createdAt DATETIME NOT NULL,
        updatedAt DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE AccessPoint ADD CONSTRAINT FK_5B0445EA34128B91 FOREIGN KEY (network_id) REFERENCES Network (id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_domain_pattern ON DomainBlacklist (pattern)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE AccessPoint DROP FOREIGN KEY FK_5B0445EA34128B91');
        $this->addSql('DROP TABLE AccessPoint');
        $this->addSql('DROP TABLE Network');
        $this->addSql('DROP INDEX uniq_domain_pattern ON DomainBlacklist');
    }
}
