<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Custom Migration to safely convert 'role' from VARCHAR to JSON array
 */
final class Version20260307120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert role column from VARCHAR string to JSON array on all entities.';
    }

    public function up(Schema $schema): void
    {
        // Drop existing defaults before casting to JSON
        $this->addSql('ALTER TABLE project ALTER COLUMN role DROP DEFAULT');
        $this->addSql('ALTER TABLE experience ALTER COLUMN role DROP DEFAULT');
        $this->addSql('ALTER TABLE certification ALTER COLUMN role DROP DEFAULT');
        $this->addSql('ALTER TABLE award ALTER COLUMN role DROP DEFAULT');

        $this->addSql('ALTER TABLE project ALTER COLUMN role TYPE JSON USING json_build_array(role)');
        $this->addSql('ALTER TABLE experience ALTER COLUMN role TYPE JSON USING json_build_array(role)');
        $this->addSql('ALTER TABLE certification ALTER COLUMN role TYPE JSON USING json_build_array(role)');
        $this->addSql('ALTER TABLE award ALTER COLUMN role TYPE JSON USING json_build_array(role)');
        
        // Re-apply a valid JSON-compatible default if necessary (Doctrine usually does '[]' at application level)
        $this->addSql("ALTER TABLE project ALTER COLUMN role SET DEFAULT '[]'");
        $this->addSql("ALTER TABLE experience ALTER COLUMN role SET DEFAULT '[]'");
        $this->addSql("ALTER TABLE certification ALTER COLUMN role SET DEFAULT '[]'");
        $this->addSql("ALTER TABLE award ALTER COLUMN role SET DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        // Drop new defaults before casting back
        $this->addSql('ALTER TABLE project ALTER COLUMN role DROP DEFAULT');
        $this->addSql('ALTER TABLE experience ALTER COLUMN role DROP DEFAULT');
        $this->addSql('ALTER TABLE certification ALTER COLUMN role DROP DEFAULT');
        $this->addSql('ALTER TABLE award ALTER COLUMN role DROP DEFAULT');

        $this->addSql('ALTER TABLE project ALTER COLUMN role TYPE VARCHAR(255) USING role->>0');
        $this->addSql('ALTER TABLE experience ALTER COLUMN role TYPE VARCHAR(255) USING role->>0');
        $this->addSql('ALTER TABLE certification ALTER COLUMN role TYPE VARCHAR(255) USING role->>0');
        $this->addSql('ALTER TABLE award ALTER COLUMN role TYPE VARCHAR(255) USING role->>0');
    }
}
