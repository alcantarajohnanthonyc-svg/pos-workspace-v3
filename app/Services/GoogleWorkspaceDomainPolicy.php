<?php

namespace App\Services;

/** Applies the configured exact-domain allowlist to Google identities. */
final class GoogleWorkspaceDomainPolicy
{
    /** @return list<string>|null Null means the configuration is invalid or empty. */
    public function allowedDomains(?string $configuration): ?array
    {
        if (!is_string($configuration) || trim($configuration) === '') {
            return null;
        }

        $domains = [];
        foreach (explode(',', $configuration) as $configuredDomain) {
            $domain = strtolower(trim($configuredDomain));
            if (!$this->isValidDomain($domain)) {
                return null;
            }
            $domains[] = $domain;
        }

        return array_values(array_unique($domains));
    }

    /** Validate provider identity data before any account lookup or write. */
    public function approvedIdentityEmail(array $identity, ?string $configuration): ?string
    {
        $subject = $identity['sub'] ?? null;
        $verified = $identity['email_verified'] ?? null;
        $email = $identity['email'] ?? null;

        if (!is_string($subject) || trim($subject) === ''
            || !in_array($verified, [true, 'true', 1, '1'], true)
            || !is_string($email)
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            return null;
        }

        $emailDomain = $this->emailDomain($email);
        $allowed = $this->allowedDomains($configuration);
        if ($emailDomain === null || $allowed === null || !in_array($emailDomain, $allowed, true)) {
            return null;
        }

        // Google may include the hosted-domain claim. When present, it must
        // independently match an approved domain. It can differ from an
        // approved email alias domain in a Workspace tenant.
        if (array_key_exists('hd', $identity)) {
            if (!is_string($identity['hd'])) {
                return null;
            }
            $hostedDomain = strtolower(trim($identity['hd']));
            if (!$this->isValidDomain($hostedDomain)
                || !in_array($hostedDomain, $allowed, true)
            ) {
                return null;
            }
        }

        return $email;
    }

    public function allowsEmail(?string $email, ?string $configuration): bool
    {
        if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $domain = $this->emailDomain($email);
        $allowed = $this->allowedDomains($configuration);

        return $domain !== null && $allowed !== null && in_array($domain, $allowed, true);
    }

    private function emailDomain(string $email): ?string
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }

        $domain = strtolower(substr($email, $at + 1));

        return $this->isValidDomain($domain) ? $domain : null;
    }

    private function isValidDomain(string $domain): bool
    {
        return strlen($domain) <= 253
            && preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $domain) === 1;
    }
}
