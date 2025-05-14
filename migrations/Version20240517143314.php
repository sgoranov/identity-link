<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240517143314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial database schema';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_token (id UUID NOT NULL, identifier TEXT NOT NULL, user_identifier VARCHAR(255) DEFAULT NULL, client_identifier VARCHAR(255) NOT NULL, expiry_date_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, scopes TEXT DEFAULT NULL, is_revoked BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN access_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN access_token.expiry_date_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE auth_code (id UUID NOT NULL, identifier TEXT NOT NULL, user_identifier VARCHAR(255) NOT NULL, client_identifier VARCHAR(255) NOT NULL, expiry_date_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, redirect_uri VARCHAR(500) DEFAULT NULL, scopes TEXT DEFAULT NULL, is_revoked BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN auth_code.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN auth_code.expiry_date_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE refresh_token (id UUID NOT NULL, access_token_id UUID DEFAULT NULL, identifier VARCHAR(255) NOT NULL, expiry_date_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_revoked BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C74F21952CCB2688 ON refresh_token (access_token_id)');
        $this->addSql('COMMENT ON COLUMN refresh_token.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN refresh_token.access_token_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN refresh_token.expiry_date_time IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F21952CCB2688 FOREIGN KEY (access_token_id) REFERENCES access_token (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT FK_C74F21952CCB2688');
        $this->addSql('DROP TABLE access_token');
        $this->addSql('DROP TABLE auth_code');
        $this->addSql('DROP TABLE refresh_token');
    }
}
