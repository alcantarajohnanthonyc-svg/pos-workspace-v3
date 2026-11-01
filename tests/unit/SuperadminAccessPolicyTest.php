<?php

namespace Tests\Unit;

use App\Services\SuperadminAccessPolicy;
use CodeIgniter\Test\CIUnitTestCase;

final class SuperadminAccessPolicyTest extends CIUnitTestCase
{
    private SuperadminAccessPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new SuperadminAccessPolicy();
    }

    public function testOnlyActiveAccountWithExplicitActiveSuperadminRoleIsAllowed(): void
    {
        $superadmin = ['user_id' => 1, 'role_id' => 1, 'role_code' => 'SUPERADMIN', 'account_status' => 'ACTIVE', 'role_status' => 'ACTIVE'];
        $this->assertTrue($this->policy->allows($superadmin));

        $ordinary = $superadmin;
        $ordinary['role_code'] = 'USER';
        $this->assertFalse($this->policy->allows($ordinary));

        $inactive = $superadmin;
        $inactive['account_status'] = 'INACTIVE';
        $this->assertFalse($this->policy->allows($inactive));

        $inactiveRole = $superadmin;
        $inactiveRole['role_status'] = 'INACTIVE';
        $this->assertFalse($this->policy->allows($inactiveRole));

        $unassigned = $superadmin;
        $unassigned['role_id'] = null;
        $this->assertFalse($this->policy->allows($unassigned));
        $this->assertFalse($this->policy->allows(null));
    }
}
