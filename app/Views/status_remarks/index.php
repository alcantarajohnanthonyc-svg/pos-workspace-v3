<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper(['form', 'security']);

$types   = $types ?? [];
$modules = $modules ?? [];
$names   = $names ?? [];
$colors  = $colors ?? [];
$can     = $can ?? [];   // deny by default: buttons show only when the controller sent true

$flashSuccess = session()->getFlashdata('success');
$flashError   = session()->getFlashdata('error');

$btnPrim  = 'inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2';
$btnGhost = 'inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600';
$btnDanger = 'inline-flex items-center justify-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-semibold text-rose-700 transition-colors hover:bg-rose-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500';
$fInput   = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-800 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 disabled:bg-slate-50';
$fLabel   = 'mb-1 block text-xs font-medium text-slate-700';

$dot = ['emerald' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'rose' => 'bg-rose-500', 'sky' => 'bg-sky-500', 'violet' => 'bg-violet-500', 'orange' => 'bg-orange-500', 'teal' => 'bg-teal-500', 'slate' => 'bg-slate-400'];
$colorSelect = static function (string $name, string $cur, string $in, bool $disabled) use ($colors): string {
    $h = '<select name="' . esc($name, 'attr') . '" class="' . $in . '" ' . ($disabled ? 'disabled' : '') . ' aria-label="Colour">';
    foreach ($colors as $c) {
        $h .= '<option value="' . $c . '"' . ($cur === $c ? ' selected' : '') . '>' . ucfirst($c) . '</option>';
    }

    return $h . '</select>';
};

// lists grouped by module
$byModule = [];
foreach ($types as $t) {
    $byModule[$t['module_slug']][] = $t;
}
$canSave = ! empty($can['update']) || ! empty($can['create']) || ! empty($can['delete']);
?>

