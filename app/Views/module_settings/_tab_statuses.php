<?php
/**
 * Module Settings > Statuses tab (the statuses a module can use, e.g. Store Details: OPEN, TEMPORARY CLOSED, PERMANENT CLOSED).
 * Include inside the tab panel of module_settings/configure:
 *
 *     <?= view('module_settings/_tab_statuses', ['moduleId' => $mid, 'canEdit' => ($canEdit || $canApproval)], ['saveData' => false]) ?>
 */
use App\Libraries\ApprovalRequests;
use App\Libraries\ModuleStatuses;
use App\Libraries\Rbac;

helper('form');

$moduleId = (int) ($moduleId ?? 0);
$module   = \Config\Database::connect()->table('rbac_modules')->where('id', $moduleId)->get()->getRowArray() ?: [];
$slug     = (string) ($module['slug'] ?? '');

if (! isset($canEdit)) {
    $uid     = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
    $isSuper = (int) (session()->get('user_role_id') ?? session()->get('role_id')) === 1;
    $canEdit = $isSuper || ApprovalRequests::isModuleAdmin($slug, $uid) || Rbac::can('module_setting', 'update');
}

$rows = ModuleStatuses::all($slug);
$dis  = $canEdit ? '' : 'disabled';
$in   = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-800 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 disabled:bg-slate-50';

$dot = [
    'emerald' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'rose' => 'bg-rose-500', 'sky' => 'bg-sky-500',
    'violet' => 'bg-violet-500', 'orange' => 'bg-orange-500', 'teal' => 'bg-teal-500', 'slate' => 'bg-slate-400',
];
$colorSelect = static function (string $name, string $cur, string $dis) use ($in): string {
    $h = '<select name="' . esc($name, 'attr') . '" class="' . $in . '" ' . $dis . ' aria-label="Colour">';
    foreach (\App\Libraries\ModuleStatuses::COLORS as $c) {
        $h .= '<option value="' . $c . '"' . ($cur === $c ? ' selected' : '') . '>' . ucfirst($c) . '</option>';
    }

    return $h . '</select>';
};
?>
<form method="post" action="<?= base_url('modules/' . $moduleId . '/statuses') ?>" class="space-y-5">
    <?= csrf_field() ?>

    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-4">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-800">Statuses</h3>
            <p class="mt-1 text-xs text-slate-500">The statuses this module can use. They fill the status dropdowns, are checked when importing, and set the colours of the cards and badges.</p>
        </div>
        <?php if ($canEdit): ?>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">
                <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
        <?php endif; ?>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto p-5">
            <table class="w-full min-w-[560px] text-left text-xs">
                <thead class="border-b border-slate-200 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-2 pr-3">Status name</th>
                        <th class="px-3 py-2">Colour</th>
                        <th class="w-24 px-3 py-2">Order</th>
                        <th class="px-3 py-2 text-center">Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r): ?>
                        <tr class="align-middle">
                            <td class="py-2 pr-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full <?= $dot[$r['color']] ?? 'bg-slate-400' ?>"></span>
                                    <input name="st[<?= (int) $r['id'] ?>][name]" value="<?= esc($r['name'], 'attr') ?>" maxlength="50" class="<?= $in ?>" <?= $dis ?> aria-label="Status name">
                                </div>
                            </td>
                            <td class="px-3 py-2"><?= $colorSelect('st[' . (int) $r['id'] . '][color]', $r['color'], $dis) ?></td>
                            <td class="px-3 py-2"><input type="number" name="st[<?= (int) $r['id'] ?>][sort]" value="<?= (int) $r['sort_order'] ?>" min="0" max="999" class="<?= $in ?>" <?= $dis ?> aria-label="Order"></td>
                            <td class="px-3 py-2 text-center"><input type="checkbox" name="st[<?= (int) $r['id'] ?>][active]" value="1" <?= $r['is_active'] ? 'checked' : '' ?> <?= $dis ?> class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600" aria-label="Active"></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($canEdit): ?>
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <tr class="align-middle bg-slate-50/60">
                                <td class="py-2 pr-3"><input name="new[<?= $i ?>][name]" maxlength="50" placeholder="<?= $i === 0 ? 'Add a new status…' : 'Another new status…' ?>" class="<?= $in ?>" aria-label="New status name"></td>
                                <td class="px-3 py-2"><?= $colorSelect('new[' . $i . '][color]', 'slate', '') ?></td>
                                <td class="px-3 py-2"><input type="number" name="new[<?= $i ?>][sort]" value="<?= 10 + $i ?>" min="0" max="999" class="<?= $in ?>" aria-label="Order"></td>
                                <td class="px-3 py-2 text-center text-[11px] text-slate-400">new</td>
                            </tr>
                        <?php endfor; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (! $rows): ?>
                <p class="mt-3 rounded-lg border border-dashed border-slate-200 bg-slate-50 p-3 text-xs text-slate-500">No status is defined yet, so this module accepts any text. Add the statuses below and save.</p>
            <?php endif; ?>
        </div>
    </section>

    <div class="flex gap-2.5 rounded-xl border border-sky-100 bg-sky-50/70 p-4 text-xs leading-relaxed text-slate-600">
        <i class="fa-solid fa-circle-info mt-0.5 text-sky-500"></i>
        <p>Untick <b>Active</b> to stop offering a status (stores that already have it keep it). Renaming a status also renames it on the stores that use it. The Order number decides the position in the dropdowns.</p>
    </div>
    <?php if (! $canEdit): ?>
        <p class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-500">You can view the statuses, but only a module administrator can change them.</p>
    <?php endif; ?>
</form>
