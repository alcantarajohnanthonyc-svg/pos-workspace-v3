<?php

namespace App\Controllers;

use App\Models\UserAccountModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\RedirectResponse;

class Login extends BaseController
{
    public function index()
    {
        return view('auth/login');
    }

    public function credentials(): RedirectResponse
    {
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');
        $username = is_string($username) ? trim($username) : '';
        $password = is_string($password) ? $password : '';
        $usernameTooLong = strlen($username) > 200;

        try {
            $throttler = service('throttler');
            $ipKey     = hash('sha256', $this->request->getIPAddress());
            $normalizedUsername = $usernameTooLong
                ? '__invalid_length__'
                : (function_exists('mb_strtolower') ? mb_strtolower($username, 'UTF-8') : strtolower($username));
            $loginKey     = hash('sha256', $normalizedUsername);
            $ipAllowed    = $throttler->check('credential-ip-' . $ipKey, 60, 60);
            $loginAllowed = $throttler->check('credential-login-' . hash('sha256', $ipKey . ':' . $loginKey), 5, 60);
        } catch (\Throwable $exception) {
            log_message('error', 'Credential login failure stage=rate_limit_unavailable');

            return $this->credentialFailure();
        }

        if (!$ipAllowed || !$loginAllowed || $username === '' || $password === '' || $usernameTooLong) {
            return $this->credentialFailure();
        }

        $usernameLength = function_exists('mb_strlen') ? mb_strlen($username) : strlen($username);

        if ($usernameLength > 50 || strlen($password) > 4096) {
            return $this->credentialFailure();
        }

        $accounts = new UserAccountModel();
        $user     = $accounts->findForCredentialLoginByUsername($username);

        if (!$accounts->verifyCredentialPassword($user, $password)) {
            return $this->credentialFailure();
        }

        if (!$accounts->isLoginReady($user)) {
            return redirect()->to(site_url('login'))
                ->with('account_setup_pending', true)
                ->with('pending_avatar_url', UserAccountModel::normalizeGooglePictureUrl($user['avatar_url'] ?? null))
                ->with('credential_login_open', true);
        }

        session()->regenerate(true);
        session()->set([
            'user_id'          => (int) $user['user_id'],
            'is_authenticated' => true,
        ]);

        return redirect()->to(site_url('dashboard'));
    }

