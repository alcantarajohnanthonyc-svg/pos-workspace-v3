<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$canCreate = ! empty($can['create']);
$canUpdate = ! empty($can['update']);
$canDelete = ! empty($can['delete']);

$btnPrim  = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors';
$btnGhost = 'inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-semibold rounded-lg transition-colors';
$dlgClass = 'm-auto w-[calc(100vw-2rem)] max-w-lg rounded-xl p-0 text-slate-800 shadow-xl backdrop:bg-slate-900/50';
?>

<div class="space-y-4 pb-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-base font-semibold text-slate-800">Branch Management</h1>
            <p class="text-xs text-slate-500">Manage individual branches mapped to specific companies.</p>
        </div>
        <?php if ($canCreate): ?>
            <button type="button" data-open-modal="modal-create" class="<?= $btnPrim ?>">
                <i class="fa-solid fa-plus"></i> Add Branch
            </button>
        <?php endif; ?>
    </div>

    <!-- Data Table -->
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase text-slate-600">
                <tr>
                    <th class="px-4 py-3">Branch Name</th>
                    <th class="px-4 py-3">Company</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <?php if ($canUpdate || $canDelete): ?>
                        <th class="px-4 py-3 text-center">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($branches as $b): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-semibold text-slate-800"><?= esc($b['branch_name']) ?></td>
                        <td class="px-4 py-3 text-slate-700">
                            <span class="font-mono text-emerald-700 font-semibold">[<?= esc($b['company_short_name']) ?>]</span> 
                            <?= esc($b['company_name']) ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold <?= $b['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>">
                                <?= ucfirst($b['status']) ?>
                            </span>
                        </td>
                        <?php if ($canUpdate || $canDelete): ?>
                            <td class="px-4 py-3 text-center space-x-1">
                                <?php if ($canUpdate): ?>
                                    <button type="button" class="btn-edit <?= $btnGhost ?>" 
                                            data-id="<?= $b['id'] ?>"
                                            data-company_id="<?= $b['company_id'] ?>"
                                            data-branch_name="<?= esc($b['branch_name'], 'attr') ?>"
                                            data-status="<?= $b['status'] ?>">
                                        <i class="fa-solid fa-pen text-slate-500"></i> Edit
                                    </button>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <button type="button" class="btn-delete inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                                            data-id="<?= $b['id'] ?>" data-name="<?= esc($b['branch_name'], 'attr') ?>">
                                        <i class="fa-solid fa-trash-can"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($branches)): ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No branches found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CREATE MODAL -->
<?php if ($canCreate): ?>
<dialog id="modal-create" class="<?= $dlgClass ?>">
    <form action="<?= base_url('branch/store') ?>" method="POST" class="flex flex-col">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Add New Branch</h2>
            <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-3 p-5 text-xs">
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Company <span class="text-red-500">*</span></label>
                <select name="company_id" required class="w-full rounded-lg border border-slate-300 p-2 text-xs focus:border-emerald-600 focus:outline-none">
                    <option value="">-- Select Company --</option>
                    <?php foreach ($companies as $comp): ?>
                        <option value="<?= (int) $comp['id'] ?>"><?= esc($comp['name']) ?> ([<?= esc($comp['short_name']) ?>])</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Branch Name <span class="text-red-500">*</span></label>
                <input type="text" name="branch_name" required class="w-full rounded-lg border border-slate-300 p-2 text-xs focus:border-emerald-600 focus:outline-none">
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close-modal class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<!-- EDIT MODAL -->
<?php if ($canUpdate): ?>
<dialog id="modal-edit" class="<?= $dlgClass ?>">
    <form id="form-edit" action="" method="POST" class="flex flex-col">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Edit Branch</h2>
            <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-3 p-5 text-xs">
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Company <span class="text-red-500">*</span></label>
                <select id="edit-company_id" name="company_id" required class="w-full rounded-lg border border-slate-300 p-2 text-xs focus:border-emerald-600 focus:outline-none">
                    <option value="">-- Select Company --</option>
                    <?php foreach ($companies as $comp): ?>
                        <option value="<?= (int) $comp['id'] ?>"><?= esc($comp['name']) ?> ([<?= esc($comp['short_name']) ?>])</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Branch Name <span class="text-red-500">*</span></label>
                <input type="text" id="edit-branch_name" name="branch_name" required class="w-full rounded-lg border border-slate-300 p-2 text-xs focus:border-emerald-600 focus:outline-none">
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Status</label>
                <select id="edit-status" name="status" class="w-full rounded-lg border border-slate-300 p-2 text-xs focus:border-emerald-600 focus:outline-none">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close-modal class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Update Changes</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<!-- DELETE MODAL -->
<?php if ($canDelete): ?>
<dialog id="modal-delete" class="<?= $dlgClass ?>">
    <form id="form-delete" action="" method="POST" class="flex flex-col">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Delete Branch</h2>
            <button type="button" data-close-modal class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="p-5 text-xs text-slate-600">
            <p>Are you sure you want to delete branch <strong id="delete-name"></strong>?</p>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close-modal class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" class="rounded-lg bg-red-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-red-700">Confirm Delete</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script>
(function() {
    document.querySelectorAll('[data-open-modal]').forEach(b => {
        b.addEventListener('click', () => {
            const d = document.getElementById(b.dataset.openModal);
            if (d && d.showModal) d.showModal();
        });
    });
    document.querySelectorAll('dialog').forEach(d => {
        d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
        d.querySelectorAll('[data-close-modal]').forEach(b => b.addEventListener('click', () => d.close()));
    });

    <?php if ($canUpdate): ?>
    document.querySelectorAll('.btn-edit').forEach(b => {
        b.addEventListener('click', () => {
            const id = b.dataset.id;
            document.getElementById('form-edit').action = '<?= base_url('branch/update') ?>/' + id;
            document.getElementById('edit-company_id').value = b.dataset.company_id;
            document.getElementById('edit-branch_name').value = b.dataset.branch_name;
            document.getElementById('edit-status').value = b.dataset.status;
            document.getElementById('modal-edit').showModal();
        });
    });
    <?php endif; ?>

    <?php if ($canDelete): ?>
    document.querySelectorAll('.btn-delete').forEach(b => {
        b.addEventListener('click', () => {
            const id = b.dataset.id;
            document.getElementById('form-delete').action = '<?= base_url('branch/delete') ?>/' + id;
            document.getElementById('delete-name').textContent = b.dataset.name;
            document.getElementById('modal-delete').showModal();
        });
    });
    <?php endif; ?>
})();
</script>
<?= $this->endSection() ?>