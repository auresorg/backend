<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251101021819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE award (id SERIAL NOT NULL, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, issuer VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, description TEXT DEFAULT NULL, url TEXT DEFAULT NULL, awarded_on DATE DEFAULT NULL, role VARCHAR(20) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8A5B2EE7A76ED395 ON award (user_id)');
        $this->addSql('ALTER TABLE award ADD CONSTRAINT FK_8A5B2EE7A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE award DROP CONSTRAINT FK_8A5B2EE7A76ED395');
        $this->addSql('DROP TABLE award');
    }
}
