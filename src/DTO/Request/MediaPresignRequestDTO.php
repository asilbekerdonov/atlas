<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Presign request: image metadata (browser uploads straight to the CDN). */
final class MediaPresignRequestDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $filename = '',

        #[Assert\NotBlank]
        public string $mimeType = '',

        #[Assert\Range(min: 1, max: 5 * 1024 * 1024)]
        public int $sizeBytes = 0,
    ) {
    }
}
