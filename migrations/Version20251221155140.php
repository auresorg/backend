<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251221155140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users ADD showEmail BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE users ADD showProjects BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE users ADD showExperience BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE users ADD showCertifications BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE users ADD showEducation BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE users ADD showAwards BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "users" DROP showEmail');
        $this->addSql('ALTER TABLE "users" DROP showProjects');
        $this->addSql('ALTER TABLE "users" DROP showExperience');
        $this->addSql('ALTER TABLE "users" DROP showCertifications');
        $this->addSql('ALTER TABLE "users" DROP showEducation');
        $this->addSql('ALTER TABLE "users" DROP showAwards');
    }
}
