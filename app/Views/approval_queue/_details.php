<?php
/**
 * Request details. Used by the side drawer (mode = drawer, HTML fragment) and by the full page (mode = page).
 * Needs: $req, $steps (by step_role), $diff, $canAct, $can, $mode, $logs
 */
helper('approval');

$req    = $req ?? [];
$steps  = $steps ?? [];
$diff   = $diff ?? ['mode' => 'update', 'rows' => [], 'changed' => 0];
$canAct = ! empty($canAct);
$can    = $can ?? [];
$mode   = $mode ?? 'drawer';
$logs   = $logs ?? [];

$id        = (int) ($req['id'] ?? 0);
$status    = (string) ($req['status'] ?? 'pending');
$priority  = (string) ($req['priority'] ?? 'medium');
$slug      = (string) ($req['module_slug'] ?? '');
$modName   = aq_module_name($slug, $req['module_name'] ?? null);
$person    = aq_person_name($req);
[$d, $t]   = aq_when($req['created_at'] ?? null);

$statusText = $status === 'pending' ? 'Pending Approval' : aq_status_label($status);

$stepPill = static function (string $role, ?array $s) use ($steps, $status): array {
    $assigned = $s && ! empty($s['approver_id']);
    $st       = (string) ($s['status'] ?? 'waiting');
    if ($st === 'approved') { return ['Approved', 'bg-emerald-50 text-emerald-700 border-emerald-200']; }
    if ($st === 'rejected') { return ['Rejected', 'bg-rose-50 text-rose-700 border-rose-200']; }
    if ($st === 'skipped')  { return ['Skipped', 'bg-slate-100 text-slate-500 border-slate-200']; }
    if ($st === 'pending')  { return ['Pending', 'bg-amber-50 text-amber-700 border-amber-200']; }
    if (! $assigned)        { return ['Not assigned', 'bg-slate-50 text-slate-400 border-slate-200']; }
    if ($status !== 'pending') { return ['Not reached', 'bg-slate-50 text-slate-400 border-slate-200']; }
    if ($role === 'backup' && (($steps['current']['status'] ?? '') === 'pending')) {
        return ['Standby', 'bg-sky-50 text-sky-700 border-sky-200'];
    }

    return ['Not yet reached', 'bg-slate-50 text-slate-500 border-slate-200'];
};

$flow = [['current', 'Current Approver'], ['backup', 'Backup Approver'], ['final', 'Final Approver']];

$changedRows = array_values(array_filter($diff['rows'], static fn ($r) => $r['state'] !== 'same'));
$sameRows    = array_values(array_filter($diff['rows'], static fn ($r) => $r['state'] === 'same'));

