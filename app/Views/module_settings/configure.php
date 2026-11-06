<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper('module');

$m                = $module ?? [];
$permissions      = $permissions ?? [];
$availableActions = $availableActions ?? [];
$children         = $children ?? [];
$parentModules    = $parentModules ?? [];
$accounts         = $accounts ?? [];
$assignedAdminIds = $assignedAdminIds ?? [];

$can       = $can ?? [];          // deny by default
$canEdit   = ! empty($can['update']);
$canDelete = ! empty($can['delete']);
$canToggle = ! empty($can['toggle_status']);
$canAdmins   = ! empty($can['manage_admins']);     // assign / remove administrators
$canApproval = ! empty($can['manage_approval']);   // save approval flags (full update OR admin of this module)

$mid        = (int) ($m['id'] ?? 0);
$isCategory = (int) ($m['is_category'] ?? 0) === 1;
$enabled    = strtolower((string) ($m['status'] ?? 'enabled')) === 'enabled';
$locked     = ($m['slug'] ?? '') === 'module_setting';   // can never be switched off

$flashSuccess = session()->getFlashdata('success');
$flashError   = session()->getFlashdata('error');
$startTab     = session()->getFlashdata('tab');
if (! in_array($startTab, ['details', 'access', 'admins', 'scope', 'notifications'], true)) { $startTab = 'details'; }

$btnPrim  = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2';
$btnGhost = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-semibold rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';
$closeBtn = 'flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';
$dlgClass = 'm-auto w-[calc(100vw-2rem)] rounded-xl p-0 text-slate-800 shadow-xl backdrop:bg-slate-900/50';

$row = static function (string $l, string $v, bool $raw = false): void {
    echo '<div class="flex gap-3 py-2"><dt class="w-32 flex-shrink-0 text-slate-500">' . esc($l) . '</dt>'
       . '<dd class="min-w-0 break-words font-medium text-slate-800">' . ($raw ? $v : esc($v)) . '</dd></div>';
};
$none = '<span class="font-normal text-slate-400">—</span>';

// Avatar: Google URL or local path (uploads/avatars/...) -> full URL; '' when none
$avatarUrl = static function (?string $a): string {
    $a = trim((string) $a);
    if ($a === '') { return ''; }
    return preg_match('#^https?://#i', $a) ? $a : base_url($a);
};
// Circle avatar. The user icon is the fallback: if the image fails to load it is removed and the icon shows.
$avatar = static function (string $url, string $size, string $tone = 'bg-emerald-100 text-emerald-700') use ($avatarUrl): string {
    $img = $url !== ''
        ? '<img src="' . esc($url, 'attr') . '" alt="" referrerpolicy="no-referrer" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">'
        : '';
    return '<span class="relative inline-flex ' . $size . ' flex-shrink-0 items-center justify-center overflow-hidden rounded-full text-[10px] ' . $tone . '"><i class="fa-solid fa-user"></i>' . $img . '</span>';
};

