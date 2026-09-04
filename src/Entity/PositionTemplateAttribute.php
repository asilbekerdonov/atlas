<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Links an attribute to a position template: candidates must fill in
 * the required attributes when attaching a CV to this position.
 */
#[ORM\Entity]
#[ORM\Table(name: 'position_template_attribute')]
#[ORM\Index(name: 'idx_position_template_attribute_position', columns: ['position_id'])]
class PositionTemplateAttribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Position::class, inversedBy: 'templateAttributes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Attribute $attribute;

    #[ORM\Column(options: ['default' => false])]
    private bool $isRequired = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    public function __construct(Position $position, Attribute $attribute, bool $isRequired = false, int $sortOrder = 0)
    {
        $this->position = $position;
        $this->attribute = $attribute;
        $this->isRequired = $isRequired;
        $this->sortOrder = $sortOrder;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
