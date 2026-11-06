<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\ModuleStatuses;
use App\Libraries\Rbac;
use App\Models\Rbac_model;

/**
 * Module "Status / Remarks" (slug status_remarks, URL status-remarks): the status lists of every module.
 *   e.g. Store Details > "Store Status";  Inventory > "Phone Status", "Deployment Status", "Receive Status".
 *
 * RBAC actions of this module:  read (open the page)  create (add a list / add statuses)  update (edit)  delete.
 * Super Admin (role_id 1) passes every check. Login is checked in every method.
 */
class StatusRemarks extends BaseController
{
    private const SLUG    = 'status_remarks';
    private const ACTIONS = ['read', 'create', 'update', 'delete'];

    // ------------------------------------------------------------------ page

    public function index()
    {
        if ($deny = $this->srDeny('read', true)) {
            return $deny;
        }
        $db = \Config\Database::connect();

        $modules = $db->table('rbac_modules')->select('id, name, slug')->where('is_category', 0)->orderBy('name', 'ASC')->get()->getResultArray();
        $names   = array_column($modules, 'name', 'slug');

        return view('status_remarks/index', $this->baseData() + [
            'types'   => ModuleStatuses::types(),
            'modules' => $modules,
            'names'   => $names,
            'colors'  => ModuleStatuses::COLORS,
        ]);
    }

    // ------------------------------------------------------------------ actions

    /** POST status-remarks/type/create : a new list (with its first statuses) for a module */
    public function createType()
    {
        if ($deny = $this->srDeny('create', false)) {
            return $deny;
        }
        $slug = trim((string) $this->request->getPost('module_slug'));
        $db   = \Config\Database::connect();
        if ($slug === '' || ! $db->table('rbac_modules')->where('slug', $slug)->countAllResults()) {
            return $this->back('error', 'Choose the module this list is for.');
        }
        $first = preg_split('/\r\n|\r|\n/', (string) $this->request->getPost('first_items')) ?: [];
        $res   = ModuleStatuses::createType($slug, (string) $this->request->getPost('name'), (string) $this->request->getPost('description'), $first);
        if (is_string($res)) {
            return $this->back('error', $res);
        }
        ActivityLog::record(self::SLUG, 'create', 'status_list', $res, 'Created the list "' . trim((string) $this->request->getPost('name')) . '" for ' . $slug, ['module' => $slug, 'first_items' => array_values(array_filter(array_map('trim', $first)))]);

        return $this->back('success', 'The list was created.', $res);
    }

    /** POST status-remarks/type/{id}/save : rename the list, edit / add / delete its statuses */
    public function saveType(int $id)
    {
        if ($deny = $this->srDeny('read', false)) {
            return $deny;
        }
        $db    = \Config\Database::connect();
        $type  = $db->table('status_types')->where('id', $id)->get()->getRowArray();
        if (! $type) {
            return $this->back('error', 'This list was not found.');
        }

        $existing = (array) ($this->request->getPost('st') ?? []);
        $new      = (array) ($this->request->getPost('new') ?? []);
        $delete   = array_values(array_unique(array_map('intval', (array) ($this->request->getPost('del') ?? []))));
        $newName  = trim((string) $this->request->getPost('type_name'));

        // what does this save do? each part needs its own action
        $addsRows = false;
        foreach ($new as $f) {
            if (trim((string) ($f['name'] ?? '')) !== '') {
                $addsRows = true;
                break;
            }
        }
        $edits = $newName !== '' && $newName !== (string) $type['name'];
        $cur   = [];
        foreach ($db->table('status_items')->where('type_id', $id)->get()->getResultArray() as $r) {
            $cur[(int) $r['id']] = $r;
        }
        foreach ($existing as $iid => $f) {
            $r = $cur[(int) $iid] ?? null;
            if ($r && (trim((string) ($f['name'] ?? '')) !== (string) $r['name'] || (string) ($f['color'] ?? '') !== (string) $r['color']
                || (int) ($f['sort'] ?? 0) !== (int) $r['sort_order'] || (int) ! empty($f['active']) !== (int) $r['is_active'])) {
                $edits = true;
            }
        }
        if ($edits && ! $this->allowed('update')) {
            return $this->back('error', 'You do not have permission to edit statuses (action: update).', $id);
        }
        if ($addsRows && ! $this->allowed('create')) {
            return $this->back('error', 'You do not have permission to add statuses (action: create).', $id);
        }
        if ($delete && ! $this->allowed('delete')) {
            return $this->back('error', 'You do not have permission to delete statuses (action: delete).', $id);
        }
        if (! $edits && ! $addsRows && ! $delete) {
            return $this->back('success', 'Nothing was changed.', $id);
        }

        $error = ModuleStatuses::saveItems($id, $newName !== '' ? $newName : null, $existing, $new, $delete);
        if ($error !== null) {
            return $this->back('error', $error, $id);
        }
        ActivityLog::record(self::SLUG, 'update', 'status_list', $id, 'Saved the statuses of "' . $type['name'] . '" (' . $type['module_slug'] . ')', [
            'edited' => $edits, 'added' => $addsRows, 'deleted_ids' => $delete,
        ]);

        return $this->back('success', 'The statuses were saved.', $id);
    }

    /** POST status-remarks/type/{id}/delete */
    public function deleteType(int $id)
    {
        if ($deny = $this->srDeny('delete', false)) {
            return $deny;
        }
        $type  = \Config\Database::connect()->table('status_types')->where('id', $id)->get()->getRowArray();
        $error = ModuleStatuses::deleteType($id);
        if ($error !== null) {
            return $this->back('error', $error, $id);
        }
        ActivityLog::record(self::SLUG, 'delete', 'status_list', $id, 'Deleted the list "' . ($type['name'] ?? '') . '" (' . ($type['module_slug'] ?? '') . ')', []);

        return $this->back('success', 'The list was deleted.');
    }

    // ------------------------------------------------------------------ helpers

    private function back(string $kind, string $message, ?int $typeId = null)
    {
        return redirect()->to(base_url('status-remarks') . ($typeId ? '#type-' . $typeId : ''))->with($kind, $message);
    }

    private function baseData(): array
    {
        $userId = session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id');
        $roleId = session()->get('user_role_id') ?? session()->get('role_id');

        return [
            'title'           => 'Status / Remarks',
            'pageTitle'       => 'Status / Remarks',
            'filteredModules' => (new Rbac_model())->get_user_modules($userId, $roleId),
            'can'             => $this->abilitiesMap(),
            'breadcrumbs'     => [['label' => 'Home', 'url' => 'dashboard'], ['label' => 'Status / Remarks']],
        ];
    }

    private function allowed(string $action): bool
    {
        $role = (int) (session()->get('user_role_id') ?? session()->get('role_id'));

        return $role === 1 || Rbac::can(self::SLUG, $action);
    }

    /** @return array<string,bool> */
    private function abilitiesMap(): array
    {
        $out = [];
        foreach (self::ACTIONS as $a) {
            $out[$a] = $this->allowed($a);
        }

        return $out;
    }

    private function srDeny(string $action, bool $page)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! $this->allowed($action)) {
            return $page
                ? redirect()->to(base_url('dashboard'))->with('error', 'You do not have permission to open Status / Remarks.')
                : redirect()->to(base_url('status-remarks'))->with('error', 'You do not have permission to do this (Status / Remarks: ' . $action . ').');
        }

        return null;
    }
}