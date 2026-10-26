<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add access_token.auth_code_identifier, linking tokens to the authorization code they descend from';
    }

    public function up(Schema $schema): void
    {
        // Existing tokens stay unlinked, so reusing a code that was exchanged before this migration revokes nothing.
        // Codes are short-lived, so only codes in use during the migration are affected.
        $this->addSql('ALTER TABLE access_token ADD auth_code_identifier TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_access_token_auth_code_identifier ON access_token (auth_code_identifier)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_access_token_auth_code_identifier');
        $this->addSql('ALTER TABLE access_token DROP COLUMN auth_code_identifier');
    }
}
