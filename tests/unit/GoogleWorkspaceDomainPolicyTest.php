<?php

namespace Tests\Unit;

use App\Services\GoogleWorkspaceDomainPolicy;
use CodeIgniter\Test\CIUnitTestCase;

final class GoogleWorkspaceDomainPolicyTest extends CIUnitTestCase
{
    private GoogleWorkspaceDomainPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new GoogleWorkspaceDomainPolicy();
    }

    public function testVerifiedIdentityFromApprovedDomainIsAcceptedCaseInsensitively(): void
    {
        $identity = ['sub' => 'google-subject', 'email_verified' => true, 'email' => 'Person@Corp.Example'];

        $this->assertSame('Person@Corp.Example', $this->policy->approvedIdentityEmail($identity, 'corp.example'));
    }

    public function testUnapprovedDomainIsRejectedBeforeAnEmailCanBeUsedForAccountLookup(): void
    {
        $identity = ['sub' => 'google-subject', 'email_verified' => true, 'email' => 'person@other.example'];

        // The callback uses this result before constructing/querying UserAccountModel.
        $this->assertNull($this->policy->approvedIdentityEmail($identity, 'corp.example'));
    }

    public function testLookalikeAndUnlistedSubdomainAreRejectedByExactMatching(): void
    {
        foreach (['person@corp.example.attacker.com', 'person@sub.corp.example'] as $email) {
            $this->assertNull($this->policy->approvedIdentityEmail([
                'sub' => 'google-subject', 'email_verified' => true, 'email' => $email,
            ], 'corp.example'));
        }
    }

    public function testMissingEmptyAndMalformedAllowlistFailClosed(): void
    {
        $identity = ['sub' => 'google-subject', 'email_verified' => true, 'email' => 'person@corp.example'];

        foreach ([null, '', '   ', 'corp.example,', '*.corp.example', 'corp.example;attacker.test'] as $config) {
            $this->assertNull($this->policy->approvedIdentityEmail($identity, $config));
        }
    }

    public function testUnverifiedEmailOrMissingProviderSubjectIsRejected(): void
    {
        $identity = ['sub' => 'google-subject', 'email_verified' => false, 'email' => 'person@corp.example'];
        $this->assertNull($this->policy->approvedIdentityEmail($identity, 'corp.example'));

        $identity['email_verified'] = true;
        unset($identity['sub']);
        $this->assertNull($this->policy->approvedIdentityEmail($identity, 'corp.example'));
    }

    public function testOptionalHostedDomainClaimMustAlsoBeAllowlisted(): void
    {
        $identity = ['sub' => 'google-subject', 'email_verified' => true, 'email' => 'person@corp.example', 'hd' => 'attacker.example'];
        $this->assertNull($this->policy->approvedIdentityEmail($identity, 'corp.example'));

        $identity['hd'] = 'corp.example';
        $this->assertSame('person@corp.example', $this->policy->approvedIdentityEmail($identity, 'corp.example'));

        $identity['email'] = 'person@alias.example';
        $identity['hd'] = 'corp.example';
        $this->assertSame('person@alias.example', $this->policy->approvedIdentityEmail($identity, 'corp.example,alias.example'));
    }

    public function testExistingAccountEmailAuthorizationFailsClosedForPendingOrRemovedDomainConfiguration(): void
    {
        $this->assertTrue($this->policy->allowsEmail('person@corp.example', 'corp.example'));
        $this->assertFalse($this->policy->allowsEmail('person@corp.example', ''));
        $this->assertFalse($this->policy->allowsEmail('person@corp.example', 'other.example'));
    }
}
