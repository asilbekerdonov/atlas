<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A typed value of one attribute for one candidate profile.
 *
 * Exactly one of the typed columns is populated, depending on the
 * attribute's AttributeDataType — never a single generic TEXT column,
 * so the DB enforces the shape of the stored value.
 */
#[ORM\Entity]
#[ORM\Table(name: 'candidate_attribute_value')]
#[ORM\UniqueConstraint(name: 'uniq_candidate_attribute_value_profile', columns: ['profile_id', 'attribute_id'])]
#[ORM\HasLifecycleCallbacks]
class CandidateAttributeValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: CandidateProfile::class, inversedBy: 'attributeValues')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CandidateProfile $profile;

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Attribute $attribute;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $valueString = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $valueText = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $valueNumeric = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $valueDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $valueDateRangeStart = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $valueDateRangeEnd = null;

    #[ORM\Column(nullable: true)]
    private ?bool $valueBoolean = null;

    #[ORM\ManyToOne(targetEntity: AttributeOption::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AttributeOption $valueOption = null;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(CandidateProfile $profile, Attribute $attribute)
    {
        $this->profile = $profile;
        $this->attribute = $attribute;
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getProfile(): CandidateProfile
    {
        return $this->profile;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getValueString(): ?string
    {
        return $this->valueString;
    }

    public function setValueString(?string $valueString): void
    {
        $this->valueString = $valueString;
    }

    public function getValueText(): ?string
    {
        return $this->valueText;
    }

    public function setValueText(?string $valueText): void
    {
        $this->valueText = $valueText;
    }

    public function getValueNumeric(): ?string
    {
        return $this->valueNumeric;
    }

    public function setValueNumeric(?string $valueNumeric): void
    {
        $this->valueNumeric = $valueNumeric;
    }

    public function getValueDate(): ?DateTimeImmutable
    {
        return $this->valueDate;
    }

    public function setValueDate(?DateTimeImmutable $valueDate): void
    {
        $this->valueDate = $valueDate;
    }

    public function getValueDateRangeStart(): ?DateTimeImmutable
    {
        return $this->valueDateRangeStart;
    }

    public function setValueDateRangeStart(?DateTimeImmutable $valueDateRangeStart): void
    {
        $this->valueDateRangeStart = $valueDateRangeStart;
    }

    public function getValueDateRangeEnd(): ?DateTimeImmutable
    {
        return $this->valueDateRangeEnd;
    }

    public function setValueDateRangeEnd(?DateTimeImmutable $valueDateRangeEnd): void
    {
        $this->valueDateRangeEnd = $valueDateRangeEnd;
    }

    public function getValueBoolean(): ?bool
    {
        return $this->valueBoolean;
    }

    public function setValueBoolean(?bool $valueBoolean): void
    {
        $this->valueBoolean = $valueBoolean;
    }

    public function getValueOption(): ?AttributeOption
    {
        return $this->valueOption;
    }

    public function setValueOption(?AttributeOption $valueOption): void
    {
        $this->valueOption = $valueOption;
    }

    /** True when no typed column is populated (attribute not filled in). */
    public function isEmpty(): bool
    {
        return $this->valueString === null
            && $this->valueText === null
            && $this->valueNumeric === null
            && $this->valueDate === null
            && $this->valueDateRangeStart === null
            && $this->valueDateRangeEnd === null
            && $this->valueBoolean === null
            && $this->valueOption === null;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
