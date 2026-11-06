<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$rows    = $rows ?? [];
$filters = ($filters ?? []) + ['q' => '', 'user' => 0, 'module' => '', 'action' => '', 'from' => '', 'to' => ''];
$page    = $page ?? 1;
$pages   = $pages ?? 1;
$total   = $total ?? 0;
$perPage = $perPage ?? 30;

$fInput = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-800 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20';
$fLabel = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500';
$qs = static function (array $over = []) use ($filters): string {
    $p = array_filter(array_merge($filters, $over), static fn ($v) => $v !== '' && $v !== 0 && $v !== null);

    return http_build_query($p);
};
$tone = static function (string $a): string {
    $k = strtolower($a);
    if (preg_match('/delete|reject|fail|remove/', $k)) { return 'bg-rose-50 text-rose-700 border-rose-200'; }
    if (preg_match('/create|add|approve|login|import/', $k)) { return 'bg-emerald-50 text-emerald-700 border-emerald-200'; }
    if (preg_match('/update|edit|save|bulk|request/', $k)) { return 'bg-amber-50 text-amber-700 border-amber-200'; }

    return 'bg-slate-100 text-slate-600 border-slate-200';
};
?>
<div class="space-y-4">
    <div class="flex items-start gap-3">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-xl text-white shadow-sm"><i class="fa-solid fa-clock-rotate-left"></i></span>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Activity Log</h1>
            <p class="text-xs text-slate-500">Who did what, in which module, when and from where.</p>
        </div>
    </div>

    <?php if (empty($tableReady)): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">The table <b>usr_activity_logs</b> does not exist yet. Run <b>usr_activity_logs.sql</b> in phpMyAdmin.</div>
    <?php endif; ?>

    <form method="get" action="<?= base_url('activity-logs') ?>" class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2"><label for="l-q" class="<?= $fLabel ?>">Search</label><input id="l-q" type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="description, url, record id…" class="<?= $fInput ?>"></div>
        <div>
            <label for="l-user" class="<?= $fLabel ?>">User</label>
            <select id="l-user" name="user" class="<?= $fInput ?>"><option value="">All users</option>
                <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $filters['user'] === (int) $u['id'] ? 'selected' : '' ?>><?= esc(trim((string) $u['name']) !== '' ? $u['name'] : $u['email_workspace']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="l-mod" class="<?= $fLabel ?>">Module</label>
            <select id="l-mod" name="module" class="<?= $fInput ?>"><option value="">All modules</option>
                <?php foreach ($modules as $m): ?><option value="<?= esc($m, 'attr') ?>" <?= $filters['module'] === $m ? 'selected' : '' ?>><?= esc($m) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="l-act" class="<?= $fLabel ?>">Action</label>
            <select id="l-act" name="action" class="<?= $fInput ?>"><option value="">All actions</option>
                <?php foreach ($actions as $a): ?><option value="<?= esc($a, 'attr') ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= esc($a) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div><label for="l-from" class="<?= $fLabel ?>">From</label><input id="l-from" type="date" name="from" value="<?= esc($filters['from'], 'attr') ?>" class="<?= $fInput ?>"></div>
            <div><label for="l-to" class="<?= $fLabel ?>">To</label><input id="l-to" type="date" name="to" value="<?= esc($filters['to'], 'attr') ?>" class="<?= $fInput ?>"></div>
        </div>
        <div class="flex items-end gap-2 lg:col-span-6">
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
            <a href="<?= base_url('activity-logs') ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-xs">
                <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr><th class="px-3 py-3">When</th><th class="px-3 py-3">User</th><th class="px-3 py-3">Module</th><th class="px-3 py-3">Action</th><th class="px-3 py-3">What happened</th><th class="px-3 py-3">From</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (! $rows): ?><tr><td colspan="6" class="px-4 py-12 text-center text-slate-400">No activity found.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $r): $who = trim((string) $r['user_name']) !== '' ? $r['user_name'] : ($r['email_workspace'] ?: ($r['account_id'] ? 'Account #' . $r['account_id'] : 'Not logged in')); ?>
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-3 py-2.5 text-slate-600"><?= esc(date('M j, Y', strtotime((string) $r['created_at']))) ?><br><span class="text-[11px] text-slate-400"><?= esc(date('g:i:s A', strtotime((string) $r['created_at']))) ?></span></td>
                            <td class="px-3 py-2.5 font-semibold text-slate-800"><?= esc($who) ?></td>
                            <td class="px-3 py-2.5 text-slate-600"><?= esc($r['module_slug'] ?? '') ?></td>
                            <td class="px-3 py-2.5"><span class="inline-block rounded-full border px-2 py-0.5 text-[10px] font-semibold <?= $tone((string) $r['action']) ?>"><?= esc($r['action']) ?></span></td>
                            <td class="px-3 py-2.5 text-slate-700">
                                <?= esc($r['description'] ?? '') ?>
                                <?php if (! empty($r['record_id'])): ?><span class="ml-1 font-mono text-[11px] text-slate-400">#<?= esc($r['record_id']) ?></span><?php endif; ?>
                                <?php if (! empty($r['details'])): ?>
                                    <details class="mt-1"><summary class="cursor-pointer text-[11px] font-semibold text-sky-700">Details</summary>
                                        <pre class="mt-1 max-h-60 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-2 text-[11px] text-slate-600"><?= esc((string) (json_encode(json_decode((string) $r['details'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $r['details'])) ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2.5 text-[11px] text-slate-500"><?= esc($r['ip_address'] ?? '') ?><br><span class="text-slate-400"><?= esc($r['method'] ?? '') ?> <?= esc($r['url'] ?? '') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
            <span><?= $total ? 'Showing ' . number_format(($page - 1) * $perPage + 1) . ' to ' . number_format(min($page * $perPage, $total)) . ' of ' . number_format($total) : '0 entries' ?></span>
            <?php if ($pages > 1): ?>
                <nav class="flex items-center gap-1" aria-label="Pagination">
                    <?php if ($page > 1): ?><a class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50" href="?<?= esc($qs(['page' => $page - 1])) ?>">Prev</a><?php endif; ?>
                    <span class="px-2">Page <?= $page ?> / <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50" href="?<?= esc($qs(['page' => $page + 1])) ?>">Next</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>