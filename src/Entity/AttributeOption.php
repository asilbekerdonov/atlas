<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One selectable option of a ONE_OF_MANY attribute.
 * Ordered by sortOrder; value is what gets persisted, label is what gets shown.
 */
#[ORM\Entity]
#[ORM\Table(name: 'attribute_option')]
#[ORM\Index(name: 'idx_attribute_option_attribute', columns: ['attribute_id'])]
class AttributeOption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Attribute::class, inversedBy: 'options')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Attribute $attribute;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(length: 255)]
    private string $value;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    public function __construct(Attribute $attribute, string $label, string $value, int $sortOrder = 0)
    {
        $this->attribute = $attribute;
        $this->label = $label;
        $this->value = $value;
        $this->sortOrder = $sortOrder;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
