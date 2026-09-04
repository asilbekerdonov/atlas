<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A comment in the discussion thread of a position.
 * messageMd is rendered as sanitized Markdown (never raw HTML).
 */
#[ORM\Entity]
#[ORM\Table(name: 'discussion')]
#[ORM\Index(name: 'idx_discussion_position', columns: ['position_id'])]
class Discussion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Position::class, inversedBy: 'discussions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(type: Types::TEXT)]
    private string $messageMd;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(Position $position, User $author, string $messageMd)
    {
        $this->position = $position;
        $this->author = $author;
        $this->messageMd = $messageMd;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function getMessageMd(): string
    {
        return $this->messageMd;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
