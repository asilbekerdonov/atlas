<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\MediaPresignRequestDTO;
use App\Service\Media\PresignedUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/** Direct-upload credentials for avatar images (no file proxying). */
final class MediaController extends AbstractController
{
    #[Route('/api/media/presign', name: 'media_presign', methods: ['POST'])]
    public function presign(#[MapRequestPayload] MediaPresignRequestDTO $dto, PresignedUploadService $presignedUploadService): JsonResponse
    {
        $presigned = $presignedUploadService->generatePresignedUpload($dto->filename, $dto->mimeType, $dto->sizeBytes);

        return new JsonResponse([
            'url' => $presigned->url,
            'fields' => $presigned->fields,
            'expiresAt' => $presigned->expiresAt,
        ], Response::HTTP_OK);
    }
}
