<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;

final class RouteAuthorizationTest extends CIUnitTestCase
{
    public function testLandingPageAndLogoutRemainProtected(): void
    {
        $routes = service('routes');

        self::assertContains('auth', $routes->getFiltersForRoute('dashboard', 'GET'));
        self::assertContains('auth', $routes->getFiltersForRoute('logout', 'POST'));
        self::assertContains('csrf', $routes->getFiltersForRoute('logout', 'POST'));
    }

    public function testAuthenticationMutationsRemainCsrfProtected(): void
    {
        $routes = service('routes');

        foreach ([
            'login/credentials',
            'login/session/replace',
            'login/session/cancel',
        ] as $path) {
            self::assertContains('csrf', $routes->getFiltersForRoute($path, 'POST'), $path.' must require CSRF protection.');
        }
    }

    public function testBusinessModuleRoutesAreNoLongerRegistered(): void
    {
        $routes = service('routes');
        $routes->getRoutes('GET');
        $routes->getRoutes('POST');

        foreach ([
            ['outlets', 'GET'],
            ['outlets/new', 'GET'],
            ['outlets/(:num)/edit', 'GET'],
            ['superadmin/dashboard', 'GET'],
            ['superadmin/modules', 'GET'],
            ['superadmin/user-management/lookups', 'GET'],
            ['superadmin/organization', 'GET'],
            ['user-management/users', 'GET'],
            ['user-management/users/(:num)', 'GET'],
            ['user-management/requests', 'GET'],
            ['outlets', 'POST'],
            ['user-management/users/(:num)/status', 'POST'],
            ['user-management/users/(:num)/module-access', 'POST'],
            ['user-management/requests/(:num)/decision', 'POST'],
            ['superadmin/modules', 'POST'],
            ['superadmin/organization/companies', 'POST'],
        ] as [$path, $method]) {
            self::assertArrayNotHasKey($path, $routes->getRoutes($method), $path.' must not be registered.');
        }
    }
}
