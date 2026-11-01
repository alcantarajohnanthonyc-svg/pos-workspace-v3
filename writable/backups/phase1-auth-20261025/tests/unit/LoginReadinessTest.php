<?php

namespace Tests\Unit;

use App\Models\UserAccountModel;
use CodeIgniter\Test\CIUnitTestCase;

final class LoginReadinessTest extends CIUnitTestCase
{
    private UserAccountModel $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = new UserAccountModel();
    }

    public function testActiveAccountWithExplicitActiveRoleIsReady(): void
    {
        $this->assertTrue($this->accounts->isLoginReady([
            'account_status' => 'ACTIVE',
            'role_id' => '2',
            'role_status' => 'ACTIVE',
        ]));
    }

    public function testNonActiveAccountStatusesAreNotReady(): void
    {
        foreach (['PENDING', 'INACTIVE', 'SUSPENDED'] as $status) {
            $this->assertFalse($this->accounts->isLoginReady([
                'account_status' => $status,
                'role_id' => '2',
                'role_status' => 'ACTIVE',
            ]), $status . ' accounts must not be login-ready.');
        }
    }

    public function testMissingInvalidOrInactiveRoleIsNotReady(): void
    {
        foreach ([null, '', 0, 'invalid'] as $roleId) {
            $this->assertFalse($this->accounts->isLoginReady([
                'account_status' => 'ACTIVE',
                'role_id' => $roleId,
                'role_status' => 'ACTIVE',
            ]));
        }

        $this->assertFalse($this->accounts->isLoginReady([
            'account_status' => 'ACTIVE',
            'role_id' => '2',
            'role_status' => 'INACTIVE',
        ]));
    }

    public function testMissingRoleStatusIsNotReady(): void
    {
        $this->assertFalse($this->accounts->isLoginReady([
            'account_status' => 'ACTIVE',
            'role_id' => '2',
        ]));
    }

    public function testCredentialPasswordVerificationRequiresTheStoredHash(): void
    {
        $password = bin2hex(random_bytes(24));
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->assertIsString($hash);

        $this->assertTrue($this->accounts->verifyCredentialPassword([
            'password_hash' => $hash,
        ], $password));

        $this->assertFalse($this->accounts->verifyCredentialPassword([
            'password_hash' => $hash,
        ], bin2hex(random_bytes(24))));

        $this->assertFalse($this->accounts->verifyCredentialPassword(null, $password));
        $this->assertFalse($this->accounts->verifyCredentialPassword([
            'password_hash' => null,
        ], $password));
    }

    public function testPendingGoogleAccountHasNoRoleOrPreassignedAccess(): void
    {
        $attributes = UserAccountModel::pendingGoogleAccountAttributes('pending-user@example.invalid', 'Google', 'User', 'https://lh3.googleusercontent.com/profile.jpg');

        $this->assertSame('PENDING', $attributes['account_status']);
        $this->assertNull($attributes['role_id']);
        $this->assertNull($attributes['username']);
        $this->assertNull($attributes['password_hash']);
        $this->assertNull($attributes['employee_code']);
        $this->assertSame('Google', $attributes['first_name']);
        $this->assertSame('User', $attributes['last_name']);
        $this->assertSame('https://lh3.googleusercontent.com/profile.jpg', $attributes['avatar_url']);
        $this->assertNull($attributes['position_id']);
        $this->assertNull($attributes['default_module_id']);
    }

    public function testGoogleProfileNamesAreOptionalTrimmedAndLimited(): void
    {
        $this->assertNull(UserAccountModel::normalizeGoogleProfileName(null));
        $this->assertNull(UserAccountModel::normalizeGoogleProfileName('   '));
        $this->assertSame('Ada', UserAccountModel::normalizeGoogleProfileName("  Ada\n"));
        $this->assertSame(str_repeat('A', 100), UserAccountModel::normalizeGoogleProfileName(str_repeat('A', 120)));
        $this->assertNull(UserAccountModel::normalizeGooglePictureUrl('http://lh3.googleusercontent.com/profile.jpg'));
        $this->assertNull(UserAccountModel::normalizeGooglePictureUrl('https://example.invalid/profile.jpg'));
        $this->assertSame('https://lh3.googleusercontent.com/profile.jpg', UserAccountModel::normalizeGooglePictureUrl('https://lh3.googleusercontent.com/profile.jpg'));
    }
}
