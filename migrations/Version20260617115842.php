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
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE AccessPoint (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) DEFAULT NULL, ssid VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, longitude VARCHAR(255) NOT NULL, latitude VARCHAR(255) NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, network_id INT NOT NULL, INDEX IDX_5B0445EA34128B91 (network_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE Network (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, operator VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, geometry JSON DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
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
