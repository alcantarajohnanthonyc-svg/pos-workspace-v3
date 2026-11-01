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

        $accounts = new UserAccountModel();
        $user     = $accounts->findForSession($userId);

        if ($user === null || !$accounts->isLoginReady($user)) {
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
