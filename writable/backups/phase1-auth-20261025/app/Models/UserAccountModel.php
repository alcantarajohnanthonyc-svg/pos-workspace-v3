<?php

namespace App\Models;

use CodeIgniter\Model;

class UserAccountModel extends Model
{
    protected $table         = 'usr_accounts';
    protected $primaryKey    = 'user_id';
    protected $returnType    = 'array';
    protected $allowedFields = [];
    protected $useTimestamps = false;

    /**
     * Create the minimal pending account row for a verified Google identity.
     * Related module, scope, and hierarchy assignments are intentionally not created here.
     */
    public function createPendingGoogleAccount(string $email, ?string $firstName = null, ?string $lastName = null, ?string $avatarUrl = null): bool
    {
        return $this->db->table($this->table)->insert(self::pendingGoogleAccountAttributes($email, $firstName, $lastName, $avatarUrl));
    }

    /**
     * @return array<string, mixed>
     */
    public static function pendingGoogleAccountAttributes(string $email, ?string $firstName = null, ?string $lastName = null, ?string $avatarUrl = null): array
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('A valid verified email is required.');
        }

        return [
            'username'         => null,
            'email'            => $email,
            'password_hash'    => null,
            'employee_code'    => null,
            'first_name'       => self::normalizeGoogleProfileName($firstName),
            'last_name'        => self::normalizeGoogleProfileName($lastName),
            'avatar_url'       => self::normalizeGooglePictureUrl($avatarUrl),
            'role_id'          => null,
            'position_id'      => null,
            'default_module_id' => null,
            'account_status'   => 'PENDING',
        ];
    }

    public static function normalizeGooglePictureUrl(?string $picture): ?string
    {
        if ($picture === null || filter_var($picture, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($picture);

        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);

        return $host === 'googleusercontent.com' || str_ends_with($host, '.googleusercontent.com')
            ? $picture
            : null;
    }

    /**
     * Normalize an optional Google profile name to the live account column limit.
     */
    public static function normalizeGoogleProfileName(mixed $name): ?string
    {
        if (!is_string($name)) {
            return null;
        }

        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', trim($name)) ?? '';

        if ($name === '') {
            return null;
        }

        return function_exists('mb_substr') ? mb_substr($name, 0, 100, 'UTF-8') : substr($name, 0, 100);
    }

    /**
     * Fill only missing names for an existing account; do not overwrite names
     * already assigned during administrator setup.
     */
    public function fillMissingGoogleNames(int $userId, ?string $firstName, ?string $lastName): void
    {
        if ($userId < 1) {
            return;
        }

        $firstName = self::normalizeGoogleProfileName($firstName);
        $lastName  = self::normalizeGoogleProfileName($lastName);
        if ($firstName !== null) {
            $this->db->table($this->table)
                ->where('user_id', $userId)
                ->groupStart()
                ->where('first_name', null)
                ->orWhere('first_name', '')
                ->groupEnd()
                ->update(['first_name' => $firstName]);
        }

        if ($lastName !== null) {
            $this->db->table($this->table)
                ->where('user_id', $userId)
                ->groupStart()
                ->where('last_name', null)
                ->orWhere('last_name', '')
                ->groupEnd()
                ->update(['last_name' => $lastName]);
        }
    }

    public function updateGooglePicture(int $userId, ?string $picture): void
    {
        $picture = self::normalizeGooglePictureUrl($picture);

        if ($userId > 0 && $picture !== null) {
            $this->db->table($this->table)
                ->where('user_id', $userId)
                ->update(['avatar_url' => $picture]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForLoginByEmail(string $email): ?array
    {
        $user = $this->identityQuery()
            ->where('usr_accounts.email', $email)
            ->first();

        return is_array($user) ? $user : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForCredentialLoginByUsername(string $username): ?array
    {
        $user = $this->select([
            'usr_accounts.user_id',
            'usr_accounts.first_name',
            'usr_accounts.last_name',
            'usr_accounts.email',
            'usr_accounts.account_status',
            'usr_accounts.role_id',
            'usr_accounts.password_hash',
            'usr_accounts.avatar_url',
            'usr_roles.role_code',
            'usr_roles.role_name',
            'usr_roles.status AS role_status',
        ])
            ->join('usr_roles', 'usr_roles.role_id = usr_accounts.role_id', 'left')
            ->where('usr_accounts.username', $username)
            ->first();

        return is_array($user) ? $user : null;
    }

    /**
     * Verify a password without exposing stored password hashes to callers.
     *
     * @param array<string, mixed>|null $user
     */
    public function verifyCredentialPassword(?array $user, string $password): bool
    {
        $hash = $user['password_hash'] ?? null;

        if (!is_string($hash) || $hash === '') {
            // Keep nonexistent accounts and accounts without a local password
            // close to the cost of checking a real password hash.
            password_hash($password, PASSWORD_DEFAULT);

            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForSession(int $userId): ?array
    {
        $user = $this->identityQuery()
            ->where('usr_accounts.user_id', $userId)
            ->first();

        return is_array($user) ? $user : null;
    }

    /**
     * A login-ready account must be active and have an explicitly assigned,
     * active role. Module permissions and scopes are checked separately.
     *
     * @param array<string, mixed> $user
     */
    public function isLoginReady(array $user): bool
    {
        $roleId = filter_var($user['role_id'] ?? null, FILTER_VALIDATE_INT);

        return ($user['account_status'] ?? null) === 'ACTIVE'
            && $roleId !== false
            && $roleId > 0
            && ($user['role_status'] ?? null) === 'ACTIVE';
    }

    private function identityQuery(): self
    {
        return $this->select([
            'usr_accounts.user_id',
            'usr_accounts.first_name',
            'usr_accounts.last_name',
            'usr_accounts.email',
            'usr_accounts.account_status',
            'usr_accounts.role_id',
            'usr_accounts.avatar_url',
            'usr_roles.role_code',
            'usr_roles.role_name',
            'usr_roles.status AS role_status',
        ])->join('usr_roles', 'usr_roles.role_id = usr_accounts.role_id', 'left');
    }
}