// Kunin ang mga assigned admin details para sa banner sa itaas
$assignedAdminsList = [];
foreach ($accounts as $acc) {
    if (in_array((int) $acc['id'], $assignedAdminIds, true)) {
        $accName = trim(($acc['first_name'] ?? '') . ' ' . ($acc['last_name'] ?? ''));
        if ($accName === '') { $accName = $acc['full_name'] ?? ($acc['name'] ?? ($acc['username'] ?? ($acc['email'] ?? 'User'))); }
        $assignedAdminsList[] = [
            'id' => $acc['id'],
            'name' => $accName,
            'role' => $acc['role_name'] ?? 'Staff',
            'avatar' => $avatarUrl($acc['avatar'] ?? '')
        ];
    }
}
?>
<div class="space-y-4 pb-8">

    <?php if ($flashSuccess): ?>
        <div role="status" class="flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs text-emerald-800">
            <i class="fa-solid fa-circle-check mt-0.5 text-emerald-600"></i><span><?= esc($flashSuccess) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div role="alert" class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-800">
            <i class="fa-solid fa-circle-exclamation mt-0.5 text-red-600"></i><span><?= esc($flashError) ?></span>
        </div>
    <?php endif; ?>

    <div>
        <a href="<?= esc(base_url('modules'), 'attr') ?>"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
            <i class="fa-solid fa-arrow-left"></i> Back to Module Settings
        </a>
    </div>

    <!-- UPPER BANNER: MODULE USERS & ADMINISTRATORS SUMMARY -->
    <section class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-12 sm:p-5 items-center">
        <div class="flex min-w-0 items-center gap-3 sm:col-span-5">
            <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl bg-emerald-50 text-2xl text-emerald-600 <?= $enabled ? '' : 'opacity-50' ?>">
                <?= module_icon_html($m['icon'] ?? '', 'h-full w-full p-2') ?>
            </div>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="truncate text-base font-semibold text-slate-800"><?= esc($m['name'] ?? '') ?></h1>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold <?= $enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span>
                </div>
                <p class="mt-0.5 truncate font-mono text-[11px] text-slate-500"><?= esc($m['slug'] ?? '') ?><?= ! empty($m['url']) ? ' · /' . esc($m['url']) : '' ?></p>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:col-span-7 items-start sm:items-center justify-between gap-3 border-t sm:border-t-0 sm:border-l border-slate-200 pt-3 sm:pt-0 sm:pl-4">
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 flex items-center gap-1.5 mb-1.5">
                    <i class="fa-solid fa-users-gear text-emerald-600"></i> Module Users & Administrators
                </div>
                <div class="flex flex-wrap items-center gap-4 text-xs">
                    <div>
                        <span class="text-slate-500">Administrators:</span> 
                        <strong class="text-slate-800"><?= count($assignedAdminIds) ?> admins</strong>
                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                            <?php if (!empty($assignedAdminsList)): ?>
                                <?php foreach ($assignedAdminsList as $adm): ?>
                                    <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full px-2.5 py-0.5 text-[11px] font-semibold">
                                        <?= $avatar($adm['avatar'], 'h-4 w-4', 'bg-emerald-200 text-emerald-800') ?>
                                        <?= esc($adm['name']) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-slate-400 italic text-[11px]">No administrators assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($canToggle && ! $locked): ?>
                <button type="button" data-open-modal="modal-status"
                        class="<?= $enabled ? $btnGhost : $btnPrim ?> flex-shrink-0">
                    <i class="fa-solid <?= $enabled ? 'fa-ban' : 'fa-circle-check' ?>"></i> <?= $enabled ? 'Disable module' : 'Enable module' ?>
                </button>
            <?php endif; ?>
        </div>
    </section>

    <!-- TABS -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto border-b border-slate-200 px-4 sm:px-5">
            <nav class="flex min-w-max gap-5" role="tablist" aria-label="Module sections">
                <?php foreach ([['details', 'Module Details', 1], ['access', 'Module Access', 2], ['admins', 'Module Administrators', 3], ['scope', 'Company / Branch Scope', 4], ['notifications', 'Notifications', 5]] as [$key, $text, $num]): ?>
                    <button type="button" role="tab" data-tab="<?= $key ?>" id="btn-tab-<?= $key ?>" aria-controls="content-tab-<?= $key ?>"
                            class="tab-link flex items-center gap-2 whitespace-nowrap rounded-t border-b-2 border-transparent py-3 text-xs font-medium text-slate-500 transition-colors hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                        <span class="tab-num flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-600"><?= $num ?></span>
                        <?= $text ?>
                        <?php if ($key === 'access'): ?><span class="rounded-full bg-slate-100 px-1.5 text-[10px] font-semibold text-slate-600"><?= count($permissions) ?></span><?php endif; ?>
                        <?php if ($key === 'admins'): ?><span class="rounded-full bg-slate-100 px-1.5 text-[10px] font-semibold text-slate-600"><?= count($assignedAdminIds) ?></span><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="p-4 sm:p-5">

            <!-- 1. DETAILS -->
            <div id="content-tab-details" class="tab-content" role="tabpanel" aria-labelledby="btn-tab-details">
                <div class="rounded-lg border border-slate-200 p-4">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-800">Module Information</h2>
                        <?php if ($canEdit): ?>
                            <button type="button" data-open-modal="modal-edit" class="<?= $btnGhost ?>"><i class="fa-solid fa-pen"></i> Edit</button>
                        <?php endif; ?>
                    </div>
                    <dl class="grid grid-cols-1 text-xs md:grid-cols-2 md:gap-x-8">
                        <div class="divide-y divide-slate-100">
                            <?php
                            $row('Module ID', (string) $mid);
                            $row('Name', (string) ($m['name'] ?? ''));
                            $row('Slug', '<span class="font-mono">' . esc($m['slug'] ?? '') . '</span>', true);
                            $row('Type', $isCategory ? 'Category container' : 'Functional module');
                            $row('Parent Category', ! empty($m['parent_name']) ? (string) $m['parent_name'] : '', false);
                            $row('Page URL', ! empty($m['url']) ? '<span class="font-mono">/' . esc($m['url']) . '</span>' : $none, true);
                            ?>
                        </div>
                        <div class="divide-y divide-slate-100 border-t border-slate-100 md:border-t-0">
                            <?php
                            $row('Status', '<span class="rounded-full px-2 py-0.5 text-[10px] font-semibold ' . ($enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600') . '">' . ($enabled ? 'Enabled' : 'Disabled') . '</span>', true);
                            $iconTxt = module_icon_is_image($m['icon'] ?? '') ? 'Uploaded image' : (string) ($m['icon'] ?? '');
                            $row('Icon', '<span class="inline-flex items-center gap-2"><span class="inline-flex h-6 w-6 items-center justify-center overflow-hidden rounded bg-emerald-50 text-emerald-600">'
                                . module_icon_html($m['icon'] ?? '', 'h-full w-full p-0.5') . '</span><span class="font-mono text-[11px] text-slate-500">' . esc($iconTxt) . '</span></span>', true);
                            $row('Badge', ! empty($m['badge']) ? '<span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-sky-700">' . esc($m['badge']) . '</span>' : $none, true);
                            $row('Display Order', (string) (int) ($m['short_order'] ?? 0));
                            $row('Permissions', $isCategory ? 'Not applicable' : (string) count($permissions));
                            ?>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- 2. ACCESS -->
            <div id="content-tab-access" class="tab-content hidden" role="tabpanel" aria-labelledby="btn-tab-access">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-800">Module Permissions</h2>
                        <p class="text-[11px] text-slate-500">Actions that can be granted to roles and accounts for this module.</p>
                    </div>
                    <?php if ($canEdit && ! $isCategory): ?>
                        <button type="button" data-open-modal="modal-perm" class="<?= $btnPrim ?>" <?= $availableActions ? '' : 'disabled title="Every action is already added"' ?>>
                            <i class="fa-solid fa-plus"></i> Add Permission
                        </button>
                    <?php endif; ?>
                </div>

                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase text-slate-600">
                            <tr>
                                <th scope="col" class="px-3 py-2.5">Action</th>
                                <th scope="col" class="px-3 py-2.5">Key</th>
                                <th scope="col" class="px-3 py-2.5">Permission Slug</th>
                                <th scope="col" class="px-3 py-2.5 text-center">Roles</th>
                                <th scope="col" class="px-3 py-2.5 text-center">Accounts</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($permissions as $p): ?>
                                <tr>
                                    <td class="px-3 py-2.5 font-semibold text-slate-800"><?= esc($p['action_name']) ?></td>
                                    <td class="px-3 py-2.5 font-mono font-semibold text-emerald-700"><?= esc($p['action_key']) ?></td>
                                    <td class="px-3 py-2.5 font-mono text-[11px] text-slate-500"><?= esc($p['permission_slug']) ?></td>
                                    <td class="px-3 py-2.5 text-center"><?= (int) $p['role_count'] ?></td>
                                    <td class="px-3 py-2.5 text-center"><?= (int) $p['user_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (! $permissions): ?>
                                <tr><td colspan="5" class="px-3 py-8 text-center text-slate-400">No permissions found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. MODULE ADMINISTRATORS & APPROVAL SETTINGS TAB -->
            <div id="content-tab-admins" class="tab-content hidden" role="tabpanel" aria-labelledby="btn-tab-admins">
                <form action="<?= esc(base_url('modules/' . $mid . '/admins'), 'attr') ?>" method="POST" class="space-y-6" id="admins-form">
                    <?= csrf_field() ?>
                    
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-800">MODULE ADMINISTRATORS</h2>
                            <p class="text-[11px] text-slate-500">Select the users who will be assigned as administrators for this module. Administrators can manage the module's actions, including approval settings.</p>
                        </div>
                        <?php if ($canApproval): ?>
                            <button type="submit" class="<?= $btnPrim ?>">
                                <i class="fa-solid fa-floppy-disk"></i> Save Changes
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        
                        <!-- LEFT: ASSIGNED ADMINISTRATORS LIST (NO CHECKBOXES/SEARCH, JUST + ADD BUTTON) -->
                        <div class="lg:col-span-5 space-y-3 rounded-lg border border-slate-200 p-4 bg-white shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-semibold text-slate-800 uppercase">Assigned Administrators</h3>
                                <?php if ($canAdmins): ?>
                                    <button type="button" data-open-modal="modal-add-admin" class="inline-flex items-center gap-1 text-xs text-emerald-600 font-semibold hover:underline">
                                        <i class="fa-solid fa-plus"></i> Add
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-slate-200 max-h-64 overflow-y-auto">
                                <table class="w-full text-left text-xs text-slate-600">
                                    <thead class="bg-slate-50 text-[10px] uppercase font-semibold text-slate-500 border-b border-slate-200 sticky top-0">
                                        <tr>
                                            <th class="py-2.5 px-3">User</th>
                                            <th class="py-2.5 px-3">Role</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100" id="assigned-admins-view-list">
                                        <?php if (!empty($assignedAdminsList)): ?>
                                            <?php foreach ($assignedAdminsList as $adm): ?>
                                                <tr class="hover:bg-slate-50">
                                                    <td class="py-2.5 px-3 font-medium text-slate-800 flex items-center gap-2">
                                                        <?= $avatar($adm['avatar'], 'h-6 w-6') ?>
                                                        <?= esc($adm['name']) ?>
                                                        <input type="hidden" name="admin_ids[]" value="<?= (int) $adm['id'] ?>">
                                                    </td>
                                                    <td class="py-2.5 px-3 text-slate-500"><?= esc($adm['role']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr id="no-assigned-row">
                                                <td colspan="2" class="py-6 text-center text-slate-400 italic">No assigned administrators. Click "+ Add" to add users.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-[11px] text-slate-500 text-right"><span id="assigned-count-lbl"><?= count($assignedAdminIds) ?></span> administrators assigned</div>
                        </div>

                        <!-- MIDDLE: MODULE ACTIONS & REQUIRES APPROVAL TOGGLES -->
                        <div class="lg:col-span-4 space-y-3 rounded-lg border border-slate-200 p-4 bg-white shadow-sm">
                            <h3 class="text-xs font-semibold text-slate-800 uppercase">Module Actions</h3>
                            <p class="text-[10px] text-slate-500">Configure which actions for this module require administrator approval.</p>

                            <div class="overflow-x-auto rounded-lg border border-slate-200">
                                <table class="w-full text-left text-xs text-slate-600">
                                    <thead class="bg-slate-50 text-[10px] uppercase font-semibold text-slate-500 border-b border-slate-200">
                                        <tr>
                                            <th class="py-2 px-3">Action</th>
                                            <th class="py-2 px-3 text-center">Requires Approval</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-[11px]">
                                        <?php if (!empty($permissions)): ?>
                                            <?php foreach ($permissions as $p): ?>
                                                <tr>
                                                    <td class="py-2.5 px-3 font-medium text-slate-800">
                                                        <?= esc($p['action_name']) ?>
                                                        <span class="block text-[9px] font-mono text-slate-400"><?= esc($p['action_key']) ?></span>
                                                    </td>
                                                    <td class="py-2.5 px-3 text-center">
                                                        <input type="checkbox" name="requires_approval[]" value="<?= (int) $p['pid'] ?>"
                                                            <?= ! empty($p['requires_approval']) ? 'checked' : '' ?> <?= $canApproval ? '' : 'disabled' ?>
                                                            class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="2" class="py-4 text-center text-slate-400">No actions configured for this module.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- RIGHT: APPROVAL SETTINGS SUMMARY PANEL -->
                        <div class="lg:col-span-3 space-y-3 rounded-lg border border-emerald-200 bg-emerald-50/40 p-4 shadow-sm self-start">
                            <div class="flex items-center gap-2 text-xs font-semibold text-emerald-800">
                                <i class="fa-solid fa-shield-halved text-emerald-600"></i> Approval Settings
                            </div>
                            <p class="text-[11px] text-slate-600">Actions configured to require approval will need authorization from assigned administrators before taking effect.</p>

                            <div class="border-t border-emerald-200 pt-3 space-y-2">
                                <div class="text-[11px] font-semibold text-slate-700 flex items-center gap-1.5">
                                    <i class="fa-solid fa-users text-slate-500"></i> Assigned Administrators
                                </div>
                                <div class="text-[11px] font-medium text-slate-800" id="summary-assigned-names">
                                    <?php if (!empty($assignedAdminsList)): ?>
                                        <?= esc(implode(', ', array_column($assignedAdminsList, 'name'))) ?>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">None assigned</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="rounded bg-sky-50 border border-sky-200 p-2.5 flex items-start gap-2 text-[10px] text-sky-900">
                                <i class="fa-solid fa-circle-info mt-0.5 text-sky-600"></i>
                                <span>Only the selected administrators can approve pending requests for the actions that require approval.</span>
                            </div>
                        </div>

                    </div>
                </form>
            </div>

            <!-- 4. COMPANY / BRANCH SCOPE TAB -->
            <div id="content-tab-scope" class="tab-content hidden" role="tabpanel" aria-labelledby="btn-tab-scope">
                <form action="<?= esc(base_url('modules/' . $mid . '/scope'), 'attr') ?>" method="POST" class="space-y-6">
                    <?= csrf_field() ?>
                    
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-800">Company / Branch Scope</h2>
                            <p class="text-[11px] text-slate-500">This setting controls if the module will be limited by company and branch.</p>
                        </div>
                        <?php if ($canEdit): ?>
                            <button type="submit" class="<?= $btnPrim ?>">
                                <i class="fa-solid fa-floppy-disk"></i> Save Changes
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        
                        <!-- LEFT: TOGGLE SWITCHES -->
                        <div class="lg:col-span-8 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Company Filtering Card -->
                                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-slate-800">Company Filtering</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="has_company_filter" value="1" class="sr-only peer" <?= !empty($m['has_company_filter']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                                            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                                        </label>
                                    </div>
                                    <p class="text-[11px] text-slate-500">Only show data from assigned companies.</p>
                                </div>

                                <!-- Branch Filtering Card -->
                                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-slate-800">Branch Filtering</span>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="has_branch_filter" value="1" class="sr-only peer" <?= !empty($m['has_branch_filter']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                                            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                                        </label>
                                    </div>
                                    <p class="text-[11px] text-slate-500">Only show data from assigned branches.</p>
                                </div>
                            </div>

                            <!-- Info Box -->
                            <div class="rounded-lg border border-sky-200 bg-sky-50/60 p-3.5 flex items-start gap-2.5 text-xs text-sky-900">
                                <i class="fa-solid fa-circle-info mt-0.5 text-sky-600"></i>
                                <div>
                                    <span class="font-semibold block mb-0.5">Note:</span>
                                    Actual companies and branches are configured per user in User Management. This setting only enables or disables the filtering feature for this module.
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT: SUMMARY PANEL -->
                        <div class="lg:col-span-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-4 self-start">
                            <div>
                                <h3 class="text-xs font-semibold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="fa-solid fa-shield-halved text-emerald-600"></i> Module Scope Summary
                                </h3>
                            </div>

                            <div class="space-y-2.5 border-t border-slate-100 pt-3 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600">Company Filtering</span>
                                    <span class="font-semibold px-2 py-0.5 rounded text-[10px] <?= !empty($m['has_company_filter']) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                        <?= !empty($m['has_company_filter']) ? 'ON' : 'OFF' ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600">Branch Filtering</span>
                                    <span class="font-semibold px-2 py-0.5 rounded text-[10px] <?= !empty($m['has_branch_filter']) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                        <?= !empty($m['has_branch_filter']) ? 'ON' : 'OFF' ?>
                                    </span>
                                </div>
                            </div>

                            <div class="border-t border-slate-100 pt-3 space-y-2">
                                <span class="text-[11px] font-semibold uppercase text-slate-500 block">Applied To Actions</span>
                                <div class="space-y-1.5 text-xs text-slate-700">
                                    <?php foreach ($permissions as $p): ?>
                                        <div class="flex items-center gap-2">
                                            <i class="fa-solid fa-check text-emerald-600 text-[10px]"></i>
                                            <span><?= esc($p['action_name']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($permissions)): ?>
                                        <span class="text-slate-400 italic text-[11px]">No actions configured yet.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
            </div>

            <!-- 5. NOTIFICATIONS TAB (In-App / Email per event) -->
            <div id="content-tab-notifications" class="tab-content hidden" role="tabpanel" aria-labelledby="btn-tab-notifications">
                <?= view('module_settings/_tab_notifications', ['moduleId' => $mid, 'canEdit' => ($canEdit || $canApproval)], ['saveData' => false]) ?>
            </div>


        </div>
    </section>
</div>

<!-- ============ POPUP MODAL: ADD USERS AS ADMINISTRATORS ============ -->
<dialog id="modal-add-admin" aria-labelledby="modal-add-admin-title" class="<?= $dlgClass ?> max-w-lg">
    <div class="flex max-h-[85vh] flex-col">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 id="modal-add-admin-title" class="text-sm font-semibold">Select Users to Add as Administrators</h2>
            <button type="button" data-close-modal aria-label="Close" class="<?= $closeBtn ?>"><i class="fa-solid fa-xmark"></i></button>
        </div>
        
        <div class="p-5 space-y-3 overflow-y-auto">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="search" id="modal-search-users" placeholder="Search user..." class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-9 pr-3 text-xs focus:border-emerald-600 focus:outline-none">
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-200 max-h-60 overflow-y-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-[10px] uppercase font-semibold text-slate-500 border-b border-slate-200 sticky top-0">
                        <tr>
                            <th class="py-2 px-3 w-8 text-center"><input type="checkbox" id="modal-select-all" class="rounded border-slate-300 text-emerald-600"></th>
                            <th class="py-2 px-3">User Name</th>
                            <th class="py-2 px-3">Role</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="modal-users-tbody">
                        <?php foreach ($accounts as $acc):
                            $accName = trim(($acc['first_name'] ?? '') . ' ' . ($acc['last_name'] ?? ''));
                            if ($accName === '') { $accName = $acc['full_name'] ?? ($acc['name'] ?? ($acc['username'] ?? ($acc['email'] ?? 'User'))); }
                            $isAlreadyAssigned = in_array((int) $acc['id'], $assignedAdminIds, true);
                        ?>
                            <tr class="hover:bg-slate-50 modal-user-row" data-id="<?= (int) $acc['id'] ?>" data-name="<?= esc($accName) ?>" data-role="<?= esc($acc['role_name'] ?? 'Staff') ?>" data-avatar="<?= esc($avatarUrl($acc['avatar'] ?? ''), 'attr') ?>">
                                <td class="py-2 px-3 text-center">
                                    <input type="checkbox" class="modal-user-checkbox rounded border-slate-300 text-emerald-600"
                                        value="<?= (int) $acc['id'] ?>" <?= $isAlreadyAssigned ? 'checked' : '' ?>>
                                </td>
                                <td class="py-2 px-3 font-medium text-slate-800 flex items-center gap-2">
                                    <?= $avatar($avatarUrl($acc['avatar'] ?? ''), 'h-6 w-6', 'bg-slate-100 text-slate-600') ?>
                                    <?= esc($accName) ?>
                                </td>
                                <td class="py-2 px-3 text-slate-500"><?= esc($acc['role_name'] ?? 'Staff') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (! $accounts): ?>
                            <tr><td colspan="3" class="py-6 text-center text-slate-400">No users found. Check the accounts table name in ModuleSettings::accountOptions().</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close-modal class="<?= $btnGhost ?>">Cancel</button>
            <button type="button" id="btn-confirm-add-admins" class="<?= $btnPrim ?>"><i class="fa-solid fa-plus"></i> Add Selected Users</button>
        </div>
    </div>
</dialog>

<!-- ============ EDIT DETAILS ============ -->
<?php if ($canEdit): ?>
<dialog id="modal-edit" aria-labelledby="modal-edit-title" class="<?= $dlgClass ?> max-w-2xl">
    <form action="<?= esc(base_url('modules/' . $mid . '/details'), 'attr') ?>" method="POST" enctype="multipart/form-data" class="flex max-h-[90vh] flex-col">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 id="modal-edit-title" class="text-sm font-semibold">Edit Module Details</h2>
            <button type="button" data-close-modal aria-label="Close" class="<?= $closeBtn ?>"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="overflow-y-auto px-5 py-4">
            <?= view('module_settings/fields', ['prefix' => 'edit', 'mode' => 'edit', 'm' => $m, 'parentModules' => $parentModules], ['saveData' => false]) ?>
        </div>
        <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close-modal class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<!-- ============ ENABLE / DISABLE ============ -->
<?php if ($canToggle && ! $locked): ?>
<dialog id="modal-status" aria-labelledby="modal-status-title" class="<?= $dlgClass ?> max-w-md">
    <form action="<?= esc(base_url('modules/' . $mid . '/status'), 'attr') ?>" method="POST" class="flex flex-col">
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="<?= $enabled ? 'disabled' : 'enabled' ?>">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 id="modal-status-title" class="text-sm font-semibold"><?= $enabled ? 'Disable' : 'Enable' ?> module</h2>
            <button type="button" data-close-modal aria-label="Close" class="<?= $closeBtn ?>"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-2 px-5 py-4 text-xs text-slate-600">
            <p><?= $enabled ? 'Disable' : 'Enable' ?> <strong><?= esc($m['name'] ?? '') ?></strong>?</p>
        </div>
        <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close-modal class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" class="<?= $btnPrim ?>"><?= $enabled ? 'Disable module' : 'Enable module' ?></button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script>
(function () {
    var ON  = ['border-emerald-600', 'text-emerald-700', 'font-semibold'];
    var OFF = ['border-transparent', 'text-slate-500', 'hover:text-slate-700', 'font-medium'];
    function switchTab(name) {
        document.querySelectorAll('.tab-content').forEach(function (el) { el.classList.add('hidden'); });
        document.querySelectorAll('.tab-link').forEach(function (btn) {
            var on = btn.dataset.tab === name;
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
            btn.classList.remove.apply(btn.classList, ON.concat(OFF));
            btn.classList.add.apply(btn.classList, on ? ON : OFF);
            var tabNum = btn.querySelector('.tab-num');
            if (tabNum) {
                tabNum.className = 'tab-num flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-semibold ' +
                    (on ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600');
            }
        });
        var targetTab = document.getElementById('content-tab-' + name);
        if (targetTab) { targetTab.classList.remove('hidden'); }
    }
    document.querySelectorAll('.tab-link').forEach(function (b) { b.addEventListener('click', function () { switchTab(b.dataset.tab); }); });
    switchTab(<?= json_encode($startTab) ?>);

    document.querySelectorAll('[data-open-modal]').forEach(function (b) {
        b.addEventListener('click', function () {
            var d = document.getElementById(b.dataset.openModal);
            if (d && d.showModal) { d.showModal(); }
        });
    });
    document.querySelectorAll('dialog').forEach(function (d) {
        d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
        d.querySelectorAll('[data-close-modal]').forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
    });

    // Re-sync the popup checkboxes with the current assigned list every time it opens
    document.querySelectorAll('[data-open-modal="modal-add-admin"]').forEach(function (b) {
        b.addEventListener('click', function () {
            var ids = Array.prototype.map.call(document.querySelectorAll('#assigned-admins-view-list input[name="admin_ids[]"]'), function (i) { return i.value; });
            document.querySelectorAll('.modal-user-checkbox').forEach(function (cb) { cb.checked = ids.indexOf(cb.value) !== -1; });
        });
    });

    // Modal Select All
    document.getElementById('modal-select-all')?.addEventListener('change', function() {
        var checked = this.checked;
        document.querySelectorAll('.modal-user-checkbox').forEach(function(cb) {
            cb.checked = checked;
        });
    });

    // Modal Search Filter
    document.getElementById('modal-search-users')?.addEventListener('input', function() {
        var filter = this.value.toLowerCase();
        document.querySelectorAll('.modal-user-row').forEach(function(row) {
            var name = row.dataset.name.toLowerCase();
            row.style.display = name.includes(filter) ? '' : 'none';
        });
    });

    // Confirm Add Users from Modal to Assigned List
    document.getElementById('btn-confirm-add-admins')?.addEventListener('click', function() {
        var tbody = document.getElementById('assigned-admins-view-list');
        var noRow = document.getElementById('no-assigned-row');
        if (noRow) noRow.remove();

        // Clear current items in view list
        tbody.innerHTML = '';

        var selectedNames = [];
        var count = 0;

        document.querySelectorAll('.modal-user-checkbox:checked').forEach(function(cb) {
            var row = cb.closest('.modal-user-row');
            var id = row.dataset.id;
            var name = row.dataset.name;
            var role = row.dataset.role;

            selectedNames.push(name);
            count++;

            var tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50';

            var td1 = document.createElement('td');
            td1.className = 'py-2.5 px-3 font-medium text-slate-800 flex items-center gap-2';
            var av = document.createElement('span');
            av.className = 'relative inline-flex h-6 w-6 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-emerald-100 text-emerald-700 text-[10px]';
            av.innerHTML = '<i class="fa-solid fa-user"></i>';
            if (row.dataset.avatar) {
                var im = document.createElement('img');
                im.src = row.dataset.avatar; im.alt = ''; im.referrerPolicy = 'no-referrer';
                im.className = 'absolute inset-0 h-full w-full object-cover';
                im.onerror = function () { im.remove(); };
                av.appendChild(im);
            }
            td1.appendChild(av);
            td1.appendChild(document.createTextNode(name));
            var hid = document.createElement('input');
            hid.type = 'hidden'; hid.name = 'admin_ids[]'; hid.value = id;
            td1.appendChild(hid);

            var td2 = document.createElement('td');
            td2.className = 'py-2.5 px-3 text-slate-500';
            td2.textContent = role;

            tr.appendChild(td1); tr.appendChild(td2);
            tbody.appendChild(tr);
        });

        if (count === 0) {
            tbody.innerHTML = `<tr id="no-assigned-row"><td colspan="2" class="py-6 text-center text-slate-400 italic">No assigned administrators. Click "+ Add" to add users.</td></tr>`;
        }

        // Update counts and summary names
        document.getElementById('assigned-count-lbl').textContent = count;
        var summaryEl = document.getElementById('summary-assigned-names');
        if (summaryEl) {
            summaryEl.textContent = selectedNames.length > 0 ? selectedNames.join(', ') : 'None assigned';
        }

        // Close modal
        document.getElementById('modal-add-admin')?.close();
    });
})();
</script>
<?= $this->endSection() ?>