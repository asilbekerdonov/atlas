<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Profile of a candidate. Version is bumped on every change so concurrent
 * autosaves can be detected via optimistic locking.
 */
#[ORM\Entity]
#[ORM\Table(name: 'candidate_profile')]
#[ORM\HasLifecycleCallbacks]
class CandidateProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'profile')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, CandidateAttributeValue> */
    #[ORM\OneToMany(targetEntity: CandidateAttributeValue::class, mappedBy: 'profile')]
    private Collection $attributeValues;

    /** @var Collection<int, Project> */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'profile')]
    private Collection $projects;

    public function __construct(User $user, string $firstName, string $lastName)
    {
        $this->user = $user;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->updatedAt = new DateTimeImmutable();
        $this->attributeValues = new ArrayCollection();
        $this->projects = new ArrayCollection();

        // Keep the inverse OneToOne link consistent in memory, so
        // $user->getProfile() works right after construction.
        $user->setProfile($this);
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Marks the profile as changed when only child rows (attribute values)
     * are touched — the optimistic-lock version then bumps on flush even
     * though no scalar profile field changed.
     */
    public function markChanged(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): void
    {
        $this->avatarUrl = $avatarUrl;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, CandidateAttributeValue> */
    public function getAttributeValues(): Collection
    {
        return $this->attributeValues;
    }

    /** @return Collection<int, Project> */
    public function getProjects(): Collection
    {
        return $this->projects;
    }
}
