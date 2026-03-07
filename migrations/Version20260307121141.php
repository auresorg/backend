<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260307121141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE award DROP CONSTRAINT FK_8A5B2EE7A76ED395');
        $this->addSql('ALTER TABLE award ALTER role TYPE JSON');
        $this->addSql('ALTER TABLE award ALTER role DROP NOT NULL');
        $this->addSql('ALTER TABLE award ALTER role TYPE JSON');
        $this->addSql('ALTER TABLE award ADD CONSTRAINT FK_8A5B2EE7A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE certification DROP CONSTRAINT FK_6C3C6D75A76ED395');
        $this->addSql('ALTER TABLE certification ALTER role TYPE JSON');
        $this->addSql('ALTER TABLE certification ALTER role DROP NOT NULL');
        $this->addSql('ALTER TABLE certification ALTER role TYPE JSON');
        $this->addSql('ALTER TABLE certification ADD CONSTRAINT FK_6C3C6D75A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE education DROP CONSTRAINT FK_DB0A5ED2A76ED395');
        $this->addSql('ALTER TABLE education ADD CONSTRAINT FK_DB0A5ED2A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE experience DROP CONSTRAINT FK_590C103A76ED395');
        $this->addSql('ALTER TABLE experience ALTER role TYPE JSON');
        $this->addSql('ALTER TABLE experience ALTER role TYPE JSON');
        $this->addSql('ALTER TABLE experience ADD CONSTRAINT FK_590C103A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project ALTER role TYPE JSON');
        $this->addSql('ALTER TABLE project ALTER role DROP NOT NULL');
        $this->addSql('ALTER TABLE project ALTER role TYPE JSON');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE experience DROP CONSTRAINT fk_590c103a76ed395');
        $this->addSql('ALTER TABLE experience ALTER role TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE experience ADD CONSTRAINT fk_590c103a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project ALTER role TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE project ALTER role SET NOT NULL');
        $this->addSql('ALTER TABLE education DROP CONSTRAINT fk_db0a5ed2a76ed395');
        $this->addSql('ALTER TABLE education ADD CONSTRAINT fk_db0a5ed2a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE certification DROP CONSTRAINT fk_6c3c6d75a76ed395');
        $this->addSql('ALTER TABLE certification ALTER role TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE certification ALTER role SET NOT NULL');
        $this->addSql('ALTER TABLE certification ADD CONSTRAINT fk_6c3c6d75a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE award DROP CONSTRAINT fk_8a5b2ee7a76ed395');
        $this->addSql('ALTER TABLE award ALTER role TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE award ALTER role SET NOT NULL');
        $this->addSql('ALTER TABLE award ADD CONSTRAINT fk_8a5b2ee7a76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
