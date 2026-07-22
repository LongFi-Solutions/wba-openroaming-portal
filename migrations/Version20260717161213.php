<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260717161213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert SMS_ACTIVE_PROVIDER Setting row (value starts NULL until a provider is activated)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO Setting (name, value) VALUES ('SMS_ACTIVE_PROVIDER', NULL)"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM Setting WHERE name = 'SMS_ACTIVE_PROVIDER'"
        );
    }
}
