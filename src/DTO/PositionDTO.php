<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\Format;
use App\Enum\Level;

/**
 * Input for creating/updating a position.
 *
 * @phpstan-type PositionTemplateAttributeDTOList list<PositionTemplateAttributeDTO>
 * @phpstan-type PositionAccessRuleDTOList list<PositionAccessRuleDTO>
 */
final readonly class PositionDTO
{
    /**
     * @param list<PositionTemplateAttributeDTO> $templateAttributes
     * @param list<PositionAccessRuleDTO>        $accessRules
     * @param list<string>                       $tags
     */
    public function __construct(
        public string $title,
        public string $shortDescription,
        public ?string $companyName = null,
        public ?Level $level = null,
        public ?Format $format = null,
        public bool $isPublic = false,
        public int $maxProjects = 4,
        public array $templateAttributes = [],
        public array $accessRules = [],
        public array $tags = [],
    ) {
    }
}
