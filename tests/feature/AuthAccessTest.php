<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Exceptions\PageNotFoundException;

final class AuthAccessTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testLoginPageRemainsAvailableToUnauthenticatedUsers(): void
    {
        $result = $this->withSession([])->get('/login');

        $result->assertStatus(200);
        $result->assertSee('Google Workspace');
    }

    public function testRootRedirectsUnauthenticatedUsersToLogin(): void
    {
        $result = $this->withSession([])->get('/');

        $result->assertRedirectTo(site_url('login'));
    }

    public function testRootRedirectsAuthenticatedRolesToLandingPage(): void
    {
        foreach (['USER', 'SUPERADMIN'] as $roleCode) {
            $result = $this->withSession(['is_authenticated' => true, 'role_code' => $roleCode])->get('/');

            $result->assertRedirectTo(site_url('dashboard'));
        }
    }

    public function testAuthenticatedUsersVisitingLoginAreSentToLandingPage(): void
    {
        $result = $this->withSession(['is_authenticated' => true])->get('/login');

        $result->assertRedirectTo(site_url('dashboard'));
    }

    public function testLandingPageRedirectsUnauthenticatedRequestsToLogin(): void
    {
        $result = $this->withSession([])->get('/dashboard');

        $result->assertRedirectTo(site_url('login'));
    }

    public function testBusinessModuleDirectUrlCannotBeResolvedForAnAuthenticatedSuperadmin(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->withSession(['is_authenticated' => true, 'role_code' => 'SUPERADMIN'])->get('/superadmin/organization');
    }
}
