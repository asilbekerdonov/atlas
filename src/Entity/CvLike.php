<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A like given by a recruiter to a CV. A recruiter can like a CV only once.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cv_like')]
#[ORM\UniqueConstraint(name: 'uniq_cv_like_cv_recruiter', columns: ['cv_id', 'recruiter_id'])]
class CvLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Cv::class, inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Cv $cv;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recruiter;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(Cv $cv, User $recruiter)
    {
        $this->cv = $cv;
        $this->recruiter = $recruiter;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCv(): Cv
    {
        return $this->cv;
    }

    public function getRecruiter(): User
    {
        return $this->recruiter;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
