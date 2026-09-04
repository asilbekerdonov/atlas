<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CandidateProfile;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\UserRole;
use DateTimeImmutable;

final class ProjectApiTest extends AbstractFunctionalTestCase
{
    private function candidate(string $email = 'candidate@example.com'): User
    {
        $user = $this->createUser($email, UserRole::ROLE_CANDIDATE);
        $profile = new CandidateProfile($user, 'Jane', 'Doe');
        $this->em->persist($profile);
        $this->em->flush();

        return $user;
    }

    private function project(User $candidate, string $name = 'ETL Pipeline', string $start = '2024-01-15'): Project
    {
        $project = new Project(
            $candidate->getProfile(),
            $name,
            new DateTimeImmutable($start),
            'Warehouse pipeline',
        );
        $this->em->persist($project);
        $this->em->flush();

        return $project;
    }

    public function testListReturnsOwnProjectsWithTags(): void
    {
        $candidate = $this->candidate();
        $tag = new Tag('Python');
        $this->em->persist($tag);
        $project = $this->project($candidate);
        $project->addTag($tag);
        $this->em->flush();

        $this->client->loginUser($candidate);
        $this->client->request('GET', '/api/projects', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $data);
        self::assertSame($project->getId(), $data[0]['id']);
        self::assertSame(['Python'], $data[0]['tags']);
        self::assertSame(1, $data[0]['version']);
    }

    public function testCreateReturns201WithView(): void
    {
        $candidate = $this->candidate();
        $this->client->loginUser($candidate);

        $response = $this->jsonRequest($this->client, 'POST', '/api/projects', [
            'name' => 'Churn Model',
            'startDate' => '2023-02-01',
            'endDate' => null,
            'descriptionMd' => 'R + survival',
            'tags' => ['R', 'Statistics'],
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Churn Model', $data['name']);
        self::assertSame(['R', 'Statistics'], $data['tags']);
        self::assertNotNull($this->em->find(Project::class, $data['id']));
    }

    public function testUpdateHappyPathReturnsNewVersion(): void
    {
        $candidate = $this->candidate();
        $project = $this->project($candidate);
        $this->client->loginUser($candidate);

        $response = $this->jsonRequest($this->client, 'PATCH', '/api/projects/' . $project->getId(), [
            'name' => 'Renamed',
            'startDate' => '2024-01-15',
            'endDate' => '2024-12-31',
            'descriptionMd' => 'New desc',
            'tags' => ['SQL'],
            'expectedVersion' => $project->getVersion(),
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Renamed', $data['name']);
        self::assertSame(['SQL'], $data['tags']);
        self::assertSame(2, $data['version']);
    }

    public function testUpdateStaleVersionReturns409Json(): void
    {
        $candidate = $this->candidate();
        $project = $this->project($candidate);
        $this->client->loginUser($candidate);

        $response = $this->jsonRequest($this->client, 'PATCH', '/api/projects/' . $project->getId(), [
            'name' => 'Renamed',
            'startDate' => '2024-01-15',
            'descriptionMd' => 'x',
            'expectedVersion' => 99,
        ]);

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('conflict', $data['error']);
        self::assertSame(1, $data['serverVersion']);
    }

    public function testUpdateWithoutVersionReturns422(): void
    {
        $candidate = $this->candidate();
        $project = $this->project($candidate);
        $this->client->loginUser($candidate);

        $response = $this->jsonRequest($this->client, 'PATCH', '/api/projects/' . $project->getId(), [
            'name' => 'Renamed',
            'startDate' => '2024-01-15',
            'descriptionMd' => 'x',
        ]);

        self::assertResponseStatusCodeSame(422, 'missing expectedVersion must fail validation, not silently overwrite');
    }

    public function testUpdateForeignProjectReturns403(): void
    {
        $owner = $this->candidate('owner@example.com');
        $project = $this->project($owner);

        $intruder = $this->candidate('intruder@example.com');
        $this->client->loginUser($intruder);

        $response = $this->jsonRequest($this->client, 'PATCH', '/api/projects/' . $project->getId(), [
            'name' => 'Hacked',
            'startDate' => '2024-01-15',
            'descriptionMd' => 'x',
            'expectedVersion' => 1,
        ]);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertSame('ETL Pipeline', $this->em->find(Project::class, $project->getId())->getName());
    }

    public function testDeleteRemovesOwnProject(): void
    {
        $candidate = $this->candidate();
        $project = $this->project($candidate);
        $id = $project->getId();
        $this->client->loginUser($candidate);

        $this->client->request('DELETE', '/api/projects/' . $id, [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(204);
        $this->em->clear();
        self::assertNull($this->em->find(Project::class, $id));
    }

    public function testDeleteForeignProjectReturns403(): void
    {
        $owner = $this->candidate('owner@example.com');
        $project = $this->project($owner);

        $intruder = $this->candidate('intruder@example.com');
        $this->client->loginUser($intruder);
        $this->client->request('DELETE', '/api/projects/' . $project->getId(), [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNotNull($this->em->find(Project::class, $project->getId()));
    }
}
