<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Read-only mapping for PostgreSQL's tsvector column: the database trigger
 * maintains the value, Doctrine never writes or hydrates it.
 */
final class TsvectorType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'tsvector';
    }

    public function getName(): string
    {
        return 'tsvector';
    }

    /** @return list<string> */
    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        return ['tsvector'];
    }
}
