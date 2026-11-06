<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Rbac;
use App\Models\Rbac_model;

/**
 * Company Structure (URL organization/structure): which departments and positions each company may use
 * (table org_company_department_positions, one row per company + department + position).
 *
 * Uses the RBAC module "organization":  read = open the page,  update = save the matrix.
 * A combination that an ACTIVE assignment of that company still uses cannot be switched off.
 * Switched-off combinations are kept as status = inactive (history stays valid, the unique key stays free).
 */
class OrganizationStructure extends BaseController
{
    private const SLUG = 'organization';

    public function index()
    {
        if ($deny = $this->deny('read', true)) {
            return $deny;
        }
        $db = \Config\Database::connect();

        $companies = $db->table('com_company')->where('status', 'active')->orderBy('name', 'ASC')->get()->getResultArray();
        $companyId = (int) $this->request->getGet('company');
        if (! in_array($companyId, array_map('intval', array_column($companies, 'id')), true)) {
            $companyId = $companies ? (int) $companies[0]['id'] : 0;
        }

        $departments = $db->table('org_departments')->where('status', 'active')->orderBy('name', 'ASC')->get()->getResultArray();
        $positions   = $db->table('org_positions')->where('status', 'active')->orderBy('level', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray();

        $selected = $companyId ? $this->activeKeys($db, $companyId) : [];
        $copyFrom = (int) $this->request->getGet('copy');
        $copied   = false;
        if ($companyId && $copyFrom > 0 && $copyFrom !== $companyId && in_array($copyFrom, array_map('intval', array_column($companies, 'id')), true)) {
            $selected = $this->activeKeys($db, $copyFrom);   // only pre-fills the boxes: nothing is saved until "Save structure"
            $copied   = true;
        }

        return view('organization/structure', $this->baseData() + [
            'companies'   => $companies,
            'companyId'   => $companyId,
            'departments' => $departments,
            'positions'   => $positions,
            'selected'    => $selected,
            'used'        => $companyId ? $this->usedKeys($db, $companyId) : [],
            'copied'      => $copied,
            'copyFrom'    => $copyFrom,
        ]);
    }

    /** POST organization/structure/save */
    public function save()
    {
        if ($deny = $this->deny('update', false)) {
            return $deny;
        }
        $db        = \Config\Database::connect();
        $companyId = (int) $this->request->getPost('company_id');
        $company   = $db->table('com_company')->where('id', $companyId)->where('status', 'active')->get()->getRowArray();
        if (! $company) {
            return $this->back('error', 'Choose a valid company.', 0);
        }

        $deps = array_map('intval', array_column($db->table('org_departments')->select('id')->where('status', 'active')->get()->getResultArray(), 'id'));
        $pos  = array_map('intval', array_column($db->table('org_positions')->select('id')->where('status', 'active')->get()->getResultArray(), 'id'));

        // wanted combinations: cell[department_id][] = position_id  (only active departments and positions count)
        $want   = [];
        $posted = $this->request->getPost('cell');
        foreach ((is_array($posted) ? $posted : []) as $d => $list) {
            $d = (int) $d;
            if (! in_array($d, $deps, true) || ! is_array($list)) {
                continue;
            }
            foreach ($list as $p) {
                $p = (int) $p;
                if (in_array($p, $pos, true)) {
                    $want[$d . ':' . $p] = [$d, $p];
                }
            }
        }

        // what exists now (any status) for the departments / positions shown in the matrix
        $existing = [];
        foreach ($db->table('org_company_department_positions')->where('company_id', $companyId)->get()->getResultArray() as $r) {
            $existing[(int) $r['department_id'] . ':' . (int) $r['position_id']] = $r;
        }

        $add = $react = $off = [];
        foreach ($want as $k => [$d, $p]) {
            if (! isset($existing[$k])) {
                $add[$k] = [$d, $p];
            } elseif ($existing[$k]['status'] !== 'active') {
                $react[$k] = $existing[$k];
            }
        }
        foreach ($existing as $k => $r) {
            if ($r['status'] === 'active' && ! isset($want[$k]) && in_array((int) $r['department_id'], $deps, true) && in_array((int) $r['position_id'], $pos, true)) {
                $off[$k] = $r;
            }
        }

        // a combination that active employees still use cannot be switched off
        $used = $this->usedKeys($db, $companyId);
        $blocked = [];
        foreach ($off as $k => $r) {
            if (! empty($used[$k])) {
                $blocked[] = $this->label($db, (int) $r['department_id'], (int) $r['position_id']) . ' (' . $used[$k] . ')';
            }
        }
        if ($blocked) {
            return $this->back('error', 'These are still used by active employees and cannot be removed: ' . implode(', ', array_slice($blocked, 0, 6)) . (count($blocked) > 6 ? ' …' : '') . '.', $companyId);
        }

        $now = date('Y-m-d H:i:s');
        $db->transBegin();
        foreach ($add as [$d, $p]) {
            $db->table('org_company_department_positions')->insert(['company_id' => $companyId, 'department_id' => $d, 'position_id' => $p, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        $ids = array_map(static fn ($r) => (int) $r['id'], array_merge(array_values($react)));
        if ($ids) {
            $db->table('org_company_department_positions')->whereIn('id', $ids)->update(['status' => 'active', 'updated_at' => $now]);
        }
        $offIds = array_map(static fn ($r) => (int) $r['id'], array_values($off));
        if ($offIds) {
            $db->table('org_company_department_positions')->whereIn('id', $offIds)->update(['status' => 'inactive', 'updated_at' => $now]);
        }
        if ($db->transStatus() === false) {
            $db->transRollback();

            return $this->back('error', 'The structure could not be saved. Please try again.', $companyId);
        }
        $db->transCommit();

        $added = count($add) + count($react);
        ActivityLog::record(self::SLUG, 'update', 'company_structure', $companyId, 'Saved the structure of "' . $company['name'] . '": ' . $added . ' added, ' . count($off) . ' removed', [
            'added' => $added, 'removed' => count($off), 'total_active' => count($want),
        ]);

        return $this->back('success', 'Structure of "' . $company['name'] . '" saved (' . count($want) . ' department / position combinations).', $companyId);
    }

    // ------------------------------------------------------------------ helpers

    /** @return array<string,true> "department:position" => true (active rows of a company) */
    private function activeKeys($db, int $companyId): array
    {
        $out = [];
        foreach ($db->table('org_company_department_positions')->where('company_id', $companyId)->where('status', 'active')->get()->getResultArray() as $r) {
            $out[(int) $r['department_id'] . ':' . (int) $r['position_id']] = true;
        }

        return $out;
    }

    /** @return array<string,int> "department:position" => number of ACTIVE assignments of this company using it */
    private function usedKeys($db, int $companyId): array
    {
        $out = [];
        if (! $db->tableExists('org_user_assignments')) {
            return $out;
        }
        foreach ($db->table('org_user_assignments')->select('department_id, position_id, COUNT(*) AS n', false)->where('company_id', $companyId)->where('status', 'active')->groupBy('department_id, position_id')->get()->getResultArray() as $r) {
            $out[(int) $r['department_id'] . ':' . (int) $r['position_id']] = (int) $r['n'];
        }

        return $out;
    }

    private function label($db, int $dep, int $pos): string
    {
        $d = $db->table('org_departments')->select('name')->where('id', $dep)->get()->getRowArray();
        $p = $db->table('org_positions')->select('name')->where('id', $pos)->get()->getRowArray();

        return ($d['name'] ?? ('#' . $dep)) . ' / ' . ($p['name'] ?? ('#' . $pos));
    }

    private function back(string $kind, string $message, int $companyId)
    {
        return redirect()->to(base_url('organization/structure') . ($companyId ? '?company=' . $companyId : ''))->with($kind, $message);
    }

    private function baseData(): array
    {
        $userId = session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id');
        $roleId = session()->get('user_role_id') ?? session()->get('role_id');

        return [
            'title'           => 'Company Structure',
            'pageTitle'       => 'Organization / Company Structure',
            'filteredModules' => (new Rbac_model())->get_user_modules($userId, $roleId),
            'can'             => ['read' => $this->allowed('read'), 'update' => $this->allowed('update')],
            'breadcrumbs'     => [['label' => 'Home', 'url' => 'dashboard'], ['label' => 'Organization', 'url' => 'organization'], ['label' => 'Company Structure']],
        ];
    }

    private function allowed(string $action): bool
    {
        $role = (int) (session()->get('user_role_id') ?? session()->get('role_id'));

        return $role === Rbac::SUPER_ADMIN_ROLE || Rbac::can(self::SLUG, $action);
    }

    private function deny(string $action, bool $page)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! $this->allowed($action)) {
            return $page
                ? redirect()->to(base_url('dashboard'))->with('error', 'You do not have permission to open Organization.')
                : redirect()->to(base_url('organization/structure'))->with('error', 'You do not have permission to do this (Organization: ' . $action . ').');
        }

        return null;
    }
}
