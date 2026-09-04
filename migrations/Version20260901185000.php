<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Full-text search for positions: tsvector column, GIN index and a trigger
 * that keeps the vector in sync with title/short_description/company_name.
 */
final class Version20260901185000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add PostgreSQL FTS (tsvector + GIN) for positions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position ADD COLUMN search_vector tsvector');
        $this->addSql("UPDATE position SET search_vector = to_tsvector('english', coalesce(title, '') || ' ' || coalesce(short_description, '') || ' ' || coalesce(company_name, ''))");
        $this->addSql('CREATE INDEX idx_position_search_vector ON position USING gin(search_vector)');

        $this->addSql(<<<'SQL'
            CREATE FUNCTION position_search_vector_update() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector := to_tsvector('english',
                    coalesce(NEW.title, '') || ' ' ||
                    coalesce(NEW.short_description, '') || ' ' ||
                    coalesce(NEW.company_name, ''));
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql
        SQL);
        $this->addSql('CREATE TRIGGER trg_position_search_vector BEFORE INSERT OR UPDATE ON position FOR EACH ROW EXECUTE FUNCTION position_search_vector_update()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER trg_position_search_vector ON position');
        $this->addSql('DROP FUNCTION position_search_vector_update()');
        $this->addSql('DROP INDEX idx_position_search_vector');
        $this->addSql('ALTER TABLE position DROP COLUMN search_vector');
    }
}
