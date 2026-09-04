<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\DTO\PresignedUploadDTO;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Generates direct-upload credentials for image storage (Cloudinary-style
 * signed upload): the browser uploads straight to the CDN, files never proxy
 * through our web server. In production point the env vars at the real
 * Cloudinary account; signature logic follows Cloudinary's documented
 * "sign params + API secret" scheme.
 */
class PresignedUploadService
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB
    private const TTL_SECONDS = 3600;

    public function __construct(
        #[Autowire(env: 'CLOUDINARY_CLOUD_NAME')] private readonly string $cloudName,
        #[Autowire(env: 'CLOUDINARY_API_KEY')] private readonly string $apiKey,
        #[Autowire(env: 'CLOUDINARY_API_SECRET')] private readonly string $apiSecret,
        #[Autowire(env: 'CLOUDINARY_UPLOAD_URL')] private readonly string $uploadUrl,
    ) {
    }

    public function generatePresignedUpload(string $filename, string $mimeType, int $sizeBytes): PresignedUploadDTO
    {
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported image type "%s"; allowed: %s.',
                $mimeType,
                implode(', ', self::ALLOWED_MIME_TYPES),
            ));
        }

        if ($sizeBytes > self::MAX_SIZE_BYTES) {
            throw new InvalidArgumentException(sprintf('Image exceeds the %d MB limit.', self::MAX_SIZE_BYTES / 1024 / 1024));
        }

        $timestamp = time();
        $publicId = sprintf('avatars/%s-%s', bin2hex(random_bytes(8)), preg_replace('/[^a-z0-9._-]/i', '', $filename));

        $paramsToSign = [
            'public_id' => $publicId,
            'timestamp' => (string) $timestamp,
        ];
        ksort($paramsToSign);
        $signature = sha1(urldecode(http_build_query($paramsToSign)) . $this->apiSecret);

        return new PresignedUploadDTO(
            url: $this->uploadUrl,
            fields: [
                'public_id' => $publicId,
                'timestamp' => (string) $timestamp,
                'api_key' => $this->apiKey,
                'signature' => $signature,
            ],
            expiresAt: $timestamp + self::TTL_SECONDS,
        );
    }
}
