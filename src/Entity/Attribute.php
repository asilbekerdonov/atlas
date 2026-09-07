<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttributeDataType;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A reusable attribute definition from the attribute library
 * (e.g. "English level", "Years of experience", "GitHub link").
 */
#[ORM\Entity]
#[ORM\Table(name: 'attribute')]
class Attribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 100, unique: true)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: AttributeCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private AttributeCategory $category;

    #[ORM\Column(length: 40, enumType: AttributeDataType::class)]
    private AttributeDataType $dataType;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isBuiltIn = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, AttributeOption> */
    #[ORM\OneToMany(targetEntity: AttributeOption::class, mappedBy: 'attribute', cascade: ['persist'], orphanRemoval: true)]
    private Collection $options;

    public function __construct(
        string $name,
        AttributeCategory $category,
        AttributeDataType $dataType,
        ?string $description = null,
    ) {
        $this->name = $name;
        $this->category = $category;
        $this->dataType = $dataType;
        $this->description = $description;
        $this->createdAt = new DateTimeImmutable();
        $this->options = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCategory(): AttributeCategory
    {
        return $this->category;
    }

    public function setCategory(AttributeCategory $category): void
    {
        $this->category = $category;
    }

    public function getDataType(): AttributeDataType
    {
        return $this->dataType;
    }

    /**
     * Changes the data type. Callers must guarantee no candidate values exist
     * yet — otherwise typed columns would be corrupted (see
     * AttributeLibraryService::updateAttribute).
     */
    public function changeDataType(AttributeDataType $dataType): void
    {
        $this->dataType = $dataType;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function isBuiltIn(): bool
    {
        return $this->isBuiltIn;
    }
    
    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, AttributeOption> */
    public function getOptions(): Collection
    {
        return $this->options;
    }
}
