<?php

declare(strict_types=1);

namespace App\Mapper;

use App\DTO\Request\PositionRequestDTO;
use App\DTO\PositionAccessRuleDTO;
use App\DTO\PositionDTO;
use App\DTO\PositionTemplateAttributeDTO;
use App\DTO\Request\PositionAccessRuleRequestDTO;
use App\DTO\Request\PositionTemplateAttributeRequestDTO;
use App\Enum\AccessRuleOperator;
use App\Enum\Format;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

class PositionRequestMapper
{
    public function __construct(
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * Преобразует Request в PositionRequestDTO
     */
    public function fromRequest(Request $request): PositionRequestDTO
    {
        $data = $this->normalizeFormTypes($this->rawPayload($request));

        /** @var PositionRequestDTO $dto */
        $dto = $this->serializer->denormalize($data, PositionRequestDTO::class);

        return $dto;
    }

    /**
     * Маппит PositionRequestDTO → PositionDTO (для сервиса)
     */
    public function toPositionDTO(PositionRequestDTO $dto): PositionDTO
    {
        $templateAttributes = array_map(
            fn ($ta): PositionTemplateAttributeDTO => $ta instanceof PositionTemplateAttributeRequestDTO
                ? new PositionTemplateAttributeDTO($ta->attributeId, $ta->isRequired, $ta->sortOrder)
                : new PositionTemplateAttributeDTO(
                    (int) ($ta['attributeId'] ?? 0),
                    (bool) ($ta['isRequired'] ?? false),
                    (int) ($ta['sortOrder'] ?? 0)
                ),
            $dto->templateAttributes,
        );

        $accessRules = array_map(
            fn ($rule): PositionAccessRuleDTO => $rule instanceof PositionAccessRuleRequestDTO
                ? new PositionAccessRuleDTO(
                    $rule->attributeId,
                    $rule->operator instanceof AccessRuleOperator ? $rule->operator : AccessRuleOperator::from($rule->operator),
                    $rule->ruleValue
                )
                : new PositionAccessRuleDTO(
                    (int) ($rule['attributeId'] ?? 0),
                    AccessRuleOperator::from((string) ($rule['operator'] ?? '')),
                    (string) ($rule['ruleValue'] ?? '')
                ),
            $dto->accessRules,
        );

        return new PositionDTO(
            title: $dto->title,
            shortDescription: $dto->shortDescription,
            companyName: $dto->companyName,
            level: $dto->level,
            format: $dto->format !== null && $dto->format !== '' 
                ? Format::tryFrom($dto->format) 
                : null,
            isPublic: $dto->isPublic,
            maxProjects: $dto->maxProjects,
            templateAttributes: $templateAttributes,
            accessRules: $accessRules,
            tags: $dto->tags,
        );
    }

    /**
     * Нормализует данные HTML-формы в типизированные данные
     */
    private function normalizeFormTypes(array $data): array
    {
        $data['isPublic'] = isset($data['isPublic']) 
            ? filter_var($data['isPublic'], FILTER_VALIDATE_BOOLEAN) 
            : false;
        $data['maxProjects'] = isset($data['maxProjects']) 
            ? (int) $data['maxProjects'] 
            : 4;
        $data['templateAttributes'] ??= [];
        $data['accessRules'] ??= [];
        $data['tags'] ??= [];
        $data['title'] ??= '';
        $data['shortDescription'] ??= '';

        return $data;
    }

    /**
     * Извлекает сырой payload из Request (JSON или form-data)
     */
    private function rawPayload(Request $request): array
    {
        return $request->getContentTypeFormat() === 'json'
            ? (json_decode((string) $request->getContent(), true) ?? [])
            : $request->request->all();
    }
}