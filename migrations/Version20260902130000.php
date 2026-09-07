<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Position owner (created_by) for recruiter data isolation.
 */
final class Version20260902130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position.created_by_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            ALTER TABLE position 
            ADD COLUMN created_by_id INT DEFAULT NULL
        ');

        $this->addSql('
            ALTER TABLE position 
            ADD CONSTRAINT FK_position_created_by 
            FOREIGN KEY (created_by_id) 
            REFERENCES "user" (id) 
            ON DELETE SET NULL 
            NOT DEFERRABLE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('
            ALTER TABLE position 
            DROP CONSTRAINT FK_position_created_by
        ');

        $this->addSql('
            ALTER TABLE position 
            DROP COLUMN created_by_id
        ');
    }
}