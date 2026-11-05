<?php

namespace App\Controllers;

use App\Libraries\ApprovalRequests;
use App\Libraries\ModuleStatuses;
use App\Libraries\Rbac;

/**
 * Saves the "Statuses" tab of Module Settings (POST modules/{id}/statuses).
 * Who may save: Super Admin, the administrators of that module, or "update" on Module Settings (slug module_setting).
 */
class ModuleStatusesController extends BaseController
{
    public function save(int $moduleId)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        $db     = \Config\Database::connect();
        $module = $db->table('rbac_modules')->where('id', $moduleId)->get()->getRowArray();
        if (! $module) {
            return redirect()->to(base_url('modules'))->with('error', 'Module not found.');
        }
        $back  = base_url('modules/configure/' . $moduleId);
        $uid   = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        $super = (int) (session()->get('user_role_id') ?? session()->get('role_id')) === 1;
        if (! ($super || ApprovalRequests::isModuleAdmin((string) $module['slug'], $uid) || Rbac::can('module_setting', 'update'))) {
            return redirect()->to($back)->with('error', 'You do not have permission to change the statuses of this module.')->with('tab', 'statuses');
        }

        $slug     = (string) $module['slug'];
        $existing = (array) ($this->request->getPost('st') ?? []);
        $new      = (array) ($this->request->getPost('new') ?? []);

        // renaming a status also renames it on the stores that use it (Store Details)
        $onRename = static function (string $old, string $newName) use ($slug, $db): void {
            if ($slug === 'store_details') {
                $db->table('store_details')->where('store_status', $old)->update(['store_status' => $newName]);
            }
        };

        $error = ModuleStatuses::save($slug, $existing, $new, $onRename);
        if ($error !== null) {
            return redirect()->to($back)->with('error', $error)->with('tab', 'statuses');
        }

        return redirect()->to($back)->with('success', 'Statuses saved.')->with('tab', 'statuses');
    }
}
