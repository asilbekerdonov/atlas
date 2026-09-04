<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Free-form project tag.
 *
 * The case-insensitive uniqueness is enforced by a functional index on
 * LOWER(name), created in the migration (Doctrine cannot express it).
 */
#[ORM\Entity]
#[ORM\Table(name: 'tag')]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 50)]
    private string $name;

    /** @var Collection<int, Project> */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'tags')]
    private Collection $projects;

    /** @var Collection<int, Position> */
    #[ORM\ManyToMany(targetEntity: Position::class, mappedBy: 'tags')]
    private Collection $positions;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->projects = new ArrayCollection();
        $this->positions = new ArrayCollection();
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

    /** @return Collection<int, Project> */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    /** @return Collection<int, Position> */
    public function getPositions(): Collection
    {
        return $this->positions;
    }
}
