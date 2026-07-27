<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715104555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            "
            INSERT INTO Setting (name, value) VALUES
            ('MAP_CENTER_LATITUDE', '0'),
            ('MAP_CENTER_LONGITUDE', '0'),
            ('MAP_CENTER_ZOOM', '12')
        "
        );

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(
            "
            DELETE FROM Setting
            WHERE name IN (
                'MAP_CENTER_LATITUDE',
                'MAP_CENTER_LONGITUDE',
                'MAP_CENTER_ZOOM'
            )
        "
        );
    }
}
