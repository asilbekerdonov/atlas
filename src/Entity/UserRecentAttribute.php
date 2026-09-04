<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks the last time a user attached an attribute in a given context,
 * so the UI can suggest the most recently used attributes first.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_recent_attribute')]
#[ORM\Index(name: 'idx_user_recent_attribute_user_last_used', columns: ['user_id', 'last_used_at'])]
class UserRecentAttribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Attribute $attribute;

    /** Where the attribute was used: 'PROFILE' or 'POSITION'. */
    #[ORM\Column(length: 20)]
    private string $context;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastUsedAt;

    public function __construct(User $user, Attribute $attribute, string $context)
    {
        $this->user = $user;
        $this->attribute = $attribute;
        $this->context = $context;
        $this->lastUsedAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getLastUsedAt(): DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    /** Marks the attribute as used right now (upserted by the service layer). */
    public function markUsed(): void
    {
        $this->lastUsedAt = new DateTimeImmutable();
    }

    public function setContext(string $context): void
    {
        $this->context = $context;
    }
}
