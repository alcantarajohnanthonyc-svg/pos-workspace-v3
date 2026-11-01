<?php

namespace App\Filters;

use App\Models\UserAccountModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class SuperadminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $userId = filter_var(session()->get('user_id'), FILTER_VALIDATE_INT);
        if (!session()->get('is_authenticated') || $userId === false || $userId < 1) {
            return redirect()->to(site_url('login'))->with('error', 'Please sign in to continue.');
        }

        $accounts = new UserAccountModel();
        $actor = $accounts->findForSession((int) $userId);
        if ($actor === null || !$accounts->isLoginReady($actor)) {
            return service('response')->setStatusCode(403)->setBody('Access denied.');
        }
        if (!service('superadminAccessPolicy')->allows($actor)) {
            try {
                $db = db_connect();
                if ($db->tableExists('log_audit_logs')) {
                    $db->table('log_audit_logs')->insert([
                        'actor_user_id' => (int) $userId,
                        'module_id' => null,
                        'event_code' => 'SUPERADMIN_ACCESS_DENIED',
                        'result' => 'DENIED',
                        'before_data' => null,
                        'after_data' => null,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Throwable) {
                log_message('error', 'Superadmin route access denial could not be audited.');
            }
            return service('response')->setStatusCode(403)->setBody('Access denied.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
