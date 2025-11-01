<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251020034423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE education ALTER school DROP NOT NULL');
        $this->addSql('ALTER TABLE education ALTER degree DROP NOT NULL');
        $this->addSql('ALTER TABLE education ALTER field DROP NOT NULL');
        $this->addSql('ALTER TABLE education ALTER start_date DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE education ALTER school SET NOT NULL');
        $this->addSql('ALTER TABLE education ALTER degree SET NOT NULL');
        $this->addSql('ALTER TABLE education ALTER field SET NOT NULL');
        $this->addSql('ALTER TABLE education ALTER start_date SET NOT NULL');
    }
}
