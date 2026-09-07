<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Format;
/**
 * A job position recruiters publish; candidates attach a CV to it.
 *
 * deletedAt implements soft delete: rows are never physically removed.
 */
#[ORM\Entity]
#[ORM\Table(name: 'position')]
#[ORM\Index(name: 'idx_position_search_vector', columns: ['search_vector'])]
class Position
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $shortDescription;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $level = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPublic = false;

    /**
     * Recruiter who created the position (data isolation: each recruiter only
     * sees/edits their own positions; NULL marks legacy shared positions).
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(options: ['default' => 4])]
    private int $maxProjects = 4;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: 'string', enumType: Format::class, nullable: true)]
    private ?Format $format = null; 
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /**
     * PostgreSQL FTS vector, kept in sync by a database trigger. Read-only for
     * the ORM — Doctrine never writes to it.
     */
    #[ORM\Column(type: 'tsvector', nullable: true, insertable: false, updatable: false)]
    private ?string $searchVector = null;

    /** @var Collection<int, PositionTemplateAttribute> */
    #[ORM\OneToMany(targetEntity: PositionTemplateAttribute::class, mappedBy: 'position', cascade: ['persist'], orphanRemoval: true)]
    private Collection $templateAttributes;

    /** @var Collection<int, PositionAccessRule> */
    #[ORM\OneToMany(targetEntity: PositionAccessRule::class, mappedBy: 'position', cascade: ['persist'], orphanRemoval: true)]
    private Collection $accessRules;

    /** @var Collection<int, Cv> */
    #[ORM\OneToMany(targetEntity: Cv::class, mappedBy: 'position')]
    private Collection $cvs;

    /** @var Collection<int, Discussion> */
    #[ORM\OneToMany(targetEntity: Discussion::class, mappedBy: 'position')]
    private Collection $discussions;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'positions')]
    #[ORM\JoinTable(
        name: 'position_tag',
        joinColumns: [new ORM\JoinColumn(name: 'position_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'tag_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
    )]
    private Collection $tags;

    public function __construct(string $title, string $shortDescription)
    {
        $this->title = $title;
        $this->shortDescription = $shortDescription;
        $this->createdAt = new DateTimeImmutable();
        $this->templateAttributes = new ArrayCollection();
        $this->accessRules = new ArrayCollection();
        $this->cvs = new ArrayCollection();
        $this->discussions = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): void
    {
        $this->shortDescription = $shortDescription;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): void
    {
        $this->companyName = $companyName;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): void
    {
        $this->level = $level;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setPublic(bool $isPublic): void
    {
        $this->isPublic = $isPublic;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getMaxProjects(): int
    {
        return $this->maxProjects;
    }

    public function setMaxProjects(int $maxProjects): void
    {
        $this->maxProjects = $maxProjects;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): void
    {
        $this->deletedAt = new DateTimeImmutable();
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, PositionTemplateAttribute> */
    public function getTemplateAttributes(): Collection
    {
        return $this->templateAttributes;
    }

    /** @return Collection<int, PositionAccessRule> */
    public function getAccessRules(): Collection
    {
        return $this->accessRules;
    }

    /** @return Collection<int, Cv> */
    public function getCvs(): Collection
    {
        return $this->cvs;
    }

    /** @return Collection<int, Discussion> */
    public function getDiscussions(): Collection
    {
        return $this->discussions;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): void
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
    }

    public function removeTag(Tag $tag): void
    {
        $this->tags->removeElement($tag);
    }
      public function getFormat(): ?Format
    {
        return $this->format;
    }

    public function setFormat(?Format $format): self
    {
        $this->format = $format;
        return $this;
    }
}
