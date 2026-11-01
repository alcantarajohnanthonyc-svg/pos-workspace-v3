<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper(['form', 'security', 'approval']);

$rows    = $rows ?? [];
$filters = ($filters ?? []) + ['module' => '', 'action' => '', 'status' => 'pending', 'q' => ''];
$stats   = ($stats ?? []) + ['pending' => 0, 'approved_today' => 0, 'rejected_today' => 0, 'modules' => 0];
$modules = $modules ?? [];
$actions = $actions ?? [];
$can     = $can ?? [];   // deny by default: buttons show only when the controller sent true
$page    = $page ?? 1;
$pages   = $pages ?? 1;
$total   = $total ?? 0;
$perPage = $perPage ?? 15;

$flashSuccess = session()->getFlashdata('success');
$flashError   = session()->getFlashdata('error');

$fInput = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] text-slate-800 focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20';
$fLabel = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500';

// keep the filters in the pagination links
$qs = static function (array $over = []) use ($filters): string {
    $p = array_merge($filters, $over);
    foreach ($p as $k => $v) {
        if ($v === '' || $v === null || ($k === 'page' && (int) $v <= 1)) {
            unset($p[$k]);
        }
    }

    return http_build_query($p);
};

$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = min($total, $page * $perPage);

$anyApprovable = false;
foreach ($rows as $r) {
    if (! empty($r['can_act']) && ! empty($can['approve'])) { $anyApprovable = true; break; }
}
?>

