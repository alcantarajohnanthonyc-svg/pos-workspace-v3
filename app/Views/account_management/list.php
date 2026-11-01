<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
// Uploaded avatars are stored as relative paths (uploads/avatars/x.jpg); Google avatars are full URLs
// Deny by default: buttons only show when the controller sent the RBAC flags and they are true
$can = $can ?? ['create' => false, 'update' => false, 'delete' => false];
$avatarUrl = static fn ($a) => $a ? (preg_match('#^(https?:)?//#i', (string) $a) ? (string) $a : base_url(ltrim((string) $a, '/'))) : '';
?>
<div class="space-y-6 pb-12">

    <!-- FLASH MESSAGES -->
    <?php if (session()->getFlashdata('success')): ?>
        <div class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">
            <i class="fa-solid fa-circle-check text-emerald-600"></i><span><?= esc(session()->getFlashdata('success')) ?></span>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800">
            <i class="fa-solid fa-circle-exclamation text-red-600"></i><span><?= esc(session()->getFlashdata('error')) ?></span>
        </div>
    <?php endif; ?>

    <!-- TOP HEADER BANNER -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-lg font-bold text-slate-800">User Management</h1>
            <p class="text-xs text-slate-500 mt-0.5">System Access Personnel Accounts</p>
        </div>
        <?php if (! empty($can['create'])): ?>
        <button type="button" id="btn-open-add-modal" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center gap-2 transition-colors">
            <i class="fa-solid fa-user-plus"></i> Add Account
        </button>
        <?php endif; ?>
    </div>

    <!-- USERS TABLE CONTAINER -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-4">Full Name</th>
                        <th class="py-3.5 px-4">Username / Email</th>
                        <th class="py-3.5 px-4">Role</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4">Live Status</th>
                        <th class="py-3.5 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $u): ?>
                            <?php 
                                $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['middle_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                if ($fullName === '') { $fullName = $u['username']; }
                            ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4 flex items-center gap-3">
                                    <?php if (!empty($u['avatar'])): ?>
                                        <img src="<?= esc($avatarUrl($u['avatar']), 'attr') ?>" alt="Avatar" class="w-9 h-9 rounded-full object-cover border border-slate-200">
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-xs border border-slate-200">
                                            <?= strtoupper(substr($u['first_name'] ?? $u['username'] ?? 'U', 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="font-bold text-slate-800 block"><?= esc($fullName) ?></span>
                                    </div>
                                </td>

                                <?php $isG = trim((string) ($u['email_workspace'] ?? '')) !== ''; ?>
                                <td class="py-3 px-4">
                                    <span class="block font-medium text-slate-800"><?= esc($isG ? $u['email_workspace'] : $u['username']) ?></span>
                                    <span class="mt-0.5 inline-flex items-center gap-1 text-[10px] text-slate-500">
                                        <?php if ($isG): ?>
                                            <i class="fa-brands fa-google"></i> Google Workspace
                                        <?php else: ?>
                                            <i class="fa-solid fa-key"></i> Username &amp; Password
                                        <?php endif; ?>
                                    </span>
                                </td>

                                <td class="py-3 px-4 text-slate-700 font-medium">
                                    <?= esc($u['role_name'] ?? 'User') ?>
                                </td>

                                <td class="py-3 px-4">
                                    <?php if (($u['account_status'] ?? 'active') === 'active'): ?>
                                        <span class="px-2.5 py-1 text-[10px] font-bold bg-emerald-100 text-emerald-700 rounded-full">Active</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 text-[10px] font-bold bg-slate-100 text-slate-600 rounded-full"><?= ucfirst(esc($u['account_status'])) ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="py-3 px-4">
                                    <?php
                                        // Live status comes from the heartbeat (auth/check_token updates session_last_seen every 30 s).
                                        // Online = still holds a login (session_id) AND heartbeat seen in the last 2 minutes.
                                        $seenTs   = ! empty($u['session_last_seen']) ? strtotime((string) $u['session_last_seen']) : false;
                                        $isOnline = ! empty($u['session_id']) && $seenTs && (time() - $seenTs) <= 120;

                                        if (! $seenTs) {
                                            $seenText = 'Never';
                                        } else {
                                            $ago = max(0, time() - $seenTs);
                                            if ($ago < 60)                          { $seenText = 'Just now'; }
                                            elseif ($ago < 3600)                    { $seenText = floor($ago / 60) . ' min ago'; }
                                            elseif (date('Y-m-d', $seenTs) === date('Y-m-d')) { $seenText = 'Today ' . date('g:i A', $seenTs); }
                                            elseif (date('Y-m-d', $seenTs) === date('Y-m-d', strtotime('-1 day'))) { $seenText = 'Yesterday ' . date('g:i A', $seenTs); }
                                            else                                    { $seenText = date('M j, Y g:i A', $seenTs); }
                                        }
                                    ?>
                                    <div class="flex flex-col" data-live-status="<?= (int) $u['id'] ?>">
                                        <?php if ($isOnline): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 rounded w-max">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> ONLINE
                                            </span>
                                            <span class="text-[10px] text-slate-400 mt-0.5">Active now</span>
                                        <?php else: ?>
                                            <span class="inline-block px-2 py-0.5 text-[9px] font-bold bg-rose-50 text-rose-600 border border-rose-200 rounded w-max">
                                                OFFLINE
                                            </span>
                                            <span class="text-[10px] text-slate-400 mt-0.5">Last seen: <?= esc($seenText) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="<?= base_url('accounts/configure/' . $u['id']) ?>" 
                                           class="p-1.5 bg-sky-50 hover:bg-sky-100 text-sky-600 rounded border border-sky-200 transition-colors" title="Configure Account">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <?php if (! empty($can['delete'])): ?>
                                        <button type="button" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded border border-rose-200 transition-colors" title="Delete Account">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">No user accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= DYNAMIC ADD ACCOUNT MODAL ================= -->
<dialog id="modal-add-account" class="m-auto w-[calc(100vw-2rem)] max-w-2xl rounded-xl p-0 text-slate-800 shadow-2xl backdrop:bg-slate-900/50">
    <form action="<?= base_url('accounts/create') ?>" method="POST" enctype="multipart/form-data" id="form-add-account" class="flex max-h-[90vh] flex-col">
        <?= csrf_field() ?>
        
        <!-- MODAL HEADER WITH STEP INDICATORS -->
        <div class="border-b border-slate-200 px-6 py-4 bg-slate-50">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold text-slate-800">Create New Account</h2>
                <button type="button" id="btn-close-add-modal" class="text-slate-400 hover:text-slate-600 text-sm"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <div class="flex items-center justify-between text-xs font-semibold" id="steps-header">
                <div id="step-badge-1" class="flex items-center gap-2 text-emerald-600">
                    <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[11px]">1</span>
                    <span id="badge-label-1">Account Type &amp; Status</span>
                </div>
                <div class="flex-1 h-0.5 bg-slate-200 mx-3"></div>
                <div id="step-badge-2" class="flex items-center gap-2 text-slate-400">
                    <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-[11px]">2</span>
                    <span id="badge-label-2">User Info</span>
                </div>
                <div class="flex-1 h-0.5 bg-slate-200 mx-3"></div>
                <div id="step-badge-3" class="flex items-center gap-2 text-slate-400">
                    <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-[11px]">3</span>
                    <span>Module Access</span>
                </div>
            </div>
        </div>

        <!-- MODAL BODY -->
        <div class="p-6 overflow-y-auto flex-1 space-y-4">

            <!-- STEP 1 -->
            <div id="step-content-1" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-2">Select Authentication Type <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center justify-center gap-2 p-3 rounded-lg border border-slate-200 bg-white cursor-pointer hover:bg-slate-50 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 text-xs font-semibold">
                            <input type="radio" name="auth_type" value="google" id="auth_type_google" checked class="text-emerald-600 focus:ring-emerald-600">
                            <i class="fa-brands fa-google text-slate-600"></i> Google Workspace
                        </label>
                        <label class="flex items-center justify-center gap-2 p-3 rounded-lg border border-slate-200 bg-white cursor-pointer hover:bg-slate-50 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 text-xs font-semibold">
                            <input type="radio" name="auth_type" value="credentials" id="auth_type_cred" class="text-emerald-600 focus:ring-emerald-600">
                            <i class="fa-solid fa-key text-slate-600"></i> Username &amp; Password
                        </label>
                    </div>
                </div>

                <!-- ACCOUNT STATUS FIELD SA STEP 1 -->
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Account Status <span class="text-red-500">*</span></label>
                    <select name="account_status" id="add_account_status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>

                <div id="wrapper-google-email" class="space-y-2 pt-2">
                    <label class="block text-xs font-medium text-slate-700">Workspace Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email_workspace" id="add_email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600" placeholder="user@bounty.com.ph">
                    <p class="text-[11px] text-slate-500">Must belong to your organization's Google Workspace domain (<strong>@bounty.com.ph</strong>).</p>
                </div>

                <div id="wrapper-credentials" class="space-y-3 pt-2 hidden">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Username <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="add_username" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600" placeholder="e.g. jdoe">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="password" name="password" id="add_password" minlength="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600 pr-10" placeholder="At least 8 characters">
                            <button type="button" id="toggle-add-pw" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 text-xs"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: User info (Username & Password accounts) -->
            <div id="step-content-2" class="hidden">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-[11rem_1fr]">

                    <!-- Photo -->
                    <div class="flex flex-col items-center gap-2 sm:border-r sm:border-slate-100 sm:pr-6">
                        <div class="flex h-28 w-28 items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-slate-300 bg-slate-50 text-slate-400">
                            <img id="avatar-preview-img" alt="Photo preview" class="hidden h-full w-full object-cover">
                            <i id="avatar-preview-icon" class="fa-solid fa-user text-3xl"></i>
                        </div>
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-emerald-600">
                            <i class="fa-solid fa-camera"></i> <span id="avatar-btn-label">Upload photo</span>
                            <input type="file" name="avatar" id="add_avatar" accept="image/png,image/jpeg,image/webp" class="sr-only">
                        </label>
                        <button type="button" id="avatar-remove" class="hidden text-[11px] font-medium text-red-600 hover:underline">Remove photo</button>
                        <p id="avatar-error" class="hidden text-center text-[11px] text-red-600"></p>
                        <p class="text-center text-[11px] text-slate-400">JPG, PNG or WEBP.<br>Max 2 MB. Optional.</p>
                    </div>

                    <!-- Fields -->
                    <div class="space-y-5">
                        <div>
                            <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Personal information</h3>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label for="add_first_name" class="mb-1 block text-xs font-medium text-slate-700">First Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="first_name" id="add_first_name" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                                </div>
                                <div>
                                    <label for="add_middle_name" class="mb-1 block text-xs font-medium text-slate-700">Middle Name</label>
                                    <input type="text" name="middle_name" id="add_middle_name" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                                </div>
                                <div>
                                    <label for="add_last_name" class="mb-1 block text-xs font-medium text-slate-700">Last Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="last_name" id="add_last_name" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                                </div>
                            </div>
                            <div class="mt-3 sm:w-1/2 sm:pr-1.5">
                                <label for="add_sex" class="mb-1 block text-xs font-medium text-slate-700">Sex</label>
                                <select name="sex" id="add_sex" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                                    <option value="">Select Sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Contact</h3>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="add_info_email" class="mb-1 block text-xs font-medium text-slate-700">Email Address</label>
                                    <input type="email" name="email" id="add_info_email" maxlength="150" placeholder="user@example.com" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                                </div>
                                <div>
                                    <label for="add_phone" class="mb-1 block text-xs font-medium text-slate-700">Phone Number</label>
                                    <input type="tel" name="phone_number" id="add_phone" maxlength="30" placeholder="09123456789" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 3 -->
            <div id="step-content-3" class="space-y-4 hidden">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Assigned Role <span class="text-red-500">*</span></label>
                    <select name="user_role_id" id="add_role_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800">
                        <?php foreach (($roles ?? []) as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= esc($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="border border-slate-200 rounded-lg p-3 bg-slate-50 space-y-3">
                    <span class="block text-xs font-semibold text-slate-700">Preview &amp; Customize Module Access:</span>
                    <div id="modal-permissions-list" class="space-y-2 max-h-48 overflow-y-auto pr-2 text-xs">
                        <?php foreach (($modules ?? []) as $m): ?>
                            <div class="bg-white p-2.5 rounded border border-slate-200">
                                <div class="font-bold text-slate-800 mb-1 flex items-center gap-1.5">
                                    <i class="<?= esc($m['icon'] ?? 'fa-solid fa-cube') ?> text-emerald-600"></i>
                                    <span><?= esc($m['name']) ?></span>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <?php foreach (($m['actions'] ?? []) as $act): ?>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input type="checkbox" name="permission_ids[]" value="<?= $act['pid'] ?>" class="modal-perm-cb rounded border-slate-300 text-emerald-600">
                                            <span class="text-slate-600"><?= esc($act['action_name']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- FOOTER BUTTONS -->
        <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-6 py-3">
            <button type="button" id="btn-step-prev" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg hidden">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back
            </button>
            <div></div>
            <div class="flex items-center gap-2">
                <button type="button" id="btn-modal-cancel" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg">Cancel</button>
                <button type="button" id="btn-step-next" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                    Next <i class="fa-solid fa-arrow-right ml-1"></i>
                </button>
                <button type="submit" id="btn-step-submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm hidden">
                    <i class="fa-solid fa-check mr-1"></i> Create Account
                </button>
            </div>
        </div>
    </form>
</dialog>

<script>
(function() {
    var modal = document.getElementById('modal-add-account');
    var btnOpen = document.getElementById('btn-open-add-modal');
    var btnClose = document.getElementById('btn-close-add-modal');
    var btnCancel = document.getElementById('btn-modal-cancel');
    var btnNext = document.getElementById('btn-step-next');
    var btnPrev = document.getElementById('btn-step-prev');
    var btnSubmit = document.getElementById('btn-step-submit');
    var roleSelect = document.getElementById('add_role_id');
    var pwInput = document.getElementById('add_password');
    var pwToggle = document.getElementById('toggle-add-pw');
    var form = document.getElementById('form-add-account');

    var currentStep = 1;
    var ROLE_PERMS = <?= json_encode($rolePermissions ?? []) ?>;
    var ALLOWED_DOMAIN = 'bounty.com.ph';

    function getSelectedAuthType() {
        return document.querySelector('input[name="auth_type"]:checked').value;
    }

    if (pwToggle) {
        pwToggle.addEventListener('click', function() {
            var isPw = pwInput.type === 'password';
            pwInput.type = isPw ? 'text' : 'password';
            pwToggle.innerHTML = isPw ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
        });
    }

    document.querySelectorAll('input[name="auth_type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var isGoogle = this.value === 'google';
            document.getElementById('wrapper-google-email').classList.toggle('hidden', !isGoogle);
            document.getElementById('wrapper-credentials').classList.toggle('hidden', isGoogle);
            document.getElementById('step-badge-2').classList.toggle('opacity-40', isGoogle);
        });
    });

    function showStep(step) {
        currentStep = step;
        var authType = getSelectedAuthType();

        [1, 2, 3].forEach(function(s) {
            // Kung Google Workspace, laktawan ang Step 2 sa UI display
            if (s === 2 && authType === 'google') {
                document.getElementById('step-content-2').classList.add('hidden');
                return;
            }

            document.getElementById('step-content-' + s).classList.toggle('hidden', s !== step);
            var badge = document.getElementById('step-badge-' + s);
            if (s === step) {
                badge.className = 'flex items-center gap-2 text-emerald-600 font-bold';
                badge.querySelector('span').className = 'w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[11px]';
            } else if (s < step) {
                badge.className = 'flex items-center gap-2 text-emerald-700';
                badge.querySelector('span').className = 'w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[11px]';
            } else {
                badge.className = 'flex items-center gap-2 text-slate-400';
                badge.querySelector('span').className = 'w-6 h-6 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-[11px]';
            }
        });

        btnPrev.classList.toggle('hidden', step === 1);
        btnNext.classList.toggle('hidden', step === 3);
        btnSubmit.classList.toggle('hidden', step !== 3);
    }

    function syncRolePermissions() {
        if (!roleSelect) return;
        var rid = parseInt(roleSelect.value);
        var allowed = ROLE_PERMS[rid] || [];
        document.querySelectorAll('.modal-perm-cb').forEach(function(cb) {
            cb.checked = allowed.includes(parseInt(cb.value));
        });
    }

    if (btnOpen) {
        btnOpen.addEventListener('click', function() {
            form.reset();
            resetAvatar();
            document.getElementById('auth_type_google').checked = true;
            document.getElementById('wrapper-google-email').classList.remove('hidden');
            document.getElementById('wrapper-credentials').classList.add('hidden');
            showStep(1);
            syncRolePermissions();
            if (modal && modal.showModal) modal.showModal();
        });
    }

    /* ---- Photo upload: preview + check (JPG/PNG/WEBP, max 2 MB) ---- */
    var avatarInput = document.getElementById('add_avatar');
    var avatarImg   = document.getElementById('avatar-preview-img');
    var avatarIcon  = document.getElementById('avatar-preview-icon');
    var avatarRm    = document.getElementById('avatar-remove');
    var avatarErr   = document.getElementById('avatar-error');
    var avatarLabel = document.getElementById('avatar-btn-label');
    var avatarUrl   = null;

    function resetAvatar() {
        if (avatarUrl) { URL.revokeObjectURL(avatarUrl); avatarUrl = null; }
        avatarInput.value = '';
        avatarImg.removeAttribute('src');
        avatarImg.classList.add('hidden');
        avatarIcon.classList.remove('hidden');
        avatarRm.classList.add('hidden');
        avatarErr.classList.add('hidden');
        avatarLabel.textContent = 'Upload photo';
    }
    avatarInput.addEventListener('change', function () {
        var f = avatarInput.files && avatarInput.files[0];
        avatarErr.classList.add('hidden');
        if (!f) { return; }
        if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) {
            avatarErr.textContent = 'Please choose a JPG, PNG or WEBP image.';
        } else if (f.size > 2 * 1024 * 1024) {
            avatarErr.textContent = 'Image is too large (max 2 MB).';
        } else {
            if (avatarUrl) { URL.revokeObjectURL(avatarUrl); }
            avatarUrl = URL.createObjectURL(f);
            avatarImg.src = avatarUrl;
            avatarImg.classList.remove('hidden');
            avatarIcon.classList.add('hidden');
            avatarRm.classList.remove('hidden');
            avatarLabel.textContent = 'Change photo';
            return;
        }
        avatarInput.value = '';
        avatarErr.classList.remove('hidden');
    });
    avatarRm.addEventListener('click', resetAvatar);

    if (btnClose) btnClose.addEventListener('click', function() { modal.close(); });
    if (btnCancel) btnCancel.addEventListener('click', function() { modal.close(); });

    // Pigilan ang agarang pag-submit ng form kapag pinindot ang Enter key
    form.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (currentStep < 3) {
                btnNext.click();
            } else {
                form.submit();
            }
        }
    });

    btnNext.addEventListener('click', function(e) {
        e.preventDefault();
        var authType = getSelectedAuthType();

        if (currentStep === 1) {
            if (authType === 'google') {
                var email = document.getElementById('add_email').value.trim();
                if (!email) {
                    alert('Please enter a Google Workspace email.');
                    return;
                }
                var domainParts = email.split('@');
                if (domainParts.length !== 2 || domainParts[1].toLowerCase() !== ALLOWED_DOMAIN.toLowerCase()) {
                    alert('Invalid domain! Email must belong to @' + ALLOWED_DOMAIN);
                    return;
                }
                showStep(3); // Direkta sa Module Access para sa Google Workspace
                return;
            } else {
                var u = document.getElementById('add_username').value.trim();
                var p = document.getElementById('add_password').value.trim();
                if (!u || !p) {
                    alert('Please fill in both Username and Password.');
                    return;
                }
                if (p.length < 8) {
                    alert('Password must be at least 8 characters long.');
                    return;
                }
                showStep(2); // Sunod sa User Info para sa Standard Credentials
                return;
            }
        } else if (currentStep === 2) {
            var fn = document.getElementById('add_first_name').value.trim();
            var ln = document.getElementById('add_last_name').value.trim();
            if (!fn || !ln) {
                alert('Please enter both First Name and Last Name.');
                return;
            }
            showStep(3); // Sunod sa Module Access
            return;
        }
    });

    btnPrev.addEventListener('click', function(e) {
        e.preventDefault();
        var authType = getSelectedAuthType();
        if (currentStep === 3 && authType === 'google') {
            showStep(1);
        } else {
            showStep(currentStep - 1);
        }
    });

    if (roleSelect) {
        roleSelect.addEventListener('change', syncRolePermissions);
    }
})();
</script>
<script>
/* Live status: every 15 s ask the server (GET = silent, no loading overlay) and update ONLY the status badges.
   The page is never reloaded and nothing else on it changes. */
