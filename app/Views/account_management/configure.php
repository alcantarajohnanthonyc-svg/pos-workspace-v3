<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$u = $selectedUser ?? [];
$fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['middle_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
if ($fullName === '') { $fullName = (string) ($u['username'] ?? 'User'); }
$initial = strtoupper(mb_substr((string) ($u['first_name'] ?? 'U'), 0, 1));

// Login method: a workspace email means Google SSO, otherwise Username & Password
$isGoogle  = trim((string) ($u['email_workspace'] ?? '')) !== '';
$authLabel = $isGoogle ? 'Google Workspace (SSO)' : 'Username & Password';
$authIcon  = $isGoogle ? 'fa-brands fa-google' : 'fa-solid fa-key';
$avatarUrl = static fn ($a) => $a ? (preg_match('#^(https?:)?//#i', (string) $a) ? (string) $a : base_url(ltrim((string) $a, '/'))) : '';
$curAvatar = $avatarUrl($u['avatar'] ?? '');
$infoEmail = trim((string) ($u['info_email'] ?? ''));
$bannerSub = $isGoogle ? (string) $u['email_workspace'] : ($infoEmail !== '' ? $infoEmail : '@' . ($u['username'] ?? ''));

// RBAC flags from the controller. Deny by default: nothing shows unless the controller sent true.
$can     = $can ?? [];
$canEdit = $can['update'] ?? false;
$canStat = $can['toggle_status'] ?? false;

$uid            = (int) ($u['id'] ?? 0);
$profileAction  = $profileAction  ?? base_url("accounts/{$uid}/profile");
$passwordAction = $passwordAction ?? base_url("accounts/{$uid}/password");
$statusAction   = $statusAction   ?? base_url("accounts/{$uid}/status");
$moduleAccessAction = $moduleAccessAction ?? base_url("accounts/{$uid}/modules");

$roleOptions = ! empty($roles) ? array_column($roles, 'name', 'id') : [1 => 'Super Admin', 2 => 'Admin', 3 => 'POS Support', 4 => 'Viewer'];

$statusOptions = [
    'active'    => ['Active',    'Can sign in and use assigned modules.'],
    'pending'   => ['Pending',   'Awaiting approval. Cannot use the workspace yet.'],
    'inactive'  => ['Inactive',  'Deactivated. Cannot sign in.'],
    'suspended' => ['Suspended', 'Temporarily blocked. Cannot sign in.'],
];
$isSelf       = (string) $uid === (string) (session()->get('account_id') ?? session()->get('id'));
$flashSuccess = session()->getFlashdata('success');
$flashError   = session()->getFlashdata('error');
$startTab     = session()->getFlashdata('tab');
if (! in_array($startTab, ['profile', 'modules', 'scope', 'activity'], true)) { $startTab = 'profile'; }

$iconClass = static function ($i): string {
    $i = trim(preg_replace('/[^a-z0-9\- ]/i', '', (string) $i));
    if ($i === '') { return 'fa-solid fa-cube'; }
    return preg_match('/\bfa-(solid|regular|brands)\b/', $i) ? $i : 'fa-solid ' . $i;
};
$isModEnabled = static function (array $m): bool {
    if (isset($m['is_enabled'])) { return (int) $m['is_enabled'] === 1; }
    if (isset($m['status']))     { return strtolower((string) $m['status']) !== 'disabled'; }
    return true;
};

// Ipakita ang Lahat ng Enabled Modules
$accessible = [];
foreach (($modules ?? []) as $m) {
    if (! $isModEnabled($m)) { continue; }
    $accessible[] = [
        'id'          => (string) $m['id'],
        'name'        => (string) ($m['name'] ?? ''),
        'description' => (string) (! empty($m['description']) ? $m['description'] : 'Manage module records and transactions.'),
        'icon'        => $iconClass($m['icon'] ?? ''),
        'actions'     => array_map(static fn ($a) => [
            'key'     => (string) ($a['action_key'] ?? $a['action_name'] ?? ''),
            'name'    => (string) ($a['action_name'] ?? $a['action_key'] ?? 'Action'),
            'pid'     => (int) ($a['id'] ?? 0),
            'granted' => (bool) ($a['granted'] ?? false),
        ], $m['actions']),
    ];
}

$accountStatus = strtolower((string) ($u['account_status'] ?? 'active'));
$statusBadge = [
    'active'   => 'bg-emerald-100 text-emerald-700',
    'pending'  => 'bg-amber-100 text-amber-700',
    'inactive' => 'bg-slate-200 text-slate-600',
][$accountStatus] ?? 'bg-slate-200 text-slate-600';

$roleName = (string) ($u['role_name'] ?? 'Unassigned');
$dash     = static fn ($v) => (trim((string) $v) === '') ? '—' : (string) $v;

$input   = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20';
$inputRO = 'w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[13px] text-slate-600';
$label   = 'block text-xs font-medium text-slate-700 mb-1';
$btnPrim = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2';
$btnGhost = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-semibold rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';

$row = static function (string $l, string $v, bool $raw = false): void {
    echo '<div class="flex gap-3 py-1.5"><dt class="w-28 flex-shrink-0 text-slate-500">' . esc($l) . '</dt>'
       . '<dd class="min-w-0 font-medium text-slate-800 break-words">' . ($raw ? $v : esc($v)) . '</dd></div>';
};
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

    <!-- BACK -->
    <div>
        <a href="<?= esc(base_url('accounts'), 'attr') ?>"
           class="inline-flex items-center gap-2 px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 font-semibold rounded-lg text-xs transition-colors border border-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
            <i class="fa-solid fa-arrow-left"></i> Back to Account List
        </a>
    </div>

    <!-- SUMMARY BANNER -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 sm:p-5">
        <div class="flex items-center gap-3 sm:gap-4 min-w-0">
            <?php if (! empty($u['avatar'])): ?>
                <img src="<?= esc($avatarUrl($u['avatar']), 'attr') ?>" alt="" class="w-14 h-14 rounded-full object-cover border border-slate-200 flex-shrink-0">
            <?php else: ?>
                <div class="w-14 h-14 rounded-full bg-emerald-600 text-white flex items-center justify-center font-semibold text-lg flex-shrink-0"><?= esc($initial) ?></div>
            <?php endif; ?>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-base font-semibold text-slate-800 truncate"><?= esc($fullName) ?></h1>
                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full <?= $statusBadge ?>"><?= esc(ucfirst($accountStatus)) ?></span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5 truncate"><?= esc($bannerSub) ?></p>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-[11px] text-slate-500">
                    <span class="flex items-center gap-1.5"><i class="<?= $authIcon ?> text-slate-400"></i> <?= esc($authLabel) ?></span>
                    <span class="text-slate-300 hidden sm:inline">|</span>
                    <span>Role: <strong class="text-slate-700"><?= esc($roleName) ?></strong></span>
                </div>
            </div>
        </div>
    </section>

    <!-- TABS -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="border-b border-slate-200 px-4 sm:px-5 overflow-x-auto">
            <nav class="flex gap-5 min-w-max" role="tablist" aria-label="Account sections">
                <?php foreach ([['profile', 'Profile', 1], ['modules', 'Module Access', 2], ['scope', 'Company / Branch Scope', 3], ['activity', 'Activity Log', 4]] as $i => $t): ?>
                    <button type="button" role="tab" id="btn-tab-<?= $t[0] ?>" data-tab="<?= $t[0] ?>"
                            aria-controls="content-tab-<?= $t[0] ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                            class="tab-link border-b-2 py-3 text-xs flex items-center gap-2 whitespace-nowrap transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 rounded-t
                                   <?= $i === 0 ? 'border-emerald-600 text-emerald-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 font-medium' ?>">
                        <span class="tab-num w-5 h-5 rounded-full text-[10px] flex items-center justify-center font-semibold <?= $i === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>"><?= $t[2] ?></span>
                        <?= $t[1] ?>
                    </button>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="p-4 sm:p-5">

            <!-- 1. PROFILE -->
            <div id="content-tab-profile" class="tab-content" role="tabpanel" aria-labelledby="btn-tab-profile">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

                    <!-- User Information -->
                    <div class="lg:col-span-8 rounded-lg border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <h2 class="text-xs font-semibold text-slate-800 uppercase tracking-wider">User Information</h2>
                            <?php if ($canEdit): ?>
                                <button type="button" data-open-modal="modal-profile" class="<?= $btnGhost ?>"><i class="fa-solid fa-pen"></i> Edit</button>
                            <?php endif; ?>
                        </div>

                        <!-- View mode -->
                        <div id="profile-view">
                            <dl class="grid grid-cols-1 md:grid-cols-2 md:gap-x-8 text-xs">
                                <div class="divide-y divide-slate-100">
                                    <?php
                                    $row('Full Name', $fullName);
                                    $row('Username', $dash($u['username'] ?? ''));
                                    $row('Email', $dash($isGoogle ? ($u['email_workspace'] ?? '') : $infoEmail));
                                    $row('Phone', $dash($u['phone_number'] ?? ''));
                                    $row('Sex', $dash($u['sex'] ?? ''));
                                    $row('Role', $roleName);
                                    foreach (['position' => 'Position', 'department' => 'Department', 'manager' => 'Manager'] as $k => $l) {
                                        if (! empty($u[$k])) { $row($l, (string) $u[$k]); }
                                    }
                                    ?>
                                </div>
                                <div class="divide-y divide-slate-100 border-t border-slate-100 md:border-t-0">
                                    <?php
                                    $row('Account Status', '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full ' . $statusBadge . '">' . esc(ucfirst($accountStatus)) . '</span>', true);
                                    $row('Login Method', '<span class="inline-flex items-center gap-1.5"><i class="' . $authIcon . ' text-slate-500"></i> ' . esc($authLabel) . '</span>', true);
                                    $row('Last Login', $dash($u['last_login_at'] ?? $u['last_login'] ?? ''));
                                    $row('Created On', $dash($u['created_at'] ?? ''));
                                    $row('Created By', $dash($u['created_by_name'] ?? 'Administrator'));
                                    ?>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Account Actions -->
                    <div class="lg:col-span-4 space-y-4">
                        <div class="rounded-lg border border-slate-200 p-4">
                            <h2 class="text-xs font-semibold text-slate-800 uppercase tracking-wider mb-3">Account Actions</h2>
                            <div class="space-y-2">
                                <?php if (! $isGoogle && $canEdit): ?>
                                    <button type="button" data-open-modal="modal-password" class="<?= $btnPrim ?> w-full"><i class="fa-solid fa-lock"></i> Change Password</button>
                                <?php endif; ?>
                                <?php if ($canStat): ?>
                                <button type="button" data-open-modal="modal-status" class="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-semibold rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                                    <i class="fa-solid fa-user-gear"></i> Change Status
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 flex items-start gap-3">
                            <i class="<?= $authIcon ?> text-slate-500 mt-0.5"></i>
                            <div class="text-[11px] text-slate-600">
                                <div class="text-xs font-semibold text-slate-800"><?= esc($authLabel) ?></div>
                                <?php if ($isGoogle): ?>
                                    Password is managed by your organization. This user logs in using <strong>Google Workspace</strong>.
                                <?php else: ?>
                                    This user signs in with a <strong>username and password</strong>. Use Change Password to reset it.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. MODULE ACCESS -->
            <div id="content-tab-modules" class="tab-content hidden" role="tabpanel" aria-labelledby="btn-tab-modules">
                <form id="modules-form" action="<?= esc($moduleAccessAction, 'attr') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= esc($u['id'] ?? '', 'attr') ?>">
                    <div id="modules-hidden"></div>

                    <!-- ROLE SELECTOR BAR -->
                    <div class="mb-4 bg-slate-50 border border-slate-200 rounded-lg p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-xs text-slate-700">
                            <i class="fa-solid fa-user-shield text-emerald-600"></i>
                            <span>User's Base Role:</span>
                            <select id="tab-role-selector" name="user_role_id" <?= $canEdit ? '' : 'disabled' ?> class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 focus:outline-none focus:border-emerald-600">
                                <?php foreach ($roleOptions as $rid => $rname): ?>
                                    <option value="<?= (int) $rid ?>" <?= (int) ($u['user_role_id'] ?? 0) === (int) $rid ? 'selected' : '' ?>><?= esc($rname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <span class="text-[11px] text-slate-500">Changing role updates default module checks. Click <strong>Save Module Access</strong> to apply.</span>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                        <div class="lg:col-span-8 overflow-x-auto rounded-lg border border-slate-200">
                            <table class="w-full text-left text-xs text-slate-600">
                                <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] font-semibold border-b border-slate-200">
                                    <tr>
                                        <th scope="col" class="py-2.5 px-3">Module</th>
                                        <th scope="col" class="py-2.5 px-3">Permissions (CRUD)</th>
                                        <th scope="col" class="py-2.5 px-3">Additional Access</th>
                                        <th scope="col" class="py-2.5 px-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100" id="modules-table-body"></tbody>
                            </table>
                        </div>

                        <div class="lg:col-span-4 rounded-lg border border-slate-200 p-4 space-y-4 self-start bg-white">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                                        <i id="right-module-icon" class="fa-solid fa-cube"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 id="right-module-name" class="text-xs font-semibold text-slate-800 truncate"></h3>
                                        <p id="right-module-desc" class="text-[11px] text-slate-500 truncate"></p>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-100 text-emerald-700 flex-shrink-0">Enabled</span>
                            </div>
                            <div class="space-y-2">
                                <h4 class="text-[11px] font-semibold text-slate-700 uppercase tracking-wider">CRUD Permissions</h4>
                                <div id="crud-actions-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 gap-2.5 text-xs"></div>
                            </div>
                            <div class="space-y-2 pt-3 border-t border-slate-100">
                                <h4 class="text-[11px] font-semibold text-slate-700 uppercase tracking-wider">Additional Access</h4>
                                <div id="additional-actions-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 gap-2.5 text-xs"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <?php if ($canEdit): ?>
                            <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Save Module Access</button>
                        <?php else: ?>
                            <span class="text-[11px] text-slate-500"><i class="fa-solid fa-lock"></i> View only. You do not have permission to change module access.</span>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- 3. COMPANY / BRANCH SCOPE (UPDATED EASY-ASSIGN UI) -->
            <div id="content-tab-scope" class="tab-content hidden" role="tabpanel" aria-labelledby="btn-tab-scope">
                <form action="<?= base_url("accounts/{$uid}/scope") ?>" method="POST" class="space-y-4">
                    <?= csrf_field() ?>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-800">Company &amp; Branch Access Scope</h2>
                            <p class="text-[11px] text-slate-500">Select the companies and branches this user is authorized to access.</p>
                        </div>
                        <?php if ($canEdit): ?>
                            <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Save Scope</button>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                        <!-- BRANCHES LIST GROUPED BY COMPANY WITH SEARCH -->
                        <div class="lg:col-span-8 rounded-lg border border-slate-200 bg-white p-4 space-y-3">
                            
                            <!-- Search Bar & Quick Toggles -->
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pb-2 border-b border-slate-100">
                                <div class="relative w-full sm:w-64">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                                    </span>
                                    <input type="text" id="branch-search-input" placeholder="Search branch or company..." 
                                           class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-3 text-xs focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                                </div>

                                <div class="flex items-center gap-2 text-xs w-full sm:w-auto justify-end">
                                    <button type="button" id="btn-select-all-branches" class="text-emerald-600 font-semibold hover:underline text-xs">Select All</button>
                                    <span class="text-slate-300">|</span>
                                    <button type="button" id="btn-deselect-all-branches" class="text-slate-500 font-semibold hover:underline text-xs">Deselect All</button>
                                </div>
                            </div>

                            <!-- Grouped Companies & Branches Container -->
                            <div class="space-y-3 max-h-[380px] overflow-y-auto pr-1" id="company-branches-container">
                                <?php 
                                // Grupopin ang mga branches ayon sa company_id
                                $groupedBranches = [];
                                foreach (($branches ?? []) as $b) {
                                    $groupedBranches[$b['company_id']][] = $b;
                                }
                                ?>

                                <?php foreach (($companies ?? []) as $comp): ?>
                                    <?php 
                                        $cBranches = $groupedBranches[$comp['id']] ?? [];
                                        if (empty($cBranches)) continue;
                                    ?>
                                    <div class="company-group rounded-lg border border-slate-200 bg-white overflow-hidden" data-company-name="<?= strtolower(esc($comp['name'] . ' ' . $comp['short_name'])) ?>">
                                        
                                        <!-- Header per Company with Group Select All -->
                                        <div class="bg-slate-50 px-3 py-2 border-b border-slate-200 flex items-center justify-between">
                                            <label class="flex items-center gap-2 cursor-pointer font-semibold text-xs text-slate-800">
                                                <input type="checkbox" class="company-group-toggle rounded border-slate-300 text-emerald-600 focus:ring-emerald-600" <?= $canEdit ? '' : 'disabled' ?>>
                                                <span><?= esc($comp['name']) ?> <span class="text-slate-400 font-mono text-[10px]">(<?= esc($comp['short_name']) ?>)</span></span>
                                            </label>
                                            <span class="text-[10px] text-slate-500 font-normal"><span class="comp-checked-count">0</span>/<?= count($cBranches) ?> selected</span>
                                        </div>

                                        <!-- Branches List for this Company -->
                                        <div class="p-2.5 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <?php foreach ($cBranches as $b): ?>
                                                <label class="branch-item flex items-center gap-2.5 p-2 rounded border border-slate-200 hover:bg-slate-50 cursor-pointer text-xs" data-branch-name="<?= strtolower(esc($b['branch_name'])) ?>">
                                                    <input type="checkbox" name="branch_ids[]" value="<?= (int) $b['id'] ?>"
                                                           data-company-id="<?= (int) $b['company_id'] ?>"
                                                           data-company-name="<?= esc($b['company_name']) ?>"
                                                           data-branch-name="<?= esc($b['branch_name']) ?>"
                                                           class="branch-checkbox rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"
                                                           <?= in_array((int) $b['id'], $assignedBranchIds ?? [], true) ? 'checked' : '' ?>
                                                           <?= $canEdit ? '' : 'disabled' ?>>
                                                    <span class="font-medium text-slate-800 truncate"><?= esc($b['branch_name']) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>

                                    </div>
                                <?php endforeach; ?>

                                <?php if (empty($branches)): ?>
                                    <div class="py-8 text-center text-slate-400 italic text-xs">No active companies or branches found. Please add branches in Company settings first.</div>
                                <?php endif; ?>
                            </div>

                        </div>

                        <!-- SUMMARY PANEL -->
                        <div class="lg:col-span-4 rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-3 self-start">
                            <h3 class="text-xs font-semibold text-slate-700 uppercase flex items-center gap-1.5">
                                <i class="fa-solid fa-shield-halved text-emerald-600"></i> Scope Summary
                            </h3>
                            <div class="space-y-2 text-xs">
                                <div>
                                    <span class="block text-[11px] font-semibold text-slate-500 uppercase">Selected Branches:</span>
                                    <div id="summary-branches" class="font-medium text-slate-800 mt-0.5 max-h-32 overflow-y-auto pr-1">None</div>
                                </div>
                                <div class="pt-2 border-t border-slate-200">
                                    <span class="block text-[11px] font-semibold text-slate-500 uppercase">Implied Companies:</span>
                                    <div id="summary-companies" class="font-medium text-slate-800 mt-0.5">None</div>
                                </div>
                            </div>
                            <div class="rounded bg-sky-50 border border-sky-200 p-2.5 flex items-start gap-2 text-[10px] text-sky-900">
                                <i class="fa-solid fa-circle-info mt-0.5 text-sky-600"></i>
                                <span>Only data belonging to the selected branches and companies will be visible to this user in filtered modules.</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 4. ACTIVITY LOG -->
            <div id="content-tab-activity" class="tab-content hidden" role="tabpanel" aria-labelledby="btn-tab-activity">
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] font-semibold border-b border-slate-200">
                            <tr>
                                <th scope="col" class="py-2.5 px-3">Date &amp; Time</th>
                                <th scope="col" class="py-2.5 px-3">Actor</th>
                                <th scope="col" class="py-2.5 px-3">Event</th>
                                <th scope="col" class="py-2.5 px-3">Module</th>
                                <th scope="col" class="py-2.5 px-3 text-center">Result</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr>
                                <td class="py-2.5 px-3 text-slate-500 whitespace-nowrap"><?= esc($u['created_at'] ?? 'N/A') ?></td>
                                <td class="py-2.5 px-3 font-medium text-slate-800">System</td>
                                <td class="py-2.5 px-3">Google Workspace first login detected</td>
                                <td class="py-2.5 px-3">Login</td>
                                <td class="py-2.5 px-3 text-center"><span class="px-2 py-0.5 bg-sky-100 text-sky-700 rounded-full font-semibold text-[10px]">Info</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- ================= MODALS ================= -->

