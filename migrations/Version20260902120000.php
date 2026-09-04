<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * viewed_by_recruiter_at on cv: drives the "N new" notification badge.
 */
final class Version20260902120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cv.viewed_by_recruiter_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv ADD COLUMN viewed_by_recruiter_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv DROP COLUMN viewed_by_recruiter_at');
    }
}
