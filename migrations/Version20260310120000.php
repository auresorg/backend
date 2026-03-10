<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add template and last_compiled columns to resumes table.
 */
final class Version20260310120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add template and last_compiled columns to resumes table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resumes ADD template VARCHAR(50) DEFAULT \'jakes\' NOT NULL');
        $this->addSql('ALTER TABLE resumes ADD last_compiled TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE resumes DROP template');
        $this->addSql('ALTER TABLE resumes DROP last_compiled');
    }
}
