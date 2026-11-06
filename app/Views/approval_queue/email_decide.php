<?php
helper('form');
$state = $state ?? 'error';
$req   = $req ?? [];
$diff  = $diff ?? ['mode' => 'info', 'rows' => []];
$pre   = $pre ?? '';
$rows  = $diff['mode'] === 'update' ? array_values(array_filter($diff['rows'], static fn ($r) => $r['state'] !== 'same')) : $diff['rows'];
$val   = static fn (string $v, string $cls): string => $v === '' ? '<span class="italic text-slate-400">empty</span>' : '<span class="' . $cls . ' break-words">' . esc($v) . '</span>';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Approval | POS Workspace V3</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
<main class="mx-auto max-w-xl p-4 sm:p-8">
    <p class="mb-3 text-xs font-semibold text-slate-500">POS Workspace <span class="text-emerald-600">V3</span></p>

    <?php if ($state === 'error'): ?>
        <div class="rounded-2xl border border-rose-200 bg-white p-6 text-center shadow-sm">
            <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-xl text-rose-600">!</span>
            <h1 class="text-base font-bold text-slate-800">Cannot continue</h1>
            <p class="mt-2 text-sm text-slate-600"><?= esc($message ?? 'This link is not valid.') ?></p>
            <a href="<?= esc(base_url('approval-queue'), 'attr') ?>" class="mt-4 inline-block rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Open POS Workspace</a>
        </div>

    <?php elseif ($state === 'done'): ?>
        <?php $ok = ($decision ?? '') === 'approve'; ?>
        <div class="rounded-2xl border <?= $ok ? 'border-emerald-200' : 'border-rose-200' ?> bg-white p-6 text-center shadow-sm">
            <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full text-xl <?= $ok ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600' ?>"><?= $ok ? '✓' : '✕' ?></span>
            <h1 class="text-base font-bold text-slate-800"><?= esc($message ?? 'Saved.') ?></h1>
            <p class="mt-2 text-sm text-slate-600">Reference <span class="font-mono font-semibold"><?= esc($req['reference_no'] ?? '') ?></span>. The requester was notified. You can close this page.</p>
        </div>

    <?php else: ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-lg font-bold text-slate-800">Approval needed</h1>
            <p class="text-xs text-slate-500"><?= esc($req['action_label'] ?? '') ?></p>

            <dl class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 text-xs">
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-slate-500">Module</dt><dd class="font-semibold"><?= esc($module) ?></dd></div>
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-slate-500">Requested by</dt><dd class="font-semibold"><?= esc($requester) ?></dd></div>
                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-slate-500">Reference</dt><dd class="font-mono font-semibold"><?= esc($req['reference_no'] ?? '') ?></dd></div>
                <?php if (! empty($req['scope_text'])): ?><div class="flex justify-between gap-4 px-3 py-2"><dt class="text-slate-500">Scope</dt><dd class="text-right font-semibold"><?= esc($req['scope_text']) ?></dd></div><?php endif; ?>
                <?php if (! empty($req['target_label'])): ?><div class="flex justify-between gap-4 px-3 py-2"><dt class="text-slate-500">Record</dt><dd class="text-right font-semibold"><?= esc($req['target_label']) ?></dd></div><?php endif; ?>
            </dl>

            <?php if ($rows): ?>
                <h2 class="mb-1 mt-4 text-sm font-bold"><?= $diff['mode'] === 'update' ? 'What will change' : ($diff['mode'] === 'delete' ? 'Record to delete' : ($diff['mode'] === 'create' ? 'New record' : 'Request details')) ?></h2>
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] uppercase tracking-wide text-slate-500">
                            <tr><th class="px-3 py-2">Field</th><?php if ($diff['mode'] === 'update'): ?><th class="px-3 py-2">Before</th><th class="px-3 py-2">After</th><?php else: ?><th class="px-3 py-2">Value</th><?php endif; ?></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($rows as $r): ?>
                                <tr class="align-top">
                                    <td class="px-3 py-2 font-semibold text-slate-700"><?= esc($r['label']) ?></td>
                                    <?php if ($diff['mode'] === 'update'): ?>
                                        <td class="bg-rose-50/70 px-3 py-2"><?= $val($r['before'], 'text-rose-700 line-through') ?></td>
                                        <td class="bg-emerald-50/70 px-3 py-2"><?= $val($r['after'], 'font-semibold text-emerald-800') ?></td>
                                    <?php elseif ($diff['mode'] === 'delete'): ?>
                                        <td class="bg-rose-50/70 px-3 py-2"><?= $val($r['before'], 'text-rose-700') ?></td>
                                    <?php else: ?>
                                        <td class="bg-emerald-50/70 px-3 py-2"><?= $val($r['after'], 'font-semibold text-emerald-800') ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (! empty($hasLines)): ?>
                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">This request has many lines, so it cannot be decided from the email. Please open POS Workspace and review the lines first.</p>
                <a href="<?= esc(base_url('approval-queue/show/' . (int) $req['id']), 'attr') ?>" class="mt-3 inline-block rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Open in POS Workspace</a>
            <?php else: ?>
                <form method="post" action="<?= esc(base_url('approval-email/' . $token . '/decide'), 'attr') ?>" id="aq-mail-form" class="mt-4 space-y-3">
                    <?= csrf_field() ?>
                    <?php if (! empty($error)): ?><p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700" role="alert"><?= esc($error) ?></p><?php endif; ?>
                    <p id="aq-mail-err" class="hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700" role="alert">Please write the reason for rejecting this request.</p>
                    <div>
                        <label for="note" class="mb-1 block text-xs font-medium text-slate-700">Note <span class="font-normal text-slate-400">(required when rejecting)</span></label>
                        <textarea id="note" name="note" rows="2" maxlength="500" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-[13px] focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20" placeholder="Add a note for the requester…"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="submit" name="decision" value="approve" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm bg-emerald-600 hover:bg-emerald-700 <?= $pre === 'approve' ? 'ring-2 ring-emerald-300 ring-offset-2' : '' ?>">Confirm approve</button>
                        <button type="submit" name="decision" value="reject" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm bg-rose-600 hover:bg-rose-700 <?= $pre === 'reject' ? 'ring-2 ring-rose-300 ring-offset-2' : '' ?>">Confirm reject</button>
                    </div>
                    <p class="text-center text-[11px] text-slate-400">Nothing is saved until you press a button above.</p>
                </form>
                <script>
                (function () {
                    var f = document.getElementById('aq-mail-form'), n = document.getElementById('note'), e = document.getElementById('aq-mail-err');
                    <?= $pre === 'reject' ? "n.focus();" : '' ?>
                    f.addEventListener('submit', function (ev) {
                        var s = ev.submitter;
                        if (s && s.value === 'reject' && n.value.trim() === '') { ev.preventDefault(); e.classList.remove('hidden'); n.focus(); return; }
                        f.querySelectorAll('button').forEach(function (b) { b.style.pointerEvents = 'none'; b.style.opacity = '.6'; });
                    });
                })();
                </script>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>