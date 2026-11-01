<?php

namespace App\Controllers;

use App\Libraries\Rbac;
use App\Models\Rbac_model;

class BranchController extends BaseController
{
    protected string $moduleSlug = 'branch';

    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! Rbac::can($this->moduleSlug, 'read') && (int) session()->get('user_role_id') !== Rbac::SUPER_ADMIN_ROLE) {
            return redirect()->to(base_url('dashboard'))->with('error', 'You do not have permission to access Branch Management.');
        }

        $userId = session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id');
        $roleId = session()->get('user_role_id') ?? session()->get('role_id');
        $db = \Config\Database::connect();

        $branches = $db->table('com_branch b')
            ->select('b.*, c.name as company_name, c.short_name as company_short_name')
            ->join('com_company c', 'c.id = b.company_id', 'inner')
            ->orderBy('b.id', 'DESC')
            ->get()->getResultArray();

        $companies = $db->table('com_company')->where('status', 'active')->get()->getResultArray();

        return view('branch/index', [
            'title'           => 'Branch Management',
            'pageTitle'       => 'Branch Management',
            'filteredModules' => (new Rbac_model())->get_user_modules($userId, $roleId),
            'can'             => Rbac::map($this->moduleSlug, ['create', 'read', 'update', 'delete']),
            'branches'        => $branches,
            'companies'       => $companies,
            'breadcrumbs'     => [
                ['label' => 'Home', 'url' => 'dashboard'],
                ['label' => 'Branch Management'],
            ],
        ]);
    }

    public function store()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! Rbac::can($this->moduleSlug, 'create') && (int) session()->get('user_role_id') !== Rbac::SUPER_ADMIN_ROLE) {
            return redirect()->back()->with('error', 'You do not have permission to create branches.');
        }

        $db = \Config\Database::connect();
        $db->table('com_branch')->insert([
            'company_id'  => (int) $this->request->getPost('company_id'),
            'branch_name' => trim($this->request->getPost('branch_name')),
            'status'      => $this->request->getPost('status') ?: 'active',
        ]);

        return redirect()->to(base_url('branch'))->with('success', 'Branch created successfully.');
    }

    public function update($id = null)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! Rbac::can($this->moduleSlug, 'update') && (int) session()->get('user_role_id') !== Rbac::SUPER_ADMIN_ROLE) {
            return redirect()->back()->with('error', 'You do not have permission to update branches.');
        }

        $db = \Config\Database::connect();
        $db->table('com_branch')->where('id', (int) $id)->update([
            'company_id'  => (int) $this->request->getPost('company_id'),
            'branch_name' => trim($this->request->getPost('branch_name')),
            'status'      => $this->request->getPost('status') ?: 'active',
        ]);

        return redirect()->to(base_url('branch'))->with('success', 'Branch updated successfully.');
    }

    public function delete($id = null)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! Rbac::can($this->moduleSlug, 'delete') && (int) session()->get('user_role_id') !== Rbac::SUPER_ADMIN_ROLE) {
            return redirect()->back()->with('error', 'You do not have permission to delete branches.');
        }

        $db = \Config\Database::connect();
        $db->table('com_branch')->where('id', (int) $id)->delete();

        return redirect()->to(base_url('branch'))->with('success', 'Branch deleted successfully.');
    }
}