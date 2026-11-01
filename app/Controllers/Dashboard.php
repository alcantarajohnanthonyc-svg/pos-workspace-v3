<?php

namespace App\Controllers;

use App\Models\Rbac_model;

class Dashboard extends BaseController
{
    public function index()
    {
        // I-check kung naka-login
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        // Kunin ang user ID at role ID mula sa session gamit ang tamang fallback keys
        $userId = session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id');
        $roleId = session()->get('user_role_id') ?? session()->get('role_id');

        // I-load ang Rbac_model para makuha ang filtered modules ng user na ito
        $rbacModel = new Rbac_model();
        $modules = $rbacModel->get_user_modules($userId, $roleId);

        // Ihanda ang mga datos na ipapasa sa view/layout
        $data = [
            'title'           => 'Dashboard',
            'pageTitle'       => 'Dashboard',
            'filteredModules' => $modules, // Ito ang gagamitin ng sidebar para lumitaw ang mga menu
            'breadcrumbs'     => [
                ['label' => 'Home', 'url' => 'dashboard'],
                ['label' => 'Dashboard']
            ]
        ];

        // I-render ang view (siguraduhing nag-extend ito sa main layout natin)
        return view('dashboard/index', $data);
    }
}