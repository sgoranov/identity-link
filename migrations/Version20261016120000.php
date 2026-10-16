<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an index on access_token.identifier, looked up on every request with a bearer token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_access_token_identifier ON access_token (identifier)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_access_token_identifier');
    }
}
