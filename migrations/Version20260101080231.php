<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101080231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cusres (id SERIAL NOT NULL, user_id INT NOT NULL, slug VARCHAR(10) NOT NULL, projects JSON DEFAULT \'[]\' NOT NULL, certifications JSON DEFAULT \'[]\' NOT NULL, awards JSON DEFAULT \'[]\' NOT NULL, experiences JSON DEFAULT \'[]\' NOT NULL, data_updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, compiled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F1E9BFC0A76ED395 ON cusres (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F1E9BFC0989D9B62 ON cusres (slug)');
        $this->addSql('COMMENT ON COLUMN cusres.data_updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN cusres.compiled_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE cusres ADD CONSTRAINT FK_F1E9BFC0A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE cusres DROP CONSTRAINT FK_F1E9BFC0A76ED395');
        $this->addSql('DROP TABLE cusres');
    }
}
