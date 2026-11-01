<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$role                   = $role ?? [];
$modules                = $modules ?? [];
$assignedPermissionIds  = $assignedPermissionIds ?? [];
$roleId                 = $role['id'] ?? null;
$can                    = $can ?? ['create' => false, 'update' => false];
$canSave                = $roleId ? ! empty($can['update']) : ! empty($can['create']);

$input   = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20';
$label   = 'block text-xs font-medium text-slate-700 mb-1';
$btnPrim = 'inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors focus:outline-none';
?>
<div class="space-y-6 pb-12">

    <!-- BACK BUTTON -->
    <div>
        <a href="<?= base_url('roles') ?>"
           class="inline-flex items-center gap-2 px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 font-semibold rounded-lg text-xs transition-colors border border-slate-200">
            <i class="fa-solid fa-arrow-left"></i> Back to Role Management
        </a>
    </div>

    <form action="<?= base_url('roles/save' . ($roleId ? '/' . $roleId : '')) ?>" method="POST" class="space-y-6">
        <?= csrf_field() ?>

        <!-- GENERAL INFORMATION -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
            <h2 class="text-xs font-semibold text-slate-800 uppercase tracking-wider">Role Information</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="<?= $label ?>">Role Name <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" required value="<?= esc($role['name'] ?? '') ?>" placeholder="e.g. Supervisor" class="<?= $input ?>" <?= $canSave ? '' : 'disabled' ?>>
                </div>
                <div>
                    <label for="description" class="<?= $label ?>">Description</label>
                    <input type="text" id="description" name="description" value="<?= esc($role['description'] ?? '') ?>" placeholder="Brief details about this role" class="<?= $input ?>" <?= $canSave ? '' : 'disabled' ?>>
                </div>
            </div>
        </section>

        <!-- MODULE PERMISSIONS ASSIGNMENT -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
            <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-semibold text-slate-800 uppercase tracking-wider">Assign Module Permissions</h2>
                    <p class="text-[11px] text-slate-500">Check the permissions and actions allowed for this role.</p>
                </div>
                <?php if ($canSave): ?>
                    <div>
                        <button type="button" id="select-all" class="text-xs text-emerald-600 font-semibold hover:underline mr-3">Select All</button>
                        <button type="button" id="deselect-all" class="text-xs text-slate-500 font-semibold hover:underline">Deselect All</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="space-y-4">
                <?php if (!empty($modules)): ?>
                    <?php foreach ($modules as $mod): ?>
                        <div class="rounded-lg border border-slate-200 p-4 bg-slate-50/50">
                            <div class="font-bold text-xs text-slate-800 mb-3 flex items-center gap-2">
                                <i class="fa-solid <?= esc($mod['icon'] ?? 'fa-cube') ?> text-emerald-600"></i> 
                                <span><?= esc($mod['name']) ?></span>
                            </div>
                            
                            <?php if (!empty($mod['permissions'])): ?>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5">
                                    <?php foreach ($mod['permissions'] as $perm): ?>
                                        <label class="flex items-center gap-2 text-xs text-slate-700 bg-white p-2.5 rounded-lg border border-slate-200 cursor-pointer hover:border-emerald-300 transition-colors">
                                            <input type="checkbox" name="permissions[]" value="<?= (int) $perm['pid'] ?>" 
                                                <?= in_array($perm['pid'], $assignedPermissionIds, true) ? 'checked' : '' ?>
                                                <?= $canSave ? '' : 'disabled' ?>
                                                class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 perm-checkbox">
                                            <span class="font-medium"><?= esc($perm['action_name']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-[11px] text-slate-400 italic">No permissions available for this module.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-xs text-slate-400 text-center py-6">No functional modules found.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- SUBMIT BUTTON -->
        <div class="flex justify-end pt-2">
            <?php if ($canSave): ?>
                <button type="submit" class="<?= $btnPrim ?>">
                    <i class="fa-solid fa-floppy-disk"></i> Save Role Permissions
                </button>
            <?php else: ?>
                <span class="text-xs text-slate-500 italic"><i class="fa-solid fa-lock"></i> View only. You do not have permission to modify roles.</span>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
document.getElementById('select-all')?.addEventListener('click', function() {
    document.querySelectorAll('.perm-checkbox').forEach(function(cb) { cb.checked = true; });
});
document.getElementById('deselect-all')?.addEventListener('click', function() {
    document.querySelectorAll('.perm-checkbox').forEach(function(cb) { cb.checked = false; });
});
</script>
<?= $this->endSection() ?>