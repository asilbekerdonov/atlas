<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration 15/17: create project_tag table (depends on project, tag)
 */
final class Version20260901160015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create project_tag table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE project_tag (
                project_id INT NOT NULL,
                tag_id     INT NOT NULL,
                PRIMARY KEY (project_id, tag_id)
            )
        ');

        $this->addSql('
            CREATE INDEX IDX_91F26D60166D1F9C 
            ON project_tag (project_id)
        ');

        $this->addSql('
            CREATE INDEX IDX_91F26D60BAD26311 
            ON project_tag (tag_id)
        ');

        $this->addSql('
            ALTER TABLE project_tag 
            ADD CONSTRAINT FK_91F26D60166D1F9C 
            FOREIGN KEY (project_id) 
            REFERENCES project (id) 
            ON DELETE CASCADE 
            NOT DEFERRABLE
        ');

        $this->addSql('
            ALTER TABLE project_tag 
            ADD CONSTRAINT FK_91F26D60BAD26311 
            FOREIGN KEY (tag_id) 
            REFERENCES tag (id) 
            ON DELETE CASCADE 
            NOT DEFERRABLE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project_tag DROP CONSTRAINT FK_91F26D60166D1F9C');
        $this->addSql('ALTER TABLE project_tag DROP CONSTRAINT FK_91F26D60BAD26311');
        $this->addSql('DROP TABLE project_tag');
    }
}