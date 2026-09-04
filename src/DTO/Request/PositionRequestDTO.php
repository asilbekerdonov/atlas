<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Input for creating/updating a position. */
final class PositionRequestDTO
{
    /**
     * @param list<PositionTemplateAttributeRequestDTO> $templateAttributes
     * @param list<PositionAccessRuleRequestDTO>        $accessRules
     * @param list<string>                              $tags
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 200)]
        public string $title = '',

        #[Assert\NotBlank]
        #[Assert\Length(max: 5000)]
        public string $shortDescription = '',

        #[Assert\Length(max: 255)]
        public ?string $companyName = null,

        #[Assert\Length(max: 100)]
        public ?string $level = null,

        public bool $isPublic = false,

        #[Assert\Range(min: 1, max: 20)]
        public int $maxProjects = 4,

        #[Assert\Valid]
        public array $templateAttributes = [],

        #[Assert\Valid]
        public array $accessRules = [],

        #[Assert\All([new Assert\Length(max: 50)])]
        public array $tags = [],
    ) {
    }
}
