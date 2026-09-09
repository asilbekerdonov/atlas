<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enforce case-insensitive uniqueness on attribute.name.
 *
 * Legacy duplicates that differed only by case (cap/CAP, gpa/GPA,
 * python/Python) were removed first, so a functional index on LOWER(name)
 * can now be created. Any future insert that matches an existing name in a
 * different case is rejected by the DB (UniqueConstraintViolationException),
 * and AttributeLibraryService performs an explicit case-insensitive check so
 * callers get a clean 409.
 */
final class Version20260907110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add case-insensitive unique index on attribute.name';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_attribute_name_lower ON attribute (LOWER(name))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_attribute_name_lower');
    }
}
