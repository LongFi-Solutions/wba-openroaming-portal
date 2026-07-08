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
        $this->addSql('ALTER TABLE AccessPoint CHANGE location location POINT DEFAULT NULL');
        $this->addSql('ALTER TABLE Network CHANGE geometry geometry GEOMETRY SRID 4326 DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE AccessPoint CHANGE location location JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE Network CHANGE geometry geometry JSON DEFAULT NULL');
    }
}
