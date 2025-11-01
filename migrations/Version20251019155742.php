<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019155742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE education (id SERIAL NOT NULL, school VARCHAR(255) NOT NULL, degree VARCHAR(255) NOT NULL, field VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, grade VARCHAR(50) DEFAULT NULL, description TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EEA76ED395');
        $this->addSql('DROP INDEX idx_2fb3d0eea76ed395');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EEA76ED395 ON project (user_id)');
        $this->addSql('ALTER TABLE users ADD education_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E92CA1BD71 FOREIGN KEY (education_id) REFERENCES education (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E92CA1BD71 ON users (education_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "users" DROP CONSTRAINT FK_1483A5E92CA1BD71');
        $this->addSql('DROP TABLE education');
        $this->addSql('DROP INDEX UNIQ_1483A5E92CA1BD71');
        $this->addSql('ALTER TABLE "users" DROP education_id');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT fk_2fb3d0eea76ed395');
        $this->addSql('DROP INDEX UNIQ_2FB3D0EEA76ED395');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT fk_2fb3d0eea76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_2fb3d0eea76ed395 ON project (user_id)');
    }
}
