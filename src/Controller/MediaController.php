<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\MediaPresignRequestDTO;
use App\Service\Media\PresignedUploadService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Direct-upload credentials for avatar images (no file proxying). */
final class MediaController extends AbstractController
{
    #[Route('/api/media/presign', name: 'media_presign', methods: ['POST'])]
    #[IsGranted('ROLE_CANDIDATE')]
    public function presign(
        Request $request,
        #[MapRequestPayload] MediaPresignRequestDTO $dto,
        PresignedUploadService $presignedUploadService,
        #[Autowire(service: 'limiter.media_presign_user')] RateLimiterFactory $userLimiter,
        #[Autowire(service: 'limiter.media_presign_ip')] RateLimiterFactory $ipLimiter,
        LoggerInterface $logger,
    ): JsonResponse
    {
        $userKey = $this->getUser()?->getUserIdentifier();
        $ipKey = $request->getClientIp() ?? 'unknown';
        $userLimit = $userLimiter->create($userKey ?? 'unknown')->consume(1);
        $ipLimit = $ipLimiter->create($ipKey)->consume(1);

        if (!$userLimit->isAccepted() || !$ipLimit->isAccepted()) {
            $retryAfter = max(
                $userLimit->getRetryAfter()->getTimestamp(),
                $ipLimit->getRetryAfter()->getTimestamp(),
            ) - time();
            $logger->warning('Cloudinary presign rate limit exceeded.', [
                'user' => $userKey,
                'ip' => $ipKey,
                'retry_after' => max(0, $retryAfter),
            ]);

            throw new TooManyRequestsHttpException(max(0, $retryAfter));
        }

        $presigned = $presignedUploadService->generatePresignedUpload($dto->filename, $dto->mimeType, $dto->sizeBytes);

        return new JsonResponse([
            'url' => $presigned->url,
            'fields' => $presigned->fields,
            'expiresAt' => $presigned->expiresAt,
        ], Response::HTTP_OK);
    }
}
