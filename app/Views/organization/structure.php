<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper('form');

$companies   = $companies ?? [];
$companyId   = (int) ($companyId ?? 0);
$departments = $departments ?? [];
$positions   = $positions ?? [];
$selected    = $selected ?? [];
$used        = $used ?? [];
$copied      = ! empty($copied);
$canEdit     = ! empty($can['update']);

$flashSuccess = session()->getFlashdata('success');
$flashError   = session()->getFlashdata('error');

$btnPrim  = 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2';
$btnGhost = 'inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-semibold rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';
$fInput   = 'rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20';

$companyName = '';
foreach ($companies as $c) { if ((int) $c['id'] === $companyId) { $companyName = (string) $c['name']; } }
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
        <a href="<?= esc(base_url('organization'), 'attr') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
            <i class="fa-solid fa-arrow-left"></i> Back to Organization
        </a>
    </div>

    <section class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-base font-semibold text-slate-800">Company structure</h1>
            <p class="mt-0.5 text-xs text-slate-500">Tick the positions each department uses in this company. Only these combinations can be given to employees.</p>
        </div>
        <form method="get" action="<?= base_url('organization/structure') ?>" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="s-company" class="mb-1 block text-xs font-medium text-slate-700">Company</label>
                <select id="s-company" name="company" class="<?= $fInput ?>" onchange="this.form.submit()">
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $companyId ? 'selected' : '' ?>><?= esc($c['name']) ?><?= ! empty($c['short_name']) ? ' (' . esc($c['short_name']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($canEdit && count($companies) > 1): ?>
                <div>
                    <label for="s-copy" class="mb-1 block text-xs font-medium text-slate-700">Copy from another company</label>
                    <select id="s-copy" name="copy" class="<?= $fInput ?>" onchange="this.form.submit()">
                        <option value="">Choose a company…</option>
                        <?php foreach ($companies as $c): if ((int) $c['id'] === $companyId) { continue; } ?>
                            <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </form>
    </section>

    <?php if (! $companies): ?>
        <p class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-xs text-slate-500">There is no active company yet. Add one in Company Management first.</p>
    <?php elseif (! $departments || ! $positions): ?>
        <p class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-xs text-slate-500">Add at least one active department and one active position in Organization first.</p>
    <?php else: ?>

        <?php if ($copied): ?>
            <div role="status" class="flex items-start gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2.5 text-xs text-sky-900">
                <i class="fa-solid fa-copy mt-0.5 text-sky-600"></i>
                <span>The boxes were filled from the other company. <b>Nothing is saved yet</b>: review them, then choose Save structure.</span>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= base_url('organization/structure/save') ?>" class="space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= $companyId ?>">

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full text-left text-xs text-slate-600">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold text-slate-600">
                        <tr>
                            <th class="sticky left-0 z-10 min-w-[11rem] bg-slate-50 px-4 py-3">Department</th>
                            <?php foreach ($positions as $p): ?>
                                <th class="px-3 py-3 text-center">
                                    <span class="block whitespace-nowrap"><?= esc($p['name']) ?></span>
                                    <span class="block font-mono text-[10px] font-normal text-slate-400">level <?= (int) $p['level'] ?></span>
                                    <?php if ($canEdit): ?><button type="button" class="mt-1 text-[10px] font-semibold text-emerald-700 hover:underline" data-col="<?= (int) $p['id'] ?>">All</button><?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                            <?php if ($canEdit): ?><th class="px-3 py-3 text-center">Row</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($departments as $d): $did = (int) $d['id']; ?>
                            <tr class="hover:bg-slate-50/70" data-row="<?= $did ?>">
                                <td class="sticky left-0 bg-white px-4 py-3 font-semibold text-slate-800"><?= esc($d['name']) ?></td>
                                <?php foreach ($positions as $p): $pid = (int) $p['id']; $k = $did . ':' . $pid; $n = (int) ($used[$k] ?? 0); ?>
                                    <td class="px-3 py-3 text-center">
                                        <input type="checkbox" name="cell[<?= $did ?>][]" value="<?= $pid ?>" data-pos="<?= $pid ?>" data-locked="<?= $n > 0 ? '1' : '0' ?>"
                                               class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"
                                               <?= isset($selected[$k]) || $n > 0 ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>
                                               <?= $n > 0 ? 'title="' . esc($n . ' active employee(s) use this: it cannot be removed', 'attr') . '"' : '' ?>
                                               aria-label="<?= esc($d['name'] . ' - ' . $p['name'], 'attr') ?>">
                                    </td>
                                <?php endforeach; ?>
                                <?php if ($canEdit): ?>
                                    <td class="whitespace-nowrap px-3 py-3 text-center">
                                        <button type="button" class="text-[11px] font-semibold text-emerald-700 hover:underline" data-rowall="<?= $did ?>">All</button>
                                        <span class="text-slate-300">|</span>
                                        <button type="button" class="text-[11px] font-semibold text-slate-500 hover:underline" data-rowclear="<?= $did ?>">None</button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-[11px] text-slate-500">Boxes of combinations that active employees use are locked. Switched-off boxes are kept as inactive, not deleted.</p>
                <?php if ($canEdit): ?>
                    <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Save structure</button>
                <?php else: ?>
                    <span class="text-[11px] text-slate-500"><i class="fa-solid fa-lock"></i> View only. You do not have permission to change the structure.</span>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
(function () {
    function boxes(root) { return Array.prototype.slice.call(root.querySelectorAll('input[type="checkbox"]')); }
    function setAll(list, state) { list.forEach(function (c) { if (c.dataset.locked !== '1') { c.checked = state; } }); }

    /* locked boxes (still used by active employees) cannot be unticked */
    document.addEventListener('click', function (e) {
        var c = e.target;
        if (c && c.matches && c.matches('input[type="checkbox"][data-locked="1"]')) { e.preventDefault(); }
    });

    document.querySelectorAll('[data-rowall]').forEach(function (b) {
        b.addEventListener('click', function () { setAll(boxes(document.querySelector('tr[data-row="' + b.dataset.rowall + '"]')), true); });
    });
    document.querySelectorAll('[data-rowclear]').forEach(function (b) {
        b.addEventListener('click', function () { setAll(boxes(document.querySelector('tr[data-row="' + b.dataset.rowclear + '"]')), false); });
    });
    document.querySelectorAll('[data-col]').forEach(function (b) {
        b.addEventListener('click', function () {
            var list = Array.prototype.slice.call(document.querySelectorAll('input[data-pos="' + b.dataset.col + '"]'));
            var allOn = list.every(function (c) { return c.checked; });
            setAll(list, !allOn);
        });
    });
})();
</script>
<?= $this->endSection() ?>
