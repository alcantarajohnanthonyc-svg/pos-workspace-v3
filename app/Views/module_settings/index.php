<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper('module');

$modules       = $modules ?? [];
$parentModules = $parentModules ?? [];
$availableActions = $availableActions ?? [];
$can           = $can ?? [];          // deny by default: buttons show only when the controller sent true
$canCreate     = ! empty($can['create']);
$canToggle     = ! empty($can['toggle_status']);

$isEnabled = static fn (array $m): bool => strtolower((string) ($m['status'] ?? 'enabled')) === 'enabled';
$total     = count($modules);
$enabled   = count(array_filter($modules, $isEnabled));
$disabled  = $total - $enabled;
$cats      = count(array_filter($modules, static fn ($m) => (int) ($m['is_category'] ?? 0) === 1));

$flashSuccess = session()->getFlashdata('success');
$flashError   = session()->getFlashdata('error');

$btnPrim  = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2';
$btnGhost = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-semibold rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';
$closeBtn = 'flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';
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

    <!-- HEADER -->
    <section class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <h1 class="text-base font-semibold text-slate-800">Module Settings</h1>
            <p class="mt-0.5 text-xs text-slate-500">Manage system modules, their permissions and whether they are available to users.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <div class="relative sm:w-60">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="search" id="search-module" placeholder="Search modules…" aria-label="Search modules"
                       class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-9 pr-3 text-xs text-slate-800 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
            </div>
            <select id="filter-status" aria-label="Filter by status"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                <option value="">All statuses</option>
                <option value="enabled">Enabled</option>
                <option value="disabled">Disabled</option>
            </select>
            <?php if ($canCreate): ?>
                <button type="button" data-open-modal="modal-add" class="<?= $btnPrim ?>"><i class="fa-solid fa-plus"></i> Add Module</button>
            <?php endif; ?>
        </div>
    </section>

    <!-- STATS -->
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <?php foreach ([
            ['Total Modules', $total, 'fa-cubes-stacked', 'bg-sky-50 text-sky-600'],
            ['Enabled', $enabled, 'fa-circle-check', 'bg-emerald-50 text-emerald-600'],
            ['Disabled', $disabled, 'fa-ban', 'bg-slate-100 text-slate-500'],
            ['Categories', $cats, 'fa-folder-tree', 'bg-indigo-50 text-indigo-600'],
        ] as [$lbl, $num, $ico, $tone]): ?>
            <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-sm <?= $tone ?>"><i class="fa-solid <?= $ico ?>"></i></div>
                <div class="min-w-0">
                    <div class="truncate text-[11px] font-medium uppercase tracking-wider text-slate-500"><?= $lbl ?></div>
                    <div class="text-lg font-semibold leading-tight text-slate-800"><?= (int) $num ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- TABLE -->
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600" id="modules-table">
                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">Module</th>
                        <th scope="col" class="px-4 py-3">Type</th>
                        <th scope="col" class="px-4 py-3">Page URL</th>
                        <th scope="col" class="px-4 py-3">Permissions</th>
                        <th scope="col" class="px-4 py-3 text-center">Order</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($modules as $m):
                        $on    = $isEnabled($m);
                        $isCat = (int) ($m['is_category'] ?? 0) === 1;
                        $acts  = $m['actions'] ?? [];
                        $lock  = ($m['slug'] ?? '') === 'module_setting';   // can never be switched off
                    ?>
                        <tr class="module-row transition-colors hover:bg-slate-50/70" data-status="<?= $on ? 'enabled' : 'disabled' ?>">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg bg-emerald-50 text-sm text-emerald-600 <?= $on ? '' : 'opacity-50' ?>">
                                        <?= module_icon_html($m['icon'] ?? '', 'h-full w-full p-1') ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="module-name truncate font-semibold text-slate-800"><?= esc($m['name']) ?></span>
                                            <?php if (! empty($m['badge'])): ?>
                                                <span class="rounded-full bg-sky-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase text-sky-700"><?= esc($m['badge']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="module-slug truncate font-mono text-[10px] text-slate-400"><?= esc($m['slug']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($isCat): ?>
                                    <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Category</span>
                                <?php else: ?>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">Module</span>
                                <?php endif; ?>
                                <?php if (! empty($m['parent_name'])): ?>
                                    <div class="mt-0.5 text-[10px] text-slate-400">in <?= esc($m['parent_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 font-mono text-[11px]"><?= ! empty($m['url']) ? '/' . esc($m['url']) : '<span class="text-slate-300">—</span>' ?></td>
                            <td class="px-4 py-3">
                                <?php if ($acts): ?>
                                    <div class="flex max-w-[16rem] flex-wrap gap-1">
                                        <?php foreach (array_slice($acts, 0, 4) as $a): ?>
                                            <span class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-medium text-slate-600"><?= esc($a['action_name']) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($acts) > 4): ?>
                                            <span class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-500">+<?= count($acts) - 4 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center"><?= (int) ($m['short_order'] ?? 0) ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <?php if ($canToggle && ! $lock): ?>
                                        <button type="button" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>"
                                                aria-label="<?= $on ? 'Disable' : 'Enable' ?> <?= esc($m['name'], 'attr') ?>"
                                                data-status-toggle data-id="<?= (int) $m['id'] ?>" data-name="<?= esc($m['name'], 'attr') ?>" data-next="<?= $on ? 'disabled' : 'enabled' ?>"
                                                class="relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-1 <?= $on ? 'bg-emerald-600' : 'bg-slate-300' ?>">
                                            <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform <?= $on ? 'translate-x-4' : 'translate-x-0.5' ?>"></span>
                                        </button>
                                    <?php endif; ?>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold <?= $on ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>"><?= $on ? 'Enabled' : 'Disabled' ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="<?= esc(base_url('modules/configure/' . (int) $m['id']), 'attr') ?>"
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                                    <i class="fa-solid fa-sliders"></i> Configure
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="no-results" class="<?= $modules ? 'hidden' : '' ?>">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                            <i class="fa-solid fa-cubes-stacked mb-2 text-lg"></i>
                            <p class="text-xs">No modules found.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- ============ ADD MODULE ============ -->
<?php if ($canCreate): ?>
<dialog id="modal-add" aria-labelledby="modal-add-title"
        class="m-auto w-[calc(100vw-2rem)] max-w-2xl rounded-xl p-0 text-slate-800 shadow-xl backdrop:bg-slate-900/50">
    <form id="form-add-module" action="<?= esc(base_url('modules/create'), 'attr') ?>" method="POST" enctype="multipart/form-data" class="flex max-h-[90vh] flex-col">
        <?= csrf_field() ?>
        <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="modal-add-title" class="text-sm font-semibold">Add Module</h2>
                <button type="button" data-close-modal aria-label="Close" class="<?= $closeBtn ?>"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="module-add-steps" class="flex items-center gap-3 text-xs font-semibold" aria-label="Module creation steps">
                <div data-step-badge="1" class="flex items-center gap-2 text-emerald-600">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">1</span>
                    <span>Module Details</span>
                </div>
                <div data-step-connector class="h-0.5 flex-1 bg-slate-200"></div>
                <div data-step-badge="2" class="flex items-center gap-2 text-slate-400">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-[11px] text-slate-600">2</span>
                    <span>Actions</span>
                </div>
            </div>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <section id="module-step-details" data-module-step="1">
                <?= view('module_settings/fields', ['prefix' => 'add', 'mode' => 'create', 'm' => [], 'parentModules' => $parentModules], ['saveData' => false]) ?>
            </section>
            <section id="module-step-actions" data-module-step="2" class="hidden space-y-3">
                <div>
                    <h3 class="text-xs font-semibold text-slate-800">Choose Module Actions</h3>
                    <p class="mt-1 text-[11px] text-slate-500">Select the existing actions this module supports. These are permission entries only; they do not create routes or features.</p>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[11px] text-slate-500">Selection is optional. You can manage permissions later.</span>
                    <div class="flex gap-3 text-[11px] font-semibold">
                        <button type="button" data-select-actions class="text-emerald-700 hover:underline">Select all</button>
                        <button type="button" data-clear-actions class="text-slate-500 hover:underline">Clear</button>
                    </div>
                </div>
                <?php if ($availableActions): ?>
                    <fieldset class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <legend class="sr-only">Available module actions</legend>
                        <?php foreach ($availableActions as $action): ?>
                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 bg-white p-3 text-xs hover:border-emerald-300 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/50">
                                <input type="checkbox" name="action_ids[]" value="<?= (int) $action['id'] ?>" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
                                <span>
                                    <span class="block font-semibold text-slate-800"><?= esc($action['name']) ?></span>
                                    <span class="font-mono text-[10px] text-slate-500"><?= esc($action['action_key']) ?></span>
                                    <?php if (! empty($action['description'])): ?><span class="mt-1 block text-[11px] text-slate-500"><?= esc($action['description']) ?></span><?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php else: ?>
                    <p class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-center text-xs text-slate-500">No actions are available in the shared action catalog.</p>
                <?php endif; ?>
            </section>
        </div>
        <div class="flex items-center justify-between gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-module-prev class="<?= $btnGhost ?> hidden"><i class="fa-solid fa-arrow-left"></i> Back</button>
            <span class="flex-1"></span>
            <button type="button" data-close-modal class="<?= $btnGhost ?>">Cancel</button>
            <button type="button" data-module-next class="<?= $btnPrim ?>">Continue <i class="fa-solid fa-arrow-right"></i></button>
            <button type="submit" data-module-submit class="<?= $btnPrim ?> hidden"><i class="fa-solid fa-plus"></i> Create Module</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<!-- ============ ENABLE / DISABLE CONFIRM ============ -->
<?php if ($canToggle): ?>
<dialog id="modal-status" aria-labelledby="modal-status-title"
        class="m-auto w-[calc(100vw-2rem)] max-w-md rounded-xl p-0 text-slate-800 shadow-xl backdrop:bg-slate-900/50">
    <form id="status-form" action="" method="POST" class="flex flex-col">
        <?= csrf_field() ?>
        <input type="hidden" name="status" id="status-value" value="">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 id="modal-status-title" class="text-sm font-semibold">Change module status</h2>
            <button type="button" data-close-modal aria-label="Close" class="<?= $closeBtn ?>"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="space-y-2 px-5 py-4 text-xs text-slate-600">
            <p id="status-text"></p>
            <p id="status-warning" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800"></p>
        </div>
        <div class="flex justify-end gap-2 rounded-b-xl border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="button" data-close-modal class="<?= $btnGhost ?>">Cancel</button>
            <button type="submit" id="status-confirm" class="<?= $btnPrim ?>">Confirm</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script src="<?= esc(base_url('assets/js/module-form.js'), 'attr') ?>" defer></script>
<script>
(function () {
    /* ---- Dialogs ---- */
    document.querySelectorAll('[data-open-modal]').forEach(function (b) {
        b.addEventListener('click', function () {
            var d = document.getElementById(b.dataset.openModal);
            if (d && d.showModal) { d.showModal(); }
        });
    });
    document.querySelectorAll('dialog').forEach(function (d) {
        d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
        d.querySelectorAll('[data-close-modal]').forEach(function (b) { b.addEventListener('click', function () { d.close(); }); });
        d.addEventListener('close', function () { var f = d.querySelector('form'); if (f && d.id !== 'modal-status') { f.reset(); } });
    });

    /* ---- Add module steps ---- */
    var addForm = document.getElementById('form-add-module');
    if (addForm) {
        var typeSelect = addForm.querySelector('[data-type]');
        var stepDetails = document.getElementById('module-step-details');
        var stepActions = document.getElementById('module-step-actions');
        var nextButton = addForm.querySelector('[data-module-next]');
        var prevButton = addForm.querySelector('[data-module-prev]');
        var submitButton = addForm.querySelector('[data-module-submit]');
        var actionsBadge = document.querySelector('#module-add-steps [data-step-badge="2"]');
        var stepConnector = document.querySelector('#module-add-steps [data-step-connector]');
        var currentStep = 1;

        function showModuleStep(step) {
            currentStep = step;
            var isCategory = typeSelect.value === '1';
            stepDetails.classList.toggle('hidden', step !== 1);
            stepActions.classList.toggle('hidden', step !== 2);
            actionsBadge.classList.toggle('hidden', isCategory);
            stepConnector.classList.toggle('hidden', isCategory);
            document.querySelectorAll('#module-add-steps [data-step-badge]').forEach(function (badge) {
                var active = Number(badge.dataset.stepBadge) === step;
                badge.classList.toggle('text-emerald-600', active);
                badge.classList.toggle('text-slate-400', !active);
                var circle = badge.querySelector('span');
                circle.classList.toggle('bg-emerald-600', active);
                circle.classList.toggle('text-white', active);
                circle.classList.toggle('bg-slate-200', !active);
                circle.classList.toggle('text-slate-600', !active);
            });
            prevButton.classList.toggle('hidden', step === 1);
            nextButton.classList.toggle('hidden', step !== 1 || isCategory);
            submitButton.classList.toggle('hidden', step === 1 && !isCategory);
        }

        nextButton.addEventListener('click', function () {
            if (!addForm.reportValidity()) { return; }
            showModuleStep(2);
        });
        prevButton.addEventListener('click', function () { showModuleStep(1); });
        typeSelect.addEventListener('change', function () {
            if (typeSelect.value === '1') {
                addForm.querySelectorAll('input[name="action_ids[]"]').forEach(function (input) { input.checked = false; });
                showModuleStep(1);
                return;
            }
            showModuleStep(currentStep);
        });
        addForm.addEventListener('submit', function (event) {
            if (currentStep === 1 && typeSelect.value !== '1') {
                event.preventDefault();
                if (addForm.reportValidity()) { showModuleStep(2); }
            }
        });
        addForm.addEventListener('reset', function () {
            setTimeout(function () { showModuleStep(1); }, 0);
        });
        addForm.querySelector('[data-select-actions]').addEventListener('click', function () {
            addForm.querySelectorAll('input[name="action_ids[]"]').forEach(function (input) { input.checked = true; });
        });
        addForm.querySelector('[data-clear-actions]').addEventListener('click', function () {
            addForm.querySelectorAll('input[name="action_ids[]"]').forEach(function (input) { input.checked = false; });
        });
    }

    /* ---- Search + status filter ---- */
    var q = document.getElementById('search-module'), st = document.getElementById('filter-status');
    function applyFilter() {
        var text = q.value.trim().toLowerCase(), status = st.value, shown = 0;
        document.querySelectorAll('.module-row').forEach(function (row) {
            var hay = row.querySelector('.module-name').textContent.toLowerCase() + ' ' + row.querySelector('.module-slug').textContent.toLowerCase();
            var ok = (text === '' || hay.indexOf(text) !== -1) && (status === '' || row.dataset.status === status);
            row.classList.toggle('hidden', !ok);
            if (ok) { shown++; }
        });
        document.getElementById('no-results').classList.toggle('hidden', shown > 0);
    }
    q.addEventListener('input', applyFilter);
    st.addEventListener('change', applyFilter);

    /* ---- Enable / disable confirm ---- */
    var dlg = document.getElementById('modal-status');
    if (dlg) {
        document.querySelectorAll('[data-status-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var off = btn.dataset.next === 'disabled';
                document.getElementById('status-form').action = <?= json_encode(base_url('modules')) ?> + '/' + btn.dataset.id + '/status';
                document.getElementById('status-value').value = btn.dataset.next;
                document.getElementById('status-text').textContent = (off ? 'Disable ' : 'Enable ') + btn.dataset.name + '?';
                document.getElementById('status-warning').textContent = off
                    ? 'It will disappear from the sidebar and nobody, not even Super Admin, can open it until it is enabled again.'
                    : 'Users who hold its permissions will see it in the sidebar again.';
                document.getElementById('status-confirm').textContent = off ? 'Disable module' : 'Enable module';
                dlg.showModal();
            });
        });
    }
})();
</script>
<?= $this->endSection() ?>