<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the (DC2Type:...) column comments, which DBAL 4 no longer uses to tell column types apart';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN access_token.id IS NULL');
        $this->addSql('COMMENT ON COLUMN access_token.expiry_date_time IS NULL');
        $this->addSql('COMMENT ON COLUMN auth_code.id IS NULL');
        $this->addSql('COMMENT ON COLUMN auth_code.expiry_date_time IS NULL');
        $this->addSql('COMMENT ON COLUMN authorization_session.id IS NULL');
        $this->addSql('COMMENT ON COLUMN authorization_session.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN authorization_session.expires_at IS NULL');
        $this->addSql('COMMENT ON COLUMN refresh_token.id IS NULL');
        $this->addSql('COMMENT ON COLUMN refresh_token.access_token_id IS NULL');
        $this->addSql('COMMENT ON COLUMN refresh_token.expiry_date_time IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('COMMENT ON COLUMN access_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN access_token.expiry_date_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN auth_code.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN auth_code.expiry_date_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN authorization_session.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN authorization_session.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN authorization_session.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN refresh_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN refresh_token.access_token_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN refresh_token.expiry_date_time IS \'(DC2Type:datetime_immutable)\'');
    }
}