    public function google(): RedirectResponse
    {
        $clientId    = (string) env('google.clientId', '');
        $redirectUri = (string) env('google.redirectUri', '');

        if ($clientId === '' || !$this->hasValidRedirectUri($redirectUri)) {
            log_message('warning', 'Google OAuth failure stage=authorization_precheck');

            return redirect()->to(site_url('login'))->with('error', 'Google sign-in is not configured correctly.');
        }

        try {
            $state = bin2hex(random_bytes(32));
        } catch (\Throwable $exception) {
            log_message('error', 'Google OAuth failure stage=state_generation');

            return redirect()->to(site_url('login'))->with('error', 'Google sign-in could not be started. Please try again.');
        }

        session()->set('google_oauth_state', $state);

        $params = [
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'prompt'        => 'select_account',
            'state'         => $state,
        ];

        return redirect()->to('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
    }

    public function googleCallback(): RedirectResponse
    {
        $expectedState = (string) session()->get('google_oauth_state');
        $receivedState = (string) $this->request->getGet('state');
        session()->remove('google_oauth_state');

        if ($expectedState === '' || $receivedState === '' || !hash_equals($expectedState, $receivedState)) {
            log_message('warning', 'Google OAuth failure stage=state_validation');

            return redirect()->to(site_url('login'))->with('error', 'Google sign-in could not be verified. Please try again.');
        }

        if ($this->request->getGet('error') !== null) {
            log_message('notice', 'Google OAuth failure stage=provider_denied');

            return redirect()->to(site_url('login'))->with('error', 'Google sign-in was canceled or could not be completed.');
        }

        $code         = (string) $this->request->getGet('code');
        $clientId     = (string) env('google.clientId', '');
        $clientSecret = (string) env('google.clientSecret', '');
        $redirectUri  = (string) env('google.redirectUri', '');

        if ($code === '' || $clientId === '' || $clientSecret === '' || !$this->hasValidRedirectUri($redirectUri)) {
            log_message('warning', 'Google OAuth failure stage=callback_precheck');

            return redirect()->to(site_url('login'))->with('error', 'Google sign-in could not be completed. Please try again.');
        }

        $tokenResponse = $this->requestGoogleJson('https://oauth2.googleapis.com/token', 'token_exchange', [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'code'          => $code,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        if (!is_array($tokenResponse) || empty($tokenResponse['access_token']) || ($tokenResponse['token_type'] ?? '') !== 'Bearer') {
            log_message('warning', 'Google OAuth failure stage=token_response_validation');

            return redirect()->to(site_url('login'))->with('error', 'Google sign-in could not be completed. Please try again.');
        }

        $googleIdentity = $this->requestGoogleJson('https://openidconnect.googleapis.com/v1/userinfo', 'identity_request', [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $tokenResponse['access_token'],
                'Accept: application/json',
            ],
        ]);

        if (!is_array($googleIdentity)
            || empty($googleIdentity['sub'])
            || !in_array($googleIdentity['email_verified'] ?? null, [true, 'true', 1, '1'], true)
            || !isset($googleIdentity['email'])
            || filter_var($googleIdentity['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            log_message('warning', 'Google OAuth failure stage=identity_validation');

            return redirect()->to(site_url('login'))->with('error', 'Google did not provide a verified account identity.');
        }

        $accounts = new UserAccountModel();
        $email    = (string) $googleIdentity['email'];
        $firstName = UserAccountModel::normalizeGoogleProfileName($googleIdentity['given_name'] ?? null);
        $lastName  = UserAccountModel::normalizeGoogleProfileName($googleIdentity['family_name'] ?? null);
        $picture   = UserAccountModel::normalizeGooglePictureUrl(is_string($googleIdentity['picture'] ?? null) ? $googleIdentity['picture'] : null);
        $user     = $accounts->findForLoginByEmail($email);

        if ($user === null) {
            try {
                if ($accounts->createPendingGoogleAccount($email, $firstName, $lastName, $picture)) {
                    log_message('notice', 'Google OAuth stage=pending_account_created');

                    return redirect()->to(site_url('login'))
                        ->with('account_setup_pending', true)
                        ->with('pending_avatar_url', $picture);
                }
            } catch (DatabaseException $exception) {
                // A unique-email collision can occur when simultaneous first logins race.
                log_message('notice', 'Google OAuth stage=pending_account_insert_conflict');
            } catch (\Throwable $exception) {
                log_message('error', 'Google OAuth failure stage=pending_account_creation');

                return redirect()->to(site_url('login'))->with('error', 'Google sign-in could not be completed. Please try again.');
            }

            try {
                $user = $accounts->findForLoginByEmail($email);
            } catch (\Throwable $exception) {
                log_message('error', 'Google OAuth failure stage=pending_account_reconciliation');

                return redirect()->to(site_url('login'))->with('error', 'Google sign-in could not be completed. Please try again.');
            }

            if ($user === null) {
                log_message('error', 'Google OAuth failure stage=pending_account_not_persisted');

                return redirect()->to(site_url('login'))->with('error', 'Google sign-in could not be completed. Please try again.');
            }
        }

        if (!$accounts->isLoginReady($user)) {
            log_message('notice', 'Google OAuth failure stage=account_or_role_status');

            return redirect()->to(site_url('login'))
                ->with('account_setup_pending', true)
                ->with('pending_avatar_url', $picture ?? UserAccountModel::normalizeGooglePictureUrl($user['avatar_url'] ?? null));
        }

        // Persist only after account readiness is confirmed. Names fill blank
        // fields only; an otherwise-valid sign-in is not denied for sync failure.
        try {
            $accounts->fillMissingGoogleNames((int) $user['user_id'], $firstName, $lastName);
            $accounts->updateGooglePicture((int) $user['user_id'], $picture);
        } catch (\Throwable $exception) {
            log_message('warning', 'Google OAuth profile sync failed');
        }

        session()->regenerate(true);
        session()->set([
            'user_id'        => (int) $user['user_id'],
            'is_authenticated' => true,
            'user_picture'   => $picture,
        ]);

        return redirect()->to(site_url('dashboard'));
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to(site_url('login'))->with('message', 'You have been signed out.');
    }

    private function credentialFailure(): RedirectResponse
    {
        return redirect()->to(site_url('login'))
            ->with('error', 'Invalid username or password. Please try again.')
            ->with('credential_login_open', true);
    }

    private function hasValidRedirectUri(string $redirectUri): bool
    {
        return $redirectUri !== ''
            && hash_equals(rtrim(site_url('login/google/callback'), '/'), rtrim($redirectUri, '/'));
    }

    /**
     * Send a verified HTTPS request to Google and return its JSON response.
     * OAuth tokens and provider responses are never logged or exposed to the user.
     *
     * @param array<int, mixed> $options
     * @return array<string, mixed>|null
     */
    private function requestGoogleJson(string $url, string $stage, array $options = []): ?array
    {
        if (!function_exists('curl_init')) {
            log_message('error', 'Google OAuth failure stage=' . $stage . ' reason=curl_unavailable');

            return null;
        }

        $curl = curl_init($url);

        if ($curl === false) {
            log_message('error', 'Google OAuth failure stage=' . $stage . ' reason=curl_initialization');

            return null;
        }

        $curlDefaults = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_FAILONERROR    => true,
        ];

        $caBundle = $this->googleCaBundlePath();
        if ($caBundle !== null) {
            $curlDefaults[CURLOPT_CAINFO] = $caBundle;
        }

        curl_setopt_array($curl, $options + $curlDefaults);

        $response = curl_exec($curl);
        $status   = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($curl);
        curl_close($curl);

        if (!is_string($response) || $status < 200 || $status >= 300) {
            log_message(
                'warning',
                'Google OAuth failure stage=' . $stage . ' http_status=' . $status . ' curl_errno=' . $curlError,
            );

            return null;
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            log_message('warning', 'Google OAuth failure stage=' . $stage . ' reason=invalid_json');

            return null;
        }

        return $decoded;
    }

    private function googleCaBundlePath(): ?string
    {
        foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile')] as $configuredPath) {
            $configuredPath = trim((string) $configuredPath);

            if ($configuredPath !== '' && is_file($configuredPath) && is_readable($configuredPath)) {
                return $configuredPath;
            }
        }

        // XAMPP for Windows includes this CA bundle beside its Apache binaries.
        $xamppBundle = dirname(ROOTPATH, 2)
            . DIRECTORY_SEPARATOR . 'apache'
            . DIRECTORY_SEPARATOR . 'bin'
            . DIRECTORY_SEPARATOR . 'curl-ca-bundle.crt';

        return is_file($xamppBundle) && is_readable($xamppBundle) ? $xamppBundle : null;
    }
}