<!-- Edit profile -->
<dialog id="modal-profile" aria-labelledby="modal-profile-title"
        class="m-auto w-[calc(100vw-2rem)] max-w-2xl rounded-xl p-0 text-slate-800 shadow-xl backdrop:bg-slate-900/50">
    <form action="<?= esc($profileAction, 'attr') ?>" method="POST" enctype="multipart/form-data" class="flex max-h-[90vh] flex-col">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 id="modal-profile-title" class="text-sm font-semibold">Edit User Information</h2>
            <button type="button" data-close-modal aria-label="Close" class="flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-3 overflow-y-auto px-5 py-4">
            <?php if (! $isGoogle): ?>
                <div class="flex items-center gap-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-emerald-600 text-lg font-semibold text-white">
                        <img data-avatar-img src="<?= esc($curAvatar, 'attr') ?>" alt="" class="<?= $curAvatar === '' ? 'hidden ' : '' ?>h-full w-full object-cover">
                        <span data-avatar-fallback class="<?= $curAvatar !== '' ? 'hidden' : '' ?>"><?= esc($initial) ?></span>
                    </div>
                    <div class="min-w-0">
                        <label class="<?= $btnGhost ?> cursor-pointer has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-emerald-600">
                            <i class="fa-solid fa-camera"></i> Change photo
                            <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" data-avatar-input class="sr-only">
                        </label>
                        <p data-avatar-error class="mt-1 hidden text-[11px] text-red-600"></p>
                        <p class="mt-1 text-[11px] text-slate-400">JPG, PNG or WEBP, max 2 MB.</p>
                    </div>
                </div>
            <?php endif; ?>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label for="first_name" class="<?= $label ?>">First Name <span class="text-red-500">*</span></label>
                    <input id="first_name" type="text" name="first_name" required maxlength="100" value="<?= esc($u['first_name'] ?? '', 'attr') ?>" class="<?= $input ?>">
                </div>
                <div>
                    <label for="middle_name" class="<?= $label ?>">Middle Name</label>
                    <input id="middle_name" type="text" name="middle_name" maxlength="100" value="<?= esc($u['middle_name'] ?? '', 'attr') ?>" class="<?= $input ?>">
                </div>
                <div>
                    <label for="last_name" class="<?= $label ?>">Last Name <span class="text-red-500">*</span></label>
                    <input id="last_name" type="text" name="last_name" required maxlength="100" value="<?= esc($u['last_name'] ?? '', 'attr') ?>" class="<?= $input ?>">
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="username" class="<?= $label ?>">Username</label>
                    <input id="username" type="text" value="<?= esc($u['username'] ?? '', 'attr') ?>" class="<?= $inputRO ?>" readonly>
                </div>
                <div>
                    <?php if ($isGoogle): ?>
                        <label for="email_workspace" class="<?= $label ?>">Email Workspace</label>
                        <input id="email_workspace" type="email" value="<?= esc($u['email_workspace'] ?? '', 'attr') ?>" class="<?= $inputRO ?>" readonly>
                    <?php else: ?>
                        <label for="info_email" class="<?= $label ?>">Email Address</label>
                        <input id="info_email" type="email" name="email" maxlength="150" value="<?= esc($infoEmail, 'attr') ?>" class="<?= $input ?>" placeholder="user@example.com">
                    <?php endif; ?>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="phone_number" class="<?= $label ?>">Phone Number</label>
                    <input id="phone_number" type="tel" name="phone_number" maxlength="30" value="<?= esc($u['phone_number'] ?? '', 'attr') ?>" class="<?= $input ?>">
                </div>
                <div>
                    <label for="sex" class="<?= $label ?>">Sex</label>
                    <select id="sex" name="sex" class="<?= $input ?>">
                        <option value="">Select Sex</option>
                        <?php foreach (['Male', 'Female', 'Other'] as $sx): ?>
                            <option value="<?= $sx ?>" <?= ($u['sex'] ?? '') === $sx ? 'selected' : '' ?>><?= $sx ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close-modal class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
        </div>
    </form>
