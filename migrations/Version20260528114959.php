<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260528114959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('TIME_INTERVAL_BETWEEN_REQUESTS', '30')");
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('TIME_INTERVAL_TO_RESET_ATTEMPTS', '60')");
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('ATTEMPTS_NUMBER', '5')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("DELETE FROM Setting WHERE name = 'TIME_INTERVAL_BETWEEN_REQUESTS'");
        $this->addSql("DELETE FROM Setting WHERE name = 'TIME_INTERVAL_TO_RESET_ATTEMPTS'");
        $this->addSql("DELETE FROM Setting WHERE name = 'ATTEMPTS_NUMBER'");
    }
}
