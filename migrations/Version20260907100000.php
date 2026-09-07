<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Normalise position.level to the Level enum values (JUNIOR..C_LEVEL).
 *
 * The column is already VARCHAR(100), so the schema stays untouched; only
 * legacy title-case labels ('Junior', 'C-Level') written before the enum
 * existed are rewritten to their canonical uppercase form.
 */
final class Version20260907100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert legacy position.level labels to Level enum values';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE position SET level = CASE level
            WHEN 'Junior' THEN 'JUNIOR'
            WHEN 'Middle' THEN 'MIDDLE'
            WHEN 'Senior' THEN 'SENIOR'
            WHEN 'Lead' THEN 'LEAD'
            WHEN 'C-Level' THEN 'C_LEVEL'
            ELSE level END
            WHERE level IN ('Junior', 'Middle', 'Senior', 'Lead', 'C-Level')");
    }

    public function down(Schema $schema): void
    {
        // Reverse mapping: canonical enum values back to the display labels
        // that the form used before the enum existed.
        $this->addSql("UPDATE position SET level = CASE level
            WHEN 'JUNIOR' THEN 'Junior'
            WHEN 'MIDDLE' THEN 'Middle'
            WHEN 'SENIOR' THEN 'Senior'
            WHEN 'LEAD' THEN 'Lead'
            WHEN 'C_LEVEL' THEN 'C-Level'
            ELSE level END
            WHERE level IN ('JUNIOR', 'MIDDLE', 'SENIOR', 'LEAD', 'C_LEVEL')");
    }
}