<div class="space-y-5">

    <div class="flex items-start gap-3">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-xl text-white shadow-sm"><i class="fa-solid fa-tags"></i></span>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Status / Remarks</h1>
            <p class="text-xs text-slate-500">The status lists of the modules, for example the <b>Store Status</b> of Store Details, or the <b>Phone Status</b>, <b>Deployment Status</b> and <b>Receive Status</b> of Inventory.</p>
        </div>
    </div>

    <?php if ($flashSuccess): ?><div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs text-emerald-800" role="status"><?= esc($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs text-rose-800" role="alert"><?= esc($flashError) ?></div><?php endif; ?>

    <!-- new list -->
    <?php if (! empty($can['create'])): ?>
        <details class="rounded-xl border border-slate-200 bg-white" <?= $types ? '' : 'open' ?>>
            <summary class="cursor-pointer select-none px-5 py-3.5 text-sm font-bold text-slate-800"><i class="fa-solid fa-plus text-emerald-600"></i> Add a status list</summary>
            <form method="post" action="<?= base_url('status-remarks/type/create') ?>" class="space-y-3 border-t border-slate-100 px-5 py-4">
                <?= csrf_field() ?>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div>
                        <label for="n-module" class="<?= $fLabel ?>">Module <span class="text-red-500">*</span></label>
                        <select id="n-module" name="module_slug" required class="<?= $fInput ?>">
                            <option value="">Choose a module</option>
                            <?php foreach ($modules as $m): ?><option value="<?= esc($m['slug'], 'attr') ?>"><?= esc($m['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="n-name" class="<?= $fLabel ?>">List name <span class="text-red-500">*</span></label>
                        <input id="n-name" name="name" required maxlength="100" placeholder="e.g. Phone Status" class="<?= $fInput ?>">
                    </div>
                    <div>
                        <label for="n-desc" class="<?= $fLabel ?>">Description</label>
                        <input id="n-desc" name="description" maxlength="255" placeholder="optional" class="<?= $fInput ?>">
                    </div>
                </div>
                <div>
                    <label for="n-items" class="<?= $fLabel ?>">First statuses <span class="font-normal text-slate-400">(one per line, optional)</span></label>
                    <textarea id="n-items" name="first_items" rows="3" placeholder="Received&#10;Deployed&#10;Returned" class="<?= $fInput ?>"></textarea>
                </div>
                <div class="flex justify-end"><button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Create list</button></div>
            </form>
        </details>
    <?php endif; ?>

    <?php if (! $types): ?>
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <i class="fa-solid fa-tags mb-2 text-3xl text-slate-300"></i>
            <p class="text-sm font-semibold text-slate-600">No status list yet</p>
            <p class="text-xs text-slate-400">Create the first list for a module.</p>
        </div>
    <?php endif; ?>

    <!-- lists, grouped by module -->
    <?php foreach ($byModule as $slug => $list): ?>
        <section class="space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500"><?= esc($names[$slug] ?? ucwords(str_replace('_', ' ', (string) $slug))) ?> <span class="font-mono text-[10px] font-normal normal-case text-slate-400">· <?= esc($slug) ?></span></h2>

            <?php foreach ($list as $t): $tid = (int) $t['id']; ?>
                <article id="type-<?= $tid ?>" class="rounded-xl border border-slate-200 bg-white">
                    <form method="post" action="<?= base_url('status-remarks/type/' . $tid . '/save') ?>">
                        <?= csrf_field() ?>
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <input name="type_name" value="<?= esc($t['name'], 'attr') ?>" maxlength="100" <?= empty($can['update']) ? 'disabled' : '' ?> class="<?= $fInput ?> max-w-xs font-semibold" aria-label="List name">
                                <span class="hidden text-[11px] text-slate-400 sm:inline"><?= esc($t['description'] ?? '') ?> <span class="font-mono">· <?= esc($t['type_key']) ?></span></span>
                            </div>
                            <?php if ($canSave): ?>
                                <button type="submit" class="<?= $btnPrim ?>"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                            <?php endif; ?>
                        </div>

                        <div class="overflow-x-auto px-5 py-3">
                            <table class="w-full min-w-[560px] text-left text-xs">
                                <thead class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="py-2 pr-3">Status</th>
                                        <th class="px-3 py-2">Colour</th>
                                        <th class="w-24 px-3 py-2">Order</th>
                                        <th class="px-3 py-2 text-center">Active</th>
                                        <?php if (! empty($can['delete'])): ?><th class="px-3 py-2 text-center">Delete</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($t['items'] as $i): $iid = (int) $i['id']; ?>
                                        <tr class="align-middle">
                                            <td class="py-2 pr-3">
                                                <div class="flex items-center gap-2">
                                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full <?= $dot[$i['color']] ?? 'bg-slate-400' ?>"></span>
                                                    <input name="st[<?= $iid ?>][name]" value="<?= esc($i['name'], 'attr') ?>" maxlength="50" <?= empty($can['update']) ? 'disabled' : '' ?> class="<?= $fInput ?>" aria-label="Status name">
                                                </div>
                                            </td>
                                            <td class="px-3 py-2"><?= $colorSelect('st[' . $iid . '][color]', (string) $i['color'], $fInput, empty($can['update'])) ?></td>
                                            <td class="px-3 py-2"><input type="number" name="st[<?= $iid ?>][sort]" value="<?= (int) $i['sort_order'] ?>" min="0" max="999" <?= empty($can['update']) ? 'disabled' : '' ?> class="<?= $fInput ?>" aria-label="Order"></td>
                                            <td class="px-3 py-2 text-center"><input type="checkbox" name="st[<?= $iid ?>][active]" value="1" <?= (int) $i['is_active'] ? 'checked' : '' ?> <?= empty($can['update']) ? 'disabled' : '' ?> class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600" aria-label="Active"></td>
                                            <?php if (! empty($can['delete'])): ?>
                                                <td class="px-3 py-2 text-center"><input type="checkbox" name="del[]" value="<?= $iid ?>" class="h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-600" aria-label="Delete <?= esc($i['name'], 'attr') ?>"></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (! empty($can['create'])): ?>
                                        <?php for ($n = 0; $n < 3; $n++): ?>
                                            <tr class="bg-slate-50/60 align-middle">
                                                <td class="py-2 pr-3"><input name="new[<?= $n ?>][name]" maxlength="50" placeholder="<?= $n === 0 ? 'Add a status…' : 'Another status…' ?>" class="<?= $fInput ?>" aria-label="New status"></td>
                                                <td class="px-3 py-2"><?= $colorSelect('new[' . $n . '][color]', 'slate', $fInput, false) ?></td>
                                                <td class="px-3 py-2"><input type="number" name="new[<?= $n ?>][sort]" value="<?= 10 + $n ?>" min="0" max="999" class="<?= $fInput ?>" aria-label="Order"></td>
                                                <td class="px-3 py-2 text-center text-[11px] text-slate-400">new</td>
                                                <?php if (! empty($can['delete'])): ?><td></td><?php endif; ?>
                                            </tr>
                                        <?php endfor; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <?php if (! $t['items']): ?><p class="mt-2 text-[11px] text-slate-400">No status yet: this list accepts any text until you add one.</p><?php endif; ?>
                        </div>
                    </form>

                    <?php if (! empty($can['delete'])): ?>
                        <form method="post" action="<?= base_url('status-remarks/type/' . $tid . '/delete') ?>" onsubmit="return confirm('Delete the list &quot;<?= esc($t['name'], 'js') ?>&quot; and all its statuses?');" class="flex justify-end border-t border-slate-100 px-5 py-2.5">
                            <?= csrf_field() ?>
                            <button type="submit" class="<?= $btnDanger ?>"><i class="fa-solid fa-trash"></i> Delete this list</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>

    <div class="flex gap-2.5 rounded-xl border border-sky-100 bg-sky-50/70 p-4 text-xs leading-relaxed text-slate-600">
        <i class="fa-solid fa-circle-info mt-0.5 text-sky-500"></i>
        <p>Untick <b>Active</b> to stop offering a status (records that already have it keep it). A status that records still use cannot be deleted. Renaming a status also renames it on the records. The <b>Order</b> number sets the position in the dropdowns.</p>
    </div>
</div>
<?= $this->endSection() ?>