<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008174351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename authorization_session.login_state to authorization_state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authorization_session RENAME COLUMN login_state TO authorization_state');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authorization_session RENAME COLUMN authorization_state TO login_state');
    }
}
