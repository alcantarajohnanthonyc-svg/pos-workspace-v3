<?php

namespace App\Filters;

use App\Models\UserAccountModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = filter_var(session()->get('user_id'), FILTER_VALIDATE_INT);

        if (!session()->get('is_authenticated') || $userId === false || $userId < 1) {
            return redirect()->to(site_url('login'))->with('error', 'Please sign in to continue.');
        }

        try {
            if (!service('activeSession')->validate((int) $userId, session_id())) {
                session()->destroy();
                return redirect()->to(site_url('login'))->with('error', 'Your workspace session has expired or was replaced. Please sign in again.');
            }
        } catch (\Throwable $exception) {
            log_message('error', 'Protected request denied because session validation was unavailable.');
            session()->destroy();
            return redirect()->to(site_url('login'))->with('error', 'Your session could not be verified. Please sign in again.');
        }

        $accounts = new UserAccountModel();
        $user     = $accounts->findForSession($userId);

        if ($user === null || !service('googleWorkspaceDomainPolicy')->allowsEmail(
            $user['email'] ?? null,
            is_string(env('google.allowedDomains')) ? env('google.allowedDomains') : null,
        )) {
            try {
                service('activeSession')->revoke((int) $userId, hash('sha256', session_id()), 'AUTH_DOMAIN_ACCESS_REVOKED');
            } catch (\Throwable $exception) {
                log_message('error', 'Protected request denied because account domain authorization could not be confirmed.');
            }
            session()->destroy();

            return redirect()->to(site_url('login'))->with('error', 'Your account is not approved for POS Workspace access. Contact your administrator.');
        }

        if (!$accounts->isApplicationReady($user)) {
            $pendingAvatarUrl = UserAccountModel::normalizeGooglePictureUrl($user['avatar_url'] ?? null);
            session()->destroy();

            return redirect()->to(site_url('login'))
                ->with('account_setup_pending', true)
                ->with('pending_avatar_url', $pendingAvatarUrl);
        }

        // Refresh display data from the database; never trust role data from a client.
        session()->set([
            'role_code'     => $user['role_code'],
            'role_name'     => $user['role_name'],
            'account_status' => $user['account_status'],
            'user_name'     => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'user_email'    => $user['email'],
            'user_picture'  => $user['avatar_url'] ?? null,
        ]);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