<div class="space-y-5">

    <!-- header -->
    <div class="flex items-start gap-3">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-xl text-white shadow-sm"><i class="fa-solid fa-shield-halved"></i></span>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Approval Queue</h1>
            <p class="text-xs text-slate-500">Review and approve pending requests based on your assigned modules and role.</p>
        </div>
    </div>

    <?php if ($flashSuccess): ?><div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs text-emerald-800" role="status"><?= esc($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs text-rose-800" role="alert"><?= esc($flashError) ?></div><?php endif; ?>

    <!-- summary cards -->
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <?php
        $cards = [
            ['Pending Requests', $stats['pending'],        'fa-regular fa-clock',      'bg-amber-100 text-amber-600',   'pending'],
            ['Approved Today',   $stats['approved_today'], 'fa-solid fa-circle-check', 'bg-emerald-100 text-emerald-600', 'approved'],
            ['Rejected Today',   $stats['rejected_today'], 'fa-solid fa-circle-xmark', 'bg-rose-100 text-rose-600',     'rejected'],
            ['Assigned Modules', $stats['modules'],        'fa-solid fa-users',        'bg-violet-100 text-violet-600', ''],
        ];
        foreach ($cards as [$label, $num, $icon, $tone, $link]):
            $tag   = $link !== '' ? 'a' : 'div';
            $href  = $link !== '' ? ' href="' . esc(base_url('approval-queue') . '?' . http_build_query(['status' => $link]), 'attr') . '"' : '';
            ?>
            <<?= $tag ?><?= $href ?> class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 <?= $link !== '' ? 'transition-colors hover:bg-slate-50' : '' ?>">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-lg <?= $tone ?>"><i class="<?= $icon ?>"></i></span>
                <span>
                    <span class="block text-xs text-slate-500"><?= esc($label) ?></span>
                    <span class="block text-2xl font-bold leading-tight text-slate-800"><?= number_format((int) $num) ?></span>
                </span>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </div>

    <!-- filters (GET: no loading overlay, no popup) -->
    <form method="get" action="<?= base_url('approval-queue') ?>" class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1.4fr]">
        <div>
            <label for="f-module" class="<?= $fLabel ?>">Module</label>
            <select id="f-module" name="module" class="<?= $fInput ?>" onchange="this.form.submit()">
                <option value="">All Modules</option>
                <?php foreach ($modules as $m): ?>
                    <option value="<?= esc($m['slug'], 'attr') ?>" <?= $filters['module'] === $m['slug'] ? 'selected' : '' ?>><?= esc(aq_module_name((string) $m['slug'], $m['name'] ?? null)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="f-action" class="<?= $fLabel ?>">Action</label>
            <select id="f-action" name="action" class="<?= $fInput ?>" onchange="this.form.submit()">
                <option value="">All Actions</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= esc($a, 'attr') ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= esc(ucfirst((string) $a)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="f-status" class="<?= $fLabel ?>">Status</label>
            <select id="f-status" name="status" class="<?= $fInput ?>" onchange="this.form.submit()">
                <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All statuses'] as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="f-q" class="<?= $fLabel ?>">Search</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                <input id="f-q" type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Search by requester, module or reference…" class="<?= $fInput ?> pl-8">
            </div>
        </div>
    </form>

    <!-- bulk bar -->
    <?php if ($anyApprovable): ?>
        <div id="aq-bulk" class="hidden items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs text-emerald-900">
            <span><strong id="aq-bulk-count">0</strong> selected</span>
            <button type="button" id="aq-bulk-approve" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2">
                <i class="fa-solid fa-circle-check"></i> Approve selected
            </button>
        </div>
    <?php endif; ?>

    <!-- table -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-xs">
                <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-10 px-4 py-3"><input type="checkbox" id="aq-all" class="rounded border-slate-300" aria-label="Select all" <?= $anyApprovable ? '' : 'disabled' ?>></th>
                        <th class="px-3 py-3">Module</th>
                        <th class="px-3 py-3">Action</th>
                        <th class="px-3 py-3">Requester</th>
                        <th class="px-3 py-3">Scope</th>
                        <th class="px-3 py-3">Requested On</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Priority</th>
                        <th class="px-3 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (! $rows): ?>
                        <tr><td colspan="9" class="px-4 py-14 text-center">
                            <i class="fa-regular fa-circle-check mb-2 text-3xl text-slate-300"></i>
                            <p class="text-sm font-semibold text-slate-600">No requests found</p>
                            <p class="text-xs text-slate-400">Nothing matches these filters.</p>
                        </td></tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $r):
                        $rid     = (int) $r['id'];
                        $person  = aq_person_name($r);
                        [$d, $t] = aq_when($r['created_at'] ?? null);
                        $actable = ! empty($r['can_act']) && ! empty($can['approve']);
                        ?>
                        <tr class="transition-colors hover:bg-slate-50/70">
                            <td class="px-4 py-3"><input type="checkbox" class="aq-check rounded border-slate-300" value="<?= $rid ?>" <?= $actable ? '' : 'disabled' ?> aria-label="Select <?= esc($r['reference_no'], 'attr') ?>"></td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-white <?= aq_module_bg((string) $r['module_slug']) ?>"><i class="<?= esc(aq_module_icon($r['module_icon'] ?? null), 'attr') ?> text-[13px]"></i></span>
                                    <span class="font-semibold text-slate-800"><?= esc(aq_module_name((string) $r['module_slug'], $r['module_name'] ?? null)) ?></span>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-slate-700"><?= esc($r['action_label']) ?></td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white"><?= esc(aq_initials($person)) ?></span>
                                    <span>
                                        <span class="block font-semibold text-slate-800"><?= esc($person) ?></span>
                                        <?php if (! empty($r['requester_title'])): ?><span class="block text-[11px] text-slate-500"><?= esc($r['requester_title']) ?></span><?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-slate-600"><?= esc($r['scope_text'] ?? '—') ?></td>
                            <td class="px-3 py-3 text-slate-600"><span class="block"><?= esc($d) ?></span><span class="block text-[11px] text-slate-400"><?= esc($t) ?></span></td>
                            <td class="px-3 py-3"><span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-semibold <?= aq_status_class((string) $r['status']) ?>"><?= esc(aq_status_label((string) $r['status'])) ?></span></td>
                            <td class="px-3 py-3"><span class="inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-semibold <?= aq_priority_class((string) $r['priority']) ?>"><?= esc(ucfirst((string) $r['priority'])) ?></span></td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" data-aq-view="<?= $rid ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">View</button>
                                    <button type="button" data-aq-menu="<?= $rid ?>" data-ref="<?= esc($r['reference_no'], 'attr') ?>" data-actable="<?= $actable ? '1' : '0' ?>" aria-label="More actions" aria-haspopup="true" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- footer / pagination -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
            <span>Showing <?= $from ?>–<?= $to ?> of <?= number_format($total) ?> request<?= $total === 1 ? '' : 's' ?></span>
            <?php if ($pages > 1): ?>
                <nav class="flex items-center gap-1" aria-label="Pagination">
                    <?php if ($page > 1): ?><a href="?<?= esc($qs(['page' => $page - 1]), 'attr') ?>" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 bg-white hover:bg-slate-50" aria-label="Previous page"><i class="fa-solid fa-chevron-left text-[10px]"></i></a><?php endif; ?>
                    <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
                        <a href="?<?= esc($qs(['page' => $p]), 'attr') ?>" class="flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs font-semibold <?= $p === $page ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $pages): ?><a href="?<?= esc($qs(['page' => $page + 1]), 'attr') ?>" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 bg-white hover:bg-slate-50" aria-label="Next page"><i class="fa-solid fa-chevron-right text-[10px]"></i></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- row menu (one shared, fixed-position so the table never clips it) -->
<div id="aq-menu" class="fixed z-40 hidden w-44 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-xs shadow-lg" role="menu">
    <button type="button" data-m="view" class="flex w-full items-center gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50" role="menuitem"><i class="fa-regular fa-eye w-4 text-slate-400"></i> View details</button>
    <button type="button" data-m="approve" class="flex w-full items-center gap-2 px-3 py-2 text-left text-emerald-700 hover:bg-emerald-50" role="menuitem"><i class="fa-solid fa-circle-check w-4"></i> Approve</button>
    <button type="button" data-m="copy" class="flex w-full items-center gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50" role="menuitem"><i class="fa-regular fa-copy w-4 text-slate-400"></i> Copy reference</button>
</div>

<!-- request details (centered dialog) -->
<div id="aq-drawer" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="aq-drawer-title">
    <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
            <h2 id="aq-drawer-title" class="text-base font-bold text-slate-800">Request Details</h2>
            <button type="button" id="aq-close" class="flex h-8 w-8 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </header>
        <div id="aq-drawer-body" class="min-h-0 flex-1 overflow-y-auto"></div>
    </div>
</div>

<?= $this->include('approval_queue/_actions_js') ?>
<script>
(function () {
    var base      = <?= json_encode(base_url('approval-queue'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var drawer    = document.getElementById('aq-drawer');
    var body      = document.getElementById('aq-drawer-body');
    var closeBtn  = document.getElementById('aq-close');
    var menu      = document.getElementById('aq-menu');
    var lastFocus = null;
    var menuId    = null;
    var menuRef   = '';

    /* ---------- drawer ---------- */
    function openDrawer(id) {
        lastFocus = document.activeElement;
        body.innerHTML = '<p class="p-6 text-sm text-slate-500"><i class="fa-solid fa-circle-notch fa-spin mr-2"></i>Loading details…</p>';
        drawer.classList.remove('hidden'); drawer.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        closeBtn.focus();

        /* GET = silent (no loading overlay) */
        fetch(base + '/details/' + encodeURIComponent(id), { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) { body.innerHTML = html; })   /* server-rendered and escaped by the view */
            .catch(function () { body.innerHTML = '<p class="p-6 text-sm text-rose-600">Could not load the details. Please try again.</p>'; });
    }
    function closeDrawer() {
        drawer.classList.add('hidden'); drawer.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }
    closeBtn.addEventListener('click', closeDrawer);
    drawer.addEventListener('mousedown', function (e) { if (e.target === drawer) { closeDrawer(); } });   /* click on the dark background closes */

    /* ---------- row menu ---------- */
    function hideMenu() { menu.classList.add('hidden'); menuId = null; }
    document.addEventListener('click', function (e) {
        var v = e.target.closest('[data-aq-view]');
        if (v) { hideMenu(); openDrawer(v.getAttribute('data-aq-view')); return; }

        var k = e.target.closest('[data-aq-menu]');
        if (k) {
            if (menuId === k.getAttribute('data-aq-menu') && !menu.classList.contains('hidden')) { hideMenu(); return; }
            menuId  = k.getAttribute('data-aq-menu');
            menuRef = k.getAttribute('data-ref') || '';
            menu.querySelector('[data-m="approve"]').classList.toggle('hidden', k.getAttribute('data-actable') !== '1');
            var r = k.getBoundingClientRect();
            menu.classList.remove('hidden');
            var h = menu.offsetHeight;
            menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
            menu.style.top  = (r.bottom + h + 8 > window.innerHeight ? r.top - h - 4 : r.bottom + 4) + 'px';
            return;
        }

        var m = e.target.closest('#aq-menu [data-m]');
        if (m) {
            var act = m.getAttribute('data-m'), id = menuId, ref = menuRef;
            hideMenu();
            if (act === 'view') { openDrawer(id); }
            if (act === 'copy' && navigator.clipboard) { navigator.clipboard.writeText(ref); }
            if (act === 'approve' && window.confirm('Approve this request?')) {
                window.AQ.post('approve/' + id, { note: '' }).then(function (j) {
                    if (j && j.ok) { window.location.reload(); } else { window.alert((j && j.error) || 'Could not approve this request.'); }
                });
            }
            return;
        }
        if (!e.target.closest('#aq-menu')) { hideMenu(); }
    });
    window.addEventListener('scroll', hideMenu, true);
    window.addEventListener('resize', hideMenu);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (!menu.classList.contains('hidden')) { hideMenu(); return; }
            if (!drawer.classList.contains('hidden')) { closeDrawer(); }
        }
    });

    /* ---------- bulk approve ---------- */
    var all   = document.getElementById('aq-all');
    var bar   = document.getElementById('aq-bulk');
    var count = document.getElementById('aq-bulk-count');
    var apprv = document.getElementById('aq-bulk-approve');
    function checks() { return Array.prototype.slice.call(document.querySelectorAll('.aq-check:not(:disabled)')); }
    function refreshBulk() {
        if (!bar) { return; }
        var n = checks().filter(function (c) { return c.checked; }).length;
        count.textContent = n;
        bar.classList.toggle('hidden', n === 0);
        bar.classList.toggle('flex', n > 0);
        if (all) { all.checked = n > 0 && n === checks().length; }
    }
    if (all) { all.addEventListener('change', function () { checks().forEach(function (c) { c.checked = all.checked; }); refreshBulk(); }); }
    document.addEventListener('change', function (e) { if (e.target.classList && e.target.classList.contains('aq-check')) { refreshBulk(); } });
    if (apprv) {
        apprv.addEventListener('click', function () {
            var ids = checks().filter(function (c) { return c.checked; }).map(function (c) { return parseInt(c.value, 10); });
            if (!ids.length || !window.confirm('Approve ' + ids.length + ' selected request(s)?')) { return; }
            apprv.disabled = true;
            window.AQ.post('bulk-approve', { ids: ids }).then(function (j) {
                if (j && j.ok) {
                    if (j.failed && j.failed.length) { window.alert(j.approved + ' approved, ' + j.failed.length + ' could not be approved.'); }
                    window.location.reload();
                } else {
                    window.alert((j && j.error) || 'Could not approve the selected requests.');
                    apprv.disabled = false;
                }
            });
        });
    }
})();
</script>
<?= $this->endSection() ?>
