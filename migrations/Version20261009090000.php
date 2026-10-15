<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add authorization_session.two_fa_request_id, binding the 2FA request to the session that initiated it';
    }

    public function up(Schema $schema): void
    {
        // Sessions waiting on 2FA have no request id stored, so their 2FA step fails and the
        // user starts a new login. Other sessions are not affected.
        $this->addSql('ALTER TABLE authorization_session ADD two_fa_request_id VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authorization_session DROP COLUMN two_fa_request_id');
    }
}
