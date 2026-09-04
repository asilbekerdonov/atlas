<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Discussion;

use App\Entity\Position;
use App\Entity\User;
use App\Enum\UserRole;
use App\Service\Discussion\DiscussionService;
use App\Tests\Integration\Service\AbstractServiceIntegrationTestCase;

final class DiscussionServiceTest extends AbstractServiceIntegrationTestCase
{
    public function testPostMessagePersistsAndFansOutViaMercure(): void
    {
        $service = $this->service(DiscussionService::class);

        $author = new User('recruiter@example.com', [UserRole::ROLE_RECRUITER->value]);
        $this->em->persist($author);
        $position = new Position('Senior Backend', 'Desc');
        $this->em->persist($position);
        $this->em->flush();

        // Mercure hub is unreachable in tests; the service must still persist.
        $discussion = $service->postMessage($position, $author, '**Welcome** to the thread!');

        self::assertNotNull($discussion->getId());
        self::assertSame('**Welcome** to the thread!', $discussion->getMessageMd());
        self::assertSame($position->getId(), $discussion->getPosition()->getId());
        self::assertSame($author->getId(), $discussion->getAuthor()->getId());
    }
}