</dialog>

<?php if (! $isGoogle): ?>
<!-- Change password -->
<dialog id="modal-password" aria-labelledby="modal-password-title"
        class="m-auto w-[calc(100vw-2rem)] max-w-md rounded-xl p-0 text-slate-800 shadow-xl backdrop:bg-slate-900/50">
    <form action="<?= esc($passwordAction, 'attr') ?>" method="POST" id="password-form" class="flex max-h-[90vh] flex-col">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 id="modal-password-title" class="text-sm font-semibold">Change Password</h2>
            <button type="button" data-close-modal aria-label="Close" class="flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-3 overflow-y-auto px-5 py-4">
            <p class="text-xs text-slate-500">Set a new password for <strong class="text-slate-700"><?= esc($fullName) ?></strong>. Use at least 8 characters.</p>
            <div>
                <label for="new_password" class="<?= $label ?>">New Password</label>
                <input id="new_password" type="password" name="new_password" required minlength="8" maxlength="72" class="<?= $input ?>">
            </div>
            <div>
                <label for="confirm_password" class="<?= $label ?>">Confirm Password</label>
                <input id="confirm_password" type="password" name="confirm_password" required minlength="8" maxlength="72" class="<?= $input ?>">
                <p id="pw-mismatch" class="mt-1 hidden text-[11px] text-red-600">Passwords do not match.</p>
            </div>
            <label class="flex cursor-pointer items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" id="pw-show" class="rounded border-slate-300 text-emerald-600"> Show passwords
            </label>
        </div>
        <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close-modal class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-lock"></i> Update Password</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<!-- Change account status -->
