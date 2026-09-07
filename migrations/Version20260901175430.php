<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260901175430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('
            CREATE TABLE position_tag (
                position_id INT NOT NULL,
                tag_id      INT NOT NULL,
                PRIMARY KEY (position_id, tag_id)
            )
        ');

        $this->addSql('
            CREATE INDEX IDX_F73FBF9BDD842E46 
            ON position_tag (position_id)
        ');

        $this->addSql('
            CREATE INDEX IDX_F73FBF9BBAD26311 
            ON position_tag (tag_id)
        ');

        $this->addSql('
            ALTER TABLE position_tag 
            ADD CONSTRAINT FK_F73FBF9BDD842E46 
            FOREIGN KEY (position_id) 
            REFERENCES position (id) 
            ON DELETE CASCADE 
            NOT DEFERRABLE
        ');

        $this->addSql('
            ALTER TABLE position_tag 
            ADD CONSTRAINT FK_F73FBF9BBAD26311 
            FOREIGN KEY (tag_id) 
            REFERENCES tag (id) 
            ON DELETE CASCADE 
            NOT DEFERRABLE
        ');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE position_tag DROP CONSTRAINT FK_F73FBF9BDD842E46');
        $this->addSql('ALTER TABLE position_tag DROP CONSTRAINT FK_F73FBF9BBAD26311');
        $this->addSql('DROP TABLE position_tag');
    }
}