$val = static function (string $v, string $cls): string {
    return $v === ''
        ? '<span class="italic text-slate-400">empty</span>'
        : '<span class="' . $cls . ' break-words">' . esc($v) . '</span>';
};
?>
<div data-aq-panel data-id="<?= $id ?>" class="space-y-5 <?= $mode === 'drawer' ? 'p-5' : '' ?>">

    <!-- status + priority -->
    <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-semibold <?= aq_status_class($status) ?>">
            <i class="fa-regular fa-clock"></i> <?= esc($statusText) ?>
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-semibold <?= aq_priority_class($priority) ?>">
            <i class="fa-solid fa-circle-exclamation"></i> <?= esc(ucfirst($priority)) ?> Priority
        </span>
    </div>

    <!-- module + action -->
    <div class="flex items-center gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white <?= aq_module_bg($slug) ?>">
            <i class="<?= esc(aq_module_icon($req['module_icon'] ?? null), 'attr') ?>"></i>
        </span>
        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-slate-800"><?= esc($modName) ?></p>
            <p class="truncate text-xs text-slate-500"><?= esc($req['action_label'] ?? '') ?></p>
        </div>
    </div>

    <!-- facts -->
    <dl class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white text-xs">
        <div class="flex items-center justify-between gap-4 px-3.5 py-2.5">
            <dt class="text-slate-500">Requester</dt>
            <dd class="flex min-w-0 items-center gap-2 text-right">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white"><?= esc(aq_initials($person)) ?></span>
                <span class="min-w-0">
                    <span class="block truncate font-semibold text-slate-800"><?= esc($person) ?></span>
                    <?php if (! empty($req['requester_title'])): ?><span class="block truncate text-[11px] text-slate-500"><?= esc($req['requester_title']) ?></span><?php endif; ?>
                </span>
            </dd>
        </div>
        <div class="flex justify-between gap-4 px-3.5 py-2.5"><dt class="text-slate-500">Requested On</dt><dd class="font-medium text-slate-800"><?= esc($d . ($t !== '' ? ' ' . $t : '')) ?></dd></div>
        <div class="flex justify-between gap-4 px-3.5 py-2.5"><dt class="text-slate-500">Module</dt><dd class="font-medium text-slate-800"><?= esc($modName) ?></dd></div>
        <div class="flex justify-between gap-4 px-3.5 py-2.5"><dt class="text-slate-500">Action</dt><dd class="text-right font-medium text-slate-800"><?= esc($req['action_label'] ?? '') ?></dd></div>
        <?php if (! empty($req['scope_text'])): ?>
            <div class="flex justify-between gap-4 px-3.5 py-2.5"><dt class="text-slate-500">Scope</dt><dd class="text-right font-medium text-slate-800"><?= esc($req['scope_text']) ?></dd></div>
        <?php endif; ?>
        <div class="flex justify-between gap-4 px-3.5 py-2.5"><dt class="text-slate-500">Reference No.</dt><dd class="font-mono font-medium text-slate-800"><?= esc($req['reference_no'] ?? '') ?></dd></div>
    </dl>

    <!-- summary -->
    <?php if (! empty($req['summary']) || ! empty($req['target_label'])): ?>
        <section>
            <h3 class="mb-2 text-sm font-bold text-slate-800">Request Details</h3>
            <div class="rounded-xl border border-sky-100 bg-sky-50/60 p-3.5 text-xs leading-relaxed text-slate-700">
                <?php if (! empty($req['summary'])): ?><p><?= esc($req['summary']) ?></p><?php endif; ?>
                <?php if (! empty($req['target_label'])): ?>
                    <p class="mt-1.5"><span class="text-slate-500">Record:</span> <span class="font-semibold"><?= esc($req['target_label']) ?></span></p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- BEFORE / AFTER -->
    <section>
        <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-800">
                <?= $diff['mode'] === 'create' ? 'New record' : ($diff['mode'] === 'delete' ? 'Record to delete' : 'What changed') ?>
            </h3>
            <?php if ($diff['mode'] === 'update' && $diff['rows']): ?>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                    <?= (int) $diff['changed'] ?> of <?= count($diff['rows']) ?> fields changed
                </span>
            <?php endif; ?>
        </div>

        <?php if (! $diff['rows']): ?>
            <p class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-500">
                No field-level details were attached to this request.
            </p>
        <?php elseif ($diff['mode'] === 'update'): ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-3 py-2">Field</th><th class="px-3 py-2">Before</th><th class="px-3 py-2">After</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($changedRows as $row): ?>
                            <tr class="align-top">
                                <td class="px-3 py-2 font-semibold text-slate-700"><?= esc($row['label']) ?></td>
                                <td class="bg-rose-50/70 px-3 py-2"><?= $val($row['before'], 'text-rose-700 line-through decoration-rose-300') ?></td>
                                <td class="bg-emerald-50/70 px-3 py-2"><?= $val($row['after'], 'font-semibold text-emerald-800') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (! $changedRows): ?>
                            <tr><td colspan="3" class="px-3 py-3 text-center text-slate-500">No field was changed.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($sameRows): ?>
                <details class="mt-2 rounded-xl border border-slate-200 bg-white text-xs">
                    <summary class="cursor-pointer select-none px-3 py-2 font-medium text-slate-600 hover:text-slate-800">
                        Show <?= count($sameRows) ?> unchanged field<?= count($sameRows) === 1 ? '' : 's' ?>
                    </summary>
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($sameRows as $row): ?>
                                <tr class="align-top">
                                    <td class="px-3 py-2 font-medium text-slate-600"><?= esc($row['label']) ?></td>
                                    <td class="px-3 py-2 text-slate-500"><?= $val($row['after'], 'text-slate-600') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            <?php endif; ?>
        <?php else: ?>
            <?php $isCreate = $diff['mode'] === 'create'; ?>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr><th class="px-3 py-2">Field</th><th class="px-3 py-2"><?= $isCreate ? 'Value to add' : 'Value to remove' ?></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($diff['rows'] as $row): ?>
                            <tr class="align-top">
                                <td class="px-3 py-2 font-semibold text-slate-700"><?= esc($row['label']) ?></td>
                                <?php if ($isCreate): ?>
                                    <td class="bg-emerald-50/70 px-3 py-2"><?= $val($row['after'], 'font-semibold text-emerald-800') ?></td>
                                <?php else: ?>
                                    <td class="bg-rose-50/70 px-3 py-2"><?= $val($row['before'], 'text-rose-700 line-through decoration-rose-300') ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- approval flow -->
    <section>
        <h3 class="mb-2 text-sm font-bold text-slate-800">Approval Flow</h3>
        <ol class="space-y-2.5">
            <?php foreach ($flow as $i => [$role, $label]): ?>
                <?php
                $s        = $steps[$role] ?? null;
                [$pt, $pc] = $stepPill($role, $s);
                $who      = $s ? trim((string) ($s['approver_name'] ?? '')) : '';
                if ($who === '' && $s && ! empty($s['approver_email'])) { $who = (string) $s['approver_email']; }
                $stt      = (string) ($s['status'] ?? 'waiting');
                $circle   = $stt === 'approved' ? 'bg-emerald-600 text-white' : ($stt === 'rejected' ? 'bg-rose-600 text-white' : ($stt === 'pending' ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500'));
                $acted    = '';
                if ($s && in_array($stt, ['approved', 'rejected'], true) && ! empty($s['acted_at'])) {
                    $actor = trim((string) ($s['actor_name'] ?? ''));
                    $acted = ($actor !== '' ? 'by ' . $actor . ' · ' : '') . date('M j, g:i A', strtotime((string) $s['acted_at']));
                }
                ?>
                <li class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold <?= $circle ?>">
                        <?php if ($stt === 'approved'): ?><i class="fa-solid fa-check"></i><?php elseif ($stt === 'rejected'): ?><i class="fa-solid fa-xmark"></i><?php else: ?><?= $i + 1 ?><?php endif; ?>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-slate-800"><?= esc($label) ?></p>
                        <p class="truncate text-[11px] text-slate-500"><?= $who !== '' ? esc($who) : 'Not assigned' ?></p>
                        <?php if ($acted !== ''): ?><p class="text-[10px] text-slate-400"><?= esc($acted) ?></p><?php endif; ?>
                        <?php if ($s && ! empty($s['note']) && in_array($stt, ['approved', 'rejected', 'skipped'], true)): ?>
                            <p class="mt-0.5 text-[11px] italic text-slate-500">“<?= esc($s['note']) ?>”</p>
                        <?php endif; ?>
                    </div>
                    <span class="shrink-0 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold <?= $pc ?>"><?= esc($pt) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <!-- decision (already decided) -->
    <?php if ($status !== 'pending' && (! empty($req['decided_at']) || ! empty($req['decision_note']))): ?>
        <section class="rounded-xl border p-3.5 text-xs <?= $status === 'rejected' ? 'border-rose-200 bg-rose-50/60 text-rose-800' : 'border-emerald-200 bg-emerald-50/60 text-emerald-800' ?>">
            <p class="font-semibold"><?= esc(aq_status_label($status)) ?><?= ! empty($req['decided_at']) ? ' on ' . esc(date('M j, Y g:i A', strtotime((string) $req['decided_at']))) : '' ?></p>
            <?php if (! empty($req['decision_note'])): ?><p class="mt-1">“<?= esc($req['decision_note']) ?>”</p><?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- actions -->
    <section class="space-y-2.5">
        <?php if ($canAct && (! empty($can['approve']) || ! empty($can['reject']))): ?>
            <h3 class="text-sm font-bold text-slate-800">Actions</h3>
            <div>
                <label for="aq-note-<?= $id ?>" class="mb-1 block text-xs font-medium text-slate-700">Note <span class="font-normal text-slate-400">(required when rejecting)</span></label>
                <textarea id="aq-note-<?= $id ?>" data-aq-note rows="2" maxlength="500" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-800 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20" placeholder="Add a note for the requester…"></textarea>
                <p data-aq-error class="mt-1 hidden text-xs text-rose-600" role="alert"></p>
            </div>
            <?php if (! empty($can['approve'])): ?>
                <button type="button" data-aq-decide="approve" data-id="<?= $id ?>" class="flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 disabled:opacity-60">
                    <i class="fa-solid fa-circle-check"></i> Approve
                </button>
            <?php endif; ?>
            <?php if (! empty($can['reject'])): ?>
                <button type="button" data-aq-decide="reject" data-id="<?= $id ?>" class="flex w-full items-center justify-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 transition-colors hover:bg-rose-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 disabled:opacity-60">
                    <i class="fa-solid fa-circle-xmark"></i> Reject
                </button>
            <?php endif; ?>
        <?php elseif ($status === 'pending'): ?>
            <p class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-500">This request is waiting for its approver. You can view it, but you cannot approve or reject it.</p>
        <?php endif; ?>

        <?php if ($mode === 'drawer'): ?>
            <a href="<?= base_url('approval-queue/show/' . $id) ?>" class="flex w-full items-center justify-center gap-2 rounded-lg border border-sky-300 bg-white px-4 py-2.5 text-sm font-semibold text-sky-700 transition-colors hover:bg-sky-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                <i class="fa-regular fa-eye"></i> View Full Details
            </a>
        <?php endif; ?>
    </section>

    <!-- activity log (full page only) -->
    <?php if ($mode === 'page'): ?>
        <section>
            <h3 class="mb-2 text-sm font-bold text-slate-800">Activity</h3>
            <?php if (! $logs): ?>
                <p class="text-xs text-slate-500">No activity yet.</p>
            <?php else: ?>
                <ul class="space-y-2">
                    <?php
                    $evt = ['submitted' => 'Submitted', 'approved_step' => 'Approved (waiting for final approver)', 'approved' => 'Approved', 'rejected' => 'Rejected'];
                    foreach ($logs as $l):
                        $who = trim((string) ($l['actor_name'] ?? ''));
                        if ($who === '') { $who = (string) ($l['email_workspace'] ?? 'System'); }
                        ?>
                        <li class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                            <p class="font-semibold text-slate-800"><?= esc($evt[$l['event']] ?? ucfirst((string) $l['event'])) ?> <span class="font-normal text-slate-500">· <?= esc($who) ?></span></p>
                            <p class="text-[10px] text-slate-400"><?= esc(date('M j, Y g:i A', strtotime((string) $l['created_at']))) ?></p>
                            <?php if (! empty($l['note'])): ?><p class="mt-0.5 italic text-slate-500">“<?= esc($l['note']) ?>”</p><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
