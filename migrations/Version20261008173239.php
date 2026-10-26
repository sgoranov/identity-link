<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008173239 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename auth_request to authorization_session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE auth_request RENAME TO authorization_session');
        $this->addSql('ALTER INDEX auth_request_pkey RENAME TO authorization_session_pkey');
        $this->addSql('ALTER INDEX idx_auth_request_expires_at RENAME TO idx_authorization_session_expires_at');
        $this->addSql('ALTER INDEX idx_auth_request_consumed RENAME TO idx_authorization_session_consumed');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_authorization_session_consumed RENAME TO idx_auth_request_consumed');
        $this->addSql('ALTER INDEX idx_authorization_session_expires_at RENAME TO idx_auth_request_expires_at');
        $this->addSql('ALTER INDEX authorization_session_pkey RENAME TO auth_request_pkey');
        $this->addSql('ALTER TABLE authorization_session RENAME TO auth_request');
    }
}
