<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Enum\UserRole;

/**
 * Admin panel role management: promote (candidate -> recruiter) and demote
 * (recruiter -> candidate). Administrator accounts and one's own role are
 * protected; non-admins must not reach the routes at all.
 */
final class AdminRoleChangeTest extends AbstractFunctionalTestCase
{
    private function admin(): User
    {
        $admin = $this->createUser('boss@platform.local', UserRole::ROLE_ADMIN);
        $this->client->loginUser($admin);

        return $admin;
    }

    /** Reloads a user from the DB inside the shared test transaction. */
    private function reload(User $user): User
    {
        $this->em->clear();

        return $this->em->getRepository(User::class)->find($user->getId());
    }

    public function testAdminCanPromoteCandidateToRecruiter(): void
    {
        $this->admin();
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);

        $this->client->request('POST', '/admin/users/' . $candidate->getId() . '/promote');

        self::assertResponseRedirects('/admin/users');
        $fresh = $this->reload($candidate);
        self::assertTrue($fresh->hasRole(UserRole::ROLE_RECRUITER), 'promoted user must be a recruiter');
        self::assertFalse($fresh->hasRole(UserRole::ROLE_CANDIDATE), 'candidate role must be removed on promote');
    }

    public function testAdminCanDemoteRecruiterToCandidate(): void
    {
        $this->admin();
        $recruiter = $this->createUser('recruiter@example.com', UserRole::ROLE_RECRUITER);

        $this->client->request('POST', '/admin/users/' . $recruiter->getId() . '/demote');

        self::assertResponseRedirects('/admin/users');
        $fresh = $this->reload($recruiter);
        self::assertTrue($fresh->hasRole(UserRole::ROLE_CANDIDATE), 'demoted user must be a candidate');
        self::assertFalse($fresh->hasRole(UserRole::ROLE_RECRUITER), 'recruiter role must be removed on demote');
    }

    public function testAdminCannotChangeAnotherAdminRole(): void
    {
        $this->admin();
        $otherAdmin = $this->createUser('peer@platform.local', UserRole::ROLE_ADMIN);

        $this->client->request('POST', '/admin/users/' . $otherAdmin->getId() . '/promote');

        self::assertResponseRedirects('/admin/users');
        $fresh = $this->reload($otherAdmin);
        self::assertTrue($fresh->hasRole(UserRole::ROLE_ADMIN), 'admin role must stay untouched');
    }

    public function testPromoteRejectsUserWhoIsNotCandidate(): void
    {
        $this->admin();
        $recruiter = $this->createUser('already@example.com', UserRole::ROLE_RECRUITER);

        $this->client->request('POST', '/admin/users/' . $recruiter->getId() . '/promote');

        self::assertResponseRedirects('/admin/users');
        $fresh = $this->reload($recruiter);
        self::assertTrue($fresh->hasRole(UserRole::ROLE_RECRUITER), 'recruiter must not change on invalid promote');
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        $admin = $this->admin();

        $this->client->request('POST', '/admin/users/' . $admin->getId() . '/demote');

        self::assertResponseRedirects('/admin/users');
        $fresh = $this->reload($admin);
        self::assertTrue($fresh->hasRole(UserRole::ROLE_ADMIN), 'admin cannot demote himself');
    }

    public function testNonAdminCannotAccessPromoteRoute(): void
    {
        $candidate = $this->createUser('intruder@example.com', UserRole::ROLE_CANDIDATE);
        $this->client->loginUser($candidate);
        $target = $this->createUser('victim@example.com', UserRole::ROLE_CANDIDATE);

        $this->client->request('POST', '/admin/users/' . $target->getId() . '/promote');

        self::assertResponseStatusCodeSame(403);
    }

    public function testUsersPageShowsRoleButtonsDisabledForAdmins(): void
    {
        $this->admin();
        $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $this->createUser('peer@platform.local', UserRole::ROLE_ADMIN);

        $this->client->request('GET', '/admin/users');
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('data-action="promote"', $html);
        self::assertStringContainsString('data-action="demote"', $html);
        // Admins must never match the role gates (ROLE_CANDIDATE / ROLE_RECRUITER).
        self::assertStringContainsString('data-role="ROLE_ADMIN"', $html);
        self::assertStringContainsString('data-role="ROLE_CANDIDATE"', $html);
    }
}
