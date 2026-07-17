<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260717161213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "
            INSERT INTO Setting (name, value) VALUES
            ('SMS_ACTIVE_PROVIDER', '')
        "
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "
            DELETE FROM Setting
            WHERE name IN (
                'SMS_ACTIVE_PROVIDER',
            )
        "
        );
    }
}
