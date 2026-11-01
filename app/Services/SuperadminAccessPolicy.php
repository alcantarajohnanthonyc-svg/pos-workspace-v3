<?php

namespace App\Services;

final class SuperadminAccessPolicy
{
    /** @param array<string, mixed>|null $user */
    public function allows(?array $user): bool
    {
        if ($user === null || ($user['account_status'] ?? null) !== 'ACTIVE'
            || ($user['role_status'] ?? null) !== 'ACTIVE'
            || ($user['role_code'] ?? null) !== 'SUPERADMIN'
        ) {
            return false;
        }

        $roleId = filter_var($user['role_id'] ?? null, FILTER_VALIDATE_INT);

        return $roleId !== false && $roleId > 0;
    }
}
