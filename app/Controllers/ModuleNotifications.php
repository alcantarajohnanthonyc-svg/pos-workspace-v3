<?php

namespace App\Controllers;

use App\Libraries\ApprovalRequests;
use App\Libraries\Notifier;
use App\Libraries\Rbac;

/**
 * Saves the "Notifications" tab of Module Settings (POST modules/{id}/notifications).
 *
 * Who may save: Super Admin (role_id 1, always), the administrators of that module, or a user who has the
 * "update" action on the Module Settings module (slug module_setting).
 */
class ModuleNotifications extends BaseController
{
    private const SETTINGS_SLUG = 'module_setting';

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

        $uid   = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        $super = (int) (session()->get('user_role_id') ?? session()->get('role_id')) === 1;
        $ok    = $super || ApprovalRequests::isModuleAdmin((string) $module['slug'], $uid) || Rbac::can(self::SETTINGS_SLUG, 'update');
        if (! $ok) {
            return redirect()->to(base_url('modules/configure/' . $moduleId))->with('error', 'You do not have permission to change the notification settings of this module.')->with('tab', 'notifications');
        }

        $events = $this->request->getPost('ev');
        $events = is_array($events) ? $events : [];
        $pref   = (string) $this->request->getPost('default_pref');

        if (! Notifier::saveSettings($moduleId, $events, $pref, $uid)) {
            return redirect()->to(base_url('modules/configure/' . $moduleId))->with('error', 'The notification settings could not be saved. Please try again.')->with('tab', 'notifications');
        }

        return redirect()->to(base_url('modules/configure/' . $moduleId))->with('success', 'Notification settings saved.')->with('tab', 'notifications');
    }
}
