<?php

namespace App\Services;

use CodeIgniter\Throttle\ThrottlerInterface;

final class CredentialLoginLimiter
{
    public function __construct(private readonly ThrottlerInterface $throttler)
    {
    }

    public function allows(string $username, string $ipAddress): bool
    {
        $normalizedUsername = strtolower(trim($username));
        $ip = trim($ipAddress);
        $pairKey = hash('sha256', $normalizedUsername . "\0" . $ip);
        $ipKey = hash('sha256', $ip);

        try {
            $pairAllowed = $this->throttler->check('pwv3-credentials-pair-' . $pairKey, 5, 60, 1);
            $ipAllowed = $this->throttler->check('pwv3-credentials-ip-' . $ipKey, 60, 60, 1);
            return $pairAllowed && $ipAllowed;
        } catch (\Throwable $exception) {
            log_message('error', 'Credential login throttler unavailable.');
            return false;
        }
    }
}
