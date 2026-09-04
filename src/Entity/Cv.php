<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CvStatus;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A candidate's CV attached to one position.
 *
 * likesCount is denormalized and must be kept in sync via CvLike,
 * so list screens can sort by popularity without counting rows.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cv')]
#[ORM\UniqueConstraint(name: 'uniq_cv_candidate_position', columns: ['candidate_id', 'position_id'])]
#[ORM\HasLifecycleCallbacks]
class Cv
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'cvs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $candidate;

    #[ORM\ManyToOne(targetEntity: Position::class, inversedBy: 'cvs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Position $position;

    #[ORM\Column(length: 20, enumType: CvStatus::class, options: ['default' => CvStatus::DRAFT->value])]
    private CvStatus $status = CvStatus::DRAFT;

    #[ORM\Column(options: ['default' => true])]
    private bool $isVisible = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $likesCount = 0;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /**
     * When a recruiter last opened this CV (or its position's CV list).
     * NULL means "not seen yet" — used by the "N new" notification badge.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $viewedByRecruiterAt = null;

    /** @var Collection<int, CvLike> */
    #[ORM\OneToMany(targetEntity: CvLike::class, mappedBy: 'cv')]
    private Collection $likes;

    public function __construct(User $candidate, Position $position)
    {
        $this->candidate = $candidate;
        $this->position = $position;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->likes = new ArrayCollection();
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

    public function getCandidate(): User
    {
        return $this->candidate;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function getStatus(): CvStatus
    {
        return $this->status;
    }

    public function publish(): void
    {
        $this->status = CvStatus::PUBLISHED;
    }

    public function unpublish(): void
    {
        $this->status = CvStatus::DRAFT;
    }

    public function isVisible(): bool
    {
        return $this->isVisible;
    }

    public function setVisible(bool $isVisible): void
    {
        $this->isVisible = $isVisible;
    }

    public function getLikesCount(): int
    {
        return $this->likesCount;
    }

    public function incrementLikes(): void
    {
        ++$this->likesCount;
    }

    public function decrementLikes(): void
    {
        $this->likesCount = max(0, $this->likesCount - 1);
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markViewedByRecruiter(): void
    {
        $this->viewedByRecruiterAt ??= new DateTimeImmutable();
    }

    public function isViewedByRecruiter(): bool
    {
        return $this->viewedByRecruiterAt !== null;
    }

    /** @return Collection<int, CvLike> */
    public function getLikes(): Collection
    {
        return $this->likes;
    }
}
