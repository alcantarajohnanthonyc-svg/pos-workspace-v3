<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$roles        = $roles ?? [];
$can          = $can ?? ['create' => false, 'update' => false, 'delete' => false];
$flashSuccess = session()->getFlashdata('success');
$flashError   = session()->getFlashdata('error');

$btnPrim = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors focus:outline-none';
?>
<div class="space-y-6 pb-12">

    <?php if ($flashSuccess): ?>
        <div class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">
            <i class="fa-solid fa-circle-check text-emerald-600"></i><span><?= esc($flashSuccess) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800">
            <i class="fa-solid fa-circle-exclamation text-red-600"></i><span><?= esc($flashError) ?></span>
        </div>
    <?php endif; ?>

    <!-- HEADER BANNER -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-lg font-bold text-slate-800">Role Management</h1>
            <p class="text-xs text-slate-500 mt-0.5">Manage user roles and assign granular module permissions.</p>
        </div>
        <?php if (! empty($can['create'])): ?>
            <div>
                <a href="<?= base_url('roles/create') ?>" class="<?= $btnPrim ?>">
                    <i class="fa-solid fa-plus"></i> Add New Role
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ROLES TABLE -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-4">Role Name</th>
                        <th class="py-3.5 px-4">Slug</th>
                        <th class="py-3.5 px-4">Description</th>
                        <th class="py-3.5 px-4 text-center">Assigned Users</th>
                        <?php if (! empty($can['update'])): ?>
                            <th class="py-3.5 px-4 text-center">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (!empty($roles)): ?>
                        <?php foreach ($roles as $r): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4 font-bold text-slate-800"><?= esc($r['name']) ?></td>
                                <td class="py-3 px-4 font-mono text-emerald-700"><?= esc($r['slug']) ?></td>
                                <td class="py-3 px-4 text-slate-500"><?= esc($r['description'] ?? 'No description.') ?></td>
                                <td class="py-3 px-4 text-center font-semibold text-slate-700"><?= (int) $r['user_count'] ?></td>
                                <?php if (! empty($can['update'])): ?>
                                    <td class="py-3 px-4 text-center">
                                        <a href="<?= base_url('roles/configure/' . $r['id']) ?>" 
                                           class="px-3 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-600 rounded-lg border border-sky-200 transition-colors font-semibold text-xs inline-flex items-center gap-1.5">
                                            <i class="fa-solid fa-gear"></i> Configure
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= ! empty($can['update']) ? 5 : 4 ?>" class="py-8 text-center text-slate-400">No roles found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>