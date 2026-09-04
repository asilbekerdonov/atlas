<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\AttributeCategory;
use App\Entity\CandidateProfile;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for functional tests: boots the kernel, opens a DB transaction
 * (rolled back after each test) and exposes fixture helpers.
 */
abstract class AbstractFunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // Keep the kernel (and the fixture transaction) alive across multiple
        // requests within one test; the default reboot would roll it back.
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback();
        }
        parent::tearDown();
    }

    protected function createUser(string $email, UserRole $role): User
    {
        $user = new User($email, [$role->value]);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function createProfile(User $user, string $firstName = 'Jane', string $lastName = 'Doe'): CandidateProfile
    {
        $profile = new CandidateProfile($user, $firstName, $lastName);
        $this->em->persist($profile);
        $this->em->flush();

        return $profile;
    }

    protected function createPosition(): Position
    {
        $position = new Position('Senior Backend', 'PHP + PostgreSQL');
        $this->em->persist($position);
        $this->em->flush();

        return $position;
    }

    /** Fetches or creates the named lookup category (shared seed rows). */
    protected function category(string $name): AttributeCategory
    {
        $category = $this->em->getRepository(AttributeCategory::class)->findOneBy(['name' => $name]);
        if ($category === null) {
            $category = new AttributeCategory($name);
            $this->em->persist($category);
            $this->em->flush();
        }

        return $category;
    }

    /** @param array<string, mixed> $payload */
    protected function jsonRequest(KernelBrowser $client, string $method, string $uri, array $payload): \Symfony\Component\HttpFoundation\Response
    {
        $client->request($method, $uri, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));

        return $client->getResponse();
    }
}
