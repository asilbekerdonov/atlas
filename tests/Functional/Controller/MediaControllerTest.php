<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Enum\UserRole;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class MediaControllerTest extends AbstractFunctionalTestCase
{
    public function testPresignRequiresCandidateRole(): void
    {
        $this->jsonRequest($this->client, 'POST', '/api/media/presign', [
            'filename' => 'avatar.jpg',
            'mimeType' => 'image/jpeg',
            'sizeBytes' => 1024,
        ]);

        self::assertResponseStatusCodeSame(403);

        $recruiter = $this->createUser('media-recruiter@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);
        $this->jsonRequest($this->client, 'POST', '/api/media/presign', [
            'filename' => 'avatar.jpg',
            'mimeType' => 'image/jpeg',
            'sizeBytes' => 1024,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testPresignLimiterAllowsTenRequestsPerKey(): void
    {
        $limiter = self::getContainer()->get('limiter.media_presign_user');
        self::assertInstanceOf(RateLimiterFactory::class, $limiter);

        $key = 'media-test-' . bin2hex(random_bytes(8));
        for ($attempt = 1; $attempt <= 10; ++$attempt) {
            self::assertTrue($limiter->create($key)->consume(1)->isAccepted());
        }

        self::assertFalse($limiter->create($key)->consume(1)->isAccepted());
    }
}
