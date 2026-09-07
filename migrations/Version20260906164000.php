<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add format column to position table
 */
final class Version20260906164000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add format column to position table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position ADD format VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position DROP format');
    }
}
