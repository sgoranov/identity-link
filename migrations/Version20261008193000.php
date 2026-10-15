<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add authorization_session.browser_binding_hash, binding each session to the browser that started it';
    }

    public function up(Schema $schema): void
    {
        // Sessions in progress have no binding cookie and could not continue. End them,
        // the empty hash below never matches a cookie anyway.
        $this->addSql('UPDATE authorization_session SET consumed = true WHERE consumed = false');
        $this->addSql("ALTER TABLE authorization_session ADD browser_binding_hash VARCHAR(64) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE authorization_session ALTER browser_binding_hash DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authorization_session DROP COLUMN browser_binding_hash');
    }
}
