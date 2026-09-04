<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Lookup row for an attribute-library category (replaces the former enum).
 *
 * Case-sensitive unique on name is a DELIBERATE deviation from the project
 * convention (tags use a LOWER(name) index): categories are written only by
 * migration/seed, never free-form user input, so case-insensitivity buys
 * nothing here.
 *
 * No optimistic-locking version: rows are only changed via migrations, there
 * is no user-facing edit path that could produce write conflicts.
 */
#[ORM\Entity]
#[ORM\Table(name: 'attribute_category')]
#[ORM\UniqueConstraint(name: 'uniq_attribute_category_name', columns: ['name'])]
class AttributeCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 100)]
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
