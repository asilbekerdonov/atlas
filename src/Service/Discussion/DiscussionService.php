<?php

declare(strict_types=1);

namespace App\Service\Discussion;

use App\Entity\Discussion;
use App\Entity\Position;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Discussion posts with real-time fan-out through Mercure. A failing hub must
 * never break the message itself — it is only logged.
 */
class DiscussionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postMessage(Position $position, User $author, string $messageMd): Discussion
    {
        $discussion = new Discussion($position, $author, $messageMd);
        $this->em->persist($discussion);
        $this->em->flush();

        $topic = sprintf('https://cv-platform/positions/%d/discussions', $position->getId());
        $payload = json_encode([
            'id' => $discussion->getId(),
            'positionId' => $position->getId(),
            'authorId' => $author->getId(),
            'messageMd' => $messageMd,
            'createdAt' => $discussion->getCreatedAt()->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);

        try {
            $this->hub->publish(new Update($topic, $payload));
        } catch (Throwable $e) {
            $this->logger->warning('Failed to publish discussion message to Mercure hub.', [
                'topic' => $topic,
                'exception' => $e,
            ]);
        }

        return $discussion;
    }
}
