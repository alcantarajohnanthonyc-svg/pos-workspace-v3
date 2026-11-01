<?php

namespace App\Controllers;

use App\Models\UserInfoModel;
use App\Models\UserModel;
use League\OAuth2\Client\Provider\Google;
use Throwable;

class Auth extends BaseController
{
    private Google $googleProvider;

    public function __construct()
    {
        // Config\Google (app/Config/Google.php) first, so no .env is needed; the old env() keys still work as a fallback
        $g            = config('Google');
        $clientId     = $g->clientId     ?: env('GOOGLE_CLIENT_ID', env('google.clientId'));
        $clientSecret = $g->clientSecret ?: env('GOOGLE_CLIENT_SECRET', env('google.clientSecret'));
        $redirectUri  = $g->redirectUri  ?: env('GOOGLE_REDIRECT_URI', env('google.redirectUri'));

        // Disables SSL verification for local development (XAMPP cURL error 60 fix)
        $guzzleClient = new \GuzzleHttp\Client([
            'verify' => false,
        ]);

        $this->googleProvider = new Google([
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri'  => $redirectUri,
        ], [
            'httpClient'   => $guzzleClient,
        ]);
    }

    public function login()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to(base_url('dashboard'));
        }

        return view('auth/login');
    }

    public function google()
    {
        $authorizationUrl = $this->googleProvider->getAuthorizationUrl([
            'scope'  => ['openid', 'email', 'profile'],
            'prompt' => 'select_account',
        ]);

        session()->set('oauth2state', $this->googleProvider->getState());

        return redirect()->to($authorizationUrl);
    }

    public function googleCallback()
    {
        $session = session();
        $code = $this->request->getGet('code');

        if (!$code) {
            $session->setFlashdata('error', 'Google sign-in was cancelled or could not be completed. Please try again.');
            return redirect()->to(base_url('auth/login'));
        }

        $returnedState = (string) $this->request->getGet('state');
        $savedState = (string) $session->get('oauth2state');
        $session->remove('oauth2state');

        if ($savedState === '' || $returnedState === '' || !hash_equals($savedState, $returnedState)) {
            $session->setFlashdata('error', 'Your Google sign-in session expired. Please try again.');
            return redirect()->to(base_url('auth/login'));
        }

        try {
            $token = $this->googleProvider->getAccessToken('authorization_code', ['code' => $code]);
            
            $response = $this->googleProvider->getAuthenticatedRequest(
                'GET',
                'https://www.googleapis.com/oauth2/v3/userinfo',
                $token
            );
            $googleUser = $this->googleProvider->getParsedResponse($response);

            $email    = strtolower(trim((string) ($googleUser['email'] ?? '')));
            $avatar   = (string) ($googleUser['picture'] ?? '');
            $fullName = trim((string) ($googleUser['name'] ?? ''));

            // Use Google's own given/family name; fall back to splitting the full name
            $firstName = trim((string) ($googleUser['given_name'] ?? ''));
            $lastName  = trim((string) ($googleUser['family_name'] ?? ''));
            if ($firstName === '' && $lastName === '' && $fullName !== '') {
                $firstName = $fullName;
                $nameParts = explode(' ', $fullName);
                if (count($nameParts) > 1) {
                    $lastName  = array_pop($nameParts);
                    $firstName = implode(' ', $nameParts);
                }
            }

            if ($email === '' || !str_contains($email, '@')) {
                $session->setFlashdata('error', 'Google did not return a valid email address. Please try again.');
                return redirect()->to(base_url('auth/login'));
            }

            $allowedDomain = strtolower(trim((string) (config('Google')->allowedDomain ?: env('GOOGLE_ALLOWED_DOMAIN', 'bounty.com.ph'))));
            $emailDomain = strtolower(substr(strrchr($email, '@'), 1) ?: '');

            if ($allowedDomain !== '' && $emailDomain !== $allowedDomain) {
                $session->setFlashdata('error', 'Access denied. Only @' . $allowedDomain . ' accounts are allowed.');
                return redirect()->to(base_url('auth/login'));
            }

            // Details taken from the Google Workspace account
            $googleProfile = ['email' => $email, 'first' => $firstName, 'last' => $lastName, 'avatar' => $avatar];

            $userModel = new UserModel();
            $account = $userModel->where('email_workspace', $email)->first();

            // 1. KUNG WALA PA SA DATABASE, GUMAWA NG RECORD BILANG PENDING
            if (!$account) {
                $newAccountId = $userModel->insert([
                    'username'        => null, // Nakatakdang NULL dahil Google Workspace ang gamit
                    'email_workspace' => $email,
                    'password'        => '', 
                    'user_role_id'    => null, 
                    'account_status'  => 'pending'
                ], true);

                if ($newAccountId) {
                    $userInfoModel = new UserInfoModel();
                    $userInfoModel->insert([
                        'account_id' => $newAccountId,
                        'email'      => $email,
                        'first_name' => $firstName,
                        'last_name'  => $lastName,
                        'avatar'     => $avatar
                    ]);
                }

                $session->setFlashdata('pending_approval_email', $email);
                $session->setFlashdata('pending_avatar_url', $avatar);
                return redirect()->to(base_url('auth/login'));
            }

            // 2. KUNG PENDING ANG STATUS
            $status = strtolower((string) ($account['account_status'] ?? 'inactive'));
            if ($status === 'pending') {
                $session->setFlashdata('pending_approval_email', $email);
                $session->setFlashdata('pending_avatar_url', $avatar);
                return redirect()->to(base_url('auth/login'));
            }

            if ($status !== 'active') {
                $session->setFlashdata('error', 'Your account is not active. Please contact the system administrator.');
                return redirect()->to(base_url('auth/login'));
            }

            // 3. SINGLE-DEVICE LOGIN CHECK (Session Conflict Prevention)
            if ($this->hasActiveOtherSession($account)) {
                // I-save ang account ID sa temporary session para magamit sa replace/cancel
                $session->set('conflict_account_id', $account['id']);
                $session->set('conflict_google_profile', $googleProfile);   // applied if the user continues
                $session->setFlashdata('session_conflict', true);
                return redirect()->to(base_url('auth/login'));
            }

            // Fill in / refresh name + photo from Google (admin-created accounts only had the email)
            $this->syncGoogleProfile($account['id'], $googleProfile);

            // Mag-login at i-save ang BAGONG session id (pagkatapos ng regenerate) sa database
            $this->startSession($account);
            if ($avatar !== '') {
                session()->set('avatar', $avatar);
            }

            return redirect()->to(base_url('dashboard'));
        } catch (Throwable $e) {
            log_message('error', 'Google authentication failed: {message}', ['message' => $e->getMessage()]);
            $session->setFlashdata('error', 'Google sign-in could not be completed. Please try again or contact the administrator.');
            return redirect()->to(base_url('auth/login'));
        }
    }

    public function loginProcess()
    {
        $identity = trim((string) $this->request->getPost('login_identity'));
        $password = (string) $this->request->getPost('password');

        if ($identity === '' || $password === '') {
            return redirect()->back()->withInput()->with('error', 'Enter your username/email and password.');
        }

        $account = (new UserModel())
            ->groupStart()
                ->where('username', $identity)
                ->orWhere('email_workspace', $identity)
            ->groupEnd()
            ->first();

        if (!$account || empty($account['password']) || !password_verify($password, (string) $account['password'])) {
            return redirect()->back()->withInput()->with('error', 'Invalid username/email or password.');
        }

        $status = strtolower((string) ($account['account_status'] ?? 'inactive'));
        if ($status === 'pending') {
            return redirect()->to(base_url('auth/login'))
                ->with('pending_approval_email', (string) ($account['email_workspace'] ?? ''));
        }

        if ($status !== 'active') {
            return redirect()->back()->with('error', 'Your account is not active. Please contact the system administrator.');
        }

        // SINGLE-DEVICE LOGIN CHECK PARA SA CREDENTIALS LOGIN
        if ($this->hasActiveOtherSession($account)) {
            session()->set('conflict_account_id', $account['id']);
            return redirect()->to(base_url('auth/login'))->with('session_conflict', true);
        }

        $this->startSession($account);
        return redirect()->to(base_url('dashboard'));
    }

    // HANDLER KAPAG PINILI ANG "Sign Out & Continue" SA CONFLICT MODAL
    public function sessionReplace()
    {
        $session = session();
        $accountId = $session->get('conflict_account_id');

        if ($accountId) {
            $userModel = new UserModel();
            $account = $userModel->find($accountId);

            if ($account) {
                $gp = $session->get('conflict_google_profile');
                if (is_array($gp) && strtolower((string) ($account['email_workspace'] ?? '')) === ($gp['email'] ?? null)) {
                    $this->syncGoogleProfile($account['id'], $gp);
                }
                $session->remove('conflict_google_profile');

                $this->startSession($account);   // bagong session id = ang lumang device ay made-detect na invalid
                $session->remove('conflict_account_id');
                return redirect()->to(base_url('dashboard'));
            }
        }

        return redirect()->to(base_url('auth/login'));
    }

    // HANDLER KAPAG PINILI ANG "Cancel" SA CONFLICT MODAL
    public function sessionCancel()
    {
        session()->remove('conflict_account_id');
        session()->remove('conflict_google_profile');
        return redirect()->to(base_url('auth/login'));
    }

    /**
     * True when this account is still signed in on ANOTHER browser/device
     * (a different session id is saved). The user then sees the "Sign Out & Continue" modal.
     * Logout clears session_id, so the modal only appears while another session really holds the account
     * (or after an unclean close, where one click on "Sign Out & Continue" takes over).
     *
     * @param array<string, mixed> $account
     */
    private function hasActiveOtherSession(array $account): bool
    {
        $savedToken = (string) ($account['session_id'] ?? '');
        return $savedToken !== '' && $savedToken !== (string) session()->get('login_token');
    }

    /**
     * Log the user in and claim the account for THIS browser.
     * A random login token is used (not PHP's session_id()): CodeIgniter regenerates the PHP session id
     * every few minutes (timeToUpdate), which would make the saved id stop matching and log the user out.
     * The token lives in the session and in usr_Account.session_id, and only changes on a new login.
     *
     * @param array<string, mixed> $account
     */
    private function startSession(array $account): void
    {
        $this->setUserSession($account);

        $token = bin2hex(random_bytes(16));   /* 32 chars */
        session()->set('login_token', $token);

        $builder = (new UserModel())->builder();

        /* 1) the login token itself - must always be saved */
        $saved = $builder->where('id', $account['id'])->update(['session_id' => $token]);
        if ($saved === false) {
            log_message('error', 'Auth: could not save session_id for account {id}: {err}', [
                'id'  => $account['id'],
                'err' => json_encode(db_connect()->error()),
            ]);
        }

        /* 2) heartbeat time - separate on purpose, so a missing column never blocks the login token */
        try {
            (new UserModel())->builder()
                ->where('id', $account['id'])
                ->update(['session_last_seen' => date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            log_message('error', 'Auth: session_last_seen not saved (run the ALTER TABLE): {m}', ['m' => $e->getMessage()]);
        }
    }

    /**
     * Heartbeat used by the layout script. 200 = this session still owns the account,
     * 401 = logged out / another device logged in / account no longer active.
     * On 401 the local session is destroyed, otherwise login() would bounce the old device back to the dashboard.
     */
    public function check_token()
    {
        $accountId = session()->get('account_id');
        $row = null;

        if (session()->get('isLoggedIn') && $accountId) {
            $row = (new UserModel())->builder()
                ->select('session_id, account_status')
                ->where('id', $accountId)
                ->get()->getRowArray();
        }

        $valid = $row
            && strtolower((string) $row['account_status']) === 'active'
            && $row['session_id'] !== null
            && hash_equals((string) $row['session_id'], (string) session()->get('login_token'));

        if (! $valid) {
            session()->destroy();
            return $this->response->setStatusCode(401)->setJSON(['ok' => false]);
        }

        try {
            (new UserModel())->builder()
                ->where('id', $accountId)
                ->update(['session_last_seen' => date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            log_message('error', 'Auth heartbeat: {m}', ['m' => $e->getMessage()]);
        }

        $unread = 0;
        try {
            $unread = \App\Libraries\Notifier::unreadCount((int) $accountId);   // the bell badge follows the heartbeat
        } catch (Throwable $e) {
            /* notifications table not created yet: ignore */
        }

        return $this->response->setJSON(['ok' => true, 'unread' => $unread]);
    }

    /**
     * Live online/offline status for the account list (polled by list.php, GET so no loading overlay).
     * Online = still holds a login token AND the heartbeat was seen in the last 2 minutes.
     * GET ?ids=1,2,3  ->  {"users": {"1": {"online": true, "seen": "Just now"}, ...}}
     * Put this route in the SAME route group / filter as the account list page.
     */
    public function live_status()
    {
        if (! session()->get('isLoggedIn')) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false]);
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $this->request->getGet('ids')))));
        $ids = array_slice($ids, 0, 500);
        if (! $ids) {
            return $this->response->setJSON(['users' => (object) []]);
        }

        $rows = (new UserModel())->builder()
            ->select('id, session_id, session_last_seen')
            ->whereIn('id', $ids)
            ->get()->getResultArray();

        $users = [];
        foreach ($rows as $r) {
            $ts     = ! empty($r['session_last_seen']) ? strtotime((string) $r['session_last_seen']) : false;
            $online = ! empty($r['session_id']) && $ts && (time() - $ts) <= 120;

            if (! $ts) {
                $seen = 'Never';
            } else {
                $ago = max(0, time() - $ts);
                if ($ago < 60)                                         { $seen = 'Just now'; }
                elseif ($ago < 3600)                                   { $seen = floor($ago / 60) . ' min ago'; }
                elseif (date('Y-m-d', $ts) === date('Y-m-d'))          { $seen = 'Today ' . date('g:i A', $ts); }
                elseif (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) { $seen = 'Yesterday ' . date('g:i A', $ts); }
                else                                                   { $seen = date('M j, Y g:i A', $ts); }
            }

            $users[(string) $r['id']] = ['online' => $online, 'seen' => $seen];
        }

        return $this->response->setJSON(['users' => $users]);
    }

    /**
     * Copy name / photo / email from the Google account into usr_account_info.
     *  - photo: follows the Google picture (Google accounts do not use uploaded photos)
     *  - name:  only replaced while it is still the placeholder the admin form created
     *           (first = email prefix, last = "User") or empty, so edited names are kept
     *
     * @param array{email:string,first:string,last:string,avatar:string} $g
     */
    private function syncGoogleProfile($accountId, array $g): void
    {
        $db   = \Config\Database::connect();
        $info = $db->table('usr_account_info')->where('account_id', $accountId)->get()->getRowArray();

        if (! $info) {
            $db->table('usr_account_info')->insert([
                'account_id' => $accountId,
                'email'      => $g['email'],
                'first_name' => $g['first'],
                'last_name'  => $g['last'],
                'avatar'     => $g['avatar'] !== '' ? $g['avatar'] : null,
            ]);
            return;
        }

        $update = [];

        if ($g['avatar'] !== '' && ($info['avatar'] ?? '') !== $g['avatar']) {
            $update['avatar'] = $g['avatar'];
        }

        $placeholder = ucfirst(explode('@', $g['email'])[0]);
        $curFirst    = trim((string) ($info['first_name'] ?? ''));
        $curLast     = trim((string) ($info['last_name'] ?? ''));
        $isPlaceholder = $curFirst === '' || ($curLast === 'User' && $curFirst === $placeholder);
        if ($isPlaceholder && ($g['first'] !== '' || $g['last'] !== '')) {
            $update['first_name'] = $g['first'];
            $update['last_name']  = $g['last'];
        }

        if (trim((string) ($info['email'] ?? '')) === '') {
            $update['email'] = $g['email'];
        }

        if ($update) {
            $db->table('usr_account_info')->where('account_id', $accountId)->update($update);
        }
    }

    /** @param array<string, mixed> $account */
    private function setUserSession(array $account): void
    {
        $profile = (new UserInfoModel())->where('account_id', $account['id'])->first() ?? [];

        session()->regenerate(true);
        session()->set([
            'account_id'      => $account['id'],
            'username'        => $account['username'] ?? '',
            'email_workspace' => $account['email_workspace'] ?? '',
            'user_role_id'    => $account['user_role_id'] ?? null,
            'first_name'      => $profile['first_name'] ?? '',
            'last_name'       => $profile['last_name'] ?? '',
            'avatar'          => $profile['avatar'] ?? '',
            'isLoggedIn'      => true,
        ]);
    }

    public function logout()
    {
        $accountId = session()->get('account_id');
        $token     = (string) session()->get('login_token');
        if ($accountId && $token !== '') {
            // Release the lock only if it still belongs to THIS browser
            // (an old device that was replaced must not unlock the new device)
            (new UserModel())->builder()
                ->where('id', $accountId)
                ->where('session_id', $token)
                ->update(['session_id' => null]);   // keep session_last_seen = "last seen" time
        }

        session()->destroy();
        return redirect()->to(base_url('auth/login'));
    }
}