<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper('form');

$positions   = $positions ?? [];
$departments = $departments ?? [];
$can         = $can ?? [];   // deny by default
$canCreate   = ! empty($can['create']);
$canUpdate   = ! empty($can['update']);
$canToggle   = ! empty($can['toggle_status']);

$flashSuccess = session()->getFlashdata('success');
$flashError   = session()->getFlashdata('error');
$startTab     = session()->getFlashdata('tab');
if (! in_array($startTab, ['positions', 'departments'], true)) { $startTab = 'positions'; }

$btnPrim  = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2';
$btnGhost = 'inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-semibold rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';
$closeBtn = 'flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';
$dlgClass = 'm-auto w-[calc(100vw-2rem)] max-w-lg rounded-xl p-0 text-slate-800 shadow-xl backdrop:bg-slate-900/50';
$fInput   = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20';
$fLabel   = 'mb-1 block text-xs font-medium text-slate-700';
$badge    = static fn (string $s): string => $s === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600';

/** Activate / deactivate button (a tiny form so the server decides) */
$statusForm = static function (string $url, array $row, bool $canToggle) use ($btnGhost): string {
    if (! $canToggle) { return ''; }
    $on   = $row['status'] === 'active';
    $next = $on ? 'inactive' : 'active';
    $busy = $on && (int) $row['in_use'] > 0;
    $tip  = $busy ? 'Used by ' . (int) $row['in_use'] . ' active employee(s): move them first' : ($on ? 'Deactivate' : 'Activate');

    return '<form method="post" action="' . esc($url, 'attr') . '" class="inline">' . csrf_field()
        . '<input type="hidden" name="status" value="' . $next . '">'
        . '<button type="submit" class="' . $btnGhost . ($busy ? ' cursor-not-allowed opacity-50' : '') . '" title="' . esc($tip, 'attr') . '" ' . ($busy ? 'disabled' : '') . '>'
        . '<i class="fa-solid ' . ($on ? 'fa-ban' : 'fa-circle-check') . '"></i> ' . ($on ? 'Deactivate' : 'Activate') . '</button></form>';
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

    <div>
        <h1 class="text-base font-semibold text-slate-800">Organization</h1>
        <p class="text-xs text-slate-500">The shared lists of positions and departments. Every company uses these same lists.</p>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto border-b border-slate-200 px-4 sm:px-5">
            <nav class="flex min-w-max gap-5" role="tablist" aria-label="Organization lists">
                <?php foreach ([['positions', 'Positions', count($positions)], ['departments', 'Departments', count($departments)]] as [$key, $text, $n]): ?>
                    <button type="button" role="tab" data-tab="<?= $key ?>" id="btn-tab-<?= $key ?>" aria-controls="content-tab-<?= $key ?>"
                            class="tab-link flex items-center gap-2 whitespace-nowrap rounded-t border-b-2 border-transparent py-3 text-xs font-medium text-slate-500 transition-colors hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                        <?= $text ?> <span class="rounded-full bg-slate-100 px-1.5 text-[10px] font-semibold text-slate-600"><?= (int) $n ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="p-4 sm:p-5">

            <!-- POSITIONS -->
            <div id="content-tab-positions" class="tab-content" role="tabpanel" aria-labelledby="btn-tab-positions">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-[11px] text-slate-500">Level 1 is the highest rank. A position cannot change level while active employees hold it.</p>
                    <?php if ($canCreate): ?>
                        <button type="button" data-open="modal-position" data-mode="create" class="<?= $btnPrim ?>"><i class="fa-solid fa-plus"></i> Add position</button>
                    <?php endif; ?>
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Level</th>
                                <th class="px-4 py-3">Position</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3 text-center">Employees</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <?php if ($canUpdate || $canToggle): ?><th class="px-4 py-3 text-right">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($positions as $p): ?>
                                <tr class="hover:bg-slate-50 <?= $p['status'] === 'active' ? '' : 'opacity-60' ?>">
                                    <td class="px-4 py-3"><span class="inline-flex h-6 min-w-6 items-center justify-center rounded-md bg-sky-50 px-1.5 font-mono text-[11px] font-semibold text-sky-700"><?= (int) $p['level'] ?></span></td>
                                    <td class="px-4 py-3 font-semibold text-slate-800"><?= esc($p['name']) ?></td>
                                    <td class="px-4 py-3 text-slate-500"><?= esc($p['description'] ?: '—') ?></td>
                                    <td class="px-4 py-3 text-center"><?= (int) $p['in_use'] ?></td>
                                    <td class="px-4 py-3 text-center"><span class="rounded-full px-2 py-0.5 text-[10px] font-semibold <?= $badge($p['status']) ?>"><?= ucfirst(esc($p['status'])) ?></span></td>
                                    <?php if ($canUpdate || $canToggle): ?>
                                        <td class="whitespace-nowrap px-4 py-3 text-right">
                                            <?php if ($canUpdate): ?>
                                                <button type="button" class="<?= $btnGhost ?>" data-open="modal-position" data-mode="edit"
                                                        data-id="<?= (int) $p['id'] ?>" data-name="<?= esc($p['name'], 'attr') ?>" data-level="<?= (int) $p['level'] ?>"
                                                        data-description="<?= esc((string) $p['description'], 'attr') ?>" data-locked="<?= (int) $p['in_use'] > 0 ? '1' : '0' ?>">
                                                    <i class="fa-solid fa-pen text-slate-500"></i> Edit
                                                </button>
                                            <?php endif; ?>
                                            <?= $statusForm(base_url('organization/positions/' . (int) $p['id'] . '/status'), $p, $canToggle) ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (! $positions): ?>
                                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No positions yet. Add the first one.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- DEPARTMENTS -->
            <div id="content-tab-departments" class="tab-content hidden" role="tabpanel" aria-labelledby="btn-tab-departments">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-[11px] text-slate-500">Which positions each company uses in a department is set separately, per company.</p>
                    <?php if ($canCreate): ?>
                        <button type="button" data-open="modal-department" data-mode="create" class="<?= $btnPrim ?>"><i class="fa-solid fa-plus"></i> Add department</button>
                    <?php endif; ?>
                </div>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold text-slate-600">
                            <tr>
                                <th class="px-4 py-3">Department</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3 text-center">Employees</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <?php if ($canUpdate || $canToggle): ?><th class="px-4 py-3 text-right">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($departments as $d): ?>
                                <tr class="hover:bg-slate-50 <?= $d['status'] === 'active' ? '' : 'opacity-60' ?>">
                                    <td class="px-4 py-3 font-semibold text-slate-800"><?= esc($d['name']) ?></td>
                                    <td class="px-4 py-3 text-slate-500"><?= esc($d['description'] ?: '—') ?></td>
                                    <td class="px-4 py-3 text-center"><?= (int) $d['in_use'] ?></td>
                                    <td class="px-4 py-3 text-center"><span class="rounded-full px-2 py-0.5 text-[10px] font-semibold <?= $badge($d['status']) ?>"><?= ucfirst(esc($d['status'])) ?></span></td>
                                    <?php if ($canUpdate || $canToggle): ?>
                                        <td class="whitespace-nowrap px-4 py-3 text-right">
                                            <?php if ($canUpdate): ?>
                                                <button type="button" class="<?= $btnGhost ?>" data-open="modal-department" data-mode="edit"
                                                        data-id="<?= (int) $d['id'] ?>" data-name="<?= esc($d['name'], 'attr') ?>" data-description="<?= esc((string) $d['description'], 'attr') ?>">
                                                    <i class="fa-solid fa-pen text-slate-500"></i> Edit
                                                </button>
                                            <?php endif; ?>
                                            <?= $statusForm(base_url('organization/departments/' . (int) $d['id'] . '/status'), $d, $canToggle) ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (! $departments): ?>
                                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No departments yet. Add the first one.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<?php if ($canCreate || $canUpdate): ?>
<!-- POSITION: add / edit -->
<dialog id="modal-position" aria-labelledby="position-title" class="<?= $dlgClass ?>">
    <form id="position-form" method="post" action="<?= base_url('organization/positions/save') ?>" class="flex flex-col">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 id="position-title" class="text-sm font-semibold text-slate-800">Add position</h2>
            <button type="button" data-close aria-label="Close" class="<?= $closeBtn ?>"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-3 p-5">
            <div>
                <label for="pos-name" class="<?= $fLabel ?>">Position name <span class="text-red-500">*</span></label>
                <input id="pos-name" name="name" required maxlength="100" class="<?= $fInput ?>">
            </div>
            <div>
                <label for="pos-level" class="<?= $fLabel ?>">Level <span class="text-red-500">*</span></label>
                <input id="pos-level" name="level" type="number" min="1" max="99" required class="<?= $fInput ?>">
                <p id="pos-level-note" class="mt-1 text-[11px] text-slate-400">1 is the highest rank. The level decides who may handle whom.</p>
            </div>
            <div>
                <label for="pos-desc" class="<?= $fLabel ?>">Description</label>
                <input id="pos-desc" name="description" maxlength="255" class="<?= $fInput ?>">
            </div>
        </div>
        <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Save position</button>
        </div>
    </form>
</dialog>

<!-- DEPARTMENT: add / edit -->
<dialog id="modal-department" aria-labelledby="department-title" class="<?= $dlgClass ?>">
    <form id="department-form" method="post" action="<?= base_url('organization/departments/save') ?>" class="flex flex-col">
        <?= csrf_field() ?>
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 id="department-title" class="text-sm font-semibold text-slate-800">Add department</h2>
            <button type="button" data-close aria-label="Close" class="<?= $closeBtn ?>"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-3 p-5">
            <div>
                <label for="dep-name" class="<?= $fLabel ?>">Department name <span class="text-red-500">*</span></label>
                <input id="dep-name" name="name" required maxlength="100" class="<?= $fInput ?>">
            </div>
            <div>
                <label for="dep-desc" class="<?= $fLabel ?>">Description</label>
                <input id="dep-desc" name="description" maxlength="255" class="<?= $fInput ?>">
            </div>
        </div>
        <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Save department</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script>
(function () {
    var base = <?= json_encode(base_url('organization')) ?>;
    var ON  = ['border-emerald-600', 'text-emerald-700', 'font-semibold'];
    var OFF = ['border-transparent', 'text-slate-500', 'hover:text-slate-700', 'font-medium'];

    function switchTab(name) {
        document.querySelectorAll('.tab-content').forEach(function (el) { el.classList.add('hidden'); });
        document.querySelectorAll('.tab-link').forEach(function (btn) {
            var on = btn.dataset.tab === name;
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
            btn.classList.remove.apply(btn.classList, ON.concat(OFF));
            btn.classList.add.apply(btn.classList, on ? ON : OFF);
        });
        document.getElementById('content-tab-' + name).classList.remove('hidden');
    }
    document.querySelectorAll('.tab-link').forEach(function (b) { b.addEventListener('click', function () { switchTab(b.dataset.tab); }); });
    switchTab(<?= json_encode($startTab) ?>);

    document.querySelectorAll('dialog').forEach(function (d) {
        d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
        d.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
    });

    document.querySelectorAll('[data-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.open, edit = btn.dataset.mode === 'edit', d = document.getElementById(id);
            if (!d) { return; }
            if (id === 'modal-position') {
                var f = document.getElementById('position-form'); f.reset();
                var lvl = document.getElementById('pos-level'), note = document.getElementById('pos-level-note');
                lvl.readOnly = false;
                note.textContent = '1 is the highest rank. The level decides who may handle whom.';
                document.getElementById('position-title').textContent = edit ? 'Edit position' : 'Add position';
                f.action = base + '/positions/' + (edit ? btn.dataset.id + '/save' : 'save');
                if (edit) {
                    document.getElementById('pos-name').value = btn.dataset.name;
                    lvl.value = btn.dataset.level;
                    document.getElementById('pos-desc').value = btn.dataset.description;
                    if (btn.dataset.locked === '1') { lvl.readOnly = true; note.textContent = 'The level is locked while active employees hold this position.'; }
                }
            } else {
                var g = document.getElementById('department-form'); g.reset();
                document.getElementById('department-title').textContent = edit ? 'Edit department' : 'Add department';
                g.action = base + '/departments/' + (edit ? btn.dataset.id + '/save' : 'save');
                if (edit) {
                    document.getElementById('dep-name').value = btn.dataset.name;
                    document.getElementById('dep-desc').value = btn.dataset.description;
                }
            }
            d.showModal();
        });
    });
})();
</script>
<?= $this->endSection() ?>
