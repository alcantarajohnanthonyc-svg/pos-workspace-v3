<?php

namespace App\Controllers;

use App\Libraries\Rbac;
use App\Models\Rbac_model;

/**
 * Activity Log viewer (slug activity_logs, URL activity-logs): who did what, when, from where.
 * RBAC action: read. Super Admin (role_id 1) always passes. Logs cannot be edited or deleted from here.
 */
class ActivityLogs extends BaseController
{
    private const SLUG     = 'activity_logs';
    private const PER_PAGE = 30;

    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        $role  = (int) (session()->get('user_role_id') ?? session()->get('role_id'));
        $canRead = $role === 1 || Rbac::can(self::SLUG, 'read');
        if (! $canRead) {
            return redirect()->to(base_url('dashboard'))->with('error', 'You do not have permission to open the Activity Log.');
        }

        $db = \Config\Database::connect();
        $f  = [
            'q'      => mb_substr(trim((string) $this->request->getGet('q')), 0, 100),
            'user'   => (int) $this->request->getGet('user'),
            'module' => mb_substr(trim((string) $this->request->getGet('module')), 0, 80),
            'action' => mb_substr(trim((string) $this->request->getGet('action')), 0, 60),
            'from'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->request->getGet('from')) ? (string) $this->request->getGet('from') : '',
            'to'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->request->getGet('to')) ? (string) $this->request->getGet('to') : '',
        ];
        $page = max(1, (int) $this->request->getGet('page'));

        $build = function (bool $select) use ($db, $f) {
            $b = $db->table('usr_activity_logs l')
                ->join('usr_account ua', 'ua.id = l.account_id', 'left')
                ->join('usr_account_info ui', 'ui.account_id = l.account_id', 'left');
            if ($select) {
                $b->select('l.*, ua.email_workspace')
                  ->select("TRIM(CONCAT(COALESCE(ui.first_name, ''), ' ', COALESCE(ui.last_name, ''))) AS user_name", false);
            }
            if ($f['user'] > 0)     { $b->where('l.account_id', $f['user']); }
            if ($f['module'] !== '') { $b->where('l.module_slug', $f['module']); }
            if ($f['action'] !== '') { $b->where('l.action', $f['action']); }
            if ($f['from'] !== '')   { $b->where('l.created_at >=', $f['from'] . ' 00:00:00'); }
            if ($f['to'] !== '')     { $b->where('l.created_at <=', $f['to'] . ' 23:59:59'); }
            if ($f['q'] !== '') {
                $b->groupStart()->like('l.description', $f['q'])->orLike('l.url', $f['q'])->orLike('l.record_id', $f['q'])->orLike('l.details', $f['q'])->groupEnd();
            }

            return $b;
        };

        $total = $db->tableExists('usr_activity_logs') ? $build(false)->countAllResults() : 0;
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);
        $rows  = $total ? $build(true)->orderBy('l.id', 'DESC')->limit(self::PER_PAGE, ($page - 1) * self::PER_PAGE)->get()->getResultArray() : [];

        $users = $db->tableExists('usr_activity_logs') ? $db->query(
            "SELECT DISTINCT l.account_id AS id, TRIM(CONCAT(COALESCE(ui.first_name, ''), ' ', COALESCE(ui.last_name, ''))) AS name, ua.email_workspace
             FROM usr_activity_logs l LEFT JOIN usr_account ua ON ua.id = l.account_id LEFT JOIN usr_account_info ui ON ui.account_id = l.account_id
             WHERE l.account_id IS NOT NULL ORDER BY name ASC"
        )->getResultArray() : [];
        $modules = $db->tableExists('usr_activity_logs') ? array_column($db->query('SELECT DISTINCT module_slug AS v FROM usr_activity_logs WHERE module_slug IS NOT NULL ORDER BY v')->getResultArray(), 'v') : [];
        $actions = $db->tableExists('usr_activity_logs') ? array_column($db->query('SELECT DISTINCT action AS v FROM usr_activity_logs ORDER BY v')->getResultArray(), 'v') : [];

        $userId = session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id');

        return view('activity_logs/index', [
            'title'           => 'Activity Log',
            'pageTitle'       => 'Activity Log',
            'filteredModules' => (new Rbac_model())->get_user_modules($userId, $role),
            'can'             => ['read' => true],
            'breadcrumbs'     => [['label' => 'Home', 'url' => 'dashboard'], ['label' => 'Activity Log']],
            'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => self::PER_PAGE,
            'filters' => $f, 'users' => $users, 'modules' => $modules, 'actions' => $actions,
            'tableReady' => $db->tableExists('usr_activity_logs'),
        ]);
    }
}