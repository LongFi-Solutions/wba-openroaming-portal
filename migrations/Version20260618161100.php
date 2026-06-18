<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618161100 extends AbstractMigration
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
            ('DELETE_UNCONFIRMED_USERS_CRON_ENABLED', 'ON'),
            ('USERS_WHEN_PROFILE_EXPIRES_CRON_ENABLED', 'ON'),
            ('LDAP_SYNC_CRON_ENABLED', 'ON'),
            ('DOMAIN_BLACKLIST_IMPORT_CRON_ENABLED', 'ON')
        "
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "
            DELETE FROM Setting
            WHERE name IN (
                'DELETE_UNCONFIRMED_USERS_CRON_ENABLED',
                'USERS_WHEN_PROFILE_EXPIRES_CRON_ENABLED',
                'LDAP_SYNC_CRON_ENABLED',
                'DOMAIN_BLACKLIST_IMPORT_CRON_ENABLED'
            )
        "
        );
    }
}
