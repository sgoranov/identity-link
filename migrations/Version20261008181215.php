<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008181215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop authorization_session.consent_approved, consent is a step of the authorization flow';
    }

    public function up(Schema $schema): void
    {
        // Sessions in progress reached COMPLETED before consent under the previous flow.
        // End them, so no code is issued for a session whose consent was never recorded.
        $this->addSql('UPDATE authorization_session SET consumed = true WHERE consumed = false');
        $this->addSql('ALTER TABLE authorization_session DROP COLUMN consent_approved');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE authorization_session ADD COLUMN consent_approved BOOLEAN DEFAULT NULL');
    }
}