(function () {
    var cells = document.querySelectorAll('[data-live-status]');
    if (!cells.length) { return; }

    var url = '<?= site_url("auth/live_status") ?>';
    var busy = false;

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text !== undefined) { n.textContent = text; }
        return n;
    }

    function render(cell, info) {
        cell.textContent = '';
        if (info.online) {
            var on = el('span', 'inline-flex items-center gap-1 px-2 py-0.5 text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 rounded w-max');
            on.appendChild(el('span', 'h-1.5 w-1.5 rounded-full bg-emerald-500'));
            on.appendChild(document.createTextNode(' ONLINE'));
            cell.appendChild(on);
            cell.appendChild(el('span', 'text-[10px] text-slate-400 mt-0.5', 'Active now'));
        } else {
            cell.appendChild(el('span', 'inline-block px-2 py-0.5 text-[9px] font-bold bg-rose-50 text-rose-600 border border-rose-200 rounded w-max', 'OFFLINE'));
            cell.appendChild(el('span', 'text-[10px] text-slate-400 mt-0.5', 'Last seen: ' + (info.seen || 'Never')));
        }
    }

    function refresh() {
        if (busy || document.hidden) { return; }
        busy = true;

        var ids = [];
        cells.forEach(function (c) { ids.push(c.getAttribute('data-live-status')); });

        fetch(url + '?ids=' + encodeURIComponent(ids.join(',')), {
            credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.users) { return; }
                cells.forEach(function (c) {
                    var info = data.users[c.getAttribute('data-live-status')];
                    if (info) { render(c, info); }
                });
            })
            .catch(function () {})
            .finally(function () { busy = false; });
    }

    setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { refresh(); } });
})();
</script>
<?= $this->endSection() ?>