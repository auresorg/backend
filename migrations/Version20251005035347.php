<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251005035347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "users" (id SERIAL NOT NULL, githubId BIGINT NOT NULL, username VARCHAR(128) NOT NULL, email VARCHAR(255) NOT NULL, avatarUrl VARCHAR(255) NOT NULL, accessToken TEXT NOT NULL, firstName VARCHAR(128) DEFAULT NULL, lastName VARCHAR(128) DEFAULT NULL, linkedin VARCHAR(255) DEFAULT NULL, portfolio VARCHAR(255) DEFAULT NULL, leetcode VARCHAR(255) DEFAULT NULL, skills JSON DEFAULT \'[]\' NOT NULL, projectsCount INT DEFAULT 0 NOT NULL, certCount INT DEFAULT 0 NOT NULL, awardsCount INT DEFAULT 0 NOT NULL, experienceCount INT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE "users"');
    }
}
