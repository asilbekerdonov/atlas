<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserRole;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Platform user: candidate, recruiter or admin.
 *
 * Password is nullable because accounts may be created via OAuth only.
 * "user" is a reserved word in PostgreSQL, hence the explicit quoted table name.
 */
#[ORM\Entity]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    /** @var list<string> Symfony security roles, e.g. ["ROLE_CANDIDATE"] */
    #[ORM\Column(type: Types::JSON)]
    private array $roles;

    #[ORM\Column(options: ['default' => false])]
    private bool $isBlocked = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\OneToOne(mappedBy: 'user')]
    private ?CandidateProfile $profile = null;

    /** @var Collection<int, OAuthIdentity> */
    #[ORM\OneToMany(targetEntity: OAuthIdentity::class, mappedBy: 'user')]
    private Collection $oauthIdentities;

    /** @var Collection<int, Cv> CVs where this user acts as a candidate */
    #[ORM\OneToMany(targetEntity: Cv::class, mappedBy: 'candidate')]
    private Collection $cvs;

    /**
     * @param list<string> $roles
     */
    public function __construct(
        string $email,
        array $roles = [UserRole::ROLE_CANDIDATE->value],
        ?string $password = null,
    ) {
        $this->email = $email;
        $this->roles = $roles;
        $this->password = $password;
        $this->createdAt = new DateTimeImmutable();
        $this->oauthIdentities = new ArrayCollection();
        $this->cvs = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function hasRole(UserRole $role): bool
    {
        return in_array($role->value, $this->roles, true);
    }

    /**
     * Adds a role if it is not present yet (idempotent).
     */
    public function addRole(UserRole $role): void
    {
        if (!$this->hasRole($role)) {
            $this->roles[] = $role->value;
        }
    }

    /**
     * Removes a role if present (idempotent).
     */
    public function removeRole(UserRole $role): void
    {
        $this->roles = array_values(array_filter(
            $this->roles,
            static fn (string $existing): bool => $existing !== $role->value,
        ));
    }

    /**
     * The user's single "main" role, derived from the stored roles list.
     * Admins inherit recruiter/candidate abilities through the security
     * role_hierarchy, so only ROLE_ADMIN itself is ever stored for them.
     */
    public function getPrimaryRole(): UserRole
    {
        return match (true) {
            $this->hasRole(UserRole::ROLE_ADMIN) => UserRole::ROLE_ADMIN,
            $this->hasRole(UserRole::ROLE_RECRUITER) => UserRole::ROLE_RECRUITER,
            default => UserRole::ROLE_CANDIDATE,
        };
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function block(): void
    {
        $this->isBlocked = true;
    }

    public function unblock(): void
    {
        $this->isBlocked = false;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    // --- Symfony security integration -------------------------------------

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
        // No transient credentials to wipe.
    }

    /**
     * Only identity data is serialized into the session; the full user is
     * reloaded by the entity provider on every request. Doctrine collections
     * must never be persisted to the session.
     *
     * @return array{id: int, email: string, roles: list<string>}
     */
    public function __serialize(): array
    {
        return ['id' => $this->id, 'email' => $this->email, 'roles' => $this->roles];
    }

    /**
     * @param array{id: int, email: string, roles: list<string>} $data
     */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->roles = $data['roles'];
    }

    public function getProfile(): ?CandidateProfile
    {
        return $this->profile;
    }

    /** Sets the owning side link; called when a CandidateProfile is created. */
    public function setProfile(?CandidateProfile $profile): void
    {
        $this->profile = $profile;
    }

    /** @return Collection<int, OAuthIdentity> */
    public function getOauthIdentities(): Collection
    {
        return $this->oauthIdentities;
    }

    /** @return Collection<int, Cv> */
    public function getCvs(): Collection
    {
        return $this->cvs;
    }
}
