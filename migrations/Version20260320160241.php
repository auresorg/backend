<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320160241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE award ALTER role DROP DEFAULT');
        $this->addSql('ALTER TABLE certification ALTER role DROP DEFAULT');
        $this->addSql('ALTER TABLE experience ALTER role DROP DEFAULT');
        $this->addSql('ALTER TABLE project ALTER role DROP DEFAULT');
        $this->addSql('ALTER TABLE users ADD razorpayCustomerId VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD razorpaySubscriptionId VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "users" DROP razorpayCustomerId');
        $this->addSql('ALTER TABLE "users" DROP razorpaySubscriptionId');
        $this->addSql('ALTER TABLE project ALTER role SET DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE experience ALTER role SET DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE certification ALTER role SET DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE award ALTER role SET DEFAULT \'[]\'');
    }
}
