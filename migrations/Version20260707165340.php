<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260707165340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("UPDATE AccessPoint SET location = ST_GeomFromText('POINT(0 0)') WHERE location IS NULL");
        $this->addSql("UPDATE Network SET geometry = ST_GeomFromText('GEOMETRYCOLLECTION EMPTY', 4326) WHERE geometry IS NULL");

        $this->addSql('ALTER TABLE AccessPoint CHANGE location location POINT NOT NULL');
        $this->addSql('ALTER TABLE Network CHANGE geometry geometry GEOMETRY SRID 4326 NOT NULL');

        $this->addSql('CREATE SPATIAL INDEX idx_ap_location ON AccessPoint (location)');
        $this->addSql('CREATE SPATIAL INDEX idx_network_geometry ON Network (geometry)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_ap_location ON AccessPoint');
        $this->addSql('DROP INDEX idx_network_geometry ON Network');

        $this->addSql('ALTER TABLE AccessPoint CHANGE location location JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE Network CHANGE geometry geometry JSON DEFAULT NULL');
    }
}