<dialog id="modal-status" aria-labelledby="modal-status-title"
        class="m-auto w-[calc(100vw-2rem)] max-w-md rounded-xl p-0 text-slate-800 shadow-xl backdrop:bg-slate-900/50">
    <form action="<?= esc($statusAction, 'attr') ?>" method="POST" class="flex max-h-[90vh] flex-col">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 id="modal-status-title" class="text-sm font-semibold">Change Account Status</h2>
            <button type="button" data-close-modal aria-label="Close" class="flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <fieldset class="space-y-2 overflow-y-auto px-5 py-4">
            <legend class="sr-only">Account status</legend>
            <?php if ($isSelf): ?>
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800">This is your own account. You cannot set it to anything other than Active.</p>
            <?php endif; ?>
            <?php foreach ($statusOptions as $val => [$sLabel, $sDesc]):
                $locked = $isSelf && $val !== 'active'; ?>
                <label class="flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-2.5 <?= $locked ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:bg-slate-50' ?>">
                    <input type="radio" name="account_status" value="<?= $val ?>" class="mt-0.5 border-slate-300 text-emerald-600"
                           <?= $accountStatus === $val ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
                    <span>
                        <span class="block text-xs font-semibold text-slate-800"><?= esc($sLabel) ?></span>
                        <span class="block text-[11px] text-slate-500"><?= esc($sDesc) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close-modal class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Save Status</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    /* ---------- Tabs ---------- */
    var ON  = ['border-emerald-600', 'text-emerald-700', 'font-semibold'];
    var OFF = ['border-transparent', 'text-slate-500', 'hover:text-slate-700', 'font-medium'];
    function switchTab(name) {
        document.querySelectorAll('.tab-content').forEach(function (el) { el.classList.add('hidden'); });
        document.querySelectorAll('.tab-link').forEach(function (btn) {
            var on = btn.dataset.tab === name;
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
            btn.classList.remove.apply(btn.classList, ON.concat(OFF));
            btn.classList.add.apply(btn.classList, on ? ON : OFF);
            btn.querySelector('.tab-num').className = 'tab-num w-5 h-5 rounded-full text-[10px] flex items-center justify-center font-semibold ' +
                (on ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600');
        });
        document.getElementById('content-tab-' + name).classList.remove('hidden');
    }
    document.querySelectorAll('.tab-link').forEach(function (btn) {
        btn.addEventListener('click', function () { switchTab(btn.dataset.tab); });
    });

    switchTab(<?= json_encode($startTab) ?>);

    /* ---------- Modals ---------- */
    document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = document.getElementById(btn.dataset.openModal);
            if (d && d.showModal) { d.showModal(); }
        });
    });
    document.querySelectorAll('dialog').forEach(function (d) {
        d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
        d.querySelectorAll('[data-close-modal]').forEach(function (b) {
            b.addEventListener('click', function () { d.close(); });
        });
    });

    /* Avatar preview + client-side check (JPG/PNG/WEBP, max 2 MB) */
    document.querySelectorAll('[data-avatar-input]').forEach(function (input) {
        var box   = input.closest('form');
        var img   = box.querySelector('[data-avatar-img]');
        var fall  = box.querySelector('[data-avatar-fallback]');
        var err   = box.querySelector('[data-avatar-error]');
        var url   = null;
        input.addEventListener('change', function () {
            var f = input.files && input.files[0];
            err.classList.add('hidden');
            if (!f) { return; }
            if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) {
                err.textContent = 'Please choose a JPG, PNG or WEBP image.';
            } else if (f.size > 2 * 1024 * 1024) {
                err.textContent = 'Image is too large (max 2 MB).';
            } else {
                if (url) { URL.revokeObjectURL(url); }
                url = URL.createObjectURL(f);
                img.src = url; img.classList.remove('hidden'); fall.classList.add('hidden');
                return;
            }
            input.value = '';
            err.classList.remove('hidden');
        });
    });

    /* Password validation */
    var pw1 = document.getElementById('new_password');
    var pw2 = document.getElementById('confirm_password');
    function checkMatch() {
        var bad = pw2.value !== '' && pw1.value !== pw2.value;
        pw2.setCustomValidity(bad ? 'Passwords do not match.' : '');
        document.getElementById('pw-mismatch').classList.toggle('hidden', !bad);
    }
    if(pw1 && pw2) {
        pw1.addEventListener('input', checkMatch);
        pw2.addEventListener('input', checkMatch);
        document.getElementById('pw-show').addEventListener('change', function (e) {
            pw1.type = pw2.type = e.target.checked ? 'text' : 'password';
        });
    }

    /* ---------- Module access & Role mapping ---------- */
    var CAN_EDIT = <?= json_encode((bool) $canEdit) ?>;
    var ALL_MODULES = <?= json_encode($accessible, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    var ROLE_PERMS  = <?= json_encode($rolePermissions ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    
    var tbody = document.getElementById('modules-table-body');
    if (tbody && ALL_MODULES.length) {

        var CRUD = { create: 'C', read: 'R', update: 'U', 'delete': 'D' };
        var CRUD_ORDER = ['create', 'read', 'update', 'delete'];
        var state = {};
        var selectedId = null;

        ALL_MODULES.forEach(function (m) {
            state[m.id] = {};
            m.actions.forEach(function (a) {
                state[m.id][a.key] = !!a.granted;
            });
        });

        var roleSelector = document.getElementById('tab-role-selector');
        if (roleSelector) {
            roleSelector.addEventListener('change', function () {
                var rid = parseInt(this.value);
                var permsForRole = ROLE_PERMS[rid] || [];
                
                ALL_MODULES.forEach(function (m) {
                    m.actions.forEach(function (a) {
                        state[m.id][a.key] = permsForRole.includes(a.pid);
                    });
                });
                renderTable();
                renderPanel();
            });
        }

        function el(tag, cls, text) {
            var e = document.createElement(tag);
            if (cls) { e.className = cls; }
            if (text !== undefined) { e.textContent = text; }
            return e;
        }
        function isCrud(a) { return Object.prototype.hasOwnProperty.call(CRUD, a.key.toLowerCase()); }
        function letter(cls, text, title) {
            var b = el('span', 'w-5 h-5 rounded text-[10px] font-semibold flex items-center justify-center text-white ' + cls, text);
            b.title = title;
            return b;
        }

        function crudCell(m) {
            var wrap = el('div', 'flex items-center gap-1');
            var granted = m.actions.filter(function (a) { return isCrud(a) && state[m.id][a.key]; });
            granted.sort(function (a, b) { return CRUD_ORDER.indexOf(a.key.toLowerCase()) - CRUD_ORDER.indexOf(b.key.toLowerCase()); });
            granted.forEach(function (a) { wrap.appendChild(letter('bg-emerald-600', CRUD[a.key.toLowerCase()], a.name)); });
            if (!granted.length) { wrap.appendChild(el('span', 'text-slate-400', '—')); }
            return wrap;
        }
        function extraCell(m) {
            var wrap = el('div', 'flex flex-wrap items-center gap-1');
            var granted = m.actions.filter(function (a) { return !isCrud(a) && state[m.id][a.key]; });
            granted.forEach(function (a) { wrap.appendChild(letter('bg-sky-600', (a.name.trim().charAt(0) || '?').toUpperCase(), a.name)); });
            if (!granted.length) { wrap.appendChild(el('span', 'text-slate-400', '—')); }
            return wrap;
        }

        function select(id) { selectedId = id; renderTable(); renderPanel(); }

        function renderTable() {
            tbody.innerHTML = '';
            ALL_MODULES.forEach(function (m) {
                var tr = el('tr', 'cursor-pointer transition-colors border-l-4 focus:outline-none focus-visible:bg-emerald-50 ' +
                    (m.id === selectedId ? 'bg-emerald-50/60 border-l-emerald-600' : 'border-l-transparent hover:bg-slate-50'));
                tr.tabIndex = 0;
                tr.setAttribute('aria-selected', m.id === selectedId ? 'true' : 'false');

                var td1 = el('td', 'py-2.5 px-3 font-semibold text-slate-800');
                var nameWrap = el('div', 'flex items-center gap-2');
                nameWrap.appendChild(el('i', (m.icon ? m.icon : 'fa-solid fa-cube') + ' w-4 text-center text-emerald-600'));
                nameWrap.appendChild(el('span', 'truncate', m.name));
                td1.appendChild(nameWrap);

                var td2 = el('td', 'py-2.5 px-3'); td2.appendChild(crudCell(m));
                var td3 = el('td', 'py-2.5 px-3'); td3.appendChild(extraCell(m));
                var td4 = el('td', 'py-2.5 px-3 text-center');
                td4.appendChild(el('span', 'px-2 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-100 text-emerald-700', 'Enabled'));

                [td1, td2, td3, td4].forEach(function (td) { tr.appendChild(td); });
                tr.addEventListener('click', function () { select(m.id); });
                tr.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(m.id); }
                });
                tbody.appendChild(tr);
            });
        }

        function renderPanel() {
            var m = ALL_MODULES.filter(function (x) { return x.id === selectedId; })[0];
            if (!m) { return; }
            document.getElementById('right-module-name').textContent = m.name + ' Module';
            document.getElementById('right-module-desc').textContent = m.description || 'Manage module records.';
            document.getElementById('right-module-icon').className = m.icon || 'fa-solid fa-cube';

            var crud = document.getElementById('crud-actions-container');
            var extra = document.getElementById('additional-actions-container');
            crud.innerHTML = ''; extra.innerHTML = '';

            m.actions.forEach(function (a) {
                var label = el('label', 'flex items-center gap-2 cursor-pointer');
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'rounded border-slate-300 text-emerald-600 focus:ring-emerald-600';
                cb.checked = !!state[m.id][a.key];
                cb.disabled = !CAN_EDIT;
                cb.addEventListener('change', function () { state[m.id][a.key] = cb.checked; renderTable(); });
                label.appendChild(cb);
                label.appendChild(el('span', 'text-slate-700 font-medium', a.name));
                (isCrud(a) ? crud : extra).appendChild(label);
            });
            if (!crud.children.length)  { crud.appendChild(el('span', 'text-slate-400 text-[11px]', 'No CRUD permissions.')); }
            if (!extra.children.length) { extra.appendChild(el('span', 'text-slate-400 text-[11px]', 'No additional access.')); }
        }

        document.getElementById('modules-form').addEventListener('submit', function () {
            var box = document.getElementById('modules-hidden');
            box.innerHTML = '';
            function add(name, value) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = name; i.value = value;
                box.appendChild(i);
            }
            ALL_MODULES.forEach(function (m) {
                add('module_ids[]', m.id);
                m.actions.forEach(function (a) {
                    if (state[m.id][a.key]) { add('permission_ids[]', a.pid); }
                });
            });
        });

        selectedId = ALL_MODULES[0].id;
        renderTable();
        renderPanel();
    }

    /* ---------- Scope Summary Live Update & Company Helpers ---------- */
    function updateScopeSummary() {
        var branchNames = [];
        var companiesMap = {};
        document.querySelectorAll('.branch-checkbox:checked').forEach(function(cb) {
            if (cb.dataset.branchName) {
                var cLabel = cb.dataset.companyName ? ' (' + cb.dataset.companyName + ')' : '';
                branchNames.push(cb.dataset.branchName + cLabel);
            }
            if (cb.dataset.companyName) companiesMap[cb.dataset.companyName] = true;
        });
        var compNames = Object.keys(companiesMap);

        var bEl = document.getElementById('summary-branches');
        var cEl = document.getElementById('summary-companies');
        if (bEl) bEl.textContent = branchNames.length ? branchNames.join(', ') : 'None';
        if (cEl) cEl.textContent = compNames.length ? compNames.join(', ') : 'None';
    }

    // Live Search Filter Box
    var searchInput = document.getElementById('branch-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var filter = this.value.toLowerCase().trim();
            document.querySelectorAll('.company-group').forEach(function(group) {
                var compMatch = group.dataset.companyName.includes(filter);
                var hasVisibleBranch = false;

                group.querySelectorAll('.branch-item').forEach(function(item) {
                    var branchMatch = item.dataset.branchName.includes(filter);
                    var match = compMatch || branchMatch;
                    item.style.display = match ? '' : 'none';
                    if (match) hasVisibleBranch = true;
                });

                group.style.display = hasVisibleBranch ? '' : 'none';
            });
        });
    }

    // Company Group Checkbox Toggle (Select All Per Company)
    document.querySelectorAll('.company-group').forEach(function(group) {
        var groupToggle = group.querySelector('.company-group-toggle');
        var checkboxes = group.querySelectorAll('.branch-checkbox');
        var countLbl = group.querySelector('.comp-checked-count');

        function updateGroupState() {
            var checked = group.querySelectorAll('.branch-checkbox:checked').length;
            if (countLbl) countLbl.textContent = checked;
            if (groupToggle) {
                groupToggle.checked = checked > 0 && checked === checkboxes.length;
                groupToggle.indeterminate = checked > 0 && checked < checkboxes.length;
            }
            updateScopeSummary();
        }

        checkboxes.forEach(function(cb) {
            cb.addEventListener('change', updateGroupState);
        });

        if (groupToggle) {
            groupToggle.addEventListener('change', function() {
                var isChecked = this.checked;
                checkboxes.forEach(function(cb) {
                    if (!cb.disabled) cb.checked = isChecked;
                });
                updateGroupState();
            });
        }

        updateGroupState();
    });

    // Global Select All / Deselect All
    var btnSelectAll = document.getElementById('btn-select-all-branches');
    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function() {
            document.querySelectorAll('.branch-checkbox:not(:disabled)').forEach(function(cb) { cb.checked = true; });
            document.querySelectorAll('.company-group').forEach(function(group) {
                var checkboxes = group.querySelectorAll('.branch-checkbox');
                var groupToggle = group.querySelector('.company-group-toggle');
                var countLbl = group.querySelector('.comp-checked-count');
                if (groupToggle) { groupToggle.checked = true; groupToggle.indeterminate = false; }
                if (countLbl) countLbl.textContent = checkboxes.length;
            });
            updateScopeSummary();
        });
    }

    var btnDeselectAll = document.getElementById('btn-deselect-all-branches');
    if (btnDeselectAll) {
        btnDeselectAll.addEventListener('click', function() {
            document.querySelectorAll('.branch-checkbox:not(:disabled)').forEach(function(cb) { cb.checked = false; });
            document.querySelectorAll('.company-group').forEach(function(group) {
                var groupToggle = group.querySelector('.company-group-toggle');
                var countLbl = group.querySelector('.comp-checked-count');
                if (groupToggle) { groupToggle.checked = false; groupToggle.indeterminate = false; }
                if (countLbl) countLbl.textContent = '0';
            });
            updateScopeSummary();
        });
    }

    updateScopeSummary();
})();
</script>
<?= $this->endSection() ?>