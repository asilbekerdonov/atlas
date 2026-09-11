<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Enum\UserRole;

/**
 * Admin panel role management: promote (candidate -> recruiter), demote
 * (recruiter -> candidate), and revoke-admin. Non-admins must not reach routes.
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

    public function testLastAdminCannotRevokeOwnAdminRole(): void
    {
        $admin = $this->admin();

        $this->client->request('POST', '/admin/users/' . $admin->getId() . '/revoke-admin');

        self::assertResponseRedirects('/admin/users');
        $this->client->followRedirect();
        self::assertStringContainsString('Cannot remove the last administrator.', (string) $this->client->getResponse()->getContent());
        $fresh = $this->reload($admin);
        self::assertTrue($fresh->hasRole(UserRole::ROLE_ADMIN), 'last admin must stay an admin');
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

    public function testAdminCanRemoveOwnAdminRoleWhenAnotherAdminExists(): void
    {
        $admin = $this->admin();
        $this->createUser('peer@platform.local', UserRole::ROLE_ADMIN);

        $this->client->request('POST', '/admin/users/' . $admin->getId() . '/revoke-admin');

        self::assertResponseRedirects('/admin/users');
        $fresh = $this->reload($admin);
        self::assertFalse($fresh->hasRole(UserRole::ROLE_ADMIN), 'admin role must be removable');
        self::assertTrue($fresh->hasRole(UserRole::ROLE_CANDIDATE), 'demoted admin must become a candidate');
    }

    public function testAdminCannotRevokeAnotherAdminsRole(): void
    {
        $this->admin();
        $otherAdmin = $this->createUser('peer@platform.local', UserRole::ROLE_ADMIN);

        $this->client->request('POST', '/admin/users/' . $otherAdmin->getId() . '/revoke-admin');

        self::assertResponseRedirects('/admin/users');
        $this->client->followRedirect();
        self::assertStringContainsString('You can only remove your own administrator role.', (string) $this->client->getResponse()->getContent());
        $fresh = $this->reload($otherAdmin);
        self::assertTrue($fresh->hasRole(UserRole::ROLE_ADMIN), 'another admin must stay an admin');
    }

    public function testDemoteRejectsAdministrator(): void
    {
        $this->admin();
        $otherAdmin = $this->createUser('peer@platform.local', UserRole::ROLE_ADMIN);

        $this->client->request('POST', '/admin/users/' . $otherAdmin->getId() . '/demote');

        self::assertResponseRedirects('/admin/users');
        $this->client->followRedirect();
        self::assertStringContainsString('Use revoke-admin action for administrator accounts.', (string) $this->client->getResponse()->getContent());
        $fresh = $this->reload($otherAdmin);
        self::assertTrue($fresh->hasRole(UserRole::ROLE_ADMIN));
    }

    public function testPromoteRejectsAdministrator(): void
    {
        $this->admin();
        $otherAdmin = $this->createUser('peer@platform.local', UserRole::ROLE_ADMIN);

        $this->client->request('POST', '/admin/users/' . $otherAdmin->getId() . '/promote');

        self::assertResponseRedirects('/admin/users');
        $fresh = $this->reload($otherAdmin);
        self::assertTrue($fresh->hasRole(UserRole::ROLE_ADMIN));
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
        self::assertStringContainsString('data-action="revoke-admin"', $html);
        self::assertStringContainsString('data-required-role="ROLE_ADMIN"', $html);
        self::assertStringContainsString('data-own-row="true"', $html);
        self::assertStringContainsString('data-own-row="false"', $html);
        self::assertStringContainsString('data-role="ROLE_ADMIN"', $html);
        self::assertStringContainsString('data-role="ROLE_CANDIDATE"', $html);
    }
}
