<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AccessRuleOperator;
use Doctrine\ORM\Mapping as ORM;

/**
 * One access rule of a position: candidates whose attribute value does not
 * match the operator/value combination cannot attach a CV to the position.
 */
#[ORM\Entity]
#[ORM\Table(name: 'position_access_rule')]
#[ORM\Index(name: 'idx_position_access_rule_position', columns: ['position_id'])]
class PositionAccessRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Position::class, inversedBy: 'accessRules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Position $position;

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Attribute $attribute;

    #[ORM\Column(length: 40, enumType: AccessRuleOperator::class)]
    private AccessRuleOperator $operator;

    /** Compared value; empty for value-less operators like IS_CHECKED. */
    #[ORM\Column(length: 255, options: ['default' => ''])]
    private string $ruleValue = '';

    public function __construct(
        Position $position,
        Attribute $attribute,
        AccessRuleOperator $operator,
        string $ruleValue = '',
    ) {
        $this->position = $position;
        $this->attribute = $attribute;
        $this->operator = $operator;
        $this->ruleValue = $ruleValue;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getOperator(): AccessRuleOperator
    {
        return $this->operator;
    }

    public function getRuleValue(): string
    {
        return $this->ruleValue;
    }
}
