<?php

namespace App\Controllers;

use App\Libraries\Rbac;
use App\Models\Rbac_model;

class CompanyController extends BaseController
{
    protected string $moduleSlug = 'company';

    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! Rbac::can($this->moduleSlug, 'read') && (int) session()->get('user_role_id') !== Rbac::SUPER_ADMIN_ROLE) {
            return redirect()->to(base_url('dashboard'))->with('error', 'You do not have permission to access Company Management.');
        }

        $userId = session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id');
        $roleId = session()->get('user_role_id') ?? session()->get('role_id');
        $db = \Config\Database::connect();

        $companies = $db->table('com_company c')
            ->select('c.*, (SELECT COUNT(*) FROM com_branch b WHERE b.company_id = c.id) as branch_count')
            ->orderBy('c.id', 'DESC')
            ->get()->getResultArray();

        return view('company/index', [
            'title'           => 'Company Management',
            'pageTitle'       => 'Company Management',
            'filteredModules' => (new Rbac_model())->get_user_modules($userId, $roleId),
            'can'             => Rbac::map($this->moduleSlug, ['create', 'read', 'update', 'delete']),
            'companies'       => $companies,
            'breadcrumbs'     => [
                ['label' => 'Home', 'url' => 'dashboard'],
                ['label' => 'Company Management'],
            ],
        ]);
    }

    public function store()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! Rbac::can($this->moduleSlug, 'create') && (int) session()->get('user_role_id') !== Rbac::SUPER_ADMIN_ROLE) {
            return redirect()->back()->with('error', 'You do not have permission to create companies.');
        }

        $db = \Config\Database::connect();
        $db->table('com_company')->insert([
            'name'       => trim($this->request->getPost('name')),
            'short_name' => trim($this->request->getPost('short_name')),
            'address'    => trim($this->request->getPost('address')),
            'status'     => $this->request->getPost('status') ?: 'active',
        ]);

        return redirect()->to(base_url('company'))->with('success', 'Company created successfully.');
    }

    public function update($id = null)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! Rbac::can($this->moduleSlug, 'update') && (int) session()->get('user_role_id') !== Rbac::SUPER_ADMIN_ROLE) {
            return redirect()->back()->with('error', 'You do not have permission to update companies.');
        }

        $db = \Config\Database::connect();
        $db->table('com_company')->where('id', (int) $id)->update([
            'name'       => trim($this->request->getPost('name')),
            'short_name' => trim($this->request->getPost('short_name')),
            'address'    => trim($this->request->getPost('address')),
            'status'     => $this->request->getPost('status') ?: 'active',
        ]);

        return redirect()->to(base_url('company'))->with('success', 'Company updated successfully.');
    }

    public function delete($id = null)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! Rbac::can($this->moduleSlug, 'delete') && (int) session()->get('user_role_id') !== Rbac::SUPER_ADMIN_ROLE) {
            return redirect()->back()->with('error', 'You do not have permission to delete companies.');
        }

        $db = \Config\Database::connect();
        $db->table('com_company')->where('id', (int) $id)->delete();

        return redirect()->to(base_url('company'))->with('success', 'Company deleted successfully.');
    }
}