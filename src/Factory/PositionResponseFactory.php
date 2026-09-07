<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Position;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PositionResponseFactory
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Создает ответ после создания/обновления позиции
     */
    public function creationResponse(bool $wantsJson, Position $position): Response
    {
        if ($wantsJson) {
            return new JsonResponse([
                'success' => true,
                'id' => $position->getId(),
                'redirectUrl' => $this->urlGenerator->generate(
                    'position_show',
                    ['id' => $position->getId()]
                ),
            ]);
        }

        return new Response(
            null,
            Response::HTTP_FOUND,
            ['Location' => $this->urlGenerator->generate(
                'position_show',
                ['id' => $position->getId()]
            )]
        );
    }

    /**
     * Создает ответ при ошибке валидации
     */
    public function validationFailureResponse(bool $wantsJson, string $errors, string $fallbackRoute, array $params = []): Response
    {
        if ($wantsJson) {
            return new JsonResponse([
                'success' => false,
                'errors' => $errors,
                'message' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // В реальном проекте используйте flash-сообщения
        return new Response(
            null,
            Response::HTTP_FOUND,
            ['Location' => $this->urlGenerator->generate($fallbackRoute, $params)]
        );
    }
    
    /**
     * Создает ответ при конфликте версий (optimistic lock)
     */
    public function conflictResponse(bool $wantsJson, int $currentVersion, Position $position): Response
    {
        if ($wantsJson) {
            return new JsonResponse([
                'success' => false,
                'error' => 'conflict',
                'serverVersion' => $currentVersion,
            ], Response::HTTP_CONFLICT);
        }

        // В реальном проекте используйте flash-сообщения
        return new Response(
            null,
            Response::HTTP_FOUND,
            ['Location' => $this->urlGenerator->generate(
                'position_edit',
                ['id' => $position->getId()]
            )]
        );
    }
}