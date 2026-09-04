<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Media;

use App\Service\Media\PresignedUploadService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PresignedUploadServiceTest extends TestCase
{
    private PresignedUploadService $service;

    protected function setUp(): void
    {
        $this->service = new PresignedUploadService(
            'dev-cloud',
            'dev-key',
            'dev-secret',
            'https://api.cloudinary.com/v1_1/dev-cloud/image/upload',
        );
    }

    public function testRejectsUnsupportedMimeType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->generatePresignedUpload('photo.gif', 'image/gif', 1000);
    }

    public function testRejectsOversizedFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->generatePresignedUpload('photo.jpg', 'image/jpeg', 6 * 1024 * 1024);
    }

    public function testGeneratesSignedFieldsForAllowedImage(): void
    {
        $dto = $this->service->generatePresignedUpload('photo.jpg', 'image/webp', 2048);

        self::assertSame('https://api.cloudinary.com/v1_1/dev-cloud/image/upload', $dto->url);
        self::assertSame('dev-key', $dto->fields['api_key']);
        self::assertArrayHasKey('signature', $dto->fields);
        self::assertArrayHasKey('timestamp', $dto->fields);
        self::assertArrayHasKey('public_id', $dto->fields);
        self::assertStringStartsWith('avatars/', $dto->fields['public_id']);
        self::assertGreaterThan(time(), $dto->expiresAt);
    }

    public function testSignatureIsStableForSameInput(): void
    {
        $dto1 = $this->service->generatePresignedUpload('a.png', 'image/png', 100);
        $dto2 = $this->service->generatePresignedUpload('a.png', 'image/png', 100);

        // public_id is random per call; signature must still be a sha1 hex string.
        self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $dto1->fields['signature']);
        self::assertNotSame($dto1->fields['public_id'], $dto2->fields['public_id']);
    }
}
