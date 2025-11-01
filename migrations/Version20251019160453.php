<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019160453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE education ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE education ADD CONSTRAINT FK_DB0A5ED2A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DB0A5ED2A76ED395 ON education (user_id)');
        $this->addSql('ALTER TABLE users DROP CONSTRAINT fk_1483a5e92ca1bd71');
        $this->addSql('DROP INDEX uniq_1483a5e92ca1bd71');
        $this->addSql('ALTER TABLE users DROP education_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE education DROP CONSTRAINT FK_DB0A5ED2A76ED395');
        $this->addSql('DROP INDEX UNIQ_DB0A5ED2A76ED395');
        $this->addSql('ALTER TABLE education DROP user_id');
        $this->addSql('ALTER TABLE "users" ADD education_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "users" ADD CONSTRAINT fk_1483a5e92ca1bd71 FOREIGN KEY (education_id) REFERENCES education (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e92ca1bd71 ON "users" (education_id)');
    }
}
