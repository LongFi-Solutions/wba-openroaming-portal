<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616133445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Removes FREERADIUS_LAST_CONNECTION_CRON setting from the database.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM Setting WHERE name = 'FREERADIUS_LAST_CONNECTION_CRON'");
        $this->addSql("DELETE FROM Setting WHERE name = 'TIME_STAMP_FREERADIUS_CRON'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('FREERADIUS_LAST_CONNECTION_CRON', '00 03 3 2 1')");
        $this->addSql("INSERT INTO Setting (name, value) VALUES ('TIME_STAMP_FREERADIUS_CRON', '0')");
    }
}
