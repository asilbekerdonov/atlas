<?php

declare(strict_types=1);

namespace App\DTO;

/** Presigned direct-upload parameters for the image storage (Cloudinary). */
final readonly class PresignedUploadDTO
{
    /**
     * @param array<string, string> $fields signed upload parameters
     */
    public function __construct(
        public string $url,
        public array $fields,
        public int $expiresAt,
    ) {
    }
}
