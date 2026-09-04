<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\AttributeCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base class for service tests running against the real PostgreSQL test
 * database. Every test runs inside a transaction that is rolled back.
 */
abstract class AbstractServiceIntegrationTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback();
        }
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    protected function service(string $class): object
    {
        return self::getContainer()->get($class);
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
}
