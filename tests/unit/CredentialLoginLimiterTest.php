<?php

namespace Tests\Unit;

use App\Services\CredentialLoginLimiter;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Throttle\ThrottlerInterface;

final class CredentialLoginLimiterTest extends CIUnitTestCase
{
    public function testChecksPairAndIpBucketsWithoutPuttingIdentifiersInKeys(): void
    {
        $throttler = $this->createMock(ThrottlerInterface::class);
        $keys = [];
        $throttler->expects($this->exactly(2))->method('check')->willReturnCallback(
            static function (string $key, int $capacity, int $seconds) use (&$keys): bool {
                $keys[] = $key;
                self::assertSame(60, $seconds);
                self::assertContains($capacity, [5, 60]);
                self::assertStringStartsWith('pwv3-credentials-', $key);
                self::assertStringNotContainsString('person@example.test', $key);
                self::assertStringNotContainsString('192.0.2.7', $key);
                return true;
            }
        );

        $this->assertTrue((new CredentialLoginLimiter($throttler))->allows('person@example.test', '192.0.2.7'));
        $this->assertCount(2, array_unique($keys));
    }

    public function testThrottleDeniesWhenEitherBucketIsExhausted(): void
    {
        $throttler = $this->createMock(ThrottlerInterface::class);
        $calls = 0;
        $throttler->expects($this->exactly(2))->method('check')->willReturnCallback(static function () use (&$calls): bool {
            return ++$calls === 1;
        });
        $this->assertFalse((new CredentialLoginLimiter($throttler))->allows('user', '192.0.2.8'));
    }

    public function testThrottleFailsClosedWhenCacheBackendThrows(): void
    {
        $throttler = $this->createMock(ThrottlerInterface::class);
        $throttler->expects($this->once())->method('check')->willThrowException(new \RuntimeException('backend unavailable'));
        $this->assertFalse((new CredentialLoginLimiter($throttler))->allows('user', '192.0.2.9'));
    }
}
