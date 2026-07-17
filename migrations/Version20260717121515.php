<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260717121515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE SMSProvider (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE SMSProviderParam (id INT AUTO_INCREMENT NOT NULL, paramType VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, smsProvider_id INT NOT NULL, INDEX IDX_1299D69BAF690EC (smsProvider_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE SMSProviderParam ADD CONSTRAINT FK_1299D69BAF690EC FOREIGN KEY (smsProvider_id) REFERENCES SMSProvider (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE SMSProviderParam DROP FOREIGN KEY FK_1299D69BAF690EC');
        $this->addSql('DROP TABLE SMSProvider');
        $this->addSql('DROP TABLE SMSProviderParam');
    }
}
